<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

final class Installer
{
    public static function lockPath(): string
    {
        return Storage::privatePath('install.lock');
    }

    public static function isLocked(): bool
    {
        return is_file(self::lockPath());
    }

    public static function lock(): void
    {
        $path = self::lockPath();
        @file_put_contents($path, (string)time());
    }

    public static function writeEnv(array $values): void
    {
        $lines = [];
        foreach ($values as $k => $v) {
            $key = trim((string)$k);
            $val = (string)$v;
            $val = str_replace(["\r", "\n"], '', $val);
            $val = str_replace('"', '\\"', $val);
            $lines[] = $key . '="' . $val . '"';
        }

        $content = implode("\n", $lines) . "\n";
        file_put_contents(base_path('.env'), $content);
    }

    public static function pdoFromParams(string $host, string $port, string $name, string $user, string $pass): PDO
    {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /** @return array{ok: bool, error: ?string} */
    public static function runMigrations(PDO $pdo): array
    {
        $dir = base_path('database/migrations');
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            return ['ok' => false, 'error' => 'Impossible de lire les migrations.'];
        }
        sort($files);

        try {
            foreach ($files as $file) {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    return ['ok' => false, 'error' => 'Impossible de lire ' . basename($file)];
                }

                $statements = self::splitSqlStatements($sql);
                foreach ($statements as $stmt) {
                    $pdo->exec($stmt);
                }
            }
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'error' => null];
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
}
