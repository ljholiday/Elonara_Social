#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Backfill Image Variants Script
 *
 * Generates multiple size variants for existing images that were uploaded
 * before the multi-variant system was implemented.
 *
 * Usage: php scripts/backfill-image-variants.php [--dry-run] [--table=TABLE]
 *
 * Options:
 *   --dry-run    Show what would be done without making changes
 *   --table      Process specific table only (users, conversation_replies)
 *   --help       Show this help message
 */

// Bootstrap application
require_once __DIR__ . '/../src/bootstrap.php';

// Parse command line arguments
$options = [
    'dry-run' => false,
    'table' => null,
    'help' => false,
];

foreach ($argv as $arg) {
    if ($arg === '--dry-run') {
        $options['dry-run'] = true;
    } elseif ($arg === '--help') {
        $options['help'] = true;
    } elseif (str_starts_with($arg, '--table=')) {
        $options['table'] = substr($arg, 8);
    }
}

if ($options['help']) {
    echo file_get_contents(__FILE__);
    exit(0);
}

$dryRun = $options['dry-run'];
$logFile = __DIR__ . '/../debug.log';

function logMessage(string $message): void
{
    global $logFile;
    $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL);
    echo $line;
    file_put_contents($logFile, $line, FILE_APPEND);
}

logMessage('Starting image variant backfill' . ($dryRun ? ' (DRY RUN)' : ''));

try {
    $db = app_service('database.connection');
    $imageService = app_service('image.service');

    // Tables to process
    $tables = [
        'users' => [
            'id_column' => 'id',
            'image_columns' => [
                'avatar_url' => ['type' => 'profile', 'entity' => 'user'],
                'cover_url' => ['type' => 'cover', 'entity' => 'user'],
            ],
        ],
        'conversation_replies' => [
            'id_column' => 'id',
            'image_columns' => [
                'image_url' => ['type' => 'post', 'entity' => 'conversation'],
            ],
            'entity_id_column' => 'conversation_id',
        ],
    ];

    // Filter to specific table if requested
    if ($options['table'] !== null) {
        if (!isset($tables[$options['table']])) {
            logMessage('Error: Unknown table ' . $options['table']);
            exit(1);
        }
        $tables = [$options['table'] => $tables[$options['table']]];
    }

    $stats = [
        'total' => 0,
        'processed' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    foreach ($tables as $tableName => $config) {
        logMessage("Processing table: {$tableName}");

        $idColumn = $config['id_column'];
        $imageColumns = $config['image_columns'];

        foreach ($imageColumns as $columnName => $imageConfig) {
            logMessage("  Processing column: {$columnName}");

            // Find rows with non-JSON image URLs from earlier versions
            $sql = "SELECT {$idColumn}";
            if (isset($config['entity_id_column'])) {
                $sql .= ", {$config['entity_id_column']}";
            }
            $sql .= ", {$columnName}
                    FROM {$tableName}
                    WHERE {$columnName} IS NOT NULL
                      AND {$columnName} != ''
                      AND {$columnName} NOT LIKE '{%'";

            $stmt = $db->pdo()->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $count = count($rows);
            $stats['total'] += $count;

            logMessage("    Found {$count} rows with single-URL images");

            foreach ($rows as $row) {
                $id = (int)$row[$idColumn];
                $imageUrl = $row[$columnName];
                $entityId = isset($config['entity_id_column'])
                    ? (int)$row[$config['entity_id_column']]
                    : $id;

                // Convert URL to file path
                $uploadBasePath = dirname(__DIR__) . '/public/uploads';
                $parsedUrl = parse_url($imageUrl);
                $filePath = $uploadBasePath . ($parsedUrl['path'] ?? '');

                if (!file_exists($filePath)) {
                    logMessage("    SKIP: File not found for ID {$id}: {$filePath}");
                    $stats['skipped']++;
                    continue;
                }

                logMessage("    Processing ID {$id}: {$filePath}");

                if ($dryRun) {
                    logMessage("    DRY RUN: Would generate variants for {$filePath}");
                    $stats['processed']++;
                    continue;
                }

                try {
                    // Note: This would require extending ImageService with a method
                    // that accepts existing file paths instead of $_FILES uploads.
                    // For now, this is a placeholder showing the intended logic.

                    // You would need to:
                    // 1. Load the existing image file
                    // 2. Generate variants using ImageService
                    // 3. Update the database with JSON of variant URLs

                    logMessage("    TODO: Implement variant generation from existing file");
                    logMessage("    Would generate {$imageConfig['type']} variants for {$imageConfig['entity']} {$entityId}");

                    $stats['skipped']++;
                } catch (Throwable $e) {
                    logMessage("    ERROR: Failed to process ID {$id}: " . $e->getMessage());
                    $stats['errors']++;
                }
            }
        }
    }

    logMessage('');
    logMessage('Backfill complete:');
    logMessage("  Total images found: {$stats['total']}");
    logMessage("  Processed: {$stats['processed']}");
    logMessage("  Skipped: {$stats['skipped']}");
    logMessage("  Errors: {$stats['errors']}");

    if ($dryRun) {
        logMessage('');
        logMessage('This was a DRY RUN. No changes were made.');
        logMessage('Run without --dry-run to apply changes.');
    }
} catch (Throwable $e) {
    logMessage('FATAL ERROR: ' . $e->getMessage());
    logMessage($e->getTraceAsString());
    exit(1);
}

logMessage('');
logMessage('Note: This backfill script is currently a framework/placeholder.');
logMessage('Full implementation requires extending ImageService to support');
logMessage('generating variants from existing files instead of new uploads.');
logMessage('');
logMessage('For now, existing images will continue to work with backward');
logMessage('older data detected - they will display as single-size images until');
logMessage('users re-upload them through the normal upload flow.');
