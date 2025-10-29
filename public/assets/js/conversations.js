/**
 * Conversations - Circles of Trust Filtering
 */

(function() {
	'use strict';

	const nav = document.querySelector('.app-nav');
	const list = document.getElementById('app-convo-list');
	const circleStatus = document.getElementById('app-circle-status');

	if (!nav || !list) {
		return;
	}

	// Get nonce from meta tag
	const nonce = document.querySelector('meta[name="csrf-token"]')?.content;

	// Track current state
	let currentCircle = 'inner';
	let currentFilter = '';

	function loadConversations(options = {}) {
		const circle = options.circle || currentCircle;
		const filter = options.filter !== undefined ? options.filter : currentFilter;
		const page = options.page || 1;

		// Update current state
		currentCircle = circle;
		currentFilter = filter;

		// Add loading state with visual feedback
		list.classList.add('app-is-loading');
		list.style.opacity = '0.5';

		if (circleStatus) {
			const loadingText = filter ? circle + ' circle - ' + filter : circle + ' circle';
			circleStatus.innerHTML = '<span class="app-text-muted">Loading ' + loadingText + '...</span>';
		}

		// Prepare form data
		const formData = new FormData();
		formData.append('nonce', nonce);
		formData.append('circle', circle);
		formData.append('page', page);
		if (filter) {
			formData.append('filter', filter);
		}

		// Make AJAX request
		fetch('/api/conversations', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin'
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				list.innerHTML = data.data.html;

				// Update circle status with metadata
				if (circleStatus && data.data.meta) {
					const meta = data.data.meta;

					// Format display text
					let displayText = '';
					if (filter === 'my-events') {
						displayText = 'My Events';
					} else if (filter === 'all-events') {
						displayText = 'All Events';
					} else {
						const circleLabel = circle.charAt(0).toUpperCase() + circle.slice(1);
						displayText = circle === 'all' ? 'All' : circleLabel + ' Circle';
						if (filter) {
							displayText += ' - ' + filter.charAt(0).toUpperCase() + filter.slice(1);
						}
					}

					circleStatus.innerHTML =
						'<strong class="app-text-primary">' + displayText + '</strong> ' +
						'<span class="app-text-muted">(' + meta.count + ' conversation' + (meta.count !== 1 ? 's' : '') + ')</span>';
				}
			} else {
				list.innerHTML = '<div class="app-text-center app-p-4"><p class="app-text-muted">Error: ' + (data.message || 'Unknown error') + '</p></div>';
			}
		})
		.catch(error => {
			console.error('Error loading conversations:', error);
			list.innerHTML = '<div class="app-text-center app-p-4"><p class="app-text-muted">Network error. Please try again.</p></div>';
		})
		.finally(() => {
			list.classList.remove('app-is-loading');
			list.style.opacity = '1';
		});
	}

	// Intercept circle navigation links
	nav.addEventListener('click', function(e) {
		const link = e.target.closest('a.app-nav-item');
		if (!link) return;

		const url = new URL(link.href);
		const circle = url.searchParams.get('circle');

		// Only intercept circle filter links
		if (circle && ['all', 'inner', 'trusted', 'extended'].includes(circle)) {
			e.preventDefault();

			// Update active states
			nav.querySelectorAll('a.app-nav-item').forEach(item => {
				item.classList.remove('active');
			});
			link.classList.add('active');

			// Load conversations for selected circle
			loadConversations({ circle: circle, filter: '' });

			// Update URL without reload
			history.pushState({ circle: circle }, '', url);
		}
	});

	// Don't reload on page load - use server-rendered content
	// Only reload when user clicks buttons
})();

/**
 * Edit reply - opens modal with existing reply data
 */
window.editReply = function(buttonElement) {
	const replyId = buttonElement.dataset.replyId;
	const content = buttonElement.dataset.replyContent || '';
	const imageUrl = buttonElement.dataset.replyImageUrl || '';
	const imageAlt = buttonElement.dataset.replyImageAlt || '';

	// Call the global function from reply-modal.js
	if (typeof window.openReplyModalForEdit === 'function') {
		window.openReplyModalForEdit(replyId, content, imageUrl, imageAlt);
	} else {
		console.error('Reply modal edit function not available');
	}
};

/**
 * Delete reply
 */
window.deleteReply = function(replyId) {
	if (!confirm('Are you sure you want to delete this reply?')) {
		return;
	}

	// Get CSRF token
	const nonce = document.querySelector('meta[name="csrf-token"]')?.content;

	// Prepare form data
	const formData = new FormData();
	formData.append('nonce', nonce);

	fetch(`/api/replies/${replyId}/delete`, {
		method: 'POST',
		body: formData
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			// Remove reply from DOM
			const replyCard = document.querySelector(`article:has(button[onclick*="deleteReply(${replyId})"])`);
			if (replyCard) {
				replyCard.remove();
			}
		} else {
			alert(data.message || 'Failed to delete reply');
		}
	})
	.catch(error => {
		console.error('Error deleting reply:', error);
		alert('Network error. Please try again.');
	});
};
