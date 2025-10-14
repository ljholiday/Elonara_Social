<?php
/**
 * Create Comprehensive Test Data for Circles of Trust
 *
 * Network Structure:
 *
 * LONN (test user) →
 *   Inner Circle:
 *     - Member of "Viva Events" (creates conversations here)
 *     - Member of "SF Tech Meetup" (Alice's community)
 *
 *   Trusted Circle (Inner + communities created by Inner members):
 *     - Alice creates "Book Club" → Carol joins
 *     - Bob creates "Sports Team" → Dave joins
 *
 *   Extended Circle (Trusted + communities created by Trusted members):
 *     - Carol creates "Art Gallery" → Eve joins
 *     - Dave creates "Gaming Group" → Frank joins
 *
 * Expected Conversation Counts for Lonn:
 *   - Inner: 6 conversations (in Viva Events + SF Tech Meetup)
 *   - Trusted: 10 conversations (+4 from Book Club + Sports Team)
 *   - Extended: 14 conversations (+4 from Art Gallery + Gaming Group)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// Start session for authentication context
session_start();

// Helper to create user
function createTestUser($username, $email, $display_name, $password = 'password123') {
    echo "  Creating user: $username... ";
    flush();

    $auth = vt_service('auth.service');
    $db = VT_Database::getInstance();

    // Check if user exists
    $existing = $db->getRow(
        $db->prepare("SELECT id FROM vt_users WHERE username = %s", $username)
    );
    if ($existing) {
        echo "User $username already exists (ID: {$existing->id})\n";
        return $existing->id;
    }

    $user_data = [
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'display_name' => $display_name
    ];

    $user_id = $auth->register($user_data);
    if (is_vt_error($user_id)) {
        echo "FAILED\n";
        die("Error: " . $user_id['message'] . "\n");
    }

    echo "OK (ID: $user_id)\n";

    // Create personal community
    echo "    Creating personal community... ";
    flush();
    $pc_id = VT_Personal_Community_Service::ensurePersonalCommunityForUser($user_id);
    echo "OK (ID: $pc_id)\n";

    return $user_id;
}

// Helper to create community
function createTestCommunity($creator_id, $name, $description) {
    $manager = new VT_Community_Manager();
    $db = VT_Database::getInstance();

    // Check if community exists
    $slug = vt_service('validation.sanitizer')->slug($name);
    $existing = $db->getRow(
        $db->prepare("SELECT id FROM vt_communities WHERE slug = %s", $slug)
    );
    if ($existing) {
        echo "Community '$name' already exists (ID: {$existing->id})\n";
        return $existing->id;
    }

    $creator = $db->getRow(
        $db->prepare("SELECT * FROM vt_users WHERE id = %d", $creator_id)
    );

    $community_data = [
        'name' => $name,
        'description' => $description,
        'visibility' => 'public',
        'creator_email' => $creator->email
    ];

    // Temporarily set current user for creation
    $_SESSION['user_id'] = $creator_id;

    $community_id = $manager->createCommunity($community_data);
    if (is_vt_error($community_id)) {
        die("Failed to create community '$name': " . $community_id['message'] . "\n");
    }

    echo "Created community: $name (ID: $community_id)\n";
    return $community_id;
}

// Helper to add member to community
function addMemberToCommunity($community_id, $user_id, $role = 'member') {
    $manager = new VT_Community_Manager();
    $db = VT_Database::getInstance();

    // Check if already member
    $existing = $db->getRow(
        $db->prepare(
            "SELECT id FROM vt_community_members WHERE community_id = %d AND user_id = %d",
            $community_id,
            $user_id
        )
    );
    if ($existing) {
        echo "  User $user_id already member of community $community_id\n";
        return true;
    }

    $user = $db->getRow(
        $db->prepare("SELECT * FROM vt_users WHERE id = %d", $user_id)
    );

    $member_data = [
        'user_id' => $user_id,
        'email' => $user->email,
        'display_name' => $user->display_name,
        'role' => $role,
        'status' => 'active'
    ];

    $result = $manager->addMember($community_id, $member_data);
    if (is_vt_error($result)) {
        die("Failed to add user $user_id to community $community_id: " . $result['message'] . "\n");
    }

    echo "  Added user $user_id to community $community_id as $role\n";
    return true;
}

// Helper to create conversation
function createTestConversation($author_id, $community_id, $title, $content) {
    $manager = new VT_Conversation_Manager();
    $db = VT_Database::getInstance();

    $author = $db->getRow(
        $db->prepare("SELECT * FROM vt_users WHERE id = %d", $author_id)
    );

    $conversation_data = [
        'title' => $title,
        'content' => $content,
        'author_id' => $author_id,
        'author_name' => $author->display_name,
        'author_email' => $author->email,
        'community_id' => $community_id,
        'privacy' => 'public'
    ];

    $_SESSION['user_id'] = $author_id;

    $conversation_id = $manager->createConversation($conversation_data);
    if (is_vt_error($conversation_id)) {
        echo "  WARNING: Failed to create conversation '$title': " . $conversation_id['message'] . "\n";
        return false;
    }

    echo "  Created conversation: $title (ID: $conversation_id)\n";
    return $conversation_id;
}

echo "\n=== CREATING TEST DATA FOR CIRCLES OF TRUST ===\n\n";

// Create test users
echo "--- Creating Users ---\n";
$lonn = createTestUser('lonn', 'lonn@test.com', 'Lonn Holiday');
$alice = createTestUser('alice', 'alice@test.com', 'Alice Chen');
$bob = createTestUser('bob', 'bob@test.com', 'Bob Smith');
$carol = createTestUser('carol', 'carol@test.com', 'Carol Martinez');
$dave = createTestUser('dave', 'dave@test.com', 'Dave Johnson');
$eve = createTestUser('eve', 'eve@test.com', 'Eve Williams');
$frank = createTestUser('frank', 'frank@test.com', 'Frank Brown');

echo "\n--- Creating Communities ---\n";

// INNER CIRCLE communities for Lonn
$viva_events = createTestCommunity($lonn, 'Viva Events', 'Main event planning community');
addMemberToCommunity($viva_events, $alice, 'member');
addMemberToCommunity($viva_events, $bob, 'member');

$sf_tech = createTestCommunity($alice, 'SF Tech Meetup', 'San Francisco tech professionals');
addMemberToCommunity($sf_tech, $lonn, 'member');
addMemberToCommunity($sf_tech, $bob, 'member');

// TRUSTED CIRCLE communities (created by Alice & Bob)
$book_club = createTestCommunity($alice, 'Book Club', 'Monthly book discussions');
addMemberToCommunity($book_club, $carol, 'member');

$sports_team = createTestCommunity($bob, 'Sports Team', 'Weekend sports activities');
addMemberToCommunity($sports_team, $dave, 'member');

// EXTENDED CIRCLE communities (created by Carol & Dave)
$art_gallery = createTestCommunity($carol, 'Art Gallery', 'Local art and exhibitions');
addMemberToCommunity($art_gallery, $eve, 'member');

$gaming_group = createTestCommunity($dave, 'Gaming Group', 'Video game enthusiasts');
addMemberToCommunity($gaming_group, $frank, 'member');

echo "\n--- Creating Conversations ---\n";

// INNER CIRCLE conversations (6 total)
echo "Inner Circle Conversations:\n";
createTestConversation($lonn, $viva_events, 'Welcome to Viva Events', 'Let\'s plan amazing gatherings together!');
createTestConversation($alice, $viva_events, 'Summer BBQ Planning', 'Who wants to organize a summer BBQ?');
createTestConversation($bob, $viva_events, 'Pool Party Ideas', 'Thinking about hosting a pool party next month.');

createTestConversation($alice, $sf_tech, 'New Tech Trends', 'What tech trends are you following?');
createTestConversation($lonn, $sf_tech, 'Startup Opportunities', 'Anyone interested in startup ideas?');
createTestConversation($bob, $sf_tech, 'DevOps Best Practices', 'Let\'s discuss DevOps tooling.');

// TRUSTED CIRCLE conversations (4 more = 10 total)
echo "\nTrusted Circle Conversations:\n";
createTestConversation($alice, $book_club, 'This Month: Dune', 'What did everyone think of Dune?');
createTestConversation($carol, $book_club, 'Next Book Suggestions', 'What should we read next?');

createTestConversation($bob, $sports_team, 'Weekend Soccer Game', 'Who\'s up for soccer this weekend?');
createTestConversation($dave, $sports_team, 'Team Jersey Ideas', 'Should we get matching jerseys?');

// EXTENDED CIRCLE conversations (4 more = 14 total)
echo "\nExtended Circle Conversations:\n";
createTestConversation($carol, $art_gallery, 'Modern Art Exhibition', 'Check out the new modern art exhibit downtown.');
createTestConversation($eve, $art_gallery, 'Photography Workshop', 'Hosting a photography workshop next week.');

createTestConversation($dave, $gaming_group, 'New RPG Release', 'The new RPG just dropped, who\'s playing?');
createTestConversation($frank, $gaming_group, 'Gaming Tournament', 'Let\'s organize a tournament!');

echo "\n=== TEST DATA CREATION COMPLETE ===\n\n";

echo "Test User: lonn (ID: $lonn)\n";
echo "Expected Results:\n";
echo "  - Inner Circle: 6 conversations (Viva Events + SF Tech Meetup)\n";
echo "  - Trusted Circle: 10 conversations (+ Book Club + Sports Team)\n";
echo "  - Extended Circle: 14 conversations (+ Art Gallery + Gaming Group)\n\n";

echo "Login as 'lonn' with password 'password123' to test.\n";
