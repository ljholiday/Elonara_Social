<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

/**
 * Authorization Service
 *
 * Implements permission checks per PERMISSIONS.md doctrine.
 * All authorization decisions centralized here.
 */
final class AuthorizationService
{
    public function __construct(private Database $database)
    {
    }

    /**
     * Check if user can view a conversation
     *
     * Rules:
     * - Public conversations: Anyone
     * - Members-only: Must be member of community
     * - Private: Must be invited/member
     */
    public function canViewConversation(array $conversation, int $viewerId, array $memberCommunities): bool
    {
        $privacy = strtolower((string)($conversation['privacy'] ?? 'public'));

        if ($privacy === 'public') {
            return true;
        }

        $communityId = (int)($conversation['community_id'] ?? 0);
        if ($communityId > 0 && in_array($communityId, $memberCommunities, true)) {
            return true;
        }

        // Author can always view their own conversations
        $authorId = (int)($conversation['author_id'] ?? 0);
        if ($authorId > 0 && $authorId === $viewerId) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can edit a conversation
     *
     * Rules:
     * - Author can edit (with optional time limit)
     * - Community admin can edit
     * - Site admin can edit
     */
    public function canEditConversation(array $conversation, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        // Author can edit
        $authorId = (int)($conversation['author_id'] ?? 0);
        if ($authorId === $viewerId) {
            return true;
        }

        // Community admin can edit
        $communityId = (int)($conversation['community_id'] ?? 0);
        if ($communityId > 0 && $this->isCommunityAdmin($communityId, $viewerId)) {
            return true;
        }

        // Site admin can edit (check user role)
        if ($this->isSiteAdmin($viewerId)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can delete a conversation
     *
     * Rules:
     * - Can delete if no replies yet
     * - Cannot delete if has replies (archive instead)
     * - Community admin can delete
     * - Site admin can delete
     */
    public function canDeleteConversation(array $conversation, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        $authorId = (int)($conversation['author_id'] ?? 0);
        $replyCount = (int)($conversation['reply_count'] ?? 0);

        // Site admin can always delete
        if ($this->isSiteAdmin($viewerId)) {
            return true;
        }

        // Community admin can delete
        $communityId = (int)($conversation['community_id'] ?? 0);
        if ($communityId > 0 && $this->isCommunityAdmin($communityId, $viewerId)) {
            return true;
        }

        // Author can delete only if no replies
        if ($authorId === $viewerId && $replyCount === 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can reply to a conversation
     *
     * Rules:
     * - Must be logged in
     * - Must be able to view the conversation
     * - Conversation must not be locked/archived
     */
    public function canReplyToConversation(array $conversation, int $viewerId, array $memberCommunities): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        // Check if locked
        $status = strtolower((string)($conversation['status'] ?? 'active'));
        if (in_array($status, ['locked', 'archived'], true)) {
            return false;
        }

        // Must be able to view to reply
        return $this->canViewConversation($conversation, $viewerId, $memberCommunities);
    }

    /**
     * Check if user can create conversations in a community
     *
     * Rules:
     * - Must be logged in
     * - Must be member of community
     */
    public function canStartConversationInCommunity(int $communityId, int $viewerId): bool
    {
        if ($viewerId <= 0 || $communityId <= 0) {
            return false;
        }

        return $this->isCommunityMember($communityId, $viewerId);
    }

    /**
     * Check if user can view a community
     *
     * Rules:
     * - Public communities: Anyone
     * - Private communities: Members only
     */
    public function canViewCommunity(array $community, int $viewerId, array $memberCommunities): bool
    {
        $privacy = strtolower((string)($community['privacy'] ?? 'public'));

        if ($privacy === 'public') {
            return true;
        }

        $communityId = (int)($community['id'] ?? 0);
        if ($communityId > 0 && in_array($communityId, $memberCommunities, true)) {
            return true;
        }

        // Creator can always view
        $creatorId = (int)($community['creator_id'] ?? 0);
        if ($creatorId > 0 && $creatorId === $viewerId) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can edit a community
     *
     * Rules:
     * - Creator/owner can edit
     * - Community admin can edit
     * - Site admin can edit
     */
    public function canEditCommunity(array $community, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        // Creator can edit
        $creatorId = (int)($community['creator_id'] ?? 0);
        if ($creatorId === $viewerId) {
            return true;
        }

        // Community admin can edit
        $communityId = (int)($community['id'] ?? 0);
        if ($communityId > 0 && $this->isCommunityAdmin($communityId, $viewerId)) {
            return true;
        }

        // Site admin can edit
        if ($this->isSiteAdmin($viewerId)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can delete a community
     *
     * Rules:
     * - Creator cannot delete (permanent)
     * - Site admin can delete (with caution)
     */
    public function canDeleteCommunity(array $community, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        // Only site admin can delete communities
        return $this->isSiteAdmin($viewerId);
    }

    /**
     * Check if user can join a community
     *
     * Rules:
     * - Public communities: Instant join (if logged in)
     * - Private communities: Must be invited
     */
    public function canJoinCommunity(array $community, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        $privacy = strtolower((string)($community['privacy'] ?? 'public'));

        // Public communities allow instant join
        if ($privacy === 'public') {
            return true;
        }

        // Private communities require invitation
        // For now, return false (invitation system is separate)
        return false;
    }

    /**
     * Check if user can edit an event
     *
     * Rules:
     * - Author can edit
     * - Site admin can edit
     */
    public function canEditEvent(array $event, int $viewerId): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        // Author can edit
        $authorId = (int)($event['author_id'] ?? 0);
        if ($authorId === $viewerId) {
            return true;
        }

        // Site admin can edit
        if ($this->isSiteAdmin($viewerId)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can create events in a community
     *
     * Rules:
     * - Public communities: Any member
     * - Private communities: Owner/admin only
     */
    public function canCreateEventInCommunity(int $communityId, int $viewerId): bool
    {
        if ($viewerId <= 0 || $communityId <= 0) {
            return false;
        }

        $community = $this->getCommunityById($communityId);
        if ($community === null) {
            return false;
        }

        $privacy = strtolower((string)($community['privacy'] ?? 'public'));

        // Site admin can always create events
        if ($this->isSiteAdmin($viewerId)) {
            return true;
        }

        // Community admin can create events
        if ($this->isCommunityAdmin($communityId, $viewerId)) {
            return true;
        }

        // Public communities: any member can create events
        if ($privacy === 'public' && $this->isCommunityMember($communityId, $viewerId)) {
            return true;
        }

        // Private communities: only owner/admin
        return false;
    }

    /**
     * Check if user is a member of a community
     */
    public function isCommunityMember(int $communityId, int $userId): bool
    {
        if ($communityId <= 0 || $userId <= 0) {
            return false;
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM vt_community_members
            WHERE community_id = ? AND user_id = ? AND status = ?
        ');
        $stmt->execute([$communityId, $userId, 'active']);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if user is admin of a community
     */
    public function isCommunityAdmin(int $communityId, int $userId): bool
    {
        if ($communityId <= 0 || $userId <= 0) {
            return false;
        }

        // Check if user is creator
        $community = $this->getCommunityById($communityId);
        if ($community !== null) {
            $creatorId = (int)($community['creator_id'] ?? 0);
            if ($creatorId === $userId) {
                return true;
            }
        }

        // Check if user has admin role in community
        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM vt_community_members
            WHERE community_id = ? AND user_id = ? AND role = ? AND status = ?
        ');
        $stmt->execute([$communityId, $userId, 'admin', 'active']);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Check if user is site admin
     */
    public function isSiteAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $pdo = $this->database->pdo();
            $stmt = $pdo->prepare('
                SELECT role FROM vt_users WHERE id = ?
            ');
            $stmt->execute([$userId]);

            $role = $stmt->fetchColumn();
            return $role === 'admin' || $role === 'super_admin';
        } catch (\PDOException $e) {
            // If role column doesn't exist, no one is admin
            // This handles graceful degradation for databases without role column yet
            return false;
        }
    }

    /**
     * Get community by ID
     *
     * @return array<string,mixed>|null
     */
    private function getCommunityById(int $communityId): ?array
    {
        if ($communityId <= 0) {
            return null;
        }

        $pdo = $this->database->pdo();
        $stmt = $pdo->prepare('
            SELECT id, title, slug, privacy, creator_id, created_at
            FROM vt_communities
            WHERE id = ?
        ');
        $stmt->execute([$communityId]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }
}
