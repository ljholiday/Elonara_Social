<?php
/** @var array<int,array<string,mixed>> $conversations */
/** @var string $circle */
/** @var array $pagination */

$circle = $circle ?? 'all';
$pagination = $pagination ?? ['page' => 1, 'per_page' => 20, 'has_more' => false, 'next_page' => null];
?>
<section class="app-section app-conversations">
  <h1 class="app-heading">Conversations</h1>

  <?php if (!empty($conversations)): ?>
    <div id="app-convo-list" class="app-stack">
      <?php foreach ($conversations as $row):
        $slug = (string)($row['slug'] ?? '');
        if ($slug === '') {
            continue;
        }

        $privacy = strtolower((string)($row['privacy'] ?? 'public'));
        if ($privacy === '') {
            $privacy = 'public';
        }

        $entity = (object)[
            'id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? ''),
            'slug' => $slug,
            'created_at' => $row['created_at'] ?? null,
            'privacy' => $privacy,
        ];

        $entity_type = 'conversation';

        $badges = [];
        if (!empty($row['event_title'])) {
            $badges[] = [
                'label' => 'Event: ' . (string)$row['event_title'],
                'class' => 'app-badge-secondary',
            ];
        } elseif (!empty($row['community_name'])) {
            $badges[] = [
                'label' => 'Community: ' . (string)$row['community_name'],
                'class' => 'app-badge-secondary',
            ];
        } else {
            $badges[] = [
                'label' => 'General Discussion',
                'class' => 'app-badge-secondary',
            ];
        }

        $badges[] = [
            'label' => ucfirst($privacy),
            'class' => $privacy === 'private' ? 'app-badge-secondary' : 'app-badge-success',
        ];

        $replyCount = (int)($row['reply_count'] ?? 0);
        $stats = $replyCount >= 0 ? [
            [
                'value' => $replyCount,
                'label' => 'Replies',
            ],
        ] : [];

        $actions = [
            [
                'label' => 'View',
                'url' => '/conversations/' . $slug,
                'class' => 'app-btn-secondary',
            ],
        ];

        $description = $row['content'] ?? '';
        $truncate_length = 35;

        include __DIR__ . '/partials/entity-card.php';
      ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div id="app-convo-list">
      <div class="app-card">
        <div class="app-card-body app-text-center app-stack app-gap-3">
          <p class="app-text-muted">No conversations found. Start a discussion and connect with your community!</p>
          <div class="app-flex app-gap-2 app-justify-center app-flex-wrap">
            <a class="app-btn app-btn-primary" href="/conversations/create">Start Conversation</a>
            <?php if ($circle !== 'all'): ?>
              <a class="app-btn app-btn-outline" href="/conversations?circle=all">Browse All Conversations</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($pagination['has_more'])): ?>
    <div class="app-mt-4">
      <a class="app-btn" href="/conversations?circle=<?= urlencode($circle) ?>&page=<?= (int)($pagination['next_page'] ?? (($pagination['page'] ?? 1) + 1)) ?>">Older Conversations</a>
    </div>
  <?php endif; ?>
</section>
