<?php
/**
 * Configuração Principal
 */

// ======================================================
// Loader simples de .env (sem dependência externa)
// ======================================================
function loadEnv(string $path): void {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Pular comentários
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        // Separar chave = valor
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remover aspas
            $value = trim($value, '"\'\'');
            // Só definir se não existe
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }
}

// Carregar .env do diretório raiz do projeto
$envPath = dirname(__DIR__, 2) . '/.env';
loadEnv($envPath);

// ======================================================
// Constantes da aplicação
// ======================================================
define('APP_NAME', 'Mikrotik Manager');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8080');

// Mikrotik API Config
define('MIKROTIK_API_URL', getenv('MIKROTIK_API_URL') ?: 'http://45.4.112.13');
define('MIKROTIK_API_PORT', getenv('MIKROTIK_API_PORT') ?: '8728');

// Database Config
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'mikrotik_manager');
define('DB_USER', getenv('DB_USER') ?: 'admin');
define('DB_PASS', getenv('DB_PASS') ?: 'admin123');

// Session Config
define('SESSION_LIFETIME', 3600); // 1 hora

// Timezone
date_default_timezone_set('America/Sao_Paulo');
