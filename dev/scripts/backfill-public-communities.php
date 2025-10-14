#!/usr/bin/env php
<?php
/**
 * Elonara Social - Backfill Public Communities Script
 *
 * This script migrates existing users from the old single personal community model
 * to the new two-community model:
 *
 * 1. Updates existing personal communities to be Circles (private, no apostrophe)
 * 2. Creates new public communities for each user
 *
 * Usage:
 *   php scripts/backfill-public-communities.php [--dry-run] [--batch-size=50]
 *
 * Options:
 *   --dry-run       Show what would be done without making changes
 *   --batch-size=N  Process N users at a time (default: 50)
 */

// Load Elonara Social bootstrap
require_once __DIR__ . '/../includes/bootstrap.php';

// Parse command line arguments
$options = getopt('', ['dry-run', 'batch-size:']);
$dry_run = isset($options['dry-run']);
$batch_size = isset($options['batch-size']) ? intval($options['batch-size']) : 50;

echo "Elonara Social - Two-Community Model Backfill\n";
echo "==========================================\n\n";

if ($dry_run) {
    echo "** DRY RUN MODE - No changes will be made **\n\n";
}

echo "Batch size: $batch_size users\n\n";

// Step 1: Fix existing personal communities
echo "Step 1: Fixing existing personal communities...\n";
echo "-----------------------------------------------\n";

if ($dry_run) {
    $db = VT_Database::getInstance();
    $communities_table = $db->prefix . 'communities';

    $old_communities = $db->getResults(
        "SELECT c.*, u.display_name
         FROM $communities_table c
         LEFT JOIN {$db->prefix}users u ON c.personal_owner_user_id = u.id
         WHERE (c.type = 'personal' OR c.name LIKE \"%'s %\")
         AND c.personal_owner_user_id IS NOT NULL
         AND c.is_active = 1"
    );

    echo "Found " . count($old_communities) . " old-style personal communities to fix:\n\n";

    foreach ($old_communities as $community) {
        $new_name = $community->display_name . ' Circle';
        $new_slug = 'circle-' . $community->personal_owner_user_id;

        echo "  Community ID {$community->id} (User {$community->personal_owner_user_id}):\n";
        echo "    Old: '{$community->name}' (type: {$community->type}, privacy: {$community->privacy})\n";
        echo "    New: '{$new_name}' (type: circle, privacy: private)\n";
        echo "    Slug: {$community->slug} → {$new_slug}\n\n";
    }
} else {
    $fixed = VT_Personal_Community_Service::fixLegacyPersonalCommunities();
    echo "Fixed $fixed personal communities (converted to Circles)\n\n";
}

// Step 2: Create missing public communities
echo "Step 2: Creating missing public communities...\n";
echo "-----------------------------------------------\n";

$db = VT_Database::getInstance();
$users_table = $db->prefix . 'users';
$communities_table = $db->prefix . 'communities';

// Get users who don't have a public community
$users_without_public = $db->getResults(
    "SELECT u.id, u.display_name
     FROM $users_table u
     WHERE u.status = 'active'
     AND NOT EXISTS (
         SELECT 1 FROM $communities_table c
         WHERE c.personal_owner_user_id = u.id
         AND c.type = 'public'
         AND c.is_active = 1
     )
     LIMIT $batch_size"
);

echo "Found " . count($users_without_public) . " users without public communities:\n\n";

if ($dry_run) {
    foreach ($users_without_public as $user) {
        echo "  User ID {$user->id} ({$user->display_name}):\n";
        echo "    Would create: '{$user->display_name}' (type: public, slug: pub-{$user->id})\n\n";
    }
} else {
    $created = 0;
    foreach ($users_without_public as $user) {
        $public_id = VT_Personal_Community_Service::createPublicCommunityForUser($user->id);
        if ($public_id) {
            $created++;
            echo "  ✓ Created public community for {$user->display_name} (ID: $public_id)\n";
        } else {
            echo "  ✗ Failed to create public community for {$user->display_name}\n";
        }
    }
    echo "\nCreated $created public communities\n\n";
}

// Step 3: Verification
echo "Step 3: Verification\n";
echo "--------------------\n";

$total_users = $db->getVar("SELECT COUNT(*) FROM $users_table WHERE status = 'active'");
$users_with_both = $db->getVar(
    "SELECT COUNT(DISTINCT u.id)
     FROM $users_table u
     WHERE u.status = 'active'
     AND EXISTS (
         SELECT 1 FROM $communities_table c1
         WHERE c1.personal_owner_user_id = u.id
         AND c1.type = 'circle'
         AND c1.is_active = 1
     )
     AND EXISTS (
         SELECT 1 FROM $communities_table c2
         WHERE c2.personal_owner_user_id = u.id
         AND c2.type = 'public'
         AND c2.is_active = 1
     )"
);

$users_with_circle_only = $db->getVar(
    "SELECT COUNT(DISTINCT u.id)
     FROM $users_table u
     WHERE u.status = 'active'
     AND EXISTS (
         SELECT 1 FROM $communities_table c1
         WHERE c1.personal_owner_user_id = u.id
         AND c1.type = 'circle'
         AND c1.is_active = 1
     )
     AND NOT EXISTS (
         SELECT 1 FROM $communities_table c2
         WHERE c2.personal_owner_user_id = u.id
         AND c2.type = 'public'
         AND c2.is_active = 1
     )"
);

$users_with_public_only = $db->getVar(
    "SELECT COUNT(DISTINCT u.id)
     FROM $users_table u
     WHERE u.status = 'active'
     AND NOT EXISTS (
         SELECT 1 FROM $communities_table c1
         WHERE c1.personal_owner_user_id = u.id
         AND c1.type = 'circle'
         AND c1.is_active = 1
     )
     AND EXISTS (
         SELECT 1 FROM $communities_table c2
         WHERE c2.personal_owner_user_id = u.id
         AND c2.type = 'public'
         AND c2.is_active = 1
     )"
);

$users_with_none = $total_users - $users_with_both - $users_with_circle_only - $users_with_public_only;

echo "Total active users: $total_users\n";
echo "  ✓ Users with BOTH communities: $users_with_both\n";
echo "  ⚠ Users with Circle only: $users_with_circle_only\n";
echo "  ⚠ Users with Public only: $users_with_public_only\n";
echo "  ✗ Users with NEITHER: $users_with_none\n\n";

if ($users_with_both == $total_users) {
    echo "✓ SUCCESS! All users now have both communities.\n\n";
} else {
    if ($dry_run) {
        echo "⚠ Run without --dry-run to apply changes.\n\n";
    } else {
        echo "⚠ Some users still missing communities. Run this script again to process more batches.\n\n";
    }
}

// Step 4: Show sample communities
if (!$dry_run && $users_with_both > 0) {
    echo "Step 4: Sample Communities\n";
    echo "--------------------------\n";

    $samples = $db->getResults(
        "SELECT c.*, u.display_name as owner_name
         FROM $communities_table c
         LEFT JOIN $users_table u ON c.personal_owner_user_id = u.id
         WHERE c.personal_owner_user_id IS NOT NULL
         AND c.is_active = 1
         ORDER BY c.created_at DESC
         LIMIT 5"
    );

    foreach ($samples as $sample) {
        $type_label = $sample->type === 'circle' ? 'Circle (Private)' : 'Public';
        echo "  {$sample->name} ({$type_label})\n";
        echo "    Owner: {$sample->owner_name}\n";
        echo "    Slug: /{$sample->slug}\n";
        echo "    Privacy: {$sample->privacy}\n\n";
    }
}

echo "Done!\n";
