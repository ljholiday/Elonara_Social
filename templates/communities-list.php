<?php
/** @var array<int,array<string,mixed>> $communities */
/** @var string $circle */

$circle = $circle ?? 'all';
?>
<section class="app-section app-communities">
  <h1 class="app-heading">Communities</h1>

  <?php
  $card_path = __DIR__ . '/partials/entity-card.php';
  if (!is_file($card_path)) {
      echo '<p class="app-text-muted">Entity card partial not found at templates/partials/entity-card.php</p>';
      return;
  }
  ?>

  <?php if (!empty($communities)) : ?>
    <div class="app-grid app-communities-grid">
      <?php foreach ($communities as $row):
        $slug = $row['slug'] ?? (string)($row['id'] ?? '');
        $privacy = strtolower((string)($row['privacy'] ?? 'public'));
        $memberCount = isset($row['member_count']) ? (int)$row['member_count'] : null;
        $eventCount = isset($row['event_count']) ? (int)$row['event_count'] : null;

        $entity = (object)[
          'id' => (int)($row['id'] ?? 0),
          'name' => (string)($row['title'] ?? ''),
          'title' => (string)($row['title'] ?? ''),
          'slug' => $slug,
          'description' => $row['description'] ?? '',
          'created_at' => $row['created_at'] ?? null,
          'privacy' => $privacy,
        ];

        $entity_type = 'community';

        $badges = [
            [
                'label' => ucfirst($privacy),
                'class' => $privacy === 'private' ? 'app-badge app-badge-secondary' : 'app-badge app-badge-success',
            ],
        ];

        $stats = [];
        if ($memberCount !== null) {
            $stats[] = [
                'value' => $memberCount,
                'label' => 'Members',
            ];
        }
        if ($eventCount !== null) {
            $stats[] = [
                'value' => $eventCount,
                'label' => 'Events',
            ];
        }

        $actions = [
            [
                'label' => 'View',
                'url' => '/communities/' . $slug,
            ],
        ];

        $description = $row['description'] ?? '';

        include __DIR__ . '/partials/entity-card.php';
      endforeach; ?>
    </div>
  <?php else: ?>
    <div class="app-card">
      <div class="app-card-body app-text-center app-stack app-gap-3">
        <p class="app-text-muted">No communities found. Discover communities others have created or start your own!</p>
        <div class="app-flex app-gap-2 app-justify-center app-flex-wrap">
          <a class="app-btn app-btn-primary" href="/communities/create">Create Community</a>
          <?php if ($circle !== 'all'): ?>
            <a class="app-btn app-btn-outline" href="/communities?circle=all">Browse All Communities</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>
