<?php
/**
 * Profile View Template
 * Displays user profile with stats and recent activity
 */

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('date_fmt')) {
    function date_fmt($date) { return date('M j, Y', strtotime($date)); }
}

$u = isset($user) && is_array($user) ? (object)$user : null;
$error = $error ?? null;
$success = $success ?? null;
$is_own = $is_own_profile ?? false;
$stats = $stats ?? ['conversations' => 0, 'replies' => 0, 'communities' => 0];
$activity = $recent_activity ?? [];
?>

<section class="app-section">
  <?php if ($success): ?>
    <div class="app-alert app-alert-success app-mb-4">
      <?= e($success) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="app-alert app-alert-error app-mb-4">
      <?= e($error) ?>
    </div>
    <a href="/" class="app-btn">Go Home</a>
  <?php elseif ($u): ?>

    <!-- Profile Header with Cover -->
    <div class="app-profile-card app-mb-6">
      <?php if (!empty($u->cover_url)): ?>
        <div class="app-profile-cover" role="img" aria-label="<?= e($u->cover_alt ?? 'Cover image') ?>">
          <?php
            $coverUrl = getImageUrl($u->cover_url, 'original', 'original');
            if ($coverUrl):
          ?>
            <img src="<?= e($coverUrl) ?>" alt="<?= e($u->cover_alt ?? 'Cover image') ?>" class="app-profile-cover-img" loading="eager">
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="app-profile-cover" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
      <?php endif; ?>

      <div class="app-avatar-row">
        <?php
          // Determine avatar URL based on preference
          $avatarUrl = '';
          $avatarPref = $u->avatar_preference ?? 'auto';

          if ($avatarPref === 'gravatar') {
              // Force Gravatar only
              if (!empty($u->email)) {
                  $hash = md5(strtolower(trim($u->email)));
                  $avatarUrl = "https://www.gravatar.com/avatar/{$hash}?s=200&d=identicon";
              }
          } elseif ($avatarPref === 'custom') {
              // Custom only - no Gravatar fallback
              if (!empty($u->avatar_url)) {
                  $avatarUrl = getImageUrl($u->avatar_url, 'original', 'original');
              }
          } else {
              // Auto mode (default): try custom first, then Gravatar
              if (!empty($u->avatar_url)) {
                  $avatarUrl = getImageUrl($u->avatar_url, 'original', 'original');
              }
              // Fallback to Gravatar if no custom avatar
              if (!$avatarUrl && !empty($u->email)) {
                  $hash = md5(strtolower(trim($u->email)));
                  $avatarUrl = "https://www.gravatar.com/avatar/{$hash}?s=200&d=identicon";
              }
          }
        ?>

        <?php if ($avatarUrl): ?>
          <img src="<?= e($avatarUrl) ?>" alt="<?= e($u->display_name ?? $u->username) ?>" class="app-profile-avatar" loading="eager">
        <?php else: ?>
          <div class="app-profile-avatar app-avatar-placeholder">
            <?= strtoupper(substr($u->display_name ?? $u->username ?? 'U', 0, 1)) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="app-profile-identity">
        <h1 class="app-heading"><?= e($u->display_name ?? $u->username) ?></h1>
        <p class="app-text-muted">@<?= e($u->username) ?></p>

        <?php if (!empty($u->bio)): ?>
          <p class="app-mt-3"><?= nl2br(e($u->bio)) ?></p>
        <?php endif; ?>

        <div class="app-card-meta app-mt-3">
          <span>Joined <?= date_fmt($u->created_at) ?></span>
        </div>

        <?php if ($is_own): ?>
          <div class="app-mt-4">
            <a href="/profile/edit" class="app-btn app-btn-primary">Edit Profile</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats -->
    <div class="app-card app-mb-6">
      <h2 class="app-heading-sm app-mb-3">Stats</h2>
      <div class="app-flex app-gap-6">
        <div class="app-stat">
          <div class="app-stat-number"><?= e($stats['conversations']) ?></div>
          <div class="app-stat-label">Conversations</div>
        </div>
        <div class="app-stat">
          <div class="app-stat-number"><?= e($stats['replies']) ?></div>
          <div class="app-stat-label">Replies</div>
        </div>
        <div class="app-stat">
          <div class="app-stat-number"><?= e($stats['communities']) ?></div>
          <div class="app-stat-label">Communities</div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="app-card">
      <h2 class="app-heading-sm app-mb-3">Recent Activity</h2>
      <?php if (!empty($activity)): ?>
        <div class="app-stack">
          <?php foreach ($activity as $item): $a = (object)$item; ?>
            <div class="app-activity-item">
              <?php if ($a->type === 'conversation'): ?>
                <div class="app-activity-type app-activity-type-conversation">
                  <div class="app-activity-action">Started a conversation</div>
                  <a href="/conversations/<?= e($a->slug) ?>" class="app-link app-activity-title"><?= e($a->title) ?></a>
                  <div class="app-activity-time"><?= date_fmt($a->created_at) ?></div>
                </div>
              <?php elseif ($a->type === 'reply'): ?>
                <div class="app-activity-type app-activity-type-reply">
                  <div class="app-activity-action">Replied to</div>
                  <a href="/conversations/<?= e($a->conversation_slug) ?>" class="app-link app-activity-title"><?= e($a->title) ?></a>
                  <div class="app-activity-time"><?= date_fmt($a->created_at) ?></div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="app-text-muted">No recent activity.</p>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</section>
