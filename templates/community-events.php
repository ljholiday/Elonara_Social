<?php
/**
 * Community Events Template
 */

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('date_fmt')) {
    function date_fmt($date) { return date('M j, Y', strtotime($date)); }
}

$community = $community ?? null;
$events = $events ?? [];
?>

<section class="app-section">
  <?php if (!$community): ?>
    <p class="app-text-muted">Community not found.</p>
  <?php else: ?>
    <div style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
      <div>
        <h1 class="app-heading">Events</h1>
        <p class="app-text-muted">Events in <?= e($community['title']) ?></p>
      </div>
      <?php if (!empty($canCreateEvent) && $canCreateEvent): ?>
        <a href="/events/create?community=<?= urlencode((string)($community['slug'] ?? $community['id'] ?? '')) ?>"
           class="app-btn app-btn-secondary">
          Create Event
        </a>
      <?php endif; ?>
    </div>

    <?php if (empty($events)): ?>
      <div class="app-card app-mt-4">
        <p class="app-text-muted">No events in this community yet.</p>
      </div>
    <?php else: ?>
      <div class="app-stack app-mt-4">
        <?php foreach ($events as $event): $ev = (object)$event; ?>
          <article class="app-card">
            <h3 class="app-heading-sm">
              <a href="/events/<?= e($ev->slug) ?>" class="app-link">
                <?= e($ev->context_label ?? $ev->title ?? '') ?>
              </a>
            </h3>
            <?php if (!empty($ev->description)): ?>
              <p class="app-card-desc"><?= e(mb_substr($ev->description, 0, 200)) ?><?= mb_strlen($ev->description) > 200 ? '...' : '' ?></p>
            <?php endif; ?>
            <div class="app-card-meta">
              <?php if (!empty($ev->event_date)): ?>
                <span><?= e(date_fmt($ev->event_date)) ?></span>
              <?php endif; ?>
              <?php if (!empty($ev->location)): ?>
                <span> · <?= e($ev->location) ?></span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
