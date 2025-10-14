<?php

$viewer = $viewer ?? ['id' => 0, 'is_member' => false, 'is_creator' => false];
$status = $status ?? (empty($community) ? 404 : 200);
?>
<section class="vt-section vt-community-detail">
  <?php if ($status === 404 || empty($community)): ?>
    <h1 class="vt-heading">Community not found</h1>
    <p class="vt-text-muted">We couldn't find that community or you do not have access.</p>
  <?php else:
    $c = (object)$community;
    $privacy = isset($c->privacy) ? strtolower((string)$c->privacy) : 'public';
  ?>
    <header class="vt-mb-4">
      <h1 class="vt-heading"><?= e($c->title ?? '') ?></h1>
      <div class="vt-sub">
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
        <p class="vt-text-accent vt-mt-2">You created this community.</p>
      <?php elseif ($viewer['is_member'] ?? false): ?>
        <p class="vt-text-accent vt-mt-2">You are a member of this community.</p>
      <?php elseif ($privacy === 'public'): ?>
        <p class="vt-text-muted vt-mt-2">You can view this community because it is public.</p>
      <?php else: ?>
        <p class="vt-text-muted vt-mt-2">You are viewing this community as a guest.</p>
      <?php endif; ?>
    </header>

    <?php if (!empty($c->description)): ?>
      <p class="vt-body"><?= e($c->description) ?></p>
    <?php endif; ?>

    <ul class="vt-metadata">
      <?php if (isset($c->member_count)): ?>
        <li><strong><?= e((string)$c->member_count) ?></strong> members</li>
      <?php endif; ?>
      <?php if (isset($c->event_count)): ?>
        <li><strong><?= e((string)$c->event_count) ?></strong> events</li>
      <?php endif; ?>
    </ul>

    <?php if (!empty($circle_context) && is_array($circle_context)): ?>
      <section class="vt-circle-summary vt-mt-4">
        <h2 class="vt-heading-sm">Your circles</h2>
        <dl class="vt-definition-grid">
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
      <div class="vt-banner vt-mt-4">
        <p class="vt-text-muted">Interested in joining? Ask a member for an invitation to participate.</p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
