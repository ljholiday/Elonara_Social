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
?>
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

<?php include __DIR__ . '/mobile-menu-modal.php'; ?>
