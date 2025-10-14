<?php
$errors = $errors ?? [];
$input = $input ?? ['name' => '', 'description' => '', 'privacy' => 'public'];
$community = $community ?? null;
?>
<section class="vt-section vt-community-edit">
  <?php if (!$community): ?>
    <h1 class="vt-heading">Community not found</h1>
    <p class="vt-text-muted">We couldn’t find that community.</p>
  <?php else: ?>
    <h1 class="vt-heading">Edit Community</h1>
    <p class="vt-text-muted">Editing <strong><?= e($community['title'] ?? '') ?></strong></p>

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

    <form method="post" action="/communities/<?= e($community['slug'] ?? '') ?>/edit" class="vt-form vt-stack">
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
        <button type="submit" class="vt-btn vt-btn-primary">Save Changes</button>
        <a class="vt-btn" href="/communities/<?= e($community['slug'] ?? '') ?>">Cancel</a>
      </div>
    </form>

    <div class="vt-danger-zone vt-mt-6">
      <h2 class="vt-heading-sm">Danger Zone</h2>
      <p class="vt-text-muted">Deleting a community cannot be undone.</p>
      <form method="post" action="/communities/<?= e($community['slug'] ?? '') ?>/delete" class="vt-inline-form" onsubmit="return confirm('Delete this community?');">
        <button type="submit" class="vt-btn vt-btn-danger">Delete Community</button>
      </form>
    </div>
  <?php endif; ?>
</section>
