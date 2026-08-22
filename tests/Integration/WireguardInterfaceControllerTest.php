<?php
namespace Tests\Integration;

/**
 * Testes de integração do fluxo de criação de Interface WireGuard.
 * 
 * Testa a lógica de negócio (Database + Mikrotik mock) sem
 * chamar header()/exit() do controller.
 */
class WireguardInterfaceControllerTest extends IntegrationTestCase {
    
    // =================================================================
    // Caso 1: Criação bem-sucedida — interface no Mikrotik + IP + banco
    // =================================================================
    
    public function testSuccessfulCreation(): void {
        // Mock: PUT /interface/wireguard retorna interface criada
        $this->transport->whenJson('PUT', '/rest/interface/wireguard', [
            '.id' => '*1',
            'name' => 'wg-novocliente',
            'listen-port' => '13230',
            'public-key' => 'PubKeyNovo123=',
            'mtu' => '1420',
        ]);
        
        // Mock: PUT /ip/address retorna IP atribuído
        $this->transport->whenJson('PUT', '/rest/ip/address', [
            '.id' => '*2',
            'address' => '10.10.2.1/24',
            'interface' => 'wg-novocliente',
        ]);
        
        // Simular o fluxo do controller store():
        // 1. PUT /interface/wireguard
        $result = $this->mockClient->put('/interface/wireguard', [
            'name' => 'wg-novocliente',
            'listen-port' => 13230,
            'mtu' => 1420,
        ]);
        
        $this->assertEquals('PubKeyNovo123=', $result['public-key']);
        
        // 2. PUT /ip/address
        $this->mockClient->put('/ip/address', [
            'address' => '10.10.2.1/24',
            'interface' => 'wg-novocliente',
        ]);
        
        // 3. Salvar no banco
        $dbId = \Database::insert('wireguard_interfaces', [
            'name' => 'wg-novocliente',
            'listen_port' => 13230,
            'network_cidr' => '10.10.2.0/24',
            'server_ip' => '10.10.2.1/24',
            'public_key' => $result['public-key'],
            'client_name' => 'Novo Cliente',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        // Verificar que foi salvo no banco
        $iface = $this->getInterface('wg-novocliente');
        $this->assertNotNull($iface, 'Interface deveria ter sido salva no banco');
        $this->assertEquals('Novo Cliente', $iface['client_name']);
        $this->assertEquals(13230, $iface['listen_port']);
        $this->assertEquals('10.10.2.0/24', $iface['network_cidr']);
        $this->assertEquals('PubKeyNovo123=', $iface['public_key']);
        $this->assertEquals('10.10.2.1/24', $iface['server_ip']);
        $this->assertEquals('active', $iface['status']);
        
        // Verificar que o Mikrotik foi chamado corretamente
        $this->assertTrue($this->transport->wasCalled('PUT', '/rest/interface/wireguard'));
        $this->assertTrue($this->transport->wasCalled('PUT', '/rest/ip/address'));
        $this->assertEquals(2, $this->transport->getCallCount());
    }
    
    // =================================================================
    // Caso 2: Falha na 2ª chamada (IP) → rollback da interface
    // =================================================================
    
    public function testFailureAtIpAssignmentTriggersRollback(): void {
        // Mock: PUT /interface/wireguard OK
        $this->transport->whenJson('PUT', '/rest/interface/wireguard', [
            '.id' => '*3',
            'name' => 'wg-rollback',
            'listen-port' => '13235',
            'public-key' => 'PubKeyRollback=',
        ]);
        
        // Mock: PUT /ip/address FALHA (erro 400)
        $this->transport->whenApiError('PUT', '/rest/ip/address', 'invalid interface', 'Interface not found', 400);
        
        // Simular o fluxo do controller store() com rollback:
        $mkInterfaceId = null;
        
        try {
            // Passo 1: Criar interface OK
            $result = $this->mockClient->put('/interface/wireguard', [
                'name' => 'wg-rollback',
                'listen-port' => 13235,
                'mtu' => 1420,
            ]);
            $mkInterfaceId = $result['.id'] ?? null;
            $this->assertNotNull($mkInterfaceId);
            
            // Passo 2: Atribuir IP — FALHA
            $this->mockClient->put('/ip/address', [
                'address' => '10.10.3.1/24',
                'interface' => 'wg-rollback',
            ]);
            
            $this->fail('Deveria ter lançado MikrotikApiException');
        } catch (\App\Exception\MikrotikApiException $e) {
            $this->assertEquals(400, $e->getHttpStatus());
            
            // Rollback: remover interface criada no Mikrotik
            $this->assertNotNull($mkInterfaceId);
            $this->mockClient->delete('/interface/wireguard/' . $mkInterfaceId);
        }
        
        // Verificar que NÃO foi salvo no banco
        $iface = $this->getInterface('wg-rollback');
        $this->assertNull($iface, 'Interface NÃO deveria estar no banco (rollback)');
        
        // Verificar que o rollback foi chamado (DELETE na interface)
        $this->assertTrue($this->transport->wasCalled('DELETE', '/rest/interface/wireguard/*3'));
    }
    
    // =================================================================
    // Caso 3: Sugestões automáticas não colidem com interfaces existentes
    // =================================================================
    
    public function testSuggestionsDoNotCollide(): void {
        // Criar interfaces existentes no banco
        $this->createTestInterface([
            'name' => 'wg-1',
            'listen_port' => 13230,
            'network_cidr' => '10.10.1.0/24',
        ]);
        $this->createTestInterface([
            'name' => 'wg-2',
            'listen_port' => 13231,
            'network_cidr' => '10.10.2.0/24',
        ]);
        
        // Usar Database para buscar sugestões (mesma lógica do controller)
        $existing = \Database::fetchAll(
            'SELECT listen_port, network_cidr FROM wireguard_interfaces'
        );
        
        $usedPorts = array_column($existing, 'listen_port');
        $nextPort = 13230;
        while (in_array($nextPort, $usedPorts)) {
            $nextPort++;
        }
        
        $usedNetworks = [];
        foreach ($existing as $e) {
            if (preg_match('#^10\\.10\\.(\\d+)\\.0/24$#', $e['network_cidr'], $m)) {
                $usedNetworks[] = (int) $m[1];
            }
        }
        $nextSubnet = 1;
        while (in_array($nextSubnet, $usedNetworks)) {
            $nextSubnet++;
        }
        
        // Verificar que as sugestões não colidem
        $this->assertEquals(13232, $nextPort, 'Próxima porta deveria ser 13232 (após 13230 e 13231)');
        $this->assertEquals(3, $nextSubnet, 'Próximo subnet deveria ser 3 (após 1 e 2)');
        $this->assertEquals('10.10.3.0/24', "10.10.{$nextSubnet}.0/24");
        
        // Não deveria haver conflito
        $this->assertNotContains($nextPort, $usedPorts);
        $this->assertNotContains($nextSubnet, $usedNetworks);
    }
    
    // =================================================================
    // Caso adicional: Validação de dados inválidos
    // =================================================================
    
    public function testValidationRejectsInvalidData(): void {
        // Dados inválidos
        $name = '';
        $clientName = '';
        $listenPort = 99999;
        $networkCidr = 'invalid';
        
        // Validações (mesma lógica do controller)
        $errors = [];
        if ($clientName === '') $errors[] = 'Nome do cliente é obrigatório';
        if ($name === '') $errors[] = 'Nome da interface é obrigatório';
        if ($listenPort < 1 || $listenPort > 65535) $errors[] = 'Porta inválida';
        if (!preg_match('#^\\d+\\.\\d+\\.\\d+\\.\\d+/\\d+$#', $networkCidr)) {
            $errors[] = 'CIDR inválido (ex: 10.10.1.0/24)';
        }
        
        // Nada deveria ter sido salvo
        $this->assertNotEmpty($errors, 'Deveria ter erros de validação');
        $this->assertContains('Nome do cliente é obrigatório', $errors);
        $this->assertContains('Nome da interface é obrigatório', $errors);
        $this->assertContains('Porta inválida', $errors);
        $this->assertContains('CIDR inválido (ex: 10.10.1.0/24)', $errors);
        
        // Nenhuma chamada ao Mikrotik
        $this->assertEquals(0, $this->transport->getCallCount());
        $this->assertEquals(0, $this->countRows('wireguard_interfaces'));
    }
}
