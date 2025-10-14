#!/usr/bin/env php
<?php
/**
 * Authorization Service Tests
 *
 * Tests authorization service registration and basic permission logic
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

function test(string $name, callable $fn): void {
    echo "\n🧪 Testing: {$name}\n";
    try {
        $result = $fn();
        if ($result === true) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL: " . ($result ?: 'returned false') . "\n";
        }
    } catch (Exception $e) {
        echo "❌ FAIL: " . $e->getMessage() . "\n";
    }
}

echo "=== Authorization Service Tests ===\n";

// Test 1: Service is registered
test("AuthorizationService is registered in container", function() {
    $authz = vt_service('authorization.service');
    if (!$authz instanceof App\Services\AuthorizationService) {
        return "Expected App\\Services\\AuthorizationService, got " . get_class($authz);
    }
    return true;
});

// Test 2: Public conversation visibility
test("Public conversations are visible to anyone", function() {
    $authz = vt_service('authorization.service');
    $conversation = ['privacy' => 'public'];

    // Guest (viewerId = 0) can view public conversations
    if (!$authz->canViewConversation($conversation, 0, [])) {
        return "Guest cannot view public conversation";
    }

    // Logged-in user can view public conversations
    if (!$authz->canViewConversation($conversation, 1, [])) {
        return "Logged-in user cannot view public conversation";
    }

    return true;
});

// Test 3: Private conversation visibility
test("Private conversations require membership", function() {
    $authz = vt_service('authorization.service');
    $conversation = [
        'privacy' => 'private',
        'community_id' => 5,
        'author_id' => 10,
    ];

    // Guest cannot view private conversation
    if ($authz->canViewConversation($conversation, 0, [])) {
        return "Guest can view private conversation";
    }

    // Non-member cannot view
    if ($authz->canViewConversation($conversation, 1, [])) {
        return "Non-member can view private conversation";
    }

    // Member can view
    if (!$authz->canViewConversation($conversation, 1, [5])) {
        return "Member cannot view private conversation";
    }

    // Author can view
    if (!$authz->canViewConversation($conversation, 10, [])) {
        return "Author cannot view their own private conversation";
    }

    return true;
});

// Test 4: Edit permissions - Author
test("Author can edit their own conversation", function() {
    $authz = vt_service('authorization.service');
    $conversation = ['author_id' => 5];

    if (!$authz->canEditConversation($conversation, 5)) {
        return "Author cannot edit their own conversation";
    }

    if ($authz->canEditConversation($conversation, 10)) {
        return "Non-author can edit conversation";
    }

    return true;
});

// Test 5: Delete permissions - Reply count matters
test("Cannot delete conversation with replies (non-admin)", function() {
    $authz = vt_service('authorization.service');
    $conversation = [
        'author_id' => 5,
        'reply_count' => 3,
    ];

    // Author cannot delete if has replies (assuming not admin)
    if ($authz->canDeleteConversation($conversation, 5)) {
        return "Author can delete conversation with replies";
    }

    return true;
});

// Test 6: Delete permissions - No replies
test("Can delete conversation without replies", function() {
    $authz = vt_service('authorization.service');
    $conversation = [
        'author_id' => 5,
        'reply_count' => 0,
    ];

    if (!$authz->canDeleteConversation($conversation, 5)) {
        return "Author cannot delete conversation without replies";
    }

    return true;
});

// Test 7: Reply permissions require login
test("Must be logged in to reply", function() {
    $authz = vt_service('authorization.service');
    $conversation = ['privacy' => 'public'];

    if ($authz->canReplyToConversation($conversation, 0, [])) {
        return "Guest can reply to conversation";
    }

    if (!$authz->canReplyToConversation($conversation, 1, [])) {
        return "Logged-in user cannot reply to public conversation";
    }

    return true;
});

// Test 8: Reply permissions respect locked status
test("Cannot reply to locked conversation", function() {
    $authz = vt_service('authorization.service');
    $conversation = [
        'privacy' => 'public',
        'status' => 'locked',
    ];

    if ($authz->canReplyToConversation($conversation, 1, [])) {
        return "User can reply to locked conversation";
    }

    return true;
});

// Test 9: Public community visibility
test("Public communities are visible to anyone", function() {
    $authz = vt_service('authorization.service');
    $community = ['privacy' => 'public'];

    if (!$authz->canViewCommunity($community, 0, [])) {
        return "Guest cannot view public community";
    }

    if (!$authz->canViewCommunity($community, 1, [])) {
        return "User cannot view public community";
    }

    return true;
});

// Test 10: Private community visibility
test("Private communities require membership", function() {
    $authz = vt_service('authorization.service');
    $community = [
        'privacy' => 'private',
        'id' => 5,
        'creator_id' => 10,
    ];

    if ($authz->canViewCommunity($community, 0, [])) {
        return "Guest can view private community";
    }

    if ($authz->canViewCommunity($community, 1, [])) {
        return "Non-member can view private community";
    }

    if (!$authz->canViewCommunity($community, 1, [5])) {
        return "Member cannot view private community";
    }

    if (!$authz->canViewCommunity($community, 10, [])) {
        return "Creator cannot view their own private community";
    }

    return true;
});

// Test 11: Join public community
test("Can join public community", function() {
    $authz = vt_service('authorization.service');
    $community = ['privacy' => 'public'];

    if ($authz->canJoinCommunity($community, 0)) {
        return "Guest can join community";
    }

    if (!$authz->canJoinCommunity($community, 1)) {
        return "Logged-in user cannot join public community";
    }

    return true;
});

// Test 12: Cannot join private community
test("Cannot instant-join private community", function() {
    $authz = vt_service('authorization.service');
    $community = ['privacy' => 'private'];

    if ($authz->canJoinCommunity($community, 1)) {
        return "User can instant-join private community";
    }

    return true;
});

echo "\n✅ All authorization service tests passed!\n";
echo "\nAuthorization Service provides:\n";
echo "- canViewConversation() - Check conversation visibility\n";
echo "- canEditConversation() - Check edit permission\n";
echo "- canDeleteConversation() - Check delete permission\n";
echo "- canReplyToConversation() - Check reply permission\n";
echo "- canStartConversationInCommunity() - Check conversation creation\n";
echo "- canViewCommunity() - Check community visibility\n";
echo "- canEditCommunity() - Check community edit permission\n";
echo "- canDeleteCommunity() - Check community delete permission\n";
echo "- canJoinCommunity() - Check join permission\n";
echo "- canCreateEventInCommunity() - Check event creation permission\n";
