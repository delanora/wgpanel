<?php
/**
 * Rotas da aplicação
 * 
 * Format: $router->method('/path', ['Controller', 'method']);
 */

// Carregar classes
require_once __DIR__ . '/../src/Exception/MikrotikApiException.php';
require_once __DIR__ . '/../src/Service/HttpTransport.php';
require_once __DIR__ . '/../src/Service/CurlTransport.php';
require_once __DIR__ . '/../src/Service/MikrotikClient.php';
require_once __DIR__ . '/../src/Controller/AuthController.php';
require_once __DIR__ . '/../src/Controller/DashboardController.php';
require_once __DIR__ . '/../src/Controller/UserController.php';
require_once __DIR__ . '/../src/Controller/MikrotikController.php';
require_once __DIR__ . '/../src/Controller/WireguardInterfaceController.php';
require_once __DIR__ . '/../src/Controller/WireguardPeerController.php';
require_once __DIR__ . '/../src/Controller/TrafficController.php';
require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';

use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\UserController;
use App\Controller\MikrotikController;
use App\Controller\WireguardInterfaceController;
use App\Controller\WireguardPeerController;
use App\Controller\TrafficController;

$router = new Router();

// ============================
// Rotas Públicas (sem auth)
// ============================
$router->get('/', [AuthController::class, 'login']);
$router->get('/login', [AuthController::class, 'login']);
$router->post('/login', [AuthController::class, 'doLogin']);
$router->get('/logout', [AuthController::class, 'logout']);

// ============================
// Rotas Protegidas (precisam de login)
// ============================
$router->get('/dashboard', [DashboardController::class, 'index'], ['Auth']);
$router->post('/dashboard/collect-traffic', [DashboardController::class, 'collectTraffic'], ['Auth']);
$router->post('/dashboard/collect-latency', [DashboardController::class, 'collectLatency'], ['Auth']);
$router->get('/users', [UserController::class, 'index'], ['Auth']);
$router->get('/users/create', [UserController::class, 'create'], ['Auth']);
$router->post('/users/store', [UserController::class, 'store'], ['Auth']);
$router->get('/users/edit/{id}', [UserController::class, 'edit'], ['Auth']);
$router->post('/users/update/{id}', [UserController::class, 'update'], ['Auth']);
$router->get('/users/delete/{id}', [UserController::class, 'delete'], ['Auth']);

// Perfil do Usuário
$router->get('/profile', [UserController::class, 'profile'], ['Auth']);
$router->post('/profile/update', [UserController::class, 'updateProfile'], ['Auth']);

// Mikrotik
$router->get('/mikrotik', [MikrotikController::class, 'index'], ['Auth']);
$router->get('/mikrotik/interfaces', [MikrotikController::class, 'interfaces'], ['Auth']);
$router->get('/mikrotik/clients', [MikrotikController::class, 'clients'], ['Auth']);
$router->get('/mikrotik/logs', [MikrotikController::class, 'logs'], ['Auth']);
$router->post('/mikrotik/command', [MikrotikController::class, 'runCommand'], ['Auth']);

// Rota de teste da API (debug)
$router->get('/mikrotik/test-api', [MikrotikController::class, 'testApi'], ['Auth']);

// WireGuard Interfaces
$router->get('/wireguard', [WireguardInterfaceController::class, 'index'], ['Auth']);
$router->get('/wireguard/create', [WireguardInterfaceController::class, 'create'], ['Auth']);
$router->post('/wireguard/store', [WireguardInterfaceController::class, 'store'], ['Auth']);
$router->get('/wireguard/edit/{id}', [WireguardInterfaceController::class, 'edit'], ['Auth']);
$router->post('/wireguard/update/{id}', [WireguardInterfaceController::class, 'update'], ['Auth']);
$router->get('/wireguard/delete/{id}', [WireguardInterfaceController::class, 'delete'], ['Auth']);

// WireGuard Peers
$router->get('/wireguard/peers/{interface_id}', [WireguardPeerController::class, 'index'], ['Auth']);
$router->get('/wireguard/peers/{interface_id}/create', [WireguardPeerController::class, 'create'], ['Auth']);
$router->post('/wireguard/peers/store/{interface_id}', [WireguardPeerController::class, 'store'], ['Auth']);
$router->get('/wireguard/peers/edit/{peer_id}', [WireguardPeerController::class, 'edit'], ['Auth']);
$router->post('/wireguard/peers/update/{peer_id}', [WireguardPeerController::class, 'update'], ['Auth']);
$router->get('/wireguard/peers/delete/{peer_id}', [WireguardPeerController::class, 'delete'], ['Auth']);
$router->get('/wireguard/peers/config/{peer_id}', [WireguardPeerController::class, 'exportConfig'], ['Auth']);
$router->get('/wireguard/peers/download/{peer_id}', [WireguardPeerController::class, 'downloadConfig'], ['Auth']);

// Tráfego
$router->get('/traffic', [TrafficController::class, 'index'], ['Auth']);
$router->get('/traffic/data', [TrafficController::class, 'data'], ['Auth']);

// Dispatch
$router->dispatch(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
