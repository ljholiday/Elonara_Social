<?php
$errors = $errors ?? [];
$input = $input ?? ['name' => '', 'description' => '', 'privacy' => 'public'];
?>
<section class="app-section app-community-create">
  <h1 class="app-heading">Create Community</h1>

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

  <form method="post" action="/communities/create" class="app-form app-stack" enctype="multipart/form-data">
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
      <input
        type="file"
        class="app-input<?= isset($errors['cover_image']) ? ' is-invalid' : '' ?>"
        id="cover_image"
        name="cover_image"
        accept="image/jpeg,image/png,image/gif,image/webp"
      >
      <small class="app-help-text">Optional. Maximum 10MB. JPEG, PNG, GIF, or WebP format.</small>
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
      <small class="app-help-text">Required if uploading an image. Describe what's in the image for screen reader users.</small>
      <?php if (isset($errors['cover_image_alt'])): ?>
        <div class="app-field-error"><?= e($errors['cover_image_alt']) ?></div>
      <?php endif; ?>
    </div>

    <div class="app-actions">
      <button type="submit" class="app-btn app-btn-primary">Create Community</button>
      <a class="app-btn" href="/communities">Cancel</a>
    </div>
  </form>
</section>
