<?php
namespace App\Middleware;

class AuthMiddleware {
    public function handle(): bool {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }
        
        // Verificar timeout da sessão
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            header('Location: /login?timeout=1');
            exit;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
}
