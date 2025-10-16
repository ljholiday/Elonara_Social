<?php
/** @var array<int,array<string,mixed>> $conversations */
/** @var string $circle */
/** @var array $pagination */

$circle = $circle ?? 'all';
$pagination = $pagination ?? ['page' => 1, 'per_page' => 20, 'has_more' => false, 'next_page' => null];
?>
<section class="app-section app-conversations">
  <h1 class="app-heading">Conversations</h1>

  <?php if (!empty($conversations)): ?>
    <div class="app-stack">
      <?php foreach ($conversations as $row):
        $item = (object)$row;
        ?>
        <article class="app-card">
          <h3 class="app-card-title">
            <a class="app-link" href="/conversations/<?= e($item->slug ?? (string)($item->id ?? '')) ?>">
              <?= e($item->context_label ?? $item->title ?? '') ?>
            </a>
            <?php
              $badge = app_visibility_badge($item->privacy ?? $item->community_privacy ?? null);
              if (!empty($badge['label'])):
            ?>
              <span class="<?= e($badge['class']) ?>" style="margin-left:0.5rem;"><?= e($badge['label']) ?></span>
            <?php endif; ?>
          </h3>
          <?php if (!empty($item->author_name)): ?>
            <div class="app-card-sub">Started by <?= e($item->author_name) ?><?php if (!empty($item->created_at)): ?> · <?= e(date_fmt($item->created_at)) ?><?php endif; ?></div>
          <?php endif; ?>
          <?php if (!empty($item->content)): ?>
            <p class="app-card-desc"><?= e(substr(strip_tags((string)$item->content), 0, 160)) ?><?= strlen(strip_tags((string)$item->content)) > 160 ? '…' : '' ?></p>
          <?php endif; ?>
          <div class="app-card-meta">
            <span><?= e((string)($item->reply_count ?? 0)) ?> replies</span>
            <?php if (!empty($item->last_reply_date)): ?>
              <span>Updated <?= e(date_fmt($item->last_reply_date)) ?></span>
            <?php endif; ?>
          </div>
          <?php if (!empty($item->community_name)): ?>
            <div class="app-card-meta">In <?= e($item->community_name) ?></div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="app-text-muted">No conversations found.</p>
  <?php endif; ?>

  <?php if (!empty($pagination['has_more'])): ?>
    <div class="app-mt-4">
      <a class="app-btn" href="/conversations?circle=<?= urlencode($circle) ?>&page=<?= (int)($pagination['next_page'] ?? (($pagination['page'] ?? 1) + 1)) ?>">Older Conversations</a>
    </div>
  <?php endif; ?>
</section>
