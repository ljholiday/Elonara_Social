<?php
/**
 * Main Navigation Partial
 *
 * Primary navigation bar with three main sections and mobile menu support.
 * Used by: form.php, page.php, two-column.php layouts
 *
 * Required variables:
 * @var string $current_path - Current request URI for active state detection
 */

$current_path = $current_path ?? $_SERVER['REQUEST_URI'] ?? '/';
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$appName = (string)app_config('app.name', 'Elonara Social');
?>
<div class="app-main-nav app-has-mobile-menu">
    <a href="/" class="app-logo-link" aria-label="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?> Home">
        <img src="<?= htmlspecialchars($assetBase . '/icons/logo-100.png', ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8'); ?>" class="app-logo">
    </a>
    <a href="/events" class="app-main-nav-item<?= str_contains($current_path, '/events') ? ' active' : ''; ?>">
        Events
    </a>
    <a href="/conversations" class="app-main-nav-item<?= str_contains($current_path, '/conversations') ? ' active' : ''; ?>">
        Conversation
    </a>
    <a href="/communities" class="app-main-nav-item<?= str_contains($current_path, '/communities') ? ' active' : ''; ?>">
        Community
    </a>
    <button type="button" class="app-mobile-menu-toggle app-main-nav-item" id="mobile-menu-toggle" aria-label="Open menu">
        <span class="app-hamburger-icon">
            <span></span>
            <span></span>
            <span></span>
        </span>
    </button>
</div>

<?php include __DIR__ . '/mobile-menu-modal.php'; ?>
