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
 *   - 'class' => 'app-member-display' (string): CSS classes
 */

declare(strict_types=1);

// Set defaults
$defaults = [
    'avatar_size'    => 32,
    'show_avatar'    => true,
    'show_name'      => true,
    'link_profile'   => true,
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

// Get profile URL
$profile_url = !empty($user->username)
    ? '/profile/' . htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8')
    : (!empty($user->id) ? '/profile/' . intval($user->id) : '/profile');

// Get avatar URL
$avatar_url = '';
$has_custom_avatar = false;

if (!empty($user->avatar_url)) {
    // User has uploaded avatar - try to get appropriate size
    $avatarSize = match(true) {
        $args['avatar_size'] >= 56 => 'medium',
        $args['avatar_size'] <= 32 => 'small',
        default => 'thumb'
    };
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

    <?php if ($args['show_name']): ?>
        <?php if ($args['link_profile'] && $profile_url): ?>
            <a href="<?= htmlspecialchars($profile_url, ENT_QUOTES, 'UTF-8') ?>" class="app-member-name app-link">
                <?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php else: ?>
            <span class="app-member-name"><?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    <?php endif; ?>
</div>
