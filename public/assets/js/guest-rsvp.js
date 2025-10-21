(function() {
	'use strict';

	const form = document.querySelector('[data-guest-rsvp-form]');
	if (!form) {
		return;
	}

	const statusRadios = form.querySelectorAll('input[name="rsvp_status"]');
	const detailsSection = form.querySelector('[data-rsvp-details]');
	const plusOneRadios = form.querySelectorAll('input[name="plus_one"]');
	const plusOneContainers = form.querySelectorAll('[data-plus-one-name]');
	const nameInput = form.querySelector('#guest_name');

	function toggleDetails() {
		if (!detailsSection) {
			return;
		}

		const selected = form.querySelector('input[name="rsvp_status"]:checked');
		if (!selected || selected.value === 'no') {
			detailsSection.classList.add('app-hidden');
			if (nameInput) {
				nameInput.removeAttribute('required');
			}
		} else {
			detailsSection.classList.remove('app-hidden');
			if (nameInput) {
				nameInput.setAttribute('required', 'required');
			}
		}
	}

	function togglePlusOne() {
		if (plusOneContainers.length === 0) {
			return;
		}

		const selected = form.querySelector('input[name="plus_one"]:checked');
		plusOneContainers.forEach(function(container) {
			if (selected && selected.value === '1') {
				container.classList.remove('app-hidden');
			} else {
				container.classList.add('app-hidden');
			}
		});
	}

	statusRadios.forEach(function(radio) {
		radio.addEventListener('change', toggleDetails);
	});

	plusOneRadios.forEach(function(radio) {
		radio.addEventListener('change', togglePlusOne);
	});

	toggleDetails();
	togglePlusOne();
})();
