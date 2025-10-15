<?php
/**
 * Mail transport configuration.
 *
 * Local development defaults to MailPit (127.0.0.1:1025) with no auth.
 * Production/staging should set the usual SMTP env vars (host, port, username, password, encryption).
 */
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

$env = static function (string $key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $value !== false && $value !== null && $value !== '' ? $value : $default;
};

$environment = app_config('environment', 'production');
$isLocal = in_array($environment, ['local', 'development'], true);

$host = (string)$env('MAIL_HOST', $isLocal ? '127.0.0.1' : 'smtp.gmail.com');
$port = (int)$env('MAIL_PORT', $isLocal ? 1025 : 587);

$authFlag = $env('MAIL_AUTH', null);
$authEnabled = $authFlag !== null
    ? filter_var((string)$authFlag, FILTER_VALIDATE_BOOLEAN)
    : (bool)$env('MAIL_USERNAME', false);

return [
    'transport' => strtolower((string)$env('MAIL_DRIVER', 'smtp')),
    'host' => $host,
    'port' => $port,
    'auth' => $authEnabled,
    'username' => $env('MAIL_USERNAME'),
    'password' => $env('MAIL_PASSWORD'),
    'encryption' => $env('MAIL_ENCRYPTION', $isLocal ? '' : PHPMailer::ENCRYPTION_STARTTLS),
    'timeout' => (int)$env('MAIL_TIMEOUT', 30),
    'from' => [
        'address' => $env('MAIL_FROM_ADDRESS', 'noreply@' . app_config('app_domain', 'example.com')),
        'name' => $env('MAIL_FROM_NAME', app_config('app_name', 'Elonara Social')),
    ],
    'reply_to' => [
        'address' => $env('MAIL_REPLY_TO_ADDRESS', app_config('support_email', 'support@' . app_config('app_domain', 'example.com'))),
        'name' => $env('MAIL_REPLY_TO_NAME', app_config('app_name', 'Elonara Social') . ' Support'),
    ],
    'debug' => $isLocal ? (int)$env('MAIL_DEBUG', 0) : 0,
];
