<?php

$status = $status ?? (empty($event) ? 404 : 200);
$event = $event ?? [];
$tab = $tab ?? 'settings';
$guest_summary = $guest_summary ?? ['total' => 0, 'confirmed' => 0];
$messages = $messages ?? [];

$tab = in_array($tab, ['settings', 'guests', 'invites'], true) ? $tab : 'settings';
?>
<section class="vt-section vt-event-manage">
  <?php if ($status === 404 || empty($event)): ?>
    <div class="vt-text-center vt-p-6">
      <h1 class="vt-heading">Event not found</h1>
      <p class="vt-text-muted">Either this event does not exist or you do not have permission to manage it.</p>
      <p class="vt-mt-4">
        <a class="vt-btn" href="/events">Back to events</a>
      </p>
    </div>
  <?php elseif ($status === 403): ?>
    <div class="vt-text-center vt-p-6">
      <h1 class="vt-heading">Access denied</h1>
      <p class="vt-text-muted">You do not have permission to manage this event.</p>
      <p class="vt-mt-4">
        <a class="vt-btn" href="/events">Back to events</a>
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
    <header class="vt-mb-4">
      <h1 class="vt-heading vt-heading-lg"><?= e($title) ?></h1>
      <?php if (!empty($eventDate)): ?>
        <div class="vt-sub"><?= e(date_fmt((string)$eventDate, 'F j, Y \a\t g:i A')) ?></div>
      <?php endif; ?>
    </header>

    <?php if ($tab === 'settings'): ?>
      <section class="vt-grid vt-gap-4">
        <article class="vt-card">
          <div class="vt-card-body">
            <h2 class="vt-heading vt-heading-sm vt-mb-2">Event status</h2>
            <p class="vt-text-muted vt-mb-0">
              Status: <strong><?= e(ucfirst((string)($event['event_status'] ?? 'active'))) ?></strong>
            </p>
          </div>
        </article>
        <article class="vt-card">
          <div class="vt-card-body">
            <h2 class="vt-heading vt-heading-sm vt-mb-2">Privacy</h2>
            <p class="vt-text-muted vt-mb-4">Current privacy: <strong><?= e($privacy) ?></strong></p>
            <a class="vt-btn vt-btn-secondary" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/edit">
              Update privacy
            </a>
          </div>
        </article>
        <article class="vt-card">
          <div class="vt-card-body">
            <h2 class="vt-heading vt-heading-sm vt-mb-2">Guest summary</h2>
            <p class="vt-text-muted vt-mb-1">
              Confirmed guests: <strong><?= e((string)($guest_summary['confirmed'] ?? 0)) ?></strong>
            </p>
            <p class="vt-text-muted vt-mb-4">
              Total invitations sent: <strong><?= e((string)($guest_summary['total'] ?? 0)) ?></strong>
            </p>
            <a class="vt-btn vt-btn-secondary" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/manage?tab=guests">
              Manage guests
            </a>
          </div>
        </article>
      </section>
    <?php elseif ($tab === 'guests'): ?>
      <section class="vt-section">
        <div class="vt-flex vt-flex-between vt-align-center vt-flex-wrap vt-gap-3 vt-mb-4">
          <h2 class="vt-heading vt-heading-md">Event guests</h2>
          <div class="vt-flex vt-gap-3 vt-align-center">
            <div class="vt-text-muted">
              Total guests:
              <strong id="event-guest-total"><?= e((string)($guest_summary['total'] ?? 0)) ?></strong>
            </div>
            <a class="vt-btn" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/manage?tab=invites">
              Send invitations
            </a>
          </div>
        </div>

        <div id="event-guests-section" class="vt-table-responsive" data-event-id="<?= e((string)$eventId) ?>">
          <table class="vt-table">
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
                <td colspan="5" class="vt-text-center vt-text-muted">Loading event guests...</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div id="event-guests-empty" class="vt-text-center vt-p-4" style="display:none;">
          <p class="vt-text-muted vt-mb-4">No guests yet. Send an invitation to get the party started.</p>
          <a class="vt-btn" href="/events/<?= e($slug !== '' ? $slug : (string)$eventId) ?>/manage?tab=invites">
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

      <hr class="vt-divider vt-my-6">

      <div class="vt-section">
        <div class="vt-section-header">
          <h2 class="vt-heading vt-heading-md vt-text-primary">Bluesky Followers</h2>
        </div>
        <p class="vt-text-muted vt-mb-4">
          Invite your Bluesky followers to this event.
          <?php
          $blueskyService = function_exists('vt_service') ? vt_service('bluesky.service') : null;
          $authService = function_exists('vt_service') ? vt_service('auth.service') : null;
          $currentUser = $authService ? $authService->getCurrentUser() : null;
          $isConnected = $blueskyService && $currentUser && $blueskyService->isConnected($currentUser->id);
          ?>
          <?php if (!$isConnected): ?>
            <a href="/profile/edit" class="vt-text-primary">Connect your Bluesky account</a> to get started.
          <?php endif; ?>
        </p>
        <?php if ($isConnected): ?>
          <button type="button" class="vt-btn vt-btn-primary" data-open-bluesky-modal>
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
