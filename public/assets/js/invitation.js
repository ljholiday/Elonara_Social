/**
 * Elonara Social Invitation Acceptance
 * Handles accepting community invitations via URL parameter
 */

document.addEventListener('DOMContentLoaded', function() {
    handleInvitationAcceptance();
});

/**
 * Check for invitation token in URL and auto-accept
 */
function handleInvitationAcceptance() {
    const urlParams = new URLSearchParams(window.location.search);
    const invitationToken = urlParams.get('invitation') || urlParams.get('token');

    if (!invitationToken) {
        return;
    }

    // Check if user is logged in by checking for CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) {
        // User not logged in - redirect to login with return URL
        const returnUrl = encodeURIComponent(window.location.href);
        window.location.href = '/login?redirect=' + returnUrl;
        return;
    }

    // Show loading state
    showInvitationStatus('Accepting invitation...', 'info');

    // Accept the invitation
    fetch('/api/invitations/accept', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'token=' + encodeURIComponent(invitationToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showInvitationStatus(data.message || 'Welcome to the community!', 'success');

            // Remove invitation parameter from URL
            const url = new URL(window.location);
            url.searchParams.delete('invitation');
            url.searchParams.delete('token');
            window.history.replaceState({}, '', url);

            // Reload page after 2 seconds to show updated member status
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showInvitationStatus(data.message || 'Failed to accept invitation', 'error');
        }
    })
    .catch(error => {
        console.error('Error accepting invitation:', error);
        showInvitationStatus('An error occurred while accepting the invitation', 'error');
    });
}

/**
 * Show invitation status message
 */
function showInvitationStatus(message, type) {
    // Remove any existing status messages
    const existing = document.querySelector('.app-invitation-status');
    if (existing) {
        existing.remove();
    }

    // Create status message
    const statusDiv = document.createElement('div');
    statusDiv.className = 'app-invitation-status app-alert app-alert-' + type;
    statusDiv.textContent = message;
    statusDiv.style.position = 'fixed';
    statusDiv.style.top = '20px';
    statusDiv.style.right = '20px';
    statusDiv.style.zIndex = '9999';
    statusDiv.style.maxWidth = '400px';

    document.body.appendChild(statusDiv);

    // Auto-remove after 5 seconds for non-info messages
    if (type !== 'info') {
        setTimeout(() => {
            statusDiv.remove();
        }, 5000);
    }
}
