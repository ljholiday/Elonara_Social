<?php
declare(strict_types=1);

$env = static function (string $key, $default = null) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $value !== false && $value !== null && $value !== '' ? $value : $default;
};

$environment = app_config('environment', 'production');
$isLocal = in_array($environment, ['local', 'development'], true);

$transport = (string)$env('MAIL_DRIVER', $env('MAIL_TRANSPORT', 'smtp'));
$host = (string)$env('MAIL_HOST', $isLocal ? '127.0.0.1' : 'smtp.gmail.com');
$port = (int)$env('MAIL_PORT', $isLocal ? 1025 : 587);
$auth = $env('MAIL_AUTH', null);
$authEnabled = $auth !== null ? filter_var((string)$auth, FILTER_VALIDATE_BOOLEAN) : (bool)$env('MAIL_USERNAME', false);
$encryption = $env('MAIL_ENCRYPTION', $isLocal ? '' : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS);
$timeout = (int)$env('MAIL_TIMEOUT', 30);

$fromAddress = (string)$env('MAIL_FROM_ADDRESS', $isLocal ? 'no-reply@localhost' : 'noreply@' . app_config('app_domain', 'example.com'));
$fromName = (string)$env('MAIL_FROM_NAME', app_config('app_name', 'Elonara Social'));

$defaultSupport = app_config('support_email', 'support@' . app_config('app_domain', 'example.com'));
$replyToAddress = (string)$env('MAIL_REPLY_TO_ADDRESS', $isLocal ? $defaultSupport : $defaultSupport);
$replyToName = (string)$env('MAIL_REPLY_TO_NAME', app_config('app_name', 'Elonara Social') . ' Support');

return [
    'transport' => strtolower($transport),
    'host' => $host,
    'port' => $port,
    'auth' => $authEnabled,
    'username' => $env('MAIL_USERNAME', null),
    'password' => $env('MAIL_PASSWORD', null),
    'encryption' => $encryption,
    'timeout' => $timeout,
    'from' => [
        'address' => $fromAddress,
        'name' => $fromName,
    ],
    'reply_to' => [
        'address' => $replyToAddress,
        'name' => $replyToName,
    ],
    'sendmail_path' => $env('MAIL_SENDMAIL_PATH', null),
    'debug' => $isLocal ? (int)$env('MAIL_DEBUG', 0) : 0,
];
