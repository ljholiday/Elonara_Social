<?php
/**
 * Elonara Social Member Display Component
 * Reusable member display with avatar, display name, and profile link
 *
 * Usage:
 * include __DIR__ . '/partials/member-display.php';
 *
 * Required variables:
 * - $user (object): User object with id, email, username, display_name, avatar_url properties
 *
 * Optional variables:
 * - $args (array): Display arguments
 *   - 'avatar_size' => 32 (int): Avatar size in pixels
 *   - 'show_avatar' => true (bool): Whether to show avatar
 *   - 'show_name' => true (bool): Whether to show name
 *   - 'link_profile' => true (bool): Whether to make it clickable
 *   - 'show_actions' => false (bool): Whether to show action buttons (connect/message/block)
 *   - 'class' => 'app-member-display' (string): CSS classes
 */

declare(strict_types=1);

// Set defaults
$defaults = [
    'avatar_size'    => 32,
    'show_avatar'    => true,
    'show_name'      => true,
    'link_profile'   => true,
    'show_actions'   => false,
    'class'          => 'app-member-display',
];

$args = isset($args) ? array_merge($defaults, $args) : $defaults;

// Ensure we have a user object
if (!isset($user) || !is_object($user)) {
    echo '<span class="' . htmlspecialchars($args['class'], ENT_QUOTES, 'UTF-8') . '">Unknown User</span>';
    return;
}

// Get display name
$display_name = $user->display_name ?? 'Unknown User';

// Get profile URL - canonical format uses numeric ID
// IDs are stable (username changes don't break links) and always present (username can be NULL)
$profile_url = !empty($user->id)
    ? '/profile/' . (int)$user->id
    : '/profile';

// Get avatar URL based on user preference
$avatar_url = '';
$has_custom_avatar = false;
$avatar_preference = $user->avatar_preference ?? 'auto';

// Determine avatar size for custom images
$avatarSize = match(true) {
    $args['avatar_size'] >= 56 => 'medium',
    $args['avatar_size'] <= 32 => 'small',
    default => 'thumb'
};

// Handle avatar based on preference
if ($avatar_preference === 'gravatar') {
    // Force Gravatar only
    if (!empty($user->email)) {
        $hash = md5(strtolower(trim($user->email)));
        $avatar_url = "https://www.gravatar.com/avatar/{$hash}?s=" . intval($args['avatar_size']) . "&d=identicon";
    } else {
        $fallbackEmail = 'default@' . (string)app_config('app_domain', 'example.com');
        $fallback_hash = md5($fallbackEmail);
        $avatar_url = "https://www.gravatar.com/avatar/{$fallback_hash}?s=" . intval($args['avatar_size']) . "&d=identicon";
    }
} elseif ($avatar_preference === 'custom') {
    // Custom only - no Gravatar fallback
    if (!empty($user->avatar_url)) {
        $avatar_url = getImageUrl($user->avatar_url, $avatarSize, 'original');
        $has_custom_avatar = !empty($avatar_url);
    }
    // If no custom avatar, leave empty (will show placeholder)
} else {
    // Auto mode (default): try custom first, then Gravatar
    if (!empty($user->avatar_url)) {
        $avatar_url = getImageUrl($user->avatar_url, $avatarSize, 'original');
        $has_custom_avatar = !empty($avatar_url);
    }

    // Fallback to Gravatar if no custom avatar
    if (!$has_custom_avatar) {
        if (!empty($user->email)) {
            $hash = md5(strtolower(trim($user->email)));
            $avatar_url = "https://www.gravatar.com/avatar/{$hash}?s=" . intval($args['avatar_size']) . "&d=identicon";
        } else {
            $fallbackEmail = 'default@' . (string)app_config('app_domain', 'example.com');
            $fallback_hash = md5($fallbackEmail);
            $avatar_url = "https://www.gravatar.com/avatar/{$fallback_hash}?s=" . intval($args['avatar_size']) . "&d=identicon";
        }
    }
}

// Determine avatar class based on size
$avatar_class = 'app-avatar';
if ($args['avatar_size'] >= 56) {
    $avatar_class .= ' app-avatar-lg';
} elseif ($args['avatar_size'] <= 32) {
    $avatar_class .= ' app-avatar-sm';
}
?>

<div class="<?= htmlspecialchars($args['class'], ENT_QUOTES, 'UTF-8') ?> app-flex app-gap">
    <?php if ($args['show_avatar']): ?>
        <?php if ($args['link_profile'] && $profile_url): ?>
            <a href="<?= htmlspecialchars($profile_url, ENT_QUOTES, 'UTF-8') ?>" class="app-avatar-link">
                <div class="<?= $avatar_class ?>">
                    <img src="<?= htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </a>
        <?php else: ?>
            <div class="<?= $avatar_class ?>">
                <img src="<?= htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div>
        <?php if ($args['show_name']): ?>
            <?php if ($args['link_profile'] && $profile_url): ?>
                <a href="<?= htmlspecialchars($profile_url, ENT_QUOTES, 'UTF-8') ?>" class="app-member-name app-link">
                    <?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php else: ?>
                <span class="app-member-name"><?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($args['show_actions']): ?>
            <?php
            // Get current viewer to check if actions should be shown
            $viewer = function_exists('app_service') ? app_service('auth.service')->getCurrentUser() : null;
            $viewerId = $viewer ? (int)$viewer->id : 0;
            $targetUserId = (int)($user->id ?? 0);

            // Don't show actions for the viewer's own profile
            if ($viewerId > 0 && $viewerId !== $targetUserId):
            ?>
                <div class="app-member-actions" style="margin-top: 0.5rem;">
                    <button type="button" class="app-btn-icon" title="Connect (coming soon)" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                    </button>
                    <button type="button" class="app-btn-icon" title="Message (coming soon)" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </button>
                    <button type="button" class="app-btn-icon" title="Block this user"
                        data-user-id="<?= $targetUserId ?>"
                        data-user-name="<?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>"
                        onclick="blockUser(<?= $targetUserId ?>, '<?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                        </svg>
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
