<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'description' => '', 'event_date' => ''];
$context = $context ?? ['allowed' => false, 'label' => '', 'label_html' => '', 'community_id' => null];
$contextLabel = (string)($context['label'] ?? '');
$contextLabelHtml = (string)($context['label_html'] ?? '');
$contextAllowed = (bool)($context['allowed'] ?? false);
?>
<section class="app-section app-event-create">
  <h1 class="app-heading">Create Event</h1>

  <?php if ($contextLabel !== '' || $contextLabelHtml !== ''): ?>
    <p class="app-text-muted">This event will be created in 
      <?php if ($contextLabelHtml !== ''): ?>
        <?= $contextLabelHtml; ?>
      <?php else: ?>
        <?= e($contextLabel); ?>
      <?php endif; ?>.
    </p>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="app-alert app-alert-error app-mb-4">
      <p>Please fix the issues below:</p>
      <ul>
        <?php foreach ($errors as $message): ?>
          <li><?= e($message) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="/events/create" class="app-form app-stack" enctype="multipart/form-data">
    <?php if (!empty($context['community_id'])): ?>
      <input type="hidden" name="community_id" value="<?= (int)$context['community_id']; ?>">
    <?php endif; ?>
    <?php if (!empty($context['community_slug'])): ?>
      <input type="hidden" name="community" value="<?= e((string)$context['community_slug']); ?>">
    <?php endif; ?>
    <div class="app-field">
      <label class="app-label" for="title">Title</label>
      <input
        class="app-input<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
        type="text"
        id="title"
        name="title"
        value="<?= e($input['title'] ?? '') ?>"
        required
      >
    </div>

    <div class="app-field">
      <label class="app-label" for="event_date">Start Date &amp; Time</label>
      <input
        class="app-input<?= isset($errors['event_date']) ? ' is-invalid' : '' ?>"
        type="datetime-local"
        id="event_date"
        name="event_date"
        value="<?= e($input['event_date'] ?? '') ?>"
      >
      <p class="app-field-help">Leave blank for TBD. Default time is 6:00 PM.</p>
    </div>

    <div class="app-field">
      <label class="app-label" for="end_date">End Date &amp; Time</label>
      <input
        class="app-input<?= isset($errors['end_date']) ? ' is-invalid' : '' ?>"
        type="datetime-local"
        id="end_date"
        name="end_date"
        value="<?= e($input['end_date'] ?? '') ?>"
      >
      <p class="app-field-help">Optional. Leave blank for single-day event.</p>
    </div>

    <div class="app-field">
      <label class="app-label" for="location">Location</label>
      <input
        class="app-input<?= isset($errors['location']) ? ' is-invalid' : '' ?>"
        type="text"
        id="location"
        name="location"
        value="<?= e($input['location'] ?? '') ?>"
        placeholder="Enter event location"
      >
      <p class="app-field-help">Optional. e.g., "Central Park" or "123 Main St, City"</p>
    </div>

    <div class="app-field">
      <label class="app-label" for="description">Description</label>
      <textarea
        class="app-textarea"
        id="description"
        name="description"
        rows="5"
      ><?= e($input['description'] ?? '') ?></textarea>
    </div>

    <div class="app-field">
      <label class="app-label">Featured Image</label>
      <div class="app-mb-3" id="featured-image-preview-container">
        <img src="" alt="Featured image preview" class="app-img" style="max-width: 400px; display: none;" id="featured-image-preview">
      </div>
      <button type="button" class="app-btn app-btn-primary" onclick="window.appOpenImageLibrary({ imageType: 'featured', targetPreview: 'featured-image-preview', targetAltInput: 'featured-image-alt', targetUrlInput: 'featured-image-url' })">
        Select Image
      </button>
      <input type="hidden" id="featured-image-alt" name="featured_image_alt" value="<?= e($input['featured_image_alt'] ?? '') ?>">
      <input type="hidden" id="featured-image-url" name="featured_image_url_uploaded" value="">
      <small class="app-help-text" style="display: block; margin-top: 0.5rem;">Click to upload a new image or choose from your library. Recommended size: 1200x630px.</small>
      <?php if (isset($errors['featured_image'])): ?>
        <div class="app-field-error"><?= e($errors['featured_image']) ?></div>
      <?php endif; ?>
      <?php if (isset($errors['featured_image_alt'])): ?>
        <div class="app-field-error"><?= e($errors['featured_image_alt']) ?></div>
      <?php endif; ?>
    </div>

    <div class="app-actions">
      <button type="submit" class="app-btn app-btn-primary"<?= $contextAllowed ? '' : ' disabled' ?>>Create Event</button>
      <a class="app-btn" href="/events">Cancel</a>
    </div>
  </form>
</section>

<?php
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
?>
<script src="<?= htmlspecialchars($assetBase . '/js/event-form.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
