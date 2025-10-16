<?php
declare(strict_types=1);

namespace App\Support;

use App\Services\CommunityService;
use App\Services\EventService;

final class ContextBuilder
{
    /**
     * @param array<string,mixed> $conversation
     * @return array<int,array{label:string,url:?string}>
     */
    public static function conversation(array $conversation, ?CommunityService $communities = null, ?EventService $events = null): array
    {
        $path = [];

        $communityId = (int)($conversation['community_id'] ?? 0);
        if ($communityId > 0) {
            $communityLabel = $conversation['community_name'] ?? $conversation['community_title'] ?? null;
            $communitySlug = $conversation['community_slug'] ?? null;

            if (($communityLabel === null || $communitySlug === null) && $communities !== null) {
                $community = $communities->getBySlugOrId((string)$communityId);
                if (is_array($community)) {
                    $communityLabel = $community['name'] ?? $community['title'] ?? $communityLabel;
                    $communitySlug = $community['slug'] ?? $communitySlug;
                }
            }

            if ($communityLabel !== null) {
                $path[] = [
                    'label' => (string)$communityLabel,
                    'url' => $communitySlug ? '/communities/' . $communitySlug : null,
                ];
            }
        }

        $eventId = (int)($conversation['event_id'] ?? 0);
        if ($eventId > 0) {
            $eventLabel = $conversation['event_title'] ?? null;
            $eventSlug = $conversation['event_slug'] ?? null;

            if (($eventLabel === null || $eventSlug === null) && $events !== null) {
                $event = $events->getBySlugOrId((string)$eventId);
                if (is_array($event)) {
                    $eventLabel = $event['title'] ?? $eventLabel;
                    $eventSlug = $event['slug'] ?? $eventSlug;
                }
            }

            if ($eventLabel !== null) {
                $path[] = [
                    'label' => (string)$eventLabel,
                    'url' => $eventSlug ? '/events/' . $eventSlug : null,
                ];
            }
        }

        $conversationLabel = $conversation['title'] ?? '';
        if ($conversationLabel !== '') {
            $path[] = [
                'label' => (string)$conversationLabel,
                'url' => null,
            ];
        }

        return $path;
    }

    /**
     * @param array<string,mixed> $event
     * @return array<int,array{label:string,url:?string}>
     */
    public static function event(array $event, ?CommunityService $communities = null): array
    {
        $path = [];

        $communityId = (int)($event['community_id'] ?? 0);
        if ($communityId > 0) {
            $communityLabel = $event['community_name'] ?? $event['community_title'] ?? null;
            $communitySlug = $event['community_slug'] ?? null;

            if (($communityLabel === null || $communitySlug === null) && $communities !== null) {
                $community = $communities->getBySlugOrId((string)$communityId);
                if (is_array($community)) {
                    $communityLabel = $community['name'] ?? $community['title'] ?? $communityLabel;
                    $communitySlug = $community['slug'] ?? $communitySlug;
                }
            }

            if ($communityLabel !== null) {
                $path[] = [
                    'label' => (string)$communityLabel,
                    'url' => $communitySlug ? '/communities/' . $communitySlug : null,
                ];
            }
        }

        $eventLabel = $event['title'] ?? '';
        $eventSlug = $event['slug'] ?? null;
        if ($eventLabel !== '') {
            $path[] = [
                'label' => (string)$eventLabel,
                'url' => $eventSlug ? '/events/' . $eventSlug : null,
            ];
        }

        return $path;
    }
}
