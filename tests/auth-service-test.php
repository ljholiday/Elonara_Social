<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$service = app_service('auth.service');
$pdo = app_service('database.connection')->pdo();

$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $username = 'codex_' . $suffix;
    $email = 'codex+' . $suffix . '@example.com';
    $password = 'CodexPass!' . $suffix;

$register = $service->register([
    'display_name' => 'Codex Tester ' . $suffix,
    'username' => $username,
    'email' => $email,
    'password' => $password,
]);

if (!$register['success']) {
    throw new RuntimeException('Registration failed: ' . json_encode($register['errors']));
}

$tokenStmt = $pdo->prepare('SELECT token FROM email_verification_tokens WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
$tokenStmt->execute([':user_id' => (int)$register['user_id']]);
$verificationToken = $tokenStmt->fetchColumn();

if (!is_string($verificationToken) || $verificationToken === '') {
    throw new RuntimeException('Verification token not created for new user.');
}

$verification = $service->verifyEmail($verificationToken);
if (!$verification['success']) {
    throw new RuntimeException('Verification failed: ' . json_encode($verification['errors'] ?? []));
}

$login = $service->attemptLogin($email, $password);
if (!$login['success']) {
    throw new RuntimeException('Login failed after registration.');
}

    if (!$service->isLoggedIn()) {
        throw new RuntimeException('Service did not report logged-in status.');
    }

    $currentUser = $service->getCurrentUser();
    if (!$currentUser || (int)$currentUser->id <= 0) {
        throw new RuntimeException('Current user not available after login.');
    }

    $createdId = (int)$currentUser->id;
    $communityCount = $pdo->prepare('SELECT COUNT(*) FROM communities WHERE creator_id = :creator_id');
    $communityCount->execute([':creator_id' => $createdId]);
    if ((int)$communityCount->fetchColumn() !== 2) {
        throw new RuntimeException('Default communities were not created for new user.');
    }

    $memberCount = $pdo->prepare('SELECT COUNT(*) FROM community_members WHERE user_id = :user_id');
    $memberCount->execute([':user_id' => $createdId]);
    if ((int)$memberCount->fetchColumn() < 2) {
        throw new RuntimeException('Default community memberships missing for new user.');
    }

    $service->logout();

    if ($service->isLoggedIn()) {
        throw new RuntimeException('Service still reports logged in after logout.');
    }

    $duplicate = $service->register([
        'display_name' => 'Duplicate',
        'username' => $username,
        'email' => $email,
        'password' => $password,
    ]);

    if ($duplicate['success'] || empty($duplicate['errors'])) {
        throw new RuntimeException('Duplicate registration succeeded unexpectedly.');
    }

    $invalidLogin = $service->attemptLogin($email, 'incorrect-password');
    if ($invalidLogin['success']) {
        throw new RuntimeException('Login succeeded with incorrect password.');
    }

    echo "AuthService tests passed.\n";
    $pdo->rollBack();
    exit(0);
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
