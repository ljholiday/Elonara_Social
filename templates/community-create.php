<?php
$errors = $errors ?? [];
$input = $input ?? ['name' => '', 'description' => '', 'privacy' => 'public'];
?>
<section class="vt-section vt-community-create">
  <h1 class="vt-heading">Create Community</h1>

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

  <form method="post" action="/communities/create" class="vt-form vt-stack">
    <div class="vt-field">
      <label class="vt-label" for="name">Name</label>
      <input
        class="vt-input<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
        type="text"
        id="name"
        name="name"
        value="<?= e($input['name'] ?? '') ?>"
        required
      >
    </div>

    <div class="vt-field">
      <label class="vt-label" for="privacy">Privacy</label>
      <select class="vt-input" id="privacy" name="privacy">
        <option value="public"<?= ($input['privacy'] ?? 'public') === 'public' ? ' selected' : '' ?>>Public</option>
        <option value="private"<?= ($input['privacy'] ?? 'public') === 'private' ? ' selected' : '' ?>>Private</option>
      </select>
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
      <button type="submit" class="vt-btn vt-btn-primary">Create Community</button>
      <a class="vt-btn" href="/communities">Cancel</a>
    </div>
  </form>
</section>
