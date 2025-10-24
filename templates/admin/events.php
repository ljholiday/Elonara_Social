<?php
declare(strict_types=1);

/** @var array<string,mixed> $pagination */
$searchQuery = $searchQuery ?? '';
$events = $events ?? [];
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'per_page' => 25, 'total' => 0];
$flash = $flash ?? [];

/** @var \App\Services\SecurityService $security */
$security = app_service('security.service');
$nonceField = fn(): string => $security->nonceField('app_admin', '_admin_nonce', false);

$currentQuery = $searchQuery !== '' ? ['q' => $searchQuery] : [];
?>

<?php if (!empty($flash)): ?>
  <div class="admin-card" style="border-left:4px solid <?= $flash['type'] === 'success' ? '#16a34a' : '#dc2626'; ?>;">
    <strong><?= htmlspecialchars(ucfirst($flash['type'])); ?>:</strong>
    <?= htmlspecialchars((string)$flash['message']); ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <form method="get" action="/admin/events" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
    <div style="flex:1 1 240px;">
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Search</label>
      <input
        class="app-input"
        name="q"
        placeholder="Event title, description, community, or host name"
        value="<?= htmlspecialchars($searchQuery); ?>"
      >
    </div>
    <div>
      <button type="submit" class="app-btn app-btn-primary">Search</button>
    </div>
  </form>
  <div style="margin-top:1rem; color:#4a5470; font-size:0.9rem;">
    <?= number_format((int)$pagination['total']); ?> total events
  </div>
</div>

<div class="admin-card">
  <?php if (empty($events)): ?>
    <p style="margin:0; color:#4a5470;">No events found for that query.</p>
  <?php else: ?>
    <div style="display:grid; gap:1.5rem;">
      <?php foreach ($events as $event): ?>
        <?php
        $id = (int)($event['id'] ?? 0);
        $title = (string)($event['title'] ?? 'Untitled Event');
        $slug = (string)($event['slug'] ?? '');
        $privacy = (string)($event['privacy'] ?? 'public');
        $eventDate = (string)($event['event_date'] ?? '');
        $endDate = (string)($event['end_date'] ?? '');
        $communityId = (int)($event['community_id'] ?? 0);
        $communityName = (string)($event['community_name'] ?? '');
        $communitySlug = (string)($event['community_slug'] ?? '');
        $hostId = (int)($event['host_id'] ?? 0);
        $hostName = (string)($event['host_name'] ?? 'Unknown');

        // Build event URL
        $eventUrl = $communitySlug !== ''
            ? "/communities/{$communitySlug}/events/{$slug}"
            : "/events/{$slug}";

        // Format dates
        $formattedDate = $eventDate !== '' ? date('M j, Y g:i A', strtotime($eventDate)) : 'No date';
        $formattedEndDate = $endDate !== '' ? date('M j, Y g:i A', strtotime($endDate)) : '';

        $badge = app_visibility_badge($privacy);
        ?>
        <div style="background:#f8faff; border-radius:10px; padding:1.25rem; border:1px solid #e0e7ff;">
          <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:1rem;">
            <div>
              <h4 style="margin:0 0 0.5rem 0; font-size:1.1rem;">
                <a href="<?= htmlspecialchars($eventUrl); ?>" class="app-link" target="_blank">
                  <?= htmlspecialchars($title); ?>
                </a>
              </h4>
              <?php if (!empty($badge['label'])): ?>
                <span class="<?= htmlspecialchars($badge['class']); ?>" style="font-size:0.7rem;">
                  <?= htmlspecialchars($badge['label']); ?>
                </span>
              <?php endif; ?>
            </div>
            <div style="text-align:right; font-size:0.85rem; color:#6b748a;">
              <div><strong>ID:</strong> <?= $id; ?></div>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:0.75rem; font-size:0.9rem; color:#495267;">
            <div>
              <strong style="color:#10162f;">Date:</strong> <?= htmlspecialchars($formattedDate); ?>
              <?php if ($formattedEndDate !== ''): ?>
                <br><strong style="color:#10162f;">End:</strong> <?= htmlspecialchars($formattedEndDate); ?>
              <?php endif; ?>
            </div>
            <div>
              <strong style="color:#10162f;">Host:</strong> <?= htmlspecialchars($hostName); ?>
            </div>
            <?php if ($communityName !== ''): ?>
              <div>
                <strong style="color:#10162f;">Community:</strong>
                <a href="/communities/<?= htmlspecialchars($communitySlug); ?>" class="app-link" target="_blank">
                  <?= htmlspecialchars($communityName); ?>
                </a>
              </div>
            <?php endif; ?>
          </div>

          <div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="<?= htmlspecialchars($eventUrl); ?>" class="app-btn app-btn-sm" target="_blank">View Event</a>
            <a href="/events/<?= $id; ?>/manage" class="app-btn app-btn-sm app-btn-secondary">Manage</a>
            <form method="post" action="/admin/events/<?= $id; ?>/delete" onsubmit="return confirm('Delete this event? This cannot be undone.');" style="display:inline;">
              <?= $nonceField(); ?>
              <button type="submit" class="app-btn app-btn-sm app-btn-danger">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ((int)$pagination['pages'] > 1): ?>
      <?php
      $page = (int)$pagination['page'];
      $pages = (int)$pagination['pages'];

      $buildLink = function (int $targetPage) use ($currentQuery): string {
          $params = $currentQuery;
          $params['page'] = $targetPage;
          return '/admin/events?' . http_build_query($params);
      };
      ?>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.5rem; font-size:0.9rem; color:#4a5470;">
        <div>
          Page <?= $page; ?> of <?= $pages; ?>
        </div>
        <div style="display:flex; gap:0.75rem;">
          <?php if ($page > 1): ?>
            <a class="app-link" href="<?= htmlspecialchars($buildLink($page - 1)); ?>">&larr; Previous</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
            <a class="app-link" href="<?= htmlspecialchars($buildLink($page + 1)); ?>">Next &rarr;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
