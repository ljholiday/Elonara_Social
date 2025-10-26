/**
 * Profile Edit Form Module
 *
 * Handles AJAX form submission for profile editing to prevent file input clearing on validation errors.
 */

(function() {
    'use strict';

    const form = document.getElementById('profile-edit-form');
    if (!form) {
        return; // Not on profile edit page
    }

    const submitButton = form.querySelector('button[type="submit"]');
    const errorContainer = document.getElementById('profile-errors');
    const successContainer = document.getElementById('profile-success');

    /**
     * Handle form submission
     */
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Disable submit button and show loading state
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.originalText = submitButton.textContent;
            submitButton.textContent = 'Saving...';
        }

        // Clear previous messages
        clearMessages();

        try {
            const formData = new FormData(form);

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                showSuccess(data.message || 'Profile updated successfully!');

                // Update preview images if new ones were uploaded
                if (data.user) {
                    updateUserData(data.user);
                }

                // Clear file inputs after successful upload
                const fileInputs = form.querySelectorAll('input[type="file"]');
                fileInputs.forEach(input => {
                    input.value = '';
                });
            } else {
                if (data.errors) {
                    showErrors(data.errors);
                } else if (data.error) {
                    showErrors({ general: data.error });
                }
            }
        } catch (error) {
            console.error('Profile update error:', error);
            showErrors({ general: 'An error occurred. Please try again.' });
        } finally {
            // Re-enable submit button
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = submitButton.dataset.originalText || 'Save Changes';
            }
        }
    });

    /**
     * Show success message
     */
    function showSuccess(message) {
        if (successContainer) {
            successContainer.innerHTML = `
                <div class="app-alert app-alert-success app-mb-4">
                    ${escapeHtml(message)}
                </div>
            `;
            successContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Show validation errors
     */
    function showErrors(errors) {
        // Clear existing field errors
        const fieldErrors = form.querySelectorAll('.app-field-error');
        fieldErrors.forEach(el => el.remove());

        const invalidInputs = form.querySelectorAll('.is-invalid');
        invalidInputs.forEach(el => el.classList.remove('is-invalid'));

        // Show errors
        if (errorContainer) {
            const errorList = Object.values(errors).map(msg =>
                `<li>${escapeHtml(msg)}</li>`
            ).join('');

            errorContainer.innerHTML = `
                <div class="app-alert app-alert-error app-mb-4">
                    <ul>${errorList}</ul>
                </div>
            `;
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Add field-specific errors
        Object.keys(errors).forEach(fieldName => {
            const input = form.querySelector(`[name="${fieldName}"]`);
            if (input) {
                input.classList.add('is-invalid');

                const errorDiv = document.createElement('div');
                errorDiv.className = 'app-field-error';
                errorDiv.textContent = errors[fieldName];
                input.parentNode.appendChild(errorDiv);
            }
        });
    }

    /**
     * Clear all messages
     */
    function clearMessages() {
        if (errorContainer) {
            errorContainer.innerHTML = '';
        }
        if (successContainer) {
            successContainer.innerHTML = '';
        }

        const fieldErrors = form.querySelectorAll('.app-field-error');
        fieldErrors.forEach(el => el.remove());

        const invalidInputs = form.querySelectorAll('.is-invalid');
        invalidInputs.forEach(el => el.classList.remove('is-invalid'));
    }

    /**
     * Update user data in the DOM after successful save
     */
    function updateUserData(user) {
        // Update avatar preview if it exists on the page
        const avatarElements = document.querySelectorAll('[data-user-avatar]');
        if (user.avatar_url) {
            const avatarData = typeof user.avatar_url === 'string' ? JSON.parse(user.avatar_url) : user.avatar_url;
            const avatarUrl = avatarData?.medium || avatarData?.original || '';

            avatarElements.forEach(el => {
                if (el.tagName === 'IMG') {
                    el.src = avatarUrl;
                }
            });
        }

        // Update other user fields as needed
        if (user.display_name) {
            const displayNameElements = document.querySelectorAll('[data-user-display-name]');
            displayNameElements.forEach(el => {
                el.textContent = user.display_name;
            });
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

})();
