<?php
/**
 * Mail transport configuration.
 *
 * Local development defaults to MailPit (127.0.0.1:1025) with no auth.
 * Production/staging should set the usual SMTP env vars (host, port, username, password, encryption).
 */
declare(strict_types=1);

return [
    'transport' => getenv('MAIL_DRIVER') ?: 'smtp',
    'host' => getenv('MAIL_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('MAIL_PORT') ?: 1025),
    'auth' => filter_var(getenv('MAIL_AUTH'), FILTER_VALIDATE_BOOLEAN),
    'username' => getenv('MAIL_USERNAME') ?: null,
    'password' => getenv('MAIL_PASSWORD') ?: null,
    'encryption' => getenv('MAIL_ENCRYPTION') ?: '',
    'timeout' => (int)(getenv('MAIL_TIMEOUT') ?: 30),
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: app_config('noreply_email'),
        'name' => getenv('MAIL_FROM_NAME') ?: app_config('app_name'),
    ],
    'reply_to' => [
        'address' => getenv('MAIL_REPLY_TO_ADDRESS') ?: app_config('support_email'),
        'name' => getenv('MAIL_REPLY_TO_NAME') ?: app_config('app_name') . ' Support',
    ],
    'debug' => (int)(getenv('MAIL_DEBUG') ?: 0),
];
