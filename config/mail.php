<?php
/**
 * Mail transport configuration.
 *
 * Local development defaults to MailPit (127.0.0.1:1025) with no auth.
 * Production/staging should set the usual SMTP env vars (host, port, username, password, encryption).
 */
declare(strict_types=1);

$defaults = [
    'MAIL_DRIVER' => 'smtp',
    'MAIL_HOST' => '127.0.0.1',
    'MAIL_PORT' => '1025',
    'MAIL_AUTH' => 'false',
    'MAIL_USERNAME' => '',
    'MAIL_PASSWORD' => '',
    'MAIL_ENCRYPTION' => '',
    'MAIL_TIMEOUT' => '30',
    'MAIL_FROM_ADDRESS' => app_config('noreply_email'),
    'MAIL_FROM_NAME' => app_config('app_name'),
    'MAIL_REPLY_TO_ADDRESS' => app_config('support_email'),
    'MAIL_REPLY_TO_NAME' => app_config('app_name') . ' Support',
    'MAIL_DEBUG' => '0',
];

$values = [];
foreach ($defaults as $key => $default) {
    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        $values[$key] = $envValue;
    }
}

if (count($values) < count($defaults)) {
    $envPath = dirname(__DIR__) . '/.env';
    if (is_file($envPath)) {
        $parsed = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
        if (is_array($parsed)) {
            foreach ($defaults as $key => $default) {
                if (!array_key_exists($key, $values) && isset($parsed[$key]) && $parsed[$key] !== '') {
                    $values[$key] = $parsed[$key];
                }
            }
        }
    }
}

$settings = $defaults + $values;

$bool = static function ($value): bool {
    if (is_bool($value)) {
        return $value;
    }
    $normalized = strtolower((string)$value);
    return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
};

return [
    'transport' => (string)$settings['MAIL_DRIVER'],
    'host' => (string)$settings['MAIL_HOST'],
    'port' => (int)$settings['MAIL_PORT'],
    'auth' => $bool($settings['MAIL_AUTH']),
    'username' => $settings['MAIL_USERNAME'] !== '' ? (string)$settings['MAIL_USERNAME'] : null,
    'password' => $settings['MAIL_PASSWORD'] !== '' ? (string)$settings['MAIL_PASSWORD'] : null,
    'encryption' => (string)$settings['MAIL_ENCRYPTION'],
    'timeout' => (int)$settings['MAIL_TIMEOUT'],
    'from' => [
        'address' => (string)$settings['MAIL_FROM_ADDRESS'],
        'name' => (string)$settings['MAIL_FROM_NAME'],
    ],
    'reply_to' => [
        'address' => (string)$settings['MAIL_REPLY_TO_ADDRESS'],
        'name' => (string)$settings['MAIL_REPLY_TO_NAME'],
    ],
    'debug' => (int)$settings['MAIL_DEBUG'],
];
