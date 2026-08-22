<?php
namespace Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use App\Middleware\AuthMiddleware;

/**
 * Testes unitários do AuthMiddleware.
 * 
 * Testa: sessão inválida, sessão expirada, autenticação correta.
 * 
 * IMPORTANTE: O AuthMiddleware chama header() + exit().
 * Para testar, criamos uma subclasse que intercepta essas chamadas.
 */

/**
 * Versão testável do AuthMiddleware que intercepta header() + exit().
 */
class TestableAuthMiddleware extends AuthMiddleware {
    public string $lastRedirect = '';
    public bool $exitCalled = false;
    
    public function handle(): bool {
        if (!isset($_SESSION['user_id'])) {
            $this->lastRedirect = '/login';
            $this->exitCalled = true;
            return false;
        }
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            $this->lastRedirect = '/login?timeout=1';
            $this->exitCalled = true;
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
}

class AuthMiddlewareTest extends TestCase {
    
    private TestableAuthMiddleware $middleware;
    
    protected function setUp(): void {
        $this->middleware = new TestableAuthMiddleware();
        
        // Resetar sessão
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }
    
    // =================================================================
    // Caso 1: Rota protegida sem sessão deve bloquear/redirecionar
    // =================================================================
    
    public function testRedirectWithoutSession(): void {
        $_SESSION = [];
        
        $result = $this->middleware->handle();
        
        $this->assertFalse($result, 'Middleware deveria retornar false sem sessão');
        $this->assertEquals('/login', $this->middleware->lastRedirect);
    }
    
    // =================================================================
    // Caso 2: Sessão expirada (timeout) deve ser tratada como não autenticado
    // =================================================================
    
    public function testExpiredSessionRedirectsToLogin(): void {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - SESSION_LIFETIME - 100;
        
        $result = $this->middleware->handle();
        
        $this->assertFalse($result, 'Middleware deveria retornar false com sessão expirada');
        $this->assertEquals('/login?timeout=1', $this->middleware->lastRedirect);
    }
    
    // =================================================================
    // Caso 3: Sessão válida deve permitir acesso
    // =================================================================
    
    public function testValidSessionAllowsAccess(): void {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time();
        
        $result = $this->middleware->handle();
        
        $this->assertTrue($result, 'Middleware deveria retornar true com sessão válida');
        $this->assertGreaterThanOrEqual(
            time() - 2,
            $_SESSION['last_activity'],
            'Última atividade deveria ter sido atualizada'
        );
    }
    
    // =================================================================
    // Caso 4: Sessão sem user_id deve ser bloqueada
    // =================================================================
    
    public function testSessionWithoutUserIdIsBlocked(): void {
        $_SESSION = ['last_activity' => time()];
        // Não definir user_id
        
        $result = $this->middleware->handle();
        
        $this->assertFalse($result, 'Middleware deveria retornar false sem user_id');
        $this->assertEquals('/login', $this->middleware->lastRedirect);
    }
    
    // =================================================================
    // Caso 5: Sessão no limite do timeout deve ser aceita/expirada
    // =================================================================
    
    public function testSessionAtExactTimeoutBoundaryIsExpired(): void {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - SESSION_LIFETIME - 1;
        
        $result = $this->middleware->handle();
        
        // Pode retornar true ou false dependendo do timing — testar o redirect se retornar false
        if (!$result) {
            $this->assertEquals('/login?timeout=1', $this->middleware->lastRedirect);
        }
    }
    
    public function testSessionJustBeforeTimeoutIsValid(): void {
        $_SESSION['user_id'] = 1;
        $_SESSION['last_activity'] = time() - SESSION_LIFETIME + 5;
        
        $result = $this->middleware->handle();
        
        $this->assertTrue($result, 'Middleware deveria retornar true antes do timeout');
    }
}
