<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'content' => ''];
$conversation = $conversation ?? null;
?>
<section class="app-section app-conversation-edit">
  <?php if (!$conversation): ?>
    <h1 class="app-heading">Conversation not found</h1>
    <p class="app-text-muted">We couldn’t find that conversation.</p>
  <?php else: ?>
    <h1 class="app-heading">Edit Conversation</h1>
    <p class="app-text-muted">Editing <strong><?= e($conversation['title'] ?? '') ?></strong></p>

    <?php if ($errors): ?>
      <div class="app-alert app-alert-error app-mb-4">
        <p>Please fix the issues below:</p>
        <ul>
          <?php foreach ($errors as $message): ?>
            <li><?= e($message) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="/conversations/<?= e($conversation['slug'] ?? '') ?>/edit" class="app-form app-stack">
      <div class="app-field">
        <label class="app-label" for="title">Title</label>
        <input
          class="app-input<?= isset($errors['title']) ? ' is-invalid' : '' ?>"
          type="text"
          id="title"
          name="title"
          value="<?= e($input['title'] ?? '') ?>"
          required
        >
      </div>

      <div class="app-field">
        <label class="app-label" for="content">Content</label>
        <textarea
          class="app-textarea<?= isset($errors['content']) ? ' is-invalid' : '' ?>"
          id="content"
          name="content"
          rows="6"
          required
        ><?= e($input['content'] ?? '') ?></textarea>
      </div>

      <div class="app-actions">
        <button type="submit" class="app-btn app-btn-primary">Save Changes</button>
        <a class="app-btn" href="/conversations/<?= e($conversation['slug'] ?? '') ?>">Cancel</a>
      </div>
    </form>

    <div class="app-danger-zone app-mt-6">
      <h2 class="app-heading-sm">Danger Zone</h2>
      <p class="app-text-muted">Deleting a conversation cannot be undone.</p>
      <form method="post" action="/conversations/<?= e($conversation['slug'] ?? '') ?>/delete" class="app-inline-form" onsubmit="return confirm('Delete this conversation?');">
        <button type="submit" class="app-btn app-btn-danger">Delete Conversation</button>
      </form>
    </div>
  <?php endif; ?>
</section>
