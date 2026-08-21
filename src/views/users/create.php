<?php $pageTitle = 'Novo Usuário - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-user-plus"></i> Novo Usuário</h1>
    <a href="/users" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/users/store" class="form">
            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Nome *</label>
                <input type="text" id="name" name="name" required 
                       placeholder="Nome completo" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" id="email" name="email" required 
                       placeholder="email@exemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Senha *</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Mínimo 6 caracteres" minlength="6">
            </div>
            
            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Perfil</label>
                <select id="role" name="role">
                    <option value="user">Usuário</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar
                </button>
                <a href="/users" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
