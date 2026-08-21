<?php $pageTitle = 'Interfaces - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-network-wired"></i> Interfaces</h1>
    <a href="/mikrotik" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($interfaces)): ?>
            <p class="text-center text-muted">Nenhuma interface encontrada ou erro na conexão.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>MAC Address</th>
                    <th>Tx Rate</th>
                    <th>Rx Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interfaces as $iface): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($iface['name'] ?? '-') ?></strong></td>
                    <td><?= htmlspecialchars($iface['type'] ?? '-') ?></td>
                    <td>
                        <?php if (($iface['disabled'] ?? true) === false): ?>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Ativo</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-times"></i> Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($iface['mac-address'] ?? '-') ?></code></td>
                    <td><?= htmlspecialchars($iface['tx-rate'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($iface['rx-rate'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
