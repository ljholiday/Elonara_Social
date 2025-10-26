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
      <label class="app-label">Cover Image</label>
      <div class="app-mb-3" id="cover-image-preview-container">
        <img src="" alt="Cover image preview" class="app-img" style="max-width: 400px; display: none;" id="cover-image-preview">
      </div>
      <button type="button" class="app-btn app-btn-primary" onclick="window.appOpenImageLibrary({ imageType: 'cover', targetPreview: 'cover-image-preview', targetAltInput: 'cover-image-alt', targetUrlInput: 'cover-image-url' })">
        Select Image
      </button>
      <input type="hidden" id="cover-image-alt" name="cover_image_alt" value="<?= e($input['cover_image_alt'] ?? '') ?>">
      <input type="hidden" id="cover-image-url" name="cover_image_url_uploaded" value="">
      <small class="app-help-text" style="display: block; margin-top: 0.5rem;">Click to upload a new image or choose from your library. Recommended size: 1200x400px.</small>
      <?php if (isset($errors['cover_image'])): ?>
        <div class="app-field-error"><?= e($errors['cover_image']) ?></div>
      <?php endif; ?>
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
