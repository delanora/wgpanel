<?php $pageTitle = 'Tráfego - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-chart-area"></i> Tráfego WireGuard</h1>
    <button onclick="refreshChart()" class="btn btn-secondary" id="refreshBtn">
        <i class="fas fa-sync-alt"></i> Atualizar
    </button>
</div>

<!-- Filtros -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" action="/traffic" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                <label for="interface_id"><i class="fas fa-shield-halved"></i> Interface</label>
                <select id="interface_id" name="interface_id" onchange="this.form.submit()">
                    <?php foreach ($interfaces as $iface): ?>
                    <option value="<?= $iface['id'] ?>" <?= $iface['id'] == $selectedInterface ? 'selected' : '' ?>>
                        <?= htmlspecialchars($iface['name']) ?> (<?= htmlspecialchars($iface['client_name']) ?>)
                    </option>
                    <?php endforeach; ?>
                    <?php if (empty($interfaces)): ?>
                    <option value="0" disabled>Nenhuma interface configurada</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin: 0;">
                <label><i class="fas fa-clock"></i> Período</label>
                <div style="display: flex; gap: 4px;">
                    <?php
                    $periods = [
                        '1h' => '1h', '6h' => '6h', '24h' => '24h',
                        '7d' => '7d', '30d' => '30d', '90d' => '90d', '180d' => '6m', '365d' => '1a',
                    ];
                    foreach ($periods as $pValue => $pLabel):
                    ?>
                    <a href="?interface_id=<?= $selectedInterface ?>&period=<?= $pValue ?>" 
                       class="btn btn-sm <?= $period === $pValue ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $pLabel ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Resumo -->
<?php if ($selectedInterface > 0): ?>
<div class="stats-grid" style="margin-bottom: 20px;">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--success);"><i class="fas fa-arrow-down"></i></div>
        <div class="stat-info">
            <h3><?= \App\Controller\WireguardPeerController::formatBytes((int) $summary['total_rx']) ?></h3>
            <p>Total Recebido (RX)</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--info);"><i class="fas fa-arrow-up"></i></div>
        <div class="stat-info">
            <h3><?= \App\Controller\WireguardPeerController::formatBytes((int) $summary['total_tx']) ?></h3>
            <p>Total Enviado (TX)</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--warning);"><i class="fas fa-bolt"></i></div>
        <div class="stat-info">
            <h3><?= \App\Controller\WireguardPeerController::formatBytes((int) $summary['peak_rx']) ?></h3>
            <p>Pico RX</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary);"><i class="fas fa-bolt"></i></div>
        <div class="stat-info">
            <h3><?= \App\Controller\WireguardPeerController::formatBytes((int) $summary['peak_tx']) ?></h3>
            <p>Pico TX</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Gráfico -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-chart-area"></i> Tráfego por Hora</h2>
    </div>
    <div class="card-body">
        <?php if ($selectedInterface <= 0): ?>
            <p class="text-center text-muted" style="padding: 40px 0;">Selecione uma interface para visualizar.</p>
        <?php elseif (empty($chartData)): ?>
            <p class="text-center text-muted" style="padding: 40px 0;">
                Nenhum dado de tráfego para este período.<br>
                A coleta automática roda a cada 5 minutos.
            </p>
        <?php else: ?>
            <div style="position: relative; height: 350px;">
                <canvas id="trafficChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($chartData)): ?>
var chartData = <?= json_encode($chartData) ?>;
var labels = chartData.map(function(r) { return r.hour.replace('T', ' ').substring(0, 16); });
var rxData = chartData.map(function(r) { return parseInt(r.rx); });
var txData = chartData.map(function(r) { return parseInt(r.tx); });

var chart = new Chart(document.getElementById('trafficChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Recebido (RX)',
                data: rxData,
                borderColor: '#39ff14',
                backgroundColor: 'rgba(57, 255, 20, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2,
                pointRadius: 2,
            },
            {
                label: 'Enviado (TX)',
                data: txData,
                borderColor: '#6ec6ff',
                backgroundColor: 'rgba(110, 198, 255, 0.1)',
                fill: true,
                tension: 0.3,
                borderWidth: 2,
                pointRadius: 2,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 12 } } },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        var bytes = ctx.parsed.y;
                        var formatted;
                        if (bytes < 1024) formatted = bytes + ' B';
                        else if (bytes < 1048576) formatted = (bytes/1024).toFixed(1) + ' KB';
                        else if (bytes < 1073741824) formatted = (bytes/1048576).toFixed(1) + ' MB';
                        else formatted = (bytes/1073741824).toFixed(2) + ' GB';
                        return ctx.dataset.label + ': ' + formatted;
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
                beginAtZero: true,
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
<?php endif; ?>

function refreshChart() {
    var btn = document.getElementById('refreshBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Atualizando...';
    window.location.reload();
}
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
