<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'description' => '', 'event_date' => ''];
?>
<section class="app-section app-event-create">
  <h1 class="app-heading">Create Event</h1>

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

  <form method="post" action="/events/create" class="app-form app-stack">
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
      <button type="submit" class="app-btn app-btn-primary">Create Event</button>
      <a class="app-btn" href="/events">Cancel</a>
    </div>
  </form>
</section>
