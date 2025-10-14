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

$page_title = $page_title ?? 'Elonara Social';
$main_content = $main_content ?? '';
$sidebar_content = $sidebar_content ?? '';
$current_path = $current_path ?? $_SERVER['REQUEST_URI'] ?? '/';
$breadcrumbs = $breadcrumbs ?? [];
$nav_items = $nav_items ?? [];

$security = vt_service('security.service');
$authService = vt_service('auth.service');
$currentUser = $authService->getCurrentUser();
$userId = $currentUser->id ?? 0;
$csrf_token = $security->createNonce('vt_nonce', (int) $userId);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?= htmlspecialchars($page_title); ?> - Elonara Social</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php if ($breadcrumbs): ?>
<div class="vt-text-muted mb-4">
    <?php
    $breadcrumb_parts = [];
    foreach ($breadcrumbs as $crumb) {
        if (isset($crumb['url'])) {
            $breadcrumb_parts[] = '<a href="' . htmlspecialchars($crumb['url']) . '" class="vt-text-primary">' . htmlspecialchars($crumb['title']) . '</a>';
        } else {
            $breadcrumb_parts[] = '<span>' . htmlspecialchars($crumb['title']) . '</span>';
        }
    }
    echo implode(' › ', $breadcrumb_parts);
    ?>
</div>
<?php endif; ?>

<div class="vt-page-two-column">
    <div class="vt-main">
        <div class="vt-main-nav vt-has-mobile-menu">
            <a href="/events" class="vt-main-nav-item<?= str_contains($current_path, '/events') ? ' active' : ''; ?>">
                Events
            </a>
            <a href="/conversations" class="vt-main-nav-item<?= str_contains($current_path, '/conversations') ? ' active' : ''; ?>">
                Conversations
            </a>
            <a href="/communities" class="vt-main-nav-item<?= str_contains($current_path, '/communities') ? ' active' : ''; ?>">
                Communities
            </a>
            <button type="button" class="vt-mobile-menu-toggle vt-main-nav-item" id="mobile-menu-toggle" aria-label="Open menu">
                <span class="vt-hamburger-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </div>

        <?php if ($nav_items): ?>
        <div class="vt-nav">
            <?php foreach ($nav_items as $nav_item): ?>
                <?php if (!empty($nav_item['type']) && $nav_item['type'] === 'button'): ?>
                    <button type="button"
                        class="vt-nav-item vt-nav-item-button<?= !empty($nav_item['active']) ? ' active' : ''; ?>"
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
                        class="vt-nav-item<?= !empty($nav_item['active']) ? ' active' : ''; ?>">
                        <?php if (!empty($nav_item['icon'])): ?>
                            <span><?= $nav_item['icon']; ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($nav_item['title']); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="vt-main-content">
            <?= $main_content; ?>
        </div>
    </div>

    <div class="vt-sidebar">
        <?php if ($sidebar_content): ?>
            <?= $sidebar_content; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../partials/mobile-menu-modal.php'; ?>

<script src="/assets/js/modal.js"></script>
<script src="/assets/js/social_elonara.js"></script>
<?php if (str_contains($current_path, '/conversations')): ?>
<script src="/assets/js/conversations.js"></script>
<?php endif; ?>
<?php if (str_contains($current_path, '/communities') || str_contains($current_path, '/events')): ?>
<script src="/assets/js/communities.js"></script>
<script src="/assets/js/invitation.js"></script>
<?php endif; ?>
//<script>
//  fetch('/assets/css/dev.css', { method: 'HEAD' })
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
