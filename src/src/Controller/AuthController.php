<?php
namespace App\Controller;

use App\Middleware\AuthMiddleware;

class AuthController {
    public array $_routeParams = [];
    
    public function login(): void {
        if (isset($_SESSION['user_id'])) {
            header('Location: /dashboard');
            exit;
        }
        
        require __DIR__ . '/../../views/auth/login.php';
    }
    
    public function doLogin(): void {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        if (!$email || !$password) {
            $error = 'Preencha todos os campos';
            require __DIR__ . '/../../views/auth/login.php';
            return;
        }
        
        // Buscar usuário no banco
        $user = \Database::fetch(
            'SELECT * FROM users WHERE email = ? AND active = true',
            [$email]
        );
        
        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Email ou senha inválidos';
            require __DIR__ . '/../../views/auth/login.php';
            return;
        }
        
        // Login bem-sucedido
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        // Atualizar último login
        \Database::update('users', [
            'last_login' => date('Y-m-d H:i:s')
        ], 'id = ?', [$user['id']]);
        
        header('Location: /dashboard');
        exit;
    }
    
    public function logout(): void {
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }
}
