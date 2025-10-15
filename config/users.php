<?php
declare(strict_types=1);

return [
    // Minimum characters required for usernames. Defaults to 2 for shorter handles.
    'username_min_length' => (int)($_ENV['USERNAME_MIN_LENGTH'] ?? 2),
    // Maximum characters allowed for usernames. Defaults to 30.
    'username_max_length' => (int)($_ENV['USERNAME_MAX_LENGTH'] ?? 30),
];
