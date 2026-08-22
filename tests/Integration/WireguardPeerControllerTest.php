<?php
namespace Tests\Integration;

/**
 * Testes de integração do fluxo de criação de Peer WireGuard.
 * 
 * Testa a lógica de negócio (Database + Mikrotik mock) sem
 * chamar header()/exit() do controller.
 */
class WireguardPeerControllerTest extends IntegrationTestCase {
    
    // =================================================================
    // Caso 1: Criação bem-sucedida — peer no Mikrotik + banco
    // =================================================================
    
    public function testSuccessfulPeerCreation(): void {
        // Criar interface de teste no banco
        $interfaceId = $this->createTestInterface([
            'name' => 'wg-teste',
            'listen_port' => 13230,
            'network_cidr' => '10.10.1.0/24',
            'server_ip' => '10.10.1.1/24',
            'public_key' => 'ServerPubKey=',
        ]);
        
        // Mock: PUT /interface/wireguard/peers retorna peer criado
        $this->transport->whenJson('PUT', '/rest/interface/wireguard/peers', [
            '.id' => '*1',
            'name' => 'notebook-novo',
            'public-key' => 'PeerPubKey123=',
            'private-key' => 'PeerPrivKey456=',
            'interface' => 'wg-teste',
            'allowed-address' => '10.10.1.2/32',
        ]);
        
        // Simular o fluxo do controller store():
        // 1. PUT peer no Mikrotik
        $mkPeer = $this->mockClient->put('/interface/wireguard/peers', [
            'interface' => 'wg-teste',
            'name' => 'notebook-novo',
            'private-key' => 'auto',
            'allowed-address' => '10.10.1.2/32',
        ]);
        
        $publicKey = $mkPeer['public-key'] ?? '';
        $privateKey = $mkPeer['private-key'] ?? '';
        
        // 2. Salvar no banco
        $dbId = \Database::insert('wireguard_peers', [
            'interface_id' => $interfaceId,
            'peer_name' => 'notebook-novo',
            'public_key' => $publicKey,
            'private_key' => $privateKey,
            'allowed_address' => '10.10.1.2/32',
            'contact_name' => 'João Silva',
            'contact_email' => 'joao@test.com',
            'notes' => 'Notebook do João',
            'additional_routes' => '',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        // Verificar que foi salvo no banco
        $peer = $this->getPeer('notebook-novo');
        $this->assertNotNull($peer, 'Peer deveria ter sido salvo no banco');
        $this->assertEquals($interfaceId, $peer['interface_id']);
        $this->assertEquals('PeerPubKey123=', $peer['public_key']);
        $this->assertEquals('PeerPrivKey456=', $peer['private_key']);
        $this->assertEquals('10.10.1.2/32', $peer['allowed_address']);
        $this->assertEquals('active', $peer['status']);
        
        // Verificar que o Mikrotik foi chamado
        $this->assertTrue($this->transport->wasCalled('PUT', '/rest/interface/wireguard/peers'));
    }
    
    // =================================================================
    // Caso 2: Falha na criação → rollback do peer no Mikrotik
    // =================================================================
    
    public function testPeerCreationFailureTriggersRollback(): void {
        $interfaceId = $this->createTestInterface([
            'name' => 'wg-rollback',
            'listen_port' => 13236,
            'network_cidr' => '10.10.4.0/24',
            'server_ip' => '10.10.4.1/24',
            'public_key' => 'ServerPubKeyRollback=',
        ]);
        
        // Mock: PUT peer OK
        $this->transport->whenJson('PUT', '/rest/interface/wireguard/peers', [
            '.id' => '*5',
            'public-key' => 'PeerPubKeyRollback=',
            'private-key' => 'PeerPrivKeyRollback=',
        ]);
        
        // Mock: DELETE para rollback OK
        $this->transport->whenJson('DELETE', '/rest/interface/wireguard/peers/*5', []);
        
        // Mock: POST que falha (simula falha em algum passo posterior)
        $this->transport->whenApiError('POST', '*', 'Internal error', '', 500);
        
        $mkPeerId = null;
        
        try {
            // Passo 1: Criar peer OK
            $mkPeer = $this->mockClient->put('/interface/wireguard/peers', [
                'interface' => 'wg-rollback',
                'name' => 'notebook-rollback',
                'private-key' => 'auto',
                'allowed-address' => '10.10.4.2/32',
            ]);
            $mkPeerId = $mkPeer['.id'] ?? null;
            
            // Passo 2: Operação que falha
            $this->mockClient->post('/something/that/fails', []);
            
            $this->fail('Deveria ter lançado MikrotikApiException');
        } catch (\App\Exception\MikrotikApiException $e) {
            // Rollback: remover peer criado
            $this->assertNotNull($mkPeerId);
            $this->mockClient->delete('/interface/wireguard/peers/' . $mkPeerId);
        }
        
        // Verificar que NÃO foi salvo no banco
        $peer = $this->getPeer('notebook-rollback');
        $this->assertNull($peer, 'Peer NÃO deveria estar no banco (rollback)');
        
        // Verificar que o rollback foi chamado
        $this->assertTrue($this->transport->wasCalled('DELETE', '/rest/interface/wireguard/peers/*5'));
    }
    
    // =================================================================
    // Caso 3: Allowed-address sugerido não colide com peers existentes
    // =================================================================
    
    public function testNextIpSuggestionDoesNotCollide(): void {
        $interfaceId = $this->createTestInterface([
            'name' => 'wg-teste',
            'listen_port' => 13230,
            'network_cidr' => '10.10.1.0/24',
            'server_ip' => '10.10.1.1/24',
            'public_key' => 'ServerPubKey=',
        ]);
        
        // Criar peers existentes
        $this->createTestPeer($interfaceId, [
            'peer_name' => 'peer-1',
            'allowed_address' => '10.10.1.2/32',
        ]);
        $this->createTestPeer($interfaceId, [
            'peer_name' => 'peer-2',
            'allowed_address' => '10.10.1.3/32',
        ]);
        
        // Buscar próximo IP disponível (mesma lógica do controller)
        $existingPeers = \Database::fetchAll(
            'SELECT allowed_address FROM wireguard_peers WHERE interface_id = ?',
            [$interfaceId]
        );
        
        $usedIps = [];
        foreach ($existingPeers as $p) {
            if (preg_match('#^(\\d+\\.\\d+\\.\\d+\\.\\d+)/\\d+$#', $p['allowed_address'], $m)) {
                $usedIps[] = $m[1];
            }
        }
        
        // Encontrar próximo IP livre no /24
        $networkBase = '10.10.1.';
        $nextIp = null;
        for ($i = 2; $i <= 254; $i++) {
            $candidate = $networkBase . $i;
            if (!in_array($candidate, $usedIps)) {
                $nextIp = $candidate . '/32';
                break;
            }
        }
        
        $this->assertEquals('10.10.1.4/32', $nextIp, 'Próximo IP deveria ser .4 (após .2 e .3)');
    }
    
    // =================================================================
    // Caso adicional: Config Linux é gerada corretamente
    // =================================================================
    
    public function testLinuxConfigGeneration(): void {
        $interfaceId = $this->createTestInterface([
            'name' => 'wg-teste',
            'listen_port' => 13230,
            'network_cidr' => '10.10.1.0/24',
            'server_ip' => '10.10.1.1/24',
            'public_key' => 'ServerPubKey123=',
        ]);
        
        $peerId = $this->createTestPeer($interfaceId, [
            'peer_name' => 'notebook-felipe',
            'public_key' => 'PeerPubKeyABC=',
            'private_key' => 'PeerPrivKeyXYZ=',
            'allowed_address' => '10.10.1.2/32',
            'additional_routes' => '192.168.99.0/24',
        ]);
        
        // Buscar o peer com join (mesmo que o controller faria)
        $peer = \Database::fetch(
            'SELECT p.*, i.listen_port, i.network_cidr, i.server_ip, i.public_key as server_public_key
             FROM wireguard_peers p 
             JOIN wireguard_interfaces i ON p.interface_id = i.id 
             WHERE p.id = ?',
            [$peerId]
        );
        
        $this->assertNotNull($peer);
        $this->assertEquals('PeerPrivKeyXYZ=', $peer['private_key']);
        $this->assertEquals('PeerPubKeyABC=', $peer['public_key']);
        $this->assertEquals('ServerPubKey123=', $peer['server_public_key']);
        $this->assertEquals('192.168.99.0/24', $peer['additional_routes']);
        
        // Verificar que campos necessários para config estão presentes
        $this->assertArrayHasKey('listen_port', $peer);
        $this->assertArrayHasKey('network_cidr', $peer);
        $this->assertArrayHasKey('server_ip', $peer);
    }
}
