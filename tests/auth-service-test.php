<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$service = vt_service('auth.service');
$pdo = vt_service('database.connection')->pdo();

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
