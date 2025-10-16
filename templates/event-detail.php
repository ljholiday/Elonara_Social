<section class="app-section app-event-detail">
  <?php if (empty($event)): ?>
    <h1 class="app-heading">Event not found</h1>
    <p class="app-text-muted">We couldn’t find that event.</p>
  <?php else: $e = (object)$event; ?>
    <?php $contextLabelHtml = $context_label_html ?? ''; ?>
    <h1 class="app-heading">
      <?= $contextLabelHtml !== '' ? $contextLabelHtml : e($e->title ?? '') ?>
      <?php
        $badge = app_visibility_badge($e->privacy ?? null, $e->community_privacy ?? null);
        if (!empty($badge['label'])):
      ?>
        <span class="<?= e($badge['class']) ?>" style="margin-left:0.75rem; font-size:0.8rem;">
          <?= e($badge['label']) ?>
        </span>
      <?php endif; ?>
    </h1>
    <?php if (!empty($e->event_date)): ?>
      <div class="app-sub"><?= e(date_fmt($e->event_date)) ?></div>
    <?php endif; ?>
    <?php if (!empty($e->description)): ?>
      <p class="app-body"><?= e($e->description) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</section>
