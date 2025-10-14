<?php
$errors = $errors ?? [];
$input = $input ?? ['name' => '', 'description' => '', 'privacy' => 'public'];
$community = $community ?? null;
?>
<section class="app-section app-community-edit">
  <?php if (!$community): ?>
    <h1 class="app-heading">Community not found</h1>
    <p class="app-text-muted">We couldn’t find that community.</p>
  <?php else: ?>
    <h1 class="app-heading">Edit Community</h1>
    <p class="app-text-muted">Editing <strong><?= e($community['title'] ?? '') ?></strong></p>

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

    <form method="post" action="/communities/<?= e($community['slug'] ?? '') ?>/edit" class="app-form app-stack">
      <div class="app-field">
        <label class="app-label" for="name">Name</label>
        <input
          class="app-input<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
          type="text"
          id="name"
          name="name"
          value="<?= e($input['name'] ?? '') ?>"
          required
        >
      </div>

      <div class="app-field">
        <label class="app-label" for="privacy">Privacy</label>
        <select class="app-input" id="privacy" name="privacy">
          <option value="public"<?= ($input['privacy'] ?? 'public') === 'public' ? ' selected' : '' ?>>Public</option>
          <option value="private"<?= ($input['privacy'] ?? 'public') === 'private' ? ' selected' : '' ?>>Private</option>
        </select>
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
        <a class="app-btn" href="/communities/<?= e($community['slug'] ?? '') ?>">Cancel</a>
      </div>
    </form>

    <div class="app-danger-zone app-mt-6">
      <h2 class="app-heading-sm">Danger Zone</h2>
      <p class="app-text-muted">Deleting a community cannot be undone.</p>
      <form method="post" action="/communities/<?= e($community['slug'] ?? '') ?>/delete" class="app-inline-form" onsubmit="return confirm('Delete this community?');">
        <button type="submit" class="app-btn app-btn-danger">Delete Community</button>
      </form>
    </div>
  <?php endif; ?>
</section>
