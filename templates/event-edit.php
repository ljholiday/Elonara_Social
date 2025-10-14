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
        <label class="app-label" for="event_date">Date &amp; Time</label>
        <input
          class="app-input<?= isset($errors['event_date']) ? ' is-invalid' : '' ?>"
          type="datetime-local"
          id="event_date"
          name="event_date"
          value="<?= e($input['event_date'] ?? '') ?>"
        >
        <p class="app-field-help">Leave blank for TBD.</p>
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
