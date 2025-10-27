<?php
declare(strict_types=1);

$stats = $stats ?? [];
$recentEvents = $recentEvents ?? [];
$recentCommunities = $recentCommunities ?? [];
?>

<div class="admin-card">
  <h3 style="font-size:1.1rem; margin-bottom:1rem;">System Status</h3>
  <?php
    $dashboardStatsItems = [];
    foreach ($stats as $stat) {
        if (!is_array($stat)) {
            continue;
        }
        $value = $stat['value'] ?? null;
        if ($value === null || trim((string)$value) === '') {
            continue;
        }
        $dashboardStatsItems[] = [
            'value' => $value,
            'label' => $stat['label'] ?? '',
        ];
    }
    if ($dashboardStatsItems !== []) {
        $items = $dashboardStatsItems;
        include __DIR__ . '/../partials/stats-row.php';
    } else {
        echo '<p style="color:#6b748a;">No stats available.</p>';
    }
  ?>
</div>

<div style="display:grid; gap:1.5rem; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));">
  <div class="admin-card">
    <h3 style="font-size:1.1rem; margin-bottom:1rem;">Recent Events</h3>
    <?php if (!$recentEvents): ?>
      <p style="color:#6b748a;">No events found.</p>
    <?php else: ?>
      <ul style="list-style:none; margin:0; padding:0;">
        <?php foreach ($recentEvents as $event): ?>
          <?php
            $eventTitle = $event['context_label'] ?? $event['title'] ?? 'Untitled event';
            $eventMetaItems = [];
            if (!empty($event['host'])) {
                $eventMetaItems[] = ['text' => 'Host ' . (string)$event['host']];
            }
            if (!empty($event['id'])) {
                $eventMetaItems[] = ['text' => 'ID ' . (string)$event['id']];
            }
          ?>
          <li style="margin-bottom:0.75rem;">
            <strong><?= htmlspecialchars($eventTitle); ?></strong>
            <?php
              $badge = app_visibility_badge($event['privacy'] ?? null, $event['community_privacy'] ?? null);
              if (!empty($badge['label'])):
            ?>
              <span class="<?= htmlspecialchars($badge['class']); ?>" style="margin-left:0.5rem; font-size:0.7rem;">
                <?= htmlspecialchars($badge['label']); ?>
              </span>
            <?php endif; ?>
            <?php if ($eventMetaItems !== []): ?>
              <div style="margin-top:0.25rem;">
                <?php
                  $items = $eventMetaItems;
                  include __DIR__ . '/../partials/meta-row.php';
                ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="admin-card">
    <h3 style="font-size:1.1rem; margin-bottom:1rem;">Recent Communities</h3>
    <?php if (!$recentCommunities): ?>
      <p style="color:#6b748a;">No communities found.</p>
    <?php else: ?>
      <ul style="list-style:none; margin:0; padding:0;">
        <?php foreach ($recentCommunities as $community): ?>
          <?php
            $communityName = $community['name'] ?? 'Untitled community';
            $communityMetaItems = [];
            if (!empty($community['member_count'])) {
                $communityMetaItems[] = ['text' => number_format((int)$community['member_count']) . ' members'];
            }
            if (!empty($community['id'])) {
                $communityMetaItems[] = ['text' => 'ID ' . (string)$community['id']];
            }
          ?>
          <li style="margin-bottom:0.75rem;">
            <strong><?= htmlspecialchars($communityName); ?></strong>
            <?php
              $badge = app_visibility_badge($community['privacy'] ?? null);
              if (!empty($badge['label'])):
            ?>
              <span class="<?= htmlspecialchars($badge['class']); ?>" style="margin-left:0.5rem; font-size:0.7rem;">
                <?= htmlspecialchars($badge['label']); ?>
              </span>
            <?php endif; ?>
            <?php if ($communityMetaItems !== []): ?>
              <div style="margin-top:0.25rem;">
                <?php
                  $items = $communityMetaItems;
                  include __DIR__ . '/../partials/meta-row.php';
                ?>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<div class="admin-card">
  <h3 style="font-size:1.1rem; margin-bottom:1rem;">Quick Actions</h3>
  <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:center;">
    <a class="app-btn admin-dashboard-btn app-btn-primary" style="text-decoration:none;" href="/admin/settings">Update site settings</a>
    <a class="app-btn admin-dashboard-btn app-btn-secondary" style="text-decoration:none;" href="/admin/events">Manage events</a>
    <a class="app-btn admin-dashboard-btn app-btn-secondary" style="text-decoration:none;" href="/admin/communities">Manage communities</a>
    <form method="post" action="/admin/search/reindex" style="margin:0;">
      <?= app_service('security.service')->nonceField('app_admin', '_admin_nonce', false); ?>
      <button type="submit" class="app-btn admin-dashboard-btn app-btn-secondary">Reindex search index</button>
    </form>
  </div>
</div>
