<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'description' => '', 'event_date' => ''];
$event = $event ?? null;
?>
<section class="vt-section vt-event-edit">
  <?php if (!$event): ?>
    <h1 class="vt-heading">Event not found</h1>
    <p class="vt-text-muted">We couldn’t find that event.</p>
  <?php else: ?>
    <h1 class="vt-heading">Edit Event</h1>
    <p class="vt-text-muted">Editing <strong><?= e($event['title'] ?? '') ?></strong></p>

    <?php if ($errors): ?>
      <div class="vt-alert vt-alert-error vt-mb-4">
        <p>Please fix the issues below:</p>
        <ul>
          <?php foreach ($errors as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="/events/<?= e($event['slug'] ?? '') ?>/edit" class="vt-form vt-stack">
      <div class="vt-field">
        <label class="vt-label" for="title">Title</label>
        <input
          class="vt-input<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
          type="text"
          id="title"
          name="title"
          value="<?= e($input['title'] ?? '') ?>"
          required
        >
      </div>

      <div class="vt-field">
        <label class="vt-label" for="event_date">Date &amp; Time</label>
        <input
          class="vt-input<?= isset($errors['event_date']) ? ' is-invalid' : '' ?>"
          type="datetime-local"
          id="event_date"
          name="event_date"
          value="<?= e($input['event_date'] ?? '') ?>"
        >
        <p class="vt-field-help">Leave blank for TBD.</p>
      </div>

      <div class="vt-field">
        <label class="vt-label" for="description">Description</label>
        <textarea
          class="vt-textarea"
          id="description"
          name="description"
          rows="5"
        ><?= e($input['description'] ?? '') ?></textarea>
      </div>

      <div class="vt-actions">
        <button type="submit" class="vt-btn vt-btn-primary">Save Changes</button>
        <a class="vt-btn" href="/events/<?= e($event['slug'] ?? '') ?>">Cancel</a>
      </div>
    </form>

    <div class="vt-danger-zone vt-mt-6">
      <h2 class="vt-heading-sm">Danger Zone</h2>
      <p class="vt-text-muted">Deleting an event cannot be undone.</p>
      <form method="post" action="/events/<?= e($event['slug'] ?? '') ?>/delete" class="vt-inline-form" onsubmit="return confirm('Delete this event?');">
        <button type="submit" class="vt-btn vt-btn-danger">Delete Event</button>
      </form>
    </div>

  <?php endif; ?>
</section>
