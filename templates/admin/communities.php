<?php
declare(strict_types=1);

/** @var array<string,mixed> $pagination */
$searchQuery = $searchQuery ?? '';
$communities = $communities ?? [];
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
  <form method="get" action="/admin/communities" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
    <div style="flex:1 1 240px;">
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Search</label>
      <input
        class="app-input"
        name="q"
        placeholder="Community name, description, or creator name"
        value="<?= htmlspecialchars($searchQuery); ?>"
      >
    </div>
    <div>
      <button type="submit" class="app-btn app-btn-primary">Search</button>
    </div>
  </form>
  <div style="margin-top:1rem; color:#4a5470; font-size:0.9rem;">
    <?= number_format((int)$pagination['total']); ?> total communities
  </div>
</div>

<div class="admin-card">
  <?php if (empty($communities)): ?>
    <p style="margin:0; color:#4a5470;">No communities found for that query.</p>
  <?php else: ?>
    <div style="display:grid; gap:1.5rem;">
      <?php foreach ($communities as $community): ?>
        <?php
        $id = (int)($community['id'] ?? 0);
        $name = (string)($community['name'] ?? 'Untitled Community');
        $slug = (string)($community['slug'] ?? '');
        $description = (string)($community['description'] ?? '');
        $privacy = (string)($community['privacy'] ?? 'public');
        $memberCount = (int)($community['member_count'] ?? 0);
        $createdAt = (string)($community['created_at'] ?? '');
        $creatorId = (int)($community['creator_id'] ?? 0);
        $creatorName = (string)($community['creator_name'] ?? 'Unknown');

        // Build community URL
        $communityUrl = "/communities/{$slug}";

        // Format created date
        $formattedDate = $createdAt !== '' ? date('M j, Y', strtotime($createdAt)) : 'Unknown';

        $badge = app_visibility_badge($privacy);
        ?>
        <div style="background:#f8faff; border-radius:10px; padding:1.25rem; border:1px solid #e0e7ff;">
          <div style="margin-bottom:1rem;">
            <h4 style="margin:0 0 0.5rem 0; font-size:1.1rem;">
              <a href="<?= htmlspecialchars($communityUrl); ?>" class="app-link" target="_blank">
                <?= htmlspecialchars($name); ?>
              </a>
            </h4>
            <?php if (!empty($badge['label'])): ?>
              <span class="<?= htmlspecialchars($badge['class']); ?>" style="font-size:0.7rem;">
                <?= htmlspecialchars($badge['label']); ?>
              </span>
            <?php endif; ?>
          </div>

          <?php if ($description !== ''): ?>
            <div style="margin-bottom:1rem; color:#495267; font-size:0.9rem;">
              <?= htmlspecialchars(substr($description, 0, 150)); ?><?= strlen($description) > 150 ? '...' : ''; ?>
            </div>
          <?php endif; ?>

          <?php
            $communityMetaItems = [
                ['text' => number_format($memberCount) . ' members'],
            ];
            if ($creatorName !== '') {
                $communityMetaItems[] = ['text' => 'Creator ' . $creatorName];
            }
            if ($formattedDate !== 'Unknown') {
                $communityMetaItems[] = ['text' => 'Created ' . $formattedDate];
            }
            $communityMetaItems[] = ['text' => 'ID ' . (string)$id];
            $items = $communityMetaItems;
            include __DIR__ . '/../partials/meta-row.php';
          ?>

          <div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="<?= htmlspecialchars($communityUrl); ?>" class="app-btn app-btn-sm" target="_blank">View Community</a>
            <a href="/communities/<?= htmlspecialchars($slug); ?>/manage" class="app-btn app-btn-sm app-btn-secondary">Manage</a>
            <form method="post" action="/admin/communities/<?= $id; ?>/delete" onsubmit="return confirm('Delete this community and all its events? This cannot be undone.');" style="display:inline;">
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
          return '/admin/communities?' . http_build_query($params);
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
