<?php
declare(strict_types=1);

namespace App\Services;

final class DefaultCommunityService
{
    public function __construct(private CommunityService $communities)
    {
    }

    public function createForUser(int $userId, string $displayName, string $email): void
    {
        if ($userId <= 0) {
            return;
        }

        $baseName = trim($displayName);
        if ($baseName === '' && $email !== '') {
            $baseName = strstr($email, '@', true) ?: $email;
        }
        if ($baseName === '') {
            $baseName = 'Member ' . $userId;
        }

        $creatorName = trim($displayName);
        if ($creatorName === '' && $email !== '') {
            $creatorName = $email;
        }
        if ($creatorName === '') {
            $creatorName = $baseName;
        }

        $description = sprintf("%s's community", $baseName);

        $definitions = [
            [
                'name' => $baseName,
                'privacy' => 'public',
                'description' => $description,
            ],
            [
                'name' => $baseName . ' Inner',
                'privacy' => 'private',
                'description' => $description . ' (inner circle)',
            ],
        ];

        foreach ($definitions as $definition) {
            try {
                $this->communities->create([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'privacy' => $definition['privacy'],
                    'creator_id' => $userId,
                    'creator_email' => $email,
                    'creator_display_name' => $creatorName,
                    'creator_role' => 'admin',
                ]);
            } catch (\Throwable $e) {
                $this->logError('Default community creation failed: ' . $e->getMessage());
            }
        }
    }

    private function logError(string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        $line = sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL);
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}
