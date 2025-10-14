#!/usr/bin/env php
<?php
/**
 * Run database migration
 */

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$migrationFile = $argv[1] ?? null;
if ($migrationFile === null || !file_exists($migrationFile)) {
    echo "Usage: php run-migration.php <path-to-migration.sql>\n";
    exit(1);
}

echo "Running migration: $migrationFile\n";

$sql = file_get_contents($migrationFile);
if ($sql === false) {
    echo "Error: Could not read migration file\n";
    exit(1);
}

try {
    $pdo = vt_service('database.connection')->pdo();
    $pdo->exec($sql);
    echo "✅ Migration completed successfully\n";
} catch (\PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
