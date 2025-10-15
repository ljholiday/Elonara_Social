<?php declare(strict_types=1);

/**
 * Rebuilds the search index from existing communities, events, and conversations.
 *
 * Usage: php dev/scripts/reindex-search.php
 */

require __DIR__ . '/../../src/bootstrap.php';

$search = app_service('search.service');
$counts = $search->reindexAll();

echo sprintf(
    "Reindex complete: %d communities, %d events, %d conversations.\n",
    $counts['communities'] ?? 0,
    $counts['events'] ?? 0,
    $counts['conversations'] ?? 0
);
