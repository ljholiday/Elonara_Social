<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * EventGuestService
 *
 * Encapsulates vt_guests CRUD operations so modern controllers avoid the legacy
 * VT_Guest_Manager dependency.
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
             FROM vt_guests
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
    public function createGuest(int $eventId, string $email, string $token, string $notes = ''): int
    {
        $stmt = $this->database->pdo()->prepare(
            "INSERT INTO vt_guests
                (event_id, email, name, status, rsvp_token, notes, created_at)
             VALUES (:event_id, :email, '', 'pending', :token, :notes, NOW())"
        );

        $stmt->execute([
            ':event_id' => $eventId,
            ':email' => $email,
            ':token' => $token,
            ':notes' => $notes,
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
             FROM vt_guests
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
             FROM vt_guests
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
            "DELETE FROM vt_guests WHERE id = :id AND event_id = :event_id"
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
            "UPDATE vt_guests
             SET rsvp_token = :token, updated_at = NOW()
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
}
