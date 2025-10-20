<?php
declare(strict_types=1);

$success = $success ?? false;
$message = $message ?? '';
$data = $data ?? [];
$redirectUrl = isset($data['redirect_url']) ? (string)$data['redirect_url'] : null;
$connectUrl = isset($data['connect_url']) ? (string)$data['connect_url'] : null;
$requiresBluesky = !empty($data['requires_bluesky']);

$displayMessage = $message !== ''
    ? $message
    : ($success ? 'Invitation accepted successfully.' : 'We were unable to process this invitation.');
?>

<div class="app-section app-max-w-lg app-mx-auto app-text-center">
    <div class="app-card">
        <div class="app-card-body app-p-6">
            <h1 class="app-heading app-heading-lg">
                <?php echo $success ? 'Invitation Accepted' : 'Join Invitation'; ?>
            </h1>

            <p class="app-text-muted app-mt-3">
                <?php echo htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <?php if ($success && $redirectUrl !== null) : ?>
                <div class="app-mt-5">
                    <a class="app-btn app-btn-primary" href="<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        Continue
                    </a>
                </div>
            <?php elseif (!$success && $requiresBluesky && $connectUrl !== null) : ?>
                <div class="app-mt-5">
                    <a class="app-btn app-btn-primary" href="<?php echo htmlspecialchars($connectUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        Connect Bluesky
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$success) : ?>
                <div class="app-mt-4">
                    <a class="app-link" href="/auth">Back to sign in</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
