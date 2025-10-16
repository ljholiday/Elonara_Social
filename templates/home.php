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
        <div class="app-grid app-grid-3 app-gap-4">
          <?php foreach ($events as $event): ?>
            <div class="app-card">
              <div class="app-card-body app-stack app-gap-2">
                <h3 class="app-heading app-heading-sm">
                  <a href="/events/<?= e($event['slug'] ?? (string)($event['id'] ?? '')); ?>" class="app-text-primary">
                    <?= e($event['context_label'] ?? $event['title'] ?? 'Untitled event'); ?>
                  </a>
                  <?php
                    $badge = app_visibility_badge($event['privacy'] ?? null, $event['community_privacy'] ?? null);
                    if (!empty($badge['label'])):
                  ?>
                    <span class="<?= e($badge['class']) ?>" style="margin-left:0.5rem;">
                      <?= e($badge['label']); ?>
                    </span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($event['event_date'])): ?>
                  <p class="app-text-muted">
                    <?= e(date_fmt($event['event_date'], 'M j, Y • g:i A')); ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($event['description'])): ?>
                  <p class="app-text-muted app-text-sm">
                    <?= e(app_truncate_words($event['description'], 24)); ?>
                  </p>
                <?php endif; ?>
                <a class="app-btn app-btn-outline app-btn-sm" href="/events/<?= e($event['slug'] ?? (string)($event['id'] ?? '')); ?>">View details</a>
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
        <div class="app-grid app-grid-3 app-gap-4">
          <?php foreach ($communities as $community): ?>
            <div class="app-card">
              <div class="app-card-body app-stack app-gap-2">
                <h3 class="app-heading app-heading-sm">
                  <a href="/communities/<?= e($community['slug'] ?? (string)($community['id'] ?? '')); ?>" class="app-text-primary">
                    <?= e($community['title'] ?? $community['name'] ?? 'Community'); ?>
                  </a>
                  <?php
                    $badge = app_visibility_badge($community['privacy'] ?? null);
                    if (!empty($badge['label'])):
                  ?>
                    <span class="<?= e($badge['class']) ?>" style="margin-left:0.5rem;">
                      <?= e($badge['label']); ?>
                    </span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($community['description'])): ?>
                  <p class="app-text-muted app-text-sm">
                    <?= e(app_truncate_words($community['description'], 24)); ?>
                  </p>
                <?php endif; ?>
                <div class="app-flex app-gap-3 app-text-muted app-text-sm">
                  <?php if (isset($community['member_count'])): ?>
                    <span><?= e((string)$community['member_count']); ?> members</span>
                  <?php endif; ?>
                  <?php if (isset($community['event_count'])): ?>
                    <span><?= e((string)$community['event_count']); ?> events</span>
                  <?php endif; ?>
                </div>
                <a class="app-btn app-btn-outline app-btn-sm" href="/communities/<?= e($community['slug'] ?? (string)($community['id'] ?? '')); ?>">View community</a>
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
        <div class="app-stack app-gap-3">
          <?php foreach ($conversations as $conversation): ?>
            <div class="app-card">
              <div class="app-card-body app-stack app-gap-2">
                <h3 class="app-heading app-heading-sm">
                  <a href="/conversations/<?= e($conversation['slug'] ?? (string)($conversation['id'] ?? '')); ?>" class="app-text-primary">
                    <?= e($conversation['context_label'] ?? $conversation['title'] ?? 'Conversation'); ?>
                  </a>
                  <?php
                    $badge = app_visibility_badge($conversation['privacy'] ?? $conversation['community_privacy'] ?? null);
                    if (!empty($badge['label'])):
                  ?>
                    <span class="<?= e($badge['class']) ?>" style="margin-left:0.5rem;">
                      <?= e($badge['label']); ?>
                    </span>
                  <?php endif; ?>
                </h3>
                <?php if (!empty($conversation['created_at'])): ?>
                  <p class="app-text-muted app-text-sm">
                    Started <?= e(app_time_ago($conversation['created_at'])); ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($conversation['excerpt'])): ?>
                  <p class="app-text-muted app-text-sm">
                    <?= e(app_truncate_words($conversation['excerpt'], 28)); ?>
                  </p>
                <?php elseif (!empty($conversation['content'])): ?>
                  <p class="app-text-muted app-text-sm">
                    <?= e(app_truncate_words($conversation['content'], 28)); ?>
                  </p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</section>
