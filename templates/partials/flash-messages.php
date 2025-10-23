<?php
/**
 * Flash Messages Partial
 *
 * Displays session flash messages for success/error/info feedback.
 * Auto-clears messages after displaying.
 *
 * Usage: Include at top of main content area in layouts
 */

declare(strict_types=1);

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
$flashInfo = $_SESSION['flash_info'] ?? null;

// Clear flash messages after reading
if ($flashSuccess) unset($_SESSION['flash_success']);
if ($flashError) unset($_SESSION['flash_error']);
if ($flashInfo) unset($_SESSION['flash_info']);
?>

<?php if ($flashSuccess): ?>
<div class="app-alert app-alert-success" role="alert">
    <?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if ($flashError): ?>
<div class="app-alert app-alert-error" role="alert">
    <?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if ($flashInfo): ?>
<div class="app-alert app-alert-info" role="alert">
    <?= htmlspecialchars($flashInfo, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
