<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Services\AuthService;
use App\Services\CommunityService;
use App\Services\EventService;
use App\Services\MailService;
use App\Services\UserService;
use App\Services\SecurityService;
use App\Services\SearchService;
use App\Support\ContextBuilder;
use App\Support\ContextLabel;

final class AdminController
{
    public function __construct(
        private AuthService $auth,
        private EventService $events,
        private CommunityService $communities,
        private MailService $mail,
        private UserService $users,
        private SearchService $search
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

        $recentEvents = array_map(function (array $event): array {
            $path = ContextBuilder::event($event, $this->communities);
            $plain = ContextLabel::renderPlain($path);
            $event['context_path'] = $path;
            $event['context_label'] = $plain !== '' ? $plain : (string)($event['title'] ?? '');
            return $event;
        }, $this->events->listRecentForAdmin(5));

        return [
            'page_title' => 'Overview',
            'nav_active' => 'dashboard',
            'stats' => $stats,
            'recentEvents' => $recentEvents,
            'recentCommunities' => $this->communities->listRecentForAdmin(5),
        ];
    }

    public function settings(): array
    {
        $this->guard();

        return [
            'page_title' => 'Site Settings',
            'nav_active' => 'settings',
            'mailConfig' => app_config('mail'),
            'analyticsConfig' => app_config('analytics', []),
        ];
    }

