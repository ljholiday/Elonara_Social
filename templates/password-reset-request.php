<?php
declare(strict_types=1);

$appName = (string)app_config('app.name', 'Elonara Social');
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
$pageTitle = 'Reset Password';
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
            <h1>Reset Your Password</h1>

            <?php if (isset($message)): ?>
                <div class="app-alert app-alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <div class="app-auth-actions">
                    <a href="/auth" class="app-btn app-btn-primary">Return to Login</a>
                </div>
            <?php else: ?>
                <p>Enter your email address and we'll send you a link to reset your password.</p>

                <form method="POST" action="/reset-password">
                    <?php if (!empty($errors)): ?>
                        <div class="app-alert app-alert-error">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="app-form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo htmlspecialchars($input['email'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="app-auth-actions">
                        <button type="submit" class="app-btn app-btn-primary">Send Reset Link</button>
                        <a href="/auth" class="app-link">Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
