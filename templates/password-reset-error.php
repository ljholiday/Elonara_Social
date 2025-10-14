<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Link Invalid - Elonara Social</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="vt-auth-container">
        <div class="vt-auth-card">
            <h1>Reset Link Invalid</h1>
            <div class="vt-alert vt-alert-error">
                <p><?php echo htmlspecialchars($error ?? 'This password reset link is invalid or has expired.'); ?></p>
            </div>
            <p>Please request a new password reset link.</p>
            <div class="vt-auth-actions">
                <a href="/reset-password" class="vt-btn vt-btn-primary">Request New Link</a>
                <a href="/auth" class="vt-link">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
