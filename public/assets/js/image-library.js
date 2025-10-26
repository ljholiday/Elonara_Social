/**
 * Image Library Module (WordPress-style)
 *
 * Provides tabbed interface with Upload and Library functionality.
 * Works with the image-library-modal partial.
 */

(function() {
    'use strict';

    const modal = document.getElementById('image-library-modal');
    if (!modal) {
        return; // Modal not on this page
    }

    const modalOverlay = modal.querySelector('.app-modal-overlay');
    const dismissButtons = modal.querySelectorAll('[data-dismiss-modal]');
    const grid = modal.querySelector('.app-image-library-grid');
    const loadingEl = modal.querySelector('.app-image-library-loading');
    const emptyEl = modal.querySelector('.app-image-library-empty');
    const errorEl = modal.querySelector('.app-image-library-error');

    // Tab elements
    const tabButtons = modal.querySelectorAll('.app-tab-btn');
    const uploadTab = document.getElementById('tab-upload');
    const libraryTab = document.getElementById('tab-library');

    // Upload elements
    const uploadArea = modal.querySelector('.app-upload-area');
    const fileInput = document.getElementById('modal-file-input');
    const selectFileBtn = document.getElementById('select-file-btn');
    const uploadPrompt = modal.querySelector('.app-upload-prompt');
    const uploadPreview = modal.querySelector('.app-upload-preview');
    const uploadPreviewImg = document.getElementById('upload-preview-img');
    const uploadAltText = document.getElementById('upload-alt-text');
    const uploadAltError = document.getElementById('upload-alt-error');
    const uploadSubmitBtn = document.getElementById('upload-submit-btn');
    const uploadCancelBtn = document.getElementById('upload-cancel-btn');

    let imagesCache = null;
    let selectedFile = null;

    /**
     * Open the modal with configuration
     * @param {Object} config - Configuration object
     * @param {string} config.imageType - Type of image (profile, cover, post, etc.)
     * @param {string} config.targetPreview - ID of preview element to update
     * @param {string} config.targetAltInput - ID of alt-text input to update
     * @param {string} config.targetUrlInput - ID of hidden input to store URL JSON
     */
    function openModal(config = {}) {
        // Apply configuration to modal
        if (config.imageType) {
            modal.dataset.imageType = config.imageType;
        }
        if (config.targetPreview) {
            modal.dataset.targetPreview = config.targetPreview;
        }
        if (config.targetAltInput) {
            modal.dataset.targetAltInput = config.targetAltInput;
        }
        if (config.targetUrlInput) {
            modal.dataset.targetUrlInput = config.targetUrlInput;
        }

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';

        // Load library images when switching to library tab or if not cached
        if (imagesCache === null) {
            loadImages();
        }
    }

    /**
     * Close the modal
     */
    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
        resetUploadForm();
    }

    /**
     * Switch between tabs
     */
    function switchTab(tabName) {
        // Update tab buttons
        tabButtons.forEach(btn => {
            const isActive = btn.dataset.tab === tabName;
            btn.classList.toggle('app-tab-active', isActive);
            btn.style.borderBottomColor = isActive ? '#3b82f6' : 'transparent';
            btn.style.color = isActive ? '' : '#6b7280';
            btn.style.fontWeight = isActive ? '500' : '400';
        });

        // Update tab content
        uploadTab.style.display = tabName === 'upload' ? 'block' : 'none';
        libraryTab.style.display = tabName === 'library' ? 'block' : 'none';

        // Load library images if switching to library tab
        if (tabName === 'library' && imagesCache === null) {
            loadImages();
        }
    }

    /**
     * Load images from API
     */
    async function loadImages() {
        const imageType = modal.dataset.imageType || '';
        const userId = modal.dataset.userId || '';

        if (!userId) {
            showError();
            return;
        }

        showLoading();

        try {
            const params = new URLSearchParams();
            if (imageType) {
                params.append('type', imageType);
            }
            params.append('limit', '50');

            const response = await fetch(`/api/user/images?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Failed to fetch images');
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Failed to fetch images');
            }

            imagesCache = data.images || [];
            renderImages(imagesCache);
        } catch (error) {
            console.error('Error loading images:', error);
            showError();
        }
    }

    /**
     * Render images in the grid
     */
    function renderImages(images) {
        hideAll();

        if (images.length === 0) {
            emptyEl.style.display = 'block';
            return;
        }

        grid.style.display = 'grid';
        grid.innerHTML = '';

        images.forEach(image => {
            const item = createImageItem(image);
            grid.appendChild(item);
        });
    }

    /**
     * Create a single image item
     */
    function createImageItem(image) {
        const urls = typeof image.urls === 'string' ? JSON.parse(image.urls) : image.urls;
        const thumbnailUrl = urls?.thumb || urls?.small || urls?.original || '';

        const item = document.createElement('div');
        item.className = 'app-image-library-item';
        item.style.cssText = 'position: relative; cursor: pointer; border: 2px solid transparent; border-radius: 8px; overflow: hidden; transition: border-color 0.2s;';

        item.innerHTML = `
            <img
                src="${escapeHtml(thumbnailUrl)}"
                alt="${escapeHtml(image.alt_text || '')}"
                style="width: 100%; height: 150px; object-fit: cover; display: block;"
            />
            <div style="padding: 0.5rem; background: #f9fafb;">
                <div style="font-size: 0.75rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    ${escapeHtml(image.alt_text || 'No description')}
                </div>
                <div style="font-size: 0.625rem; color: #9ca3af; margin-top: 0.25rem;">
                    ${formatDate(image.created_at)}
                </div>
            </div>
        `;

        item.addEventListener('click', () => selectImage(image, item));

        item.addEventListener('mouseenter', () => {
            item.style.borderColor = '#3b82f6';
        });

        item.addEventListener('mouseleave', () => {
            item.style.borderColor = 'transparent';
        });

        return item;
    }

    /**
     * Handle image selection
     */
    function selectImage(image, itemElement) {
        const urls = typeof image.urls === 'string' ? JSON.parse(image.urls) : image.urls;

        // Update using the same function as upload
        updateTargetElements(urls, image.alt_text || '');

        // Visual feedback
        const allItems = grid.querySelectorAll('.app-image-library-item');
        allItems.forEach(item => {
            item.style.borderColor = 'transparent';
        });
        itemElement.style.borderColor = '#10b981';
        itemElement.style.borderWidth = '3px';

        // Close modal after selection
        setTimeout(() => {
            closeModal();
        }, 300);
    }

    /**
     * Show loading state
     */
    function showLoading() {
        hideAll();
        loadingEl.style.display = 'block';
    }

    /**
     * Show error state
     */
    function showError() {
        hideAll();
        errorEl.style.display = 'block';
    }

    /**
     * Hide all states
     */
    function hideAll() {
        loadingEl.style.display = 'none';
        emptyEl.style.display = 'none';
        errorEl.style.display = 'none';
        grid.style.display = 'none';
    }

    /**
     * Format date for display
     */
    function formatDate(dateString) {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        } catch {
            return dateString;
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Handle file selection (button or drag-drop)
     */
    function handleFileSelection(file) {
        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file (JPEG, PNG, GIF, or WebP).');
            return;
        }

        // Validate file size (10MB)
        const maxSize = 10 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('Image file must be less than 10MB.');
            return;
        }

        selectedFile = file;

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            uploadPreviewImg.src = e.target.result;
            uploadPrompt.style.display = 'none';
            uploadPreview.style.display = 'block';
            uploadAltText.value = '';
            uploadAltError.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    /**
     * Reset upload form
     */
    function resetUploadForm() {
        fileInput.value = '';
        selectedFile = null;
        uploadPrompt.style.display = 'block';
        uploadPreview.style.display = 'none';
        uploadAltText.value = '';
        uploadAltError.style.display = 'none';
    }

    /**
     * Upload file to server
     */
    async function uploadFile() {
        if (!selectedFile) {
            return;
        }

        const altText = uploadAltText.value.trim();
        if (!altText) {
            uploadAltError.textContent = 'Image description is required for accessibility.';
            uploadAltError.style.display = 'block';
            return;
        }

        uploadAltError.style.display = 'none';
        uploadSubmitBtn.disabled = true;
        uploadSubmitBtn.textContent = 'Uploading...';

        try {
            const imageType = modal.dataset.imageType || 'post';
            const formData = new FormData();

            formData.append('image', selectedFile);
            formData.append('alt_text', altText);
            formData.append('image_type', imageType);

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) {
                formData.append('nonce', csrfToken);
            }

            const response = await fetch('/api/images/upload', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                // Update preview and inputs with uploaded image
                updateTargetElements(data.urls, altText);

                // Clear cache to reload library
                imagesCache = null;

                // Show success and close
                closeModal();
            } else {
                throw new Error(data.error || 'Upload failed');
            }
        } catch (error) {
            console.error('Upload error:', error);
            uploadAltError.textContent = error.message || 'Failed to upload image. Please try again.';
            uploadAltError.style.display = 'block';
        } finally {
            uploadSubmitBtn.disabled = false;
            uploadSubmitBtn.textContent = 'Upload & Select';
        }
    }

    /**
     * Update target elements with selected image
     */
    function updateTargetElements(imageUrl, altText) {
        const urls = typeof imageUrl === 'string' ? JSON.parse(imageUrl) : imageUrl;

        // Update URL hidden input (for form submission)
        const targetUrlInput = modal.dataset.targetUrlInput;
        const urlInputEl = document.getElementById(targetUrlInput);
        if (urlInputEl) {
            urlInputEl.value = typeof imageUrl === 'string' ? imageUrl : JSON.stringify(urls);
        }

        // Update preview
        const targetPreview = modal.dataset.targetPreview;
        const previewEl = document.getElementById(targetPreview);
        if (previewEl) {
            const previewUrl = urls?.medium || urls?.original || '';
            if (previewEl.tagName === 'IMG') {
                previewEl.src = previewUrl;
                previewEl.style.display = 'block';
            } else if (previewEl.tagName === 'DIV') {
                const img = document.createElement('img');
                img.id = targetPreview;
                img.src = previewUrl;
                img.alt = altText || '';
                img.className = previewEl.className;
                previewEl.parentNode.replaceChild(img, previewEl);
            }
        }

        // Update alt-text input
        const targetAltInput = modal.dataset.targetAltInput;
        const altInputEl = document.getElementById(targetAltInput);
        if (altInputEl) {
            altInputEl.value = altText || '';
        }
    }

    // Event listeners for tabs
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    // Event listeners for file upload
    if (selectFileBtn) {
        selectFileBtn.addEventListener('click', () => fileInput.click());
    }

    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files[0]) {
                handleFileSelection(e.target.files[0]);
            }
        });
    }

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = '#3b82f6';
        uploadArea.style.backgroundColor = '#eff6ff';
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.style.borderColor = '#d1d5db';
        uploadArea.style.backgroundColor = '#f9fafb';
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = '#d1d5db';
        uploadArea.style.backgroundColor = '#f9fafb';

        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileSelection(e.dataTransfer.files[0]);
        }
    });

    if (uploadSubmitBtn) {
        uploadSubmitBtn.addEventListener('click', uploadFile);
    }
    if (uploadCancelBtn) {
        uploadCancelBtn.addEventListener('click', resetUploadForm);
    }

    // Event listeners for modal dismissal
    dismissButtons.forEach(button => {
        button.addEventListener('click', closeModal);
    });

    modalOverlay.addEventListener('click', closeModal);

    // ESC key to close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });

    // Expose global function to open modal
    window.appOpenImageLibrary = openModal;

})();
