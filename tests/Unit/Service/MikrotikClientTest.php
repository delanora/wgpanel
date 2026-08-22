<?php
namespace Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use App\Service\MikrotikClient;
use App\Exception\MikrotikApiException;

/**
 * Testes unitários do MikrotikClient.
 * 
 * Usa MockTransport para simular respostas HTTP sem bater no Mikrotik real.
 */
class MikrotikClientTest extends TestCase {
    
    private MockTransport $transport;
    private MikrotikClient $client;
    
    protected function setUp(): void {
        $this->transport = new MockTransport();
        $this->client = new MikrotikClient(
            baseUrl: 'http://192.168.1.1',
            username: 'admin',
            password: 'test123',
            timeout: 5,
            verifySsl: false,
            logEnabled: false,
            logFile: '/dev/null',
            transport: $this->transport
        );
    }
    
    // =================================================================
    // Caso 1: GET bem-sucedido retorna array decodificado
    // =================================================================
    
    public function testGetReturnsDecodedJson(): void {
        $expected = [
            ['name' => 'ether1', 'type' => 'ethernet', 'status' => 'running'],
            ['name' => 'wg0', 'type' => 'wireguard', 'status' => 'running'],
        ];
        
        $this->transport->whenJson('GET', '/rest/interface', $expected);
        
        $result = $this->client->get('/interface');
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('ether1', $result[0]['name']);
        $this->assertEquals('wireguard', $result[1]['type']);
    }
    
    public function testGetWithQueryPassesQueryString(): void {
        $expected = [['name' => 'wg0', 'disabled' => 'false']];
        
        $this->transport->whenJson('GET', '/rest/interface/wireguard', $expected);
        
        $result = $this->client->get('/interface/wireguard', ['disabled' => 'false']);
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        
        // Verificar que a query string foi passada
        $call = $this->transport->getLastCall();
        $this->assertStringContainsString('disabled=false', $call['url']);
    }
    
    // =================================================================
    // Caso 2: POST envia body correto como JSON e método correto
    // =================================================================
    
    public function testPutSendsCorrectBody(): void {
        // A API Mikrotik retorna um objeto flat (não array de objetos)
        $response = ['.id' => '*1', 'name' => 'wg-teste', 'listen-port' => '13230'];
        
        $this->transport->whenJson('PUT', '/rest/interface/wireguard', $response);
        
        $body = ['name' => 'wg-teste', 'listen-port' => '13230'];
        $result = $this->client->put('/interface/wireguard', $body);
        
        $this->assertIsArray($result);
        $this->assertEquals('wg-teste', $result['name']);
        
        // Verificar que o body foi enviado como JSON
        $call = $this->transport->getLastCall();
        $this->assertEquals('PUT', $call['method']);
        $this->assertJson($call['options']['body'] ?? '');
        $decoded = json_decode($call['options']['body'], true);
        $this->assertEquals('wg-teste', $decoded['name']);
    }
    
