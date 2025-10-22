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
$shouldAutoOpen = $reply_errors !== [];
?>

<!-- Reply Modal -->
<div
    id="reply-modal"
    class="app-modal app-reply-modal"
    style="display: none;"
    data-auto-open="<?= $shouldAutoOpen ? '1' : '0'; ?>"
    data-conversation-slug="<?= e($c->slug ?? '') ?>"
>
  <div class="app-modal-overlay"></div>
  <div class="app-modal-content">
    <div class="app-modal-header">
      <h3 class="app-modal-title" id="reply-modal-title">Add Reply</h3>
      <button type="button" class="app-btn app-btn-sm" data-dismiss-modal>&times;</button>
    </div>
    <form method="post" action="/conversations/<?= e($c->slug ?? '') ?>/reply" class="app-form" enctype="multipart/form-data" id="reply-form">
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
          <input type="hidden" id="reply-mode" name="reply_mode" value="create">
          <input type="hidden" id="reply-id" name="reply_id" value="">
          <div id="existing-image-preview" style="display: none;" class="app-form-group">
            <label class="app-form-label">Current Image</label>
            <div class="app-reply-current-image">
              <img id="existing-image" src="" alt="" style="max-width: 200px; height: auto; border-radius: 4px;">
              <p id="existing-image-alt" class="app-text-muted" style="font-size: 0.875rem; margin-top: 0.25rem;"></p>
            </div>
          </div>
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
        <button type="submit" class="app-btn app-btn-primary" id="reply-submit-btn">Post Reply</button>
        <button type="button" class="app-btn" data-dismiss-modal>Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php
static $replyModalScriptsLoaded = false;
if (!$replyModalScriptsLoaded) :
    $replyModalScriptsLoaded = true;
    $assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
?>
    <script src="<?= htmlspecialchars($assetBase . '/js/reply-modal.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
