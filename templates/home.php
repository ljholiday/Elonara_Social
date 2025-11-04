<?php
/** @var object $viewer */
/** @var array<int,array<string,mixed>> $upcoming_events */
/** @var array<int,array<string,mixed>> $my_communities */
/** @var array<int,array<string,mixed>> $recent_conversations */

$viewerName = e($viewer->display_name ?? $viewer->username ?? 'Friend');
$events = $upcoming_events ?? [];
$communities = $my_communities ?? [];
$conversations = $recent_conversations ?? [];
?>

<section class="app-section app-dashboard">
  <div class="app-container app-stack app-gap-6">


    <header>

          <a href="https://elonara.com/how-to-elonara/" target="_blank" class="app-btn-banner " >Learn How To Elonara</a>

    </header>

    <section class="app-stack app-gap-3">
      <div class="app-flex app-flex-between app-items-center">
        <h2 class="app-heading app-heading-md">Upcoming events</h2>
        <a class="app-link" href="/events">View all events →</a>
      </div>
      <?php if ($events === []): ?>
        <div class="app-card">
          <div class="app-card-body app-text-center app-stack app-gap-3">
            <p class="app-text-muted">No events on your calendar yet.</p>
            <a class="app-btn app-btn-secondary" href="/events/create">Plan your first event</a>
          </div>
        </div>
      <?php else: ?>
        <div class="app-invitations-list">
          <?php foreach ($events as $event): ?>
            <?php
              $eventSlug = $event['slug'] ?? (string)($event['id'] ?? '');
              $eventUrl = '/events/' . $eventSlug;
              $eventTitle = $event['context_label'] ?? $event['title'] ?? 'Untitled event';
              $eventDate = !empty($event['event_date']) ? date_fmt($event['event_date'], 'M j, Y • g:i A') : '';
              $eventDescription = !empty($event['description']) ? app_truncate_words($event['description'], 24) : '';
              $badge = app_visibility_badge($event['privacy'] ?? null, $event['community_privacy'] ?? null);

              $badges = [];
              if (!empty($badge['label'])) {
                  $badges[] = ['label' => $badge['label'], 'class' => $badge['class']];
              }

              $bodyHtml = $eventDescription !== ''
                  ? '<div class="app-text-muted app-text-sm">' . htmlspecialchars($eventDescription, ENT_QUOTES, 'UTF-8') . '</div>'
                  : '';

              $actions = [];
              ob_start();
              ?>
              <a class="app-btn app-btn-sm app-btn-outline" href="<?= e($eventUrl); ?>">View details</a>
              <?php
              $actions[] = ob_get_clean();

              $card = [
                  'badges' => $badges,
                  'title' => $eventTitle,
                  'title_url' => $eventUrl,
                  'subtitle' => $eventDate,
                  'body_html' => $bodyHtml,
                  'actions' => $actions,
              ];
              include __DIR__ . '/partials/member-card.php';
            ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="app-stack app-gap-3">
      <div class="app-flex app-flex-between app-items-center">
        <h2 class="app-heading app-heading-md">Your communities</h2>
        <a class="app-link" href="/communities">Browse communities →</a>
      </div>
      <?php if ($communities === []): ?>
        <div class="app-card">
          <div class="app-card-body app-text-center app-stack app-gap-3">
            <p class="app-text-muted">You haven’t joined any communities yet.</p>
            <a class="app-btn app-btn-secondary" href="/communities">Discover communities</a>
          </div>
        </div>
      <?php else: ?>
        <div class="app-invitations-list">
          <?php foreach ($communities as $community): ?>
            <?php
              $communitySlug = $community['slug'] ?? (string)($community['id'] ?? '');
              $communityUrl = '/communities/' . $communitySlug;
              $communityName = $community['title'] ?? $community['name'] ?? 'Community';
              $communityDescription = !empty($community['description']) ? app_truncate_words($community['description'], 24) : '';
              $badge = app_visibility_badge($community['privacy'] ?? null);

              $badges = [];
              if (!empty($badge['label'])) {
                  $badges[] = ['label' => $badge['label'], 'class' => $badge['class']];
              }

              $metaItems = [];
              if (isset($community['member_count'])) {
                  $metaItems[] = [
                      'text' => number_format((int)$community['member_count']) . ' members',
                  ];
              }
              if (isset($community['event_count'])) {
                  $metaItems[] = [
                      'text' => number_format((int)$community['event_count']) . ' events',
                  ];
              }

              $bodyHtml = $communityDescription !== ''
                  ? '<div class="app-text-muted app-text-sm">' . htmlspecialchars($communityDescription, ENT_QUOTES, 'UTF-8') . '</div>'
                  : '';

              $actions = [];
              ob_start();
              ?>
              <a class="app-btn app-btn-sm app-btn-outline" href="<?= e($communityUrl); ?>">View community</a>
              <?php
              $actions[] = ob_get_clean();

              $card = [
                  'badges' => $badges,
                  'title' => $communityName,
                  'title_url' => $communityUrl,
                  'body_html' => $bodyHtml,
                  'meta_items' => $metaItems,
                  'actions' => $actions,
              ];
              include __DIR__ . '/partials/member-card.php';
            ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="app-stack app-gap-3">
      <div class="app-flex app-flex-between app-items-center">
        <h2 class="app-heading app-heading-md">Recent conversations</h2>
        <a class="app-link" href="/conversations">Go to conversations →</a>
      </div>
      <?php if ($conversations === []): ?>
        <div class="app-card">
          <div class="app-card-body app-text-center app-stack app-gap-3">
            <p class="app-text-muted">No conversations yet. Start the first one!</p>
            <a class="app-btn app-btn-secondary" href="/conversations/create">Start a conversation</a>
          </div>
        </div>
      <?php else: ?>
        <div class="app-invitations-list">
          <?php foreach ($conversations as $conversation): ?>
            <?php
              $conversationSlug = $conversation['slug'] ?? (string)($conversation['id'] ?? '');
              $conversationUrl = '/conversations/' . $conversationSlug;
              $conversationTitle = $conversation['context_label'] ?? $conversation['title'] ?? 'Conversation';
              $conversationBadge = app_visibility_badge($conversation['privacy'] ?? $conversation['community_privacy'] ?? null);
              $conversationExcerpt = !empty($conversation['excerpt'])
                ? app_truncate_words($conversation['excerpt'], 28)
                : (!empty($conversation['content']) ? app_truncate_words($conversation['content'], 28) : '');
              $startedAt = !empty($conversation['created_at']) ? app_time_ago($conversation['created_at']) : '';

              $badges = [];
              if (!empty($conversationBadge['label'])) {
                  $badges[] = ['label' => $conversationBadge['label'], 'class' => $conversationBadge['class']];
              }

              $bodyHtml = $conversationExcerpt !== ''
                  ? '<small class="app-text-muted">' . htmlspecialchars($conversationExcerpt, ENT_QUOTES, 'UTF-8') . '</small>'
                  : '';

              $actions = [];
              ob_start();
              ?>
              <a class="app-btn app-btn-sm app-btn-outline" href="<?= e($conversationUrl); ?>">Open conversation</a>
              <?php
              $actions[] = ob_get_clean();

              $card = [
                  'badges' => $badges,
                  'title' => $conversationTitle,
                  'title_url' => $conversationUrl,
                  'subtitle' => $startedAt !== '' ? 'Started ' . $startedAt : null,
                  'body_html' => $bodyHtml,
                  'actions' => $actions,
              ];
              include __DIR__ . '/partials/member-card.php';
            ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</section>
