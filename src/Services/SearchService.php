<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class SearchService
{
    /**
     * @var array<string,array{label:string,badge_class:string}>
     */
    private const TYPE_META = [
        'event' => ['label' => 'Event', 'badge_class' => 'app-badge app-badge-event'],
        'community' => ['label' => 'Community', 'badge_class' => 'app-badge app-badge-community'],
        'conversation' => ['label' => 'Conversation', 'badge_class' => 'app-badge app-badge-conversation'],
        'member' => ['label' => 'Member', 'badge_class' => 'app-badge app-badge-member'],
    ];

    public function __construct(private Database $database)
    {
    }

    /**
     * Run a lightweight search over the indexed content.
     *
     * @return array<int,array<string,string>>
     */
    public function search(string $query, int $limit = 8, ?int $viewerId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $pdo = $this->database->pdo();

        $sql = '
            SELECT title, content, url, entity_type, visibility_scope, owner_user_id
            FROM search
            WHERE (visibility_scope = :public_scope';

        $params = [
            ':public_scope' => 'public',
            ':like_title' => '%' . $query . '%',
            ':like_content' => '%' . $query . '%',
            ':limit' => $limit,
        ];

        if ($viewerId !== null) {
            $sql .= ' OR owner_user_id = :viewer_id';
            $params[':viewer_id'] = $viewerId;
        }

        $sql .= ')
            AND (title LIKE :like_title OR content LIKE :like_content)
            ORDER BY last_activity_at DESC
            LIMIT :limit';

        $stmt = $pdo->prepare($sql);

        foreach ($params as $placeholder => $value) {
            $paramType = PDO::PARAM_STR;
            if ($placeholder === ':limit' || $placeholder === ':viewer_id') {
                $paramType = PDO::PARAM_INT;
            }
            $stmt->bindValue($placeholder, $value, $paramType);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            $type = (string)($row['entity_type'] ?? 'content');
            $meta = self::TYPE_META[$type] ?? [
                'label' => ucfirst($type !== '' ? $type : 'Result'),
                'badge_class' => 'app-badge',
            ];

            $results[] = [
                'title' => (string)$row['title'],
                'url' => (string)$row['url'],
                'entity_type' => $type,
                'badge_label' => $meta['label'],
                'badge_class' => $meta['badge_class'],
                'snippet' => $this->buildSnippet((string)$row['content'], $query),
            ];
        }

        return $results;
    }

    public function indexCommunity(
        int $communityId,
        string $name,
        string $description,
        string $slug,
        int $ownerId,
        string $privacy,
        ?string $activityAt = null
    ): void {
        $this->upsert('community', $communityId, [
            'title' => $name,
            'content' => $description,
            'url' => '/communities/' . ltrim($slug, '/'),
            'owner_user_id' => $ownerId,
            'community_id' => $communityId,
            'event_id' => 0,
            'visibility_scope' => $privacy === 'private' ? 'private' : 'public',
            'last_activity_at' => $activityAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function indexEvent(
        int $eventId,
        string $title,
        string $description,
        string $slug,
        int $ownerId,
        string $privacy,
        ?string $eventDate = null
    ): void {
        $this->upsert('event', $eventId, [
            'title' => $title,
            'content' => $description,
            'url' => '/events/' . ltrim($slug, '/'),
            'owner_user_id' => $ownerId,
            'community_id' => 0,
            'event_id' => $eventId,
            'visibility_scope' => $privacy === 'private' ? 'private' : 'public',
            'last_activity_at' => $eventDate ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function indexConversation(
        int $conversationId,
        string $title,
        string $content,
        string $slug,
        int $ownerId,
        ?int $communityId,
        ?int $eventId,
        string $privacy,
        ?string $activityAt = null
    ): void {
        $this->upsert('conversation', $conversationId, [
            'title' => $title,
            'content' => $content,
            'url' => '/conversations/' . ltrim($slug, '/'),
            'owner_user_id' => $ownerId,
            'community_id' => $communityId ?? 0,
            'event_id' => $eventId ?? 0,
            'visibility_scope' => $privacy === 'private' ? 'private' : 'public',
            'last_activity_at' => $activityAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function remove(string $entityType, int $entityId): void
    {
        $stmt = $this->database->pdo()->prepare(
            'DELETE FROM search WHERE entity_type = :type AND entity_id = :id'
        );
        $stmt->execute([
            ':type' => $entityType,
            ':id' => $entityId,
        ]);
    }

    /**
     * Rebuild the entire search index.
     *
     * @return array{communities:int, events:int, conversations:int}
     */
    public function reindexAll(): array
    {
        $pdo = $this->database->pdo();
        $now = date('Y-m-d H:i:s');

        $counts = [
            'communities' => 0,
            'events' => 0,
            'conversations' => 0,
        ];

        // Communities
        $stmt = $pdo->query("
            SELECT id, name, description, slug, creator_id, privacy, updated_at, created_at
            FROM communities
        ");
        $rows = $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $this->indexCommunity(
                (int)$row['id'],
                (string)$row['name'],
                (string)($row['description'] ?? ''),
                (string)$row['slug'],
                (int)($row['creator_id'] ?? 0),
                (string)($row['privacy'] ?? 'public'),
                (string)($row['updated_at'] ?? $row['created_at'] ?? $now)
            );
            $counts['communities']++;
        }

        // Events
        $stmt = $pdo->query("
            SELECT id, title, description, slug, author_id, privacy, event_date, updated_at
            FROM events
        ");
        $rows = $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $this->indexEvent(
                (int)$row['id'],
                (string)$row['title'],
                (string)($row['description'] ?? ''),
                (string)$row['slug'],
                (int)($row['author_id'] ?? 0),
                (string)($row['privacy'] ?? 'public'),
                (string)($row['event_date'] ?? $row['updated_at'] ?? $now)
            );
            $counts['events']++;
        }

        // Conversations
        $stmt = $pdo->query("
            SELECT id, title, content, slug, author_id, community_id, event_id, privacy, updated_at, created_at
            FROM conversations
        ");
        $rows = $stmt?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $this->indexConversation(
                (int)$row['id'],
                (string)$row['title'],
                (string)($row['content'] ?? ''),
                (string)$row['slug'],
                (int)($row['author_id'] ?? 0),
                $row['community_id'] !== null ? (int)$row['community_id'] : null,
                $row['event_id'] !== null ? (int)$row['event_id'] : null,
                (string)($row['privacy'] ?? 'public'),
                (string)($row['updated_at'] ?? $row['created_at'] ?? $now)
            );
            $counts['conversations']++;
        }

        return $counts;
    }

    private function upsert(string $entityType, int $entityId, array $data): void
    {
        $now = date('Y-m-d H:i:s');
        $pdo = $this->database->pdo();

        $stmt = $pdo->prepare(
            'INSERT INTO search (
                entity_type,
                entity_id,
                title,
                content,
                url,
                owner_user_id,
                community_id,
                event_id,
                visibility_scope,
                last_activity_at,
                created_at,
                updated_at
            ) VALUES (
                :entity_type,
                :entity_id,
                :title,
                :content,
                :url,
                :owner_user_id,
                :community_id,
                :event_id,
                :visibility_scope,
                :last_activity_at,
                :created_at,
                :updated_at
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                content = VALUES(content),
                url = VALUES(url),
                owner_user_id = VALUES(owner_user_id),
                community_id = VALUES(community_id),
                event_id = VALUES(event_id),
                visibility_scope = VALUES(visibility_scope),
                last_activity_at = VALUES(last_activity_at),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':url' => $data['url'],
            ':owner_user_id' => $data['owner_user_id'] ?? 0,
            ':community_id' => $data['community_id'] ?? 0,
            ':event_id' => $data['event_id'] ?? 0,
            ':visibility_scope' => $data['visibility_scope'] ?? 'public',
            ':last_activity_at' => $data['last_activity_at'] ?? $now,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    private function buildSnippet(string $content, string $query): string
    {
        $text = strip_tags($content);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        $lowerText = mb_strtolower($text);
        $lowerQuery = mb_strtolower($query);
        $position = mb_strpos($lowerText, $lowerQuery);

        $start = $position !== false ? max(0, $position - 40) : 0;
        $snippet = mb_substr($text, $start, 120);

        if ($start > 0) {
            $snippet = '…' . $snippet;
        }

        if ($start + 120 < mb_strlen($text)) {
            $snippet .= '…';
        }

        return $snippet;
    }
}
