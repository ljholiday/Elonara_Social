<?php
$entity = (object)($entity ?? []);
$url = $entity->url ?? '/events/' . e($entity->slug ?? (string)($entity->id ?? ''));
?>
<article class="app-card">
  <h3 class="app-card-title">
    <a class="app-link" href="<?= e($url) ?>"><?= e($entity->title ?? '') ?></a>
  </h3>
  <?php if (!empty($entity->event_date)): ?>
    <div class="app-card-sub"><?= e(date_fmt($entity->event_date)) ?></div>
  <?php endif; ?>
  <?php if (!empty($entity->description)): ?>
    <p class="app-card-desc"><?= e($entity->description) ?></p>
  <?php endif; ?>
</article>
