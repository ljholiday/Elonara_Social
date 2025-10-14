<section class="vt-section vt-conversation-detail">
  <?php if (empty($conversation)): ?>
    <h1 class="vt-heading">Conversation not found</h1>
    <p class="vt-text-muted">We couldn’t find that conversation.</p>
  <?php else: $c = (object)$conversation; ?>
    <h1 class="vt-heading"><?= e($c->title ?? '') ?></h1>
    <div class="vt-sub vt-flex vt-gap">
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
          $args = ['avatar_size' => 24, 'class' => 'vt-member-display-inline'];
          include __DIR__ . '/partials/member-display.php';
      }
      if (!empty($c->created_at)) {
          echo '<span> · ' . e(date_fmt($c->created_at)) . '</span>';
      }
      ?>
    </div>
    <?php if (!empty($c->content)): ?>
      <div class="vt-body vt-mt-4">
        <?= nl2br(e($c->content)) ?>
      </div>
    <?php endif; ?>
    <div class="vt-card-meta vt-mt-4">
      <span><?= e((string)($c->reply_count ?? 0)) ?> replies</span>
      <?php if (!empty($c->last_reply_date)): ?>
        <span>Last reply <?= e(date_fmt($c->last_reply_date)) ?></span>
      <?php endif; ?>
    </div>

    <section class="vt-section vt-mt-6">
      <h2 class="vt-heading-sm">Replies</h2>
      <?php if (!empty($replies)): ?>
        <div class="vt-stack">
          <?php
          $conversationService = function_exists('vt_service') ? vt_service('conversation.service') : null;
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
            <article class="vt-card">
              <div class="vt-card-sub vt-flex vt-gap vt-flex-between">
                <div class="vt-flex vt-gap">
                  <?php
                  $user = (object)[
                      'id' => $r->author_id ?? null,
                      'username' => $r->author_username ?? null,
                      'display_name' => $r->author_display_name ?? $r->author_name ?? 'Unknown',
                      'email' => $r->author_email ?? null,
                      'avatar_url' => $r->author_avatar_url ?? null
                  ];
                  $args = ['avatar_size' => 32, 'class' => 'vt-member-display-inline'];
                  include __DIR__ . '/partials/member-display.php';
                  ?>
                  <?php if (!empty($r->created_at)): ?>
                    <span class="vt-text-muted"> · <?= e(date_fmt($r->created_at)) ?></span>
                  <?php endif; ?>
                </div>
                <?php
                // Check if current user can edit/delete this reply
                $currentUser = function_exists('vt_service') ? vt_service('auth.service')->getCurrentUser() : null;
                $currentUserId = $currentUser->id ?? 0;
                $replyAuthorId = (int)($r->author_id ?? 0);
                $canEdit = $currentUserId > 0 && $currentUserId === $replyAuthorId;
                ?>
                <?php if ($canEdit): ?>
                  <div class="vt-reply-actions">
                    <button type="button" class="vt-btn-icon" title="Edit reply" onclick="editReply(<?= e((string)($r->id ?? 0)) ?>)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                      </svg>
                    </button>
                    <button type="button" class="vt-btn-icon" title="Delete reply" onclick="deleteReply(<?= e((string)($r->id ?? 0)) ?>)">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                      </svg>
                    </button>
                  </div>
                <?php endif; ?>
              </div>
              <div class="vt-card-body">
                <?php if (!empty($r->image_url)): ?>
                  <div class="vt-reply-image vt-mb-3">
                    <img src="<?= e($r->image_url) ?>" alt="<?= e($r->image_alt ?? '') ?>" class="vt-img" loading="lazy">
                  </div>
                <?php endif; ?>
                <div class="vt-card-desc"><?= $content ?></div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="vt-text-muted">No replies yet.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/reply-modal.php'; ?>
