<?php
/** @var array<int,array<string,mixed>> $communities */
/** @var string $circle */

$circle = $circle ?? 'all';
?>
<section class="app-section app-communities">
  <h1 class="app-heading">Communities</h1>

  <?php
  $card_path = __DIR__ . '/partials/card.php';
  if (!is_file($card_path)) {
      echo '<p class="app-text-muted">Card partial not found at templates/partials/card.php</p>';
      return;
  }
  ?>

  <?php if (!empty($communities)) : ?>
    <div class="app-grid app-communities-grid">
      <?php foreach ($communities as $row):
        $entity = (object)[
          'id'          => $row['id'] ?? null,
          'title'       => $row['title'] ?? '',
          'description' => $row['description'] ?? '',
          'slug'        => $row['slug'] ?? (string)($row['id'] ?? ''),
          'url'         => '/communities/' . ($row['slug'] ?? (string)($row['id'] ?? '')),
          'privacy'     => $row['privacy'] ?? null,
        ];
        include __DIR__ . '/partials/card.php';
      endforeach; ?>
    </div>
  <?php else: ?>
    <p class="app-text-muted">No communities found.</p>
  <?php endif; ?>
</section>
