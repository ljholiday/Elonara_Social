<?php
/**
 * Guest Layout Template
 *
 * Provides the base HTML structure for unauthenticated pages (login, register, password reset).
 *
 * Expected variables:
 * @var string $page_title - Page title
 * @var string $content - Main content HTML
 */

declare(strict_types=1);

$page_title = $page_title ?? 'Elonara Social';
$content = $content ?? '';

// Generate CSRF token for meta tag
$security = vt_service('security.service');
$csrf_token = $security->createNonce('vt_nonce');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?= htmlspecialchars($page_title); ?> - Elonara Social</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="vt-body vt-guest">

<div class="vt-layout-guest">
    <main class="vt-main" role="main">
        <?= $content; ?>
    </main>
</div>

<script src="/assets/js/social_elonara.js"></script>
</body>
</html>
