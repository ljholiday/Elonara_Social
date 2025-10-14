<section class="app-section app-event-detail">
  <?php if (empty($event)): ?>
    <h1 class="app-heading">Event not found</h1>
    <p class="app-text-muted">We couldn’t find that event.</p>
  <?php else: $e = (object)$event; ?>
    <h1 class="app-heading"><?= e($e->title ?? '') ?></h1>
    <?php if (!empty($e->event_date)): ?>
      <div class="app-sub"><?= e(date_fmt($e->event_date)) ?></div>
    <?php endif; ?>
    <?php if (!empty($e->description)): ?>
      <p class="app-body"><?= e($e->description) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</section>

