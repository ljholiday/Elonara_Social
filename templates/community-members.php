<?php
/**
 * Community Members Template
 */

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

$community = $community ?? null;
$members = $members ?? [];
?>

<section class="vt-section">
  <?php if (!$community): ?>
    <p class="vt-text-muted">Community not found.</p>
  <?php else: ?>
    <h1 class="vt-heading">Members</h1>
    <p class="vt-text-muted">Members of <?= e($community['title']) ?></p>

    <?php if (empty($members)): ?>
      <div class="vt-card vt-mt-4">
        <p class="vt-text-muted">No members in this community yet.</p>
      </div>
    <?php else: ?>
      <div class="vt-stack vt-mt-4">
        <?php foreach ($members as $member):
          $user = (object)[
              'id' => $member['user_id'] ?? null,
              'username' => $member['username'] ?? null,
              'display_name' => $member['display_name'] ?? $member['username'] ?? 'Unknown',
              'email' => $member['email'] ?? null,
              'avatar_url' => $member['avatar_url'] ?? null
          ];
          $args = ['avatar_size' => 48];
          ?>
          <div class="vt-card">
            <?php include __DIR__ . '/partials/member-display.php'; ?>
            <?php if (!empty($member['role'])): ?>
              <span class="vt-badge"><?= e(ucfirst($member['role'])) ?></span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
