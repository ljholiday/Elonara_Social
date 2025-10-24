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

    <!-- Google Analytics -->
    <?php
    $gaTrackingId = (string)app_config('analytics.google_tracking_id', '');
    if ($gaTrackingId !== ''):
    ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaTrackingId, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= htmlspecialchars($gaTrackingId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>');
    </script>
    <?php endif; ?>
