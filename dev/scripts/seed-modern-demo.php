<?php
declare(strict_types=1);

/**
 * Seed demo data for the modern rewrite.
 *
 * Usage:
 *   php dev/scripts/seed-modern-demo.php
 *
 * Creates a handful of communities and events required by the new
 * /communities and /events routes if they are missing. Safe to re-run;
 * existing slugs will be skipped.
 */

require __DIR__ . '/../../src/bootstrap.php';

/**
 * @param array<string, mixed> $row
 */
function seedRow(PDO $pdo, string $table, array $row, string $slugKey = 'slug'): void
{
    $slug = $row[$slugKey] ?? null;
    if (!$slug) {
        throw new RuntimeException(sprintf('Cannot seed %s row without "%s"', $table, $slugKey));
    }

    $stmt = $pdo->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE %s = :slug', $table, $slugKey));
    $stmt->execute(['slug' => $slug]);

    if ((int)$stmt->fetchColumn() > 0) {
        printf("[%s] Skipping existing %s\n", date('H:i:s'), $slug);
        return;
    }

    $columns = array_keys($row);
    $placeholders = array_map(static fn($col) => ':' . $col, $columns);

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    $insert = $pdo->prepare($sql);
    foreach ($row as $column => $value) {
        $insert->bindValue(':' . $column, $value);
    }

    $insert->execute();
    printf("[%s] Inserted %s\n", date('H:i:s'), $slug);
}

$pdo = vt_service('database.connection')->pdo();
$pdo->beginTransaction();

try {
    $communities = [
        [
            'slug' => 'codex-builders',
            'name' => 'Codex Builders',
            'description' => 'Discussion hub for the ongoing social_elonara refactor.',
            'privacy' => 'public',
            'creator_id' => 1,
            'created_by' => 1,
            'creator_email' => 'demo@example.com',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ],
        [
            'slug' => 'supper-club',
            'name' => 'Saturday Supper Club',
            'description' => 'Rotating dinner party series for friends and neighbors.',
            'privacy' => 'public',
            'creator_id' => 1,
            'created_by' => 1,
            'creator_email' => 'demo@example.com',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
        ],
    ];

    foreach ($communities as $community) {
        seedRow($pdo, 'vt_communities', $community);
    }

    $now = date('Y-m-d H:i:s');

    $events = [
        [
            'slug' => 'codex-kickoff',
            'title' => 'Codex Kickoff Call',
            'description' => 'Walkthrough of the modern architecture and refactor roadmap.',
            'event_date' => date('Y-m-d H:i:s', strtotime('+7 days 18:00')),
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => 1,
            'author_id' => 1,
            'post_id' => 0,
            'event_status' => 'active',
            'status' => 'active',
            'visibility' => 'public',
            'privacy' => 'public',
        ],
        [
            'slug' => 'harvest-dinner',
            'title' => 'Harvest Dinner',
            'description' => 'Seasonal potluck with fresh autumn recipes.',
            'event_date' => date('Y-m-d H:i:s', strtotime('+14 days 19:00')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'created_by' => 1,
            'author_id' => 1,
            'post_id' => 0,
            'event_status' => 'active',
            'status' => 'active',
            'visibility' => 'public',
            'privacy' => 'public',
        ],
    ];

    foreach ($events as $event) {
        seedRow($pdo, 'vt_events', $event);
    }

    $pdo->commit();
    echo "\nDone. Visit /communities and /events to confirm seeded entries.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error seeding demo data: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
