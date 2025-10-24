<?php
declare(strict_types=1);

$mailConfig = $mailConfig ?? [];
$flash = $flash ?? [];
$analyticsConfig = $analyticsConfig ?? [];
?>

<?php if (!empty($flash)): ?>
  <div class="admin-card" style="border-left:4px solid <?= $flash['type'] === 'success' ? '#16a34a' : '#dc2626'; ?>;">
    <strong><?= htmlspecialchars(ucfirst($flash['type'])); ?>:</strong> <?= htmlspecialchars($flash['message']); ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h3 style="font-size:1.1rem; margin-bottom:1rem;">Analytics</h3>
  <p style="color:#6b748a; margin-bottom:1.5rem;">
    Configure Google Analytics tracking for your site. Enter your tracking ID (e.g., G-XXXXXXXXXX or UA-XXXXXXXXX) to enable analytics.
  </p>

  <form method="post" action="/admin/settings/analytics" style="margin-bottom:2rem;">
    <?php echo app_service('security.service')->nonceField('app_admin', '_admin_nonce', false); ?>

    <div style="margin-bottom:1rem;">
      <label for="ga_tracking_id" style="display:block; font-weight:600; margin-bottom:0.35rem;">Google Analytics Tracking ID</label>
      <input
        type="text"
        id="ga_tracking_id"
        name="ga_tracking_id"
        class="app-input"
        value="<?= htmlspecialchars((string)($analyticsConfig['google_tracking_id'] ?? '')); ?>"
        placeholder="G-XXXXXXXXXX or UA-XXXXXXXXX"
        style="max-width: 400px;">
      <small style="display:block; color:#6b748a; margin-top:0.25rem;">Leave empty to disable tracking</small>
    </div>

    <button type="submit" class="app-btn app-btn-primary">Save Analytics Settings</button>
  </form>
</div>

<div class="admin-card">
  <h3 style="font-size:1.1rem; margin-bottom:1rem;">Mail Transport</h3>
  <p style="color:#6b748a; margin-bottom:1.5rem;">
    These values come from <code>config/config.php</code> in the <code>mail</code> section. Update them in your configuration file. You can send a test email to confirm connectivity.
  </p>

  <div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Host</label>
      <input class="app-input" value="<?= htmlspecialchars((string)($mailConfig['host'] ?? '')); ?>" readonly>
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Port</label>
      <input class="app-input" value="<?= htmlspecialchars((string)($mailConfig['port'] ?? '')); ?>" readonly>
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Auth Required</label>
      <input class="app-input" value="<?= !empty($mailConfig['auth']) ? 'Yes' : 'No'; ?>" readonly>
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Encryption</label>
      <input class="app-input" value="<?= htmlspecialchars((string)($mailConfig['encryption'] ?? 'none')); ?>" readonly>
    </div>
  </div>

  <div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); margin-top:1rem;">
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">From Address</label>
      <input class="app-input" value="<?= htmlspecialchars((string)($mailConfig['from']['address'] ?? '')); ?>" readonly>
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">From Name</label>
      <input class="app-input" value="<?= htmlspecialchars((string)($mailConfig['from']['name'] ?? '')); ?>" readonly>
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Reply-To Address</label>
      <input class="app-input" value="<?= htmlspecialchars((string)($mailConfig['reply_to']['address'] ?? '')); ?>" readonly>
    </div>
    <div>
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Reply-To Name</label>
      <input class="app-input" value="<?= htmlspecialchars((string)($mailConfig['reply_to']['name'] ?? '')); ?>" readonly>
    </div>
  </div>

  <form method="post" action="/admin/settings/test-mail" style="margin-top:2rem;">
    <?php echo app_service('security.service')->nonceField('app_admin', '_admin_nonce', false); ?>
    <button type="submit" class="app-btn app-btn-primary">Send Test Email</button>
  </form>
</div>
