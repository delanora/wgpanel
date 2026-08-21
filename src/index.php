<?php
/**
 * Mikrotik Manager - Front Controller
 * Todas as requisições passam por aqui
 */

session_start();

// Error reporting (desativar em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Carregar classes primeiro
require_once __DIR__ . '/src/Router.php';

// Carregar configuração
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Carregar rotas (usa Router e Controllers)
require_once __DIR__ . '/config/routes.php';
