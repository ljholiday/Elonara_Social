<?php
/**
 * Admin Layout - Sidebar navigation with main content area
 * Used for: Admin dashboard and administrative pages
 *
 * Expected variables:
 * @var string $page_title - Page title for the admin section
 * @var string $page_description - Optional description for the admin page
 * @var string $content - Main admin content HTML
 * @var string $nav_active - Current active navigation item key
 */

declare(strict_types=1);

$appName = (string)app_config('app.name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$page_title = $page_title ?? 'Admin Dashboard';
$page_description = $page_description ?? '';
$fullTitle = $page_title === $appName ? $appName : $page_title . ' · Admin · ' . $appName;

$security = app_service('security.service');
$csrfToken = $security->createNonce('app_admin');

/** @var array<string,mixed> $statsRenderer */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="theme-color" content="#4B0082">
<?php include __DIR__ . '/../partials/head-meta.php'; ?>

    <title><?= htmlspecialchars($fullTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/app.css', ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/admin.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<div style="display:flex; align-items:flex-start;">
    <aside class="admin-sidebar">
        <h1 style="font-size:1.1rem; font-weight:700; margin-bottom:0.5rem;">
            <?= htmlspecialchars($appName); ?> Admin
        </h1>
        <nav>
            <a href="/admin" class="<?= ($nav_active ?? '') === 'dashboard' ? 'is-active' : ''; ?>">Overview</a>
            <a href="/admin/settings" class="<?= ($nav_active ?? '') === 'settings' ? 'is-active' : ''; ?>">Site Settings</a>
            <a href="/admin/events" class="<?= ($nav_active ?? '') === 'events' ? 'is-active' : ''; ?>">Manage Events</a>
            <a href="/admin/communities" class="<?= ($nav_active ?? '') === 'communities' ? 'is-active' : ''; ?>">Manage Communities</a>
            <a href="/admin/users" class="<?= ($nav_active ?? '') === 'users' ? 'is-active' : ''; ?>">Users</a>
            <div style="margin-top:2rem; font-size:0.85rem; opacity:0.65;">
                Environment: <?= htmlspecialchars(app_config('environment', 'production')); ?>
            </div>
        </nav>
    </aside>
    <main class="admin-main">
        <header class="admin-header">
            <h2 style="font-size:1.75rem; font-weight:700; color:#10162f; margin:0;">
                <?= htmlspecialchars($page_title); ?>
            </h2>
            <?php if (!empty($page_description)): ?>
                <p style="color:#4a5470; margin-top:0.5rem;">
                    <?= htmlspecialchars($page_description); ?>
                </p>
            <?php endif; ?>
        </header>
        <section>
            <?= $content ?? ''; ?>
        </section>
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
