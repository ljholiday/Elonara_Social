<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\CommunityService;
use App\Services\EventService;
use App\Services\MailService;

final class AdminController
{
    public function __construct(
        private AuthService $auth,
        private EventService $events,
        private CommunityService $communities,
        private MailService $mail
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

    public function settings(): array
    {
        $this->guard();

        return [
            'page_title' => 'Site Settings',
            'nav_active' => 'settings',
            'mailConfig' => require __DIR__ . '/../../../config/mail.php',
        ];
    }

    public function sendTestEmail(): array
    {
        $this->guard();

        $user = $this->auth->getCurrentUser();
        $to = $user?->email ?? app_config('support_email');

        $success = $this->mail->send($to, 'Admin Mail Test', '<p>If you received this, mail is working.</p>', 'If you received this, mail is working.');
        return [
            'redirect' => '/admin/settings',
            'flash' => $success
                ? ['type' => 'success', 'message' => 'Test email sent to ' . $to]
                : ['type' => 'error', 'message' => 'Failed to send test email. Check debug.log for details.'],
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
