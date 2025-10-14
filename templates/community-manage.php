<?php

$community = $community ?? null;
$tab = $tab ?? 'members';
$members = $members ?? [];
$viewer_role = $viewer_role ?? null;
$viewer_id = $viewer_id ?? 0;
$can_manage_members = $can_manage_members ?? false;
$statusCode = $status ?? 200;

?>
<section class="vt-section vt-community-manage">
  <?php if ($statusCode === 404 || empty($community)): ?>
    <div class="vt-text-center vt-p-6">
      <h1 class="vt-heading">Community not found</h1>
      <p class="vt-text-muted">We couldn’t find that community or it may have been removed.</p>
      <p class="vt-mt-4">
        <a class="vt-btn" href="/communities">Back to communities</a>
      </p>
    </div>
  <?php elseif ($statusCode === 403): ?>
    <div class="vt-text-center vt-p-6">
      <h1 class="vt-heading">Access denied</h1>
      <p class="vt-text-muted">You do not have permission to manage this community.</p>
      <p class="vt-mt-4">
        <a class="vt-btn" href="/communities">Back to communities</a>
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
    <header class="vt-mb-4">
      <h1 class="vt-heading vt-heading-lg"><?= e($name) ?></h1>
      <?php if (!empty($community['description'])): ?>
        <p class="vt-text-muted"><?= e((string)$community['description']) ?></p>
      <?php endif; ?>
    </header>

    <?php if ($tab === 'members'): ?>
      <section class="vt-section">
        <div class="vt-flex vt-flex-between vt-align-center vt-flex-wrap vt-gap-3 vt-mb-4">
          <h2 class="vt-heading vt-heading-md">Members</h2>
          <p class="vt-text-muted">Total members: <strong><?= e((string)$memberCount) ?></strong></p>
        </div>

        <div class="vt-table-responsive">
          <table class="vt-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Joined</th>
                <th>Role / Actions</th>
              </tr>
            </thead>
            <tbody id="members-table-body">
              <?php if (empty($members)): ?>
                <tr>
                  <td colspan="4" class="vt-text-center vt-text-muted">No members yet.</td>
                </tr>
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
                  ?>
                  <tr id="member-row-<?= e((string)$memberId) ?>">
                    <td><strong><?= e($displayName) ?></strong></td>
                    <td><?= e($email) ?></td>
                    <td><?= $joinedAt ? e(date('M j, Y', strtotime((string)$joinedAt))) : '—' ?></td>
                    <td>
                      <div class="vt-flex vt-gap-2 vt-align-center">
                        <?php
                          $roleLabelClass = $role === 'admin' ? 'primary' : ($role === 'moderator' ? 'secondary' : 'secondary');
                        ?>
                        <?php if ($can_manage_members): ?>
                          <?php if ($isViewer): ?>
                            <span class="vt-text-muted vt-text-sm">You</span>
                          <?php else: ?>
                            <select class="vt-form-input vt-form-input-sm"
                              onchange="changeMemberRole(<?= e((string)$memberId) ?>, this.value, <?= e((string)$communityId) ?>)">
                              <option value="member"<?= $role === 'member' ? ' selected' : '' ?>>Member</option>
                              <option value="moderator"<?= $role === 'moderator' ? ' selected' : '' ?>>Moderator</option>
                              <option value="admin"<?= $role === 'admin' ? ' selected' : '' ?>>Admin</option>
                            </select>
                            <button class="vt-btn vt-btn-sm vt-btn-danger"
                              onclick="removeMember(<?= e((string)$memberId) ?>, <?= htmlspecialchars(json_encode($displayName), ENT_QUOTES, 'UTF-8') ?>, <?= e((string)$communityId) ?>)">
                              Remove
                            </button>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="vt-badge vt-badge-<?= $roleLabelClass ?>"><?= e(ucfirst($role)) ?></span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php else: ?>
      <?php
        $entity_type = 'community';
        $entity_id = $communityId;
        $invite_url = $shareLink;
        $show_pending = true;
        include __DIR__ . '/partials/invitation-section.php';
      ?>

      <hr class="vt-divider vt-my-6">

      <div class="vt-section">
        <div class="vt-section-header">
          <h2 class="vt-heading vt-heading-md vt-text-primary">Bluesky Followers</h2>
        </div>
        <p class="vt-text-muted vt-mb-4">
          Invite your Bluesky followers to this community.
          <?php
          $blueskyService = function_exists('vt_service') ? vt_service('bluesky.service') : null;
          $authService = function_exists('vt_service') ? vt_service('auth.service') : null;
          $currentUser = $authService ? $authService->getCurrentUser() : null;
          $isConnected = $blueskyService && $currentUser && $blueskyService->isConnected($currentUser->id);
          ?>
          <?php if (!$isConnected): ?>
            <a href="/profile/edit" class="vt-text-primary">Connect your Bluesky account</a> to get started.
          <?php endif; ?>
        </p>
        <?php if ($isConnected): ?>
          <button type="button" class="vt-btn vt-btn-primary" data-open-bluesky-modal>
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

<script src="/assets/js/communities.js"></script>
