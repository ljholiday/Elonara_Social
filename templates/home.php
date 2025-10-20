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
    <header class="app-card">
      <div class="app-card-body app-flex app-flex-between app-flex-wrap app-gap-4">
        <div class="app-flex app-flex-column app-gap-2">
          <h1 class="app-heading app-heading-lg">Welcome back, <?= $viewerName; ?></h1>
          <p class="app-text-muted app-text-lg">
            Plan events, keep up with your communities, and jump into the conversations that matter.
          </p>
<!--
          <div class="app-flex app-gap-2 app-flex-wrap">
            <a class="app-btn app-btn-primary" href="/events/create">Create event</a>
            <a class="app-btn app-btn-secondary" href="/communities/create">Start a community</a>
            <a class="app-btn app-btn-outline" href="/conversations/create">New conversation</a>
          </div>
-->
        </div>
        <aside class="app-card app-max-w-sm">
          <div class="app-card-body app-stack app-gap-2">
            <h2 class="app-heading app-heading-sm app-text-muted">Quick stats</h2>
            <div class="app-flex app-gap-4">
              <div>
                <div class="app-heading app-heading-md"><?= count($events); ?></div>
                <div class="app-text-muted app-text-sm">Upcoming events</div>
              </div>
              <div>
                <div class="app-heading app-heading-md"><?= count($communities); ?></div>
                <div class="app-text-muted app-text-sm">Communities</div>
              </div>
              <div>
                <div class="app-heading app-heading-md"><?= count($conversations); ?></div>
                <div class="app-text-muted app-text-sm">New conversations</div>
              </div>
            </div>
          </div>
        </aside>
      </div>
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
            ?>
            <div class="app-invitation-item">
              <div class="app-invitation-badges">
                <?php if (!empty($badge['label'])): ?>
                  <span class="<?= e($badge['class']) ?>"><?= e($badge['label']); ?></span>
                <?php endif; ?>
              </div>
              <div class="app-invitation-details">
                <strong><a href="<?= e($eventUrl); ?>" class="app-text-primary"><?= e($eventTitle); ?></a></strong>
                <?php if ($eventDate !== ''): ?>
                  <div class="app-text-muted app-text-sm"><?= e($eventDate); ?></div>
                <?php endif; ?>
                <?php if ($eventDescription !== ''): ?>
                  <small class="app-text-muted"><?= e($eventDescription); ?></small>
                <?php endif; ?>
              </div>
              <div class="app-invitation-actions">
                <a class="app-btn app-btn-sm app-btn-outline" href="<?= e($eventUrl); ?>">View details</a>
              </div>
            </div>
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
            ?>
            <div class="app-invitation-item">
              <div class="app-invitation-badges">
                <?php if (!empty($badge['label'])): ?>
                  <span class="<?= e($badge['class']) ?>"><?= e($badge['label']); ?></span>
                <?php endif; ?>
              </div>
              <div class="app-invitation-details">
                <strong><a href="<?= e($communityUrl); ?>" class="app-text-primary"><?= e($communityName); ?></a></strong>
                <?php if ($communityDescription !== ''): ?>
                  <div class="app-text-muted app-text-sm"><?= e($communityDescription); ?></div>
                <?php endif; ?>
                <small class="app-text-muted">
                  <?php if (isset($community['member_count'])): ?>
                    <?= e((string)$community['member_count']); ?> members
                  <?php endif; ?>
                  <?php if (isset($community['event_count'])): ?>
                    • <?= e((string)$community['event_count']); ?> events
                  <?php endif; ?>
                </small>
              </div>
              <div class="app-invitation-actions">
                <a class="app-btn app-btn-sm app-btn-outline" href="<?= e($communityUrl); ?>">View community</a>
              </div>
            </div>
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
            ?>
            <div class="app-invitation-item">
              <div class="app-invitation-badges">
                <?php if (!empty($conversationBadge['label'])): ?>
                  <span class="<?= e($conversationBadge['class']) ?>"><?= e($conversationBadge['label']); ?></span>
                <?php endif; ?>
              </div>
              <div class="app-invitation-details">
                <strong><a href="<?= e($conversationUrl); ?>" class="app-text-primary"><?= e($conversationTitle); ?></a></strong>
                <?php if ($startedAt !== ''): ?>
                  <div class="app-text-muted app-text-sm">Started <?= e($startedAt); ?></div>
                <?php endif; ?>
                <?php if ($conversationExcerpt !== ''): ?>
                  <small class="app-text-muted"><?= e($conversationExcerpt); ?></small>
                <?php endif; ?>
              </div>
              <div class="app-invitation-actions">
                <a class="app-btn app-btn-sm app-btn-outline" href="<?= e($conversationUrl); ?>">Open conversation</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</section>
