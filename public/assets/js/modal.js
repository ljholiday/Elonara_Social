/**
 * Modal Utilities
 * Reusable modal functions for Elonara Social
 */

(function() {
	'use strict';

	/**
	 * Show error modal with copyable text
	 */
	window.vtShowError = function(message, title) {
		title = title || 'Error';

		const modal = document.createElement('div');
		modal.className = 'app-modal';
		modal.innerHTML = `
			<div class="app-modal-overlay"></div>
			<div class="app-modal-content">
				<div class="app-modal-header">
					<h3 class="app-modal-title">${escapeHtml(title)}</h3>
					<button type="button" class="app-btn app-btn-sm" data-dismiss="modal">&times;</button>
				</div>
				<div class="app-modal-body">
					<p>${escapeHtml(message)}</p>
				</div>
				<div class="app-modal-footer">
					<button type="button" class="app-btn" data-dismiss="modal">Close</button>
				</div>
			</div>
		`;

		document.body.appendChild(modal);
		document.body.classList.add('app-modal-open');

		// Close handlers
		const closeButtons = modal.querySelectorAll('[data-dismiss="modal"]');
		const overlay = modal.querySelector('.app-modal-overlay');

		closeButtons.forEach(btn => {
			btn.addEventListener('click', function() {
				closeModal(modal);
			});
		});

		overlay.addEventListener('click', function() {
			closeModal(modal);
		});

		// ESC key
		function handleEscape(e) {
			if (e.key === 'Escape') {
				closeModal(modal);
				document.removeEventListener('keydown', handleEscape);
			}
		}
		document.addEventListener('keydown', handleEscape);
	};

	/**
	 * Show success modal
	 */
	window.vtShowSuccess = function(message, title) {
		title = title || 'Success';
		vtShowError(message, title);
	};

	/**
	 * Close modal
	 */
	function closeModal(modal) {
		document.body.classList.remove('app-modal-open');
		modal.remove();
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
})();