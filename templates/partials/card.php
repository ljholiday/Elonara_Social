<?php
$entity = (object)($entity ?? []);
$url = $entity->url ?? '/events/' . e($entity->slug ?? (string)($entity->id ?? ''));
?>
<article class="vt-card">
  <h3 class="vt-card-title">
    <a class="vt-link" href="<?= e($url) ?>"><?= e($entity->title ?? '') ?></a>
  </h3>
  <?php if (!empty($entity->event_date)): ?>
    <div class="vt-card-sub"><?= e(date_fmt($entity->event_date)) ?></div>
  <?php endif; ?>
  <?php if (!empty($entity->description)): ?>
    <p class="vt-card-desc"><?= e($entity->description) ?></p>
  <?php endif; ?>
</article>
