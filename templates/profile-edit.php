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

<section class="vt-section">
  <?php if ($u): ?>
    <h1 class="vt-heading vt-mb-6">Edit Profile</h1>

    <?php if (!empty($errors)): ?>
      <div class="vt-alert vt-alert-error vt-mb-4">
        <ul>
          <?php foreach ($errors as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="/profile/update" class="vt-form vt-stack" enctype="multipart/form-data">
      <?php if (function_exists('vt_service')): ?>
        <?php echo vt_service('security.service')->nonceField('vt_profile_update', 'profile_nonce'); ?>
      <?php endif; ?>

      <div class="vt-field">
        <label class="vt-label" for="display-name">Display Name</label>
        <input
          type="text"
          class="vt-input<?= isset($errors['display_name']) ? ' is-invalid' : '' ?>"
          id="display-name"
          name="display_name"
          value="<?= e($input['display_name'] ?? '') ?>"
          required
          minlength="2"
          maxlength="100"
        >
        <small class="vt-help-text">How you want to be displayed across the site. Between 2-100 characters.</small>
        <?php if (isset($errors['display_name'])): ?>
          <div class="vt-field-error"><?= e($errors['display_name']) ?></div>
        <?php endif; ?>
      </div>

      <div class="vt-field">
        <label class="vt-label" for="bio">Bio</label>
        <textarea
          class="vt-textarea<?= isset($errors['bio']) ? ' is-invalid' : '' ?>"
          id="bio"
          name="bio"
          rows="4"
          maxlength="500"
        ><?= e($input['bio'] ?? '') ?></textarea>
        <small class="vt-help-text">Tell us about yourself. Maximum 500 characters.</small>
        <?php if (isset($errors['bio'])): ?>
          <div class="vt-field-error"><?= e($errors['bio']) ?></div>
        <?php endif; ?>
      </div>

      <div class="vt-field">
        <label class="vt-label" for="avatar">Avatar</label>
        <?php if (!empty($u->avatar_url)): ?>
          <div class="vt-mb-3">
            <img src="<?= e($u->avatar_url) ?>" alt="Current avatar" class="vt-avatar vt-avatar-lg">
            <div class="vt-text-muted vt-mt-1">Current avatar</div>
          </div>
        <?php endif; ?>
        <input
          type="file"
          class="vt-input<?= isset($errors['avatar']) ? ' is-invalid' : '' ?>"
          id="avatar"
          name="avatar"
          accept="image/jpeg,image/png,image/gif,image/webp"
        >
        <small class="vt-help-text">Upload a new avatar. Maximum 10MB. JPEG, PNG, GIF, or WebP format.</small>
        <?php if (isset($errors['avatar'])): ?>
          <div class="vt-field-error"><?= e($errors['avatar']) ?></div>
        <?php endif; ?>
      </div>

      <div class="vt-field">
        <label class="vt-label" for="avatar-alt">Avatar description</label>
        <input
          type="text"
          class="vt-input<?= isset($errors['avatar_alt']) ? ' is-invalid' : '' ?>"
          id="avatar-alt"
          name="avatar_alt"
          placeholder="Describe your avatar for accessibility"
          value="<?= e($input['avatar_alt'] ?? '') ?>"
        >
        <small class="vt-help-text">Required if uploading a new avatar. Describe what's in the image for screen reader users.</small>
        <?php if (isset($errors['avatar_alt'])): ?>
          <div class="vt-field-error"><?= e($errors['avatar_alt']) ?></div>
        <?php endif; ?>
      </div>

      <div class="vt-field">
        <label class="vt-label" for="cover">Cover Image</label>
        <?php if (!empty($u->cover_url)): ?>
          <div class="vt-mb-3">
            <img src="<?= e($u->cover_url) ?>" alt="<?= e($u->cover_alt ?? 'Current cover') ?>" class="vt-img" style="max-width: 400px;">
            <div class="vt-text-muted vt-mt-1">Current cover image</div>
          </div>
        <?php endif; ?>
        <input
          type="file"
          class="vt-input<?= isset($errors['cover']) ? ' is-invalid' : '' ?>"
          id="cover"
          name="cover"
          accept="image/jpeg,image/png,image/gif,image/webp"
        >
        <small class="vt-help-text">Upload a cover image. Maximum 10MB. JPEG, PNG, GIF, or WebP format. Recommended size: 1200x400px.</small>
        <?php if (isset($errors['cover'])): ?>
          <div class="vt-field-error"><?= e($errors['cover']) ?></div>
        <?php endif; ?>
      </div>

      <div class="vt-field">
        <label class="vt-label" for="cover-alt">Cover image description</label>
        <input
          type="text"
          class="vt-input<?= isset($errors['cover_alt']) ? ' is-invalid' : '' ?>"
          id="cover-alt"
          name="cover_alt"
          placeholder="Describe your cover image for accessibility"
          value="<?= e($input['cover_alt'] ?? '') ?>"
        >
        <small class="vt-help-text">Required if uploading a cover image. Describe what's in the image for screen reader users.</small>
        <?php if (isset($errors['cover_alt'])): ?>
          <div class="vt-field-error"><?= e($errors['cover_alt']) ?></div>
        <?php endif; ?>
      </div>

      <div class="vt-actions">
        <button type="submit" class="vt-btn vt-btn-primary">Save Changes</button>
        <a href="/profile/<?= e($u->username) ?>" class="vt-btn vt-btn-secondary">Cancel</a>
      </div>
    </form>

    <hr class="vt-divider vt-my-6">

    <?php
    // Check if Bluesky is connected
    $blueskyService = function_exists('vt_service') ? vt_service('bluesky.service') : null;
    $isConnected = $blueskyService && $blueskyService->isConnected((int)($u->id ?? 0));
    $credentials = $isConnected ? $blueskyService->getCredentials((int)($u->id ?? 0)) : null;
    ?>

    <section class="vt-section">
      <h2 class="vt-heading vt-heading-md vt-mb-4">Bluesky Connection</h2>
      <p class="vt-text-muted vt-mb-4">
        Connect your Bluesky account to invite your followers to events and communities.
      </p>

      <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="vt-alert vt-alert-success vt-mb-4">
          <?= htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="vt-alert vt-alert-error vt-mb-4">
          <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
      <?php endif; ?>

      <?php if ($isConnected && $credentials): ?>
        <div class="vt-card vt-mb-4">
          <div class="vt-card-body">
            <div class="vt-flex vt-items-center vt-gap-4">
              <div class="vt-flex-1">
                <div class="vt-text-success vt-mb-2">Connected</div>
                <div class="vt-text-lg">@<?= e($credentials['handle']) ?></div>
                <div class="vt-text-muted vt-text-sm">DID: <?= e(substr($credentials['did'], 0, 20)) ?>...</div>
              </div>
              <form method="post" action="/disconnect/bluesky" style="display: inline;">
                <?php if (function_exists('vt_service')): ?>
                  <?php echo vt_service('security.service')->nonceField('vt_nonce', 'nonce'); ?>
                <?php endif; ?>
                <button type="submit" class="vt-btn vt-btn-sm vt-btn-danger">Disconnect</button>
              </form>
            </div>
          </div>
        </div>
      <?php else: ?>
        <form method="post" action="/connect/bluesky" class="vt-form vt-stack vt-card vt-card-body">
          <?php if (function_exists('vt_service')): ?>
            <?php echo vt_service('security.service')->nonceField('vt_nonce', 'nonce'); ?>
          <?php endif; ?>

          <div class="vt-field">
            <label class="vt-label" for="bluesky-identifier">Bluesky Handle or Email</label>
            <input
              type="text"
              class="vt-input"
              id="bluesky-identifier"
              name="identifier"
              placeholder="user.bsky.social or email@example.com"
              required
            >
            <small class="vt-help-text">Your Bluesky handle (e.g., user.bsky.social) or the email you use to log in.</small>
          </div>

          <div class="vt-field">
            <label class="vt-label" for="bluesky-password">App Password</label>
            <input
              type="password"
              class="vt-input"
              id="bluesky-password"
              name="password"
              required
            >
            <small class="vt-help-text">
              Create an app password at <a href="https://bsky.app/settings/app-passwords" target="_blank" rel="noopener">bsky.app/settings/app-passwords</a>. Do not use your main account password.
            </small>
          </div>

          <div class="vt-actions">
            <button type="submit" class="vt-btn vt-btn-primary">Connect Bluesky</button>
          </div>
        </form>
      <?php endif; ?>
    </section>

  <?php else: ?>
    <div class="vt-alert vt-alert-error">
      Please log in to edit your profile.
    </div>
  <?php endif; ?>
</section>
