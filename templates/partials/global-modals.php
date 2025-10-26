<?php
/**
 * Global Modals Partial
 *
 * Includes modals that should be available on every page
 *
 * === IMAGE LIBRARY MODAL ===
 *
 * Usage: To add image selection to any form field:
 *
 * 1. Add a preview element (img tag):
 *    <img id="my-image-preview" src="" alt="" style="max-width: 200px;">
 *
 * 2. Add a hidden input for alt-text:
 *    <input type="hidden" id="my-image-alt" name="my_image_alt" value="">
 *
 * 3. Add a button to open the modal:
 *    <button type="button" onclick="window.appOpenImageLibrary({
 *        imageType: 'post',
 *        targetPreview: 'my-image-preview',
 *        targetAltInput: 'my-image-alt'
 *    })">
 *        Select Image
 *    </button>
 *
 * Configuration options:
 * - imageType: 'profile', 'cover', 'post', 'featured', 'reply' (used for filtering library)
 * - targetPreview: ID of the image element to update with selected image
 * - targetAltInput: ID of the input to store alt-text
 * - targetInput: (optional) ID of hidden input to store full URL JSON
 *
 * The modal provides:
 * - Upload tab: Drag & drop or select files, preview, add alt-text, upload
 * - Library tab: Grid of previously uploaded images with thumbnails
 *
 * Both tabs will update the preview and alt-text input when an image is selected.
 */

// Get current user for image library
$authService = function_exists('app_service') ? app_service('auth.service') : null;
$currentUser = $authService ? $authService->getCurrentUser() : null;
$current_user_id = (int)($currentUser->id ?? 0);
?>

<!-- Global Image Library Modal -->
<div
    id="image-library-modal"
    class="app-modal app-image-library-modal"
    style="display: none;"
    data-user-id="<?= htmlspecialchars((string)$current_user_id, ENT_QUOTES, 'UTF-8') ?>"
>
  <div class="app-modal-overlay"></div>
  <div class="app-modal-content app-image-library-modal-content" style="max-width: 800px; width: 90%;">
    <div class="app-modal-header">
      <h3 class="app-modal-title">Select Image</h3>
      <button type="button" class="app-btn app-btn-sm" data-dismiss-modal>&times;</button>
    </div>

    <!-- Tabs -->
    <div class="app-tabs" style="border-bottom: 1px solid #e5e7eb;">
      <button type="button" class="app-tab-btn app-tab-active" data-tab="upload" style="padding: 0.75rem 1.5rem; border: none; background: none; cursor: pointer; border-bottom: 2px solid #3b82f6; font-weight: 500;">
        Upload
      </button>
      <button type="button" class="app-tab-btn" data-tab="library" style="padding: 0.75rem 1.5rem; border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; color: #6b7280;">
        Library
      </button>
    </div>

    <div class="app-modal-body" style="min-height: 400px;">
      <!-- Upload Tab -->
      <div class="app-tab-content" id="tab-upload" style="padding: 2rem;">
        <div class="app-upload-area" style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 3rem; text-align: center; background: #f9fafb;">
          <input
            type="file"
            id="modal-file-input"
            accept="image/jpeg,image/png,image/gif,image/webp"
            style="display: none;"
          >
          <div class="app-upload-prompt">
            <svg style="width: 64px; height: 64px; margin: 0 auto 1rem; color: #9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <p style="font-size: 1.125rem; font-weight: 500; margin-bottom: 0.5rem;">Drop files to upload</p>
            <p style="color: #6b7280; margin-bottom: 1.5rem;">or</p>
            <button type="button" class="app-btn app-btn-primary" id="select-file-btn">Select Files</button>
            <p style="color: #9ca3af; font-size: 0.875rem; margin-top: 1rem;">Maximum file size: 10MB</p>
          </div>

          <!-- Upload Preview -->
          <div class="app-upload-preview" style="display: none; margin-top: 2rem;">
            <img id="upload-preview-img" src="" alt="" style="max-width: 100%; max-height: 300px; border-radius: 8px; margin-bottom: 1rem;">
            <div class="app-field">
              <label class="app-label" for="upload-alt-text">Image description (required)</label>
              <input
                type="text"
                class="app-input"
                id="upload-alt-text"
                placeholder="Describe this image for accessibility"
                style="max-width: 500px; margin: 0 auto;"
              >
              <div class="app-field-error" id="upload-alt-error" style="display: none;"></div>
            </div>
            <div style="margin-top: 1.5rem;">
              <button type="button" class="app-btn app-btn-primary" id="upload-submit-btn">Upload & Select</button>
              <button type="button" class="app-btn" id="upload-cancel-btn">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Library Tab -->
      <div class="app-tab-content" id="tab-library" style="display: none; padding: 2rem;">
        <div class="app-image-library-loading" style="display: none; text-align: center; padding: 2rem;">
          <p>Loading your images...</p>
        </div>
        <div class="app-image-library-empty" style="display: none; text-align: center; padding: 2rem;">
          <p class="app-text-muted">No images found in your library.</p>
          <p class="app-text-muted app-text-sm">Switch to the Upload tab to add images to your library.</p>
        </div>
        <div class="app-image-library-error" style="display: none; text-align: center; padding: 2rem;">
          <p class="app-text-danger">Failed to load images. Please try again.</p>
        </div>
        <div class="app-image-library-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem;">
          <!-- Thumbnails will be loaded here by JavaScript -->
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Load image library JavaScript (only once per page)
static $imageLibraryScriptsLoaded = false;
if (!$imageLibraryScriptsLoaded) :
    $imageLibraryScriptsLoaded = true;
    $assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
?>
    <script src="<?= htmlspecialchars($assetBase . '/js/image-library.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>
