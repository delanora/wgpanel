<?php $pageTitle = 'Dashboard - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
    <button onclick="refreshDashboard()" class="btn btn-secondary" id="refreshBtn">
        <i class="fas fa-sync-alt"></i> Atualizar Agora
    </button>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary);"><i class="fas fa-shield-halved"></i></div>
        <div class="stat-info">
            <h3><?= $data['active_interfaces'] ?> / <?= $data['total_interfaces'] ?></h3>
            <p>Interfaces WireGuard</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success);"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?= $data['active_peers'] ?> / <?= $data['total_peers'] ?></h3>
            <p>Peers Ativos</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success);"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?= count($data['connected_peers'] ?? []) ?></h3>
            <p>Peers Conectados</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--secondary);"><i class="fas fa-users-gear"></i></div>
        <div class="stat-info">
            <h3><?= $data['active_users'] ?></h3>
            <p>Usuários do Sistema</p>
        </div>
    </div>
</div>

<!-- Peers Conectados -->
<?php if (!empty($data['connected_peers'])): ?>
<div class="card" style="margin-bottom: 20px; border-left: 4px solid var(--success);">
    <div class="card-header">
        <h2 style="color: var(--success);"><i class="fas fa-check-circle"></i> Peers Conectados</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <table class="table">
            <thead>
                <tr>
                    <th>Peer</th>
                    <th>Interface</th>
                    <th>IP</th>
                    <th>Contato</th>
                    <th>Último Handshake</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['connected_peers'] as $cp): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($cp['peer_name']) ?></strong></td>
                    <td><?= htmlspecialchars($cp['interface_name']) ?></td>
                    <td><code><?= htmlspecialchars($cp['allowed_address']) ?></code></td>
                    <td><?= htmlspecialchars($cp['contact_name'] ?: '-') ?></td>
                    <td>
                        <span class="badge badge-success"><?= htmlspecialchars($cp['last_handshake']) ?> atrás</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

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

var colors = ['#2563eb', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
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

// Refresh
function refreshDashboard() {
    var btn = document.getElementById('refreshBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Coletando...';
    
    fetch('/dashboard/collect-traffic', { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Desconhecido'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> Atualizar Agora';
            }
        })
        .catch(function(err) {
            alert('Erro de conexão: ' + err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Atualizar Agora';
        });
}

// Auto-refresh a cada 30s
setTimeout(function() { window.location.reload(); }, 30000);
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
