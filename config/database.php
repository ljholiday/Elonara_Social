<?php
/**
 * Elonara Social Database Configuration Sample
 * Copy this file to database.php and update with your credentials
 */

return [
    'host' => 'localhost',
    'dbname' => 'social_elonara',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
