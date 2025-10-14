<?php

$status = $status ?? (empty($event) ? 404 : 200);
$event = $event ?? [];
$tab = $tab ?? 'settings';
$guest_summary = $guest_summary ?? ['total' => 0, 'confirmed' => 0];
$messages = $messages ?? [];

$tab = in_array($tab, ['settings', 'guests', 'invites'], true) ? $tab : 'settings';
?>
<section class="app-section app-event-manage">
  <?php if ($status === 404 || empty($event)): ?>
    <div class="app-text-center app-p-6">
      <h1 class="app-heading">Event not found</h1>
      <p class="app-text-muted">Either this event does not exist or you do not have permission to manage it.</p>
      <p class="app-mt-4">
        <a class="app-btn" href="/events">Back to events</a>
      </p>
    </div>
  <?php elseif ($status === 403): ?>
    <div class="app-text-center app-p-6">
      <h1 class="app-heading">Access denied</h1>
      <p class="app-text-muted">You do not have permission to manage this event.</p>
      <p class="app-mt-4">
        <a class="app-btn" href="/events">Back to events</a>
      </p>
    </div>
  <?php else:
    $slug = (string)($event['slug'] ?? '');
    $eventId = (int)($event['id'] ?? 0);
    $title = (string)($event['title'] ?? 'Untitled event');
    $eventDate = $event['event_date'] ?? null;
    $privacy = ucfirst((string)($event['privacy'] ?? 'public'));

    $invitationLink = '/events/' . ($slug !== '' ? rawurlencode($slug) : (string)$eventId) . '?join=1';
    $tabs = [
      'settings' => 'Overview',
      'guests'   => 'Guests',
      'invites'  => 'Invitations',
    ];
  ?>
    <header class="app-mb-4">
      <h1 class="app-heading app-heading-lg"><?= e($title) ?></h1>
      <?php if (!empty($eventDate)): ?>
        <div class="app-sub"><?= e(date_fmt((string)$eventDate, 'F j, Y \a\t g:i A')) ?></div>
      <?php endif; ?>
    </header>

    <?php if ($tab === 'settings'): ?>
      <section class="app-grid app-gap-4">
        <article class="app-card">
          <div class="app-card-body">
            <h2 class="app-heading app-heading-sm app-mb-2">Event status</h2>
            <p class="app-text-muted app-mb-0">
              Status: <strong><?= e(ucfirst((string)($event['event_status'] ?? 'active'))) ?></strong>
            </p>
          </div>
        </article>
        <article class="app-card">
          <div class="app-card-body">
            <h2 class="app-heading app-heading-sm app-mb-2">Privacy</h2>
            <p class="app-text-muted app-mb-4">Current privacy: <strong><?= e($privacy) ?></strong></p>
            <a class="app-btn app-btn-secondary" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/edit">
              Update privacy
            </a>
          </div>
        </article>
        <article class="app-card">
          <div class="app-card-body">
            <h2 class="app-heading app-heading-sm app-mb-2">Guest summary</h2>
            <p class="app-text-muted app-mb-1">
              Confirmed guests: <strong><?= e((string)($guest_summary['confirmed'] ?? 0)) ?></strong>
            </p>
            <p class="app-text-muted app-mb-4">
              Total invitations sent: <strong><?= e((string)($guest_summary['total'] ?? 0)) ?></strong>
            </p>
            <a class="app-btn app-btn-secondary" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/manage?tab=guests">
              Manage guests
            </a>
          </div>
        </article>
      </section>
    <?php elseif ($tab === 'guests'): ?>
      <section class="app-section">
        <div class="app-flex app-flex-between app-align-center app-flex-wrap app-gap-3 app-mb-4">
          <h2 class="app-heading app-heading-md">Event guests</h2>
          <div class="app-flex app-gap-3 app-align-center">
            <div class="app-text-muted">
              Total guests:
              <strong id="event-guest-total"><?= e((string)($guest_summary['total'] ?? 0)) ?></strong>
            </div>
            <a class="app-btn" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/manage?tab=invites">
              Send invitations
            </a>
          </div>
        </div>

        <div id="event-guests-section" class="app-table-responsive" data-event-id="<?= e((string)$eventId) ?>">
          <table class="app-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>RSVP Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="event-guests-body">
              <tr>
                <td colspan="5" class="app-text-center app-text-muted">Loading event guests...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div id="event-guests-empty" class="app-text-center app-p-4" style="display:none;">
          <p class="app-text-muted app-mb-4">No guests yet. Send an invitation to get the party started.</p>
          <a class="app-btn" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/manage?tab=invites">
            Invite guests
          </a>
        </div>
      </section>
    <?php elseif ($tab === 'invites'): ?>
      <?php
      $entity_type = 'event';
      $entity_id = $eventId;
      $invite_url = $invitationLink;
      $show_pending = true;
      include __DIR__ . '/partials/invitation-section.php';
      ?>

      <hr class="app-divider app-my-6">

      <div class="app-section">
        <div class="app-section-header">
          <h2 class="app-heading app-heading-md app-text-primary">Bluesky Followers</h2>
        </div>
        <p class="app-text-muted app-mb-4">
          Invite your Bluesky followers to this event.
          <?php
          $blueskyService = function_exists('app_service') ? app_service('bluesky.service') : null;
          $authService = function_exists('app_service') ? app_service('auth.service') : null;
          $currentUser = $authService ? $authService->getCurrentUser() : null;
          $isConnected = $blueskyService && $currentUser && $blueskyService->isConnected($currentUser->id);
          ?>
          <?php if (!$isConnected): ?>
            <a href="/profile/edit" class="app-text-primary">Connect your Bluesky account</a> to get started.
          <?php endif; ?>
        </p>
        <?php if ($isConnected): ?>
          <button type="button" class="app-btn app-btn-primary" data-open-bluesky-modal>
            Select Followers to Invite
          </button>
        <?php endif; ?>
      </div>

      <?php
      // Include Bluesky follower selector modal
      $entity_type = 'event';
      $entity_id = $eventId;
      include __DIR__ . '/partials/bluesky-follower-selector.php';
      ?>
    <?php endif; ?>
  <?php endif; ?>
</section>

<script src="/assets/js/communities.js"></script>
