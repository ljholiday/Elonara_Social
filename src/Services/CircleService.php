<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

final class CircleService
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array{
     *   inner: array{communities: array<int>, creators: array<int>},
     *   trusted: array{communities: array<int>, creators: array<int>},
     *   extended: array{communities: array<int>, creators: array<int>}
     * }
     */
    public function buildContext(int $viewerId): array
    {
        if ($viewerId <= 0) {
            return [
                'inner' => ['communities' => [], 'creators' => []],
                'trusted' => ['communities' => [], 'creators' => []],
                'extended' => ['communities' => [], 'creators' => []],
            ];
        }

        $innerCommunities = $this->fetchCommunitiesForUsers([$viewerId]);
        $innerCreators = $this->fetchMembersForCommunities($innerCommunities);
        $innerCreators[] = $viewerId;

        $innerCommunities = $this->uniqueInts($innerCommunities);
        $innerCreators = $this->uniqueInts($innerCreators);

        $trustedCommunities = $this->uniqueInts(array_merge(
            $innerCommunities,
            $this->fetchCommunitiesForUsers($innerCreators)
        ));
        $trustedCreators = $this->uniqueInts(array_merge(
            $innerCreators,
            $this->fetchMembersForCommunities($trustedCommunities)
        ));

        $extendedCommunities = $this->uniqueInts(array_merge(
            $trustedCommunities,
            $this->fetchCommunitiesForUsers($trustedCreators)
        ));
        $extendedCreators = $this->uniqueInts(array_merge(
            $trustedCreators,
            $this->fetchMembersForCommunities($extendedCommunities)
        ));

        return [
            'inner' => [
                'communities' => $innerCommunities,
                'creators' => $innerCreators,
            ],
            'trusted' => [
                'communities' => $trustedCommunities,
                'creators' => $trustedCreators,
            ],
            'extended' => [
                'communities' => $extendedCommunities,
                'creators' => $extendedCreators,
            ],
        ];
    }

    /**
     * @return array<int>|null
     */
    public function resolveCommunitiesForCircle(array $context, string $circle): ?array
    {
        $circle = strtolower($circle);
        return match ($circle) {
            'inner' => $context['inner']['communities'] ?? [],
            'trusted' => $this->uniqueInts(array_merge(
                $context['inner']['communities'] ?? [],
                $context['trusted']['communities'] ?? []
            )),
            'extended' => $this->uniqueInts(array_merge(
                $context['inner']['communities'] ?? [],
                $context['trusted']['communities'] ?? [],
                $context['extended']['communities'] ?? []
            )),
            'all' => null,
            default => null,
        };
    }

    /**
     * @return array<int>
     */
    public function memberCommunities(array $context): array
    {
        return $context['inner']['communities'] ?? [];
    }

    /**
     * @param array<int> $userIds
     * @return array<int>
     */
    private function fetchCommunitiesForUsers(array $userIds): array
    {
        $userIds = $this->uniqueInts($userIds);
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT DISTINCT community_id FROM vt_community_members WHERE user_id IN ($placeholders) AND status = 'active'";

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($userIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        /** @var array<int> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $this->uniqueInts($rows);
    }

    /**
     * @param array<int> $communityIds
     * @return array<int>
     */
    private function fetchMembersForCommunities(array $communityIds): array
    {
        $communityIds = $this->uniqueInts($communityIds);
        if ($communityIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($communityIds), '?'));
        $sql = "SELECT DISTINCT user_id FROM vt_community_members WHERE community_id IN ($placeholders) AND status = 'active'";

        $stmt = $this->db->pdo()->prepare($sql);
        foreach ($communityIds as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        /** @var array<int> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $this->uniqueInts($rows);
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

        $ints = array_map(static fn($value) => (int) $value, $values);
        $ints = array_values(array_unique($ints));
        sort($ints);

        return $ints;
    }
}
