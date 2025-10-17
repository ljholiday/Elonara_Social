<section class="app-section app-conversation-detail">
  <?php if (empty($conversation)): ?>
    <h1 class="app-heading">Conversation not found</h1>
    <p class="app-text-muted">We couldn’t find that conversation.</p>
  <?php else: $c = (object)$conversation; ?>
    <?php $contextLabelHtml = $context_label_html ?? ''; ?>
    <h1 class="app-heading">
      <?= $contextLabelHtml !== '' ? $contextLabelHtml : e($c->title ?? '') ?>
      <?php
        $badge = app_visibility_badge($c->privacy ?? $c->community_privacy ?? null);
        if (!empty($badge['label'])):
      ?>
        <span class="<?= e($badge['class']) ?>" style="margin-left:0.75rem; font-size:0.8rem;">
          <?= e($badge['label']) ?>
        </span>
      <?php endif; ?>
    </h1>
    <div class="app-sub app-flex app-gap">
      <?php
      if (!empty($c->author_username) || !empty($c->author_display_name)) {
          echo '<span>Started by</span>';
          $user = (object)[
              'id' => $c->author_id ?? null,
              'username' => $c->author_username ?? null,
              'display_name' => $c->author_display_name ?? $c->author_name ?? 'Unknown',
              'email' => $c->author_email ?? null,
              'avatar_url' => $c->author_avatar_url ?? null
          ];
          $args = ['avatar_size' => 24, 'class' => 'app-member-display-inline'];
          include __DIR__ . '/partials/member-display.php';
      }
      if (!empty($c->created_at)) {
          echo '<span> · ' . e(date_fmt($c->created_at)) . '</span>';
      }
      ?>
    </div>
    <?php if (!empty($c->content)): ?>
      <div class="app-body app-mt-4">
        <?= nl2br(e($c->content)) ?>
      </div>
    <?php endif; ?>
    <div class="app-card-meta app-mt-4">
      <span><?= e((string)($c->reply_count ?? 0)) ?> replies</span>
      <?php if (!empty($c->last_reply_date)): ?>
        <span>Last reply <?= e(date_fmt($c->last_reply_date)) ?></span>
      <?php endif; ?>
    </div>

    <section class="app-section app-mt-6">
      <h2 class="app-heading-sm">Replies</h2>
      <?php if (!empty($replies)): ?>
        <div class="app-stack">
          <?php
          $conversationService = function_exists('app_service') ? app_service('conversation.service') : null;
          foreach ($replies as $reply):
            $r = (object)$reply;
            $content = e($r->content ?? '');
            // Process embeds if service available
            if ($conversationService && method_exists($conversationService, 'processContentEmbeds')) {
              $content = $conversationService->processContentEmbeds($content);
            } else {
              $content = nl2br($content);
            }
          ?>
            <article class="app-card">
              <div class="app-card-sub app-flex app-gap app-flex-between">
                <div class="app-flex app-gap">
                  <?php
                  $user = (object)[
                      'id' => $r->author_id ?? null,
                      'username' => $r->author_username ?? null,
                      'display_name' => $r->author_display_name ?? $r->author_name ?? 'Unknown',
                      'email' => $r->author_email ?? null,
                      'avatar_url' => $r->author_avatar_url ?? null
                  ];
                  $args = ['avatar_size' => 32, 'class' => 'app-member-display-inline'];
                  include __DIR__ . '/partials/member-display.php';
                  ?>
                  <?php if (!empty($r->created_at)): ?>
                    <span class="app-text-muted"> · <?= e(date_fmt($r->created_at)) ?></span>
                  <?php endif; ?>
                </div>
                <?php
                // Check if current user can edit/delete this reply
                $currentUser = function_exists('app_service') ? app_service('auth.service')->getCurrentUser() : null;
                $currentUserId = (int)($currentUser?->id ?? 0);
                $replyAuthorId = (int)($r->author_id ?? 0);
                $canEdit = $currentUserId > 0 && $currentUserId === $replyAuthorId;
                ?>
                <?php if ($canEdit): ?>
                  <div class="app-reply-actions">
                    <button type="button" class="app-btn-icon" title="Edit reply" onclick="editReply(<?= e((string)($r->id ?? 0)) ?>)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                      </svg>
                    </button>
                    <button type="button" class="app-btn-icon" title="Delete reply" onclick="deleteReply(<?= e((string)($r->id ?? 0)) ?>)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                <?php endif; ?>
              </div>
              <div class="app-card-body">
                <?php if (!empty($r->image_url)): ?>
                  <div class="app-reply-image app-mb-3">
                    <img src="<?= e($r->image_url) ?>" alt="<?= e($r->image_alt ?? '') ?>" class="app-img" loading="lazy">
                  </div>
                <?php endif; ?>
                <div class="app-card-desc"><?= $content ?></div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="app-text-muted">No replies yet.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/reply-modal.php'; ?>
