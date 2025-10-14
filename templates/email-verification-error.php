<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Failed - Elonara Social</title>
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="vt-auth-container">
        <div class="vt-auth-card">
            <h1>Email Verification Failed</h1>
            <div class="vt-alert vt-alert-error">
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>This verification link is invalid or has expired.</p>
                <?php endif; ?>
            </div>
            <div class="vt-auth-actions">
                <a href="/auth" class="vt-btn vt-btn-primary">Go to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
