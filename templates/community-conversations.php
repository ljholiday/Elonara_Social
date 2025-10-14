<?php
/**
 * Community Conversations Template
 */

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('date_fmt')) {
    function date_fmt($date) { return date('M j, Y', strtotime($date)); }
}

$community = $community ?? null;
$conversations = $conversations ?? [];
?>

<section class="vt-section">
  <?php if (!$community): ?>
    <p class="vt-text-muted">Community not found.</p>
  <?php else: ?>
    <h1 class="vt-heading">Conversations</h1>
    <p class="vt-text-muted">Conversations in <?= e($community['title']) ?></p>

    <?php if (empty($conversations)): ?>
      <div class="vt-card vt-mt-4">
        <p class="vt-text-muted">No conversations in this community yet.</p>
      </div>
    <?php else: ?>
      <div class="vt-stack vt-mt-4">
        <?php foreach ($conversations as $conversation): $c = (object)$conversation; ?>
          <article class="vt-card">
            <h3 class="vt-heading-sm">
              <a href="/conversations/<?= e($c->slug) ?>" class="vt-link">
                <?= e($c->title) ?>
              </a>
            </h3>
            <?php if (!empty($c->content)): ?>
              <p class="vt-card-desc"><?= e(mb_substr($c->content, 0, 200)) ?><?= mb_strlen($c->content) > 200 ? '...' : '' ?></p>
            <?php endif; ?>
            <div class="vt-card-meta">
              <span><?= e($c->author_name ?? 'Unknown') ?></span>
              <?php if (!empty($c->created_at)): ?>
                <span> · <?= e(date_fmt($c->created_at)) ?></span>
              <?php endif; ?>
              <?php if (!empty($c->reply_count)): ?>
                <span> · <?= e((string)$c->reply_count) ?> replies</span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
