<?php
/**
 * Form Layout - Centered single column with header and form wrapper
 * Used for: Create and edit forms
 *
 * Expected variables:
 * @var string $page_title - Page title
 * @var string $page_description - Optional page description
 * @var string $content - Form content HTML
 * @var string $current_path - Current request path
 */

declare(strict_types=1);

$appName = (string)app_config('app.name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$page_title = $page_title ?? $appName;
$page_description = $page_description ?? '';
$current_path = $current_path ?? $_SERVER['REQUEST_URI'] ?? '/';
$content = $content ?? '';
$fullTitle = $page_title === $appName ? $appName : $page_title . ' - ' . $appName;

$security = app_service('security.service');
$csrf_token = $security->createNonce('app_nonce');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?= htmlspecialchars($fullTitle); ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/app.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>

<div class="app-page-form-centered">
    <?php include __DIR__ . '/../partials/main-nav.php'; ?>

    <div class="app-header">
        <h1 class="app-heading app-heading-lg app-text-primary"><?= htmlspecialchars($page_title); ?></h1>
        <?php if ($page_description): ?>
            <p class="app-text-muted"><?= htmlspecialchars($page_description); ?></p>
        <?php endif; ?>
    </div>

    <div class="app-section">
        <?= $content; ?>
    </div>
</div>

<?php include __DIR__ . '/../partials/mobile-menu-modal.php'; ?>

<script src="<?= htmlspecialchars($assetBase . '/js/modal.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?= htmlspecialchars($assetBase . '/js/app.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
