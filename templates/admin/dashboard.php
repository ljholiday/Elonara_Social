<?php
declare(strict_types=1);

$stats = $stats ?? [];
$recentEvents = $recentEvents ?? [];
$recentCommunities = $recentCommunities ?? [];
?>

<div class="admin-card">
  <h3 style="font-size:1.1rem; margin-bottom:1rem;">System Status</h3>
  <div style="display:flex; flex-wrap:wrap; gap:1rem;">
    <?php foreach ($stats as $stat): ?>
      <div style="flex:1 1 200px; background:#f8faff; border-radius:10px; padding:1rem;">
        <div style="font-size:0.85rem; color:#495267; text-transform:uppercase; letter-spacing:0.04em;">
          <?= htmlspecialchars($stat['label']); ?>
        </div>
        <div style="font-size:1.5rem; font-weight:700; color:#10162f;">
          <?= htmlspecialchars((string)$stat['value']); ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div style="display:grid; gap:1.5rem; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));">
  <div class="admin-card">
    <h3 style="font-size:1.1rem; margin-bottom:1rem;">Recent Events</h3>
    <?php if (!$recentEvents): ?>
      <p style="color:#6b748a;">No events found.</p>
    <?php else: ?>
      <ul style="list-style:none; margin:0; padding:0;">
        <?php foreach ($recentEvents as $event): ?>
          <li style="margin-bottom:0.75rem;">
            <strong><?= htmlspecialchars($event['title'] ?? 'Untitled event'); ?></strong><br>
            <small style="color:#6b748a;">ID <?= htmlspecialchars((string)($event['id'] ?? '')); ?> · Host <?= htmlspecialchars($event['host'] ?? ''); ?></small>
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
          <li style="margin-bottom:0.75rem;">
            <strong><?= htmlspecialchars($community['name'] ?? 'Untitled community'); ?></strong><br>
            <small style="color:#6b748a;">ID <?= htmlspecialchars((string)($community['id'] ?? '')); ?> · Members <?= htmlspecialchars((string)($community['member_count'] ?? 0)); ?></small>
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
