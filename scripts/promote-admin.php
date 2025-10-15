#!/usr/bin/env php
<?php declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$usage = "Usage: php scripts/promote-admin.php user@example.com [admin|super_admin]\n";

if ($argc < 2) {
    fwrite(STDERR, $usage);
    exit(1);
}

$email = trim($argv[1]);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}

$role = strtolower($argv[2] ?? 'super_admin');
$allowedRoles = ['admin', 'super_admin'];
if (!in_array($role, $allowedRoles, true)) {
    fwrite(STDERR, "Role must be 'admin' or 'super_admin'.\n");
    exit(1);
}

$pdo = app_service('database.connection')->pdo();
$stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false) {
    fwrite(STDERR, "No user found with email {$email}.\n");
    exit(1);
}

if (($user['role'] ?? '') === $role) {
    fwrite(STDOUT, "User {$email} is already {$role}.\n");
    exit(0);
}

$update = $pdo->prepare("UPDATE users SET role = :role, updated_at = NOW() WHERE id = :id LIMIT 1");
$update->execute([
    ':role' => $role,
    ':id' => (int)$user['id'],
]);

if ($update->rowCount() === 1) {
    fwrite(STDOUT, "Promoted {$email} to {$role}.\n");
    exit(0);
}

fwrite(STDERR, "Failed to update role for {$email}.\n");
exit(1);
