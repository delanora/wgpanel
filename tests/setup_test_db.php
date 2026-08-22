<?php
/**
 * Script de setup do banco de dados de teste.
 * 
 * Cria o banco mikrotik_manager_test e aplica todas as migrations.
 * Pode ser chamado via: composer test:setup ou php tests/setup_test_db.php
 */

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$adminUser = getenv('DB_USER') ?: 'postgres';
$adminPass = getenv('DB_PASS') ?: '';
$testDb = getenv('DB_NAME_TEST') ?: 'mikrotik_manager_test';
$testUser = getenv('DB_USER_TEST') ?: $adminUser;

echo "=== Setup Banco de Dados de Teste ===" . PHP_EOL;
echo "Host: {$host}" . PHP_EOL;
echo "Banco: {$testDb}" . PHP_EOL;

// Conectar ao PostgreSQL (banco padrão 'postgres') para criar o banco de teste
try {
    $dsn = "pgsql:host={$host};port={$port};dbname=postgres";
    $pdo = new PDO($dsn, $adminUser, $adminPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Verificar se o banco de teste já existe
    $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '{$testDb}'");
    if ($stmt->fetch()) {
        echo "Banco '{$testDb}' já existe. Recriando..." . PHP_EOL;
        $pdo->exec("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '{$testDb}' AND pid <> pg_backend_pid()");
        $pdo->exec("DROP DATABASE \"{$testDb}\"");
    }
    
    // Criar banco de teste com owner correto
    $pdo->exec("CREATE DATABASE \"{$testDb}\" OWNER \"{$testUser}\"");
    echo "Banco '{$testDb}' criado com sucesso." . PHP_EOL;
    
    // Conectar ao banco de teste
    $dsn = "pgsql:host={$host};port={$port};dbname={$testDb}";
    $testPdo = new PDO($dsn, $adminUser, $adminPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // Garantir permissões no schema
    $testPdo->exec("GRANT ALL ON SCHEMA public TO \"{$testUser}\"");
    
    // Arquivos de migration em ordem
    $migrationDir = __DIR__ . '/../database/';
    $migrations = [
        'init.sql',
        '002_wireguard_tables.sql',
        '003_wireguard_traffic_log.sql',
    ];
    
    foreach ($migrations as $file) {
        $path = $migrationDir . $file;
        if (file_exists($path)) {
            echo "Aplicando: {$file}..." . PHP_EOL;
            $sql = file_get_contents($path);
            $testPdo->exec($sql);
            echo "  ✓ OK" . PHP_EOL;
        }
    }
    
    // Dar ownership de todas as tabelas e sequences ao usuário de teste
    $tables = ['users', 'sessions', 'wireguard_interfaces', 'wireguard_peers', 'wireguard_traffic_log'];
    foreach ($tables as $table) {
        $testPdo->exec("ALTER TABLE {$table} OWNER TO \"{$testUser}\"");
    }
    $testPdo->exec("GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO \"{$testUser}\"");
    $testPdo->exec("GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO \"{$testUser}\"");
    
    echo PHP_EOL . "=== Setup concluído com sucesso! ===" . PHP_EOL;
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
