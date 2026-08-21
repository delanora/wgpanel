<?php $pageTitle = 'Peers - ' . $interface['name'] . ' - ' . APP_NAME; ?>
<?php require __DIR__ . '/../../layouts/header.php'; ?>

<div class="page-header">
    <h1>
        <i class="fas fa-users"></i> Peers
        <small style="font-size: 14px; color: var(--secondary); font-weight: normal;">
            — <?= htmlspecialchars($interface['name']) ?> (<?= htmlspecialchars($interface['client_name']) ?>)
        </small>
    </h1>
    <div style="display: flex; gap: 10px;">
        <a href="/wireguard" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Interfaces
        </a>
        <a href="/wireguard/peers/<?= $interface['id'] ?>/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Novo Peer
        </a>
    </div>
</div>

<!-- Info da Interface -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 15px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; font-size: 13px;">
            <div>
                <strong>Rede:</strong><br>
                <code><?= htmlspecialchars($interface['network_cidr']) ?></code>
            </div>
            <div>
                <strong>IP Servidor:</strong><br>
                <code><?= htmlspecialchars($interface['server_ip']) ?></code>
            </div>
            <div>
                <strong>Porta:</strong><br>
                <?= $interface['listen_port'] ?>
            </div>
            <div>
                <strong>Próximo IP:</strong><br>
                <code><?= htmlspecialchars($nextIp) ?></code>
            </div>
            <div>
                <strong>Public Key:</strong><br>
                <code style="font-size: 11px;"><?= htmlspecialchars(substr($interface['public_key'], 0, 24)) ?>...</code>
            </div>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <?php
    $messages = [
        'created' => 'Peer criado com sucesso!',
        'updated' => 'Peer atualizado com sucesso!',
        'deleted' => 'Peer removido com sucesso!',
    ];
    echo $messages[$_GET['success']] ?? 'Operação realizada com sucesso!';
    ?>
</div>
<?php endif; ?>

<?php if (empty($peers)): ?>
<div class="card">
    <div class="card-body text-center" style="padding: 60px 20px;">
        <i class="fas fa-user-plus" style="font-size: 48px; color: var(--gray); margin-bottom: 15px;"></i>
        <h3 style="color: var(--secondary); margin-bottom: 10px;">Nenhum peer nesta interface</h3>
        <p style="color: var(--secondary); margin-bottom: 20px;">Crie o primeiro peer para começar a conectar clientes.</p>
        <a href="/wireguard/peers/<?= $interface['id'] ?>/create" class="btn btn-primary">
            <i class="fas fa-plus"></i> Criar Primeiro Peer
        </a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Peer</th>
                    <th>Contato</th>
                    <th>IP Alocado</th>
                    <th>Status</th>
                    <th>Tráfego</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($peers as $peer): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($peer['peer_name']) ?></strong>
                        <?php if ($peer['notes']): ?>
                            <br><small class="text-muted" title="<?= htmlspecialchars($peer['notes']) ?>">
                                <?= htmlspecialchars(strlen($peer['notes']) > 40 ? substr($peer['notes'], 0, 40) . '...' : $peer['notes']) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($peer['contact_name']): ?>
                            <?= htmlspecialchars($peer['contact_name']) ?>
                            <?php if ($peer['contact_email']): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($peer['contact_email']) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($peer['allowed_address']) ?></code></td>
                    <td>
                        <?php if ($peer['status'] === 'disabled'): ?>
                            <span class="badge badge-warning">Desabilitado</span>
                        <?php elseif ($peer['connection_status'] === 'connected'): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-circle" style="font-size: 6px; vertical-align: middle;"></i> Conectado
                            </span>
                        <?php elseif ($peer['connection_status'] === 'never'): ?>
                            <span class="badge badge-secondary">Nunca conectou</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Desconectado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small>
                            <i class="fas fa-arrow-down" style="color: var(--success);"></i> <?= \App\Controller\WireguardPeerController::formatBytes($peer['rx']) ?>
                            <i class="fas fa-arrow-up" style="color: var(--info); margin-left: 8px;"></i> <?= \App\Controller\WireguardPeerController::formatBytes($peer['tx']) ?>
                        </small>
                    </td>
                    <td class="actions">
                        <a href="/wireguard/peers/config/<?= $peer['id'] ?>" class="btn btn-sm btn-primary" title="Ver Config">
                            <i class="fas fa-file-code"></i>
                        </a>
                        <a href="/wireguard/peers/edit/<?= $peer['id'] ?>" class="btn btn-sm btn-secondary" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="/wireguard/peers/delete/<?= $peer['id'] ?>" class="btn btn-sm btn-danger" title="Excluir"
                           onclick="return confirm('Excluir peer <?= htmlspecialchars($peer['peer_name']) ?>?')">
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

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
