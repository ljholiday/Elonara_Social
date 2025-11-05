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
        private ?EmbedService $embedService = null,
        private ?SearchService $search = null,
        private ?CircleService $circles = null
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit = 20): array
    {
        $sql = "SELECT
                    conv.id,
                    conv.title,
                    conv.slug,
                    conv.content,
                    conv.author_name,
                    conv.created_at,
                    COALESCE(replies.reply_total, conv.reply_count) AS reply_count,
                    conv.last_reply_date,
                    conv.privacy,
                    conv.community_id,
                    conv.event_id,
                    com.name AS community_name,
                    com.slug AS community_slug,
                    evt.title AS event_title,
                    evt.slug AS event_slug
                FROM conversations conv
                LEFT JOIN (
                    SELECT conversation_id, COUNT(*) AS reply_total
                    FROM conversation_replies
                    GROUP BY conversation_id
                ) replies ON replies.conversation_id = conv.id
                LEFT JOIN communities com ON conv.community_id = com.id
                LEFT JOIN events evt ON conv.event_id = evt.id
                ORDER BY COALESCE(conv.updated_at, conv.created_at) DESC
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
                "SELECT conv.id, conv.title, conv.slug, conv.content, conv.author_id, conv.author_name, conv.created_at,
                        COALESCE(replies.reply_total, conv.reply_count) AS reply_count,
                        conv.last_reply_date, conv.community_id, conv.event_id, conv.privacy, com.privacy AS community_privacy,
                        u.username AS author_username, u.display_name AS author_display_name, u.email AS author_email, u.avatar_url AS author_avatar_url,
                        com.name AS community_name, com.slug AS community_slug,
                        evt.title AS event_title, evt.slug AS event_slug
                 FROM conversations conv
                 LEFT JOIN (
                     SELECT conversation_id, COUNT(*) AS reply_total
                     FROM conversation_replies
                     GROUP BY conversation_id
                 ) replies ON replies.conversation_id = conv.id
                 LEFT JOIN communities com ON conv.community_id = com.id
                 LEFT JOIN events evt ON conv.event_id = evt.id
                 LEFT JOIN users u ON conv.author_id = u.id
                 WHERE conv.id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => (int)$slugOrId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT conv.id, conv.title, conv.slug, conv.content, conv.author_id, conv.author_name, conv.created_at,
                        COALESCE(replies.reply_total, conv.reply_count) AS reply_count,
                        conv.last_reply_date, conv.community_id, conv.event_id, conv.privacy, com.privacy AS community_privacy,
                        u.username AS author_username, u.display_name AS author_display_name, u.email AS author_email, u.avatar_url AS author_avatar_url,
                        com.name AS community_name, com.slug AS community_slug,
                        evt.title AS event_title, evt.slug AS event_slug
                 FROM conversations conv
                 LEFT JOIN (
                     SELECT conversation_id, COUNT(*) AS reply_total
                     FROM conversation_replies
                     GROUP BY conversation_id
                 ) replies ON replies.conversation_id = conv.id
                 LEFT JOIN communities com ON conv.community_id = com.id
                 LEFT JOIN events evt ON conv.event_id = evt.id
                 LEFT JOIN users u ON conv.author_id = u.id
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

        $authorId = isset($data['author_id']) ? (int)$data['author_id'] : 0;
        $authorName = trim((string)($data['author_name'] ?? ''));
        $authorEmail = trim((string)($data['author_email'] ?? ''));
        if ($authorName === '') {
            $authorName = 'Anonymous';
        }

        $privacy = (string)($data['privacy'] ?? 'public');
        if (!in_array($privacy, ['public', 'private'], true)) {
            $privacy = 'public';
        }

        $pdo = $this->db->pdo();
        $slug = $this->ensureUniqueSlug($pdo, $this->slugify($title));
        $now = date('Y-m-d H:i:s');
        $communityId = isset($data['community_id']) ? (int)$data['community_id'] : 0;
        $eventId = isset($data['event_id']) ? (int)$data['event_id'] : 0;

        $stmt = $pdo->prepare(
            "INSERT INTO conversations (
                title,
                slug,
                content,
                author_id,
                author_name,
                author_email,
                community_id,
                event_id,
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
                :community_id,
                :event_id,
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
            ':author_id' => $authorId,
            ':author_name' => $authorName,
            ':author_email' => $authorEmail,
            ':community_id' => $communityId > 0 ? $communityId : null,
            ':event_id' => $eventId > 0 ? $eventId : null,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':reply_count' => 0,
            ':last_reply_date' => $now,
            ':privacy' => $privacy,
        ]);

        $conversationId = (int)$pdo->lastInsertId();

        // Auto-membership: "Speaking is joining"
        if ($communityId > 0 && $authorId > 0 && $authorEmail !== '') {
            $memberService = new CommunityMemberService($this->db);
            try {
                $memberService->addMember(
                    $communityId,
                    $authorId,
                    $authorEmail,
                    $authorName,
                    'member'
                );
            } catch (\RuntimeException $e) {
                // Silently ignore if already a member or other membership errors
                // The conversation was created successfully, membership is secondary
            }
        }

        if ($this->search !== null) {
            $this->search->indexConversation(
                $conversationId,
                $title,
                $content,
                $slug,
                $authorId,
                $communityId > 0 ? $communityId : null,
                $eventId > 0 ? $eventId : null,
                $privacy,
                $now
            );
        }

        return $slug;
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
             FROM events e
             LEFT JOIN guests g ON g.event_id = e.id
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
        $stmt = $this->db->pdo()->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
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
        $stmt = $this->db->pdo()->prepare('SELECT privacy FROM communities WHERE id = :id LIMIT 1');
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
    public function listReplies(int $conversationId, ?int $viewerId = null): array
    {
        $sql = 'SELECT r.id, r.conversation_id, r.parent_reply_id, r.content, r.image_url, r.image_alt, r.author_name, r.created_at, r.depth_level,
                    u.id AS author_id, u.username AS author_username, u.display_name AS author_display_name, u.email AS author_email, u.avatar_url AS author_avatar_url, u.avatar_preference AS author_avatar_preference
             FROM conversation_replies r
             LEFT JOIN users u ON r.author_id = u.id';

        // Exclude replies from blocked users
        if ($viewerId !== null && $viewerId > 0) {
            $sql .= ' LEFT JOIN user_blocks ub ON ub.blocked_user_id = r.author_id AND ub.blocker_user_id = :viewer_id
                      WHERE r.conversation_id = :cid AND ub.id IS NULL';
            $params = [':cid' => $conversationId, ':viewer_id' => $viewerId];
        } else {
            $sql .= ' WHERE r.conversation_id = :cid';
            $params = [':cid' => $conversationId];
        }

        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

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

            $uploaderId = (int)($data['author_id'] ?? 0);
            $uploadResult = $this->imageService->upload(
                file: $data['image'],
                uploaderId: $uploaderId,
                altText: $imageAlt,
                imageType: 'reply',
                entityType: 'conversation',
                entityId: $conversationId,
                context: [
                    'conversation_id' => $conversationId,
                    'reply_id' => 0  // Will be updated after reply is created
                ]
            );

            if (!$uploadResult['success']) {
                throw new \RuntimeException($uploadResult['error'] ?? 'Failed to upload image.');
            }

            $imageUrl = $uploadResult['urls'];
        }

        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO conversation_replies (conversation_id, parent_reply_id, content, image_url, image_alt, author_id, author_name, author_email, depth_level, created_at, updated_at)
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

        $replyId = (int)$pdo->lastInsertId();

        // Update conversation reply count and last reply date
        $updateStmt = $pdo->prepare(
            'UPDATE conversations
             SET reply_count = reply_count + 1,
                 last_reply_date = :last_reply_date,
                 updated_at = :updated_at
             WHERE id = :conversation_id'
        );
        $updateStmt->execute([
            ':last_reply_date' => $now,
            ':updated_at' => $now,
            ':conversation_id' => (int)$conversation['id'],
        ]);

        // Auto-membership: "Speaking is joining"
        $communityId = isset($conversation['community_id']) ? (int)$conversation['community_id'] : 0;
        if ($communityId > 0 && $authorId > 0 && $authorEmail !== '') {
            $memberService = new CommunityMemberService($this->db);
            try {
                $memberService->addMember(
                    $communityId,
                    $authorId,
                    $authorEmail,
                    $authorName,
                    'member'
                );
            } catch (\RuntimeException $e) {
                // Silently ignore if already a member or other membership errors
                // The reply was created successfully, membership is secondary
            }
        }

        // Conversation linking: "Speaking is connecting" (per trust.xml v1.1 section 7)
        if ($this->circles && $authorId > 0) {
            try {
                $this->linkConversationParticipants($conversationId, $authorId);
            } catch (\Throwable $e) {
                // Silently ignore linking errors - the reply was created successfully
                // Linking is a trust-building feature, not a requirement
            }
        }

        return $replyId;
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
            "UPDATE conversations
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

        if ($this->search !== null) {
            $this->search->indexConversation(
                (int)($conversation['id'] ?? 0),
                $title,
                $content,
                $slug,
                (int)($conversation['author_id'] ?? 0),
                isset($conversation['community_id']) ? (int)$conversation['community_id'] : null,
                isset($conversation['event_id']) ? (int)$conversation['event_id'] : null,
                (string)($conversation['privacy'] ?? 'public'),
                $now
            );
        }

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

        $stmt = $pdo->prepare('DELETE FROM conversations WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        $deleted = $stmt->rowCount() === 1;

        if ($deleted && $this->search !== null) {
            $this->search->remove('conversation', (int)($conversation['id'] ?? 0));
        }

        return $deleted;
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
                   c.created_at, c.reply_count, c.last_reply_date, c.privacy,
                   u.username AS author_name,
                   com.name AS community_name, com.slug AS community_slug,
                   e.title AS event_title, e.slug AS event_slug
            FROM conversations c
            LEFT JOIN users u ON c.author_id = u.id
            LEFT JOIN communities com ON c.community_id = com.id
            LEFT JOIN events e ON c.event_id = e.id
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
     * List conversations filtered by author hop distance (global feed).
     * Per trust.xml Section 5: "Author hop distance only."
     *
     * @param array<int>|null $allowedUsers User IDs within N hops, or null for 'all'
     * @param array<int> $memberCommunities Communities viewer is member of
     * @param array{page?: int, per_page?: int, filter?: string} $options
     * @return array{conversations: array<int, array<string, mixed>>, pagination: array}
     */
    public function listByAuthorHop(int $viewerId, ?array $allowedUsers, array $memberCommunities, array $options = []): array
    {
        $options = array_merge(['page' => 1, 'per_page' => 20, 'filter' => ''], $options);
        $page = max(1, (int)$options['page']);
        $perPage = max(1, (int)$options['per_page']);
        $offset = ($page - 1) * $perPage;
        $fetchLimit = $perPage + 1;
        $filter = strtolower((string)$options['filter']);

        $allowedUsers = $allowedUsers === null ? null : $this->uniqueInts($allowedUsers);
        $memberCommunities = $this->uniqueInts($memberCommunities);

        // Always include viewer's own content in their circles
        if ($allowedUsers !== null && $viewerId > 0) {
            $allowedUsers[] = $viewerId;
            $allowedUsers = $this->uniqueInts($allowedUsers);
        }

        if ($allowedUsers !== null && $allowedUsers === []) {
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

        if ($allowedUsers !== null) {
            $conditions[] = 'conv.author_id IN (' . $this->placeholderList(count($allowedUsers)) . ')';
            $params = array_merge($params, $allowedUsers);
        }

        $privacyParts = ["com.privacy = 'public'"];
        if ($memberCommunities !== []) {
            $privacyParts[] = 'conv.community_id IN (' . $this->placeholderList(count($memberCommunities)) . ')';
            $params = array_merge($params, $memberCommunities);
        }
        $conditions[] = '(' . implode(' OR ', $privacyParts) . ')';

        if ($filter === 'my-events') {
            $eventIds = $this->lookupViewerEventIds($viewerId, null);
            if ($eventIds === []) {
                return [
                    'conversations' => [],
                    'pagination' => ['page' => $page, 'per_page' => $perPage, 'has_more' => false, 'next_page' => null],
                ];
            }
            $conditions[] = 'conv.event_id IN (' . $this->placeholderList(count($eventIds)) . ')';
            $params = array_merge($params, $eventIds);
        } elseif ($filter === 'all-events') {
            $conditions[] = 'conv.event_id IS NOT NULL';
        } elseif ($filter === 'communities') {
            $conditions[] = 'conv.community_id IS NOT NULL';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT
                conv.id,
                conv.title,
                conv.slug,
                conv.content,
                conv.author_name,
                conv.created_at,
                COALESCE(replies.reply_total, conv.reply_count) AS reply_count,
                conv.last_reply_date,
                conv.privacy,
                conv.community_id,
                conv.event_id,
                com.name AS community_name,
                com.slug AS community_slug,
                com.privacy AS community_privacy,
                evt.title AS event_title,
                evt.slug AS event_slug
            FROM conversations conv
            LEFT JOIN (
                SELECT conversation_id, COUNT(*) AS reply_total
                FROM conversation_replies
                GROUP BY conversation_id
            ) replies ON replies.conversation_id = conv.id
            LEFT JOIN communities com ON conv.community_id = com.id
            LEFT JOIN events evt ON conv.event_id = evt.id
            $where
            ORDER BY COALESCE(conv.updated_at, conv.created_at) DESC
            LIMIT $fetchLimit OFFSET $offset";

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
            ],
        ];
    }

    /**
     * List conversations by a specific author (personal feed).
     *
     * @param array<int> $memberCommunities
     * @param array{page?: int, per_page?: int} $options
     * @return array{conversations: array, pagination: array}
     */
    public function listByAuthor(int $authorId, int $viewerId, array $memberCommunities, array $options = []): array
    {
        $options = array_merge(['page' => 1, 'per_page' => 20], $options);
        $page = max(1, (int)$options['page']);
        $perPage = max(1, (int)$options['per_page']);
        $offset = ($page - 1) * $perPage;
        $fetchLimit = $perPage + 1;

        $memberCommunities = $this->uniqueInts($memberCommunities);

        $params = [$authorId];
        $conditions = ['conv.author_id = ?'];

        $privacyParts = ["com.privacy = 'public'"];
        if ($memberCommunities !== []) {
            $privacyParts[] = 'conv.community_id IN (' . $this->placeholderList(count($memberCommunities)) . ')';
            $params = array_merge($params, $memberCommunities);
        }
        $conditions[] = '(' . implode(' OR ', $privacyParts) . ')';

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT
                conv.id,
                conv.title,
                conv.slug,
                conv.content,
                conv.author_name,
                conv.created_at,
                COALESCE(replies.reply_total, conv.reply_count) AS reply_count,
                conv.last_reply_date,
                conv.privacy,
                conv.community_id,
                conv.event_id,
                com.name AS community_name,
                com.slug AS community_slug
            FROM conversations conv
            LEFT JOIN (
                SELECT conversation_id, COUNT(*) AS reply_total
                FROM conversation_replies
                GROUP BY conversation_id
            ) replies ON replies.conversation_id = conv.id
            LEFT JOIN communities com ON conv.community_id = com.id
            $where
            ORDER BY conv.created_at DESC
            LIMIT $fetchLimit OFFSET $offset";

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
            ],
        ];
    }

    /**
     * List all conversations in a community (community feed).
     * Per trust.xml Section 5: Shows all member content if community is accessible.
     *
     * @param array{page?: int, per_page?: int} $options
     * @return array{conversations: array, pagination: array}
     */
    public function listByCommunity(int $communityId, int $viewerId, array $options = []): array
    {
        if ($communityId <= 0) {
            return [
                'conversations' => [],
                'pagination' => ['page' => 1, 'per_page' => 20, 'has_more' => false, 'next_page' => null],
            ];
        }

        $options = array_merge(['page' => 1, 'per_page' => 20], $options);
        $page = max(1, (int)$options['page']);
        $perPage = max(1, (int)$options['per_page']);
        $offset = ($page - 1) * $perPage;
        $fetchLimit = $perPage + 1;

        $sql = "SELECT
                conv.id,
                conv.title,
                conv.slug,
                conv.content,
                conv.author_name,
                conv.author_id,
                conv.created_at,
                COALESCE(replies.reply_total, conv.reply_count) AS reply_count,
                conv.last_reply_date,
                conv.privacy,
                conv.community_id,
                conv.event_id,
                com.name AS community_name,
                com.slug AS community_slug,
                evt.title AS event_title,
                evt.slug AS event_slug
            FROM conversations conv
            LEFT JOIN (
                SELECT conversation_id, COUNT(*) AS reply_total
                FROM conversation_replies
                GROUP BY conversation_id
            ) replies ON replies.conversation_id = conv.id
            LEFT JOIN communities com ON conv.community_id = com.id
            LEFT JOIN events evt ON conv.event_id = evt.id
            WHERE conv.community_id = ?
            ORDER BY COALESCE(conv.updated_at, conv.created_at) DESC
            LIMIT $fetchLimit OFFSET $offset";

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([$communityId]);

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
            ],
        ];
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

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM conversations WHERE slug = :slug');

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
        $stmt = $pdo->prepare('SELECT * FROM conversation_replies WHERE id = :id');
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

        // Handle image - either from library URL or new upload
        $imageUrl = null;
        $imageAlt = null;
        $updateImage = false;

        if (!empty($data['image_url'])) {
            // Image selected from library - use existing URL
            $imageUrl = $data['image_url'];
            $imageAlt = trim((string)($data['image_alt'] ?? ''));
            if ($imageAlt === '') {
                throw new \RuntimeException('Image alt-text is required for accessibility.');
            }
            $updateImage = true;
        } elseif ($this->imageService && !empty($data['image']) && !empty($data['image']['tmp_name'])) {
            // New file upload
            $imageAlt = trim((string)($data['image_alt'] ?? ''));
            if ($imageAlt === '') {
                throw new \RuntimeException('Image alt-text is required for accessibility.');
            }

            $uploaderId = (int)($reply['author_id'] ?? 0);
            $conversationId = (int)($reply['conversation_id'] ?? 0);
            $uploadResult = $this->imageService->upload(
                file: $data['image'],
                uploaderId: $uploaderId,
                altText: $imageAlt,
                imageType: 'reply',
                entityType: 'conversation',
                entityId: $conversationId,
                context: [
                    'conversation_id' => $conversationId,
                    'reply_id' => $replyId
                ]
            );

            if (!$uploadResult['success']) {
                throw new \RuntimeException($uploadResult['error'] ?? 'Failed to upload image.');
            }

            $imageUrl = $uploadResult['urls'];
            $updateImage = true;
        } elseif (isset($data['image_alt'])) {
            // Update alt text only if no new image but alt text provided
            $imageAlt = trim((string)$data['image_alt']);
            $updateImage = true;
        }

        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s');

        if ($updateImage) {
            $sql = 'UPDATE conversation_replies
                    SET content = :content, image_url = :image_url, image_alt = :image_alt, updated_at = :updated_at
                    WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':content' => $content,
                ':image_url' => $imageUrl ?? $reply['image_url'] ?? null,
                ':image_alt' => $imageAlt ?? $reply['image_alt'] ?? null,
                ':updated_at' => $now,
                ':id' => $replyId
            ]);
        } else {
            $sql = 'UPDATE conversation_replies
                    SET content = :content, updated_at = :updated_at
                    WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                ':content' => $content,
                ':updated_at' => $now,
                ':id' => $replyId
            ]);
        }
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
        $stmt = $pdo->prepare('DELETE FROM conversation_replies WHERE id = :id');
        return $stmt->execute([':id' => $replyId]);
    }

    /**
     * Link conversation participants per trust.xml v1.1 section 7.
     * Creates mutual user_links between the author and all other participants.
     */
    private function linkConversationParticipants(int $conversationId, int $authorId): void
    {
        if (!$this->circles || $authorId <= 0) {
            return;
        }

        // Get the conversation to find its author
        $conversation = $this->getBySlugOrId((string)$conversationId);
        if ($conversation === null) {
            return;
        }

        $conversationAuthorId = isset($conversation['author_id']) ? (int)$conversation['author_id'] : 0;

        // Collect all participant IDs (conversation author + all repliers)
        $participants = [];

        // Add conversation author
        if ($conversationAuthorId > 0 && $conversationAuthorId !== $authorId) {
            $participants[] = $conversationAuthorId;
        }

        // Get all unique author_ids from replies to this conversation
        $stmt = $this->db->pdo()->prepare(
            'SELECT DISTINCT author_id FROM conversation_replies WHERE conversation_id = ? AND author_id > 0 AND author_id != ?'
        );
        $stmt->execute([$conversationId, $authorId]);
        $replyAuthors = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($replyAuthors as $replyAuthorId) {
            $participants[] = (int)$replyAuthorId;
        }

        // Create links between current author and all participants
        foreach ($participants as $participantId) {
            if ($participantId > 0 && $participantId !== $authorId) {
                $this->circles->createLink($authorId, $participantId);
            }
        }
    }
}