    public function testPostCreatesResource(): void {
        $response = ['.id' => '*1', 'public-key' => 'abc123'];
        
        $this->transport->whenJson('POST', '/rest/interface/wireguard/peers', $response);
        
        $result = $this->client->post('/interface/wireguard/peers', [
            'private-key' => 'auto',
            'interface' => 'wg0',
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals('abc123', $result['public-key']);
        
        $call = $this->transport->getLastCall();
        $this->assertEquals('POST', $call['method']);
    }
    
    // =================================================================
    // Caso 3: Resposta com status >= 400 lança MikrotikApiException
    // =================================================================
    
    public function testApiErrorThrowsException(): void {
        $this->transport->whenApiError('GET', '/rest/system/identity', 'Unauthorized', '', 401);
        
        $this->expectException(MikrotikApiException::class);
        $this->expectExceptionMessage('Unauthorized');
        
        $this->client->get('/system/identity');
    }
    
    public function testApiErrorContainsDetails(): void {
        $this->transport->whenApiError('PUT', '/rest/interface/wireguard', 'already have interface with this name', 'Interface already exists', 409);
        
        try {
            $this->client->put('/interface/wireguard', ['name' => 'wg-dup']);
            $this->fail('Deveria ter lançado MikrotikApiException');
        } catch (MikrotikApiException $e) {
            $this->assertEquals('already have interface with this name', $e->getMessage());
            $this->assertEquals(409, $e->getHttpStatus());
            $this->assertEquals('Interface already exists', $e->getDetail());
            $this->assertStringContainsString('/rest/interface/wireguard', $e->getEndpoint());
            $this->assertEquals('PUT', $e->getMethod());
        }
    }
    
    // =================================================================
    // Caso 4: Timeout de conexão lança exceção tratável
    // =================================================================
    
    public function testConnectionErrorThrowsException(): void {
        $this->transport->whenConnectionError('*', '*', 'Connection timed out after 5 seconds', 28);
        
        $this->expectException(MikrotikApiException::class);
        $this->expectExceptionMessage('Erro de conexão com a API Mikrotik');
        
        $this->client->get('/system/resource');
    }
    
    public function testConnectionErrorContainsCurlDetails(): void {
        $this->transport->whenConnectionError('*', '*', 'Could not resolve host: 192.168.1.1', 6);
        
        try {
            $this->client->get('/system/identity');
            $this->fail('Deveria ter lançado MikrotikApiException');
        } catch (MikrotikApiException $e) {
            $this->assertStringContainsString('conexão', $e->getMessage());
            $this->assertEquals(6, $e->getApiErrorCode());
            $this->assertEquals(0, $e->getHttpStatus()); // Sem HTTP status em erro de conexão
        }
    }
    
    // =================================================================
    // Caso 5: Uso correto de .proplist e .query
    // =================================================================
    
    public function testPostWithProplistAndQuery(): void {
        $response = ['.id' => '*1', 'address' => '10.10.1.1/24', 'interface' => 'wg0'];
        
        $this->transport->whenJson('POST', '/rest/ip/address', $response);
        
        $result = $this->client->post('/ip/address', [
            '.proplist' => 'address,interface',
            '.query' => ['interface' => 'wg0'],
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals('10.10.1.1/24', $result['address']);
        
        // Verificar que .proplist e .query foram enviados no body
        $call = $this->transport->getLastCall();
        $decoded = json_decode($call['options']['body'], true);
        $this->assertEquals('address,interface', $decoded['.proplist']);
        $this->assertEquals(['interface' => 'wg0'], $decoded['.query']);
    }
    
    // =================================================================
    // Testes adicionais: Request log e autenticação
    // =================================================================
    
    public function testRequestLogTracksCalls(): void {
        $this->transport->whenJson('GET', '/rest/system/identity', ['name' => 'MikroTik']);
        
        $this->client->get('/system/identity');
        
        $log = $this->client->getRequestLog();
        $this->assertCount(1, $log);
        $this->assertEquals('GET', $log[0]['method']);
        $this->assertStringContainsString('/rest/system/identity', $log[0]['url']);
    }
    
    public function testAuthenticationHeaderIsSent(): void {
        $this->transport->whenJson('GET', '/rest/system/identity', ['name' => 'MikroTik']);
        
        $this->client->get('/system/identity');
        
        $call = $this->transport->getLastCall();
        $this->assertEquals('admin', $call['options']['username']);
        $this->assertEquals('test123', $call['options']['password']);
    }
    
    public function testDeleteMethodWorks(): void {
        $this->transport->whenJson('DELETE', '/rest/interface/wireguard/*1', []);
        
        $result = $this->client->delete('/interface/wireguard/*1');
        
        $this->assertIsArray($result);
        $call = $this->transport->getLastCall();
        $this->assertEquals('DELETE', $call['method']);
    }
    
    public function testFromEnvCreatesInstance(): void {
        putenv('MIKROTIK_API_URL=http://test.local');
        putenv('MIKROTIK_USER=testuser');
        putenv('MIKROTIK_PASS=testpass');
        putenv('MIKROTIK_TIMEOUT=15');
        putenv('MIKROTIK_VERIFY_SSL=true');
        putenv('MIKROTIK_LOG_ENABLED=false');
        putenv('MIKROTIK_LOG_FILE=/tmp/test.log');
        
        $client = MikrotikClient::fromEnv(new MockTransport());
        
        $this->assertInstanceOf(MikrotikClient::class, $client);
        
        // Cleanup
        putenv('MIKROTIK_API_URL');
        putenv('MIKROTIK_USER');
        putenv('MIKROTIK_PASS');
        putenv('MIKROTIK_TIMEOUT');
        putenv('MIKROTIK_VERIFY_SSL');
        putenv('MIKROTIK_LOG_ENABLED');
        putenv('MIKROTIK_LOG_FILE');
    }
}
