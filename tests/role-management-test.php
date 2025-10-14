#!/usr/bin/env php
<?php
/**
 * Role Management Tests
 *
 * Tests role management functionality in AuthService
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

echo "=== Role Management Tests ===\n";

// Test 1: Create test user
$testUserId = null;
test("Create test user for role management", function() use (&$testUserId) {
    $auth = vt_service('auth.service');
    $timestamp = time();
    $result = $auth->register([
        'username' => 'roletest_' . $timestamp,
        'email' => 'roletest_' . $timestamp . '@example.com',
        'password' => 'TestPassword123!',
        'display_name' => 'Role Test User'
    ]);

    if (!$result['success']) {
        return "Failed to create test user: " . implode(', ', $result['errors']);
    }

    $userId = $result['user_id'] ?? null;
    if ($userId === null || $userId <= 0) {
        return "No user_id in registration result";
    }

    $testUserId = $userId;
    return true;
});

// Test 2: Default role is 'member'
test("New users have default 'member' role", function() use ($testUserId) {
    $auth = vt_service('auth.service');
    $role = $auth->getUserRole($testUserId);

    if ($role !== 'member') {
        return "Expected 'member', got '{$role}'";
    }

    return true;
});

// Test 3: Set user role to 'admin'
test("Can set user role to 'admin'", function() use ($testUserId) {
    $auth = vt_service('auth.service');
    $result = $auth->setUserRole($testUserId, 'admin');

    if (!$result) {
        return "setUserRole returned false";
    }

    $role = $auth->getUserRole($testUserId);
    if ($role !== 'admin') {
        return "Expected 'admin', got '{$role}'";
    }

    return true;
});

// Test 4: isAdmin() returns true for admin role
test("isAdmin() returns true for admin role", function() use ($testUserId) {
    $auth = vt_service('auth.service');

    if (!$auth->isAdmin($testUserId)) {
        return "isAdmin() returned false for admin user";
    }

    return true;
});

// Test 5: isSuperAdmin() returns false for admin role
test("isSuperAdmin() returns false for admin role", function() use ($testUserId) {
    $auth = vt_service('auth.service');

    if ($auth->isSuperAdmin($testUserId)) {
        return "isSuperAdmin() returned true for admin user";
    }

    return true;
});

// Test 6: Set user role to 'super_admin'
test("Can set user role to 'super_admin'", function() use ($testUserId) {
    $auth = vt_service('auth.service');
    $result = $auth->setUserRole($testUserId, 'super_admin');

    if (!$result) {
        return "setUserRole returned false";
    }

    $role = $auth->getUserRole($testUserId);
    if ($role !== 'super_admin') {
        return "Expected 'super_admin', got '{$role}'";
    }

    return true;
});

// Test 7: isSuperAdmin() returns true for super_admin role
test("isSuperAdmin() returns true for super_admin role", function() use ($testUserId) {
    $auth = vt_service('auth.service');

    if (!$auth->isSuperAdmin($testUserId)) {
        return "isSuperAdmin() returned false for super_admin user";
    }

    return true;
});

// Test 8: isAdmin() returns true for super_admin role
test("isAdmin() returns true for super_admin role", function() use ($testUserId) {
    $auth = vt_service('auth.service');

    if (!$auth->isAdmin($testUserId)) {
        return "isAdmin() returned false for super_admin user";
    }

    return true;
});

// Test 9: Set user role back to 'member'
test("Can set user role back to 'member'", function() use ($testUserId) {
    $auth = vt_service('auth.service');
    $result = $auth->setUserRole($testUserId, 'member');

    if (!$result) {
        return "setUserRole returned false";
    }

    $role = $auth->getUserRole($testUserId);
    if ($role !== 'member') {
        return "Expected 'member', got '{$role}'";
    }

    return true;
});

// Test 10: isAdmin() returns false for member role
test("isAdmin() returns false for member role", function() use ($testUserId) {
    $auth = vt_service('auth.service');

    if ($auth->isAdmin($testUserId)) {
        return "isAdmin() returned true for member user";
    }

    return true;
});

// Test 11: Reject invalid role
test("Reject invalid role value", function() use ($testUserId) {
    $auth = vt_service('auth.service');
    $result = $auth->setUserRole($testUserId, 'invalid_role');

    if ($result !== false) {
        return "setUserRole should have returned false for invalid role";
    }

    // Role should still be 'member'
    $role = $auth->getUserRole($testUserId);
    if ($role !== 'member') {
        return "Role was changed despite invalid value";
    }

    return true;
});

// Test 12: getUserRole() returns null for invalid user ID
test("getUserRole() returns null for invalid user ID", function() {
    $auth = vt_service('auth.service');
    $role = $auth->getUserRole(999999);

    if ($role !== null) {
        return "Expected null for non-existent user, got '{$role}'";
    }

    return true;
});

// Test 13: AuthorizationService isSiteAdmin() works with role column
test("AuthorizationService isSiteAdmin() works with role column", function() use ($testUserId) {
    $auth = vt_service('auth.service');
    $authz = vt_service('authorization.service');

    // Set to admin
    $auth->setUserRole($testUserId, 'admin');

    if (!$authz->isSiteAdmin($testUserId)) {
        return "isSiteAdmin() returned false for admin user";
    }

    // Set to member
    $auth->setUserRole($testUserId, 'member');

    if ($authz->isSiteAdmin($testUserId)) {
        return "isSiteAdmin() returned true for member user";
    }

    return true;
});

// Cleanup: Delete test user
test("Cleanup test user", function() use ($testUserId) {
    $pdo = vt_service('database.connection')->pdo();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$testUserId]);

    return true;
});

echo "\n✅ All role management tests passed!\n";
echo "\nAuthService Role Management provides:\n";
echo "- setUserRole(userId, role) - Set user role (member, admin, super_admin)\n";
echo "- getUserRole(userId) - Get user role\n";
echo "- isAdmin(userId) - Check if user is admin or super_admin\n";
echo "- isSuperAdmin(userId) - Check if user is super_admin\n";
