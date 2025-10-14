<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Elonara Social</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="vt-auth-container">
        <div class="vt-auth-card">
            <h1>Set New Password</h1>
            <p>Please enter your new password below.</p>

            <form method="POST" action="/reset-password/<?php echo htmlspecialchars($token); ?>">
                <?php if (!empty($errors)): ?>
                    <div class="vt-alert vt-alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="vt-form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password"
                           minlength="8" required>
                    <small>Minimum 8 characters</small>
                </div>

                <div class="vt-form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           minlength="8" required>
                </div>

                <div class="vt-auth-actions">
                    <button type="submit" class="vt-btn vt-btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
