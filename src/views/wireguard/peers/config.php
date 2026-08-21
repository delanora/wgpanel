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

<style>
/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-size: 13px;
}

.breadcrumb a {
    color: var(--text-muted);
    transition: color 0.15s;
}

.breadcrumb a:hover {
    color: var(--accent);
}

.breadcrumb-sep {
    color: var(--text-muted);
    font-size: 12px;
}

.breadcrumb-current {
    color: var(--text-secondary);
}

/* Peer Details */
.peer-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.peer-detail {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.peer-detail-wide {
    grid-column: 1 / -1;
}

.peer-detail-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
}

.peer-detail-value {
    font-size: 14px;
    color: var(--text-primary);
    font-weight: 500;
}

.peer-detail-mono {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--accent);
    word-break: break-all;
    background: var(--bg-elevated);
    border: 1px solid var(--border-subtle);
    padding: 8px 12px;
    border-radius: var(--radius-sm);
}

/* Tabs */
.tabs-header {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--border-subtle);
}

.tab {
    flex: 1;
    padding: 14px 20px;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 600;
    font-family: var(--font-sans);
    cursor: pointer;
    transition: all 0.15s;
    border-bottom: 2px solid transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab:hover {
    color: var(--text-secondary);
    background: var(--bg-hover);
}

.tab.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Config Toolbar */
.config-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-subtle);
}

.config-toolbar-label {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 500;
}

.config-toolbar-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

/* Copy Success */
.copy-success {
    display: none;
    margin-top: 8px;
    font-size: 12px;
    color: var(--success);
}

/* Config Code Block */
.config-code {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 24px;
    font-family: var(--font-mono);
    font-size: 13px;
    line-height: 1.7;
    color: var(--text-secondary);
    overflow-x: auto;
    white-space: pre;
    margin: 0;
}

.config-code .section {
    color: var(--accent);
    font-weight: 700;
}

.config-code .key {
    color: #94a3b8;
}

.config-code .value {
    color: var(--text-primary);
}
</style>

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
