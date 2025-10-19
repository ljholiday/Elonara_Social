<?php

$viewer = $viewer ?? ['id' => 0, 'is_member' => false, 'is_creator' => false];
$status = $status ?? (empty($community) ? 404 : 200);
?>
<section class="app-section app-community-detail">
  <?php if ($status === 404 || empty($community)): ?>
    <h1 class="app-heading">Community not found</h1>
    <p class="app-text-muted">We couldn't find that community or you do not have access.</p>
  <?php else:
    $c = (object)$community;
    $privacy = isset($c->privacy) ? strtolower((string)$c->privacy) : 'public';
    $coverImageData = $community['cover_image'] ?? ($community['featured_image'] ?? null);
    $coverImageUrl = !empty($coverImageData) ? getImageUrl($coverImageData, 'desktop', 'original') : '';
    $coverImageAlt = trim((string)($community['cover_image_alt'] ?? $community['featured_image_alt'] ?? ''));
  ?>
    <?php if ($coverImageUrl !== ''): ?>
      <figure class="app-mb-4" style="margin:0;border-radius:8px;overflow:hidden;">
        <img
          src="<?= e($coverImageUrl) ?>"
          alt="<?= e($coverImageAlt !== '' ? $coverImageAlt : 'Community cover image') ?>"
          style="display:block;width:100%;height:auto;"
        >
      </figure>
    <?php endif; ?>
    <header class="app-mb-4">
      <h1 class="app-heading">
        <?= e($c->title ?? '') ?>
        <?php
          $badge = app_visibility_badge($c->privacy ?? null);
          if (!empty($badge['label'])):
        ?>
          <span class="<?= e($badge['class']) ?>" style="margin-left:0.75rem; font-size:0.8rem;">
            <?= e($badge['label']) ?>
          </span>
        <?php endif; ?>
      </h1>
      <div class="app-sub">
        <?php
        $bits = [];
        if ($privacy !== '') {
            $bits[] = ucfirst($privacy) . ' community';
        }
        if (!empty($c->created_at)) {
            $bits[] = 'Created ' . date_fmt($c->created_at);
        }
        echo e(implode(' · ', $bits));
        ?>
      </div>
      <?php if ($viewer['is_creator'] ?? false): ?>
        <p class="app-text-accent app-mt-2">You created this community.</p>
      <?php elseif ($viewer['is_member'] ?? false): ?>
        <p class="app-text-accent app-mt-2">You are a member of this community.</p>
      <?php elseif ($privacy === 'public'): ?>
        <p class="app-text-muted app-mt-2">You can view this community because it is public.</p>
      <?php else: ?>
        <p class="app-text-muted app-mt-2">You are viewing this community as a guest.</p>
      <?php endif; ?>
    </header>

    <?php if (!empty($c->description)): ?>
      <p class="app-body"><?= e($c->description) ?></p>
    <?php endif; ?>

    <ul class="app-metadata">
      <?php if (isset($c->member_count)): ?>
        <li><strong><?= e((string)$c->member_count) ?></strong> members</li>
      <?php endif; ?>
      <?php if (isset($c->event_count)): ?>
        <li><strong><?= e((string)$c->event_count) ?></strong> events</li>
      <?php endif; ?>
    </ul>

    <?php if (!empty($circle_context) && is_array($circle_context)): ?>
      <section class="app-circle-summary app-mt-4">
        <h2 class="app-heading-sm">Your circles</h2>
        <dl class="app-definition-grid">
          <?php foreach (['inner' => 'Inner circle', 'trusted' => 'Trusted circle', 'extended' => 'Extended circle'] as $key => $label): ?>
            <?php
              $communities = $circle_context[$key]['communities'] ?? [];
              $creators = $circle_context[$key]['creators'] ?? [];
            ?>
            <div>
              <dt><?= e($label) ?></dt>
              <dd>
                <?= e(count($communities)) ?> communities ·
                <?= e(count($creators)) ?> creators
              </dd>
            </div>
          <?php endforeach; ?>
        </dl>
      </section>
    <?php endif; ?>

    <?php if ($privacy === 'public' && !($viewer['is_member'] ?? false)): ?>
      <div class="app-banner app-mt-4">
        <p class="app-text-muted">Interested in joining? Ask a member for an invitation to participate.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
