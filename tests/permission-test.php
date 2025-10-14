<?php declare(strict_types=1);

use App\Services\AuthService;

require_once dirname(__DIR__) . '/src/bootstrap.php';

final class AuthServiceTest
{
    private AuthService $auth;
    /** @var array<int, array{status:string,message:string}> */
    private array $results = [];

    public function __construct()
    {
        $service = vt_service('auth.service');
        if (!$service instanceof AuthService) {
            throw new RuntimeException('auth.service did not resolve to App\Services\AuthService.');
        }

        $this->auth = $service;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function run(): void
    {
        echo "Running AuthService tests...\n\n";

        $this->resetSession();
        $this->testSessionUserId();

        $this->resetSession();
        $this->testSessionEmail();

        $this->resetSession();
        $this->testEmptyState();

        $this->report();
    }

    private function testSessionUserId(): void
    {
        echo "Checking session-based currentUserId... ";
        $_SESSION['user_id'] = '123';

        $value = $this->auth->currentUserId();
        if ($value === 123) {
            $this->pass('currentUserId returns session user_id as int.');
        } else {
            $this->fail('Expected 123, got ' . var_export($value, true));
        }
    }

    private function testSessionEmail(): void
    {
        echo "Checking session-based currentUserEmail... ";
        $_SESSION['user_email'] = 'session@example.com';

        $value = $this->auth->currentUserEmail();
        if ($value === 'session@example.com') {
            $this->pass('currentUserEmail returns session email.');
        } else {
            $this->fail('Expected session@example.com, got ' . var_export($value, true));
        }
    }

    private function testEmptyState(): void
    {
        echo "Checking empty state returns nulls... ";
        $id = $this->auth->currentUserId();
        $email = $this->auth->currentUserEmail();

        if ($id === null && $email === null) {
            $this->pass('AuthService returns null when no session or legacy user is present.');
        } else {
            $this->fail('Expected null/null, got ' . var_export([$id, $email], true));
        }
    }

    private function pass(string $message): void
    {
        echo "PASS\n";
        $this->results[] = ['status' => 'PASS', 'message' => $message];
    }

    private function fail(string $message): void
    {
        echo "FAIL\n";
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
    }

    private function report(): void
    {
        echo "\n" . str_repeat('=', 42) . "\n";
        echo "AuthService Test Results\n";
        echo str_repeat('=', 42) . "\n";

        foreach ($this->results as $result) {
            echo "{$result['status']}: {$result['message']}\n";
        }

        $failed = array_filter($this->results, static fn($r) => $r['status'] === 'FAIL');
        echo "\n";
        $hasFailures = $failed !== [];
        if (!$hasFailures) {
            echo "AuthService behavior looks good.\n";
        } else {
            echo "Investigate the failing assertions above.\n";
        }
        exit($hasFailures ? 1 : 0);
    }

    private function resetSession(): void
    {
        $_SESSION = [];
    }
}

(new AuthServiceTest())->run();
