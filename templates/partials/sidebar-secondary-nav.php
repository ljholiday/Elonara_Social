<?php
/**
 * Elonara Social Standardized Secondary Navigation for Sidebar
 * Universal secondary menu used across all pages
 */

declare(strict_types=1);

/** @var object|null $viewer */
$viewer = $viewer ?? null;
$is_logged_in = $viewer !== null;
?>

<div class="app-sidebar-section">

    <?php if ($is_logged_in): ?>
    <!-- Search Section -->
    <div class="app-search-box app-mb-4">
        <input type="text" id="app-search-input" class="app-input" placeholder="Search..." autocomplete="off">
        <div id="app-search-results" class="app-search-results" style="display: none;"></div>
    </div>
    <?php endif; ?>

    <div class="app-sidebar-nav">
        <?php if ($is_logged_in): ?>
            <a href="/events/create" class="app-btn app-btn-secondary app-btn-block">
                Create Event
            </a>

            <a href="/communities/create" class="app-btn app-btn-secondary app-btn-block">
                Create Community
            </a>

            <a href="/profile" class="app-btn app-btn-secondary app-btn-block">
                My Profile
            </a>

            <a href="/" class="app-btn app-btn-secondary app-btn-block">
                Dashboard
            </a>

        <?php else: ?>
            <a href="/events" class="app-btn app-btn-secondary app-btn-block">
                Browse Events
            </a>

            <a href="/conversations" class="app-btn app-btn-secondary app-btn-block">
                Join Conversations
            </a>

            <a href="/communities" class="app-btn app-btn-secondary app-btn-block">
                Browse Communities
            </a>

            <a href="/auth" class="app-btn app-btn-secondary app-btn-block">
                Sign In
            </a>
        <?php endif; ?>
    </div>

    <div class="app-text-muted app-text-sm app-text-center">
        <a target="_blank" href="https://elonara.com/weblog">Info</a> -- <a target="_blank"  href="https://elonara.com/privacy-policy">Privacy</a> -- <a target="_blank" href="https://elonara.com/contact-elonara/">Contact</a>
    </div>

    <?php if ($is_logged_in): ?>
    <!-- Profile Card -->
    <div class="app-profile-card app-mt-4">
        <div class="app-flex app-gap app-mb">
            <?php
            $user = $viewer;
            $args = ['avatar_size' => 56];
            include __DIR__ . '/member-display.php';
            ?>
            <?php if (!empty($viewer->location)): ?>
                <div class="app-flex-1">
                    <div class="app-text-muted"><?= htmlspecialchars($viewer->location, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="app-flex app-gap app-flex-column">
            <a href="/profile" class="app-btn app-btn-block">
                Profile
            </a>
            <a href="/logout" class="app-btn app-btn-block">
                Logout
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>
