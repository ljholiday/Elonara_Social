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
<div id="reply-modal" class="vt-modal vt-reply-modal" style="display: none;">
  <div class="vt-modal-overlay"></div>
  <div class="vt-modal-content">
    <div class="vt-modal-header">
      <h3 class="vt-modal-title">Add Reply</h3>
      <button type="button" class="vt-btn vt-btn-sm" data-dismiss-modal>&times;</button>
    </div>
    <form method="post" action="/conversations/<?= e($c->slug ?? '') ?>/reply" class="vt-form" enctype="multipart/form-data">
      <div class="vt-modal-body">
        <div class="vt-reply-form">
          <?php if (!empty($reply_errors)): ?>
            <div class="vt-alert vt-alert-error vt-mb-4">
              <ul>
                <?php foreach ($reply_errors as $message): ?>
                  <li><?= e($message) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <?php if (function_exists('vt_service')): ?>
            <?php echo vt_service('security.service')->nonceField('vt_conversation_reply', 'reply_nonce'); ?>
          <?php endif; ?>
          <div class="vt-form-group">
            <label class="vt-form-label" for="reply-content">Reply</label>
            <textarea class="vt-form-textarea<?= isset($reply_errors['content']) ? ' is-invalid' : '' ?>" id="reply-content" name="content" rows="4" required><?= e($reply_input['content'] ?? '') ?></textarea>
          </div>
          <div class="vt-form-group">
            <label class="vt-form-label" for="reply-image">Image (optional)</label>
            <input type="file" class="vt-form-input<?= isset($reply_errors['image_alt']) ? ' is-invalid' : '' ?>" id="reply-image" name="reply_image" accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="vt-form-help">Maximum 10MB. JPEG, PNG, GIF, or WebP format.</small>
          </div>
          <div class="vt-form-group">
            <label class="vt-form-label" for="image-alt">Image description</label>
            <input type="text" class="vt-form-input<?= isset($reply_errors['image_alt']) ? ' is-invalid' : '' ?>" id="image-alt" name="image_alt" placeholder="Describe the image for accessibility" value="<?= e($reply_input['image_alt'] ?? '') ?>">
            <small class="vt-form-help">Required if uploading an image. Describe what's in the image for screen reader users.</small>
            <?php if (isset($reply_errors['image_alt'])): ?>
              <div class="vt-form-error"><?= e($reply_errors['image_alt']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="vt-modal-footer">
        <button type="submit" class="vt-btn vt-btn-primary">Post Reply</button>
        <button type="button" class="vt-btn" data-dismiss-modal>Cancel</button>
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
  const overlay = modal.querySelector('.vt-modal-overlay');

  // Open modal
  if (openBtn) {
    openBtn.addEventListener('click', function() {
      modal.style.display = 'block';
      document.body.classList.add('vt-modal-open');
    });
  }

  // Close modal function
  function closeModal() {
    modal.style.display = 'none';
    document.body.classList.remove('vt-modal-open');
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
  document.body.classList.add('vt-modal-open');
  <?php endif; ?>
})();
</script>
