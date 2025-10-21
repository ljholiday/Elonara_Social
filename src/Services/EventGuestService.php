<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * EventGuestService
 *
 * Encapsulates guests CRUD operations so modern controllers remain decoupled
 * from the static manager layer.
 */
final class EventGuestService
{
    public function __construct(private Database $database)
    {
    }

    public function guestExists(int $eventId, string $email): bool
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT id
             FROM guests
             WHERE event_id = :event_id
               AND email = :email
               AND status != 'declined'
             LIMIT 1"
        );

        $stmt->execute([
            ':event_id' => $eventId,
            ':email' => $email,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Create a pending guest invitation entry.
     *
     * @return int Inserted guest id
     */
    public function createGuest(int $eventId, string $email, string $token, string $notes = '', string $invitationSource = 'direct'): int
    {
        $stmt = $this->database->pdo()->prepare(
            "INSERT INTO guests
                (event_id, email, name, status, rsvp_token, notes, invitation_source, rsvp_date)
             VALUES (:event_id, :email, '', 'pending', :token, :notes, :invitation_source, NOW())"
        );

        $stmt->execute([
            ':event_id' => $eventId,
            ':email' => $email,
            ':token' => $token,
            ':notes' => $notes,
            ':invitation_source' => $invitationSource,
        ]);

        return (int)$this->database->pdo()->lastInsertId();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function listGuests(int $eventId): array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT id,
                    event_id,
                    email,
                    name,
                    status,
                    invitation_source,
                    dietary_restrictions,
                    plus_one,
                    plus_one_name,
                    notes,
                    rsvp_token,
                    rsvp_date,
                    temporary_guest_id
             FROM guests
             WHERE event_id = :event_id
             ORDER BY rsvp_date DESC, id DESC"
        );

        $stmt->execute([':event_id' => $eventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findGuestForEvent(int $eventId, int $guestId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT *
             FROM guests
             WHERE id = :id AND event_id = :event_id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $guestId,
            ':event_id' => $eventId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function deleteGuest(int $eventId, int $guestId): void
    {
        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM guests WHERE id = :id AND event_id = :event_id"
        );

        $stmt->execute([
            ':id' => $guestId,
            ':event_id' => $eventId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Guest not found for this event.');
        }
    }

    public function updateGuestToken(int $eventId, int $guestId, string $token): void
    {
        $stmt = $this->database->pdo()->prepare(
            "UPDATE guests
             SET rsvp_token = :token, rsvp_date = NOW()
             WHERE id = :id AND event_id = :event_id"
        );

        $stmt->execute([
            ':token' => $token,
            ':id' => $guestId,
            ':event_id' => $eventId,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Guest not found for this event.');
        }
    }

    /**
     * Fetch a guest invitation by RSVP token with event context.
     *
     * @return array<string,mixed>|null
     */
    public function findGuestByToken(string $token): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT
                g.*,
                e.title AS event_title,
                e.slug AS event_slug,
                e.event_date,
                e.event_time,
                e.venue_info,
                e.description AS event_description,
                e.featured_image,
                e.allow_plus_ones,
                e.max_guests,
                e.guest_limit
             FROM guests g
             JOIN events e ON g.event_id = e.id
             WHERE g.rsvp_token = :token
             LIMIT 1"
        );

        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Update guest invitation by RSVP token.
     *
     * @param array<string,mixed> $data
     */
    public function updateGuestByToken(string $token, array $data): bool
    {
        if ($token === '') {
            return false;
        }

        $columns = [];
        $params = [':token' => $token];

        foreach ($data as $key => $value) {
            $columns[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        if ($columns === []) {
            return false;
        }

        $sql = "UPDATE guests SET " . implode(', ', $columns) . " WHERE rsvp_token = :token";
        $stmt = $this->database->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function countGuestsByStatus(int $eventId, string $status): int
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT COUNT(*) FROM guests
             WHERE event_id = :event_id AND status = :status"
        );

        $stmt->execute([
            ':event_id' => $eventId,
            ':status' => $status,
        ]);

        return (int)$stmt->fetchColumn();
    }
}
