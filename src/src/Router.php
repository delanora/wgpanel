<?php
/**
 * Router simples para PHP
 */

class Router {
    private array $routes = [];
    private array $middleware = [];
    
    public function get(string $path, callable|array $handler, array $middleware = []): self {
        $this->routes['GET'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        return $this;
    }
    
    public function post(string $path, callable|array $handler, array $middleware = []): self {
        $this->routes['POST'][$path] = ['handler' => $handler, 'middleware' => $middleware];
        return $this;
    }
    
    public function group(string $prefix, callable $callback, array $middleware = []): self {
        $previousPrefix = $this->currentPrefix ?? '';
        $this->currentPrefix = $previousPrefix . $prefix;
        
        $callback($this);
        
        $this->currentPrefix = $previousPrefix;
        return $this;
    }
    
    private string $currentPrefix = '';
    
    public function dispatch(string $uri): void {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Buscar rota correspondente
        foreach ($this->routes[$method] ?? [] as $path => $route) {
            $fullPath = $this->currentPrefix . $path;
            
            $routeParams = $this->matchRoute($fullPath, $uri);
            if ($routeParams !== null) {
                // Executar middlewares
                foreach ($route['middleware'] as $mw) {
                    $middlewareClass = "App\\Middleware\\{$mw}Middleware";
                    if (class_exists($middlewareClass)) {
                        $middleware = new $middlewareClass();
                        if (!$middleware->handle()) {
                            return;
                        }
                    }
                }
                
                // Executar handler
                $this->callHandler($route['handler'], $uri, $routeParams);
                return;
            }
        }
        
        // 404
        http_response_code(404);
        require __DIR__ . '/../../views/404.php';
    }
    
    private function matchRoute(string $pattern, string $uri): ?array {
        // Converter pattern para regex
        $regex = str_replace('/', '\/', $pattern);
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^\/]+)', $regex);
        $regex = '/^' . $regex . '$/';
        
        if (preg_match($regex, $uri, $matches)) {
            // Filtrar apenas parâmetros nomeados (chaves string)
            return array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
        }
        return null;
    }
    
    private function callHandler(callable|array $handler, string $uri, array $params = []): void {
        if (is_array($handler)) {
            $controllerClass = $handler[0];
            $method = $handler[1];
            $controller = new $controllerClass();
            // Injetar parâmetros da rota no controller
            $controller->_routeParams = $params;
            $controller->$method();
        } elseif (is_callable($handler)) {
            call_user_func_array($handler, array_values($params));
        }
    }
}
