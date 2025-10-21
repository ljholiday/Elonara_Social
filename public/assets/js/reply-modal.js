(function() {
	'use strict';

	const modal = document.getElementById('reply-modal');
	if (!modal) {
		return;
	}

	const openBtn = document.querySelector('[data-open-reply-modal]');
	const closeBtns = modal.querySelectorAll('[data-dismiss-modal]');
	const overlay = modal.querySelector('.app-modal-overlay');
	const form = modal.querySelector('form');

	function openModal() {
		modal.style.display = 'block';
		document.body.classList.add('app-modal-open');
	}

	function closeModal() {
		modal.style.display = 'none';
		document.body.classList.remove('app-modal-open');
		if (form) {
			form.reset();
		}
	}

	if (openBtn) {
		openBtn.addEventListener('click', function() {
			openModal();
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

	if (modal.dataset.autoOpen === '1') {
		openModal();
	}
})();
