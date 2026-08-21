<?php $pageTitle = 'Login - ' . APP_NAME; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="fas fa-tower-broadcast"></i>
                <h1>Mikrotik Manager</h1>
                <p>Faça login para continuar</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['timeout'])): ?>
            <div class="alert alert-warning">Sua sessão expirou. Faça login novamente.</div>
            <?php endif; ?>
            
            <form method="POST" action="/login" class="login-form">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="seu@email.com" autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Senha</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="••••••••" autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sign-in-alt"></i> Entrar
                </button>
            </form>
        </div>
    </div>
</body>
</html>
