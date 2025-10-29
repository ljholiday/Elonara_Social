<?php
require __DIR__ . '/src/bootstrap.php';

use App\Database\Database;

try {
    $db = new Database();
    $pdo = $db->pdo();

    // Disable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Get all tables
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Dropping " . count($tables) . " tables...\n";

    foreach ($tables as $table) {
        echo "Dropping table: $table\n";
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "\nAll tables dropped successfully!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
