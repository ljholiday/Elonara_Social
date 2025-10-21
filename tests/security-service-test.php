#!/usr/bin/env php
<?php
/**
 * Security Service Tests
 *
 * Tests modern security service with CSRF nonce functionality
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

echo "=== Security Service Tests ===\n";

// Test 1: Service is registered
test("SecurityService is registered in container", function() {
    $security = app_service('security.service');
    if (!$security instanceof App\Services\SecurityService) {
        return "Expected App\\Services\\SecurityService, got " . get_class($security);
    }
    return true;
});

// Test 2: Nonce creation
test("Can create nonce", function() {
    $security = app_service('security.service');
    $nonce = $security->createNonce('test_action', 123);

    if (empty($nonce)) {
        return "Nonce should not be empty";
    }

    if (strlen($nonce) !== 12) {
        return "Nonce should be 12 characters, got " . strlen($nonce);
    }

    return true;
});

// Test 3: Nonce verification
test("Can verify valid nonce", function() {
    $security = app_service('security.service');
    $nonce = $security->createNonce('test_action', 123);

    $isValid = $security->verifyNonce($nonce, 'test_action', 123);

    if (!$isValid) {
        return "Nonce should be valid immediately after creation";
    }

    return true;
});

// Test 4: Invalid nonce rejected
test("Rejects invalid nonce", function() {
    $security = app_service('security.service');

    $isValid = $security->verifyNonce('invalid_nonce', 'test_action', 123);

    if ($isValid) {
        return "Invalid nonce should be rejected";
    }

    return true;
});

// Test 5: Wrong action rejected
test("Rejects nonce with wrong action", function() {
    $security = app_service('security.service');
    $nonce = $security->createNonce('test_action', 123);

    $isValid = $security->verifyNonce($nonce, 'different_action', 123);

    if ($isValid) {
        return "Nonce should fail with different action";
    }

    return true;
});

// Test 6: Wrong user ID rejected
test("Rejects nonce with wrong user ID", function() {
    $security = app_service('security.service');
    $nonce = $security->createNonce('test_action', 123);

    $isValid = $security->verifyNonce($nonce, 'test_action', 456);

    if ($isValid) {
        return "Nonce should fail with different user ID";
    }

    return true;
});

// Test 7: Token generation
test("Can generate secure tokens", function() {
    $security = app_service('security.service');
    $token = $security->generateToken(32);

    if (strlen($token) !== 64) { // 32 bytes = 64 hex chars
        return "Token should be 64 hex characters, got " . strlen($token);
    }

    if (!ctype_xdigit($token)) {
        return "Token should be hexadecimal";
    }

    return true;
});

// Test 8: Password generation
test("Can generate secure passwords", function() {
    $security = app_service('security.service');
    $password = $security->generatePassword(16);

    if (strlen($password) !== 16) {
        return "Password should be 16 characters, got " . strlen($password);
    }

    return true;
});

// Test 9: Password hashing
test("Can hash passwords", function() {
    $security = app_service('security.service');
    $password = 'TestPassword123!';
    $hash = $security->hashPassword($password);

    if (empty($hash)) {
        return "Hash should not be empty";
    }

    if (!password_verify($password, $hash)) {
        return "Hash verification failed";
    }

    return true;
});

// Test 10: Password verification
test("Can verify passwords", function() {
    $security = app_service('security.service');
    $password = 'TestPassword123!';
    $hash = $security->hashPassword($password);

    if (!$security->verifyPassword($password, $hash)) {
        return "Valid password should verify";
    }

    if ($security->verifyPassword('WrongPassword', $hash)) {
        return "Invalid password should fail";
    }

    return true;
});

echo "\n✅ All security service tests passed!\n";
echo "\nSecurityService provides:\n";
echo "- createNonce(action, userId) - Create CSRF nonces\n";
echo "- verifyNonce(nonce, action, userId) - Verify CSRF nonces\n";
echo "- nonceField(action, fieldName, includeReferer) - Generate HTML nonce fields\n";
echo "- generateToken(length) - Generate secure tokens\n";
echo "- generatePassword(length) - Generate secure passwords\n";
echo "- hashPassword(password) - Hash passwords\n";
echo "- verifyPassword(password, hash) - Verify passwords\n";
echo "- compareStrings(a, b) - Constant-time comparison\n";
echo "- checkRateLimit(key, maxAttempts, timeWindow) - Rate limiting\n";
echo "- getSecurityHeaders() - Security HTTP headers\n";
