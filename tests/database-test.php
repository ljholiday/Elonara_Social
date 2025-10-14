<?php declare(strict_types=1);

use App\Database\Database;
use App\Services\EventService;

require_once dirname(__DIR__) . '/src/bootstrap.php';

final class DatabaseTest
{
    private Database $database;
    private \PDO $pdo;
    /** @var array<int, array{status:string,message:string}> */
    private array $results = [];

    public function __construct()
    {
        $service = vt_service('database.connection');
        if (!$service instanceof Database) {
            throw new RuntimeException('database.connection did not return an App\Database\Database instance.');
        }

        $this->database = $service;
        $this->pdo = $service->pdo();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function run(): void
    {
        echo "Running Database integration tests...\n\n";

        $this->testConnection();
        $this->testSchema();
        $this->testConfigCrud();
        $this->testEventServiceRecent();
        $this->seedCommunityFixtures();

        $this->report();
    }

    private function testConnection(): void
    {
        echo "Testing basic connection... ";
        try {
            $value = $this->pdo
                ->query('SELECT 1')
                ->fetchColumn();

            if ((int)$value === 1) {
                $this->pass('Database connection alive (SELECT 1).');
            } else {
                $this->fail('SELECT 1 returned unexpected value: ' . var_export($value, true));
            }
        } catch (\Throwable $e) {
            $this->fail('Connection query threw exception: ' . $e->getMessage());
        }
    }

    private function testSchema(): void
    {
        echo "Verifying core tables... ";
        $requiredTables = [
            'vt_users',
            'vt_events',
            'vt_config',
            'vt_communities',
            'vt_conversations',
        ];

        try {
            $missing = [];
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table LIMIT 1'
            );

            foreach ($requiredTables as $table) {
                $stmt->execute([':table' => $table]);
                if ($stmt->fetchColumn() === false) {
                    $missing[] = $table;
                }
            }

            if ($missing === []) {
                $this->pass('All required tables exist.');
            } else {
                $this->fail('Missing tables: ' . implode(', ', $missing));
            }
        } catch (\Throwable $e) {
            $this->fail('Schema verification failed: ' . $e->getMessage());
        }
    }

    private function testConfigCrud(): void
    {
        echo "Testing vt_config CRUD... ";
        $optionName = 'codex_test_' . bin2hex(random_bytes(8));
        $optionValue = 'value_' . bin2hex(random_bytes(4));
        $updatedValue = 'updated_' . bin2hex(random_bytes(4));

        try {
            $insert = $this->pdo->prepare(
                "INSERT INTO vt_config (option_name, option_value, autoload) VALUES (:name, :value, 'no')"
            );
            $insert->execute([':name' => $optionName, ':value' => $optionValue]);

            $select = $this->pdo->prepare("SELECT option_value FROM vt_config WHERE option_name = :name");
            $select->execute([':name' => $optionName]);
            $fetched = $select->fetchColumn();

            if (!is_string($fetched) || $fetched !== $optionValue) {
                $this->fail('Inserted option not found or returned wrong value.');
                $this->cleanupConfig($optionName);
                return;
            }

            $update = $this->pdo->prepare(
                "UPDATE vt_config SET option_value = :value WHERE option_name = :name"
            );
            $update->execute([':value' => $updatedValue, ':name' => $optionName]);

            $select->execute([':name' => $optionName]);
            $fetchedUpdated = $select->fetchColumn();

            if (!is_string($fetchedUpdated) || $fetchedUpdated !== $updatedValue) {
                $this->fail('Updated option returned wrong value.');
                $this->cleanupConfig($optionName);
                return;
            }

            $delete = $this->pdo->prepare("DELETE FROM vt_config WHERE option_name = :name");
            $delete->execute([':name' => $optionName]);

            $select->execute([':name' => $optionName]);
            if ($select->fetchColumn() !== false) {
                $this->fail('Option was not deleted cleanly.');
                return;
            }

            $this->pass('CRUD flow succeeded on vt_config.');
        } catch (\Throwable $e) {
            $this->fail('CRUD flow threw exception: ' . $e->getMessage());
            $this->cleanupConfig($optionName);
        }
    }

