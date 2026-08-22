<?php
namespace App\Controller;

use App\Service\MikrotikClient;
use App\Exception\MikrotikApiException;

class DashboardController {
    
    public array $_routeParams = [];
    private MikrotikClient $client;
    
    public function __construct(\App\Service\MikrotikClient $client = null) {
        $this->client = $client ?? MikrotikClient::fromEnv();
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
            // Latência
            'latency_data' => [],
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
        
        // Latência — última leitura de cada alvo
        $latencyTargets = ['8.8.8.8', '1.1.1.1', 'registro.br', 'outlook.office365.com', 'whatsapp.com'];
        $latencyLabels = ['8.8.8.8' => 'Google', '1.1.1.1' => 'Cloudflare', 'registro.br' => 'Registro.br', 'outlook.office365.com' => 'Microsoft 365', 'whatsapp.com' => 'WhatsApp'];
        
        foreach ($latencyTargets as $target) {
            $latest = \Database::fetch(
                'SELECT * FROM latency_log WHERE target = ? ORDER BY checked_at DESC LIMIT 1',
                [$target]
            );
            
            $data['latency_data'][] = [
                'target'          => $target,
                'label'           => $latencyLabels[$target],
                'rtt_avg_ms'      => $latest ? $latest['rtt_avg_ms'] : null,
                'packet_loss_pct' => $latest ? $latest['packet_loss_pct'] : null,
                'checked_at'      => $latest ? $latest['checked_at'] : null,
            ];
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
    
    /**
     * Coleta latência sob demanda (botão "Atualizar Agora").
     */
    public function collectLatency(): void {
        header('Content-Type: application/json');
        
        $targets = [
            ['target' => '8.8.8.8',              'label' => 'Google'],
            ['target' => '1.1.1.1',              'label' => 'Cloudflare'],
            ['target' => 'registro.br',          'label' => 'Registro.br'],
            ['target' => 'outlook.office365.com', 'label' => 'Microsoft 365'],
            ['target' => 'whatsapp.com',         'label' => 'WhatsApp'],
        ];
        
        $count = 0;
        
        foreach ($targets as $t) {
            try {
                $result = $this->client->post('/ping', [
                    'address' => $t['target'],
                    'count' => '4',
                ]);
                
                $rttValues = [];
                $totalPackets = 4;
                $receivedPackets = 0;
                
                if (is_array($result)) {
                    foreach ($result as $packet) {
                        $time = $packet['time'] ?? '';
                        if ($time !== '' && $time !== '0') {
                            $receivedPackets++;
                            $rttValues[] = self::parseRtt($time);
                        }
                    }
                }
                
                $rttAvg = !empty($rttValues) ? round(array_sum($rttValues) / count($rttValues), 2) : null;
                $packetLoss = $totalPackets > 0
                    ? round((($totalPackets - $receivedPackets) / $totalPackets) * 100, 2)
                    : 100;
                
                if ($receivedPackets === 0) {
                    $rttAvg = null;
                    $packetLoss = 100;
                }
                
                \Database::insert('latency_log', [
                    'target'           => $t['target'],
                    'target_label'     => $t['label'],
                    'rtt_avg_ms'       => $rttAvg,
                    'packet_loss_pct'  => $packetLoss,
                    'checked_at'       => date('Y-m-d H:i:s'),
                ]);
                
                $count++;
                
            } catch (\Exception $e) {
                \Database::insert('latency_log', [
                    'target'           => $t['target'],
                    'target_label'     => $t['label'],
                    'rtt_avg_ms'       => null,
                    'packet_loss_pct'  => 100,
                    'checked_at'       => date('Y-m-d H:i:s'),
                ]);
                $count++;
            }
        }
        
        echo json_encode(['success' => true, 'count' => $count]);
    }
    
    /**
     * Parseia tempo RTT do Mikrotik (formato '12ms299us') para float em ms.
     */
    private static function parseRtt(string $time): float {
        $ms = 0.0;
        if (preg_match('/(\d+(\.\d+)?)ms/', $time, $m)) {
            $ms = (float) $m[1];
        }
        if (preg_match('/(\d+(\.\d+)?)us/', $time, $m)) {
            $ms += (float) $m[1] / 1000;
        }
        return round($ms, 2);
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
