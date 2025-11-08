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

    <div id="profile-success"></div>

    <div id="profile-errors">
      <?php if (!empty($errors)): ?>
        <div class="app-alert app-alert-error app-mb-4">
          <ul>
            <?php foreach ($errors as $message): ?>
              <li><?= e($message) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>

    <form method="post" action="/profile/update" class="app-form app-stack" enctype="multipart/form-data" id="profile-edit-form">
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
        <label class="app-label">Avatar Source</label>
        <div class="app-radio-group app-mb-3">
          <?php $avatarPref = $u->avatar_preference ?? 'auto'; ?>
          <label class="app-radio-label">
            <input
              type="radio"
              name="avatar_preference"
              value="auto"
              <?= $avatarPref === 'auto' ? 'checked' : '' ?>
            >
            <span>Auto (use custom if available, otherwise Gravatar)</span>
          </label>
          <label class="app-radio-label">
            <input
              type="radio"
              name="avatar_preference"
              value="custom"
              <?= $avatarPref === 'custom' ? 'checked' : '' ?>
            >
            <span>Custom avatar only</span>
          </label>
          <label class="app-radio-label">
            <input
              type="radio"
              name="avatar_preference"
              value="gravatar"
              <?= $avatarPref === 'gravatar' ? 'checked' : '' ?>
            >
            <span>Gravatar only</span>
          </label>
        </div>
      </div>

      <div class="app-field">
        <label class="app-label">Avatar Image</label>
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
            <div class="app-avatar app-avatar-lg app-avatar-placeholder" id="avatar-preview">
              <?= strtoupper(substr($u->display_name ?? $u->username ?? 'U', 0, 1)) ?>
            </div>
          <?php endif; ?>
        </div>
        <button type="button" class="app-btn app-btn-primary" onclick="window.appOpenImageLibrary({ imageType: 'profile', targetPreview: 'avatar-preview', targetAltInput: 'avatar-alt', targetUrlInput: 'avatar-url' })">
          Select Image
        </button>
        <input type="hidden" id="avatar-alt" name="avatar_alt" value="<?= e($input['avatar_alt'] ?? '') ?>">
        <input type="hidden" id="avatar-url" name="avatar_url_uploaded" value="">
        <small class="app-help-text" style="display: block; margin-top: 0.5rem;">Click to upload a new image or choose from your library.</small>
        <?php if (isset($errors['avatar'])): ?>
          <div class="app-field-error"><?= e($errors['avatar']) ?></div>
        <?php endif; ?>
        <?php if (isset($errors['avatar_alt'])): ?>
          <div class="app-field-error"><?= e($errors['avatar_alt']) ?></div>
        <?php endif; ?>
      </div>

      <div class="app-field">
        <label class="app-label">Cover Image</label>
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
        <button type="button" class="app-btn app-btn-primary" onclick="window.appOpenImageLibrary({ imageType: 'cover', targetPreview: 'cover-preview', targetAltInput: 'cover-alt', targetUrlInput: 'cover-url' })">
          Select Image
        </button>
        <input type="hidden" id="cover-alt" name="cover_alt" value="<?= e($input['cover_alt'] ?? '') ?>">
        <input type="hidden" id="cover-url" name="cover_url_uploaded" value="">
        <small class="app-help-text" style="display: block; margin-top: 0.5rem;">Click to upload a cover image or choose from your library. Recommended size: 1200x400px.</small>
        <?php if (isset($errors['cover'])): ?>
          <div class="app-field-error"><?= e($errors['cover']) ?></div>
        <?php endif; ?>
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

    $oauthService = null;
    $oauthEnabled = false;
    $oauthStatus = ['connected' => false, 'needs_reauth' => false];

    if (function_exists('app_service') && (bool)app_config('bluesky.oauth.enabled', false)) {
        try {
            $maybeService = app_service('bluesky.oauth.service');
            if ($maybeService && $maybeService->isEnabled()) {
                $oauthService = $maybeService;
                $oauthEnabled = true;
                $oauthStatus = $oauthService->getIdentityStatus((int)($u->id ?? 0));
            }
        } catch (\Throwable $e) {
            $oauthService = null;
            $oauthEnabled = false;
            $oauthStatus = ['connected' => false, 'needs_reauth' => false];
        }
    }
    $profileRedirect = '/profile/edit';
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

      <?php if ($oauthEnabled): ?>
        <div class="app-card app-mb-4">
          <div class="app-card-body app-flex app-flex-column app-gap-3">
            <div class="app-flex app-justify-between app-items-center">
              <div>
                <div class="app-text-muted app-text-sm">OAuth Status</div>
                <?php if ($oauthStatus['connected'] ?? false): ?>
                  <div class="app-text-lg app-font-semibold">@<?= e($oauthStatus['handle'] ?? $credentials['handle'] ?? 'unknown') ?></div>
                  <?php if (!empty($oauthStatus['did'])): ?>
                    <div class="app-text-muted app-text-sm">DID: <?= e(substr((string)$oauthStatus['did'], 0, 24)) ?>...</div>
                  <?php endif; ?>
                  <?php if ($oauthStatus['needs_reauth'] ?? false): ?>
                    <div class="app-alert app-alert-warning app-mt-3">
                      OAuth token expired. Please reauthorize to continue posting and syncing followers.
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <div class="app-text-lg app-font-semibold">Not authorized yet</div>
                  <div class="app-text-muted app-text-sm">Authorize once to unlock verified Bluesky identity.</div>
                <?php endif; ?>
              </div>
              <div>
                <?php if ($oauthStatus['connected'] ?? false): ?>
                  <?php $reauthQuery = http_build_query(['redirect' => $profileRedirect, 'reauthorize' => 1]); ?>
                  <a href="/auth/bluesky/start?<?= htmlspecialchars($reauthQuery, ENT_QUOTES, 'UTF-8'); ?>"
                     class="app-btn app-btn-secondary">Reauthorize via Bluesky</a>
                <?php else: ?>
                  <?php $authQuery = http_build_query(['redirect' => $profileRedirect]); ?>
                  <a href="/auth/bluesky/start?<?= htmlspecialchars($authQuery, ENT_QUOTES, 'UTF-8'); ?>"
                     class="app-btn app-btn-primary">Authorize via Bluesky</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
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

    <hr class="app-divider app-my-6">

    <section class="app-section">
      <h2 class="app-heading app-heading-md app-mb-4">Privacy & Blocking</h2>
      <p class="app-text-muted app-mb-4">
        Manage users you've blocked from interacting with you.
      </p>

      <?php
      // Get blocked users for current user
      $blockService = function_exists('app_service') ? app_service('block.service') : null;
      $blocked_users = $blockService && isset($u->id) ? $blockService->getBlockedUsers((int)$u->id) : [];
      ?>

      <?php include __DIR__ . '/partials/blocked-users-list.php'; ?>
    </section>

  <?php else: ?>
    <div class="app-alert app-alert-error">
      Please log in to edit your profile.
    </div>
  <?php endif; ?>
</section>

<?php if ($u): ?>
  <?php
  // Load profile edit JavaScript
  $assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
  ?>
  <script src="<?= htmlspecialchars($assetBase . '/js/profile-edit.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
