<?php
/**
 * Profile Edit Template
 * Form for editing user profile
 */

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

$u = isset($user) && is_array($user) ? (object)$user : null;
$errors = $errors ?? [];
$input = $input ?? [];
?>

<section class="app-section">
  <?php if ($u): ?>
    <h1 class="app-heading app-mb-6">Edit Profile</h1>

    <?php if (!empty($errors)): ?>
      <div class="app-alert app-alert-error app-mb-4">
        <ul>
          <?php foreach ($errors as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="/profile/update" class="app-form app-stack" enctype="multipart/form-data">
      <?php if (function_exists('app_service')): ?>
        <?php echo app_service('security.service')->nonceField('app_profile_update', 'profile_nonce'); ?>
      <?php endif; ?>

      <div class="app-field">
        <label class="app-label" for="display-name">Display Name</label>
        <input
          type="text"
          class="app-input<?= isset($errors['display_name']) ? ' is-invalid' : '' ?>"
          id="display-name"
          name="display_name"
          value="<?= e($input['display_name'] ?? '') ?>"
          required
          minlength="2"
          maxlength="100"
        >
        <small class="app-help-text">How you want to be displayed across the site. Between 2-100 characters.</small>
        <?php if (isset($errors['display_name'])): ?>
          <div class="app-field-error"><?= e($errors['display_name']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field">
        <label class="app-label" for="bio">Bio</label>
        <textarea
          class="app-textarea<?= isset($errors['bio']) ? ' is-invalid' : '' ?>"
          id="bio"
          name="bio"
          rows="4"
          maxlength="500"
        ><?= e($input['bio'] ?? '') ?></textarea>
        <small class="app-help-text">Tell us about yourself. Maximum 500 characters.</small>
        <?php if (isset($errors['bio'])): ?>
          <div class="app-field-error"><?= e($errors['bio']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field">
        <label class="app-label" for="avatar">Avatar</label>
        <div class="app-mb-3" id="avatar-preview-container">
          <?php if (!empty($u->avatar_url)): ?>
            <?php
              $avatarUrl = getImageUrl($u->avatar_url, 'medium', 'original');
              if ($avatarUrl):
            ?>
              <img src="<?= e($avatarUrl) ?>" alt="Current avatar" class="app-avatar app-avatar-lg" id="avatar-preview">
              <div class="app-text-muted app-mt-1">Current avatar</div>
            <?php else: ?>
              <div class="app-avatar app-avatar-lg app-avatar-placeholder" id="avatar-preview">
                <?= strtoupper(substr($u->display_name ?? $u->username ?? 'U', 0, 1)) ?>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="app-avatar app-avatar-lg app-avatar-placeholder" id="avatar-preview" style="display: none;">
              <?= strtoupper(substr($u->display_name ?? $u->username ?? 'U', 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>
        <input
          type="file"
          class="app-input<?= isset($errors['avatar']) ? ' is-invalid' : '' ?>"
          id="avatar"
          name="avatar"
          accept="image/jpeg,image/png,image/gif,image/webp"
          data-preview="avatar-preview"
        >
        <small class="app-help-text">Upload a new avatar. Maximum 10MB. JPEG, PNG, GIF, or WebP format.</small>
        <?php if (isset($errors['avatar'])): ?>
          <div class="app-field-error"><?= e($errors['avatar']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field">
        <label class="app-label" for="avatar-alt">Avatar description</label>
        <input
          type="text"
          class="app-input<?= isset($errors['avatar_alt']) ? ' is-invalid' : '' ?>"
          id="avatar-alt"
          name="avatar_alt"
          placeholder="Describe your avatar for accessibility"
          value="<?= e($input['avatar_alt'] ?? '') ?>"
        >
        <small class="app-help-text">Required if uploading a new avatar. Describe what's in the image for screen reader users.</small>
        <?php if (isset($errors['avatar_alt'])): ?>
          <div class="app-field-error"><?= e($errors['avatar_alt']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field">
        <label class="app-label" for="cover">Cover Image</label>
        <div class="app-mb-3" id="cover-preview-container">
          <?php if (!empty($u->cover_url)): ?>
            <?php
              $coverUrl = getImageUrl($u->cover_url, 'tablet', 'original');
              if ($coverUrl):
            ?>
              <img src="<?= e($coverUrl) ?>" alt="<?= e($u->cover_alt ?? 'Current cover') ?>" class="app-img" style="max-width: 400px;" id="cover-preview">
              <div class="app-text-muted app-mt-1">Current cover image</div>
            <?php endif; ?>
          <?php else: ?>
            <img src="" alt="Cover preview" class="app-img" style="max-width: 400px; display: none;" id="cover-preview">
          <?php endif; ?>
        </div>
        <input
          type="file"
          class="app-input<?= isset($errors['cover']) ? ' is-invalid' : '' ?>"
          id="cover"
          name="cover"
          accept="image/jpeg,image/png,image/gif,image/webp"
          data-preview="cover-preview"
        >
        <small class="app-help-text">Upload a cover image. Maximum 10MB. JPEG, PNG, GIF, or WebP format. Recommended size: 1200x400px.</small>
        <?php if (isset($errors['cover'])): ?>
          <div class="app-field-error"><?= e($errors['cover']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field">
        <label class="app-label" for="cover-alt">Cover image description</label>
        <input
          type="text"
          class="app-input<?= isset($errors['cover_alt']) ? ' is-invalid' : '' ?>"
          id="cover-alt"
          name="cover_alt"
          placeholder="Describe your cover image for accessibility"
          value="<?= e($input['cover_alt'] ?? '') ?>"
        >
        <small class="app-help-text">Required if uploading a cover image. Describe what's in the image for screen reader users.</small>
        <?php if (isset($errors['cover_alt'])): ?>
          <div class="app-field-error"><?= e($errors['cover_alt']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-actions">
        <button type="submit" class="app-btn app-btn-primary">Save Changes</button>
        <a href="/profile/<?= e($u->username) ?>" class="app-btn app-btn-secondary">Cancel</a>
      </div>
    </form>

    <hr class="app-divider app-my-6">

    <?php
    // Check if Bluesky is connected
    $blueskyService = function_exists('app_service') ? app_service('bluesky.service') : null;
    $isConnected = $blueskyService && $blueskyService->isConnected((int)($u->id ?? 0));
    $credentials = $isConnected ? $blueskyService->getCredentials((int)($u->id ?? 0)) : null;
    ?>

    <section class="app-section">
      <h2 class="app-heading app-heading-md app-mb-4">Bluesky Connection</h2>
      <p class="app-text-muted app-mb-4">
        Connect your Bluesky account to invite your followers to events and communities.
      </p>

      <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="app-alert app-alert-success app-mb-4">
          <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="app-alert app-alert-error app-mb-4">
          <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
      <?php endif; ?>

      <?php if ($isConnected && $credentials): ?>
        <div class="app-card app-mb-4">
          <div class="app-card-body">
            <div class="app-flex app-items-center app-gap-4">
              <div class="app-flex-1">
                <div class="app-text-success app-mb-2">Connected</div>
                <div class="app-text-lg">@<?= e($credentials['handle']) ?></div>
                <div class="app-text-muted app-text-sm">DID: <?= e(substr($credentials['did'], 0, 20)) ?>...</div>
              </div>
              <form method="post" action="/disconnect/bluesky" style="display: inline;">
                <?php if (function_exists('app_service')): ?>
                  <?php echo app_service('security.service')->nonceField('app_nonce', 'nonce'); ?>
                <?php endif; ?>
                <button type="submit" class="app-btn app-btn-sm app-btn-danger">Disconnect</button>
              </form>
            </div>
          </div>
        </div>
      <?php else: ?>
        <form method="post" action="/connect/bluesky" class="app-form app-stack app-card app-card-body">
          <?php if (function_exists('app_service')): ?>
            <?php echo app_service('security.service')->nonceField('app_nonce', 'nonce'); ?>
          <?php endif; ?>

          <div class="app-field">
            <label class="app-label" for="bluesky-identifier">Bluesky Handle or Email</label>
            <input
              type="text"
              class="app-input"
              id="bluesky-identifier"
              name="identifier"
              placeholder="user.bsky.social or email@example.com"
              required
            >
            <small class="app-help-text">Your Bluesky handle (e.g., user.bsky.social) or the email you use to log in.</small>
          </div>

          <div class="app-field">
            <label class="app-label" for="bluesky-password">App Password</label>
            <input
              type="password"
              class="app-input"
              id="bluesky-password"
              name="password"
              required
            >
            <small class="app-help-text">
              Create an app password at <a href="https://bsky.app/settings/app-passwords" target="_blank" rel="noopener">bsky.app/settings/app-passwords</a>. Do not use your main account password.
            </small>
          </div>

          <div class="app-actions">
            <button type="submit" class="app-btn app-btn-primary">Connect Bluesky</button>
          </div>
        </form>
      <?php endif; ?>
    </section>

  <?php else: ?>
    <div class="app-alert app-alert-error">
      Please log in to edit your profile.
    </div>
  <?php endif; ?>
</section>
