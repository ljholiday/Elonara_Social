<?php
declare(strict_types=1);

$appName = (string)app_config('app_name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$page_title = $page_title ?? 'Admin Dashboard';
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
    <title><?= htmlspecialchars($fullTitle); ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/app.css', ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        .admin-sidebar { width: 220px; padding: 1.5rem; background: #10162f; color: #fff; min-height: 100vh; }
        .admin-sidebar a { color: rgba(255,255,255,0.85); display: block; margin-bottom: 0.75rem; text-decoration: none; }
        .admin-sidebar a.is-active, .admin-sidebar a:hover { color: #fff; font-weight: 600; }
        .admin-main { flex: 1; padding: 2rem; background: #f5f7fb; min-height: 100vh; }
        .admin-header { margin-bottom: 2rem; }
        .admin-card { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 8px 24px rgba(15,23,42,0.08); margin-bottom: 1.5rem; }
    </style>
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

<script src="<?= htmlspecialchars($assetBase . '/js/app.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
