<?php $pageTitle = 'Meu Perfil - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-user-circle"></i> Meu Perfil</h1>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>
<div class="alert alert-success">Perfil atualizado com sucesso!</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/profile/update" class="form">
            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Nome *</label>
                <input type="text" id="name" name="name" required 
                       value="<?= htmlspecialchars($user['name']) ?>">
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="text" disabled value="<?= htmlspecialchars($user['email']) ?>">
                <small class="text-muted">O email não pode ser alterado</small>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-user-tag"></i> Perfil</label>
                <input type="text" disabled value="<?= ucfirst($user['role']) ?>">
            </div>
            
            <hr>
            <h3>Trocar Senha</h3>
            
            <div class="form-group">
                <label for="current_password"><i class="fas fa-lock"></i> Senha Atual</label>
                <input type="password" id="current_password" name="current_password" 
                       placeholder="Digite sua senha atual">
            </div>
            
            <div class="form-group">
                <label for="new_password"><i class="fas fa-key"></i> Nova Senha</label>
                <input type="password" id="new_password" name="new_password" 
                       placeholder="Nova senha (mínimo 6 caracteres)" minlength="6">
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
