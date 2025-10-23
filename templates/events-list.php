<?php
/** @var array<int,array<string,mixed>> $events */
/** @var string $filter */

$filter = $filter ?? 'all';
?>
<section class="app-section app-events">
  <h1 class="app-heading">Upcoming Events</h1>

  <?php
  $card_path = __DIR__ . '/partials/entity-card.php';
  if (!is_file($card_path)) {
      echo '<p class="app-text-muted">Entity card partial not found at templates/partials/entity-card.php</p>';
      return;
  }
  ?>

  <?php if (!empty($events)) : ?>
    <div class="app-grid app-events-grid">
      <?php foreach ($events as $row):
        $slug = $row['slug'] ?? (string)($row['id'] ?? '');
        $privacy = strtolower((string)($row['privacy'] ?? 'public'));
        $contextLabel = (string)($row['context_label'] ?? '');

        $entity = (object)[
          'id' => (int)($row['id'] ?? 0),
          'title' => $contextLabel !== '' ? $contextLabel : (string)($row['title'] ?? ''),
          'slug' => $slug,
          'event_date' => $row['event_date'] ?? null,
          'end_date' => $row['end_date'] ?? null,
          'description' => $row['description'] ?? '',
          'venue_info' => $row['location'] ?? '',
          'privacy' => $privacy,
        ];

        $entity_type = 'event';

        $badges = [];
        if ($contextLabel !== '') {
            $badges[] = [
                'label' => $contextLabel,
                'class' => 'app-badge app-badge-secondary',
            ];
        }
        $badges[] = [
            'label' => ucfirst($privacy),
            'class' => $privacy === 'private' ? 'app-badge app-badge-secondary' : 'app-badge app-badge-success',
        ];

        $stats = [];

        $actions = [
            [
                'label' => 'View',
                'url' => '/events/' . $slug,
            ],
        ];

        $description = $row['description'] ?? '';

        include __DIR__ . '/partials/entity-card.php';
      endforeach; ?>
    </div>
  <?php else: ?>
    <div class="app-card">
      <div class="app-card-body app-text-center app-stack app-gap-3">
        <?php if ($filter === 'my'): ?>
          <p class="app-text-muted">You don't have any upcoming events yet. Plan your first event or check out what others are organizing!</p>
          <div class="app-flex app-gap-2 app-justify-center app-flex-wrap">
            <a class="app-btn app-btn-primary" href="/events/create">Create Event</a>
            <a class="app-btn app-btn-outline" href="/events?filter=all">Browse All Events</a>
          </div>
        <?php else: ?>
          <p class="app-text-muted">No events found. Be the first to create one!</p>
          <a class="app-btn app-btn-primary" href="/events/create">Create Event</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
