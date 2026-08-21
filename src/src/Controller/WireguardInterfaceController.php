<?php
namespace App\Controller;

use App\Service\MikrotikClient;
use App\Exception\MikrotikApiException;

class WireguardInterfaceController {
    
    private MikrotikClient $client;
    
    /** @var array Parâmetros da rota injetados pelo Router */
    public array $_routeParams = [];
    
    public function __construct() {
        $this->client = MikrotikClient::fromEnv();
    }
    
    /**
     * Lista todas as interfaces WireGuard do banco + status ao vivo do Mikrotik.
     */
    public function index(): void {
        $interfaces = \Database::fetchAll(
            'SELECT * FROM wireguard_interfaces ORDER BY created_at DESC'
        );
        
        // Buscar status ao vivo do Mikrotik
        $mikrotikInterfaces = [];
        try {
            $wgInterfaces = $this->client->get('/interface/wireguard');
            if (is_array($wgInterfaces)) {
                foreach ($wgInterfaces as $iface) {
                    $mikrotikInterfaces[$iface['name']] = $iface;
                }
            }
        } catch (\Exception $e) {
            // Ignorar erro - mostrar apenas dados do banco
        }
        
        // Buscar contagem de peers por interface
        $peerCounts = [];
        $peerCountsRaw = \Database::fetchAll(
            'SELECT interface_id, COUNT(*) as total, 
                    COUNT(*) FILTER (WHERE status = \'active\') as active_count 
             FROM wireguard_peers GROUP BY interface_id'
        );
        foreach ($peerCountsRaw as $pc) {
            $peerCounts[$pc['interface_id']] = $pc;
        }
        
        // Enriquecer dados
        foreach ($interfaces as &$iface) {
            $mk = $mikrotikInterfaces[$iface['name']] ?? null;
            $disabled = $mk['disabled'] ?? false;
            $iface['running'] = $mk !== null && ($disabled === false || $disabled === 'false' || $disabled === 0);
            $iface['running_at_time'] = $mk['running-time'] ?? null;
            $iface['mk_id'] = $mk['.id'] ?? null;
            $iface['peer_count'] = $peerCounts[$iface['id']]['total'] ?? 0;
            $iface['peer_active_count'] = $peerCounts[$iface['id']]['active_count'] ?? 0;
        }
        unset($iface);
        
        require __DIR__ . '/../../views/wireguard/index.php';
    }
    
    /**
     * Formulário de criação de interface.
     * Sugere porta e CIDR automáticos.
     */
    public function create(): void {
        $suggestions = $this->getSuggestions();
        
        require __DIR__ . '/../../views/wireguard/create.php';
    }
    
    /**
     * Processa a criação da interface WireGuard.
     * 
     * Fluxo:
     * 1. PUT /rest/interface/wireguard → cria a interface
     * 2. PUT /rest/ip/address → atribui IP do servidor
     * 3. Salva no banco de dados
     * 4. Se falhar em qualquer etapa → rollback
     */
    public function store(): void {
        $name = trim($_POST['name'] ?? '');
        $clientName = trim($_POST['client_name'] ?? '');
        $listenPort = (int) ($_POST['listen_port'] ?? 13230);
        $networkCidr = trim($_POST['network_cidr'] ?? '');
        
        // Validações
        $errors = [];
        if ($clientName === '') $errors[] = 'Nome do cliente é obrigatório';
        if ($name === '') $errors[] = 'Nome da interface é obrigatório';
        if ($listenPort < 1 || $listenPort > 65535) $errors[] = 'Porta inválida';
        if (!preg_match('#^\d+\.\d+\.\d+\.\d+/\d+$#', $networkCidr)) {
            $errors[] = 'CIDR inválido (ex: 10.10.1.0/24)';
        }
        
        if (!empty($errors)) {
            $suggestions = $this->getSuggestions();
            $error = implode('<br>', $errors);
            require __DIR__ . '/../../views/wireguard/create.php';
            return;
        }
        
        // Extrair IPs do CIDR
        $cidrParts = explode('/', $networkCidr);
        $networkBase = $cidrParts[0];
        $subnetMask = (int) $cidrParts[1];
        
        // IP do servidor = primeiro IP da rede (network + 1)
        $serverIp = $this->incrementIp($networkBase) . '/' . $subnetMask;
        
        // Gerar chave privada/pubica no Mikrotik
        $mkInterfaceId = null;
        
        try {
            // Passo 1: Criar interface no Mikrotik
            $result = $this->client->put('/interface/wireguard', [
                'name' => $name,
                'listen-port' => $listenPort,
                'mtu' => 1420,
            ]);
            
            $mkInterfaceId = $result['.id'] ?? null;
            $publicKey = $result['public-key'] ?? '';
            
            if (!$publicKey) {
                throw new \Exception('Mikrotik não retornou public-key');
            }
            
            // Passo 2: Atribuir IP do servidor
            $this->client->put('/ip/address', [
                'address' => $serverIp,
                'interface' => $name,
            ]);
            
            // Passo 3: Salvar no banco
            $dbId = \Database::insert('wireguard_interfaces', [
                'name' => $name,
                'listen_port' => $listenPort,
                'network_cidr' => $networkCidr,
                'server_ip' => $serverIp,
                'public_key' => $publicKey,
                'client_name' => $clientName,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
            header('Location: /wireguard?success=created');
            exit;
            
        } catch (MikrotikApiException $e) {
            // Rollback: remover interface criada no Mikrotik
            $this->rollback($mkInterfaceId, $name);
            $error = 'Erro Mikrotik: ' . $e->getUserMessage();
            $suggestions = $this->getSuggestions();
            require __DIR__ . '/../../views/wireguard/create.php';
            
        } catch (\Exception $e) {
            $this->rollback($mkInterfaceId, $name);
            $error = 'Erro: ' . $e->getMessage();
            $suggestions = $this->getSuggestions();
            require __DIR__ . '/../../views/wireguard/create.php';
        }
    }
    
    /**
     * Formulário de edição (nome do cliente, status).
     * NÃO permite editar CIDR (para evitar conflito com peers).
     */
    public function edit(): void {
        $id = $this->getRouteParam('id');
        $interface = \Database::fetch('SELECT * FROM wireguard_interfaces WHERE id = ?', [$id]);
        
        if (!$interface) {
            header('Location: /wireguard');
            exit;
        }
        
        // Status no Mikrotik
        $running = false;
        try {
            $mkIface = $this->client->get('/interface/wireguard', ['name' => $interface['name']]);
            if (!empty($mkIface)) {
                $running = !($mkIface[0]['disabled'] ?? false);
            }
        } catch (\Exception $e) { /* ignorar */ }
        
        $interface['running'] = $running;
        
        require __DIR__ . '/../../views/wireguard/edit.php';
    }
    
    /**
     * Processa edição (apenas cliente_name e status).
     */
    public function update(): void {
        $id = $this->getRouteParam('id');
        $clientName = trim($_POST['client_name'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'disabled';
        
        $interface = \Database::fetch('SELECT * FROM wireguard_interfaces WHERE id = ?', [$id]);
        if (!$interface) {
            header('Location: /wireguard');
            exit;
        }
        
        try {
            // Sincronizar status no Mikrotik
            $mkIface = $this->client->get('/interface/wireguard', ['name' => $interface['name']]);
            if (!empty($mkIface)) {
                $mkId = $mkIface[0]['.id'] ?? null;
                if ($mkId) {
                    $this->client->patch('/interface/wireguard/' . $mkId, [
                        'disabled' => $status === 'disabled',
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Continuar mesmo se o Mikrotik falhar - salvar no banco
        }
        
        \Database::update('wireguard_interfaces', [
            'client_name' => $clientName,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);
        
        header('Location: /wireguard?success=updated');
        exit;
    }
    
    /**
     * Exclui interface WireGuard:
     * 1. Remove peers do Mikrotik
     * 2. Remove IP address do Mikrotik
     * 3. Remove interface do Mikrotik
     * 4. Remove do banco (cascade peers)
     */
    public function delete(): void {
        $id = $this->getRouteParam('id');
        $interface = \Database::fetch('SELECT * FROM wireguard_interfaces WHERE id = ?', [$id]);
        
        if (!$interface) {
            header('Location: /wireguard');
            exit;
        }
        
        $interfaceName = $interface['name'];
        
        try {
            // 1. Remover peers da interface no Mikrotik
            $peers = $this->client->get('/interface/wireguard/peers', [
                'interface' => $interfaceName,
            ]);
            if (!empty($peers) && is_array($peers)) {
                foreach ($peers as $peer) {
                    $peerId = $peer['.id'] ?? null;
                    if ($peerId) {
                        try {
                            $this->client->delete('/interface/wireguard/peers/' . $peerId);
                        } catch (\Exception $e) { /* peer pode já ter sido removido */ }
                    }
                }
            }
            
            // 2. Remover IP addresses associados
            $addresses = $this->client->get('/ip/address', [
                'interface' => $interfaceName,
            ]);
            if (!empty($addresses) && is_array($addresses)) {
                foreach ($addresses as $addr) {
                    $addrId = $addr['.id'] ?? null;
                    if ($addrId) {
                        try {
                            $this->client->delete('/ip/address/' . $addrId);
                        } catch (\Exception $e) { /* ignorar */ }
                    }
                }
            }
            
            // 3. Remover interface
            $mkIface = $this->client->get('/interface/wireguard', ['name' => $interfaceName]);
            if (!empty($mkIface)) {
                $mkId = $mkIface[0]['.id'] ?? null;
                if ($mkId) {
                    $this->client->delete('/interface/wireguard/' . $mkId);
                }
            }
            
        } catch (\Exception $e) {
            // Log mas continuar - remover do banco mesmo se Mikrotik falhar
            error_log("WireGuard delete Mikrotik error: " . $e->getMessage());
        }
        
        // 4. Remover do banco (CASCADE remove peers automaticamente)
        \Database::delete('wireguard_interfaces', 'id = ?', [$id]);
        
        header('Location: /wireguard?success=deleted');
        exit;
    }
    
    // =====================================================================
    // Métodos auxiliares
    // =====================================================================
    
    /**
     * Gera sugestões automáticas de porta e CIDR.
     */
    private function getSuggestions(): array {
        $existing = \Database::fetchAll('SELECT listen_port, network_cidr FROM wireguard_interfaces');
        
        // Próxima porta livre (acima de 13230)
        $usedPorts = array_column($existing, 'listen_port');
        $nextPort = 13230;
        while (in_array($nextPort, $usedPorts)) {
            $nextPort++;
        }
        
        // Próximo /24 livre (base 10.10.X.0)
        $usedNetworks = [];
        foreach ($existing as $e) {
            if (preg_match('#^10\.10\.(\d+)\.0/24$#', $e['network_cidr'], $m)) {
                $usedNetworks[] = (int) $m[1];
            }
        }
        $nextSubnet = 1;
        while (in_array($nextSubnet, $usedNetworks)) {
            $nextSubnet++;
        }
        $suggestedCidr = "10.10.{$nextSubnet}.0/24";
        
        return [
            'next_port' => $nextPort,
            'next_cidr' => $suggestedCidr,
            'used_ports' => $usedPorts,
            'used_networks' => $usedNetworks,
        ];
    }
    
    /**
     * Incrementa um IP em 1 (ex: 10.10.1.0 → 10.10.1.1)
     */
    private function incrementIp(string $ip, int $increment = 1): string {
        $parts = explode('.', $ip);
        $parts[3] = (int) $parts[3] + $increment;
        
        // Propagar carry
        for ($i = 3; $i > 0; $i--) {
            if ($parts[$i] > 255) {
                $parts[$i] -= 256;
                $parts[$i - 1]++;
            }
        }
        
        return implode('.', $parts);
    }
    
    /**
     * Rollback: remove interface Mikrotik se foi criada antes de falhar.
     */
    private function rollback(?string $mkId, string $name): void {
        if ($mkId) {
            try {
                $this->client->delete('/interface/wireguard/' . $mkId);
            } catch (\Exception $e) {
                error_log("WireGuard rollback failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Extrai parâmetro da URL (simplificado).
     */
    private function getRouteParam(string $key): string {
        // Prioridade: parâmetros injetados pelo Router
        if (!empty($this->_routeParams[$key])) {
            return $this->_routeParams[$key];
        }
        // Fallback: extrair da URL manualmente
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($uri, '/'));
        $segments = array_values($segments);
        // Último segmento numérico = geralmente o ID
        $last = end($segments);
        if (is_numeric($last) && ($key === 'id')) {
            return $last;
        }
        return '';
    }
}
