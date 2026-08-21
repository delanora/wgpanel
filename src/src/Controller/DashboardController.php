<?php
namespace App\Controller;

use App\Service\MikrotikClient;
use App\Exception\MikrotikApiException;

class DashboardController {
    
    public array $_routeParams = [];
    private MikrotikClient $client;
    
    public function __construct() {
        $this->client = MikrotikClient::fromEnv();
    }
    
    public function index(): void {
        $data = [
            'user_name' => $_SESSION['user_name'] ?? 'Usuário',
            'total_users' => \Database::fetch('SELECT COUNT(*) as count FROM users')['count'],
            'active_users' => \Database::fetch('SELECT COUNT(*) as count FROM users WHERE active = true')['count'],
            // WireGuard
            'total_interfaces' => 0,
            'active_interfaces' => 0,
            'total_peers' => 0,
            'active_peers' => 0,
            'disconnected_peers' => [],
            // Tráfego
            'traffic_by_interface' => [],
            'traffic_labels' => [],
        ];
        
        // Interfaces
        $ifaces = \Database::fetchAll('SELECT * FROM wireguard_interfaces');
        $data['total_interfaces'] = count($ifaces);
        $data['active_interfaces'] = count(array_filter($ifaces, fn($i) => $i['status'] === 'active'));
        
        // Peers
        $allPeers = \Database::fetchAll(
            'SELECT p.*, i.name as interface_name FROM wireguard_peers p JOIN wireguard_interfaces i ON p.interface_id = i.id'
        );
        $data['total_peers'] = count($allPeers);
        $data['active_peers'] = count(array_filter($allPeers, fn($p) => $p['status'] === 'active'));
        
        // Status ao vivo do Mikrotik (conectados)
        try {
            $mkPeers = $this->client->get('/interface/wireguard/peers');
            $mkPeersByKey = [];
            if (is_array($mkPeers)) {
                foreach ($mkPeers as $mp) {
                    $mkPeersByKey[$mp['public-key'] ?? ''] = $mp;
                }
            }
            
            $now = time();
            foreach ($allPeers as $peer) {
                if ($peer['status'] !== 'active') continue;
                
                $mk = $mkPeersByKey[$peer['public_key']] ?? null;
                $lastHandshake = $mk['last-handshake'] ?? 'never';
                
                $connected = false;
                if ($lastHandshake !== '' && $lastHandshake !== 'never' && $lastHandshake !== '0s') {
                    $hsTime = $this->parseRelativeTime($lastHandshake);
                    if ($hsTime !== null && ($now - $hsTime) <= 180) {
                        $connected = true;
                    }
                }
                
                if ($connected) {
                    $data['connected_peers'][] = [
                        'peer_name' => $peer['peer_name'],
                        'interface_name' => $peer['interface_name'],
                        'allowed_address' => $peer['allowed_address'],
                        'last_handshake' => $lastHandshake,
                        'contact_name' => $peer['contact_name'],
                    ];
                }
            }
        } catch (\Exception $e) {
            // Ignorar erro de conexão
        }
        
        // Tráfego agregado por interface (últimas 288 registros = 24h se cron a cada 5min)
        $trafficLogs = \Database::fetchAll(
            'SELECT i.name as interface_name, SUM(t.rx) as total_rx, SUM(t.tx) as total_tx
             FROM wireguard_traffic_log t
             JOIN wireguard_peers p ON t.peer_id = p.id
             JOIN wireguard_interfaces i ON p.interface_id = i.id
             WHERE t.logged_at >= NOW() - INTERVAL \'24 hours\'
             GROUP BY i.name
             ORDER BY i.name'
        );
        
        foreach ($trafficLogs as $tl) {
            $data['traffic_by_interface'][] = [
                'name' => $tl['interface_name'],
                'rx' => (int) $tl['total_rx'],
                'tx' => (int) $tl['total_tx'],
            ];
        }
        
        // Dados para gráfico (últimas 50 entradas de log por interface)
        $chartData = \Database::fetchAll(
            'SELECT i.name as interface_name, 
                    DATE_TRUNC(\'hour\', t.logged_at) as hour,
                    MAX(t.rx) as rx, MAX(t.tx) as tx
             FROM wireguard_traffic_log t
             JOIN wireguard_peers p ON t.peer_id = p.id
             JOIN wireguard_interfaces i ON p.interface_id = i.id
             WHERE t.logged_at >= NOW() - INTERVAL \'12 hours\'
             GROUP BY i.name, DATE_TRUNC(\'hour\', t.logged_at)
             ORDER BY hour ASC'
        );
        
        $data['chart_data'] = $chartData;
        
        require __DIR__ . '/../../views/dashboard/index.php';
    }
    
    /**
     * Coleta tráfego sob demanda (botão "Atualizar Agora").
     */
    public function collectTraffic(): void {
        header('Content-Type: application/json');
        
        try {
            $peers = \Database::fetchAll(
                'SELECT p.id, p.public_key FROM wireguard_peers p WHERE p.status = ?',
                ['active']
            );
            
            if (empty($peers)) {
                echo json_encode(['success' => true, 'count' => 0]);
                return;
            }
            
            $mkPeers = $this->client->get('/interface/wireguard/peers');
            $mkPeersByKey = [];
            if (is_array($mkPeers)) {
                foreach ($mkPeers as $mp) {
                    $key = $mp['public-key'] ?? '';
                    if ($key) $mkPeersByKey[$key] = $mp;
                }
            }
            
            $count = 0;
            foreach ($peers as $peer) {
                $mk = $mkPeersByKey[$peer['public_key']] ?? null;
                $rx = (int) ($mk['rx'] ?? 0);
                $tx = (int) ($mk['tx'] ?? 0);
                
                \Database::insert('wireguard_traffic_log', [
                    'peer_id' => $peer['id'],
                    'rx' => $rx,
                    'tx' => $tx,
                    'logged_at' => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }
            
            echo json_encode(['success' => true, 'count' => $count]);
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    private function parseRelativeTime(string $time): ?int {
        if ($time === '' || $time === 'never' || $time === '0s') return null;
        $totalSeconds = 0;
        if (preg_match('/(\d+)d/', $time, $m)) $totalSeconds += (int)$m[1] * 86400;
        if (preg_match('/(\d+)h/', $time, $m)) $totalSeconds += (int)$m[1] * 3600;
        if (preg_match('/(\d+)m/', $time, $m)) $totalSeconds += (int)$m[1] * 60;
        if (preg_match('/(\d+)s/', $time, $m)) $totalSeconds += (int)$m[1];
        return time() - $totalSeconds;
    }
}
