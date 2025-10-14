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

<section class="vt-section vt-dashboard">
  <div class="vt-container vt-stack vt-gap-6">
    <header class="vt-card">
      <div class="vt-card-body vt-flex vt-flex-between vt-flex-wrap vt-gap-4">
        <div class="vt-flex vt-flex-column vt-gap-2">
          <h1 class="vt-heading vt-heading-lg">Welcome back, <?= $viewerName; ?></h1>
          <p class="vt-text-muted vt-text-lg">
            Plan events, keep up with your communities, and jump into the conversations that matter.
          </p>
          <div class="vt-flex vt-gap-2 vt-flex-wrap">
            <a class="vt-btn vt-btn-primary" href="/events/create">Create event</a>
            <a class="vt-btn vt-btn-secondary" href="/communities/create">Start a community</a>
            <a class="vt-btn vt-btn-outline" href="/conversations/create">New conversation</a>
          </div>
        </div>
        <aside class="vt-card vt-max-w-sm">
          <div class="vt-card-body vt-stack vt-gap-2">
            <h2 class="vt-heading vt-heading-sm vt-text-muted">Quick stats</h2>
            <div class="vt-flex vt-gap-4">
              <div>
                <div class="vt-heading vt-heading-md"><?= count($events); ?></div>
                <div class="vt-text-muted vt-text-sm">Upcoming events</div>
              </div>
              <div>
                <div class="vt-heading vt-heading-md"><?= count($communities); ?></div>
                <div class="vt-text-muted vt-text-sm">Communities</div>
              </div>
              <div>
                <div class="vt-heading vt-heading-md"><?= count($conversations); ?></div>
                <div class="vt-text-muted vt-text-sm">New conversations</div>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </header>

    <section class="vt-stack vt-gap-3">
      <div class="vt-flex vt-flex-between vt-items-center">
        <h2 class="vt-heading vt-heading-md">Upcoming events</h2>
        <a class="vt-link" href="/events">View all events →</a>
      </div>
      <?php if ($events === []): ?>
        <div class="vt-card">
          <div class="vt-card-body vt-text-center vt-stack vt-gap-3">
            <p class="vt-text-muted">No events on your calendar yet.</p>
            <a class="vt-btn vt-btn-secondary" href="/events/create">Plan your first event</a>
          </div>
        </div>
      <?php else: ?>
        <div class="vt-grid vt-grid-3 vt-gap-4">
          <?php foreach ($events as $event): ?>
            <div class="vt-card">
              <div class="vt-card-body vt-stack vt-gap-2">
                <h3 class="vt-heading vt-heading-sm">
                  <a href="/events/<?= e($event['slug'] ?? (string)($event['id'] ?? '')); ?>" class="vt-text-primary">
                    <?= e($event['title'] ?? 'Untitled event'); ?>
                  </a>
                </h3>
                <?php if (!empty($event['event_date'])): ?>
                  <p class="vt-text-muted">
                    <?= e(date_fmt($event['event_date'], 'M j, Y • g:i A')); ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($event['description'])): ?>
                  <p class="vt-text-muted vt-text-sm">
                    <?= e(vt_truncate_words($event['description'], 24)); ?>
                  </p>
                <?php endif; ?>
                <a class="vt-btn vt-btn-outline vt-btn-sm" href="/events/<?= e($event['slug'] ?? (string)($event['id'] ?? '')); ?>">View details</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="vt-stack vt-gap-3">
      <div class="vt-flex vt-flex-between vt-items-center">
        <h2 class="vt-heading vt-heading-md">Your communities</h2>
        <a class="vt-link" href="/communities">Browse communities →</a>
      </div>
      <?php if ($communities === []): ?>
        <div class="vt-card">
          <div class="vt-card-body vt-text-center vt-stack vt-gap-3">
            <p class="vt-text-muted">You haven’t joined any communities yet.</p>
            <a class="vt-btn vt-btn-secondary" href="/communities">Discover communities</a>
          </div>
        </div>
      <?php else: ?>
        <div class="vt-grid vt-grid-3 vt-gap-4">
          <?php foreach ($communities as $community): ?>
            <div class="vt-card">
              <div class="vt-card-body vt-stack vt-gap-2">
                <h3 class="vt-heading vt-heading-sm">
                  <a href="/communities/<?= e($community['slug'] ?? (string)($community['id'] ?? '')); ?>" class="vt-text-primary">
                    <?= e($community['title'] ?? $community['name'] ?? 'Community'); ?>
                  </a>
                </h3>
                <?php if (!empty($community['description'])): ?>
                  <p class="vt-text-muted vt-text-sm">
                    <?= e(vt_truncate_words($community['description'], 24)); ?>
                  </p>
                <?php endif; ?>
                <div class="vt-flex vt-gap-3 vt-text-muted vt-text-sm">
                  <?php if (isset($community['member_count'])): ?>
                    <span><?= e((string)$community['member_count']); ?> members</span>
                  <?php endif; ?>
                  <?php if (isset($community['event_count'])): ?>
                    <span><?= e((string)$community['event_count']); ?> events</span>
                  <?php endif; ?>
                </div>
                <a class="vt-btn vt-btn-outline vt-btn-sm" href="/communities/<?= e($community['slug'] ?? (string)($community['id'] ?? '')); ?>">View community</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="vt-stack vt-gap-3">
      <div class="vt-flex vt-flex-between vt-items-center">
        <h2 class="vt-heading vt-heading-md">Recent conversations</h2>
        <a class="vt-link" href="/conversations">Go to conversations →</a>
      </div>
      <?php if ($conversations === []): ?>
        <div class="vt-card">
          <div class="vt-card-body vt-text-center vt-stack vt-gap-3">
            <p class="vt-text-muted">No conversations yet. Start the first one!</p>
            <a class="vt-btn vt-btn-secondary" href="/conversations/create">Start a conversation</a>
          </div>
        </div>
      <?php else: ?>
        <div class="vt-stack vt-gap-3">
          <?php foreach ($conversations as $conversation): ?>
            <div class="vt-card">
              <div class="vt-card-body vt-stack vt-gap-2">
                <h3 class="vt-heading vt-heading-sm">
                  <a href="/conversations/<?= e($conversation['slug'] ?? (string)($conversation['id'] ?? '')); ?>" class="vt-text-primary">
                    <?= e($conversation['title'] ?? 'Conversation'); ?>
                  </a>
                </h3>
                <?php if (!empty($conversation['created_at'])): ?>
                  <p class="vt-text-muted vt-text-sm">
                    Started <?= e(vt_time_ago($conversation['created_at'])); ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($conversation['excerpt'])): ?>
                  <p class="vt-text-muted vt-text-sm">
                    <?= e(vt_truncate_words($conversation['excerpt'], 28)); ?>
                  </p>
                <?php elseif (!empty($conversation['content'])): ?>
                  <p class="vt-text-muted vt-text-sm">
                    <?= e(vt_truncate_words($conversation['content'], 28)); ?>
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
