<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    public PDO $pdo;

    public function __construct(array $cfg)
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['db_host'], (int)($cfg['db_port'] ?? 3306), $cfg['db_name']);
        $this->pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec("SET time_zone = '+07:00'");
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database($GLOBALS['__config']);
        }
        return self::$instance;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', array_map(fn($c) => "`$c`", $cols)),
            implode(',', array_fill(0, count($cols), '?'))
        );
        $this->query($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        return $this->query("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $params))->rowCount();
    }

    /** Chạy các file migration mới trong database/migrations. */
    public function migrate(): array
    {
        $applied = [];
        $current = 0;
        try {
            $current = (int)$this->value("SELECT v FROM settings WHERE k = 'schema_version'");
        } catch (\PDOException) {
            $current = 0;
        }
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $version = (int)basename($file);
            if ($version <= $current) {
                continue;
            }
            $sql = (string)file_get_contents($file);
            foreach (preg_split('/;\s*[\r\n]+/', $sql) as $statement) {
                if (trim($statement) !== '') {
                    $this->pdo->exec($statement);
                }
            }
            $this->query("REPLACE INTO settings (k, v) VALUES ('schema_version', ?)", [(string)$version]);
            $applied[] = basename($file);
        }
        return $applied;
    }
}
