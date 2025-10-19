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

    <form method="post" action="/communities/<?= e($community['slug'] ?? '') ?>/edit" class="app-form app-stack" enctype="multipart/form-data">
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

      <div class="app-field">
        <label class="app-label" for="cover_image">Cover Image</label>
        <?php if (!empty($community['cover_image'])): ?>
          <div class="app-mb-3">
            <?php
              $coverUrl = getImageUrl($community['cover_image'], 'mobile', 'original');
              if ($coverUrl):
            ?>
              <img src="<?= e($coverUrl) ?>" alt="<?= e($community['cover_image_alt'] ?? 'Current cover image') ?>" style="max-width: 100%; height: auto; border-radius: 4px;">
              <div class="app-text-muted app-mt-1">Current cover image</div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <input
          type="file"
          class="app-input<?= isset($errors['cover_image']) ? ' is-invalid' : '' ?>"
          id="cover_image"
          name="cover_image"
          accept="image/jpeg,image/png,image/gif,image/webp"
        >
        <small class="app-help-text">Optional. Upload a new image to replace the current one. Maximum 10MB.</small>
        <?php if (isset($errors['cover_image'])): ?>
          <div class="app-field-error"><?= e($errors['cover_image']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field">
        <label class="app-label" for="cover_image_alt">Cover image description</label>
        <input
          type="text"
          class="app-input<?= isset($errors['cover_image_alt']) ? ' is-invalid' : '' ?>"
          id="cover_image_alt"
          name="cover_image_alt"
          placeholder="Describe the image for accessibility"
          value="<?= e($input['cover_image_alt'] ?? '') ?>"
        >
        <small class="app-help-text">Required if uploading a new image. Describe what's in the image for screen reader users.</small>
        <?php if (isset($errors['cover_image_alt'])): ?>
          <div class="app-field-error"><?= e($errors['cover_image_alt']) ?></div>
        <?php endif; ?>
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
