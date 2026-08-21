<?php $pageTitle = 'Nova Interface WireGuard - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <div>
        <nav class="breadcrumb">
            <a href="/wireguard">WireGuard</a>
            <span class="breadcrumb-sep">/</span>
            <span class="breadcrumb-current">Nova Interface</span>
        </nav>
        <h1><i class="fas fa-plus-circle"></i> Nova Interface</h1>
    </div>
    <a href="/wireguard" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-info-circle"></i> Informações</h2>
    </div>
    <div class="card-body">
        <p>Preencha os dados abaixo para criar uma nova interface WireGuard. 
           Alguns campos são sugeridos automaticamente com base nas interfaces já existentes.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/wireguard/store" class="form" id="createForm">
            
            <div class="form-group">
                <label for="client_name"><i class="fas fa-building"></i> Nome do Cliente / Empresa *</label>
                <input type="text" id="client_name" name="client_name" required 
                       placeholder="Ex: Empresa ABC, João Silva"
                       value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>">
                <small class="text-muted">Nome identificador do dono desta interface</small>
            </div>
            
            <div class="form-group">
                <label for="name"><i class="fas fa-network-wired"></i> Nome da Interface *</label>
                <input type="text" id="name" name="name" required 
                       placeholder="Ex: wg-empresa-abc"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       pattern="[a-zA-Z0-9_-]+"
                       title="Apenas letras, números, hífen e underscore">
                <small class="text-muted">Nome que aparecerá no Mikrotik (ex: wg-cliente-fulano)</small>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="listen_port"><i class="fas fa-plug"></i> Porta de Escuta *</label>
                    <input type="number" id="listen_port" name="listen_port" required 
                           min="1" max="65535"
                           value="<?= htmlspecialchars($_POST['listen_port'] ?? $suggestions['next_port']) ?>">
                    <small class="text-muted">
                        Porta sugerida: <strong><?= $suggestions['next_port'] ?></strong>
                        (portas em uso: <?= implode(', ', $suggestions['used_ports']) ?: 'nenhuma' ?>)
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="network_cidr"><i class="fas fa-project-diagram"></i> Rede (CIDR) *</label>
                    <input type="text" id="network_cidr" name="network_cidr" required 
                           placeholder="Ex: 10.10.1.0/24"
                           value="<?= htmlspecialchars($_POST['network_cidr'] ?? $suggestions['next_cidr']) ?>"
                           pattern="^\d+\.\d+\.\d+\.\d+/\d{1,2}$">
                    <small class="text-muted">
                        Rede sugerida: <strong><?= $suggestions['next_cidr'] ?></strong>
                    </small>
                </div>
            </div>
            
            <div class="card" style="background: var(--accent-bg); border: 1px solid var(--accent-border); margin-top: 10px;">
                <div class="card-body" style="padding: 15px;">
                    <h4 style="font-size: 14px; margin-bottom: 8px; color: var(--accent);">
                        <i class="fas fa-lightbulb"></i> O que acontecerá:
                    </h4>
                    <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--text-secondary);">
                        <li>Uma interface WireGuard será criada no Mikrotik</li>
                        <li>O IP do servidor (primeiro IP da rede) será atribuído automaticamente</li>
                        <li>A public key será salva no banco de dados</li>
                        <li>A interface ficará ativa e pronta para receber peers</li>
                    </ol>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Criar Interface
                </button>
                <a href="/wireguard" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-gerar nome da interface a partir do nome do cliente
document.getElementById('client_name').addEventListener('input', function() {
    const nameField = document.getElementById('name');
    if (!nameField.dataset.manual) {
        const slug = this.value
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        nameField.value = slug ? 'wg-' + slug : '';
    }
});

document.getElementById('name').addEventListener('input', function() {
    this.dataset.manual = this.value !== '' ? '1' : '';
});

// Loading ao enviar
document.getElementById('createForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando interface no Mikrotik...';
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
