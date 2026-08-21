<?php $pageTitle = 'Clientes - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-laptop"></i> Clientes Conectados</h1>
    <a href="/mikrotik" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($clients)): ?>
            <p class="text-center text-muted">Nenhum cliente encontrado ou erro na conexão.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>IP</th>
                    <th>MAC Address</th>
                    <th>Interface</th>
                    <th>Uptime</th>
                    <th>Tráfego</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?= htmlspecialchars($client['user'] ?? $client['name'] ?? '-') ?></td>
                    <td><code><?= htmlspecialchars($client['address'] ?? '-') ?></code></td>
                    <td><code><?= htmlspecialchars($client['mac-address'] ?? '-') ?></code></td>
                    <td><?= htmlspecialchars($client['interface'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($client['uptime'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($client['bytes-in'] ?? '-') ?> / <?= htmlspecialchars($client['bytes-out'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
