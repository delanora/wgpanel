<?php $pageTitle = 'Novo Peer - ' . $interface['name'] . ' - ' . APP_NAME; ?>
<?php require __DIR__ . '/../../layouts/header.php'; ?>

<div class="page-header">
    <h1>
        <i class="fas fa-user-plus"></i> Novo Peer
        <small style="font-size: 14px; color: var(--secondary); font-weight: normal;">
            — <?= htmlspecialchars($interface['name']) ?>
        </small>
    </h1>
    <a href="/wireguard/peers/<?= $interface['id'] ?>" class="btn btn-secondary">
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
        <p>Preencha os dados do peer. O sistema gera automaticamente as chaves e configuração.</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/wireguard/peers/store/<?= $interface['id'] ?>" class="form" id="createForm">
            
            <div class="form-group">
                <label for="peer_name"><i class="fas fa-laptop"></i> Nome do Peer *</label>
                <input type="text" id="peer_name" name="peer_name" required 
                       placeholder="Ex: notebook-joao, celular-maria"
                       value="<?= htmlspecialchars($_POST['peer_name'] ?? '') ?>"
                       pattern="[a-zA-Z0-9_-]+"
                       title="Apenas letras, números, hífen e underscore">
                <small class="text-muted">Identificador do dispositivo (ex: notebook-joao)</small>
            </div>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="contact_name"><i class="fas fa-user"></i> Nome do Contato</label>
                    <input type="text" id="contact_name" name="contact_name" 
                           placeholder="Nome da pessoa"
                           value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="contact_email"><i class="fas fa-envelope"></i> E-mail do Contato</label>
                    <input type="email" id="contact_email" name="contact_email" 
                           placeholder="email@exemplo.com"
                           value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="allowed_address"><i class="fas fa-network-wired"></i> Allowed Address *</label>
                <input type="text" id="allowed_address" name="allowed_address" required 
                       value="<?= htmlspecialchars($_POST['allowed_address'] ?? $nextIp) ?>">
                <small class="text-muted">
                    IP sugerido: <strong><?= htmlspecialchars($nextIp) ?></strong>
                    (rede: <?= htmlspecialchars($interface['network_cidr']) ?>)
                </small>
            </div>
            
            <div class="form-group">
                <label for="additional_routes"><i class="fas fa-route"></i> Rotas Adicionais (Allowed IPs)</label>
                <input type="text" id="additional_routes" name="additional_routes" 
                       placeholder="Ex: 192.168.99.0/24, 10.0.0.0/8"
                       value="<?= htmlspecialchars($_POST['additional_routes'] ?? '') ?>">
                <small class="text-muted">
                    Redes adicionais que este peer acessará via VPN (separar por vírgula).<br>
                    Exemplos: <code>192.168.99.0/24</code>, <code>10.0.0.0/8</code>, <code>172.16.0.0/12</code><br>
                    Essas rotas são adicionadas tanto no <strong>AllowedIPs</strong> quanto nas rotas <strong>PostUp</strong> (Windows).
                </small>
            </div>
            
            <div class="form-group">
                <label for="notes"><i class="fas fa-sticky-note"></i> Observações</label>
                <textarea id="notes" name="notes" rows="3" 
                          placeholder="Informações adicionais sobre este peer..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
            
            <div class="card" style="background: var(--accent-bg); border: 1px solid var(--accent-border); margin-top: 10px;">
                <div class="card-body" style="padding: 15px;">
                    <h4 style="font-size: 14px; margin-bottom: 8px; color: var(--accent);">
                        <i class="fas fa-lightbulb"></i> O que acontecerá:
                    </h4>
                    <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--text-secondary);">
                        <li>Par de chaves (private/public) será gerado pelo Mikrotik automaticamente</li>
                        <li>A config completa do cliente será gerada (.conf pronta para importar)</li>
                        <li>Os dados serão salvos no banco para gerenciamento futuro</li>
                    </ol>
                </div>
            </div>
            
            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Criar Peer
                </button>
                <a href="/wireguard/peers/<?= $interface['id'] ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('createForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando peer no Mikrotik...';
});
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
