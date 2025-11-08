<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Centralized logger for Bluesky integration so production
 * diagnostics land in a predictable repository file.
 */
final class BlueskyLogger
{
    private const LOG_FILENAME = 'debug-invite.log';

    public static function log(string $message): void
    {
        $path = self::logPath();
        $line = sprintf('[%s] %s%s', date('c'), $message, PHP_EOL);
        error_log($line, 3, $path);
    }

    private static function logPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . self::LOG_FILENAME;
    }
}