    private function testEventServiceRecent(): void
    {
        echo "Testing EventService::listRecent... ";
        try {
            $service = vt_service('event.service');
            if (!$service instanceof EventService) {
                $this->fail('event.service did not resolve to EventService.');
                return;
            }

            $events = $service->listRecent(5);
            if (!is_array($events)) {
                $this->fail('EventService::listRecent returned non-array.');
                return;
            }

            $this->pass(sprintf('Retrieved %d recent events (<=5 requested).', count($events)));
        } catch (\Throwable $e) {
            $this->fail('EventService::listRecent threw exception: ' . $e->getMessage());
        }
    }

    private function seedCommunityFixtures(): void
    {
        echo "Ensuring community fixtures... ";

        try {
            $startedTransaction = false;
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $startedTransaction = true;
            }

            $creator = $this->ensureTestUser();

            $communities = [
                [
                    'slug' => 'modern-public-community',
                    'name' => 'Modern Public Community',
                    'description' => 'Public sample community for the modern routes.',
                    'privacy' => 'public',
                    'created_at' => '2025-01-02 10:00:00',
                    'updated_at' => '2025-01-02 10:00:00',
                    'event_count' => 0,
                    'members' => [
                        ['user_id' => $creator['id'], 'role' => 'admin'],
                    ],
                ],
                [
                    'slug' => 'modern-private-circle',
                    'name' => 'Modern Private Circle',
                    'description' => 'Private fixture to exercise access controls.',
                    'privacy' => 'private',
                    'created_at' => '2025-01-03 10:00:00',
                    'updated_at' => '2025-01-03 10:00:00',
                    'event_count' => 0,
                    'members' => [
                        ['user_id' => $creator['id'], 'role' => 'admin'],
                    ],
                ],
            ];

            foreach ($communities as $communityData) {
                echo "  - " . $communityData['slug'] . "... ";
                $memberCount = count($communityData['members']);
                $communityId = $this->ensureCommunity($communityData, $creator, $memberCount);

                foreach ($communityData['members'] as $member) {
                    $this->ensureMembership($communityId, (int)$member['user_id'], (string)($member['role'] ?? 'member'));
                }

                $this->updateMemberCount($communityId, $memberCount);
                echo "done\n";
            }

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            $this->pass('Community fixtures ready (public and private).');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->fail('Failed to ensure community fixtures: ' . $e->getMessage());
        }
    }

    /**
     * @return array{id:int,email:string,display_name:string}
     */
    private function ensureTestUser(): array
    {
        $email = 'codex-tester@example.com';

        $stmt = $this->pdo->prepare('SELECT id, display_name FROM vt_users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            return [
                'id' => (int)$existing['id'],
                'email' => $email,
                'display_name' => (string)$existing['display_name'],
            ];
        }

        $baseUsername = 'codex_tester';
        $username = $baseUsername;
        $suffix = 1;

        $checkUsername = $this->pdo->prepare('SELECT COUNT(*) FROM vt_users WHERE username = :username');
        while (true) {
            $checkUsername->execute([':username' => $username]);
            if ((int)$checkUsername->fetchColumn() === 0) {
                break;
            }
            $username = $baseUsername . '_' . $suffix++;
        }

        $now = '2025-01-01 10:00:00';

        $insertUser = $this->pdo->prepare(
            "INSERT INTO vt_users (username, email, password_hash, display_name, status, created_at, updated_at)
             VALUES (:username, :email, :password_hash, :display_name, 'active', :created_at, :updated_at)"
        );

        $insertUser->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => password_hash('codex-pass', PASSWORD_DEFAULT),
            ':display_name' => 'Codex Tester',
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return [
            'id' => $id,
            'email' => $email,
            'display_name' => 'Codex Tester',
        ];
    }

    /**
     * @param array<string,mixed> $community
     */
    private function ensureCommunity(array $community, array $creator, int $memberCount): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM vt_communities WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $community['slug']]);
        $existingId = $stmt->fetchColumn();

        $payload = [
            ':name' => $community['name'],
            ':description' => $community['description'],
            ':privacy' => $community['privacy'],
            ':member_count' => $memberCount,
            ':event_count' => $community['event_count'],
            ':updated_at' => $community['updated_at'],
        ];

        if ($existingId !== false) {
            $update = $this->pdo->prepare(
                "UPDATE vt_communities
                 SET name = :name,
                     description = :description,
                     privacy = :privacy,
                     member_count = :member_count,
                     event_count = :event_count,
                     updated_at = :updated_at
                 WHERE id = :id"
            );
            $update->execute(array_merge($payload, [':id' => (int)$existingId]));
            return (int)$existingId;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO vt_communities (
                name,
                slug,
                description,
                type,
                privacy,
                member_count,
                event_count,
                creator_id,
                creator_email,
                is_active,
                requires_approval,
                created_at,
                updated_at,
                created_by
            ) VALUES (
                :name,
                :slug,
                :description,
                'standard',
                :privacy,
                :member_count,
                :event_count,
                :creator_id,
                :creator_email,
                1,
                0,
                :created_at,
                :updated_at,
                :created_by
            )"
        );

        $insert->execute(array_merge(
            $payload,
            [
                ':slug' => $community['slug'],
                ':creator_id' => $creator['id'],
                ':creator_email' => $creator['email'],
                ':created_at' => $community['created_at'],
                ':created_by' => $creator['id'],
            ]
        ));

        return (int)$this->pdo->lastInsertId();
    }

    private function ensureMembership(int $communityId, int $userId, string $role): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM vt_community_members WHERE community_id = :community_id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $userId,
        ]);

        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $userStmt = $this->pdo->prepare('SELECT email, display_name FROM vt_users WHERE id = :id LIMIT 1');
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            throw new RuntimeException('Cannot seed membership; user not found.');
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO vt_community_members (
                community_id,
                user_id,
                email,
                display_name,
                role,
                status,
                joined_at,
                last_seen_at
            ) VALUES (
                :community_id,
                :user_id,
                :email,
                :display_name,
                :role,
                'active',
                :joined_at,
                :last_seen_at
            )"
        );

        $now = '2025-01-04 10:00:00';

        $insert->execute([
            ':community_id' => $communityId,
            ':user_id' => $userId,
            ':email' => $user['email'],
            ':display_name' => $user['display_name'],
            ':role' => $role,
            ':joined_at' => $now,
            ':last_seen_at' => $now,
        ]);
    }

    private function updateMemberCount(int $communityId, int $count): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE vt_communities SET member_count = :member_count WHERE id = :id'
        );
        $stmt->execute([
            ':member_count' => $count,
            ':id' => $communityId,
        ]);
    }

    private function cleanupConfig(string $optionName): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM vt_config WHERE option_name = :name");
        $stmt->execute([':name' => $optionName]);
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
        echo "\n" . str_repeat('=', 40) . "\n";
        echo "Database Test Results\n";
        echo str_repeat('=', 40) . "\n";

        $total = count($this->results);
        $passed = array_filter($this->results, static fn($r) => $r['status'] === 'PASS');
        $failed = array_filter($this->results, static fn($r) => $r['status'] === 'FAIL');

        echo "Total tests: $total\n";
        echo "Passed: " . count($passed) . "\n";
        echo "Failed: " . count($failed) . "\n\n";

        foreach ($this->results as $result) {
            echo "  {$result['status']}: {$result['message']}\n";
        }

        echo "\n";
        $hasFailures = $failed !== [];
        if (!$hasFailures) {
            echo "Database layer looks healthy.\n";
        } else {
            echo "Investigate the failing checks above.\n";
        }
        exit($hasFailures ? 1 : 0);
    }
}

(new DatabaseTest())->run();
