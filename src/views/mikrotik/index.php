<?php $pageTitle = 'Mikrotik - ' . APP_NAME; ?>
<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="page-header">
    <h1><i class="fas fa-tower-broadcast"></i> Mikrotik Router</h1>
</div>

<div class="card">
    <div class="card-header">
        <h2>Status da Conexão</h2>
    </div>
    <div class="card-body">
        <div class="status-info">
            <p><strong>Endereço:</strong> <?= MIKROTIK_API_URL ?></p>
            <p><strong>Porta:</strong> <?= MIKROTIK_API_PORT ?></p>
            <p>
                <strong>Status:</strong> 
                <?php if ($connected): ?>
                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Conectado</span>
                <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Desconectado</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="card-links">
            <a href="/mikrotik/interfaces" class="card-link">
                <i class="fas fa-network-wired"></i> Interfaces
            </a>
            <a href="/mikrotik/clients" class="card-link">
                <i class="fas fa-laptop"></i> Clientes Conectados
            </a>
            <a href="/mikrotik/logs" class="card-link">
                <i class="fas fa-list"></i> Logs
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-terminal"></i> Executar Comando</h2>
    </div>
    <div class="card-body">
        <form id="commandForm" class="form">
            <div class="form-group">
                <label for="command">Comando:</label>
                <input type="text" id="command" name="command" 
                       placeholder="Ex: system resource print" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-play"></i> Executar
            </button>
        </form>
        <div id="commandResult" class="command-result" style="display:none;">
            <pre id="commandOutput"></pre>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
