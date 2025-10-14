<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'content' => ''];
$conversation = $conversation ?? null;
?>
<section class="vt-section vt-conversation-edit">
  <?php if (!$conversation): ?>
    <h1 class="vt-heading">Conversation not found</h1>
    <p class="vt-text-muted">We couldn’t find that conversation.</p>
  <?php else: ?>
    <h1 class="vt-heading">Edit Conversation</h1>
    <p class="vt-text-muted">Editing <strong><?= e($conversation['title'] ?? '') ?></strong></p>

    <?php if ($errors): ?>
      <div class="vt-alert vt-alert-error vt-mb-4">
        <p>Please fix the issues below:</p>
        <ul>
          <?php foreach ($errors as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="/conversations/<?= e($conversation['slug'] ?? '') ?>/edit" class="vt-form vt-stack">
      <div class="vt-field">
        <label class="vt-label" for="title">Title</label>
        <input
          class="vt-input<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
          type="text"
          id="title"
          name="title"
          value="<?= e($input['title'] ?? '') ?>"
          required
        >
      </div>

      <div class="vt-field">
        <label class="vt-label" for="content">Content</label>
        <textarea
          class="vt-textarea<?= isset($errors['content']) ? ' is-invalid' : '' ?>"
          id="content"
          name="content"
          rows="6"
          required
        ><?= e($input['content'] ?? '') ?></textarea>
      </div>

      <div class="vt-actions">
        <button type="submit" class="vt-btn vt-btn-primary">Save Changes</button>
        <a class="vt-btn" href="/conversations/<?= e($conversation['slug'] ?? '') ?>">Cancel</a>
      </div>
    </form>

    <div class="vt-danger-zone vt-mt-6">
      <h2 class="vt-heading-sm">Danger Zone</h2>
      <p class="vt-text-muted">Deleting a conversation cannot be undone.</p>
      <form method="post" action="/conversations/<?= e($conversation['slug'] ?? '') ?>/delete" class="vt-inline-form" onsubmit="return confirm('Delete this conversation?');">
        <button type="submit" class="vt-btn vt-btn-danger">Delete Conversation</button>
      </form>
    </div>
  <?php endif; ?>
</section>
