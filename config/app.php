<?php
declare(strict_types=1);

return [
    'app_name' => $_ENV['APP_NAME'] ?? 'Elonara Social',
    'app_domain' => $_ENV['APP_DOMAIN'] ?? 'social.elonara.com',
    'app_url' => $_ENV['APP_URL'] ?? 'https://social.elonara.com',
    'asset_url' => $_ENV['ASSET_URL'] ?? '/assets',
    'support_email' => $_ENV['SUPPORT_EMAIL'] ?? 'support@social.elonara.com',
    'noreply_email' => $_ENV['NOREPLY_EMAIL'] ?? 'noreply@social.elonara.com',
];
