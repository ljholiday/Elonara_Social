<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'content' => ''];
?>
<section class="vt-section vt-conversation-create">
  <h1 class="vt-heading">Start Conversation</h1>

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

  <form method="post" action="/conversations/create" class="vt-form vt-stack">
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
      <button type="submit" class="vt-btn vt-btn-primary">Publish Conversation</button>
      <a class="vt-btn" href="/conversations">Cancel</a>
    </div>
  </form>
</section>
