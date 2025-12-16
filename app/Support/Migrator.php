<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class Migrator
{
    private const TABLE = 'schema_migrations';

    /** @return string[] */
    public static function pending(PDO $pdo): array
    {
        $files = self::migrationFiles();
        $applied = self::applied($pdo);

        $pending = [];
        foreach ($files as $file) {
            $base = basename($file);
            if (!isset($applied[$base])) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    /** @return array{ok: bool, applied: string[], error: ?string} */
    public static function applyPending(PDO $pdo): array
    {
        self::ensureMigrationsTable($pdo);

        $pending = self::pending($pdo);
        $appliedNow = [];

        try {
            foreach ($pending as $file) {
                $base = basename($file);
                $sql = file_get_contents($file);
                if ($sql === false) {
                    return ['ok' => false, 'applied' => $appliedNow, 'error' => 'Impossible de lire ' . $base];
                }

                $statements = self::splitSqlStatements($sql);
                foreach ($statements as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (\PDOException $e) {
                        if (self::isIgnorableMigrationError($e)) {
                            continue;
                        }
                        return ['ok' => false, 'applied' => $appliedNow, 'error' => $base . ': ' . $e->getMessage()];
                    }
                }

                $ins = $pdo->prepare('INSERT IGNORE INTO ' . self::TABLE . ' (migration) VALUES (:m)');
                $ins->execute(['m' => $base]);
                $appliedNow[] = $base;
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'applied' => $appliedNow, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'applied' => $appliedNow, 'error' => null];
    }

    private static function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        );
    }

    /** @return array<string, true> */
    private static function applied(PDO $pdo): array
    {
        try {
            $pdo->query('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');
        } catch (\Throwable $e) {
            self::ensureMigrationsTable($pdo);
        }

        $rows = $pdo->query('SELECT migration FROM ' . self::TABLE)->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(string)$r['migration']] = true;
        }
        return $out;
    }

    /** @return string[] */
    private static function migrationFiles(): array
    {
        $dir = base_path('database/migrations');
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            return [];
        }
        sort($files);
        return $files;
    }

    /** @return string[] */
    private static function splitSqlStatements(string $sql): array
    {
        $sql = str_replace("\r\n", "\n", $sql);
        $chunks = explode(';', $sql);
        $out = [];
        foreach ($chunks as $chunk) {
            $stmt = trim($chunk);
            if ($stmt === '') {
                continue;
            }
            $out[] = $stmt . ';';
        }
        return $out;
    }

    private static function isIgnorableMigrationError(\PDOException $e): bool
    {
        $code = (string)$e->getCode();
        $msg = strtolower($e->getMessage());

        // MySQL common errors: 1050 table exists, 1060 duplicate column, 1061 duplicate key name, 1068 multiple primary key
        if (in_array($code, ['1050', '1060', '1061', '1068'], true)) {
            return true;
        }

        // SQLSTATE common
        if (in_array($code, ['42S01', '42S21', '42000'], true)) {
            if (str_contains($msg, 'already exists') || str_contains($msg, 'duplicate') || str_contains($msg, 'exists')) {
                return true;
            }
        }

        return false;
    }
}
