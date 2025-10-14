<?php
declare(strict_types=1);

$appName = (string)app_config('app_name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$pageTitle = 'Verification Failed';
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
            <h1>Email Verification Failed</h1>
            <div class="app-alert app-alert-error">
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>This verification link is invalid or has expired.</p>
                <?php endif; ?>
            </div>
            <div class="app-auth-actions">
                <a href="/auth" class="app-btn app-btn-primary">Go to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
