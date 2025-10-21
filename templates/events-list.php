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
    <p class="app-text-muted">
      <?php if ($filter === 'my'): ?>
        You do not have any upcoming events yet.
      <?php else: ?>
        No events found.
      <?php endif; ?>
    </p>
  <?php endif; ?>
</section>
