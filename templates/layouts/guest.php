<?php
/**
 * Guest Layout Template
 *
 * Provides the base HTML structure for unauthenticated pages (login, register, password reset).
 *
 * Expected variables:
 * @var string $page_title - Page title
 * @var string $content - Main content HTML
 */

declare(strict_types=1);

$appName = (string)app_config('app.name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$page_title = $page_title ?? $appName;
$page_description = $page_description ?? '';
$content = $content ?? '';
$fullTitle = $page_title === $appName ? $appName : $page_title . ' - ' . $appName;

// Generate CSRF token for meta tag
$security = app_service('security.service');
$csrf_token = $security->createNonce('app_nonce');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#4B0082">
<?php include __DIR__ . '/../partials/head-meta.php'; ?>

    <title><?= htmlspecialchars($fullTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/app.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="app-body app-guest">

<div class="app-layout-guest">
    <main class="app-main" role="main">
        <?= $content; ?>
    </main>
</div>

<?php include __DIR__ . '/../partials/global-modals.php'; ?>

<script src="<?= htmlspecialchars($assetBase . '/js/app.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js');
    });
}
</script>
</body>
</html>
