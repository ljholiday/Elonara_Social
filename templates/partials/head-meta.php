<?php
/**
 * Head Meta Tags Partial
 *
 * SEO and social media meta tags for inclusion in layout <head> sections.
 *
 * Required variables:
 * @var string $fullTitle - Full page title with app name
 * @var string $page_description - Optional page description
 * @var string $assetBase - Base path for assets
 */

declare(strict_types=1);

// Temporary debug - remove after testing
var_dump('Config test:', app_config('app.url'), app_config('app'));
exit;

$appUrl = rtrim((string)app_config('app.url', 'http://localhost'), '/');
$currentUrl = $appUrl . ($_SERVER['REQUEST_URI'] ?? '/');
?>
    <?php if ($page_description): ?>
    <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?= htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($page_description): ?>
    <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta property="og:image" content="<?= htmlspecialchars($appUrl . $assetBase . '/icons/og-image.png', ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image:width" content="630">
    <meta property="og:image:height" content="630">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($fullTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($page_description): ?>
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($appUrl . $assetBase . '/icons/og-image.png', ENT_QUOTES, 'UTF-8'); ?>">
