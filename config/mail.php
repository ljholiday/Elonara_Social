<?php
declare(strict_types=1);

$env = static function (string $key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
};

$environment = app_config('environment', 'production');
$transport = (string)$env('MAIL_DRIVER', $env('MAIL_TRANSPORT', $environment === 'local' ? 'smtp' : 'smtp'));
$host = (string)$env('MAIL_HOST', $environment === 'local' ? '127.0.0.1' : 'smtp.gmail.com');
$port = (int)$env('MAIL_PORT', $environment === 'local' ? 1025 : 587);
$auth = $env('MAIL_AUTH', null);
$authEnabled = $auth !== null ? filter_var($auth, FILTER_VALIDATE_BOOLEAN) : (bool)$env('MAIL_USERNAME', false);
$encryption = $env('MAIL_ENCRYPTION', '');
$timeout = (int)$env('MAIL_TIMEOUT', 30);

$fromAddress = (string)$env('MAIL_FROM_ADDRESS', $environment === 'local' ? 'no-reply@localhost' : 'noreply@' . app_config('app_domain', 'example.com'));
$fromName = (string)$env('MAIL_FROM_NAME', app_config('app_name', 'Elonara Social'));
$replyToAddress = (string)$env('MAIL_REPLY_TO_ADDRESS', $environment === 'local' ? 'support@localhost' : app_config('support_email', 'support@' . app_config('app_domain', 'example.com')));
$replyToName = (string)$env('MAIL_REPLY_TO_NAME', $fromName);

return [
    // Mail transport to use: smtp, sendmail, or mail
    'transport' => strtolower($transport),
    // SMTP settings (ignored unless transport is smtp)
    'host' => $host,
    'port' => $port,
    'auth' => $authEnabled,
    'username' => $env('MAIL_USERNAME', null),
    'password' => $env('MAIL_PASSWORD', null),
    'encryption' => $encryption,
    'timeout' => $timeout,
    // Defaults for the From header
    'from' => [
        'address' => $fromAddress,
        'name' => $fromName,
    ],
    // Reply-to header (optional)
    'reply_to' => [
        'address' => $replyToAddress,
        'name' => $replyToName,
    ],
    // When using sendmail() you can override the path here
    'sendmail_path' => $env('MAIL_SENDMAIL_PATH', null),
    // Toggle SMTP debug level (0 silent, 2 verbose)
    'debug' => $environment === 'local' ? (int)$env('MAIL_DEBUG', 0) : 0,
];
