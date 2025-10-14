<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Elonara Social</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="vt-auth-container">
        <div class="vt-auth-card">
            <h1>Reset Your Password</h1>

            <?php if (isset($message)): ?>
                <div class="vt-alert vt-alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <div class="vt-auth-actions">
                    <a href="/auth" class="vt-btn vt-btn-primary">Return to Login</a>
                </div>
            <?php else: ?>
                <p>Enter your email address and we'll send you a link to reset your password.</p>

                <form method="POST" action="/reset-password">
                    <?php if (!empty($errors)): ?>
                        <div class="vt-alert vt-alert-error">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo htmlspecialchars($error); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="vt-form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo htmlspecialchars($input['email'] ?? ''); ?>"
                               required>
                    </div>

                    <div class="vt-auth-actions">
                        <button type="submit" class="vt-btn vt-btn-primary">Send Reset Link</button>
                        <a href="/auth" class="vt-link">Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
