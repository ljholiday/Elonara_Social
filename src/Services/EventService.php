<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * EventService
 * Thin data-access layer for events.
 *
 * Notes:
 * - Uses PDO with ERRMODE_EXCEPTION (set in Database).
 * - Returns associative arrays (no objects) to keep templates simple.
 */
final class EventService
{
    private Database $db;

    public function __construct(
        Database $db,
        private ?SearchService $search = null
    ) {
        $this->db = $db;
    }

    /**
     * List recent events, newest first.
     *
     * @param int $limit Max rows to return.
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit = 20): array
    {
        $sql = "SELECT id, title, event_date, end_date, location, slug, description, privacy,
                       featured_image, featured_image_alt
                FROM events
                ORDER BY event_date DESC
                LIMIT :lim";

        $stmt = $this->db->pdo()->prepare($sql);
        // bindValue with explicit type so MySQL accepts LIMIT as integer
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAll(): int
    {
        $stmt = $this->db->pdo()->query('SELECT COUNT(*) FROM events');
        return (int)$stmt->fetchColumn();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecentForAdmin(int $limit = 5): array
    {
        $sql = "SELECT e.id, e.title, e.event_date, e.end_date, e.privacy, u.display_name AS host,
                       e.community_id, com.name AS community_name, com.slug AS community_slug
                FROM events e
                LEFT JOIN users u ON u.id = e.author_id
                LEFT JOIN communities com ON e.community_id = com.id
                ORDER BY e.event_date DESC
                LIMIT :lim";
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{events: array<int, array<string, mixed>>, total: int}
     */
    public function listForAdmin(string $search = '', int $limit = 25, int $offset = 0): array
    {
        $search = trim($search);
        $hasSearch = $search !== '';

        $where = [];
        $params = [];

        if ($hasSearch) {
            $where[] = "(e.title LIKE :search OR e.description LIKE :search OR com.name LIKE :search OR u.display_name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total
        $countSql = "SELECT COUNT(DISTINCT e.id) FROM events e
                     LEFT JOIN users u ON u.id = e.author_id
                     LEFT JOIN communities com ON e.community_id = com.id
                     {$whereClause}";
        $countStmt = $this->db->pdo()->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Fetch events
        $sql = "SELECT e.id, e.title, e.event_date, e.end_date, e.privacy, e.slug,
                       e.community_id, com.name AS community_name, com.slug AS community_slug,
                       u.id AS host_id, u.display_name AS host_name
                FROM events e
                LEFT JOIN users u ON u.id = e.author_id
                LEFT JOIN communities com ON e.community_id = com.id
                {$whereClause}
                ORDER BY e.event_date DESC
                LIMIT :lim OFFSET :off";

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'events' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMine(int $viewerId, ?string $viewerEmail = null, int $limit = 20): array
    {
        if ($viewerId <= 0) {
            return [];
        }

        $email = $viewerEmail !== null ? trim($viewerEmail) : $this->lookupUserEmail($viewerId);

        $sql = "SELECT DISTINCT e.id, e.title, e.event_date, e.end_date, e.location, e.slug, e.description, e.privacy,
                       e.community_id, e.featured_image, e.featured_image_alt,
                       com.name AS community_name, com.slug AS community_slug
                FROM events e
                LEFT JOIN guests g ON g.event_id = e.id
                LEFT JOIN communities com ON e.community_id = com.id
                WHERE e.event_status = 'active'
                  AND e.status = 'active'
                  AND (
                        e.author_id = :viewer_author
                        OR g.converted_user_id = :viewer_guest
                        OR g.email = :viewer_email
                    )
                ORDER BY e.event_date DESC
                LIMIT :lim";

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->bindValue(':viewer_author', $viewerId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_guest', $viewerId, PDO::PARAM_INT);
        if ($email !== null && $email !== '') {
            $stmt->bindValue(':viewer_email', $email, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':viewer_email', '', PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a single event by slug or numeric id.
     *
     * @param string $slugOrId Slug like "my-event" or numeric id like "42".
     * @return array<string, mixed>|null
     */
    public function getBySlugOrId(string $slugOrId): ?array
    {
        $pdo = $this->db->pdo();

        if (ctype_digit($slugOrId)) {
            $stmt = $pdo->prepare(
                "SELECT e.id, e.title, e.event_date, e.end_date, e.location, e.slug, e.description, e.author_id, e.event_status, e.privacy, e.guest_limit, e.community_id,
                        e.featured_image, e.featured_image_alt,
                        com.name AS community_name, com.slug AS community_slug
                 FROM events e
                 LEFT JOIN communities com ON e.community_id = com.id
                 WHERE e.id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => (int)$slugOrId]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT e.id, e.title, e.event_date, e.end_date, e.location, e.slug, e.description, e.author_id, e.event_status, e.privacy, e.guest_limit, e.community_id,
                        e.featured_image, e.featured_image_alt,
                        com.name AS community_name, com.slug AS community_slug
                 FROM events e
                 LEFT JOIN communities com ON e.community_id = com.id
                 WHERE e.slug = :slug
                 LIMIT 1"
            );
            $stmt->execute([':slug' => $slugOrId]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * @param array{title:string,description:string,event_date:?string} $data
     */
    public function create(array $data): string
    {
        $title = trim($data['title']);
        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }

        $pdo = $this->db->pdo();
        $slug = $this->ensureUniqueSlug($pdo, $this->slugify($title));

        $createdAt = date('Y-m-d H:i:s');
        $authorId = (int)($data['author_id'] ?? 0);
        $createdBy = (int)($data['created_by'] ?? $authorId);
        $communityId = (int)($data['community_id'] ?? 0);
        $privacy = (string)($data['privacy'] ?? 'public');
        $visibility = (string)($data['visibility'] ?? 'public');

        $stmt = $pdo->prepare(
            "INSERT INTO events (
                title,
                slug,
                description,
                event_date,
                end_date,
                location,
                featured_image,
                featured_image_alt,
                created_at,
                updated_at,
                created_by,
                author_id,
                post_id,
                event_status,
                status,
                visibility,
                privacy,
                community_id
            ) VALUES (
                :title,
                :slug,
                :description,
                :event_date,
                :end_date,
                :location,
                :featured_image,
                :featured_image_alt,
                :created_at,
                :updated_at,
                :created_by,
                :author_id,
                :post_id,
                :event_status,
                :status,
                :visibility,
                :privacy,
                :community_id
            )"
        );

        $stmt->execute([
            ':title' => $title,
            ':slug' => $slug,
            ':description' => $data['description'],
            ':event_date' => $data['event_date'],
            ':end_date' => $data['end_date'] ?? null,
            ':location' => $data['location'] ?? null,
            ':featured_image' => $data['featured_image'] ?? null,
            ':featured_image_alt' => $data['featured_image_alt'] ?? null,
            ':created_at' => $createdAt,
            ':updated_at' => $createdAt,
            ':created_by' => $createdBy,
            ':author_id' => $authorId,
            ':post_id' => 0,
            ':event_status' => 'active',
            ':status' => 'active',
            ':visibility' => $visibility,
            ':privacy' => $privacy,
            ':community_id' => $communityId > 0 ? $communityId : null,
        ]);

        $eventId = (int)$pdo->lastInsertId();

        if ($this->search !== null) {
            $this->search->indexEvent(
                $eventId,
                $title,
                (string)($data['description'] ?? ''),
                $slug,
                $authorId,
                $communityId,
                $privacy,
                $data['event_date'] ?? null
            );
        }

        return $slug;
    }

    /**
     * @param array{title:string,description:string,event_date:?string} $data
     */
    public function update(string $slugOrId, array $data): string
    {
        $event = $this->getBySlugOrId($slugOrId);
        if ($event === null) {
            throw new RuntimeException('Event not found.');
        }

        $title = trim($data['title']);
        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }

        $slug = (string)($event['slug'] ?? $slugOrId);
        $pdo = $this->db->pdo();
        $updatedAt = date('Y-m-d H:i:s');

        // Build dynamic UPDATE query based on provided data
        $setFields = [
            'title = :title',
            'description = :description',
            'event_date = :event_date',
            'end_date = :end_date',
            'location = :location',
            'updated_at = :updated_at',
        ];

        $params = [
            ':title' => $title,
            ':description' => $data['description'],
            ':event_date' => $data['event_date'],
            ':end_date' => $data['end_date'] ?? null,
            ':location' => $data['location'] ?? null,
            ':updated_at' => $updatedAt,
            ':slug' => $slug,
        ];

        // Only update featured image if provided
        if (isset($data['featured_image'])) {
            $setFields[] = 'featured_image = :featured_image';
            $setFields[] = 'featured_image_alt = :featured_image_alt';
            $params[':featured_image'] = $data['featured_image'];
            $params[':featured_image_alt'] = $data['featured_image_alt'] ?? null;
        }

        $sql = "UPDATE events SET " . implode(', ', $setFields) . " WHERE slug = :slug LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($this->search !== null) {
            $this->search->indexEvent(
                (int)($event['id'] ?? 0),
                $title,
                (string)($data['description'] ?? ''),
                $slug,
                (int)($event['author_id'] ?? 0),
                $event['community_id'] !== null ? (int)$event['community_id'] : null,
                (string)($event['privacy'] ?? 'public'),
                $data['event_date'] ?? ($event['event_date'] ?? null)
            );
        }

        return $slug;
    }

    public function delete(string $slugOrId): bool
    {
        $event = $this->getBySlugOrId($slugOrId);
        if ($event === null) {
            return false;
        }

        $slug = (string)($event['slug'] ?? $slugOrId);
        $pdo = $this->db->pdo();

        $stmt = $pdo->prepare('DELETE FROM events WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        $deleted = $stmt->rowCount() === 1;

        if ($deleted && $this->search !== null) {
            $this->search->remove('event', (int)($event['id'] ?? 0));
        }

        return $deleted;
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
            SELECT id, title, slug, description, event_date, end_date, location, author_id, community_id, created_at, privacy,
                   featured_image, featured_image_alt
            FROM events
            WHERE community_id = :community_id
            ORDER BY event_date DESC, created_at DESC
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
        return $slug !== '' ? $slug : 'event';
    }

    private function ensureUniqueSlug(PDO $pdo, string $slug): string
    {
        $base = $slug;
        $i = 1;

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM events WHERE slug = :slug');

        while (true) {
            $stmt->execute([':slug' => $slug]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = $base . '-' . ++$i;
        }
    }

    private function lookupUserEmail(int $viewerId): ?string
    {
        if ($viewerId <= 0) {
            return null;
        }

        $stmt = $this->db->pdo()->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $viewerId, PDO::PARAM_INT);
        $stmt->execute();

        $email = $stmt->fetchColumn();
        return is_string($email) ? $email : null;
    }

}
