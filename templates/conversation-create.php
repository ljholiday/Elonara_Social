<?php
$errors = $errors ?? [];
$input = $input ?? ['title' => '', 'content' => ''];
$context = $context ?? ['allowed' => false, 'label' => '', 'label_html' => '', 'community_id' => null, 'event_id' => null];
$contextLabel = (string)($context['label'] ?? '');
$contextLabelHtml = (string)($context['label_html'] ?? '');
$contextAllowed = (bool)($context['allowed'] ?? false);
?>
<section class="app-section app-conversation-create">
  <h1 class="app-heading">Start Conversation</h1>

  <?php if ($contextLabel !== '' || $contextLabelHtml !== ''): ?>
    <p class="app-text-muted">This conversation will be posted in 
      <?php if ($contextLabelHtml !== ''): ?>
        <?= $contextLabelHtml; ?>
      <?php else: ?>
        <?= e($contextLabel); ?>
      <?php endif; ?>.
    </p>
  <?php endif; ?>

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
    <?php if (!empty($context['community_id'])): ?>
      <input type="hidden" name="community_id" value="<?= (int)$context['community_id']; ?>">
    <?php endif; ?>
    <?php if (!empty($context['community_slug'])): ?>
      <input type="hidden" name="community" value="<?= e((string)$context['community_slug']); ?>">
    <?php endif; ?>
    <?php if (!empty($context['event_id'])): ?>
      <input type="hidden" name="event_id" value="<?= (int)$context['event_id']; ?>">
    <?php endif; ?>
    <?php if (!empty($context['event_slug'])): ?>
      <input type="hidden" name="event" value="<?= e((string)$context['event_slug']); ?>">
    <?php endif; ?>

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
      <button type="submit" class="app-btn app-btn-primary"<?= $contextAllowed ? '' : ' disabled' ?>>Publish Conversation</button>
      <a class="app-btn" href="/conversations">Cancel</a>
    </div>
  </form>
</section>
