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

$appName = (string)app_config('app_name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$page_title = $page_title ?? $appName;
$main_content = $main_content ?? '';
$sidebar_content = $sidebar_content ?? '';
$current_path = $current_path ?? $_SERVER['REQUEST_URI'] ?? '/';
$breadcrumbs = $breadcrumbs ?? [];
$nav_items = $nav_items ?? [];
$fullTitle = $page_title === $appName ? $appName : $page_title . ' - ' . $appName;

$security = app_service('security.service');
$authService = app_service('auth.service');
$currentUser = $authService->getCurrentUser();
$userId = $currentUser->id ?? 0;
$csrf_token = $security->createNonce('app_nonce', (int) $userId);
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
        <div class="app-main-nav app-has-mobile-menu">
            <a href="/events" class="app-main-nav-item<?= str_contains($current_path, '/events') ? ' active' : ''; ?>">
                Events
            </a>
            <a href="/conversations" class="app-main-nav-item<?= str_contains($current_path, '/conversations') ? ' active' : ''; ?>">
                Conversations
            </a>
            <a href="/communities" class="app-main-nav-item<?= str_contains($current_path, '/communities') ? ' active' : ''; ?>">
                Communities
            </a>
            <button type="button" class="app-mobile-menu-toggle app-main-nav-item" id="mobile-menu-toggle" aria-label="Open menu">
                <span class="app-hamburger-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </div>

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
            <?= $main_content; ?>
        </div>
    </div>

    <div class="app-sidebar">
        <?php if ($sidebar_content): ?>
            <?= $sidebar_content; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../partials/mobile-menu-modal.php'; ?>

<script src="<?= htmlspecialchars($assetBase . '/js/modal.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?= htmlspecialchars($assetBase . '/js/app.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php if (str_contains($current_path, '/conversations')): ?>
<script src="<?= htmlspecialchars($assetBase . '/js/conversations.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
<?php if (str_contains($current_path, '/communities') || str_contains($current_path, '/events')): ?>
<script src="<?= htmlspecialchars($assetBase . '/js/communities.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?= htmlspecialchars($assetBase . '/js/invitation.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
//<script>
//  fetch(<?= json_encode($assetBase . '/css/dev.css'); ?>, { method: 'HEAD' })
//    .then(response => {
//      if (response.ok) {
//        const link = document.createElement('link');
//        link.rel = 'stylesheet';
//        link.href = '/assets/css/dev.css';
//        document.head.appendChild(link);
//      }
//    })
//    .catch(() => {
//      // dev.css doesn't exist - silently ignore
//    });
//</script>
</body>
</html>
