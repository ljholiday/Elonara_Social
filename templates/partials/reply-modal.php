<?php
/**
 * Reply Modal Partial
 *
 * Reusable modal for adding replies to conversations
 *
 * Required variables:
 * - $c: Conversation object with slug
 * - $reply_errors: Array of validation errors (optional)
 * - $reply_input: Array of previous input values (optional)
 */

$reply_errors = $reply_errors ?? [];
$reply_input = $reply_input ?? [];
?>

<!-- Reply Modal -->
<div id="reply-modal" class="app-modal app-reply-modal" style="display: none;">
  <div class="app-modal-overlay"></div>
  <div class="app-modal-content">
    <div class="app-modal-header">
      <h3 class="app-modal-title">Add Reply</h3>
      <button type="button" class="app-btn app-btn-sm" data-dismiss-modal>&times;</button>
    </div>
    <form method="post" action="/conversations/<?= e($c->slug ?? '') ?>/reply" class="app-form" enctype="multipart/form-data">
      <div class="app-modal-body">
        <div class="app-reply-form">
          <?php if (!empty($reply_errors)): ?>
            <div class="app-alert app-alert-error app-mb-4">
              <ul>
                <?php foreach ($reply_errors as $message): ?>
                  <li><?= e($message) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (function_exists('app_service')): ?>
            <?php echo app_service('security.service')->nonceField('app_conversation_reply', 'reply_nonce'); ?>
          <?php endif; ?>
          <div class="app-form-group">
            <label class="app-form-label" for="reply-content">Reply</label>
            <textarea class="app-form-textarea<?= isset($reply_errors['content']) ? ' is-invalid' : '' ?>" id="reply-content" name="content" rows="4" required><?= e($reply_input['content'] ?? '') ?></textarea>
          </div>
          <div class="app-form-group">
            <label class="app-form-label" for="reply-image">Image (optional)</label>
            <input type="file" class="app-form-input<?= isset($reply_errors['image_alt']) ? ' is-invalid' : '' ?>" id="reply-image" name="reply_image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="app-form-help">Maximum 10MB. JPEG, PNG, GIF, or WebP format.</small>
          </div>
          <div class="app-form-group">
            <label class="app-form-label" for="image-alt">Image description</label>
            <input type="text" class="app-form-input<?= isset($reply_errors['image_alt']) ? ' is-invalid' : '' ?>" id="image-alt" name="image_alt" placeholder="Describe the image for accessibility" value="<?= e($reply_input['image_alt'] ?? '') ?>">
            <small class="app-form-help">Required if uploading an image. Describe what's in the image for screen reader users.</small>
            <?php if (isset($reply_errors['image_alt'])): ?>
              <div class="app-form-error"><?= e($reply_errors['image_alt']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="app-modal-footer">
        <button type="submit" class="app-btn app-btn-primary">Post Reply</button>
        <button type="button" class="app-btn" data-dismiss-modal>Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="/assets/js/modal.js"></script>
<script>
(function() {
  'use strict';

  const modal = document.getElementById('reply-modal');
  const openBtn = document.querySelector('[data-open-reply-modal]');
  const closeBtns = modal.querySelectorAll('[data-dismiss-modal]');
  const overlay = modal.querySelector('.app-modal-overlay');
  const form = modal.querySelector('form');

  // Open modal
  if (openBtn) {
    openBtn.addEventListener('click', function() {
      modal.style.display = 'block';
      document.body.classList.add('app-modal-open');
    });
  }

  // Close modal function
  function closeModal() {
    modal.style.display = 'none';
    document.body.classList.remove('app-modal-open');
    // Clear the form when closing
    if (form) {
      form.reset();
    }
  }

  // Close button handlers
  closeBtns.forEach(btn => {
    btn.addEventListener('click', closeModal);
  });

  // Overlay click
  if (overlay) {
    overlay.addEventListener('click', closeModal);
  }

  // ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modal.style.display === 'block') {
      closeModal();
    }
  });

  // Auto-show modal if there are errors
  <?php if (!empty($reply_errors)): ?>
  modal.style.display = 'block';
  document.body.classList.add('app-modal-open');
  <?php endif; ?>
})();
</script>
