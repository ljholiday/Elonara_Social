<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\CommunityService;
use App\Services\EventService;

final class AdminController
{
    public function __construct(
        private AuthService $auth,
        private EventService $events,
        private CommunityService $communities
    ) {
    }

    public function dashboard(): array
    {
        $this->guard();

        $stats = [
            ['label' => 'Users', 'value' => $this->countUsers()],
            ['label' => 'Pending Verifications', 'value' => $this->countPendingUsers()],
            ['label' => 'Events', 'value' => $this->events->countAll()],
            ['label' => 'Communities', 'value' => $this->communities->countAll()],
        ];

        return [
            'page_title' => 'Overview',
            'nav_active' => 'dashboard',
            'stats' => $stats,
            'recentEvents' => $this->events->listRecentForAdmin(5),
            'recentCommunities' => $this->communities->listRecentForAdmin(5),
        ];
    }

    private function guard(): void
    {
        $user = $this->auth->getCurrentUser();
        if ($user === null || !in_array($user->role ?? 'member', ['admin', 'super_admin'], true)) {
            header('Location: /auth');
            exit;
        }
    }

    private function countUsers(): int
    {
        $pdo = app_service('database.connection')->pdo();
        return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    private function countPendingUsers(): int
    {
        $pdo = app_service('database.connection')->pdo();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
        return (int)$stmt->fetchColumn();
    }
}
