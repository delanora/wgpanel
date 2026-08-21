<?php $pageTitle = 'Config - ' . $peer['peer_name'] . ' - ' . APP_NAME; ?>
<?php require __DIR__ . '/../../layouts/header.php'; ?>

<div class="page-header">
    <h1>
        <i class="fas fa-file-code"></i> Config do Peer
        <small style="font-size: 14px; color: var(--secondary); font-weight: normal;">
            — <?= htmlspecialchars($peer['peer_name']) ?>
        </small>
    </h1>
    <div style="display: flex; gap: 10px;">
        <a href="/wireguard/peers/<?= $peer['interface_id'] ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<!-- Dados do Peer -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h2><i class="fas fa-info-circle"></i> Dados do Peer</h2>
    </div>
    <div class="card-body">
        <div style="display: flex; flex-wrap: wrap; gap: 20px 40px; font-size: 13px;">
            <div><strong>Peer:</strong> <?= htmlspecialchars($peer['peer_name']) ?></div>
            <div><strong>IP:</strong> <code><?= htmlspecialchars($peer['allowed_address']) ?></code></div>
            <div><strong>Interface:</strong> <?= htmlspecialchars($peer['interface_name']) ?></div>
            <div><strong>Pub Key:</strong> <code><?= htmlspecialchars($peer['public_key']) ?></code></div>
            <div><strong>Pvt Key:</strong> <code><?= htmlspecialchars($peer['private_key']) ?></code></div>
        </div>
    </div>
</div>

<!-- Tabs Linux / Windows -->
<div class="card">
    <div class="card-header" style="padding: 0;">
        <div style="display: flex; border-bottom: 1px solid var(--gray);">
            <button class="tab-btn active" onclick="showTab('linux')" id="tab-linux" style="flex:1; padding:12px; border:none; background:transparent; cursor:pointer; font-weight:600; border-bottom:3px solid var(--primary);">
                <i class="fab fa-linux"></i> Linux / macOS / Android
            </button>
            <button class="tab-btn" onclick="showTab('windows')" id="tab-windows" style="flex:1; padding:12px; border:none; background:transparent; cursor:pointer; font-weight:600; color:var(--secondary); border-bottom:3px solid transparent;">
                <i class="fab fa-windows"></i> Windows
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Linux -->
        <div id="config-linux">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span style="font-size: 13px; color: var(--secondary);">Config para importar no app WireGuard (Linux/macOS/Android/iOS)</span>
                <div style="display: flex; gap: 8px;">
                    <button onclick="copyConfig('linux')" class="btn btn-sm btn-secondary" id="copyBtn-linux">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                    <a href="/wireguard/peers/download/<?= $peer['id'] ?>?os=linux" class="btn btn-sm btn-primary">
                        <i class="fas fa-download"></i> Download .conf
                    </a>
                </div>
            </div>
            <pre id="configText-linux" style="background: var(--dark); color: #22c55e; padding: 20px; border-radius: var(--radius); font-size: 13px; overflow-x: auto; white-space: pre-wrap; margin: 0;"><?= htmlspecialchars($configLinux) ?></pre>
            <div id="copySuccess-linux" style="display: none; margin-top: 8px; color: var(--success); font-size: 13px;">
                <i class="fas fa-check-circle"></i> Copiado!
            </div>
        </div>
        
        <!-- Windows -->
        <div id="config-windows" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span style="font-size: 13px; color: var(--secondary);">Config para Windows (inclui rotas netsh para roaming correto)</span>
                <div style="display: flex; gap: 8px;">
                    <button onclick="copyConfig('windows')" class="btn btn-sm btn-secondary" id="copyBtn-windows">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                    <a href="/wireguard/peers/download/<?= $peer['id'] ?>?os=windows" class="btn btn-sm btn-primary">
                        <i class="fas fa-download"></i> Download .conf
                    </a>
                </div>
            </div>
            <pre id="configText-windows" style="background: var(--dark); color: #22c55e; padding: 20px; border-radius: var(--radius); font-size: 13px; overflow-x: auto; white-space: pre-wrap; margin: 0;"><?= htmlspecialchars($configWindows) ?></pre>
            <div id="copySuccess-windows" style="display: none; margin-top: 8px; color: var(--success); font-size: 13px;">
                <i class="fas fa-check-circle"></i> Copiado!
            </div>
        </div>
    </div>
</div>

<script>
// Tabs
function showTab(os) {
    document.getElementById('config-linux').style.display = os === 'linux' ? 'block' : 'none';
    document.getElementById('config-windows').style.display = os === 'windows' ? 'block' : 'none';
    
    document.getElementById('tab-linux').style.borderBottomColor = os === 'linux' ? 'var(--primary)' : 'transparent';
    document.getElementById('tab-linux').style.color = os === 'linux' ? 'inherit' : 'var(--secondary)';
    document.getElementById('tab-windows').style.borderBottomColor = os === 'windows' ? 'var(--primary)' : 'transparent';
    document.getElementById('tab-windows').style.color = os === 'windows' ? 'inherit' : 'var(--secondary)';
}

// Copy
function copyConfig(os) {
    var text = document.getElementById('configText-' + os).textContent;
    navigator.clipboard.writeText(text).then(function() {
        var btn = document.getElementById('copyBtn-' + os);
        btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
        document.getElementById('copySuccess-' + os).style.display = 'block';
        setTimeout(function() {
            btn.innerHTML = '<i class="fas fa-copy"></i> Copiar';
            document.getElementById('copySuccess-' + os).style.display = 'none';
        }, 3000);
    });
}
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
