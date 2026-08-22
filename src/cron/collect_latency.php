<?php
/**
 * Coleta de latência (ping) via Mikrotik RouterOS
 *
 * Chamar via crontab (a cada 5 minutos):
 *   /usr/bin/php /var/www/wgpanel/src/cron/collect_latency.php
 *
 * Para cada alvo fixo, chama POST /rest/ping com count=4,
 * calcula média de RTT e percentual de perda, salva no banco.
 */

// Carregar configurações
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/Exception/MikrotikApiException.php';
require_once dirname(__DIR__) . '/src/Service/HttpTransport.php';
require_once dirname(__DIR__) . '/src/Service/CurlTransport.php';
require_once dirname(__DIR__) . '/src/Service/MikrotikClient.php';

/**
 * Parseia tempo RTT do Mikrotik (formato '12ms299us') para float em milissegundos.
 */
function parseRtt(string $time): float {
    $ms = 0.0;
    if (preg_match('/(\d+(\.\d+)?)ms/', $time, $m)) {
        $ms = (float) $m[1];
    }
    if (preg_match('/(\d+(\.\d+)?)us/', $time, $m)) {
        $ms += (float) $m[1] / 1000;
    }
    return round($ms, 2);
}

$client = \App\Service\MikrotikClient::fromEnv();

// Alvos fixos de monitoramento
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
        // Chamar ping via REST API do Mikrotik
        $result = $client->post('/ping', [
            'address' => $t['target'],
            'count' => '4',
        ]);

        // Processar resultado
        $rttValues = [];
        $receivedPackets = 0;

        if (is_array($result)) {
            foreach ($result as $packet) {
                // Mikrotik retorna pacotes com 'time' no formato '12ms299us'
                $time = $packet['time'] ?? '';
                if ($time !== '' && $time !== '0') {
                    $receivedPackets++;
                    $rttValues[] = parseRtt($time);
                }
            }
        }

        // Calcular média e perda
        $rttAvg = !empty($rttValues) ? round(array_sum($rttValues) / count($rttValues), 2) : null;
        $packetLoss = $receivedPackets < 4
            ? round(((4 - $receivedPackets) / 4) * 100, 2)
            : 0;

        // Se nenhum pacote retornou
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

        $rttDisplay = $rttAvg !== null ? "{$rttAvg}ms" : "Sem resposta";
        echo "[" . date('Y-m-d H:i:s') . "] {$t['label']} ({$t['target']}): {$rttDisplay} | Perda: {$packetLoss}%" . PHP_EOL;
        $count++;

    } catch (\Exception $e) {
        // Erro neste alvo não afeta os outros
        \Database::insert('latency_log', [
            'target'           => $t['target'],
            'target_label'     => $t['label'],
            'rtt_avg_ms'       => null,
            'packet_loss_pct'  => 100,
            'checked_at'       => date('Y-m-d H:i:s'),
        ]);

        echo "[" . date('Y-m-d H:i:s') . "] {$t['label']} ({$t['target']}): ERRO - " . $e->getMessage() . PHP_EOL;
        $count++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Coleta de latência concluída: {$count} alvos verificados." . PHP_EOL;
