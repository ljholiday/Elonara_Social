<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Manages pending invitation context across OAuth redirects.
 * Stores only lightweight metadata so controllers can resume acceptance post-auth.
 */
final class PendingInviteSessionStore
{
    private const SESSION_KEY = 'pending_invite_context';

    /**
     * Capture a community invitation context.
     *
     * @param array<string,mixed> $metadata
     */
    public function captureCommunity(string $token, string $redirect, array $metadata = []): void
    {
        if ($token === '') {
            return;
        }

        $this->remember(array_merge($metadata, [
            'invite_token' => $token,
            'channel' => $metadata['channel'] ?? 'community',
            'redirect' => $redirect,
        ]));
    }

    /**
     * Capture an event RSVP invitation context.
     *
     * @param array<string,mixed> $metadata
     */
    public function captureEvent(string $token, string $redirect, array $metadata = []): void
    {
        if ($token === '') {
            return;
        }

        $this->remember(array_merge($metadata, [
            'event_token' => $token,
            'channel' => $metadata['channel'] ?? 'event',
            'redirect' => $redirect,
        ]));
    }

    /**
     * Persist invitation state for later retrieval.
     *
     * @param array<string,mixed> $context
     */
    public function remember(array $context): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'invite_token' => isset($context['invite_token']) ? (string)$context['invite_token'] : null,
            'event_token' => isset($context['event_token']) ? (string)$context['event_token'] : null,
            'channel' => isset($context['channel']) ? (string)$context['channel'] : null,
            'redirect' => isset($context['redirect']) ? (string)$context['redirect'] : null,
            'metadata' => isset($context['metadata']) && is_array($context['metadata'])
                ? $context['metadata']
                : [],
            'captured_at' => time(),
        ];
    }

    /**
     * Retrieve the stored context without clearing it.
     *
     * @return array<string,mixed>|null
     */
    public function peek(): ?array
    {
        $context = $_SESSION[self::SESSION_KEY] ?? null;
        return is_array($context) ? $context : null;
    }

    /**
     * Retrieve and clear the stored context.
     *
     * @return array<string,mixed>|null
     */
    public function pop(): ?array
    {
        $context = $this->peek();
        if ($context !== null) {
            unset($_SESSION[self::SESSION_KEY]);
        }

        return $context;
    }

    public function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}
