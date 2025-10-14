<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private PDO $pdo;

    public function __construct(array $cfg = [])
    {
        // ------------------------------------------------------------------
        // Load project-level configuration if nothing was passed explicitly.
        // ------------------------------------------------------------------
        if (empty($cfg)) {
            $configPath = __DIR__ . '/../../config/database.php';
            if (!file_exists($configPath)) {
                throw new RuntimeException("Database configuration not found at: $configPath");
            }

            $cfg = require $configPath;

            if (!is_array($cfg)) {
                throw new RuntimeException("Invalid configuration format in: $configPath");
            }
        }

        // ------------------------------------------------------------------
        // Resolve connection parameters.
        // ------------------------------------------------------------------
        $host     = $cfg['host'] ?? '127.0.0.1';
        $port     = (string)($cfg['port'] ?? '3306');
        $dbname   = $cfg['name'] ?? $cfg['dbname'] ?? 'social_elonara';
        $charset  = $cfg['charset'] ?? 'utf8mb4';
        $username = $cfg['user'] ?? $cfg['username'] ?? 'root';
        $password = $this->stringify($cfg['pass'] ?? $cfg['password'] ?? '');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $dbname,
            $charset
        );

        $defaultOptions = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $options = isset($cfg['options']) && is_array($cfg['options'])
            ? $cfg['options'] + $defaultOptions
            : $defaultOptions;

        // ------------------------------------------------------------------
        // Attempt connection.
        // ------------------------------------------------------------------
        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    // ----------------------------------------------------------------------
    // Public API
    // ----------------------------------------------------------------------

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param mixed $value
     */
    private function stringify($value): string
    {
        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }
}

