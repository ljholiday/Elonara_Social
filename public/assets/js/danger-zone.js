(function() {
	'use strict';

	const forms = document.querySelectorAll('[data-danger-zone-form]');
	if (forms.length === 0) {
		return;
	}

	forms.forEach(function(form) {
		form.addEventListener('submit', function(event) {
			const entityName = form.dataset.entityName || '';
			const confirmationType = form.dataset.confirmationType || 'confirm';

			if (confirmationType === 'type_name') {
				const input = form.querySelector('[data-confirm-name-input]');
				if (!input || input.value !== entityName) {
					event.preventDefault();
					window.alert('The name you entered does not match. Deletion cancelled.');
					return;
				}
			}

			const confirmed = window.confirm(
				'Are you sure you want to delete "' + entityName + '"? This action cannot be undone.'
			);

			if (!confirmed) {
				event.preventDefault();
			}
		});
	});
})();