    public function users(): array
    {
        $this->guard();

        $request = $this->request();
        $search = trim((string)$request->query('q', ''));
        $page = max(1, (int)$request->query('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $result = $this->users->listForAdmin($search, $perPage, $offset);
        $total = $result['total'];
        $pages = max(1, (int)ceil($total / $perPage));

        return [
            'page_title' => 'Users',
            'nav_active' => 'users',
            'page_description' => 'Search, approve, and manage member accounts.',
            'searchQuery' => $search,
            'users' => $result['users'],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'per_page' => $perPage,
                'total' => $total,
            ],
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

    public function saveAnalyticsSettings(): array
    {
        $this->guard();

        $request = $this->request();
        $nonce = (string)$request->input('_admin_nonce', '');
        $currentUser = $this->auth->getCurrentUser();
        $currentUserId = (int)($currentUser?->id ?? 0);

        if (!$this->security()->verifyNonce($nonce, 'app_admin', $currentUserId)) {
            return $this->redirectWithFlash('error', 'Security check failed. Please refresh and try again.', '/admin/settings');
        }

        $trackingId = trim((string)$request->input('ga_tracking_id', ''));

        // Validate format if not empty
        if ($trackingId !== '' && !preg_match('/^(G|UA|GT|AW)-[A-Z0-9]+$/i', $trackingId)) {
            return $this->redirectWithFlash('error', 'Invalid tracking ID format. Expected G-XXXXXXXXXX or UA-XXXXXXXXX', '/admin/settings');
        }

        try {
            $this->updateConfigFile('analytics', ['google_tracking_id' => $trackingId]);
            $message = $trackingId !== ''
                ? 'Analytics tracking enabled successfully.'
                : 'Analytics tracking disabled successfully.';
            return $this->redirectWithFlash('success', $message, '/admin/settings');
        } catch (\Throwable $e) {
            $this->logAdminError('save_analytics', $currentUserId, $e->getMessage());
            return $this->redirectWithFlash('error', 'Failed to save analytics settings. Check logs for details.', '/admin/settings');
        }
    }

    public function reindexSearch(): array
    {
        $this->guard();

        $request = $this->request();
        $nonce = (string)$request->input('_admin_nonce', '');
        $currentUser = $this->auth->getCurrentUser();
        $currentUserId = (int)($currentUser?->id ?? 0);

        if (!$this->security()->verifyNonce($nonce, 'app_admin', $currentUserId)) {
            return $this->redirectWithFlash('error', 'Security check failed. Please refresh and try again.', '/admin');
        }

        try {
            $counts = $this->search->reindexAll();
            $message = sprintf(
                'Search index rebuilt (%d communities, %d events, %d conversations).',
                $counts['communities'] ?? 0,
                $counts['events'] ?? 0,
                $counts['conversations'] ?? 0
            );

            return $this->redirectWithFlash('success', $message, '/admin');
        } catch (\Throwable $e) {
            $this->logAdminError('reindex_search', (int)$currentUserId, $e->getMessage());
            return $this->redirectWithFlash('error', 'Failed to rebuild search index. Check logs for details.', '/admin');
        }
    }

    /**
     * @return array{redirect?:string, flash?:array{type:string,message:string}}
     */
    public function handleUserAction(string $action, int $userId): array
    {
        $this->guard();

        $action = strtolower($action);
        $allowed = ['reset-password', 'resend-verification', 'approve', 'delete'];
        if (!in_array($action, $allowed, true)) {
            return $this->redirectWithFlash('error', 'Unknown action requested.');
        }

        if ($userId <= 0) {
            return $this->redirectWithFlash('error', 'Invalid user id.');
        }

        $request = $this->request();
        $nonce = (string)$request->input('_admin_nonce', '');

        $currentUser = $this->auth->getCurrentUser();
        $currentUserId = (int)($currentUser?->id ?? 0);

        if (!$this->security()->verifyNonce($nonce, 'app_admin', $currentUserId)) {
            return $this->redirectWithFlash('error', 'Security check failed. Please refresh and try again.');
        }

        $target = $this->users->getAdminUser($userId);
        if ($target === null) {
            return $this->redirectWithFlash('error', 'User not found.');
        }

        $currentRole = (string)($currentUser->role ?? 'member');
        $targetRole = (string)($target['role'] ?? 'member');
        $targetStatus = (string)($target['status'] ?? '');

        if ($userId === (int)$currentUserId && $action === 'delete') {
            return $this->redirectWithFlash('error', 'You cannot delete your own account.');
        }

        if ($targetRole === 'super_admin' && $currentRole !== 'super_admin') {
            return $this->redirectWithFlash('error', 'Only super admins can modify that account.');
        }

        if ($targetStatus === 'deleted' && $action !== 'delete') {
            return $this->redirectWithFlash('error', 'That account has already been deleted.');
        }

        return match ($action) {
            'reset-password' => $this->processResetPassword($userId, (string)$target['email']),
            'resend-verification' => $this->processResendVerification($userId, (string)$target['email']),
            'approve' => $this->processManualApprove($userId, $targetStatus),
            'delete' => $this->processDelete($userId),
            default => $this->redirectWithFlash('error', 'Unsupported operation.'),
        };
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

    private function request(): Request
    {
        /** @var Request $request */
        $request = app_service('http.request');
        return $request;
    }

    private function security(): SecurityService
    {
        /** @var SecurityService $service */
        $service = app_service('security.service');
        return $service;
    }

    /**
     * @return array{redirect:string, flash:array{type:string,message:string}}
     */
    private function redirectWithFlash(string $type, string $message, string $redirect = '/admin/users'): array
    {
        return [
            'redirect' => $redirect,
            'flash' => [
                'type' => $type,
                'message' => $message,
            ],
        ];
    }

    /**
     * @return array{redirect:string, flash:array{type:string,message:string}}
     */
    private function processResetPassword(int $userId, string $email): array
    {
        $result = $this->auth->adminSendPasswordReset($userId);
        if ($result['success']) {
            $message = $result['message'] ?? ('Password reset email sent to ' . $email);
            return $this->redirectWithFlash('success', $message);
        }

        $error = $result['errors']['user'] ?? 'Unable to send reset email.';
        return $this->redirectWithFlash('error', $error);
    }

    /**
     * @return array{redirect:string, flash:array{type:string,message:string}}
     */
    private function processResendVerification(int $userId, string $email): array
    {
        $result = $this->auth->sendVerificationEmail($userId, $email);
        if ($result['success']) {
            return $this->redirectWithFlash('success', 'Verification email resent to ' . $email . '.');
        }

        $message = $result['message'] ?? 'Unable to send verification email.';
        return $this->redirectWithFlash('error', $message);
    }

    /**
     * @return array{redirect:string, flash:array{type:string,message:string}}
     */
    private function processManualApprove(int $userId, string $currentStatus): array
    {
        if ($currentStatus === 'active') {
            return $this->redirectWithFlash('success', 'User already active.');
        }

        $updated = $this->users->activateUser($userId);
        if ($updated) {
            return $this->redirectWithFlash('success', 'User approved and activated.');
        }

        return $this->redirectWithFlash('error', 'Unable to activate user.');
    }

    /**
     * @return array{redirect:string, flash:array{type:string,message:string}}
     */
    private function processDelete(int $userId): array
    {
        $deleted = $this->users->deleteUser($userId);
        if ($deleted) {
            return $this->redirectWithFlash('success', 'User deleted.');
        }

        return $this->redirectWithFlash('error', 'Unable to delete user.');
    }

    /**
     * Update a section of the config/config.php file
     *
     * @param string $section The top-level config key to update
     * @param array<string,mixed> $data The data to set for that section
     * @throws \RuntimeException if config file cannot be read or written
     */
    private function updateConfigFile(string $section, array $data): void
    {
        $configPath = dirname(__DIR__, 3) . '/config/config.php';

        if (!is_file($configPath) || !is_readable($configPath)) {
            throw new \RuntimeException('Config file not found or not readable.');
        }

        $currentConfig = require $configPath;
        if (!is_array($currentConfig)) {
            throw new \RuntimeException('Config file did not return an array.');
        }

        // Update the section
        $currentConfig[$section] = $data;

        // Generate PHP code
        $export = var_export($currentConfig, true);
        $content = <<<PHP
<?php
/**
 * Elonara Social - Master Configuration File
 *
 * SINGLE SOURCE OF TRUTH for all application configuration.
 * This file is gitignored - never commit to repository.
 */

declare(strict_types=1);

return {$export};

PHP;

        if (file_put_contents($configPath, $content) === false) {
            throw new \RuntimeException('Failed to write config file.');
        }
    }

    private function logAdminError(string $action, int $userId, string $message): void
    {
        error_log(sprintf('[ADMIN ERROR] Action: %s, User: %d, Message: %s', $action, $userId, $message));
    }
}
