<?php
/** @var array<int,array<string,mixed>> $events */
/** @var string $filter */

$filter = $filter ?? 'all';
?>
<section class="vt-section vt-events">
  <h1 class="vt-heading">Upcoming Events</h1>

  <?php
  $card_path = __DIR__ . '/partials/card.php';
  if (!is_file($card_path)) {
      echo '<p class="vt-text-muted">Card partial not found at templates/partials/card.php</p>';
      return;
  }
  ?>

  <?php if (!empty($events)) : ?>
    <div class="vt-grid vt-events-grid">
      <?php foreach ($events as $row):
        $entity = (object)[
          'id'          => $row['id'] ?? null,
          'title'       => $row['title'] ?? '',
          'description' => $row['description'] ?? '',
          'event_date'  => $row['event_date'] ?? '',
          'slug'        => $row['slug'] ?? (string)($row['id'] ?? ''),
          'url'         => '/events/' . ($row['slug'] ?? (string)($row['id'] ?? '')),
        ];
        include __DIR__ . '/partials/card.php';
      endforeach; ?>
    </div>
  <?php else: ?>
    <p class="vt-text-muted">
      <?php if ($filter === 'my'): ?>
        You do not have any upcoming events yet.
      <?php else: ?>
        No events found.
      <?php endif; ?>
    </p>
  <?php endif; ?>
</section>
