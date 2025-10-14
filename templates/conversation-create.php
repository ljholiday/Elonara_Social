<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'content' => ''];
?>
<section class="app-section app-conversation-create">
  <h1 class="app-heading">Start Conversation</h1>

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

  <form method="post" action="/conversations/create" class="app-form app-stack">
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
      <button type="submit" class="app-btn app-btn-primary">Publish Conversation</button>
      <a class="app-btn" href="/conversations">Cancel</a>
    </div>
  </form>
</section>
