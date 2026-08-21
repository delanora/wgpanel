<?php
/**
 * Database Configuration - PostgreSQL via PDO
 */

class Database {
    private static ?PDO $instance = null;
    
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    DB_HOST, DB_PORT, DB_NAME
                );
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die('Erro de conexão: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }
    
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public static function fetch(string $sql, array $params = []): ?array {
        $result = self::query($sql, $params)->fetch();
        return $result === false ? null : $result;
    }
    
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }
    
    public static function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }
    
    public static function update(string $table, array $data, string $where, array $whereParams = []): void {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        self::query($sql, array_merge(array_values($data), $whereParams));
    }
    
    public static function delete(string $table, string $where, array $params = []): void {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        self::query($sql, $params);
    }
}
