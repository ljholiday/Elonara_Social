<section class="app-section app-event-detail">
  <?php if (empty($event)): ?>
    <h1 class="app-heading">Event not found</h1>
    <p class="app-text-muted">We couldn’t find that event.</p>
  <?php else: $e = (object)$event; ?>
    <?php
      $contextLabelHtml = $context_label_html ?? '';
      $featuredImageUrl = !empty($e->featured_image) ? getImageUrl($e->featured_image, 'desktop', 'original') : '';
      $featuredImageAlt = trim((string)($e->featured_image_alt ?? ''));
      $dateDisplay = '';
      if (!empty($e->event_date)) {
          $dateDisplay = !empty($e->end_date)
              ? sprintf('%s - %s', date_fmt($e->event_date), date_fmt($e->end_date))
              : date_fmt($e->event_date);
      }
      $recurrenceSummary = (string)($recurrence_summary ?? '');
      $hasLocation = !empty($e->location);
    ?>
    <?php if ($featuredImageUrl !== ''): ?>
      <figure class="app-mb-4" style="margin:0;border-radius:8px;overflow:hidden;">
        <img
          src="<?= e($featuredImageUrl) ?>"
          alt="<?= e($featuredImageAlt !== '' ? $featuredImageAlt : 'Event featured image') ?>"
          style="display:block;width:100%;height:auto;"
        >
      </figure>
    <?php endif; ?>
    <header class="app-mb-4">
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
    </header>
    <?php if ($dateDisplay !== '' || $recurrenceSummary !== '' || $hasLocation): ?>
      <div class="app-flex app-flex-wrap app-gap app-mb-4 app-text-muted">
        <?php if ($dateDisplay !== ''): ?>
          <div><?= e($dateDisplay) ?></div>
        <?php endif; ?>
        <?php if ($recurrenceSummary !== ''): ?>
          <div><?= e($recurrenceSummary) ?></div>
        <?php endif; ?>
        <?php if ($hasLocation): ?>
          <div><?= e($e->location) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($e->description)): ?>
      <p class="app-body"><?= e($e->description) ?></p>
    <?php endif; ?>
  <?php endif; ?>
</section>
