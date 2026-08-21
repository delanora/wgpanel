<?php $pageTitle = 'WireGuard - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-shield-halved"></i> Interfaces WireGuard</h1>
    <a href="/wireguard/create" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nova Interface
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <?php
    $messages = [
        'created' => 'Interface criada com sucesso!',
        'updated' => 'Interface atualizada com sucesso!',
        'deleted' => 'Interface removida com sucesso!',
    ];
    echo $messages[$_GET['success']] ?? 'Operação realizada com sucesso!';
    ?>
</div>
<?php endif; ?>

<?php if (empty($interfaces)): ?>
<div class="card">
    <div class="card-body text-center" style="padding: 60px 20px;">
        <i class="fas fa-shield-halved" style="font-size: 48px; color: var(--gray); margin-bottom: 15px;"></i>
        <h3 style="color: var(--secondary); margin-bottom: 10px;">Nenhuma interface WireGuard</h3>
        <p style="color: var(--secondary); margin-bottom: 20px;">Clique no botão abaixo para criar a primeira interface.</p>
        <a href="/wireguard/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Criar Primeira Interface
        </a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Interface</th>
                    <th>Cliente</th>
                    <th>Rede</th>
                    <th>IP Servidor</th>
                    <th>Porta</th>
                    <th>Status</th>
                    <th>Peers</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interfaces as $iface): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($iface['name']) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars(substr($iface['public_key'], 0, 16)) ?>...</small>
                    </td>
                    <td><?= htmlspecialchars($iface['client_name'] ?: '-') ?></td>
                    <td><code><?= htmlspecialchars($iface['network_cidr']) ?></code></td>
                    <td><code><?= htmlspecialchars($iface['server_ip']) ?></code></td>
                    <td><?= $iface['listen_port'] ?></td>
                    <td>
                        <?php if ($iface['running']): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i> Rodando
                            </span>
                        <?php else: ?>
                            <span class="badge badge-danger">
                                <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i> Parado
                            </span>
                        <?php endif; ?>
                        <?php if ($iface['status'] === 'disabled'): ?>
                            <span class="badge badge-warning" style="margin-left: 4px;">Desabilitada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= $iface['peer_active_count'] ?> / <?= $iface['peer_count'] ?></span>
                    </td>
                    <td class="actions">
                        <a href="/wireguard/peers/<?= $iface['id'] ?>" class="btn btn-ghost" title="Gerenciar Peers">
                            <i class="fas fa-users"></i>
                        </a>
                        <a href="/wireguard/edit/<?= $iface['id'] ?>" class="btn btn-ghost" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="/wireguard/delete/<?= $iface['id'] ?>" class="btn btn-ghost btn-danger" title="Excluir"
                           onclick="return confirm('Tem certeza que deseja excluir a interface <?= htmlspecialchars($iface['name']) ?>? Isso removerá também todos os peers associados.')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
