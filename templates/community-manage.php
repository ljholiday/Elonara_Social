<?php

$community = $community ?? null;
$tab = $tab ?? 'members';
$members = $members ?? [];
$viewer_role = $viewer_role ?? null;
$viewer_id = (int)($viewer_id ?? 0);
$can_manage_members = $can_manage_members ?? false;
$statusCode = $status ?? 200;

$securityService = app_service('security.service');
$communityActionNonce = $securityService->createNonce('app_community_action', $viewer_id);
$blueskyActionNonce = $securityService->createNonce('app_bluesky_action', $viewer_id);

?>
<section class="app-section app-community-manage"
  data-community-id="<?= e((string)($community['id'] ?? 0)) ?>"
  data-community-action-nonce="<?= htmlspecialchars($communityActionNonce, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if ($statusCode === 404 || empty($community)): ?>
    <div class="app-text-center app-p-6">
      <h1 class="app-heading">Community not found</h1>
      <p class="app-text-muted">We couldn’t find that community or it may have been removed.</p>
      <p class="app-mt-4">
        <a class="app-btn" href="/communities">Back to communities</a>
      </p>
    </div>
  <?php elseif ($statusCode === 403): ?>
    <div class="app-text-center app-p-6">
      <h1 class="app-heading">Access denied</h1>
      <p class="app-text-muted">You do not have permission to manage this community.</p>
      <p class="app-mt-4">
        <a class="app-btn" href="/communities">Back to communities</a>
      </p>
    </div>
  <?php else:
    $communityId = (int)($community['id'] ?? 0);
    $name = (string)($community['title'] ?? $community['name'] ?? 'Community');
    $slug = (string)($community['slug'] ?? $communityId);
    $memberCount = count($members);
    $shareLink = '/communities/' . ($slug !== '' ? rawurlencode($slug) : (string)$communityId) . '?join=1';
    $tab = in_array($tab, ['members', 'invites'], true) ? $tab : 'members';
    $viewerRole = $viewer_role;
  ?>
    <header class="app-mb-4">
      <h1 class="app-heading app-heading-lg"><?= e($name) ?></h1>
      <?php if (!empty($community['description'])): ?>
        <p class="app-text-muted"><?= e((string)$community['description']) ?></p>
      <?php endif; ?>
    </header>

    <?php if ($tab === 'members'): ?>
      <section class="app-section">
        <div class="app-flex app-flex-between app-align-center app-flex-wrap app-gap-3 app-mb-4">
          <h2 class="app-heading app-heading-md">Members</h2>
          <p class="app-text-muted">Total members: <strong><?= e((string)$memberCount) ?></strong></p>
        </div>

        <div id="community-members-table" class="app-invitations-list">
          <?php if (empty($members)): ?>
            <div class="app-text-center app-text-muted">No members yet.</div>
          <?php else: ?>
            <?php foreach ($members as $member): ?>
              <?php
                $memberId = (int)($member['id'] ?? 0);
                $userId = (int)($member['user_id'] ?? 0);
                $role = (string)($member['role'] ?? 'member');
                $joinedAt = $member['joined_at'] ?? null;
                $displayName = (string)($member['display_name'] ?? $member['email'] ?? 'Member');
                $email = (string)($member['email'] ?? '');
                $isViewer = $userId > 0 && $userId === $viewer_id;
                $roleLabelClass = $role === 'admin' ? 'primary' : ($role === 'moderator' ? 'secondary' : 'secondary');

                $badges = [
                    ['label' => ucfirst($role), 'class' => 'app-badge app-badge-' . $roleLabelClass],
                ];
                if ($isViewer) {
                    $badges[] = ['label' => 'You', 'class' => 'app-badge app-badge-secondary'];
                }

                $actions = [];
                if ($can_manage_members) {
                    if ($isViewer) {
                        $actions[] = '<span class="app-text-muted app-text-sm">Account owner</span>';
                    } else {
                        ob_start();
                        ?>
                        <select class="app-form-input app-form-input-sm"
                          onchange="changeMemberRole(<?= e((string)$memberId) ?>, this.value, <?= e((string)$communityId) ?>)">
                          <option value="member"<?= $role === 'member' ? ' selected' : '' ?>>Member</option>
                          <option value="moderator"<?= $role === 'moderator' ? ' selected' : '' ?>>Moderator</option>
                          <option value="admin"<?= $role === 'admin' ? ' selected' : '' ?>>Admin</option>
                        </select>
                        <?php
                        $actions[] = ob_get_clean();

                        ob_start();
                        ?>
                        <button class="app-btn app-btn-sm app-btn-danger"
                          onclick="removeMember(<?= e((string)$memberId) ?>, <?= htmlspecialchars(json_encode($displayName), ENT_QUOTES, 'UTF-8') ?>, <?= e((string)$communityId) ?>)">
                          Remove
                        </button>
                        <?php
                        $actions[] = ob_get_clean();
                    }
                }

                $card = [
                    'attributes' => ['id' => 'member-row-' . (int)$memberId],
                    'badges' => $badges,
                    'title' => $displayName,
                    'subtitle' => $email !== '' ? $email : null,
                    'meta' => $joinedAt ? 'Joined ' . date('M j, Y', strtotime((string)$joinedAt)) : null,
                    'actions' => $actions,
                ];
                include __DIR__ . '/partials/member-card.php';
              ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    <?php else: ?>
      <?php
        $entity_type = 'community';
        $entity_id = $communityId;
        $invite_url = $shareLink;
        $show_pending = true;
        $cancel_nonce = $communityActionNonce;
        $bluesky_nonce = $blueskyActionNonce;
        include __DIR__ . '/partials/invitation-section.php';
      ?>

      <hr class="app-divider app-my-6">

      <div class="app-section">
        <div class="app-section-header">
          <h2 class="app-heading app-heading-md app-text-primary">Bluesky Followers</h2>
        </div>
        <p class="app-text-muted app-mb-4">
          Invite your Bluesky followers to this community.
          <?php
          $blueskyService = function_exists('app_service') ? app_service('bluesky.service') : null;
          $authService = function_exists('app_service') ? app_service('auth.service') : null;
          $currentUser = $authService ? $authService->getCurrentUser() : null;
          $isConnected = $blueskyService && $currentUser && $blueskyService->isConnected((int)$currentUser?->id);
          ?>
          <?php if (!$isConnected): ?>
            <a href="/profile/edit" class="app-text-primary">Connect your Bluesky account</a> to get started.
          <?php endif; ?>
        </p>
        <?php if ($isConnected): ?>
          <button type="button" class="app-btn app-btn-primary" data-open-bluesky-modal>
            Select Followers to Invite
          </button>
        <?php endif; ?>
      </div>

      <?php
      // Include Bluesky follower selector modal
      $entity_type = 'community';
      $entity_id = $communityId;
      include __DIR__ . '/partials/bluesky-follower-selector.php';
      ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
