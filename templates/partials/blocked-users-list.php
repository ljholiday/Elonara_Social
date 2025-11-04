<?php
/**
 * Blocked Users List Component
 * Displays list of blocked users with unblock buttons
 *
 * Usage:
 * $blocked_users = app_service('block.service')->getBlockedUsers($userId);
 * include __DIR__ . '/partials/blocked-users-list.php';
 *
 * Required variables:
 * - $blocked_users (array): Array of blocked user records from BlockService::getBlockedUsers()
 */

declare(strict_types=1);

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

$blocked_users = $blocked_users ?? [];
?>

<?php if (empty($blocked_users)): ?>
    <p class="app-text-muted">You haven't blocked anyone yet.</p>
<?php else: ?>
    <div class="app-stack">
        <?php foreach ($blocked_users as $blockedUser): ?>
            <div class="app-card">
                <div class="app-card-body app-flex app-gap app-flex-between app-items-center">
                    <div class="app-flex app-gap">
                        <?php
                        // Create user object for member-display partial
                        $user = (object)[
                            'id' => $blockedUser['blocked_user_id'] ?? 0,
                            'username' => $blockedUser['username'] ?? '',
                            'display_name' => $blockedUser['display_name'] ?? 'Unknown User',
                            'email' => $blockedUser['email'] ?? '',
                            'avatar_url' => $blockedUser['avatar_url'] ?? null,
                            'avatar_preference' => $blockedUser['avatar_preference'] ?? 'auto'
                        ];
                        $args = ['avatar_size' => 48, 'show_actions' => false];
                        include __DIR__ . '/member-display.php';
                        ?>
                        <div class="app-flex app-flex-col app-gap-sm">
                            <?php if (!empty($blockedUser['created_at'])): ?>
                                <span class="app-text-muted app-text-sm">
                                    Blocked <?= e(date_fmt($blockedUser['created_at'])) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($blockedUser['reason'])): ?>
                                <span class="app-text-muted app-text-sm">
                                    Reason: <?= e($blockedUser['reason']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="app-btn app-btn-sm app-btn-secondary"
                        onclick="unblockUser(<?= (int)($blockedUser['blocked_user_id'] ?? 0) ?>, '<?= e($blockedUser['display_name'] ?? 'this user') ?>')">
                        Unblock
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
