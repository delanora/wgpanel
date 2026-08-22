<?php $pageTitle = 'Dashboard - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <div>
        <nav class="breadcrumb">
            <span class="breadcrumb-current">Dashboard</span>
        </nav>
        <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
    </div>
    <button onclick="refreshDashboard()" class="btn btn-secondary" id="refreshBtn">
        <i class="fas fa-sync-alt"></i> Atualizar Agora
    </button>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-accent"><i class="fas fa-shield-halved"></i></div>
        <div class="stat-info">
            <h3><?= $data['active_interfaces'] ?> / <?= $data['total_interfaces'] ?></h3>
            <p>Interfaces WireGuard</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon icon-accent"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?= $data['active_peers'] ?> / <?= $data['total_peers'] ?></h3>
            <p>Peers Ativos</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon icon-accent"><i class="fas fa-signal"></i></div>
        <div class="stat-info">
            <h3><?= count(array_filter($data['latency_data'] ?? [], fn($l) => $l['rtt_avg_ms'] !== null)) ?> / <?= count($data['latency_data'] ?? []) ?></h3>
            <p>Alvos Respondendo</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon icon-accent"><i class="fas fa-users-gear"></i></div>
        <div class="stat-info">
            <h3><?= $data['active_users'] ?></h3>
            <p>Usuários do Sistema</p>
        </div>
    </div>
</div>

<!-- Monitoramento de Latência -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2><i class="fas fa-tower-broadcast"></i> Monitoramento de Latência</h2>
        <button onclick="refreshLatency()" class="btn btn-ghost" id="latencyBtn" title="Atualizar latência">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th></th>
                    <th>Alvo</th>
                    <th style="text-align: right;">Latência (RTT)</th>
                    <th style="text-align: right;">Perda de Pacote</th>
                    <th style="text-align: right;">Última Verificação</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Ícones SVG inline para cada serviço
                $icons = [
                    'Google' => '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align: middle;"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>',
                    'Cloudflare' => '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align: middle;"><path fill="#F38020" d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg>',
                    'Registro.br' => '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align: middle;"><circle cx="12" cy="12" r="10" fill="#009846"/><text x="12" y="16" text-anchor="middle" fill="white" font-size="10" font-weight="bold">.br</text></svg>',
                    'Microsoft 365' => '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align: middle;"><rect x="1" y="1" width="10" height="10" fill="#F25022"/><rect x="13" y="1" width="10" height="10" fill="#7FBA00"/><rect x="1" y="13" width="10" height="10" fill="#00A4EF"/><rect x="13" y="13" width="10" height="10" fill="#FFB900"/></svg>',
                    'WhatsApp' => '<svg viewBox="0 0 24 24" width="18" height="18" style="vertical-align: middle;"><path fill="#25D366" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path fill="#25D366" d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492l4.634-1.22A11.95 11.95 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-2.09 0-4.045-.554-5.726-1.52l-.41-.24-2.734.718.727-2.665-.268-.425A9.694 9.694 0 0 1 2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75z"/></svg>',
                ];
                
                foreach ($data['latency_data'] as $latency):
                    $rtt = $latency['rtt_avg_ms'];
                    $loss = $latency['packet_loss_pct'];
                    $hasResponse = $rtt !== null;
                    
                    // Determinar cor
                    if (!$hasResponse || $loss === 100) {
                        $statusColor = '#ef4444'; // Vermelho
                        $statusLabel = 'Sem resposta';
                    } elseif ($rtt > 150 || $loss > 20) {
                        $statusColor = '#ef4444'; // Vermelho
                        $statusLabel = round($rtt, 1) . 'ms';
                    } elseif ($rtt > 50 || $loss >= 1) {
                        $statusColor = '#eab308'; // Amarelo
                        $statusLabel = round($rtt, 1) . 'ms';
                    } else {
                        $statusColor = '#22c55e'; // Verde
                        $statusLabel = round($rtt, 1) . 'ms';
                    }
                ?>
                <tr>
                    <td style="width: 40px; text-align: center;">
                        <?= $icons[$latency['label']] ?? '<i class="fas fa-globe" style="color: var(--text-muted);"></i>' ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($latency['label']) ?></strong>
                        <span style="color: var(--text-muted); font-size: 12px; margin-left: 6px;"><?= htmlspecialchars($latency['target']) ?></span>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($hasResponse): ?>
                            <span style="color: <?= $statusColor ?>; font-weight: 600; font-family: var(--font-mono); font-size: 14px;">
                                <?= $statusLabel ?>
                            </span>
                        <?php else: ?>
                            <span style="color: <?= $statusColor ?>; font-weight: 600; font-size: 13px;">
                                <?= $statusLabel ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($loss == 0): ?>
                            <span style="color: var(--success); font-size: 13px;">0%</span>
                        <?php elseif ($loss <= 20): ?>
                            <span style="color: var(--warning); font-size: 13px;"><?= round($loss) ?>%</span>
                        <?php else: ?>
                            <span style="color: var(--danger); font-size: 13px;"><?= round($loss) ?>%</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right; color: var(--text-muted); font-size: 12px;">
                        <?php if ($latency['checked_at']): ?>
                            <?= date('d/m H:i', strtotime($latency['checked_at'])) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Gráfico de Tráfego -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h2><i class="fas fa-chart-bar"></i> Tráfego Agregado por Interface</h2>
    </div>
    <div class="card-body">
        <?php if (empty($data['traffic_by_interface'])): ?>
            <p class="text-center text-muted" style="padding: 20px 0;">
                Nenhum dado de tráfego ainda.<br>
                Coleta automática a cada 5 minutos via cron.
            </p>
        <?php else: ?>
            <div style="position: relative; height: 200px;">
                <canvas id="trafficChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tráfego Total -->
