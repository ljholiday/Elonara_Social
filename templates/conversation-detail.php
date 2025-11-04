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
    <?php
      $conversationHeaderMeta = [];
      $authorName = $c->author_display_name ?? $c->author_name ?? $c->author_username ?? '';
      if ($authorName !== '') {
          $conversationHeaderMeta[] = ['text' => 'Started by ' . $authorName];
      }
      if (!empty($c->created_at)) {
          $conversationHeaderMeta[] = ['text' => date_fmt($c->created_at)];
      }
      if ($conversationHeaderMeta !== []) {
          $items = $conversationHeaderMeta;
          include __DIR__ . '/partials/meta-row.php';
      }
    ?>
    <?php if (!empty($c->content)): ?>
      <div class="app-body app-mt-4">
        <?= nl2br(e($c->content)) ?>
      </div>
    <?php endif; ?>
    <?php
      $conversationStatsMeta = [];
      $conversationStatsMeta[] = ['text' => number_format((int)($c->reply_count ?? 0)) . ' replies'];
      if (!empty($c->last_reply_date)) {
          $conversationStatsMeta[] = ['text' => 'Last reply ' . date_fmt($c->last_reply_date)];
      }
      $items = $conversationStatsMeta;
      include __DIR__ . '/partials/meta-row.php';
    ?>

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
                      'avatar_url' => $r->author_avatar_url ?? null,
                      'avatar_preference' => $r->author_avatar_preference ?? 'auto'
                  ];
                  $args = ['avatar_size' => 32, 'class' => 'app-member-display-inline', 'show_actions' => true];
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
                    <button type="button" class="app-btn-icon" title="Edit reply"
                      data-reply-id="<?= e((string)($r->id ?? 0)) ?>"
                      data-reply-content="<?= htmlspecialchars($r->content ?? '', ENT_QUOTES, 'UTF-8') ?>"
                      data-reply-image-url="<?= htmlspecialchars($r->image_url ?? '', ENT_QUOTES, 'UTF-8') ?>"
                      data-reply-image-alt="<?= htmlspecialchars($r->image_alt ?? '', ENT_QUOTES, 'UTF-8') ?>"
                      onclick="editReply(this)">
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
                    <?php
                      $url_data = $r->image_url;
                      $alt = $r->image_alt ?? '';
                      $default_size = 'original';
                      $class = 'app-img';
                      $lazy = true;
                      $use_picture = true;
                      include __DIR__ . '/partials/responsive-image.php';
                    ?>
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
