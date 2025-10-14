<?php declare(strict_types=1);

use App\Http\Controller\EventController;
use App\Services\AuthService;
use App\Services\CircleService;
use App\Services\CommunityService;
use App\Services\ConversationService;
use App\Services\EventService;

require_once dirname(__DIR__) . '/src/bootstrap.php';

final class SimpleSmokeTest
{
    /** @var array<int, string> */
    private array $messages = [];

    public function run(): void
    {
        echo "Running container smoke test...\n\n";

        $this->assertService('event.service', EventService::class);
        $this->assertService('community.service', CommunityService::class);
        $this->assertService('conversation.service', ConversationService::class);
        $this->assertService('circle.service', CircleService::class);
        $this->assertService('auth.service', AuthService::class);
        $this->assertController('controller.events', EventController::class);

        $this->report();
    }

    private function assertService(string $id, string $expectedClass): void
    {
        echo "Resolving service '{$id}'... ";
        try {
            $instance = vt_service($id);
            if ($instance instanceof $expectedClass) {
                echo "PASS\n";
                $this->messages[] = "PASS: Service '{$id}' resolved to {$expectedClass}.";
            } else {
                echo "FAIL\n";
                $resolved = is_object($instance) ? get_class($instance) : gettype($instance);
                $this->messages[] = "FAIL: Service '{$id}' resolved to {$resolved}, expected {$expectedClass}.";
            }
        } catch (\Throwable $e) {
            echo "FAIL\n";
            $this->messages[] = "FAIL: Service '{$id}' threw exception: " . $e->getMessage();
        }
    }

    private function assertController(string $id, string $expectedClass): void
    {
        echo "Resolving controller '{$id}'... ";
        try {
            $resolved = vt_service($id);
            if ($resolved instanceof $expectedClass) {
                echo "PASS\n";
                $this->messages[] = "PASS: Controller '{$id}' resolved to {$expectedClass}.";
            } else {
                echo "FAIL\n";
                $type = is_object($resolved) ? get_class($resolved) : gettype($resolved);
                $this->messages[] = "FAIL: Controller '{$id}' resolved to {$type}, expected {$expectedClass}.";
            }
        } catch (\Throwable $e) {
            echo "FAIL\n";
            $this->messages[] = "FAIL: Controller '{$id}' threw exception: " . $e->getMessage();
        }
    }

    private function report(): void
    {
        echo "\nSummary:\n";
        foreach ($this->messages as $message) {
            echo "  - {$message}\n";
        }

        $hasFailures = $this->hasFailures();
        if ($hasFailures) {
            echo "\nContainer smoke test detected issues.\n";
        } else {
            echo "\nContainer smoke test succeeded.\n";
        }
        exit($hasFailures ? 1 : 0);
    }

    private function hasFailures(): bool
    {
        foreach ($this->messages as $message) {
            if (str_starts_with($message, 'FAIL:')) {
                return true;
            }
        }

        return false;
    }
}

(new SimpleSmokeTest())->run();
