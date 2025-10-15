#!/usr/bin/env php
<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$pdo = vt_service('database.connection')->pdo();
$invitationService = vt_service('invitation.manager');
$eventGuests = vt_service('event.guest.service');
$security = vt_service('security.service');
$auth = vt_service('auth.service');

$eventId = null;
$guestId = null;
$token = null;
$hostId = null;
$hostUserEmail = null;
$success = false;

try {
    $suffix = bin2hex(random_bytes(5));
    $token = $security->generateToken(32);

    // Create host user for attribution
    $hostUserEmail = 'host+' . $suffix . '@example.com';
    $register = $auth->register([
        'display_name' => 'Host ' . strtoupper($suffix),
        'username' => 'host_' . $suffix,
        'email' => $hostUserEmail,
        'password' => 'HostPass!' . $suffix,
    ]);
    if (!$register['success']) {
        throw new RuntimeException('Failed to register host user for test: ' . json_encode($register['errors']));
    }
    $hostId = (int)$register['user_id'];

    // Create event
    $stmt = $pdo->prepare("
        INSERT INTO events (
            title,
            slug,
            description,
            event_date,
            event_time,
            guest_limit,
            allow_plus_ones,
            author_id,
            post_id,
            created_by
        ) VALUES (
            :title,
            :slug,
            :description,
            NOW() + INTERVAL 7 DAY,
            '6:00 PM',
            25,
            1,
            :author_id,
            0,
            :created_by
        )
    ");
    $stmt->execute([
        ':title' => 'Bluesky Launch Party ' . strtoupper($suffix),
        ':slug' => 'bluesky-launch-' . $suffix,
        ':description' => 'Celebrate the launch with friends from Bluesky.',
        ':author_id' => $hostId,
        ':created_by' => $hostId,
    ]);
    $eventId = (int)$pdo->lastInsertId();

    // Create Bluesky guest invitation
    $blueskyDid = 'did:plc:' . substr(bin2hex(random_bytes(10)), 0, 16);
    $guestId = $eventGuests->createGuest(
        $eventId,
        'bsky:' . $blueskyDid,
        $token,
        '',
        'bluesky'
    );

    // Ensure invitation lookup works
    $lookup = $invitationService->getEventInvitationByToken($token);
    if (!$lookup['success']) {
        throw new RuntimeException('Failed to load RSVP invitation: ' . $lookup['message']);
    }

    $inviteData = $lookup['data'];
    if (($inviteData['guest']['status'] ?? '') !== 'pending') {
        throw new RuntimeException('Expected initial status to be pending.');
    }

    // Respond to invitation (Yes)
    $response = $invitationService->respondToEventInvitation($token, 'yes', [
        'guest_name' => 'Sky Guest ' . strtoupper($suffix),
        'guest_phone' => '555-867-5309',
        'dietary_restrictions' => 'Vegetarian',
        'guest_notes' => 'Excited to join!',
        'plus_one' => 1,
        'plus_one_name' => 'Guest Friend',
    ]);

    if (!$response['success']) {
        throw new RuntimeException('RSVP response failed: ' . $response['message']);
    }

    $updatedGuest = $response['data']['guest'];
    if (($updatedGuest['status'] ?? '') !== 'confirmed') {
        throw new RuntimeException('Expected status to be confirmed after RSVP.');
    }

    if (($updatedGuest['plus_one'] ?? 0) !== 1) {
        throw new RuntimeException('Plus one was not stored.');
    }

    if (!str_contains((string)($response['data']['message'] ?? ''), 'RSVP confirmed')) {
        throw new RuntimeException('Success message did not mention confirmation.');
    }

    $success = true;
    echo "✅ Event Bluesky RSVP acceptance passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '❌ ' . $e->getMessage() . PHP_EOL);
} finally {
    if ($guestId !== null) {
        $pdo->prepare('DELETE FROM guests WHERE id = :id')->execute([':id' => $guestId]);
    }
    if ($eventId !== null) {
        $pdo->prepare('DELETE FROM events WHERE id = :id')->execute([':id' => $eventId]);
    }
    if ($hostId !== null) {
        $pdo->prepare('DELETE FROM community_members WHERE user_id = :user_id')->execute([':user_id' => $hostId]);
        $pdo->prepare('DELETE FROM communities WHERE creator_id = :creator_id')->execute([':creator_id' => $hostId]);
        $pdo->prepare('DELETE FROM user_profiles WHERE user_id = :user_id')->execute([':user_id' => $hostId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $hostId]);
    }
}

exit($success ? 0 : 1);
