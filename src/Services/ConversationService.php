<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class ConversationService
{
    public function __construct(
        private Database $db,
        private ?ImageService $imageService = null,
        private ?EmbedService $embedService = null
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit = 20): array
    {
        $sql = "SELECT
                    id,
                    title,
                    slug,
                    content,
                    author_name,
                    created_at,
                    reply_count,
                    last_reply_date
                FROM vt_conversations
                ORDER BY COALESCE(updated_at, created_at) DESC
                LIMIT :lim";

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBySlugOrId(string $slugOrId): ?array
    {
        $pdo = $this->db->pdo();

        if (ctype_digit($slugOrId)) {
            $stmt = $pdo->prepare(
                "SELECT conv.id, conv.title, conv.slug, conv.content, conv.author_id, conv.author_name, conv.created_at, conv.reply_count, conv.last_reply_date, conv.community_id, conv.privacy, com.privacy AS community_privacy,
                        u.username AS author_username, u.display_name AS author_display_name, u.email AS author_email, u.avatar_url AS author_avatar_url
                 FROM vt_conversations conv
                 LEFT JOIN vt_communities com ON conv.community_id = com.id
                 LEFT JOIN vt_users u ON conv.author_id = u.id
                 WHERE conv.id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => (int)$slugOrId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT conv.id, conv.title, conv.slug, conv.content, conv.author_id, conv.author_name, conv.created_at, conv.reply_count, conv.last_reply_date, conv.community_id, conv.privacy, com.privacy AS community_privacy,
                        u.username AS author_username, u.display_name AS author_display_name, u.email AS author_email, u.avatar_url AS author_avatar_url
                 FROM vt_conversations conv
                 LEFT JOIN vt_communities com ON conv.community_id = com.id
                 LEFT JOIN vt_users u ON conv.author_id = u.id
                 WHERE conv.slug = :slug
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slugOrId]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @param array{title:string,content:string} $data
     */
    public function create(array $data): string
    {
        $title = trim($data['title']);
        if ($title === '') {
            throw new \RuntimeException('Title is required.');
        }

        $content = trim($data['content']);
        if ($content === '') {
            throw new \RuntimeException('Content is required.');
        }

        $pdo = $this->db->pdo();
        $slug = $this->ensureUniqueSlug($pdo, $this->slugify($title));
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "INSERT INTO vt_conversations (
                title,
                slug,
                content,
                author_id,
                author_name,
                author_email,
                created_at,
                updated_at,
                reply_count,
                last_reply_date,
                privacy
            ) VALUES (
                :title,
                :slug,
                :content,
                :author_id,
                :author_name,
                :author_email,
                :created_at,
                :updated_at,
                :reply_count,
                :last_reply_date,
                :privacy
            )"
        );

        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':content' => $content,
            ':author_id' => 1,
            ':author_name' => 'Demo Author',
            ':author_email' => 'demo@example.com',
            ':created_at' => $now,
            ':updated_at' => $now,
            ':reply_count' => 0,
            ':last_reply_date' => $now,
            ':privacy' => 'public',
        ]);

        return $slug;
    }

    /**
     * @return array{conversations: array<int, array<string, mixed>>, pagination: array{page:int, per_page:int, has_more:bool, next_page:int|null}}
     */
    public function listByCircle(int $viewerId, string $circle, ?array $allowedCommunities, array $memberCommunities, array $options = []): array
    {
        $options = array_merge(['page' => 1, 'per_page' => 20, 'filter' => '', 'viewer_email' => null], $options);
        $page = max(1, (int)$options['page']);
        $perPage = max(1, (int)$options['per_page']);
        $offset = ($page - 1) * $perPage;
        $fetchLimit = $perPage + 1;
        $filter = strtolower((string)$options['filter']);
        $viewerEmail = is_string($options['viewer_email']) ? trim($options['viewer_email']) : null;

        $allowedCommunities = $allowedCommunities === null ? null : $this->uniqueInts($allowedCommunities);
        $memberCommunities = $this->uniqueInts($memberCommunities);

        if ($allowedCommunities !== null && $allowedCommunities === []) {
            return [
                'conversations' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more' => false,
                    'next_page' => null,
                ],
            ];
        }

        $conditions = [];
        $params = [];

        if ($allowedCommunities === null) {
            $privacyParts = ["com.privacy = 'public'"];
            if ($memberCommunities !== []) {
                $privacyParts[] = 'conv.community_id IN (' . $this->placeholderList(count($memberCommunities)) . ')';
                $params = array_merge($params, $memberCommunities);
            }
            $conditions[] = '(' . implode(' OR ', $privacyParts) . ')';
        } else {
            $conditions[] = 'conv.community_id IN (' . $this->placeholderList(count($allowedCommunities)) . ')';
            $params = array_merge($params, $allowedCommunities);

            $privacyParts = ["com.privacy = 'public'"];
            if ($memberCommunities !== []) {
                $privacyParts[] = 'conv.community_id IN (' . $this->placeholderList(count($memberCommunities)) . ')';
                $params = array_merge($params, $memberCommunities);
            }
            $conditions[] = '(' . implode(' OR ', $privacyParts) . ')';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        if ($filter === 'my-events') {
            $eventIds = $this->lookupViewerEventIds($viewerId, $viewerEmail);
            if ($eventIds === []) {
                return [
                    'conversations' => [],
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'has_more' => false,
                        'next_page' => null,
                        'total' => 0,
                    ],
                ];
            }
            $where .= ($where ? ' AND ' : 'WHERE ') . 'conv.event_id IN (' . $this->placeholderList(count($eventIds)) . ')';
            $params = array_merge($params, $eventIds);
        } elseif ($filter === 'all-events') {
            $where .= ($where ? ' AND ' : 'WHERE ') . 'conv.event_id IS NOT NULL';
        } elseif ($filter === 'communities') {
            $where .= ($where ? ' AND ' : 'WHERE ') . 'conv.community_id IS NOT NULL';
        }

        $sql = "SELECT
                conv.id,
                conv.title,
                conv.slug,
                conv.content,
                conv.author_name,
                conv.created_at,
                conv.reply_count,
                conv.last_reply_date,
                conv.community_id,
                conv.event_id,
                com.name AS community_name,
                com.slug AS community_slug,
                com.privacy AS community_privacy
            FROM vt_conversations conv
            LEFT JOIN vt_communities com ON conv.community_id = com.id
            $where
            ORDER BY COALESCE(conv.updated_at, conv.created_at) DESC
            LIMIT $fetchLimit OFFSET $offset";

        $countSql = "SELECT COUNT(*)
            FROM vt_conversations conv
            LEFT JOIN vt_communities com ON conv.community_id = com.id
            $where";

        $countStmt = $this->db->pdo()->prepare($countSql);
        foreach ($params as $index => $value) {
            $countStmt->bindValue($index + 1, (int)$value, PDO::PARAM_INT);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, (int)$value, PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $perPage);
        }

        return [
            'conversations' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_page' => $hasMore ? $page + 1 : null,
                'total' => $total,
            ],
        ];
    }

    /**
     * @param array<int|string> $values
     * @return array<int>
     */
    private function uniqueInts(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $ints = array_map(static fn($value) => (int)$value, $values);
        $ints = array_values(array_unique($ints));
        sort($ints);

        return $ints;
    }

    private function placeholderList(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
    }

    /**
     * @return array<int>
     */
    private function lookupViewerEventIds(int $viewerId, ?string $viewerEmail): array
    {
        if ($viewerId <= 0) {
            return [];
        }

        $email = $viewerEmail !== null && $viewerEmail !== ''
            ? $viewerEmail
            : $this->lookupUserEmail($viewerId);

        $stmt = $this->db->pdo()->prepare(
            "SELECT DISTINCT e.id
             FROM vt_events e
             LEFT JOIN vt_guests g ON g.event_id = e.id
             WHERE e.event_status = 'active'
               AND e.status = 'active'
               AND (
                    e.author_id = :viewer
                    OR g.converted_user_id = :viewer
                    OR g.email = :viewer_email
               )"
        );
        $stmt->bindValue(':viewer', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_email', $email ?? '', PDO::PARAM_STR);
        $stmt->execute();

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $ids !== false ? array_map('intval', $ids) : [];
    }

    private function lookupUserEmail(int $viewerId): ?string
    {
        $stmt = $this->db->pdo()->prepare('SELECT email FROM vt_users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $viewerId, PDO::PARAM_INT);
        $stmt->execute();

        $email = $stmt->fetchColumn();
        return is_string($email) ? trim($email) : null;
    }

    public function canViewerAccess(array $conversation, int $viewerId, array $memberCommunities): bool
    {
        if (empty($conversation)) {
            return false;
        }

        $conversationPrivacy = $conversation['privacy'] ?? 'public';
        $communityId = isset($conversation['community_id']) ? (int)$conversation['community_id'] : 0;
        $communityPrivacy = $conversation['community_privacy'] ?? null;

        if ($communityId === 0) {
            if ($conversationPrivacy === 'public') {
                return true;
            }

            return $viewerId > 0 && isset($conversation['author_id']) && (int)$conversation['author_id'] === $viewerId;
        }

        if ($communityPrivacy === null) {
            $communityPrivacy = $this->lookupCommunityPrivacy($communityId);
        }

        if ($communityPrivacy === 'public') {
            return true;
        }

        return in_array($communityId, $memberCommunities, true);
    }

    private function lookupCommunityPrivacy(int $communityId): ?string
    {
        $stmt = $this->db->pdo()->prepare('SELECT privacy FROM vt_communities WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $communityId, PDO::PARAM_INT);
        $stmt->execute();

        $privacy = $stmt->fetchColumn();
        return is_string($privacy) ? $privacy : null;
    }

    /**
     * @param array{title:string,content:string} $data
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listReplies(int $conversationId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT r.id, r.conversation_id, r.parent_reply_id, r.content, r.image_url, r.image_alt, r.author_name, r.created_at, r.depth_level,
                    u.id AS author_id, u.username AS author_username, u.display_name AS author_display_name, u.email AS author_email, u.avatar_url AS author_avatar_url
             FROM vt_conversation_replies r
             LEFT JOIN vt_users u ON r.author_id = u.id
             WHERE r.conversation_id = :cid
             ORDER BY r.created_at ASC'
        );
        $stmt->execute([':cid' => $conversationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array{
     *   content:string,
     *   author_id?:int,
     *   author_name?:string,
     *   author_email?:string,
     *   image?:array,
     *   image_alt?:string
     * } $data
     */
    public function addReply(int $conversationId, array $data): int
    {
        $conversation = $this->getBySlugOrId((string)$conversationId);
        if ($conversation === null) {
            throw new \RuntimeException('Conversation not found.');
        }

        $content = trim((string)($data['content'] ?? ''));
        if ($content === '') {
            throw new \RuntimeException('Reply content is required.');
        }

        $authorId = isset($data['author_id']) ? (int)$data['author_id'] : 0;
        $authorName = trim((string)($data['author_name'] ?? ''));
        $authorEmail = trim((string)($data['author_email'] ?? ''));

        if ($authorName === '') {
            $authorName = 'Anonymous';
        }

        // Handle image upload
        $imageUrl = null;
        $imageAlt = null;
        if ($this->imageService && !empty($data['image']) && !empty($data['image']['tmp_name'])) {
            $imageAlt = trim((string)($data['image_alt'] ?? ''));
            if ($imageAlt === '') {
                throw new \RuntimeException('Image alt-text is required for accessibility.');
            }

            $uploadResult = $this->imageService->upload(
                $data['image'],
                $imageAlt,
                'post',
                'conversation',
                $conversationId
            );

            if (!$uploadResult['success']) {
                throw new \RuntimeException($uploadResult['error'] ?? 'Failed to upload image.');
            }

            $imageUrl = $uploadResult['url'];
        }

        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO vt_conversation_replies (conversation_id, parent_reply_id, content, image_url, image_alt, author_id, author_name, author_email, depth_level, created_at, updated_at)
             VALUES (:conversation_id, :parent_reply_id, :content, :image_url, :image_alt, :author_id, :author_name, :author_email, :depth_level, :created_at, :updated_at)'
        );

        $stmt->execute([
            ':conversation_id' => (int)$conversation['id'],
            ':parent_reply_id' => null,
            ':content' => $content,
            ':image_url' => $imageUrl,
            ':image_alt' => $imageAlt,
            ':author_id' => $authorId,
            ':author_name' => $authorName,
            ':author_email' => $authorEmail,
            ':depth_level' => 0,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Process content to add embeds
     */
    public function processContentEmbeds(string $content): string
    {
        if (!$this->embedService || empty($content)) {
            return $content;
        }

        return $this->embedService->processContent($content);
    }

    public function update(string $slugOrId, array $data): string
    {
        $conversation = $this->getBySlugOrId($slugOrId);
        if ($conversation === null) {
            throw new \RuntimeException('Conversation not found.');
        }

        $title = trim($data['title']);
        if ($title === '') {
            throw new \RuntimeException('Title is required.');
        }

        $content = trim($data['content']);
        if ($content === '') {
            throw new \RuntimeException('Content is required.');
        }

        $slug = (string)($conversation['slug'] ?? $slugOrId);
        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "UPDATE vt_conversations
             SET title = :title,
                 content = :content,
                 updated_at = :updated_at
             WHERE slug = :slug
             LIMIT 1"
        );

        $stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':updated_at' => $now,
            ':slug' => $slug,
        ]);

        return $slug;
    }

    public function delete(string $slugOrId): bool
    {
        $conversation = $this->getBySlugOrId($slugOrId);
        if ($conversation === null) {
            return false;
        }

        $slug = (string)($conversation['slug'] ?? $slugOrId);
        $pdo = $this->db->pdo();

        $stmt = $pdo->prepare('DELETE FROM vt_conversations WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        return $stmt->rowCount() === 1;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function listByEvent(int $eventId, int $limit = 50): array
    {
        if ($eventId <= 0) {
            return [];
        }

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('
            SELECT c.id, c.title, c.slug, c.content, c.author_id, c.event_id, c.community_id,
                   c.created_at, c.reply_count, c.last_reply_date,
                   u.username AS author_name
            FROM vt_conversations c
            LEFT JOIN vt_users u ON c.author_id = u.id
            WHERE c.event_id = :event_id
            ORDER BY c.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':event_id', $eventId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function listByCommunity(int $communityId, int $limit = 50): array
    {
        if ($communityId <= 0) {
            return [];
        }

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('
            SELECT c.id, c.title, c.slug, c.content, c.author_id, c.event_id, c.community_id,
                   c.created_at, c.reply_count, c.last_reply_date,
                   u.username AS author_name
            FROM vt_conversations c
            LEFT JOIN vt_users u ON c.author_id = u.id
            WHERE c.community_id = :community_id
            ORDER BY c.created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':community_id', $communityId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function slugify(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'conversation';
    }

    private function ensureUniqueSlug(PDO $pdo, string $slug): string
    {
        $base = $slug;
        $i = 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vt_conversations WHERE slug = :slug');

        while (true) {
            $stmt->execute([':slug' => $slug]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . ++$i;
        }
    }

    /**
     * Get a single reply by ID
     */
    public function getReply(int $replyId): ?array
    {
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('SELECT * FROM vt_conversation_replies WHERE id = :id');
        $stmt->execute([':id' => $replyId]);
        $reply = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $reply !== false ? $reply : null;
    }

    /**
     * Update a reply
     */
    public function updateReply(int $replyId, array $data): bool
    {
        $reply = $this->getReply($replyId);
        if ($reply === null) {
            throw new \RuntimeException('Reply not found.');
        }

        $content = trim((string)($data['content'] ?? ''));
        if ($content === '') {
            throw new \RuntimeException('Reply content is required.');
        }

        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'UPDATE vt_conversation_replies
             SET content = :content, updated_at = :updated_at
             WHERE id = :id'
        );

        return $stmt->execute([
            ':content' => $content,
            ':updated_at' => $now,
            ':id' => $replyId
        ]);
    }

    /**
     * Delete a reply
     */
    public function deleteReply(int $replyId): bool
    {
        $reply = $this->getReply($replyId);
        if ($reply === null) {
            throw new \RuntimeException('Reply not found.');
        }

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('DELETE FROM vt_conversation_replies WHERE id = :id');
        return $stmt->execute([':id' => $replyId]);
    }
}
