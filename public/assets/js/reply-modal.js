(function() {
	'use strict';

	const modal = document.getElementById('reply-modal');
	if (!modal) {
		return;
	}

	const openBtn = document.querySelector('[data-open-reply-modal]');
	const closeBtns = modal.querySelectorAll('[data-dismiss-modal]');
	const overlay = modal.querySelector('.app-modal-overlay');
	const form = document.getElementById('reply-form');
	const modalTitle = document.getElementById('reply-modal-title');
	const submitBtn = document.getElementById('reply-submit-btn');
	const modeInput = document.getElementById('reply-mode');
	const replyIdInput = document.getElementById('reply-id');
	const contentTextarea = document.getElementById('reply-content');
	const imageInput = document.getElementById('reply-image');
	const imageAltInput = document.getElementById('image-alt');
	const existingImagePreview = document.getElementById('existing-image-preview');
	const existingImage = document.getElementById('existing-image');
	const existingImageAlt = document.getElementById('existing-image-alt');
	const replyImagePreview = document.getElementById('reply-image-preview');
	const replyImagePreviewContainer = document.getElementById('reply-image-preview-container');
	const replyImageAltDisplay = document.getElementById('reply-image-alt-display');
	const replyImageAltHidden = document.getElementById('reply-image-alt-hidden');

	function openModal() {
		modal.style.display = 'block';
		document.body.classList.add('app-modal-open');
	}

	function closeModal() {
		modal.style.display = 'none';
		document.body.classList.remove('app-modal-open');
		resetModal();
	}

	function resetModal() {
		if (form) {
			form.reset();
		}
		if (modeInput) {
			modeInput.value = 'create';
		}
		if (replyIdInput) {
			replyIdInput.value = '';
		}
		if (modalTitle) {
			modalTitle.textContent = 'Add Reply';
		}
		if (submitBtn) {
			submitBtn.textContent = 'Post Reply';
		}
		if (existingImagePreview) {
			existingImagePreview.style.display = 'none';
		}
		if (replyImagePreviewContainer) {
			replyImagePreviewContainer.style.display = 'none';
		}
		// Reset form action to create endpoint
		const conversationSlug = modal.dataset.conversationSlug;
		if (form && conversationSlug) {
			form.action = '/conversations/' + conversationSlug + '/reply';
		}
	}

	// Open modal for creating a new reply
	if (openBtn) {
		openBtn.addEventListener('click', function() {
			resetModal();
			openModal();
		});
	}

	// Open modal for editing an existing reply
	window.openReplyModalForEdit = function(replyId, content, imageUrl, imageAlt) {
		resetModal();

		if (modeInput) {
			modeInput.value = 'edit';
		}
		if (replyIdInput) {
			replyIdInput.value = replyId;
		}
		if (modalTitle) {
			modalTitle.textContent = 'Edit Reply';
		}
		if (submitBtn) {
			submitBtn.textContent = 'Update Reply';
		}
		if (contentTextarea) {
			contentTextarea.value = content || '';
		}
		if (imageAltInput) {
			imageAltInput.value = imageAlt || '';
		}

		// Show existing image if present
		if (imageUrl && existingImagePreview && existingImage) {
			existingImage.src = imageUrl;
			existingImage.alt = imageAlt || '';
			if (existingImageAlt) {
				existingImageAlt.textContent = imageAlt ? 'Alt text: ' + imageAlt : '';
			}
			existingImagePreview.style.display = 'block';
		}

		// Update form action to edit endpoint
		if (form) {
			form.action = '/api/replies/' + replyId + '/edit';
		}

		openModal();
	};

	// Handle form submission
	if (form) {
		form.addEventListener('submit', function(e) {
			const mode = modeInput ? modeInput.value : 'create';

			// For edit mode, use AJAX to submit and stay on page
			if (mode === 'edit') {
				e.preventDefault();

				const replyId = replyIdInput ? replyIdInput.value : '';
				const formData = new FormData(form);

				// Get CSRF token from meta tag
				const nonce = document.querySelector('meta[name="csrf-token"]')?.content;
				if (nonce) {
					formData.set('nonce', nonce);
				}

				// Disable submit button during request
				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = 'Updating...';
				}

				fetch('/api/replies/' + replyId + '/edit', {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						// Reload page to show updated reply
						window.location.reload();
					} else {
						alert(data.message || 'Failed to update reply');
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = 'Update Reply';
						}
					}
				})
				.catch(error => {
					console.error('Error updating reply:', error);
					alert('Network error. Please try again.');
					if (submitBtn) {
						submitBtn.disabled = false;
						submitBtn.textContent = 'Update Reply';
					}
				});
			}
			// For create mode, let the form submit normally (page reload with validation errors if any)
		});
	}

	closeBtns.forEach(function(btn) {
		btn.addEventListener('click', function() {
			closeModal();
		});
	});

	if (overlay) {
		overlay.addEventListener('click', function() {
			closeModal();
		});
	}

	document.addEventListener('keydown', function(event) {
		if (event.key === 'Escape' && modal.style.display === 'block') {
			closeModal();
		}
	});

	// Watch for image library updates
	if (replyImagePreview && replyImagePreviewContainer && replyImageAltHidden && replyImageAltDisplay) {
		// Use MutationObserver to watch when image library updates the preview
		const observer = new MutationObserver(() => {
			if (replyImagePreview.src && replyImagePreview.src !== '') {
				replyImagePreviewContainer.style.display = 'block';
				const altText = replyImageAltHidden.value;
				replyImageAltDisplay.textContent = altText ? 'Alt: ' + altText : '';
			}
		});
		observer.observe(replyImagePreview, { attributes: true, attributeFilter: ['src'] });

		// Also watch for changes to alt text input
		const altObserver = new MutationObserver(() => {
			const altText = replyImageAltHidden.value;
			replyImageAltDisplay.textContent = altText ? 'Alt: ' + altText : '';
		});
		altObserver.observe(replyImageAltHidden, { attributes: true, attributeFilter: ['value'] });
	}

	if (modal.dataset.autoOpen === '1') {
		openModal();
	}
})();
