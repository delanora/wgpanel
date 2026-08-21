<?php
namespace App\Controller;

use App\Service\MikrotikClient;
use App\Exception\MikrotikApiException;

class WireguardPeerController {
    
    private MikrotikClient $client;
    
    /** @var array Parâmetros da rota injetados pelo Router */
    public array $_routeParams = [];
    
    public function __construct() {
        $this->client = MikrotikClient::fromEnv();
    }
    
    /**
     * Lista peers de uma interface específica.
     * Combina dados do banco com estado ao vivo do Mikrotik.
     */
    public function index(): void {
        $interfaceId = (int) $this->getRouteParam('interface_id');
        $interface = \Database::fetch('SELECT * FROM wireguard_interfaces WHERE id = ?', [$interfaceId]);
        
        if (!$interface) {
            header('Location: /wireguard');
            exit;
        }
        
        // Buscar peers do banco
        $peers = \Database::fetchAll(
            'SELECT * FROM wireguard_peers WHERE interface_id = ? ORDER BY created_at DESC',
            [$interfaceId]
        );
        
        // Buscar peers ao vivo do Mikrotik
        $mkPeers = [];
        try {
            $mkPeersData = $this->client->get('/interface/wireguard/peers', [
                'interface' => $interface['name'],
            ]);
            if (is_array($mkPeersData)) {
                foreach ($mkPeersData as $mp) {
                    // Indexar por public_key para facilitar lookup
                    $mkPeers[$mp['public-key'] ?? ''] = $mp;
                }
            }
        } catch (\Exception $e) {
            // Ignorar - mostrar apenas dados do banco
        }
        
        // Enriquecer dados dos peers com info ao vivo
        $now = time();
        foreach ($peers as &$peer) {
            $mk = $mkPeers[$peer['public_key']] ?? null;
            
            $peer['current_endpoint'] = $mk['current-endpoint-address'] ?? null;
            $peer['last_handshake'] = $mk['last-handshake'] ?? null;
            $peer['rx'] = (int) ($mk['rx'] ?? 0);
            $peer['tx'] = (int) ($mk['tx'] ?? 0);
            $peer['mk_disabled'] = $mk['disabled'] ?? false;
            $peer['mk_id'] = $mk['.id'] ?? null;
            
            // Status de conexão
            if ($peer['last_handshake'] === null || $peer['last_handshake'] === '' || $peer['last_handshake'] === 'never') {
                $peer['connection_status'] = 'never';
            } else {
                // Converter last-handshake para timestamp
                $hsTime = $this->parseRelativeTime($peer['last_handshake']);
                if ($hsTime !== null && ($now - $hsTime) < 180) {
                    $peer['connection_status'] = 'connected';
                } else {
                    $peer['connection_status'] = 'disconnected';
                }
            }
        }
        unset($peer);
        
        // Buscar próxima IP livre
        $nextIp = $this->getNextAvailableIp($interface);
        
        require __DIR__ . '/../../views/wireguard/peers/index.php';
    }
    
    /**
     * Formulário de criação de peer.
     */
    public function create(): void {
        $interfaceId = (int) $this->getRouteParam('interface_id');
        $interface = \Database::fetch('SELECT * FROM wireguard_interfaces WHERE id = ?', [$interfaceId]);
        
        if (!$interface) {
            header('Location: /wireguard');
            exit;
        }
        
        $nextIp = $this->getNextAvailableIp($interface);
        
        require __DIR__ . '/../../views/wireguard/peers/create.php';
    }
    
