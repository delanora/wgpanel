<?php
/**
 * Coleta de tráfego WireGuard
 *
 * Chamar via crontab:
 *   5-minute cron: /usr/bin/php /var/www/wgpanel/src/cron/collect_traffic.php
 *
 * Coleta rx/tx de todos os peers ativos e salva no banco.
 */

// Carregar configurações
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Exception/MikrotikApiException.php';
require_once dirname(__DIR__) . '/src/Service/MikrotikClient.php';

$client = \App\Service\MikrotikClient::fromEnv();

// Buscar todos os peers com suas interfaces
$peers = \Database::fetchAll(
    'SELECT p.id, p.public_key, p.allowed_address, i.name as interface_name
     FROM wireguard_peers p
     JOIN wireguard_interfaces i ON p.interface_id = i.id
     WHERE p.status = ?',
    ['active']
);

if (empty($peers)) {
    echo "[" . date('Y-m-d H:i:s') . "] Nenhum peer ativo encontrado." . PHP_EOL;
    exit(0);
}

// Buscar todos os peers do Mikrotik de uma vez
try {
    $mkPeers = $client->get('/interface/wireguard/peers');
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Erro ao buscar peers: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Indexar por public_key
$mkPeersByKey = [];
if (is_array($mkPeers)) {
    foreach ($mkPeers as $mp) {
        $key = $mp['public-key'] ?? '';
        if ($key) {
            $mkPeersByKey[$key] = $mp;
        }
    }
}

$count = 0;
foreach ($peers as $peer) {
    $mk = $mkPeersByKey[$peer['public_key']] ?? null;
    
    if ($mk === null) {
        // Peer não encontrado no Mikrotik, registrar 0
        $rx = 0;
        $tx = 0;
    } else {
        $rx = (int) ($mk['rx'] ?? 0);
        $tx = (int) ($mk['tx'] ?? 0);
    }
    
    // Salvar no banco
    \Database::insert('wireguard_traffic_log', [
        'peer_id' => $peer['id'],
        'rx' => $rx,
        'tx' => $tx,
        'logged_at' => date('Y-m-d H:i:s'),
    ]);
    
    $count++;
}

echo "[" . date('Y-m-d H:i:s') . "] Coleta concluída: {$count} peers registrados." . PHP_EOL;
