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

<div class="vt-sidebar-section">

    <?php if ($is_logged_in): ?>
    <!-- Search Section -->
    <div class="vt-search-box vt-mb-4">
        <input type="text" id="vt-search-input" class="vt-input" placeholder="Search..." autocomplete="off">
        <div id="vt-search-results" class="vt-search-results" style="display: none;"></div>
    </div>
    <?php endif; ?>

    <div class="vt-sidebar-nav">
        <?php if ($is_logged_in): ?>
            <a href="/events/create" class="vt-btn vt-btn-secondary vt-btn-block">
                Create Event
            </a>

            <a href="/conversations/create" class="vt-btn vt-btn-secondary vt-btn-block">
                Create Conversation
            </a>

            <a href="/communities/create" class="vt-btn vt-btn-secondary vt-btn-block">
                Create Community
            </a>

            <a href="/profile" class="vt-btn vt-btn-secondary vt-btn-block">
                My Profile
            </a>

            <a href="/" class="vt-btn vt-btn-secondary vt-btn-block">
                Dashboard
            </a>

        <?php else: ?>
            <a href="/events" class="vt-btn vt-btn-secondary vt-btn-block">
                Browse Events
            </a>

            <a href="/conversations" class="vt-btn vt-btn-secondary vt-btn-block">
                Join Conversations
            </a>

            <a href="/communities" class="vt-btn vt-btn-secondary vt-btn-block">
                Browse Communities
            </a>

            <a href="/auth" class="vt-btn vt-btn-secondary vt-btn-block">
                Sign In
            </a>
        <?php endif; ?>
    </div>

    <?php if ($is_logged_in): ?>
    <!-- Profile Card -->
    <div class="vt-profile-card vt-mt-4">
        <div class="vt-flex vt-gap vt-mb">
            <?php
            $user = $viewer;
            $args = ['avatar_size' => 56];
            include __DIR__ . '/member-display.php';
            ?>
            <?php if (!empty($viewer->location)): ?>
                <div class="vt-flex-1">
                    <div class="vt-text-muted"><?= htmlspecialchars($viewer->location, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="vt-flex vt-gap vt-flex-column">
            <a href="/profile" class="vt-btn vt-btn-block">
                Profile
            </a>
            <a href="/logout" class="vt-btn vt-btn-block">
                Logout
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>