    /**
     * Cria um peer WireGuard:
     * 1. PUT /rest/interface/wireguard/peers (private-key=auto)
     * 2. POST /rest/interface/wireguard/peers/show-client-config
     * 3. Salvar no banco
     * 4. Rollback se falhar
     */
    public function store(): void {
        $interfaceId = (int) $this->getRouteParam('interface_id');
        $interface = \Database::fetch('SELECT * FROM wireguard_interfaces WHERE id = ?', [$interfaceId]);
        
        if (!$interface) {
            header('Location: /wireguard');
            exit;
        }
        
        $peerName = trim($_POST['peer_name'] ?? '');
        $contactName = trim($_POST['contact_name'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $allowedAddress = trim($_POST['allowed_address'] ?? '');
        $additionalRoutes = trim($_POST['additional_routes'] ?? '');
        
        // Validações
        $errors = [];
        if ($peerName === '') $errors[] = 'Nome do peer é obrigatório';
        if (!preg_match('#^\d+\.\d+\.\d+\.\d+/\d+$#', $allowedAddress)) {
            $errors[] = 'Allowed address inválido (ex: 10.10.1.2/32)';
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
            $nextIp = $this->getNextAvailableIp($interface);
            require __DIR__ . '/../../views/wireguard/peers/create.php';
            return;
        }
        
        $mkPeerId = null;
        
        try {
            // Passo 1: Criar peer no Mikrotik
            $mkPeer = $this->client->put('/interface/wireguard/peers', [
                'interface' => $interface['name'],
                'name' => $peerName,
                'private-key' => 'auto',
                'allowed-address' => $allowedAddress,
            ]);
            
            $mkPeerId = $mkPeer['.id'] ?? null;
            $publicKey = $mkPeer['public-key'] ?? '';
            $privateKey = $mkPeer['private-key'] ?? '';
            
            // Passo 2: Gerar config do cliente
            $clientConfig = '';
            $serverPublicKey = $interface['public_key'];
            $serverEndpoint = parse_url(MIKROTIK_API_URL, PHP_URL_HOST);
            $serverPort = $interface['listen_port'];
            
            // Configs já geradas acima
            
            // Passo 3: Salvar no banco
            $dbId = \Database::insert('wireguard_peers', [
                'interface_id' => $interfaceId,
                'peer_name' => $peerName,
                'public_key' => $publicKey,
                'private_key' => $privateKey,
                'allowed_address' => $allowedAddress,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'notes' => $notes,
                'additional_routes' => $additionalRoutes,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
            header('Location: /wireguard/peers/' . $interfaceId . '?success=created');
            exit;
            
        } catch (MikrotikApiException $e) {
            $this->rollback($mkPeerId);
            $error = 'Erro Mikrotik: ' . $e->getUserMessage();
            $nextIp = $this->getNextAvailableIp($interface);
            require __DIR__ . '/../../views/wireguard/peers/create.php';
            
        } catch (\Exception $e) {
            $this->rollback($mkPeerId);
            $error = 'Erro: ' . $e->getMessage();
            $nextIp = $this->getNextAvailableIp($interface);
            require __DIR__ . '/../../views/wireguard/peers/create.php';
        }
    }
    
    /**
     * Edição de peer: nome/contato/observações (banco) e status (Mikrotik).
     */
    public function edit(): void {
        $peerId = (int) $this->getRouteParam('peer_id');
        $peer = \Database::fetch(
            'SELECT p.*, i.name as interface_name FROM wireguard_peers p 
             JOIN wireguard_interfaces i ON p.interface_id = i.id 
             WHERE p.id = ?',
            [$peerId]
        );
        
        if (!$peer) {
            header('Location: /wireguard');
            exit;
        }
        
        require __DIR__ . '/../../views/wireguard/peers/edit.php';
    }
    
    /**
     * Processa edição do peer.
     */
    public function update(): void {
        $peerId = (int) $this->getRouteParam('peer_id');
        $peer = \Database::fetch('SELECT * FROM wireguard_peers WHERE id = ?', [$peerId]);
        
        if (!$peer) {
            header('Location: /wireguard');
            exit;
        }
        
        $contactName = trim($_POST['contact_name'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $additionalRoutes = trim($_POST['additional_routes'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'disabled';
        
        // Sincronizar status no Mikrotik
        if ($peer['public_key']) {
            try {
                $mkPeers = $this->client->get('/interface/wireguard/peers', [
                    'public-key' => $peer['public_key'],
                ]);
                if (!empty($mkPeers[0])) {
                    $mkId = $mkPeers[0]['.id'] ?? null;
                    if ($mkId) {
                        $this->client->patch('/interface/wireguard/peers/' . $mkId, [
                            'disabled' => $status === 'disabled',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                error_log("WireGuard peer sync error: " . $e->getMessage());
            }
        }
        
        \Database::update('wireguard_peers', [
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'notes' => $notes,
            'additional_routes' => $additionalRoutes,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$peerId]);
        
        header('Location: /wireguard/peers/' . $peer['interface_id'] . '?success=updated');
        exit;
    }
    
    /**
     * Exclui peer do Mikrotik e do banco.
     */
    public function delete(): void {
        $peerId = (int) $this->getRouteParam('peer_id');
        $peer = \Database::fetch('SELECT * FROM wireguard_peers WHERE id = ?', [$peerId]);
        
        if (!$peer) {
            header('Location: /wireguard');
            exit;
        }
        
        $interfaceId = $peer['interface_id'];
        
        try {
            // Remover do Mikrotik
            if ($peer['public_key']) {
                $mkPeers = $this->client->get('/interface/wireguard/peers', [
                    'public-key' => $peer['public_key'],
                ]);
                if (!empty($mkPeers[0])) {
                    $mkId = $mkPeers[0]['.id'] ?? null;
                    if ($mkId) {
                        $this->client->delete('/interface/wireguard/peers/' . $mkId);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("WireGuard peer delete Mikrotik error: " . $e->getMessage());
        }
        
        \Database::delete('wireguard_peers', 'id = ?', [$peerId]);
        
        header('Location: /wireguard/peers/' . $interfaceId . '?success=deleted');
        exit;
    }
    
    /**
     * Exporta config do peer: texto para copiar + download .conf.
     */
    public function exportConfig(): void {
        $peerId = (int) $this->getRouteParam('peer_id');
        $peer = \Database::fetch(
            'SELECT p.*, i.name as interface_name, i.listen_port, i.network_cidr, i.server_ip, i.public_key as server_public_key
             FROM wireguard_peers p 
             JOIN wireguard_interfaces i ON p.interface_id = i.id 
             WHERE p.id = ?',
            [$peerId]
        );
        
        if (!$peer) {
            header('Location: /wireguard');
            exit;
        }
        
        // Gerar configs
        $serverEndpoint = parse_url(MIKROTIK_API_URL, PHP_URL_HOST);
        $serverIp = explode('/', $peer['server_ip'])[0];
        $additionalRoutes = $peer['additional_routes'] ?? '';
        $configLinux = $this->generateLinuxConfig(
            $peer['private_key'], $peer['server_public_key'], $serverEndpoint, $peer['listen_port'], $peer['allowed_address'], $additionalRoutes
        );
        $configWindows = $this->generateWindowsConfig(
            $peer['private_key'], $peer['server_public_key'], $serverEndpoint, $peer['listen_port'], $peer['allowed_address'], $serverIp, $peer['network_cidr'], $additionalRoutes
        );
        
        require __DIR__ . '/../../views/wireguard/peers/config.php';
    }
    
    /**
     * Download do arquivo .conf.
     */
    public function downloadConfig(): void {
        $peerId = (int) $this->getRouteParam('peer_id');
        $peer = \Database::fetch(
            'SELECT p.*, i.listen_port, i.public_key as server_public_key
             FROM wireguard_peers p 
             JOIN wireguard_interfaces i ON p.interface_id = i.id 
             WHERE p.id = ?',
            [$peerId]
        );
        
        if (!$peer) {
            header('Location: /wireguard');
            exit;
        }
        
        $serverEndpoint = parse_url(MIKROTIK_API_URL, PHP_URL_HOST);
        $additionalRoutes = $peer['additional_routes'] ?? '';
        $configLinux = $this->generateLinuxConfig(
            $peer['private_key'], $peer['server_public_key'], $serverEndpoint, $peer['listen_port'], $peer['allowed_address'], $additionalRoutes
        );
        $configWindows = $this->generateWindowsConfig(
            $peer['private_key'], $peer['server_public_key'], $serverEndpoint, $peer['listen_port'], $peer['allowed_address'], $peer['server_ip'] ?? '', $peer['network_cidr'] ?? '', $additionalRoutes
        );
        
        // Download de ambas as versões
        $baseFilename = 'wireguard-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($peer['peer_name']));
        $os = $_GET['os'] ?? 'linux';
        
        if ($os === 'windows') {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $baseFilename . '-windows.conf"');
            header('Content-Length: ' . strlen($configWindows));
            echo $configWindows;
        } else {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $baseFilename . '-linux.conf"');
            header('Content-Length: ' . strlen($configLinux));
            echo $configLinux;
        }
        exit;
    }
    
    // =====================================================================
    // Métodos auxiliares
    // =====================================================================
    
    /**
     * Gera config .conf para Linux/macOS/Android.
     */
    private function generateLinuxConfig(
        string $privateKey,
        string $serverPublicKey,
        string $serverEndpoint,
        int $serverPort,
        string $allowedAddress,
        string $additionalRoutes = ''
    ): string {
        // Montar AllowedIPs: IP do peer + rotas adicionais
        $allowedIps = $allowedAddress;
        if ($additionalRoutes !== '') {
            $routes = array_filter(array_map('trim', explode(',', $additionalRoutes)));
            $allowedIps .= ', ' . implode(', ', $routes);
        }
        
        return "[Interface]\n"
             . "PrivateKey = {$privateKey}\n"
             . "Address = {$allowedAddress}\n"
             . "DNS = 1.1.1.1, 8.8.8.8\n"
             . "\n"
             . "[Peer]\n"
             . "PublicKey = {$serverPublicKey}\n"
             . "Endpoint = {$serverEndpoint}:{$serverPort}\n"
             . "AllowedIPs = {$allowedIps}\n"
             . "PersistentKeepalive = 25\n";
    }
    
    /**
     * Gera config .conf para Windows com rotas netsh.
     */
    private function generateWindowsConfig(
        string $privateKey,
        string $serverPublicKey,
        string $serverEndpoint,
        int $serverPort,
        string $allowedAddress,
        string $serverIp,
        string $networkCidr,
        string $additionalRoutes = ''
    ): string {
        // Todas as rotas: rede da interface + rotas adicionais
        $allRoutes = [$networkCidr];
        if ($additionalRoutes !== '') {
            $extra = array_filter(array_map('trim', explode(',', $additionalRoutes)));
            $allRoutes = array_merge($allRoutes, $extra);
        }
        
        // Montar AllowedIPs: IP do peer + todas as rotas
        $allowedIps = $allowedAddress;
        if (count($allRoutes) > 0) {
            $allowedIps .= ', ' . implode(', ', $allRoutes);
        }
        
        // Montar PostUp: adicionar rota para servidor + cada rede
        $postUpParts = [];
        $postDownParts = [];
        
        // Rota para o servidor
        $postUpParts[] = "netsh int ipv4 add route {$serverIp}/32 interface=%WIREGUARD_TUNNEL_NAME%";
        $postDownParts[] = "netsh int ipv4 delete route {$serverIp}/32 interface=%WIREGUARD_TUNNEL_NAME%";
        
        // Cada rede como rota via servidor
        foreach ($allRoutes as $route) {
            $postUpParts[] = "netsh int ipv4 add route {$route} interface=%WIREGUARD_TUNNEL_NAME% nexthop={$serverIp}";
            $postDownParts[] = "netsh int ipv4 delete route {$route} interface=%WIREGUARD_TUNNEL_NAME% nexthop={$serverIp}";
        }
        
        return "[Interface]\n"
             . "PrivateKey = {$privateKey}\n"
             . "Address = {$allowedAddress}\n"
             . "MTU = 1450\n"
             . "\n"
             . "PostUp = " . implode(' && ', $postUpParts) . "\n"
             . "PostDown = " . implode(' && ', $postDownParts) . "\n"
             . "Table = off\n"
             . "\n"
             . "[Peer]\n"
             . "PublicKey = {$serverPublicKey}\n"
             . "Endpoint = {$serverEndpoint}:{$serverPort}\n"
             . "AllowedIPs = {$allowedIps}\n"
             . "PersistentKeepalive = 25\n";
    }
    
    /**
     * Calcula o próximo IP disponível dentro do CIDR da interface.
     */
    private function getNextAvailableIp(array $interface): string {
        $cidr = $interface['network_cidr'];
        $parts = explode('/', $cidr);
        $baseIp = $parts[0];
        $mask = (int) ($parts[1] ?? 24);
        
        // IPs já usados por peers
        $usedIps = array_column(
            \Database::fetchAll('SELECT allowed_address FROM wireguard_peers WHERE interface_id = ?', [$interface['id']]),
            'allowed_address'
        );
        
        // IP do servidor (primeiro IP)
        $serverIpBase = $this->incrementIp($baseIp);
        
        // Encontrar próximo IP livre (começando do segundo IP)
        $nextIp = $this->incrementIp($baseIp, 2); // pular o .1 (servidor)
        $maxIp = $this->incrementIp($baseIp, 254); // último antes do broadcast
        
        while ($nextIp !== $maxIp) {
            $cidrCandidate = $nextIp . '/32';
            $used = false;
            foreach ($usedIps as $usedIp) {
                if (str_starts_with($usedIp, $nextIp . '/')) {
                    $used = true;
                    break;
                }
            }
            if (!$used) {
                return $cidrCandidate;
            }
            $nextIp = $this->incrementIp($nextIp);
        }
        
        return $this->incrementIp($baseIp, 2) . '/32';
    }
    
    /**
     * Incrementa um IP em N.
     */
    private function incrementIp(string $ip, int $increment = 1): string {
        $parts = explode('.', $ip);
        $parts[3] = (int) $parts[3] + $increment;
        for ($i = 3; $i > 0; $i--) {
            if ($parts[$i] > 255) {
                $parts[$i] -= 256;
                $parts[$i - 1]++;
            }
        }
        return implode('.', $parts);
    }
    
    /**
     * Rollback: remove peer criado no Mikrotik.
     */
    private function rollback(?string $mkPeerId): void {
        if ($mkPeerId) {
            try {
                $this->client->delete('/interface/wireguard/peers/' . $mkPeerId);
            } catch (\Exception $e) {
                error_log("WireGuard peer rollback failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Extrai parâmetro da rota.
     */
    private function getRouteParam(string $key): string {
        if (!empty($this->_routeParams[$key])) {
            return $this->_routeParams[$key];
        }
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = explode('/', trim($uri, '/'));
        $segments = array_values($segments);
        // Procurar o segmento depois do nome do parâmetro
        foreach ($segments as $i => $segment) {
            if ($segment === $key && isset($segments[$i + 1])) {
                return $segments[$i + 1];
            }
        }
        // Fallback: último segmento numérico
        $last = end($segments);
        if (is_numeric($last) && in_array($key, ['id', 'peer_id', 'interface_id'])) {
            return $last;
        }
        return '';
    }
    
    /**
     * Converte tempo relativo do Mikrotik (ex: "3m20s") para timestamp.
     */
    private function parseRelativeTime(string $time): ?int {
        if ($time === '' || $time === 'never' || $time === '0s') {
            return null;
        }
        
        $totalSeconds = 0;
        if (preg_match('/(\d+)d/', $time, $m)) $totalSeconds += (int)$m[1] * 86400;
        if (preg_match('/(\d+)h/', $time, $m)) $totalSeconds += (int)$m[1] * 3600;
        if (preg_match('/(\d+)m/', $time, $m)) $totalSeconds += (int)$m[1] * 60;
        if (preg_match('/(\d+)s/', $time, $m)) $totalSeconds += (int)$m[1];
        
        return time() - $totalSeconds;
    }
    
    /**
     * Formata bytes em formato legível.
     */
    public static function formatBytes(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
