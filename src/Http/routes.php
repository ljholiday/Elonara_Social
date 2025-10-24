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
        $authService = app_service('auth.service');
        if (!$authService->isLoggedIn()) {
            header('Location: /auth');
            exit;
        }

        $view = app_service('controller.home')->dashboard();

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        app_render('home.php', [
            'page_title' => 'Home',
            'page_description' => 'Your personal dashboard - upcoming events, communities, and recent conversations',
            'viewer' => $view['viewer'],
            'upcoming_events' => $view['upcoming_events'],
            'my_communities' => $view['my_communities'],
            'recent_conversations' => $view['recent_conversations'],
            'nav_items' => [],
            'sidebar_content' => $sidebar,
        ], 'two-column');
        return true;
    });

    // Admin
    $router->get('/admin', static function (Request $request) {
        $view = app_service('controller.admin')->dashboard();
        app_render('admin/dashboard.php', $view + ['page_title' => 'Admin Overview'], 'admin');
        return true;
    });

    $router->get('/admin/settings', static function (Request $request) {
        $view = app_service('controller.admin')->settings();
        if (!empty($_SESSION['admin_flash'])) {
            $view['flash'] = $_SESSION['admin_flash'];
            unset($_SESSION['admin_flash']);
        }
        app_render('admin/settings.php', $view, 'admin');
        return true;
    });

    $router->get('/admin/users', static function (Request $request) {
        $view = app_service('controller.admin')->users();
        if (!empty($_SESSION['admin_flash'])) {
            $view['flash'] = $_SESSION['admin_flash'];
            unset($_SESSION['admin_flash']);
        }
        app_render('admin/users.php', $view, 'admin');
        return true;
    });

    $router->post('/admin/users/{userId}/{action}', static function (Request $request, string $userId, string $action) {
        $result = app_service('controller.admin')->handleUserAction($action, (int)$userId);
        if (!empty($result['flash'])) {
            $_SESSION['admin_flash'] = $result['flash'];
        }
        $redirect = $result['redirect'] ?? '/admin/users';
        header('Location: ' . $redirect);
        exit;
    });

    $router->post('/admin/settings/test-mail', static function (Request $request) {
        $result = app_service('controller.admin')->sendTestEmail();
        if (!empty($result['flash'])) {
            $_SESSION['admin_flash'] = $result['flash'];
        }
        header('Location: ' . ($result['redirect'] ?? '/admin/settings'));
        exit;
    });

    $router->post('/admin/settings/analytics', static function (Request $request) {
        $result = app_service('controller.admin')->saveAnalyticsSettings();
        if (!empty($result['flash'])) {
            $_SESSION['admin_flash'] = $result['flash'];
        }
        header('Location: ' . ($result['redirect'] ?? '/admin/settings'));
        exit;
    });

    $router->post('/admin/search/reindex', static function (Request $request) {
        $result = app_service('controller.admin')->reindexSearch();
        if (!empty($result['flash'])) {
            $_SESSION['admin_flash'] = $result['flash'];
        }
        header('Location: ' . ($result['redirect'] ?? '/admin'));
        exit;
    });

    // Auth routes
    $router->get('/auth', static function (Request $request) {
        $view = app_service('controller.auth')->landing();
        app_render('auth-landing.php', array_merge($view, ['page_title' => 'Sign In or Register']), 'guest');
        return true;
    });

    $router->post('/auth/login', static function (Request $request) {
        $view = app_service('controller.auth')->login();
        if (isset($view['redirect'])) {
            header('Location: ' . $view['redirect']);
            exit;
        }
        app_render('auth-landing.php', array_merge($view, ['page_title' => 'Sign In']), 'guest');
        return true;
    });

    $router->post('/auth/register', static function (Request $request) {
        $view = app_service('controller.auth')->register();
        if (isset($view['redirect'])) {
            header('Location: ' . $view['redirect']);
            exit;
        }
        app_render('auth-landing.php', array_merge($view, ['page_title' => 'Register']), 'guest');
        return true;
    });

    $router->post('/auth/logout', static function (Request $request) {
        $result = app_service('controller.auth')->logout();
        header('Location: ' . ($result['redirect'] ?? '/auth'));
        exit;
    });

    $router->get('/logout', function () {
        app_service('controller.auth')->logout();
        header('Location: /auth');
        exit;
    });


    // Password Reset
    $router->get('/reset-password', static function (Request $request) {
        $view = app_service('controller.auth')->requestReset();
        app_render('password-reset-request.php', array_merge($view, ['page_title' => 'Reset Password']), 'guest');
        return true;
    });

    $router->post('/reset-password', static function (Request $request) {
        $result = app_service('controller.auth')->sendResetEmail();
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
        app_render('password-reset-request.php', $data, 'guest');
        return true;
    });

    $router->get('/reset-password/{token}', static function (Request $request, string $token) {
        $view = app_service('controller.auth')->showResetForm($token);
        if (!$view['valid']) {
            http_response_code(400);
            app_render('password-reset-error.php', [
                'page_title' => 'Reset Password Error',
                'error' => $view['error'] ?? 'Invalid or expired token.'
            ], 'guest');
            return true;
        }
        app_render('password-reset-form.php', [
            'page_title' => 'Reset Password',
            'token' => $view['token'],
            'errors' => []
        ], 'guest');
        return true;
    });

    $router->post('/reset-password/{token}', static function (Request $request, string $token) {
        $result = app_service('controller.auth')->processReset($token);
        if (isset($result['redirect'])) {
            $_SESSION['flash_message'] = $result['message'] ?? 'Password reset successfully.';
            header('Location: ' . $result['redirect']);
            exit;
        }
        app_render('password-reset-form.php', [
            'page_title' => 'Reset Password',
            'errors' => $result['errors'] ?? [],
            'token' => $result['token'] ?? $token
        ], 'guest');
        return true;
    });

    // Email Verification
    $router->get('/verify-email/{token}', static function (Request $request, string $token) {
        $result = app_service('controller.auth')->verifyEmail($token);
        if ($result['success']) {
            $_SESSION['flash_message'] = $result['message'] ?? 'Email verified successfully.';
            header('Location: ' . ($result['redirect'] ?? '/'));
            exit;
        }
        app_render('email-verification-error.php', [
            'page_title' => 'Email Verification Error',
            'errors' => $result['errors'] ?? ['token' => 'Verification failed.']
        ], 'guest');
        return true;
    });

    // Profile Routes
    $router->get('/profile', static function (Request $request) {
        $result = app_service('controller.profile')->showOwn();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (isset($result['error'])) {
            header('Location: /auth');
            exit;
        }
        return true;
    });

    // IMPORTANT: /profile/edit must come BEFORE /profile/{username} to avoid treating "edit" as a username
    $router->get('/profile/edit', static function (Request $request) {
        $result = app_service('controller.profile')->edit();
        if (isset($result['error'])) {
            header('Location: /auth');
            exit;
        }

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildProfileTabs($result['user'], $viewer, '/profile/edit');

        app_render('profile-edit.php', [
            'page_title' => 'Edit Profile',
            'page_description' => 'Update your profile information, avatar, and settings',
            'user' => $result['user'],
            'errors' => $result['errors'],
            'input' => $result['input'],
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar
        ], 'two-column');
        return true;
    });

    $router->get('/profile/{username}', static function (Request $request, string $username) {
        $result = app_service('controller.profile')->show($username);

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildProfileTabs($result['user'], $viewer, '/profile/' . $username);

        $profileDescription = $result['user'] && !empty($result['user']['bio'])
            ? substr(strip_tags($result['user']['bio']), 0, 160)
            : ($result['user'] ? 'View ' . e($result['user']['display_name'] ?? $result['user']['username']) . '\'s profile and activity' : 'User profile');

        app_render('profile-view.php', [
            'page_title' => $result['user'] ? e($result['user']['display_name'] ?? $result['user']['username']) . ' - Profile' : 'User Not Found',
            'page_description' => $profileDescription,
            'user' => $result['user'],
            'is_own_profile' => $result['is_own_profile'],
            'stats' => $result['stats'],
            'recent_activity' => $result['recent_activity'],
            'error' => $result['error'] ?? null,
            'success' => isset($_GET['updated']) ? 'Profile updated successfully!' : null,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar
        ], 'two-column');
        return true;
    });

    $router->post('/profile/update', static function (Request $request) {
        try {
            $result = app_service('controller.profile')->update($request);
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
            $viewer = app_service('auth.service')->getCurrentUser();
            include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
            $sidebar = ob_get_clean();

            $navService = app_service('navigation.service');
            $tabs = $navService->buildProfileTabs($result['user'], $viewer, '/profile/edit');

            app_render('profile-edit.php', [
                'page_title' => 'Edit Profile',
                'user' => $result['user'],
                'errors' => $result['errors'] ?? [],
                'input' => $result['input'] ?? [],
                'nav_items' => $tabs,
                'sidebar_content' => $sidebar
            ], 'two-column');
            return true;
        } catch (\Throwable $e) {
            error_log("Profile update route error: " . $e->getMessage());
            http_response_code(500);
            echo "An error occurred while updating your profile. Please try again.";
            exit;
        }
    });

    // Bluesky Connection
    $router->post('/connect/bluesky', static function (Request $request) {
        $result = app_service('controller.bluesky')->connect();
        header('Location: ' . ($result['redirect'] ?? '/profile/edit'));
        exit;
    });

    $router->post('/disconnect/bluesky', static function (Request $request) {
        $result = app_service('controller.bluesky')->disconnect();
        header('Location: ' . ($result['redirect'] ?? '/profile/edit'));
        exit;
    });

    // API: Bluesky
    $router->post('/api/bluesky/sync', static function (Request $request) {
        $response = app_service('controller.bluesky')->syncFollowers();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->get('/api/bluesky/followers', static function (Request $request) {
        $response = app_service('controller.bluesky')->followers();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    // API: Conversations
    $router->post('/api/conversations', static function (Request $request) {
        $response = app_service('controller.conversations.api')->list();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/conversations/{slug}/replies', static function (Request $request, string $slug) {
        $response = app_service('controller.conversations.api')->reply($slug);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/replies/{id}/edit', static function (Request $request, string $id) {
        header('Content-Type: application/json');
        $replyId = (int)$id;
        $conversationService = app_service('conversation.service');
        $authService = app_service('auth.service');
        $securityService = app_service('security.service');

        try {
            // Get current user first
            $currentUser = $authService->getCurrentUser();
            $currentUserId = (int)($currentUser->id ?? 0);

            // Verify nonce
            $nonce = $request->input('nonce');
            if (!$securityService->verifyNonce($nonce, 'app_nonce', $currentUserId)) {
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
            if ($currentUserId !== (int)($reply['author_id'] ?? 0)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                return true;
            }

            // Prepare update data
            $content = $request->input('content');
            $updateData = ['content' => $content];

            // Check for image upload
            $hasImage = !empty($_FILES['reply_image']) && !empty($_FILES['reply_image']['tmp_name']);
            if ($hasImage) {
                $imageAlt = trim((string)$request->input('image_alt', ''));
                if ($imageAlt === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Image alt-text is required for accessibility.']);
                    return true;
                }
                $updateData['image'] = $_FILES['reply_image'];
                $updateData['image_alt'] = $imageAlt;
            } else {
                // If no new image, preserve existing alt text
                $imageAlt = trim((string)$request->input('image_alt', ''));
                if ($imageAlt !== '') {
                    $updateData['image_alt'] = $imageAlt;
                }
            }

            // Update reply
            $conversationService->updateReply($replyId, $updateData);

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

            $conversationService = app_service('conversation.service');
            $authService = app_service('auth.service');
            $securityService = app_service('security.service');

            // Get current user first
            $currentUser = $authService->getCurrentUser();
            $currentUserId = (int)($currentUser->id ?? 0);

            // Verify nonce
            $nonce = $request->input('nonce');
            if (!$securityService->verifyNonce($nonce, 'app_nonce', $currentUserId)) {
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
        $response = app_service('controller.communities.api')->join((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    // API: Invitations
    $router->post('/api/invitations/accept', static function (Request $request) {
        $response = app_service('controller.invitations')->accept();
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/{type}/{id}/invitations', static function (Request $request, string $type, string $id) {
        $entityId = (int)$id;
        $controller = app_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->sendCommunity($entityId)
            : $controller->sendEvent($entityId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/communities/{communityId}/invitations/{invitationId}/resend', static function (Request $request, string $communityId, string $invitationId) {
        $response = app_service('controller.invitations')->resendCommunity((int)$communityId, (int)$invitationId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->get('/api/events/{id}/guests', static function (Request $request, string $id) {
        $eventId = (int)$id;
        $controller = app_service('controller.invitations');
        $response = $controller->listEvent($eventId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->get('/api/{type}/{id}/invitations', static function (Request $request, string $type, string $id) {
        $entityId = (int)$id;
        $controller = app_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->listCommunity($entityId)
            : $controller->listEvent($entityId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/events/{eventId}/invitations/{invitationId}/resend', static function (Request $request, string $eventId, string $invitationId) {
        $response = app_service('controller.invitations')->resendEvent((int)$eventId, (int)$invitationId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->delete('/api/{type}/{entityId}/invitations/{invitationId}', static function (Request $request, string $type, string $entityId, string $invitationId) {
        $controller = app_service('controller.invitations');
        $response = $type === 'communities'
            ? $controller->deleteCommunity((int)$entityId, (int)$invitationId)
            : $controller->deleteEvent((int)$entityId, (int)$invitationId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->get('/api/search', static function (Request $request) {
        $response = app_service('controller.search')->search($request);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

// API: Bluesky Invitations
    
    $router->post('/api/invitations/bluesky/event/{id}', static function (Request $request, string $id) {
        $response = app_service('controller.bluesky.invitation')->inviteEvent((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/invitations/bluesky/community/{id}', static function (Request $request, string $id) {
        $response = app_service('controller.bluesky.invitation')->inviteCommunity((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->get('/api/communities/{id}/members', static function (Request $request, string $id) {
        $response = app_service('controller.invitations')->listCommunityMembers((int)$id);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->post('/api/communities/{communityId}/members/{memberId}/role', static function (Request $request, string $communityId, string $memberId) {
        $response = app_service('controller.invitations')->updateCommunityMemberRole((int)$communityId, (int)$memberId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

    $router->delete('/api/communities/{communityId}/members/{memberId}', static function (Request $request, string $communityId, string $memberId) {
        $response = app_service('controller.invitations')->removeCommunityMember((int)$communityId, (int)$memberId);
        http_response_code($response['status'] ?? 200);
        header('Content-Type: application/json');
        echo json_encode($response['body']);
        return true;
    });

// Events
    $router->get('/events', static function (Request $request) {
        $view = app_service('controller.events')->index();
        $filter = $view['filter'] ?? 'all';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        app_render('events-list.php', array_merge($view, [
            'page_title' => 'Events',
            'page_description' => 'Browse upcoming events and gatherings in your community',
            'nav_items' => [
                ['title' => 'All', 'url' => '/events?filter=all', 'active' => $filter === 'all'],
                ['title' => 'My Events', 'url' => '/events?filter=my', 'active' => $filter === 'my'],
            ],
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/events/create', static function (Request $request) {
        $view = app_service('controller.events')->create();
        app_render('event-create.php', array_merge($view, ['page_title' => 'Create Event']), 'form');
        return true;
    });

    $router->post('/events/create', static function (Request $request) {
        $result = app_service('controller.events')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        app_render('event-create.php', array_merge($result, ['page_title' => 'Create Event']), 'form');
        return true;
    });

    $router->get('/events/{slug}/edit', static function (Request $request, string $slug) {
        $view = app_service('controller.events')->edit($slug);
        if ($view['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return true;
        }
        app_render('event-edit.php', array_merge($view, ['page_title' => 'Edit Event']), 'form');
        return true;
    });

    $router->get('/events/{slug}/manage', static function (Request $request, string $slug) {
        $tab = $request->query('tab');

        // If no tab specified, redirect to event view page
        if (!$tab) {
            header('Location: /events/' . $slug);
            exit;
        }

        $view = app_service('controller.events')->manage($slug);
        $status = $view['status'] ?? 200;
        if ($status !== 200) {
            http_response_code($status);
        }
        $eventTitle = $view['event']['title'] ?? 'Event';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $currentUri = '/events/' . $slug . '/manage?tab=' . $tab;
        $tabs = $navService->buildEventManageTabs($view['event'], $currentUri);

        app_render('event-manage.php', array_merge($view, [
            'page_title' => 'Manage ' . $eventTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->post('/events/{slug}/edit', static function (Request $request, string $slug) {
        $result = app_service('controller.events')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['event']) || $result['event'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return true;
        }
        app_render('event-edit.php', array_merge($result, ['page_title' => 'Edit Event']), 'form');
        return true;
    });

    $router->post('/events/{slug}/delete', static function (Request $request, string $slug) {
        $result = app_service('controller.events')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->get('/events/{slug}', static function (Request $request, string $slug) {
        $view = app_service('controller.events')->show($slug);
        $eventTitle = $view['event']['title'] ?? 'Event';
        $eventDescription = !empty($view['event']['description'])
            ? substr(strip_tags($view['event']['description']), 0, 160)
            : 'View event details, RSVP, and connect with attendees';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildEventTabs($view['event'], $viewer, '/events/' . $slug);

        app_render('event-detail.php', array_merge($view, [
            'page_title' => $eventTitle,
            'page_description' => $eventDescription,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/events/{slug}/conversations', static function (Request $request, string $slug) {
        $view = app_service('controller.events')->conversations($slug);
        $eventTitle = $view['event']['title'] ?? 'Event';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildEventTabs($view['event'], $viewer, '/events/' . $slug . '/conversations');

        app_render('event-conversations.php', array_merge($view, [
            'page_title' => 'Conversations - ' . $eventTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    // Communities
    $router->get('/communities', static function (Request $request) {
        $view = app_service('controller.communities')->index();
        $circle = $view['circle'] ?? 'all';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        app_render('communities-list.php', array_merge($view, [
            'page_title' => 'Communities',
            'page_description' => 'Discover and join communities based on shared interests',
            'nav_items' => [
                ['title' => 'All', 'url' => '/communities?circle=all', 'active' => $circle === 'all'],
                ['title' => 'Inner', 'url' => '/communities?circle=inner', 'active' => $circle === 'inner'],
                ['title' => 'Trusted', 'url' => '/communities?circle=trusted', 'active' => $circle === 'trusted'],
                ['title' => 'Extended', 'url' => '/communities?circle=extended', 'active' => $circle === 'extended'],
            ],
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/communities/create', static function (Request $request) {
        $view = app_service('controller.communities')->create();
        app_render('community-create.php', array_merge($view, ['page_title' => 'Create Community']), 'form');
        return true;
    });

    $router->post('/communities/create', static function (Request $request) {
        $result = app_service('controller.communities')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        app_render('community-create.php', array_merge($result, ['page_title' => 'Create Community']), 'form');
        return true;
    });

    $router->get('/communities/{slug}/edit', static function (Request $request, string $slug) {
        $view = app_service('controller.communities')->edit($slug);
        if ($view['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return true;
        }
        app_render('community-edit.php', array_merge($view, ['page_title' => 'Edit Community']), 'form');
        return true;
    });

    $router->get('/communities/{slug}/manage', static function (Request $request, string $slug) {
        $tab = $request->query('tab');

        // If no tab specified, redirect to community view page
        if (!$tab) {
            header('Location: /communities/' . $slug);
            exit;
        }

        $view = app_service('controller.communities')->manage($slug);
        $status = $view['status'] ?? 200;
        if ($status !== 200) {
            http_response_code($status);
        }
        $communityTitle = $view['community']['title'] ?? $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $currentUri = '/communities/' . $slug . '/manage?tab=' . $tab;
        $tabs = $navService->buildCommunityManageTabs($view['community'], $currentUri);

        app_render('community-manage.php', array_merge($view, [
            'page_title' => 'Manage ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->post('/communities/{slug}/edit', static function (Request $request, string $slug) {
        $result = app_service('controller.communities')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['community']) || $result['community'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return true;
        }
        app_render('community-edit.php', array_merge($result, ['page_title' => 'Edit Community']), 'form');
        return true;
    });

    $router->post('/communities/{slug}/delete', static function (Request $request, string $slug) {
        $result = app_service('controller.communities')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->get('/communities/{slug}', static function (Request $request, string $slug) {
        $view = app_service('controller.communities')->show($slug);
        $status = (int)($view['status'] ?? ($view['community'] === null ? 404 : 200));
        if ($status !== 200) {
            http_response_code($status);
        }
        $communityTitle = $view['community']['title'] ?? $view['community']['name'] ?? 'Community';
        $communityDescription = !empty($view['community']['description'])
            ? substr(strip_tags($view['community']['description']), 0, 160)
            : 'Join this community to connect with members and participate in events';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug);

        app_render('community-detail.php', array_merge($view, [
            'page_title' => $communityTitle,
            'page_description' => $communityDescription,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/communities/{slug}/events', static function (Request $request, string $slug) {
        $view = app_service('controller.communities')->events($slug);
        $communityTitle = $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug . '/events');

        app_render('community-events.php', array_merge($view, [
            'page_title' => 'Events - ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/communities/{slug}/conversations', static function (Request $request, string $slug) {
        $view = app_service('controller.communities')->conversations($slug);
        $communityTitle = $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug . '/conversations');

        app_render('community-conversations.php', array_merge($view, [
            'page_title' => 'Conversations - ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/communities/{slug}/members', static function (Request $request, string $slug) {
        $view = app_service('controller.communities')->members($slug);
        $communityTitle = $view['community']['name'] ?? 'Community';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildCommunityTabs($view['community'], $viewer, '/communities/' . $slug . '/members');

        app_render('community-members.php', array_merge($view, [
            'page_title' => 'Members - ' . $communityTitle,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    // Conversations
    $router->get('/conversations', static function (Request $request) {
        $view = app_service('controller.conversations')->index();
        $circle = $view['circle'] ?? 'all';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        app_render('conversations-list.php', array_merge($view, [
            'page_title' => 'Conversations',
            'page_description' => 'Join discussions and connect with community members',
            'nav_items' => [
                ['title' => 'All', 'url' => '/conversations?circle=all', 'active' => $circle === 'all'],
                ['title' => 'Inner', 'url' => '/conversations?circle=inner', 'active' => $circle === 'inner'],
                ['title' => 'Trusted', 'url' => '/conversations?circle=trusted', 'active' => $circle === 'trusted'],
                ['title' => 'Extended', 'url' => '/conversations?circle=extended', 'active' => $circle === 'extended'],
            ],
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/conversations/create', static function (Request $request) {
        $view = app_service('controller.conversations')->create();
        app_render('conversation-create.php', array_merge($view, ['page_title' => 'New Conversation']), 'form');
        return true;
    });

    $router->post('/conversations/create', static function (Request $request) {
        $result = app_service('controller.conversations')->store();
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        app_render('conversation-create.php', array_merge($result, ['page_title' => 'New Conversation']), 'form');
        return true;
    });

    $router->get('/conversations/{slug}/edit', static function (Request $request, string $slug) {
        $view = app_service('controller.conversations')->edit($slug);
        if ($view['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return true;
        }

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildConversationTabs($view['conversation'], $viewer, '/conversations/' . $slug . '/edit');

        app_render('conversation-edit.php', array_merge($view, [
            'page_title' => 'Edit Conversation',
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->post('/conversations/{slug}/edit', static function (Request $request, string $slug) {
        $result = app_service('controller.conversations')->update($slug);
        if (isset($result['redirect'])) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        if (!isset($result['conversation']) || $result['conversation'] === null) {
            http_response_code(404);
            echo 'Not Found';
            return true;
        }
        app_render('conversation-edit.php', array_merge($result, ['page_title' => 'Edit Conversation']), 'form');
        return true;
    });

    $router->post('/conversations/{slug}/delete', static function (Request $request, string $slug) {
        $result = app_service('controller.conversations')->destroy($slug);
        header('Location: ' . $result['redirect']);
        exit;
    });

    $router->post('/conversations/{slug}/reply', static function (Request $request, string $slug) {
        try {
            $result = app_service('controller.conversations')->reply($slug);
            if (isset($result['redirect'])) {
                header('Location: ' . $result['redirect']);
                exit;
            }
            $conversationTitle = $result['conversation']['title'] ?? 'Conversation';

            ob_start();
            $viewer = app_service('auth.service')->getCurrentUser();
            include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
            $sidebar = ob_get_clean();

            $navService = app_service('navigation.service');
            $tabs = $navService->buildConversationTabs($result['conversation'], $viewer, '/conversations/' . $slug);

            app_render('conversation-detail.php', array_merge($result, [
                'page_title' => $conversationTitle,
                'nav_items' => $tabs,
                'sidebar_content' => $sidebar,
            ]), 'two-column');
            return true;
        } catch (\Throwable $e) {
            error_log("Reply route error: " . $e->getMessage());
            http_response_code(500);
            echo "An error occurred while posting your reply. Please try again.";
            exit;
        }
    });

    $router->get('/conversations/{slug}', static function (Request $request, string $slug) {
        $view = app_service('controller.conversations')->show($slug);
        $conversationTitle = $view['conversation']['title'] ?? 'Conversation';
        $conversationDescription = !empty($view['conversation']['content'])
            ? substr(strip_tags($view['conversation']['content']), 0, 160)
            : 'Join the discussion and share your thoughts';

        ob_start();
        $viewer = app_service('auth.service')->getCurrentUser();
        include dirname(__DIR__, 2) . '/templates/partials/sidebar-secondary-nav.php';
        $sidebar = ob_get_clean();

        $navService = app_service('navigation.service');
        $tabs = $navService->buildConversationTabs($view['conversation'], $viewer, '/conversations/' . $slug);

        app_render('conversation-detail.php', array_merge($view, [
            'page_title' => $conversationTitle,
            'page_description' => $conversationDescription,
            'nav_items' => $tabs,
            'sidebar_content' => $sidebar,
        ]), 'two-column');
        return true;
    });

    $router->get('/rsvp/{token}', static function (Request $request, string $token) {
        $invitationService = app_service('invitation.service');
        $security = app_service('security.service');

        $result = $invitationService->getEventInvitationByToken($token);

        if (!$result['success']) {
            app_render('guest-rsvp.php', [
                'page_title' => 'RSVP Invitation',
                'page_description' => 'Respond to your event invitation',
                'error_message' => $result['message'],
                'token' => $token,
                'nonce' => $security->createNonce('guest_rsvp'),
            ], 'form');
            return true;
        }

        $data = $result['data'];
        $guest = $data['guest'];
        $event = $data['event'];
        $isBluesky = (bool)($data['is_bluesky'] ?? false);

        $quickResponse = strtolower((string)$request->query('response', ''));
        $preselect = '';
        if (in_array($quickResponse, ['yes', 'no', 'maybe'], true) && ($guest['status'] ?? 'pending') === 'pending') {
            $preselect = $quickResponse;
        }

        $formValues = [
            'guest_name' => $guest['name'] ?? '',
            'guest_phone' => $guest['phone'] ?? '',
            'dietary_restrictions' => $guest['dietary_restrictions'] ?? '',
            'guest_notes' => $guest['notes'] ?? '',
            'plus_one' => (int)($guest['plus_one'] ?? 0),
            'plus_one_name' => $guest['plus_one_name'] ?? '',
        ];

        app_render('guest-rsvp.php', [
            'page_title' => $event['title'] !== '' ? 'RSVP: ' . $event['title'] : 'RSVP Invitation',
            'page_description' => 'Let the host know if you can make it.',
            'event' => $event,
            'guest' => $guest,
            'token' => $token,
            'preselect' => $preselect,
            'is_bluesky' => $isBluesky,
            'form_values' => $formValues,
            'errors' => [],
            'success_message' => '',
            'nonce' => $security->createNonce('guest_rsvp'),
        ], 'form');
        return true;
    });

    $router->post('/rsvp/{token}', static function (Request $request, string $token) {
        $invitationService = app_service('invitation.service');
        $security = app_service('security.service');

        $initial = $invitationService->getEventInvitationByToken($token);
        if (!$initial['success']) {
            app_render('guest-rsvp.php', [
                'page_title' => 'RSVP Invitation',
                'page_description' => 'Respond to your event invitation',
                'error_message' => $initial['message'],
                'token' => $token,
                'nonce' => $security->createNonce('guest_rsvp'),
            ], 'form');
            return true;
        }

        $data = $initial['data'];
        $event = $data['event'];
        $guest = $data['guest'];
        $isBluesky = (bool)($data['is_bluesky'] ?? false);

        $errors = [];
        $successMessage = '';

        $statusInput = strtolower(trim((string)$request->input('rsvp_status', '')));
        $input = [
            'guest_name' => (string)$request->input('guest_name', ''),
            'guest_phone' => (string)$request->input('guest_phone', ''),
            'dietary_restrictions' => (string)$request->input('dietary_restrictions', ''),
            'guest_notes' => (string)$request->input('guest_notes', ''),
            'plus_one' => (int)$request->input('plus_one', 0),
            'plus_one_name' => (string)$request->input('plus_one_name', ''),
        ];

        $nonce = (string)$request->input('nonce', '');
        if (!$security->verifyNonce($nonce, 'guest_rsvp', 0)) {
            $errors[] = 'Security verification failed. Please refresh and try again.';
        }

        if ($statusInput === '') {
            $errors[] = 'Please choose an RSVP option.';
        }

        if ($errors === []) {
            $response = $invitationService->respondToEventInvitation($token, $statusInput, $input);
            if ($response['success']) {
                $responseData = $response['data'];
                $guest = $responseData['guest'];
                $event = $responseData['event'];
                $isBluesky = \str_starts_with(strtolower((string)($guest['email'] ?? '')), 'bsky:');
                $successMessage = (string)($responseData['message'] ?? 'RSVP updated.');

                $input = [
                    'guest_name' => $guest['name'] ?? '',
                    'guest_phone' => $guest['phone'] ?? '',
                    'dietary_restrictions' => $guest['dietary_restrictions'] ?? '',
                    'guest_notes' => $guest['notes'] ?? '',
                    'plus_one' => (int)($guest['plus_one'] ?? 0),
                    'plus_one_name' => $guest['plus_one_name'] ?? '',
                ];

                $currentStatus = strtolower((string)($guest['status'] ?? 'pending'));
                $statusInput = match ($currentStatus) {
                    'confirmed' => 'yes',
                    'declined' => 'no',
                    'maybe' => 'maybe',
                    default => $statusInput,
                };
            } else {
                $errors[] = $response['message'];
            }
        }

        $formValues = $input;
        $preselect = $statusInput;

        app_render('guest-rsvp.php', [
            'page_title' => $event['title'] !== '' ? 'RSVP: ' . $event['title'] : 'RSVP Invitation',
            'page_description' => 'Let the host know if you can make it.',
            'event' => $event,
            'guest' => $guest,
            'token' => $token,
            'preselect' => $preselect,
            'is_bluesky' => $isBluesky,
            'form_values' => $formValues,
            'errors' => $errors,
            'success_message' => $successMessage,
            'nonce' => $security->createNonce('guest_rsvp'),
        ], 'form');
        return true;
    });

$router->get('/invitation/accept', static function (Request $request) {
    $token = (string)$request->query('token', '');

    if ($token === '') {
        http_response_code(400);
        app_render('invitation-accept.php', [
            'page_title' => 'Join Invitation',
            'success' => false,
            'message' => 'Missing invitation token.',
            'status' => 400,
            'data' => [],
        ], 'guest');
        return true;
    }

    $controller = app_service('controller.invitations');
    $response = $controller->acceptToken($token);

    if (isset($response['redirect'])) {
        header('Location: ' . $response['redirect']);
        exit;
    }

    $status = (int)($response['status'] ?? 200);
    $body = $response['body'] ?? [];
    $success = (bool)($body['success'] ?? false);
    $data = $body['data'] ?? [];

    if ($success && isset($data['redirect_url'])) {
        header('Location: ' . $data['redirect_url']);
        exit;
    }

    http_response_code($status);
    $message = (string)($body['message'] ?? ($success ? 'Invitation accepted successfully.' : 'Unable to accept invitation.'));

    app_render('invitation-accept.php', [
        'page_title' => $success ? 'Invitation Accepted' : 'Join Invitation',
        'success' => $success,
        'message' => $message,
        'status' => $status,
        'data' => $data,
    ], 'guest');

    return true;
});

};
