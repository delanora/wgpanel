<?php $pageTitle = 'Editar Peer - ' . $peer['peer_name'] . ' - ' . APP_NAME; ?>
<?php require __DIR__ . '/../../layouts/header.php'; ?>

<div class="page-header">
    <h1>
        <i class="fas fa-edit"></i> Editar Peer
        <small style="font-size: 14px; color: var(--secondary); font-weight: normal;">
            — <?= htmlspecialchars($peer['peer_name']) ?>
        </small>
    </h1>
    <a href="/wireguard/peers/<?= $peer['interface_id'] ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?= htmlspecialchars($peer['peer_name']) ?></h2>
    </div>
    <div class="card-body">
        <form method="POST" action="/wireguard/peers/update/<?= $peer['id'] ?>" class="form">
            
            <div class="form-group">
                <label><i class="fas fa-laptop"></i> Nome do Peer</label>
                <input type="text" disabled value="<?= htmlspecialchars($peer['peer_name']) ?>">
                <small class="text-muted">O nome do peer não pode ser alterado</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-network-wired"></i> Allowed Address</label>
                <input type="text" disabled value="<?= htmlspecialchars($peer['allowed_address']) ?>">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-key"></i> Public Key</label>
                <input type="text" disabled value="<?= htmlspecialchars($peer['public_key']) ?>">
            </div>
            
            <hr>
            
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="contact_name"><i class="fas fa-user"></i> Nome do Contato</label>
                    <input type="text" id="contact_name" name="contact_name" 
                           value="<?= htmlspecialchars($peer['contact_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="contact_email"><i class="fas fa-envelope"></i> E-mail do Contato</label>
                    <input type="email" id="contact_email" name="contact_email" 
                           value="<?= htmlspecialchars($peer['contact_email']) ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                <select id="status" name="status">
                    <option value="active" <?= $peer['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="disabled" <?= $peer['status'] === 'disabled' ? 'selected' : '' ?>>Desabilitado</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="additional_routes"><i class="fas fa-route"></i> Rotas Adicionais (Allowed IPs)</label>
                <input type="text" id="additional_routes" name="additional_routes" 
                       value="<?= htmlspecialchars($peer['additional_routes'] ?? '') ?>"
                       placeholder="Ex: 192.168.99.0/24, 10.0.0.0/8">
                <small class="text-muted">
                    Redes adicionais (separar por vírgula). Atualizam AllowedIPs e PostUp/PostDown.
                </small>
            </div>
            
            <div class="form-group">
                <label for="notes"><i class="fas fa-sticky-note"></i> Observações</label>
                <textarea id="notes" name="notes" rows="3"><?= htmlspecialchars($peer['notes']) ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="/wireguard/peers/<?= $peer['interface_id'] ?>" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
