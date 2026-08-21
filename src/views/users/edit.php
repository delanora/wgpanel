<?php $pageTitle = 'Editar Usuário - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-user-edit"></i> Editar Usuário</h1>
    <a href="/users" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="/users/update/<?= $user['id'] ?>" class="form">
            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Nome *</label>
                <input type="text" id="name" name="name" required 
                       value="<?= htmlspecialchars($user['name']) ?>">
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" id="email" name="email" required 
                       value="<?= htmlspecialchars($user['email']) ?>">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Nova Senha (deixe vazio para manter)</label>
                <input type="password" id="password" name="password" 
                       placeholder="••••••••" minlength="6">
            </div>
            
            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Perfil</label>
                <select id="role" name="role">
                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Usuário</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="active" <?= $user['active'] ? 'checked' : '' ?>>
                    <i class="fas fa-check-circle"></i> Usuário Ativo
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Atualizar
                </button>
                <a href="/users" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
