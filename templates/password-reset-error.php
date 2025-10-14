<?php
declare(strict_types=1);

$appName = (string)app_config('app_name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$pageTitle = 'Reset Link Invalid';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle . ' - ' . $appName); ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . '/css/auth.css', ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="app-auth-container">
        <div class="app-auth-card">
            <h1>Reset Link Invalid</h1>
            <div class="app-alert app-alert-error">
                <p><?php echo htmlspecialchars($error ?? 'This password reset link is invalid or has expired.'); ?></p>
            </div>
            <p>Please request a new password reset link.</p>
            <div class="app-auth-actions">
                <a href="/reset-password" class="app-btn app-btn-primary">Request New Link</a>
                <a href="/auth" class="app-link">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
