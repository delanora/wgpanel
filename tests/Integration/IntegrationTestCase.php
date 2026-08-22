<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Service\MikrotikClient;
use Tests\Unit\Service\MockTransport;

/**
 * Base class para testes de integração.
 * 
 * Configura banco de teste e fornece helpers para testes
 * que combinam MikrotikClient mock com banco real.
 */
abstract class IntegrationTestCase extends TestCase {
    
    protected static \PDO $testPdo;
    protected MockTransport $transport;
    protected MikrotikClient $mockClient;
    
    public static function setUpBeforeClass(): void {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '5432';
        $user = getenv('DB_USER') ?: 'postgres';
        $pass = getenv('DB_PASS') ?: '';
        $db = getenv('DB_NAME_TEST') ?: 'mikrotik_manager_test';
        
        self::$testPdo = new \PDO(
            "pgsql:host={$host};port={$port};dbname={$db}",
            $user,
            $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
    
    protected function setUp(): void {
        // Criar mock do MikrotikClient
        $this->transport = new MockTransport();
        $this->mockClient = new MikrotikClient(
            baseUrl: 'http://192.168.1.1',
            username: 'admin',
            password: 'test',
            timeout: 5,
            verifySsl: false,
            logEnabled: false,
            logFile: '/dev/null',
            transport: $this->transport
        );
        
        // Limpar tabelas (respeitando foreign keys)
        $this->truncateTables();
    }
    
    protected function tearDown(): void {
        $this->truncateTables();
    }
    
    /**
     * Limpa todas as tabelas de teste.
     */
    private function truncateTables(): void {
        // Fechar conexão singleton do Database para forçar reconnect ao banco de teste
        $reflection = new \ReflectionClass('\Database');
        $prop = $reflection->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        
        $tables = ['wireguard_traffic_log', 'wireguard_peers', 'wireguard_interfaces', 'sessions', 'users'];
        foreach ($tables as $table) {
            self::$testPdo->exec("TRUNCATE TABLE {$table} RESTART IDENTITY CASCADE");
        }
    }
    
    /**
     * Insere um usuário admin de teste no banco.
     */
    protected function createTestUser(array $overrides = []): int {
        $data = array_merge([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'active' => true,
        ], $overrides);
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::$testPdo->prepare("INSERT INTO users ({$columns}) VALUES ({$placeholders})")
            ->execute(array_values($data));
        
        return (int) self::$testPdo->lastInsertId();
    }
    
    /**
     * Insere uma interface WireGuard de teste.
     */
    protected function createTestInterface(array $overrides = []): int {
        $data = array_merge([
            'name' => 'wg-teste',
            'listen_port' => 13230,
            'network_cidr' => '10.10.1.0/24',
            'server_ip' => '10.10.1.1/24',
            'public_key' => 'TestPublicKey123=',
            'client_name' => 'Cliente Teste',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides);
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::$testPdo->prepare("INSERT INTO wireguard_interfaces ({$columns}) VALUES ({$placeholders})")
            ->execute(array_values($data));
        
        return (int) self::$testPdo->lastInsertId();
    }
    
    /**
     * Insere um peer de teste.
     */
    protected function createTestPeer(int $interfaceId, array $overrides = []): int {
        $data = array_merge([
            'interface_id' => $interfaceId,
            'peer_name' => 'notebook-teste',
            'public_key' => 'TestPeerPubKey=',
            'private_key' => 'TestPeerPrivKey=',
            'allowed_address' => '10.10.1.2/32',
            'contact_name' => 'João Teste',
            'contact_email' => 'joao@test.com',
            'notes' => '',
            'additional_routes' => '',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides);
        
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        self::$testPdo->prepare("INSERT INTO wireguard_peers ({$columns}) VALUES ({$placeholders})")
            ->execute(array_values($data));
        
        return (int) self::$testPdo->lastInsertId();
    }
    
    /**
     * Busca uma interface pelo nome.
     */
    protected function getInterface(string $name): ?array {
        $stmt = self::$testPdo->prepare("SELECT * FROM wireguard_interfaces WHERE name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Busca um peer pelo nome.
     */
    protected function getPeer(string $name): ?array {
        $stmt = self::$testPdo->prepare("SELECT * FROM wireguard_peers WHERE peer_name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Conta registros em uma tabela.
     */
    protected function countRows(string $table, string $where = '1=1', array $params = []): int {
        $stmt = self::$testPdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
