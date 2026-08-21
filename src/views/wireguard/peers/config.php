<?php $pageTitle = 'Config - ' . $peer['peer_name'] . ' - ' . APP_NAME; ?>
<?php require __DIR__ . '/../../layouts/header.php'; ?>

<!-- Breadcrumb Header -->
<div class="page-header">
    <div>
        <nav class="breadcrumb">
            <a href="/wireguard/peers/<?= $peer['interface_id'] ?>">Peers</a>
            <span class="breadcrumb-sep">/</span>
            <span class="breadcrumb-current"><?= htmlspecialchars($peer['peer_name']) ?></span>
        </nav>
        <h1>Config do Peer</h1>
    </div>
    <a href="/wireguard/peers/<?= $peer['interface_id'] ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<!-- Dados do Peer -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h2><i class="fas fa-circle-info"></i> Dados do Peer</h2>
    </div>
    <div class="card-body">
        <div class="peer-details">
            <div class="peer-detail">
                <span class="peer-detail-label">Peer</span>
                <span class="peer-detail-value"><?= htmlspecialchars($peer['peer_name']) ?></span>
            </div>
            <div class="peer-detail">
                <span class="peer-detail-label">IP Alocado</span>
                <span class="peer-detail-value"><code><?= htmlspecialchars($peer['allowed_address']) ?></code></span>
            </div>
            <div class="peer-detail">
                <span class="peer-detail-label">Interface</span>
                <span class="peer-detail-value"><?= htmlspecialchars($peer['interface_name']) ?></span>
            </div>
            <div class="peer-detail peer-detail-wide">
                <span class="peer-detail-label">Public Key</span>
                <span class="peer-detail-value peer-detail-mono"><?= htmlspecialchars($peer['public_key']) ?></span>
            </div>
            <div class="peer-detail peer-detail-wide">
                <span class="peer-detail-label">Private Key</span>
                <span class="peer-detail-value peer-detail-mono"><?= htmlspecialchars($peer['private_key']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Config Card -->
<div class="card">
    <div class="card-header" style="padding: 0; border-bottom: none;">
        <div class="tabs-header">
            <button class="tab active" onclick="showTab('linux')" id="tab-linux">
                <i class="fab fa-linux"></i> Linux / macOS / Android
            </button>
            <button class="tab" onclick="showTab('windows')" id="tab-windows">
                <i class="fab fa-windows"></i> Windows
            </button>
        </div>
    </div>
    <div class="card-body" style="padding-top: 0;">
        <!-- Linux -->
        <div id="config-linux" class="tab-content active">
            <div class="config-toolbar">
                <span class="config-toolbar-label">Config para importar no app WireGuard</span>
                <div class="config-toolbar-actions">
                    <button onclick="copyConfig('linux')" class="btn btn-ghost" id="copyBtn-linux" title="Copiar config">
                        <i class="fas fa-copy"></i>
                    </button>
                    <a href="/wireguard/peers/download/<?= $peer['id'] ?>?os=linux" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Download .conf
                    </a>
                </div>
            </div>
            <pre id="configText-linux" class="config-code"><?= htmlspecialchars($configLinux) ?></pre>
            <div id="copySuccess-linux" class="copy-success">
                <i class="fas fa-check-circle"></i> Copiado!
            </div>
        </div>

        <!-- Windows -->
        <div id="config-windows" class="tab-content">
            <div class="config-toolbar">
                <span class="config-toolbar-label">Config para Windows com rotas netsh</span>
                <div class="config-toolbar-actions">
                    <button onclick="copyConfig('windows')" class="btn btn-ghost" id="copyBtn-windows" title="Copiar config">
                        <i class="fas fa-copy"></i>
                    </button>
                    <a href="/wireguard/peers/download/<?= $peer['id'] ?>?os=windows" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Download .conf
                    </a>
                </div>
            </div>
            <pre id="configText-windows" class="config-code"><?= htmlspecialchars($configWindows) ?></pre>
            <div id="copySuccess-windows" class="copy-success">
                <i class="fas fa-check-circle"></i> Copiado!
            </div>
        </div>
    </div>
</div>


<script>
function showTab(os) {
    document.getElementById('config-linux').classList.toggle('active', os === 'linux');
    document.getElementById('config-windows').classList.toggle('active', os === 'windows');
    document.getElementById('tab-linux').classList.toggle('active', os === 'linux');
    document.getElementById('tab-windows').classList.toggle('active', os === 'windows');
}

function copyConfig(os) {
    var text = document.getElementById('configText-' + os).textContent;
    navigator.clipboard.writeText(text).then(function() {
        var btn = document.getElementById('copyBtn-' + os);
        btn.innerHTML = '<i class="fas fa-check"></i>';
        document.getElementById('copySuccess-' + os).style.display = 'block';
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-copy"></i>';
            document.getElementById('copySuccess-' + os).style.display = 'none';
        }, 3000);
    });
}
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
