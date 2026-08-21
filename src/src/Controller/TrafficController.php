<?php
namespace App\Controller;

use App\Service\MikrotikClient;
use App\Exception\MikrotikApiException;

class TrafficController {
    
    public array $_routeParams = [];
    
    /**
     * Página principal de tráfego.
     * Recebe interface_id e período via query string.
     */
    public function index(): void {
        $interfaces = \Database::fetchAll(
            'SELECT id, name, client_name FROM wireguard_interfaces WHERE status = ? ORDER BY name',
            ['active']
        );
        
        $selectedInterface = (int) ($_GET['interface_id'] ?? 0);
        $period = $_GET['period'] ?? '24h';
        
        // Validar período
        $validPeriods = ['1h', '6h', '24h', '7d', '30d', '90d', '180d', '365d'];
        if (!in_array($period, $validPeriods)) {
            $period = '24h';
        }
        
        // Se não selecionou interface, usar a primeira
        if ($selectedInterface === 0 && !empty($interfaces)) {
            $selectedInterface = $interfaces[0]['id'];
        }
        
        // Mapear período para intervalo SQL
        $intervalMap = [
            '1h'   => '1 hour',
            '6h'   => '6 hours',
            '24h'  => '1 day',
            '7d'   => '7 days',
            '30d'  => '30 days',
            '90d'  => '90 days',
            '180d' => '180 days',
            '365d' => '365 days',
        ];
        $sqlInterval = $intervalMap[$period];
        
        // Buscar dados de tráfego
        $chartData = [];
        $summary = [
            'total_rx' => 0,
            'total_tx' => 0,
            'peer_count' => 0,
            'peak_rx' => 0,
            'peak_tx' => 0,
        ];
        
        if ($selectedInterface > 0) {
            // Tráfego agregado por hora
            $chartData = \Database::fetchAll(
                "SELECT DATE_TRUNC('hour', t.logged_at) as hour,
                        SUM(t.rx) as rx, SUM(t.tx) as tx
                 FROM wireguard_traffic_log t
                 JOIN wireguard_peers p ON t.peer_id = p.id
                 WHERE p.interface_id = ?
                   AND t.logged_at >= NOW() - INTERVAL '{$sqlInterval}'
                 GROUP BY DATE_TRUNC('hour', t.logged_at)
                 ORDER BY hour ASC",
                [$selectedInterface]
            );
            
            // Resumo
            $summaryRow = \Database::fetch(
                "SELECT COALESCE(SUM(t.rx), 0) as total_rx,
                        COALESCE(SUM(t.tx), 0) as total_tx,
                        COUNT(DISTINCT p.id) as peer_count,
                        COALESCE(MAX(t.rx), 0) as peak_rx,
                        COALESCE(MAX(t.tx), 0) as peak_tx
                 FROM wireguard_traffic_log t
                 JOIN wireguard_peers p ON t.peer_id = p.id
                 WHERE p.interface_id = ?
                   AND t.logged_at >= NOW() - INTERVAL '{$sqlInterval}'",
                [$selectedInterface]
            );
            
            if ($summaryRow) {
                $summary = $summaryRow;
            }
        }
        
        require __DIR__ . '/../../views/traffic/index.php';
    }
    
    /**
     * API JSON para dados de tráfego (usado pelo gráfico via AJAX).
     */
    public function data(): void {
        header('Content-Type: application/json');
        
        $interfaceId = (int) ($_GET['interface_id'] ?? 0);
        $period = $_GET['period'] ?? '24h';
        
        $intervalMap = [
            '1h' => '1 hour', '6h' => '6 hours', '24h' => '1 day',
            '7d' => '7 days', '30d' => '30 days', '90d' => '90 days',
            '180d' => '180 days', '365d' => '365 days',
        ];
        $sqlInterval = $intervalMap[$period] ?? '1 day';
        
        if ($interfaceId <= 0) {
            echo json_encode(['labels' => [], 'rx' => [], 'tx' => []]);
            return;
        }
        
        $data = \Database::fetchAll(
            "SELECT DATE_TRUNC('hour', t.logged_at) as hour,
                    SUM(t.rx) as rx, SUM(t.tx) as tx
             FROM wireguard_traffic_log t
             JOIN wireguard_peers p ON t.peer_id = p.id
             WHERE p.interface_id = ?
               AND t.logged_at >= NOW() - INTERVAL '{$sqlInterval}'
             GROUP BY DATE_TRUNC('hour', t.logged_at)
             ORDER BY hour ASC",
            [$interfaceId]
        );
        
        $labels = [];
        $rx = [];
        $tx = [];
        
        foreach ($data as $row) {
            $labels[] = date('d/m H:i', strtotime($row['hour']));
            $rx[] = (int) $row['rx'];
            $tx[] = (int) $row['tx'];
        }
        
        echo json_encode(['labels' => $labels, 'rx' => $rx, 'tx' => $tx]);
    }
}
