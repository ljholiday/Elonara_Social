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

<section class="vt-section">
  <?php if ($success): ?>
    <div class="vt-alert vt-alert-success vt-mb-4">
      <?= e($success) ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="vt-alert vt-alert-error vt-mb-4">
      <?= e($error) ?>
    </div>
    <a href="/" class="vt-btn">Go Home</a>
  <?php elseif ($u): ?>

    <!-- Profile Header with Cover -->
    <div class="vt-profile-card vt-mb-6">
      <?php if (!empty($u->cover_url)): ?>
        <div class="vt-profile-cover" style="background-image: url('<?= e($u->cover_url) ?>');" role="img" aria-label="<?= e($u->cover_alt ?? 'Cover image') ?>"></div>
      <?php else: ?>
        <div class="vt-profile-cover" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
      <?php endif; ?>

      <div class="vt-avatar-row">
        <?php if (!empty($u->avatar_url)): ?>
          <img src="<?= e($u->avatar_url) ?>" alt="<?= e($u->display_name ?? $u->username) ?>" class="vt-profile-avatar">
        <?php else: ?>
          <div class="vt-profile-avatar vt-avatar-placeholder">
            <?= strtoupper(substr($u->display_name ?? $u->username ?? 'U', 0, 1)) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="vt-profile-identity">
        <h1 class="vt-heading"><?= e($u->display_name ?? $u->username) ?></h1>
        <p class="vt-text-muted">@<?= e($u->username) ?></p>

        <?php if (!empty($u->bio)): ?>
          <p class="vt-mt-3"><?= nl2br(e($u->bio)) ?></p>
        <?php endif; ?>

        <div class="vt-card-meta vt-mt-3">
          <span>Joined <?= date_fmt($u->created_at) ?></span>
        </div>

        <?php if ($is_own): ?>
          <div class="vt-mt-4">
            <a href="/profile/edit" class="vt-btn vt-btn-primary">Edit Profile</a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats -->
    <div class="vt-card vt-mb-6">
      <h2 class="vt-heading-sm vt-mb-3">Stats</h2>
      <div class="vt-flex vt-gap-6">
        <div class="vt-stat">
          <div class="vt-stat-number"><?= e($stats['conversations']) ?></div>
          <div class="vt-stat-label">Conversations</div>
        </div>
        <div class="vt-stat">
          <div class="vt-stat-number"><?= e($stats['replies']) ?></div>
          <div class="vt-stat-label">Replies</div>
        </div>
        <div class="vt-stat">
          <div class="vt-stat-number"><?= e($stats['communities']) ?></div>
          <div class="vt-stat-label">Communities</div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="vt-card">
      <h2 class="vt-heading-sm vt-mb-3">Recent Activity</h2>
      <?php if (!empty($activity)): ?>
        <div class="vt-stack">
          <?php foreach ($activity as $item): $a = (object)$item; ?>
            <div class="vt-activity-item">
              <?php if ($a->type === 'conversation'): ?>
                <div class="vt-activity-type vt-activity-type-conversation">
                  <div class="vt-activity-action">Started a conversation</div>
                  <a href="/conversations/<?= e($a->slug) ?>" class="vt-link vt-activity-title"><?= e($a->title) ?></a>
                  <div class="vt-activity-time"><?= date_fmt($a->created_at) ?></div>
                </div>
              <?php elseif ($a->type === 'reply'): ?>
                <div class="vt-activity-type vt-activity-type-reply">
                  <div class="vt-activity-action">Replied to</div>
                  <a href="/conversations/<?= e($a->conversation_slug) ?>" class="vt-link vt-activity-title"><?= e($a->title) ?></a>
                  <div class="vt-activity-time"><?= date_fmt($a->created_at) ?></div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="vt-text-muted">No recent activity.</p>
      <?php endif; ?>
    </div>

  <?php endif; ?>
</section>
