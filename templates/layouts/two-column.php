<?php
/**
 * Two-Column Layout - Main content + sidebar
 * Used for: List pages, detail pages with sidebar
 *
 * Expected variables:
 * @var string $page_title - Page title
 * @var string $main_content - Main content HTML
 * @var string $sidebar_content - Sidebar content HTML
 * @var string $current_path - Current request path
 * @var array $breadcrumbs - Optional breadcrumb array
 * @var array $nav_items - Optional secondary navigation
 */

declare(strict_types=1);

$appName = (string)app_config('app.name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$page_title = $page_title ?? $appName;
$page_description = $page_description ?? '';
$main_content = $main_content ?? '';
$sidebar_content = $sidebar_content ?? '';
$current_path = $current_path ?? $_SERVER['REQUEST_URI'] ?? '/';
$breadcrumbs = $breadcrumbs ?? [];
$nav_items = $nav_items ?? [];
$fullTitle = $page_title === $appName ? $appName : $page_title . ' - ' . $appName;

$security = app_service('security.service');
$authService = app_service('auth.service');
$currentUser = $authService->getCurrentUser();
$userId = (int)($currentUser?->id ?? 0);
$csrf_token = $security->createNonce('app_nonce', $userId);
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
<body>

<?php if ($breadcrumbs): ?>
<div class="app-text-muted mb-4">
    <?php
    $breadcrumb_parts = [];
    foreach ($breadcrumbs as $crumb) {
        if (isset($crumb['url'])) {
            $breadcrumb_parts[] = '<a href="' . htmlspecialchars($crumb['url']) . '" class="app-text-primary">' . htmlspecialchars($crumb['title']) . '</a>';
        } else {
            $breadcrumb_parts[] = '<span>' . htmlspecialchars($crumb['title']) . '</span>';
        }
    }
    echo implode(' › ', $breadcrumb_parts);
    ?>
</div>
<?php endif; ?>

<div class="app-page-two-column">
    <div class="app-main">
        <?php include __DIR__ . '/../partials/main-nav.php'; ?>

        <?php if ($nav_items): ?>
        <div class="app-nav">
            <?php foreach ($nav_items as $nav_item): ?>
                <?php if (!empty($nav_item['type']) && $nav_item['type'] === 'button'): ?>
                    <button type="button"
                        class="app-nav-item app-nav-item-button<?= !empty($nav_item['active']) ? ' active' : ''; ?>"
                        <?php if (!empty($nav_item['data'])): ?>
                            <?php foreach ($nav_item['data'] as $key => $value): ?>
                                data-<?= htmlspecialchars($key); ?>="<?= htmlspecialchars($value); ?>"
                            <?php endforeach; ?>
                        <?php endif; ?>>
                        <?php if (!empty($nav_item['icon'])): ?>
                            <span><?= $nav_item['icon']; ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($nav_item['title']); ?>
                    </button>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($nav_item['url']); ?>"
                        class="app-nav-item<?= !empty($nav_item['active']) ? ' active' : ''; ?>">
                        <?php if (!empty($nav_item['icon'])): ?>
                            <span><?= $nav_item['icon']; ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($nav_item['title']); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="app-main-content">
            <?php include __DIR__ . '/../partials/flash-messages.php'; ?>
            <?= $main_content; ?>
        </div>
    </div>

    <div class="app-sidebar">
        <?php if ($sidebar_content): ?>
            <?= $sidebar_content; ?>
        <?php endif; ?>
    </div>
</div>

<script src="<?= htmlspecialchars($assetBase . '/js/modal.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?= htmlspecialchars($assetBase . '/js/app.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php if (str_contains($current_path, '/conversations')): ?>
<script src="<?= htmlspecialchars($assetBase . '/js/conversations.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
<?php if (str_contains($current_path, '/communities') || str_contains($current_path, '/events')): ?>
<script src="<?= htmlspecialchars($assetBase . '/js/membership.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js');
    });
}
</script>
</body>
</html>
