<?php $pageTitle = 'Editar Interface WireGuard - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <div>
        <nav class="breadcrumb">
            <a href="/wireguard">WireGuard</a>
            <span class="breadcrumb-sep">/</span>
            <span class="breadcrumb-current">Editar</span>
        </nav>
        <h1><i class="fas fa-edit"></i> Editar Interface</h1>
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
        <h2><?= htmlspecialchars($interface['name']) ?></h2>
    </div>
    <div class="card-body">
        <form method="POST" action="/wireguard/update/<?= $interface['id'] ?>" class="form">
            
            <div class="form-group">
                <label><i class="fas fa-network-wired"></i> Nome da Interface</label>
                <input type="text" disabled value="<?= htmlspecialchars($interface['name']) ?>">
                <small class="text-muted">O nome da interface não pode ser alterado</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-plug"></i> Porta de Escuta</label>
                <input type="text" disabled value="<?= $interface['listen_port'] ?>">
                <small class="text-muted">A porta não pode ser alterada após a criação</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-project-diagram"></i> Rede (CIDR)</label>
                <input type="text" disabled value="<?= htmlspecialchars($interface['network_cidr']) ?>">
                <small class="text-muted">
                    <i class="fas fa-lock"></i> 
                    O CIDR não pode ser alterado para evitar conflito com peers já existentes.
                    Se precisar mudar a rede, crie uma nova interface.
                </small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-key"></i> Public Key</label>
                <input type="text" disabled value="<?= htmlspecialchars($interface['public_key']) ?>">
                <small class="text-muted">Chave gerada automaticamente pelo Mikrotik</small>
            </div>
            
            <hr>
            
            <div class="form-group">
                <label for="client_name"><i class="fas fa-building"></i> Nome do Cliente / Empresa</label>
                <input type="text" id="client_name" name="client_name" 
                       value="<?= htmlspecialchars($interface['client_name']) ?>"
                       placeholder="Nome do cliente">
            </div>
            
            <div class="form-group">
                <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                <select id="status" name="status">
                    <option value="active" <?= $interface['status'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                    <option value="disabled" <?= $interface['status'] === 'disabled' ? 'selected' : '' ?>>Desabilitado</option>
                </select>
                <small class="text-muted">
                    <?php if ($interface['running']): ?>
                        <i class="fas fa-circle" style="color: var(--success); font-size: 8px;"></i> 
                        Status atual no Mikrotik: <strong>Rodando</strong>
                    <?php else: ?>
                        <i class="fas fa-circle" style="color: var(--danger); font-size: 8px;"></i> 
                        Status atual no Mikrotik: <strong>Parado</strong>
                    <?php endif; ?>
                </small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="/wireguard" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
