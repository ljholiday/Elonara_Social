<section class="vt-section vt-event-detail">
  <?php if (empty($event)): ?>
    <h1 class="vt-heading">Event not found</h1>
    <p class="vt-text-muted">We couldn’t find that event.</p>
  <?php else: $e = (object)$event; ?>
    <h1 class="vt-heading"><?= e($e->title ?? '') ?></h1>
    <?php if (!empty($e->event_date)): ?>
      <div class="vt-sub"><?= e(date_fmt($e->event_date)) ?></div>
    <?php endif; ?>
    <?php if (!empty($e->description)): ?>
      <p class="vt-body"><?= e($e->description) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</section>

