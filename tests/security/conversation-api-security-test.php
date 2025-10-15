<?php declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = vt_service('database.connection')->pdo();
$auth = vt_service('auth.service');
$security = vt_service('security.service');
$conversationService = vt_service('conversation.service');
$communityService = vt_service('community.service');

$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));
    $email = 'circle+tester-' . $suffix . '@example.com';
    $password = 'SecurePass!' . $suffix;
    $username = 'circle_tester_' . $suffix;

    $register = $auth->register([
        'display_name' => 'Circle Tester ' . $suffix,
        'username' => $username,
        'email' => $email,
        'password' => $password,
    ]);

    if (!$register['success']) {
        throw new RuntimeException('Failed to register test user: ' . json_encode($register['errors']));
    }

    $login = $auth->attemptLogin($email, $password);
    if (!$login['success']) {
        throw new RuntimeException('Failed to log in test user.');
    }

    $viewerId = $auth->currentUserId();
    if ($viewerId === null || $viewerId <= 0) {
        throw new RuntimeException('Auth service did not report a logged-in user.');
    }

    $slug = $conversationService->create([
        'title' => 'Security Test Conversation ' . $suffix,
        'content' => 'Testing circle security hardening.',
    ]);
    $conversation = $conversationService->getBySlugOrId($slug);
    if ($conversation === null || !isset($conversation['id'])) {
        throw new RuntimeException('Failed to create test conversation.');
    }
    $conversationId = (int)$conversation['id'];

    $viewer = $auth->getCurrentUser();
    $viewerEmail = is_object($viewer) && isset($viewer->email) ? (string)$viewer->email : '';
    $viewerName = is_object($viewer) && isset($viewer->display_name) ? (string)$viewer->display_name : '';

    $createdCommunity = $communityService->create([
        'name' => 'Security Test Community ' . $suffix,
        'description' => 'Used for verifying nonce enforcement.',
        'privacy' => 'public',
        'creator_id' => $viewerId,
        'creator_email' => $viewerEmail,
        'creator_display_name' => $viewerName,
    ]);
    $community = $communityService->getBySlugOrId($createdCommunity['slug']);
    if ($community === null || !isset($community['id'])) {
        throw new RuntimeException('Failed to create test community.');
    }
    $communityId = (int)$community['id'];

    $resetGlobals = static function (): void {
        $_POST = [];
        $_GET = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/tests';
    };

    $resetGlobals();
    $_POST = [
        'nonce' => 'invalid-nonce',
        'circle' => 'inner',
    ];
    $_SERVER['REQUEST_URI'] = '/api/conversations';
    $response = vt_service('controller.conversations.api')->list();
    if (($response['status'] ?? 0) !== 403) {
        throw new RuntimeException('Conversation list did not reject invalid nonce.');
    }

    $resetGlobals();
    $validListNonce = $security->createNonce('vt_nonce', $viewerId);
    $_POST = [
        'nonce' => $validListNonce,
        'circle' => 'inner',
    ];
    $_SERVER['REQUEST_URI'] = '/api/conversations';
    $response = vt_service('controller.conversations.api')->list();
    if (($response['status'] ?? 0) !== 200) {
        throw new RuntimeException('Conversation list failed with valid nonce: ' . json_encode($response));
    }

    $resetGlobals();
    $_POST = [
        'nonce' => 'invalid-nonce',
        'content' => 'Attempted reply',
    ];
    $_SERVER['REQUEST_URI'] = '/api/conversations/' . $conversationId . '/replies';
    $response = vt_service('controller.conversations.api')->reply((string)$conversationId);
    if (($response['status'] ?? 0) !== 403) {
        throw new RuntimeException('Conversation reply did not reject invalid nonce.');
    }

    $resetGlobals();
    $validReplyNonce = $security->createNonce('vt_conversation_reply', $viewerId);
    $_POST = [
        'nonce' => $validReplyNonce,
        'content' => 'Circle security reply test.',
    ];
    $_SERVER['REQUEST_URI'] = '/api/conversations/' . $conversationId . '/replies';
    $response = vt_service('controller.conversations.api')->reply((string)$conversationId);
    if (($response['status'] ?? 0) !== 201) {
        throw new RuntimeException('Conversation reply failed with valid nonce: ' . json_encode($response));
    }

    $resetGlobals();
    $_POST = [
        'nonce' => 'invalid-nonce',
    ];
    $_SERVER['REQUEST_URI'] = '/api/communities/' . $communityId . '/join';
    $response = vt_service('controller.communities.api')->join($communityId);
    if (($response['status'] ?? 0) !== 403) {
        throw new RuntimeException('Community join did not reject invalid nonce.');
    }

    $resetGlobals();
    $validJoinNonce = $security->createNonce('vt_nonce', $viewerId);
    $_POST = [
        'nonce' => $validJoinNonce,
    ];
    $_SERVER['REQUEST_URI'] = '/api/communities/' . $communityId . '/join';
    $response = vt_service('controller.communities.api')->join($communityId);
    if (($response['status'] ?? 0) !== 200) {
        throw new RuntimeException('Community join failed with valid nonce: ' . json_encode($response));
    }

    echo "Conversation API security tests passed.\n";

    $pdo->rollBack();
    $auth->logout();
    exit(0);
} catch (Throwable $e) {
    $pdo->rollBack();
    $auth->logout();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
