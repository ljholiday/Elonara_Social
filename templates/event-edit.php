<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'description' => '', 'event_date' => ''];
$event = $event ?? null;
?>
<section class="app-section app-event-edit">
  <?php if (!$event): ?>
    <h1 class="app-heading">Event not found</h1>
    <p class="app-text-muted">We couldn’t find that event.</p>
  <?php else: ?>
    <h1 class="app-heading">Edit Event</h1>
    <p class="app-text-muted">Editing <strong><?= e($event['title'] ?? '') ?></strong></p>

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

    <form method="post" action="/events/<?= e($event['slug'] ?? '') ?>/edit" class="app-form app-stack">
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

      <div class="app-actions">
        <button type="submit" class="app-btn app-btn-primary">Save Changes</button>
        <a class="app-btn" href="/events/<?= e($event['slug'] ?? '') ?>">Cancel</a>
      </div>
    </form>

    <div class="app-danger-zone app-mt-6">
      <h2 class="app-heading-sm">Danger Zone</h2>
      <p class="app-text-muted">Deleting an event cannot be undone.</p>
      <form method="post" action="/events/<?= e($event['slug'] ?? '') ?>/delete" class="app-inline-form" onsubmit="return confirm('Delete this event?');">
        <button type="submit" class="app-btn app-btn-danger">Delete Event</button>
      </form>
    </div>

  <?php endif; ?>
</section>

<?php if ($event): ?>
<?php
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
?>
<script src="<?= htmlspecialchars($assetBase . '/js/event-form.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
