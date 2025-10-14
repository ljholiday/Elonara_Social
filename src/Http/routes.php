<?php
declare(strict_types=1);

use App\Http\Router;
use App\Http\Request;

// Load template helpers
require_once dirname(__DIR__, 2) . '/templates/_helpers.php';

/**
 * Application routes
 *
 * @param Router $router
 * @return void
 */
return static function (Router $router): void {
    // Auth redirects
    $router->any('/login', static function (Request $request) {
        header('Location: /auth');
        exit;
    });

    $router->any('/register', static function (Request $request) {
        header('Location: /auth');
        exit;
    });

    // Home
    $router->get('/', static function (Request $request) {
        $authService = vt_service('auth.service');
        if (!$authService->isLoggedIn()) {
            header('Location: /auth');
            exit;
        }

        $view = vt_service('controller.home')->dashboard();

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        vt_render('home.php', [
            'page_title' => 'Home',
            'viewer' => $view['viewer'],
            'upcoming_events' => $view['upcoming_events'],
            'my_communities' => $view['my_communities'],
            'recent_conversations' => $view['recent_conversations'],
            'nav_items' => [],
            'sidebar_content' => $sidebar,
        ], 'two-column');
        return null;
    });

    // Auth routes
    $router->get('/auth', static function (Request $request) {
        $view = vt_service('controller.auth')->landing();
        vt_render('auth-landing.php', array_merge($view, ['page_title' => 'Sign In or Register']), 'guest');
        return null;
    });

    $router->post('/auth/login', static function (Request $request) {
        $view = vt_service('controller.auth')->login();
        if (isset($view['redirect'])) {
            header('Location: ' . $view['redirect']);
            exit;
        }
        vt_render('auth-landing.php', array_merge($view, ['page_title' => 'Sign In']), 'guest');
        return null;
    });

    $router->post('/auth/register', static function (Request $request) {
        $view = vt_service('controller.auth')->register();
        if (isset($view['redirect'])) {
            header('Location: ' . $view['redirect']);
            exit;
        }
        vt_render('auth-landing.php', array_merge($view, ['page_title' => 'Register']), 'guest');
        return null;
    });

    $router->post('/auth/logout', static function (Request $request) {
        $result = vt_service('controller.auth')->logout();
        header('Location: ' . ($result['redirect'] ?? '/auth'));
        exit;
    });

    $router->get('/logout', function () {
        vt_service('controller.auth')->logout();
        header('Location: /auth');
        exit;
    });


    // Password Reset
    $router->get('/reset-password', static function (Request $request) {
        $view = vt_service('controller.auth')->requestReset();
        vt_render('password-reset-request.php', array_merge($view, ['page_title' => 'Reset Password']), 'guest');
        return null;
    });

    $router->post('/reset-password', static function (Request $request) {
        $result = vt_service('controller.auth')->sendResetEmail();
        if (isset($result['message'])) {
            $data = [
                'page_title' => 'Reset Password',
                'message' => $result['message'],
                'errors' => [],
                'input' => ['email' => '']
            ];
        } else {
            $data = [
                'page_title' => 'Reset Password',
                'message' => null,
                'errors' => $result['errors'] ?? [],
                'input' => $result['input'] ?? ['email' => '']
            ];
        }
        vt_render('password-reset-request.php', $data, 'guest');
        return null;
    });

    $router->get('/reset-password/{token}', static function (Request $request, string $token) {
        $view = vt_service('controller.auth')->showResetForm($token);
        if (!$view['valid']) {
            http_response_code(400);
            vt_render('password-reset-error.php', [
                'page_title' => 'Reset Password Error',
                'error' => $view['error'] ?? 'Invalid or expired token.'
            ], 'guest');
            return null;
        }
        vt_render('password-reset-form.php', [
            'page_title' => 'Reset Password',
            'token' => $view['token'],
            'errors' => []
        ], 'guest');
        return null;
    });

    $router->post('/reset-password/{token}', static function (Request $request, string $token) {
        $result = vt_service('controller.auth')->processReset($token);
        if (isset($result['redirect'])) {
            $_SESSION['flash_message'] = $result['message'] ?? 'Password reset successfully.';
            header('Location: ' . $result['redirect']);
            exit;
        }
        vt_render('password-reset-form.php', [
            'page_title' => 'Reset Password',
            'errors' => $result['errors'] ?? [],
            'token' => $result['token'] ?? $token
        ], 'guest');
        return null;
    });

    // Email Verification
    $router->get('/verify-email/{token}', static function (Request $request, string $token) {
        $result = vt_service('controller.auth')->verifyEmail($token);
        if ($result['success']) {
            $_SESSION['flash_message'] = $result['message'] ?? 'Email verified successfully.';
            header('Location: ' . ($result['redirect'] ?? '/'));
            exit;
        }
        vt_render('email-verification-error.php', [
            'page_title' => 'Email Verification Error',
            'errors' => $result['errors'] ?? ['token' => 'Verification failed.']
        ], 'guest');
        return null;
    });

    // Profile Routes
    $router->get('/profile', static function (Request $request) {
        $result = vt_service('controller.profile')->showOwn();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (isset($result['error'])) {
            header('Location: /auth');
            exit;
        }
        return null;
    });

    // IMPORTANT: /profile/edit must come BEFORE /profile/{username} to avoid treating "edit" as a username
    $router->get('/profile/edit', static function (Request $request) {
        $result = vt_service('controller.profile')->edit();
        if (isset($result['error'])) {
            header('Location: /auth');
            exit;
        }

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildProfileTabs($result['user'], $viewer, '/profile/edit');

        vt_render('profile-edit.php', [
            'page_title' => 'Edit Profile',
            'user' => $result['user'],
            'errors' => $result['errors'],
            'input' => $result['input'],
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar
        ], 'two-column');
        return null;
    });

    $router->get('/profile/{username}', static function (Request $request, string $username) {
        $result = vt_service('controller.profile')->show($username);
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Profile view - avatar_url: " . ($result['user']['avatar_url'] ?? 'NULL') . "\n", FILE_APPEND);

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildProfileTabs($result['user'], $viewer, '/profile/' . $username);

        vt_render('profile-view.php', [
            'page_title' => $result['user'] ? e($result['user']['display_name'] ?? $result['user']['username']) . ' - Profile' : 'User Not Found',
            'user' => $result['user'],
            'is_own_profile' => $result['is_own_profile'],
            'stats' => $result['stats'],
            'recent_activity' => $result['recent_activity'],
            'error' => $result['error'] ?? null,
            'success' => isset($_GET['updated']) ? 'Profile updated successfully!' : null,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar
        ], 'two-column');
        return null;
    });

    $router->post('/profile/update', static function (Request $request) {
        error_log("Profile update route hit - FILES: " . json_encode($_FILES));
        try {
            $result = vt_service('controller.profile')->update($request);
            if (isset($result['redirect'])) {
                header('Location: ' . $result['redirect']);
                exit;
            }
            if (isset($result['error'])) {
                $_SESSION['flash_error'] = $result['error'];
                header('Location: /auth');
                exit;
            }
            ob_start();
            $viewer = vt_service('auth.service')->getCurrentUser();
            include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
            $sidebar = ob_get_clean();

            $navService = vt_service('navigation.service');
            $tabs = $navService->buildProfileTabs($result['user'], $viewer, '/profile/edit');

            vt_render('profile-edit.php', [
                'page_title' => 'Edit Profile',
                'user' => $result['user'],
                'errors' => $result['errors'] ?? [],
                'input' => $result['input'] ?? [],
                'nav_items' => $tabs,
                'sidebar_content' => $sidebar
            ], 'two-column');
            return null;
        } catch (\Throwable $e) {
            file_put_contents(__DIR__ . '/../../debug.log', date('[Y-m-d H:i:s] ') . "Profile update route error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            http_response_code(500);
            echo "Error updating profile. Check debug.log for details.";
            exit;
        }
    });

    // Bluesky Connection
    $router->post('/connect/bluesky', static function (Request $request) {
        $authService = vt_service('auth.service');
        $blueskyService = vt_service('bluesky.service');
        $securityService = vt_service('security.service');

        $currentUser = $authService->getCurrentUser();
        if ($currentUser === null) {
            $_SESSION['flash_error'] = 'You must be logged in to connect Bluesky';
            header('Location: /auth');
            exit;
        }

        $nonce = (string)$request->input('nonce', '');
        if (!$securityService->verifyNonce($nonce, 'vt_nonce',(int)$currentUser->id)) {
            $_SESSION['flash_error'] = 'Security verification failed';
            header('Location: /profile/edit');
            exit;
        }

        $identifier = trim((string)$request->input('identifier', ''));
        $password = trim((string)$request->input('password', ''));

        if ($identifier === '' || $password === '') {
            $_SESSION['flash_error'] = 'Bluesky handle and app password are required';
            header('Location: /profile/edit');
            exit;
        }

        $sessionResult = $blueskyService->createSession($identifier, $password);

        if (!$sessionResult['success']) {
            $_SESSION['flash_error'] = $sessionResult['message'];
            header('Location: /profile/edit');
            exit;
        }

        $stored = $blueskyService->storeCredentials(
            (int)(int)$currentUser->id,
            $sessionResult['did'],
            $sessionResult['handle'],
            $sessionResult['accessJwt'],
            $sessionResult['refreshJwt']
        );

        if (!$stored) {
            $_SESSION['flash_error'] = 'Failed to store Bluesky credentials';
            header('Location: /profile/edit');
            exit;
        }

        // Sync followers in background
        $blueskyService->syncFollowers((int)$currentUser->id);

        $_SESSION['flash_success'] = 'Bluesky account connected successfully!';
        $logFile = dirname(__DIR__, 2) . '/debug.log';
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Bluesky connected - Setting flash_success in session\n", FILE_APPEND);
        header('Location: /profile/edit');
        exit;
    });

    $router->post('/disconnect/bluesky', static function (Request $request) {
        $authService = vt_service('auth.service');
        $blueskyService = vt_service('bluesky.service');
        $securityService = vt_service('security.service');

        $currentUser = $authService->getCurrentUser();
        if ($currentUser === null) {
            $_SESSION['flash_error'] = 'You must be logged in';
            header('Location: /auth');
            exit;
        }

        $nonce = (string)$request->input('nonce', '');
        if (!$securityService->verifyNonce($nonce, 'vt_nonce', (int)$currentUser->id)) {
            $_SESSION['flash_error'] = 'Security verification failed';
            header('Location: /profile/edit');
            exit;
        }

        $blueskyService->disconnectAccount((int)$currentUser->id);

        $_SESSION['flash_success'] = 'Bluesky account disconnected';
        header('Location: /profile/edit');
        exit;
    });

    // API: Bluesky
    $router->post('/api/bluesky/sync', static function (Request $request) {
        $authService = vt_service('auth.service');
        $blueskyService = vt_service('bluesky.service');
        $securityService = vt_service('security.service');

        header('Content-Type: application/json');

        $currentUser = $authService->getCurrentUser();
        if ($currentUser === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return null;
        }

        $nonce = (string)$request->input('nonce', '');
        if (!$securityService->verifyNonce($nonce, 'vt_nonce', (int)$currentUser->id)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security verification failed']);
            return null;
        }

        $result = $blueskyService->syncFollowers((int)$currentUser->id);
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode($result);
        return null;
    });

    $router->get('/api/bluesky/followers', static function (Request $request) {
        $authService = vt_service('auth.service');
        $blueskyService = vt_service('bluesky.service');

        header('Content-Type: application/json');

        $currentUser = $authService->getCurrentUser();
        if ($currentUser === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return null;
        }

        $result = $blueskyService->getCachedFollowers((int)$currentUser->id);
        http_response_code($result['success'] ? 200 : 404);
        echo json_encode($result);
        return null;
    });

    // API: Conversations
    $router->post('/api/conversations', static function (Request $request) {
        $response = vt_service('controller.conversations.api')->list();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->post('/api/conversations/{slug}/replies', static function (Request $request, string $slug) {
        $response = vt_service('controller.conversations.api')->reply($slug);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->post('/api/replies/{id}/edit', static function (Request $request, string $id) {
        header('Content-Type: application/json');
        $replyId = (int)$id;
        $conversationService = vt_service('conversation.service');
        $authService = vt_service('auth.service');
        $securityService = vt_service('security.service');

        try {
            // Verify nonce
            $nonce = $request->input('nonce');
            if (!$securityService->verifyNonce($nonce, 'vt_nonce')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                return true;
            }

            // Get reply and check ownership
            $reply = $conversationService->getReply($replyId);
            if (!$reply) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Reply not found']);
                return true;
            }

            $currentUser = $authService->getCurrentUser();
            $currentUserId = (int)$currentUser->id ?? 0;
            if ($currentUserId !== (int)($reply['author_id'] ?? 0)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                return true;
            }

            // Update reply
            $content = $request->input('content');
            $conversationService->updateReply($replyId, ['content' => $content]);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Reply updated']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return true;
    });

    $router->post('/api/replies/{id}/delete', static function (Request $request, string $id) {
        try {
            header('Content-Type: application/json');
            $replyId = (int)$id;

            $conversationService = vt_service('conversation.service');
            $authService = vt_service('auth.service');
            $securityService = vt_service('security.service');

            // Verify nonce
            $nonce = $request->input('nonce');
            if (!$securityService->verifyNonce($nonce, 'vt_nonce')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid security token']);
                return true;
            }

            // Get reply and check ownership
            $reply = $conversationService->getReply($replyId);
            if (!$reply) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Reply not found']);
                return true;
            }

            $currentUser = $authService->getCurrentUser();
            $currentUserId = (int)$currentUser->id ?? 0;
            if ($currentUserId !== (int)($reply['author_id'] ?? 0)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                return true;
            }

            // Delete reply
            $conversationService->deleteReply($replyId);

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Reply deleted']);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        return true;
    });

    // API: Communities
    $router->post('/api/communities/{id}/join', static function (Request $request, string $id) {
        $response = vt_service('controller.communities.api')->join((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    // API: Invitations
    $router->post('/api/invitations/accept', static function (Request $request) {
        $response = vt_service('controller.invitations')->accept();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/{type}/{id}/invitations', static function (Request $request, string $type, string $id) {
        $entityId = (int)$id;
        $controller = vt_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->sendCommunity($entityId)
            : $controller->sendEvent($entityId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->get('/api/{type}/{id}/invitations', static function (Request $request, string $type, string $id) {
        $entityId = (int)$id;
        $controller = vt_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->listCommunity($entityId)
            : $controller->listEvent($entityId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/events/{eventId}/invitations/{invitationId}/resend', static function (Request $request, string $eventId, string $invitationId) {
        $response = vt_service('controller.invitations')->resendEvent((int)$eventId, (int)$invitationId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->delete('/api/{type}/{entityId}/invitations/{invitationId}', static function (Request $request, string $type, string $entityId, string $invitationId) {
        $controller = vt_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->deleteCommunity((int)$entityId, (int)$invitationId)
            : $controller->deleteEvent((int)$entityId, (int)$invitationId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

// API: Bluesky Invitations
    
    $router->post('/api/invitations/bluesky/event/{id}', static function (Request $request, string $id) {
        $authService = vt_service('auth.service');
        $invitationService = vt_service('invitation.manager');
        $securityService = vt_service('security.service');

        header('Content-Type: application/json');

        $currentUser = $authService->getCurrentUser();
        if ($currentUser === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return true;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $nonce = (string)($body['nonce'] ?? '');
        $followerDids = $body['follower_dids'] ?? [];

        if (!$securityService->verifyNonce($nonce, 'vt_nonce', (int)$currentUser->id)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security verification failed']);
            return true;
        }

        if (!is_array($followerDids)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid follower_dids format']);
            return true;
        }

        try {
            $result = $invitationService->inviteBlueskyFollowersToEvent((int)$id, (int)$currentUser->id, $followerDids);
            http_response_code($result['status']);
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'] ?? '',
                'data' => $result['data'] ?? []
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        return true;
    });

    $router->post('/api/invitations/bluesky/community/{id}', static function (Request $request, string $id) {
        $authService = vt_service('auth.service');
        $invitationService = vt_service('invitation.manager');
        $securityService = vt_service('security.service');

        header('Content-Type: application/json');

        $currentUser = $authService->getCurrentUser();
        if ($currentUser === null) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            return true;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $nonce = (string)($body['nonce'] ?? '');
        $followerDids = $body['follower_dids'] ?? [];

        if (!$securityService->verifyNonce($nonce, 'vt_nonce', (int)$currentUser->id)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security verification failed']);
            return true;
        }

        if (!is_array($followerDids)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid follower_dids format']);
            return true;
        }

        try {
            $result = $invitationService->inviteBlueskyFollowersToCommunity((int)$id, (int)$currentUser->id, $followerDids);
            http_response_code($result['status']);
            echo json_encode([
                'success' => $result['success'],
                'message' => $result['message'] ?? '',
                'data' => $result['data'] ?? []
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        return true;
    });

    $router->get('/api/communities/{id}/members', static function (Request $request, string $id) {
        $response = vt_service('controller.invitations')->listCommunityMembers((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/communities/{communityId}/members/{memberId}/role', static function (Request $request, string $communityId, string $memberId) {
        $response = vt_service('controller.invitations')->updateCommunityMemberRole((int)$communityId, (int)$memberId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

    $router->delete('/api/communities/{communityId}/members/{memberId}', static function (Request $request, string $communityId, string $memberId) {
        $response = vt_service('controller.invitations')->removeCommunityMember((int)$communityId, (int)$memberId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return null;
    });

// Events
    $router->get('/events', static function (Request $request) {
        $view = vt_service('controller.events')->index();
        $filter = $view['filter'] ?? 'all';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        vt_render('events-list.php', array_merge($view, [
            'page_title' => 'Events',
            'nav_items' => [
                ['title' => 'All', 'url' => '/events?filter=all', 'active' => $filter === 'all'],
                ['title' => 'My Events', 'url' => '/events?filter=my', 'active' => $filter === 'my'],
            ],
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/events/create', static function (Request $request) {
        $view = vt_service('controller.events')->create();
        vt_render('event-create.php', array_merge($view, ['page_title' => 'Create Event']), 'form');
        return null;
    });

    $router->post('/events/create', static function (Request $request) {
        $result = vt_service('controller.events')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        vt_render('event-create.php', array_merge($result, ['page_title' => 'Create Event']), 'form');
        return null;
    });

    $router->get('/events/{slug}/edit', static function (Request $request, string $slug) {
        $view = vt_service('controller.events')->edit($slug);
        if ($view['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        vt_render('event-edit.php', array_merge($view, ['page_title' => 'Edit Event']), 'form');
        return null;
    });

    $router->get('/events/{slug}/manage', static function (Request $request, string $slug) {
        $tab = $request->query('tab');

        // If no tab specified, redirect to event view page
        if (!$tab) {
            header('Location: /events/' . $slug);
            exit;
        }

        $view = vt_service('controller.events')->manage($slug);
        $status = $view['status'] ?? 200;
        if ($status !== 200) {
            http_response_code($status);
        }
        $eventTitle = $view['event']['title'] ?? 'Event';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $currentUri = '/events/' . $slug . '/manage?tab=' . $tab;
        $tabs = $navService->buildEventManageTabs($view['event'], $currentUri);

        vt_render('event-manage.php', array_merge($view, [
            'page_title' => 'Manage ' . $eventTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->post('/events/{slug}/edit', static function (Request $request, string $slug) {
        $result = vt_service('controller.events')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['event']) || $result['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        vt_render('event-edit.php', array_merge($result, ['page_title' => 'Edit Event']), 'form');
        return null;
    });

    $router->post('/events/{slug}/delete', static function (Request $request, string $slug) {
        $result = vt_service('controller.events')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->get('/events/{slug}', static function (Request $request, string $slug) {
        $view = vt_service('controller.events')->show($slug);
        $eventTitle = $view['event']['title'] ?? 'Event';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildEventTabs($view['event'], $viewer, '/events/' . $slug);

        vt_render('event-detail.php', array_merge($view, [
            'page_title' => $eventTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/events/{slug}/conversations', static function (Request $request, string $slug) {
        $view = vt_service('controller.events')->conversations($slug);
        $eventTitle = $view['event']['title'] ?? 'Event';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildEventTabs($view['event'], $viewer, '/events/' . $slug . '/conversations');

        vt_render('event-conversations.php', array_merge($view, [
            'page_title' => 'Conversations - ' . $eventTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    // Communities
    $router->get('/communities', static function (Request $request) {
        $view = vt_service('controller.communities')->index();
        $circle = $view['circle'] ?? 'all';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        vt_render('communities-list.php', array_merge($view, [
            'page_title' => 'Communities',
            'nav_items' => [
                ['title' => 'All', 'url' => '/communities?circle=all', 'active' => $circle === 'all'],
                ['title' => 'Inner', 'url' => '/communities?circle=inner', 'active' => $circle === 'inner'],
                ['title' => 'Trusted', 'url' => '/communities?circle=trusted', 'active' => $circle === 'trusted'],
                ['title' => 'Extended', 'url' => '/communities?circle=extended', 'active' => $circle === 'extended'],
            ],
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/communities/create', static function (Request $request) {
        $view = vt_service('controller.communities')->create();
        vt_render('community-create.php', array_merge($view, ['page_title' => 'Create Community']), 'form');
        return null;
    });

    $router->post('/communities/create', static function (Request $request) {
        $result = vt_service('controller.communities')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        vt_render('community-create.php', array_merge($result, ['page_title' => 'Create Community']), 'form');
        return null;
    });

    $router->get('/communities/{slug}/edit', static function (Request $request, string $slug) {
        $view = vt_service('controller.communities')->edit($slug);
        if ($view['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        vt_render('community-edit.php', array_merge($view, ['page_title' => 'Edit Community']), 'form');
        return null;
    });

    $router->get('/communities/{slug}/manage', static function (Request $request, string $slug) {
        $tab = $request->query('tab');

        // If no tab specified, redirect to community view page
        if (!$tab) {
            header('Location: /communities/' . $slug);
            exit;
        }

        $view = vt_service('controller.communities')->manage($slug);
        $status = $view['status'] ?? 200;
        if ($status !== 200) {
            http_response_code($status);
        }
        $communityTitle = $view['community']['title'] ?? $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $currentUri = '/communities/' . $slug . '/manage?tab=' . $tab;
        $tabs = $navService->buildCommunityManageTabs($view['community'], $currentUri);

        vt_render('community-manage.php', array_merge($view, [
            'page_title' => 'Manage ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->post('/communities/{slug}/edit', static function (Request $request, string $slug) {
        $result = vt_service('controller.communities')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['community']) || $result['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        vt_render('community-edit.php', array_merge($result, ['page_title' => 'Edit Community']), 'form');
        return null;
    });

    $router->post('/communities/{slug}/delete', static function (Request $request, string $slug) {
        $result = vt_service('controller.communities')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->get('/communities/{slug}', static function (Request $request, string $slug) {
        $view = vt_service('controller.communities')->show($slug);
        $status = (int)($view['status'] ?? ($view['community'] === null ? 404 : 200));
        if ($status !== 200) {
            http_response_code($status);
        }
        $communityTitle = $view['community']['title'] ?? $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug);

        vt_render('community-detail.php', array_merge($view, [
            'page_title' => $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/communities/{slug}/events', static function (Request $request, string $slug) {
        $view = vt_service('controller.communities')->events($slug);
        $communityTitle = $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug . '/events');

        vt_render('community-events.php', array_merge($view, [
            'page_title' => 'Events - ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/communities/{slug}/conversations', static function (Request $request, string $slug) {
        $view = vt_service('controller.communities')->conversations($slug);
        $communityTitle = $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug . '/conversations');

        vt_render('community-conversations.php', array_merge($view, [
            'page_title' => 'Conversations - ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/communities/{slug}/members', static function (Request $request, string $slug) {
        $view = vt_service('controller.communities')->members($slug);
        $communityTitle = $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug . '/members');

        vt_render('community-members.php', array_merge($view, [
            'page_title' => 'Members - ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    // Conversations
    $router->get('/conversations', static function (Request $request) {
        $view = vt_service('controller.conversations')->index();
        $circle = $view['circle'] ?? 'all';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        vt_render('conversations-list.php', array_merge($view, [
            'page_title' => 'Conversations',
            'nav_items' => [
                ['title' => 'All', 'url' => '/conversations?circle=all', 'active' => $circle === 'all'],
                ['title' => 'Inner', 'url' => '/conversations?circle=inner', 'active' => $circle === 'inner'],
                ['title' => 'Trusted', 'url' => '/conversations?circle=trusted', 'active' => $circle === 'trusted'],
                ['title' => 'Extended', 'url' => '/conversations?circle=extended', 'active' => $circle === 'extended'],
            ],
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/conversations/create', static function (Request $request) {
        $view = vt_service('controller.conversations')->create();
        vt_render('conversation-create.php', array_merge($view, ['page_title' => 'New Conversation']), 'form');
        return null;
    });

    $router->post('/conversations/create', static function (Request $request) {
        $result = vt_service('controller.conversations')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        vt_render('conversation-create.php', array_merge($result, ['page_title' => 'New Conversation']), 'form');
        return null;
    });

    $router->get('/conversations/{slug}/edit', static function (Request $request, string $slug) {
        $view = vt_service('controller.conversations')->edit($slug);
        if ($view['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildConversationTabs($view['conversation'], $viewer, '/conversations/' . $slug . '/edit');

        vt_render('conversation-edit.php', array_merge($view, [
            'page_title' => 'Edit Conversation',
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->post('/conversations/{slug}/edit', static function (Request $request, string $slug) {
        $result = vt_service('controller.conversations')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['conversation']) || $result['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return null;
        }
        vt_render('conversation-edit.php', array_merge($result, ['page_title' => 'Edit Conversation']), 'form');
        return null;
    });

    $router->post('/conversations/{slug}/delete', static function (Request $request, string $slug) {
        $result = vt_service('controller.conversations')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->post('/conversations/{slug}/reply', static function (Request $request, string $slug) {
        $result = vt_service('controller.conversations')->reply($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $conversationTitle = $result['conversation']['title'] ?? 'Conversation';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildConversationTabs($result['conversation'], $viewer, '/conversations/' . $slug);

        vt_render('conversation-detail.php', array_merge($result, [
            'page_title' => $conversationTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

    $router->get('/conversations/{slug}', static function (Request $request, string $slug) {
        $view = vt_service('controller.conversations')->show($slug);
        $conversationTitle = $view['conversation']['title'] ?? 'Conversation';

        ob_start();
        $viewer = vt_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = vt_service('navigation.service');
        $tabs = $navService->buildConversationTabs($view['conversation'], $viewer, '/conversations/' . $slug);

        vt_render('conversation-detail.php', array_merge($view, [
            'page_title' => $conversationTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return null;
    });

$router->get('/invitation/accept', static function (Request $request) {
    $token = $request->query('token');

    if (!$token) {
        http_response_code(400);
        echo 'Missing invitation token.';
        return null;
    }

    // Use the existing controller to process the token
    $controller = vt_service('controller.invitations');
    $result = $controller->accept($token);

    // If the controller returns data or redirect info, handle it here
    if (isset($result['redirect'])) {
        header('Location: ' . $result['redirect']);
        exit;
    }

    // Simple success message if no redirect is specified
    echo 'Invitation accepted successfully.';
    return null;
});

};
