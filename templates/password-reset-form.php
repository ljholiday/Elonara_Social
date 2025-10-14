<?php
declare(strict_types=1);

$appName = (string)app_config('app_name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$pageTitle = 'Set New Password';
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
            <h1>Set New Password</h1>
            <p>Please enter your new password below.</p>

            <form method="POST" action="/reset-password/<?php echo htmlspecialchars($token); ?>">
                <?php if (!empty($errors)): ?>
                    <div class="app-alert app-alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="app-form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password"
                           minlength="8" required>
                    <small>Minimum 8 characters</small>
                </div>

                <div class="app-form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           minlength="8" required>
                </div>

                <div class="app-auth-actions">
                    <button type="submit" class="app-btn app-btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
