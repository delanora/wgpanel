<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="sidebar">
        <div class="sidebar-header">
            <h3><svg width="24" height="24" viewBox="0 0 24 24" fill="none" style="margin-right: 2px;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="currentColor" opacity="0.3"/><path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z" fill="currentColor"/></svg> WG Panel</h3>
        </div>
        <ul class="nav-menu">
            <li><a href="/dashboard" class="<?= $_SERVER['REQUEST_URI'] === '/dashboard' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a></li>
            <li><a href="/wireguard" class="<?= str_contains($_SERVER['REQUEST_URI'], '/wireguard') ? 'active' : '' ?>">
                <i class="fas fa-shield-halved"></i> WireGuard
            </a></li>
            <li><a href="/traffic" class="<?= str_contains($_SERVER['REQUEST_URI'], '/traffic') ? 'active' : '' ?>">
                <i class="fas fa-chart-area"></i> Tráfego
            </a></li>
            <li><a href="/users" class="<?= str_contains($_SERVER['REQUEST_URI'], '/users') ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Usuários
            </a></li>
            <li><a href="/profile" class="<?= $_SERVER['REQUEST_URI'] === '/profile' ? 'active' : '' ?>">
                <i class="fas fa-user-circle"></i> Perfil
            </a></li>
        </ul>
        <div class="sidebar-footer">
            <span class="user-info">
                <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
            </span>
            <a href="/logout" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="<?= isset($_SESSION['user_id']) ? 'with-sidebar' : 'full-page' ?>">
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
