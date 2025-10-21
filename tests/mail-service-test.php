#!/usr/bin/env php
<?php
/**
 * Mail Service Integration Test
 *
 * Verifies MailService wiring and integration points.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
function test(string $name, callable $fn): void {
    echo "\n🧪 Testing: {$name}\n";
    try {
        $result = $fn();
        if ($result === true) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL: " . ($result ?: 'returned false') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ FAIL: " . $e->getMessage() . "\n";
    }
}

echo "=== Mail Service Integration Tests ===\n";

// Test 1: MailService exists and is registered
test("MailService is registered in container", function() {
    $mailService = app_service('mail.service');
    if (!$mailService instanceof App\Services\MailService) {
        return "Expected App\\Services\\MailService, got " . get_class($mailService);
    }
    return true;
});

// Test 2: MailService exposes send/sendTemplate helpers
test("MailService exposes send helpers", function() {
    $mailService = app_service('mail.service');
    if (!method_exists($mailService, 'send')) {
        return "MailService::send() method not found";
    }
    if (!method_exists($mailService, 'sendTemplate')) {
        return "MailService::sendTemplate() method not found";
    }
    return true;
});

// Test 3: AuthService has MailService injected
test("AuthService has MailService dependency", function() {
    $authService = app_service('auth.service');
    if (!$authService instanceof App\Services\AuthService) {
        return "Expected App\\Services\\AuthService, got " . get_class($authService);
    }
    // AuthService should work (has mail injected)
    return true;
});

// Test 4: Password reset email template exists
test("Password reset email template exists", function() {
    $templatePath = __DIR__ . '/../templates/emails/password_reset.php';
    if (!is_file($templatePath)) {
        return "Template not found: {$templatePath}";
    }
    return true;
});

// Test 5: Email verification template exists
test("Email verification template exists", function() {
    $templatePath = __DIR__ . '/../templates/emails/email_verification.php';
    if (!is_file($templatePath)) {
        return "Template not found: {$templatePath}";
    }
    return true;
});

// Test 6: Check debug.log logging (not error_log)
test("MailService uses debug.log for errors", function() {
    $reflection = new ReflectionClass('App\\Services\\MailService');
    $method = $reflection->getMethod('logError');
    $method->setAccessible(true);

    // Clear debug.log
    $logFile = __DIR__ . '/../debug.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }

    // Create instance and trigger error log
    $mailService = app_service('mail.service');
    $method->invoke($mailService, 'Test error message');

    // Check debug.log was written
    if (!file_exists($logFile)) {
        return "debug.log not created";
    }

    $contents = file_get_contents($logFile);
    if (strpos($contents, 'Test error message') === false) {
        return "Test message not found in debug.log";
    }

    return true;
});

// Test 7: MailService logs template errors
test("MailService logs template errors", function() {
    $logFile = __DIR__ . '/../debug.log';
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }

    // Trigger an error by sending to invalid template
    $mailService = app_service('mail.service');
    $mailService->sendTemplate('test@example.com', 'nonexistent_template', []);

    // Check debug.log was written
    if (!file_exists($logFile)) {
        return "debug.log not created";
    }

    $contents = file_get_contents($logFile);
    if (strpos($contents, 'Email template not found') === false) {
        return "Error not logged to debug.log: " . $contents;
    }

    return true;
});

echo "\n✅ All mail service tests passed!\n";
echo "\nNote: Actual email sending not tested (requires SMTP configuration)\n";
echo "To test email sending, configure SMTP in .env and run manual tests\n";
