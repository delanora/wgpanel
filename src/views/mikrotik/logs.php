<?php $pageTitle = 'Logs - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-list"></i> Logs do Mikrotik</h1>
    <a href="/mikrotik" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <p class="text-center text-muted">Nenhum log encontrado ou erro na conexão.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Nível</th>
                    <th>Topics</th>
                    <th>Mensagem</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['time'] ?? '-') ?></td>
                    <td>
                        <?php
                        $level = $log['level'] ?? 'info';
                        $badgeClass = match($level) {
                            'error', 'critical' => 'danger',
                            'warning' => 'warning',
                            'info' => 'info',
                            default => 'secondary',
                        };
                        ?>
                        <span class="badge badge-<?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($level)) ?></span>
                    </td>
                    <td><?= htmlspecialchars($log['topics'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($log['message'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
