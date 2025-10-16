<?php
/**
 * Elonara Social Database Configuration
 */

return [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => isset($_ENV['DB_PORT']) ? (int)$_ENV['DB_PORT'] : 3306,
    'dbname' => $_ENV['DB_NAME'] ?? 'social_elonara',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? 'root',
    'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