<?php if (!empty($data['traffic_by_interface'])): ?>
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-exchange-alt"></i> Tráfego Total (24h)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Interface</th>
                    <th style="text-align: right;"><i class="fas fa-arrow-down"></i> Recebido (RX)</th>
                    <th style="text-align: right;"><i class="fas fa-arrow-up"></i> Enviado (TX)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['traffic_by_interface'] as $t): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
                    <td style="text-align: right;"><?= \App\Controller\WireguardPeerController::formatBytes($t['rx']) ?></td>
                    <td style="text-align: right;"><?= \App\Controller\WireguardPeerController::formatBytes($t['tx']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($data['chart_data'])): ?>
var chartData = <?= json_encode($data['chart_data']) ?>;
var datasets = {};
chartData.forEach(function(row) {
    if (!datasets[row.interface_name]) {
        datasets[row.interface_name] = { rx: [], tx: [], labels: [] };
    }
    datasets[row.interface_name].rx.push(row.rx);
    datasets[row.interface_name].tx.push(row.tx);
    datasets[row.interface_name].labels.push(row.hour);
});

var colors = ['#0ea5e9', '#38bdf8', '#64748b', '#94a3b8', '#a78bfa', '#f472b6'];
var datasetsArray = [];
var allLabels = [];
Object.keys(datasets).forEach(function(name, idx) {
    var color = colors[idx % colors.length];
    datasetsArray.push({
        label: name + ' (RX)',
        data: datasets[name].rx,
        borderColor: color,
        backgroundColor: color + '33',
        fill: false,
        tension: 0.3,
        borderWidth: 2,
        pointRadius: 3,
    });
    datasetsArray.push({
        label: name + ' (TX)',
        data: datasets[name].tx,
        borderColor: color,
        backgroundColor: color + '33',
        borderDash: [5, 5],
        fill: false,
        tension: 0.3,
        borderWidth: 2,
        pointRadius: 3,
    });
    if (allLabels.length === 0) {
        allLabels = datasets[name].labels.map(function(l) {
            return l.replace('T', ' ').substring(0, 16);
        });
    }
});

if (datasetsArray.length > 0) {
    new Chart(document.getElementById('trafficChart'), {
        type: 'line',
        data: {
            labels: allLabels,
            datasets: datasetsArray,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var bytes = ctx.parsed.y;
                            if (bytes < 1024) return ctx.dataset.label + ': ' + bytes + ' B';
                            if (bytes < 1048576) return ctx.dataset.label + ': ' + (bytes/1024).toFixed(1) + ' KB';
                            if (bytes < 1073741824) return ctx.dataset.label + ': ' + (bytes/1048576).toFixed(1) + ' MB';
                            return ctx.dataset.label + ': ' + (bytes/1073741824).toFixed(2) + ' GB';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { font: { size: 10 }, maxRotation: 45 },
                    grid: { display: false },
                },
                y: {
                    ticks: {
                        font: { size: 10 },
                        callback: function(value) {
                            if (value < 1024) return value + ' B';
                            if (value < 1048576) return (value/1024).toFixed(0) + ' KB';
                            if (value < 1073741824) return (value/1048576).toFixed(1) + ' MB';
                            return (value/1073741824).toFixed(2) + ' GB';
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Refresh Dashboard (tráfego + latência)
function refreshDashboard() {
    var btn = document.getElementById('refreshBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Coletando...';
    
    // Coletar tráfego e latência em paralelo
    Promise.all([
        fetch('/dashboard/collect-traffic', { method: 'POST' }).then(function(r) { return r.json(); }),
        fetch('/dashboard/collect-latency', { method: 'POST' }).then(function(r) { return r.json(); })
    ]).then(function(results) {
        window.location.reload();
    }).catch(function(err) {
        alert('Erro de conexão: ' + err);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Atualizar Agora';
    });
}

// Refresh só latência
function refreshLatency() {
    var btn = document.getElementById('latencyBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('/dashboard/collect-latency', { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Desconhecido'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Atualizar';
            }
        })
        .catch(function(err) {
            alert('Erro de conexão: ' + err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        });
}

// Auto-refresh a cada 30s
setTimeout(function() { window.location.reload(); }, 30000);
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
