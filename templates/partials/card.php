<?php
$entity = (object)($entity ?? []);
$url = $entity->url ?? '/events/' . e($entity->slug ?? (string)($entity->id ?? ''));
$badge = null;
if (property_exists($entity, 'privacy')) {
    $badge = app_visibility_badge($entity->privacy);
}
?>
<article class="app-card">
  <h3 class="app-card-title">
    <a class="app-link" href="<?= e($url) ?>"><?= e($entity->title ?? '') ?></a>
    <?php if ($badge !== null && ($badge['label'] ?? '') !== ''): ?>
      <span class="<?= e($badge['class']) ?>" style="margin-left:0.5rem;"><?= e($badge['label']) ?></span>
    <?php endif; ?>
  </h3>
  <?php if (!empty($entity->event_date)): ?>
    <div class="app-card-sub"><?= e(date_fmt($entity->event_date)) ?></div>
  <?php endif; ?>
  <?php if (!empty($entity->description)): ?>
    <p class="app-card-desc"><?= e($entity->description) ?></p>
  <?php endif; ?>
</article>
