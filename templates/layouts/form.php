<?php
/**
 * Form Layout - Centered single column with header and form wrapper
 * Used for: Create and edit forms
 *
 * Expected variables:
 * @var string $page_title - Page title
 * @var string $page_description - Optional page description
 * @var string $content - Form content HTML
 * @var string $current_path - Current request path
 */

declare(strict_types=1);

$page_title = $page_title ?? 'Elonara Social';
$page_description = $page_description ?? '';
$current_path = $current_path ?? $_SERVER['REQUEST_URI'] ?? '/';
$content = $content ?? '';

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
<body>

<div class="vt-page-form-centered">
    <div class="vt-main-nav">
        <a href="/events" class="vt-main-nav-item<?= (str_contains($current_path, '/events') || str_contains($current_path, '/create-event')) ? ' active' : ''; ?>">
            Events
        </a>
        <a href="/conversations" class="vt-main-nav-item<?= (str_contains($current_path, '/conversations') || str_contains($current_path, '/create-conversation')) ? ' active' : ''; ?>">
            Conversations
        </a>
        <a href="/communities" class="vt-main-nav-item<?= (str_contains($current_path, '/communities') || str_contains($current_path, '/create-community')) ? ' active' : ''; ?>">
            Communities
        </a>
    </div>

    <div class="vt-header">
        <h1 class="vt-heading vt-heading-lg vt-text-primary"><?= htmlspecialchars($page_title); ?></h1>
        <?php if ($page_description): ?>
            <p class="vt-text-muted"><?= htmlspecialchars($page_description); ?></p>
        <?php endif; ?>
    </div>

    <div class="vt-section">
        <?= $content; ?>
    </div>
</div>

<script src="/assets/js/modal.js"></script>
<script src="/assets/js/social_elonara.js"></script>
</body>
</html>
