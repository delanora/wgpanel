<?php $pageTitle = 'Usuários - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-users"></i> Usuários</h1>
    <a href="/users/create" class="btn btn-primary">
        <i class="fas fa-plus"></i> Novo Usuário
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <?php
    $messages = [
        'created' => 'Usuário criado com sucesso!',
        'updated' => 'Usuário atualizado com sucesso!',
        'deleted' => 'Usuário excluído com sucesso!',
    ];
    echo $messages[$_GET['success']] ?? 'Operação realizada com sucesso!';
    ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'self_delete'): ?>
<div class="alert alert-error">Você não pode excluir sua própria conta!</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Perfil</th>
                    <th>Status</th>
                    <th>Último Login</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" class="text-center">Nenhum usuário encontrado</td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <span class="badge badge-<?= $user['role'] === 'admin' ? 'primary' : 'secondary' ?>">
                            <?= $user['role'] ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= $user['active'] ? 'success' : 'danger' ?>">
                            <?= $user['active'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca' ?></td>
                    <td class="actions">
                        <a href="/users/edit/<?= $user['id'] ?>" class="btn btn-ghost" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="/users/delete/<?= $user['id'] ?>" class="btn btn-ghost btn-danger" title="Excluir"
                           onclick="return confirm('Tem certeza que deseja excluir este usuário?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
