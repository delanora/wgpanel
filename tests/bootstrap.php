<?php
/**
 * PHPUnit Bootstrap
 * 
 * Carrega autoloader do Composer e configurações de teste.
 */

// Autoload do Composer (PSR-4)
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar .env de teste (se existir) ou usar variáveis do sistema
$testEnv = __DIR__ . '/../.env.test';
if (file_exists($testEnv)) {
    $lines = file($testEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim(trim($value), '"\''));
        }
    }
}

// Configurações de banco de testes (override das de produção)
if (getenv('DB_NAME_TEST') === false) {
    putenv('DB_NAME_TEST=mikrotik_manager_test');
}
if (getenv('DB_HOST') === false) {
    putenv('DB_HOST=localhost');
}
if (getenv('DB_PORT') === false) {
    putenv('DB_PORT=5432');
}
if (getenv('DB_USER') === false) {
    putenv('DB_USER=postgres');
}
if (getenv('DB_PASS') === false) {
    putenv('DB_PASS=');
}

// Definir constantes para o Database
define('DB_HOST', getenv('DB_HOST'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_NAME', getenv('DB_NAME_TEST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('SESSION_LIFETIME', 3600);

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Carregar classes manuais do projeto (o autoloader do Composer cuida dos namespaces)
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/src/Service/HttpTransport.php';
require_once __DIR__ . '/../src/src/Service/CurlTransport.php';
require_once __DIR__ . '/../src/src/Service/MikrotikClient.php';
require_once __DIR__ . '/../src/src/Exception/MikrotikApiException.php';
