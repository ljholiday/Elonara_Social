<?php
declare(strict_types=1);

/** @var array<string,mixed> $pagination */
$searchQuery = $searchQuery ?? '';
$users = $users ?? [];
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'per_page' => 25, 'total' => 0];
$flash = $flash ?? [];

/** @var \App\Services\SecurityService $security */
$security = app_service('security.service');
$nonceField = fn(): string => $security->nonceField('app_admin', '_admin_nonce', false);

$currentQuery = $searchQuery !== '' ? ['q' => $searchQuery] : [];
?>

<?php if (!empty($flash)): ?>
  <div class="admin-card" style="border-left:4px solid <?= $flash['type'] === 'success' ? '#16a34a' : '#dc2626'; ?>;">
    <strong><?= htmlspecialchars(ucfirst($flash['type'])); ?>:</strong>
    <?= htmlspecialchars((string)$flash['message']); ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <form method="get" action="/admin/users" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
    <div style="flex:1 1 240px;">
      <label style="display:block; font-weight:600; margin-bottom:0.35rem;">Search</label>
      <input
        class="app-input"
        name="q"
        placeholder="Name, username, email, or DID"
        value="<?= htmlspecialchars($searchQuery); ?>"
      >
    </div>
    <div>
      <button type="submit" class="app-btn app-btn-primary">Search</button>
    </div>
  </form>
  <div style="margin-top:1rem; color:#4a5470; font-size:0.9rem;">
    <?= number_format((int)$pagination['total']); ?> total users
  </div>
</div>

<div class="admin-card">
  <?php if (empty($users)): ?>
    <p style="margin:0; color:#4a5470;">No users found for that query.</p>
  <?php else: ?>
    <div class="admin-user-grid">
      <?php foreach ($users as $user): ?>
        <?php
        $id = (int)($user['id'] ?? 0);
        $status = (string)($user['status'] ?? '');
        $role = (string)($user['role'] ?? '');
        $username = (string)($user['username'] ?? '');
        $email = (string)($user['email'] ?? '');
        $did = trim((string)($user['did'] ?? ''));
        $isVerified = (int)($user['is_verified'] ?? 0) === 1;
        $verifiedLabel = $isVerified ? 'Yes' : 'No';
        $verifiedClass = $isVerified ? 'is-verified' : 'is-unverified';
        ?>
        <div class="admin-user-card">
          <div class="admin-user-header">
            <div>
              <div class="admin-user-name"><?= htmlspecialchars((string)($user['display_name'] ?? '')); ?></div>
              <?php if ($status !== ''): ?>
                <span class="admin-user-status"><?= htmlspecialchars(strtoupper($status)); ?></span>
              <?php endif; ?>
            </div>
            <div>
              <div class="admin-user-field is-right">
                <span>ID:</span> <?= $id; ?>
              </div>
              <div class="admin-user-field is-right">
                <span>Role:</span> <?= htmlspecialchars($role); ?>
              </div>
            </div>
          </div>
          <div class="admin-user-meta">
            <div class="admin-user-field">
              <span>Username:</span> <?= $username !== '' ? htmlspecialchars($username) : '—'; ?>
            </div>
            <div class="admin-user-field">
              <span>Email:</span> <?= htmlspecialchars($email); ?>
            </div>
            <div class="admin-user-field">
              <span>DID:</span> <?= $did !== '' ? htmlspecialchars($did) : '—'; ?>
            </div>
            <div class="admin-user-field">
              <span>Verified:</span>
              <span class="admin-user-verified <?= $verifiedClass; ?>"><?= $verifiedLabel; ?></span>
            </div>
          </div>
          <div class="admin-user-actions">
            <form method="post" action="/admin/users/<?= $id; ?>/reset-password">
              <?= $nonceField(); ?>
              <button type="submit" class="app-btn app-btn-sm">Reset Password</button>
            </form>
            <form method="post" action="/admin/users/<?= $id; ?>/resend-verification">
              <?= $nonceField(); ?>
              <button type="submit" class="app-btn app-btn-sm app-btn-secondary">Resend Registration Email</button>
            </form>
            <form method="post" action="/admin/users/<?= $id; ?>/approve">
              <?= $nonceField(); ?>
              <button type="submit" class="app-btn app-btn-sm app-btn-primary">Manually Approve</button>
            </form>
            <form method="post" action="/admin/users/<?= $id; ?>/delete" onsubmit="return confirm('Delete this user? This cannot be undone.');">
              <?= $nonceField(); ?>
              <button type="submit" class="app-btn app-btn-sm app-btn-danger">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ((int)$pagination['pages'] > 1): ?>
      <?php
      $page = (int)$pagination['page'];
      $pages = (int)$pagination['pages'];

      $buildLink = function (int $targetPage) use ($currentQuery): string {
          $params = $currentQuery;
          $params['page'] = $targetPage;
          return '/admin/users?' . http_build_query($params);
      };
      ?>
      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.5rem; font-size:0.9rem; color:#4a5470;">
        <div>
          Page <?= $page; ?> of <?= $pages; ?>
        </div>
        <div style="display:flex; gap:0.75rem;">
          <?php if ($page > 1): ?>
            <a class="app-link" href="<?= htmlspecialchars($buildLink($page - 1)); ?>">&larr; Previous</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
            <a class="app-link" href="<?= htmlspecialchars($buildLink($page + 1)); ?>">Next &rarr;</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
