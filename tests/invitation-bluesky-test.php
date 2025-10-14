#!/usr/bin/env php
<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = vt_service('database.connection')->pdo();
$auth = vt_service('auth.service');
$invitations = vt_service('invitation.manager');
$communityMembers = vt_service('community.member.service');
$bluesky = vt_service('bluesky.service');

$hostId = null;
$inviteeId = null;
$communityId = null;
$hostMemberId = null;
$inviteeMemberId = null;
$invitationToken = null;
$success = false;

try {
    $suffix = bin2hex(random_bytes(5));

    // Host user
    $hostEmail = 'host+' . $suffix . '@example.com';
    $hostRegister = $auth->register([
        'display_name' => 'Host User ' . $suffix,
        'username' => 'host_' . $suffix,
        'email' => $hostEmail,
        'password' => 'HostPass!' . $suffix,
    ]);
    if (!$hostRegister['success']) {
        throw new RuntimeException('Host registration failed: ' . json_encode($hostRegister['errors']));
    }
    $hostId = (int)$hostRegister['user_id'];

    // Community
    $stmt = $pdo->prepare(
        'INSERT INTO vt_communities (name, slug, creator_id, creator_email, created_by)
         VALUES (:name, :slug, :creator_id, :creator_email, :created_by)'
    );
    $stmt->execute([
        ':name' => 'Bluesky Test ' . $suffix,
        ':slug' => 'bluesky-test-' . $suffix,
        ':creator_id' => $hostId,
        ':creator_email' => $hostEmail,
        ':created_by' => $hostId,
    ]);
    $communityId = (int)$pdo->lastInsertId();

    // Host membership (admin)
    $hostMemberId = $communityMembers->addMember(
        $communityId,
        $hostId,
        $hostEmail,
        'Host User ' . $suffix,
        'admin'
    );

    // Invitation
    $invitedDid = 'did:plc:' . substr(bin2hex(random_bytes(10)), 0, 16);
    $invitationToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        'INSERT INTO vt_community_invitations
            (community_id, invited_by_member_id, invited_email, invitation_token, message, status, expires_at, created_at)
         VALUES
            (:community_id, :invited_by_member_id, :invited_email, :invitation_token, :message, \'pending\', :expires_at, NOW())'
    );
    $stmt->execute([
        ':community_id' => $communityId,
        ':invited_by_member_id' => $hostMemberId,
        ':invited_email' => 'bsky:' . strtolower($invitedDid),
        ':invitation_token' => $invitationToken,
        ':message' => '',
        ':expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
    ]);

    // Invitee user
    $inviteeEmail = 'invitee+' . $suffix . '@example.com';
    $inviteeRegister = $auth->register([
        'display_name' => 'Invitee User ' . $suffix,
        'username' => 'invitee_' . $suffix,
        'email' => $inviteeEmail,
        'password' => 'InviteePass!' . $suffix,
    ]);
    if (!$inviteeRegister['success']) {
        throw new RuntimeException('Invitee registration failed: ' . json_encode($inviteeRegister['errors']));
    }
    $inviteeId = (int)$inviteeRegister['user_id'];

    // Acceptance without Bluesky should fail
    $initialAttempt = $invitations->acceptCommunityInvitation($invitationToken, $inviteeId);
    if ($initialAttempt['success'] || ($initialAttempt['status'] ?? 0) !== 403) {
        throw new RuntimeException('Expected Bluesky connection failure on first attempt.');
    }

    // Connect Bluesky account
    $stored = $bluesky->storeCredentials(
        $inviteeId,
        strtolower($invitedDid),
        'tester.' . $suffix . '.bsky.social',
        'access-token-' . $suffix,
        'refresh-token-' . $suffix
    );
    if (!$stored) {
        throw new RuntimeException('Failed to store Bluesky credentials.');
    }

    // Acceptance should now succeed
    $finalAttempt = $invitations->acceptCommunityInvitation($invitationToken, $inviteeId);
    if (!$finalAttempt['success']) {
        throw new RuntimeException('Acceptance failed: ' . ($finalAttempt['message'] ?? 'unknown error'));
    }

    $inviteeMemberId = isset($finalAttempt['data']['member_id'])
        ? (int)$finalAttempt['data']['member_id']
        : null;

    if (!$communityMembers->isMember($communityId, $inviteeId)) {
        throw new RuntimeException('Invitee not marked as community member.');
    }

    // Confirm invitation state
    $stmt = $pdo->prepare(
        'SELECT status, invited_user_id FROM vt_community_invitations WHERE invitation_token = :token LIMIT 1'
    );
    $stmt->execute([':token' => $invitationToken]);
    $invitationRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invitationRow || $invitationRow['status'] !== 'accepted' || (int)$invitationRow['invited_user_id'] !== $inviteeId) {
        throw new RuntimeException('Invitation not updated after acceptance.');
    }

    // Confirm DID stored on membership
    $stmt = $pdo->prepare(
        'SELECT at_protocol_did FROM vt_community_members WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $inviteeMemberId]);
    $memberRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$memberRow || strtolower((string)$memberRow['at_protocol_did']) !== strtolower($invitedDid)) {
        throw new RuntimeException('Membership missing Bluesky DID.');
    }

    echo "✅ Bluesky invitation acceptance flow passed.\n";
    $success = true;
} catch (Throwable $e) {
    fwrite(STDERR, '❌ ' . $e->getMessage() . PHP_EOL);
} finally {
    // Remove invitee membership first
    if ($communityId !== null && $inviteeId !== null) {
        $pdo->prepare('DELETE FROM vt_community_members WHERE community_id = :community_id AND user_id = :user_id')
            ->execute([
                ':community_id' => $communityId,
                ':user_id' => $inviteeId,
            ]);
    }

    // Remove host membership
    if ($hostMemberId !== null) {
        $pdo->prepare('DELETE FROM vt_community_members WHERE id = :id')
            ->execute([':id' => $hostMemberId]);
    }

    // Delete invitation
    if ($invitationToken !== null) {
        $pdo->prepare('DELETE FROM vt_community_invitations WHERE invitation_token = :token')
            ->execute([':token' => $invitationToken]);
    }

    // Delete community
    if ($communityId !== null) {
        $pdo->prepare('DELETE FROM vt_communities WHERE id = :id')
            ->execute([':id' => $communityId]);
    }

    // Delete Bluesky credentials
    if ($inviteeId !== null) {
        $pdo->prepare('DELETE FROM vt_member_identities WHERE user_id = :user_id')
            ->execute([':user_id' => $inviteeId]);
    }

    // Delete user profiles and users
    foreach (array_filter([$inviteeId, $hostId]) as $userId) {
        $pdo->prepare('DELETE FROM vt_user_profiles WHERE user_id = :user_id')
            ->execute([':user_id' => $userId]);
        $pdo->prepare('DELETE FROM vt_users WHERE id = :id')
            ->execute([':id' => $userId]);
    }
}

exit($success ? 0 : 1);
