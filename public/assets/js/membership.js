/**
 * Elonara Social Membership Management
 * Handles invitations, member/guest management, and community features
 */

document.addEventListener('DOMContentLoaded', function() {
    // Invitation acceptance (from email links)
    handleInvitationAcceptance();

    // Community and event management
    initCommunityTabs();
    initJoinButtons();
    initInvitationCopy();
    initInvitationForm();
    initPendingInvitations();
    initEventGuestsSection();
});

// ============================================================================
// INVITATION ACCEPTANCE (from email links)
// ============================================================================

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

// ============================================================================
// COMMUNITY FEATURES
// ============================================================================

/**
 * Initialize tab functionality for communities
 */
function initCommunityTabs() {
    const communityTabs = document.querySelectorAll('[data-filter]');
    const communityTabContents = document.querySelectorAll('.app-communities-tab-content');

    if (!communityTabs.length) {
        return;
    }

    communityTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const filter = this.getAttribute('data-filter');

            // Update active tab
            communityTabs.forEach(t => t.classList.remove('app-active'));
            this.classList.add('app-active');

            // Show corresponding content
            communityTabContents.forEach(content => {
                if (content.getAttribute('data-filter') === filter) {
                    content.style.display = 'block';
                } else {
                    content.style.display = 'none';
                }
            });
        });
    });
}

/**
 * Initialize join button handlers
 */
function initJoinButtons() {
    const joinButtons = document.querySelectorAll('.app-join-btn');

    joinButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const communityId = this.getAttribute('data-community-id');
            const actionUrl = '/api/communities/' + communityId + '/join';

            fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'nonce=' + encodeURIComponent(getCSRFToken())
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Unable to join community');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });
}

/**
 * Get CSRF token from meta tag
 */
function getCSRFToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================================
// INVITATION URL COPYING
// ============================================================================

/**
 * Initialize invitation URL copy buttons
 */
function initInvitationCopy() {
    const copyButtons = document.querySelectorAll('.app-copy-invitation-url');

    copyButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            copyInvitationUrl(url);
        });
    });
}

// ============================================================================
// MEMBER MANAGEMENT
// ============================================================================

/**
 * Change a member's role
 */
function changeMemberRole(memberId, newRole, communityId) {
    if (!confirm('Change this member\'s role to ' + newRole + '?')) {
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId + '/role', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'role=' + encodeURIComponent(newRole) + '&nonce=' + encodeURIComponent(getCSRFToken())
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.data && data.data.html) {
                refreshMemberTable(communityId, data.data.html);
            } else {
                window.location.reload();
            }
        } else {
            alert(data.message || 'Failed to change role');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

/**
 * Remove a member from the community
 */
function removeMember(memberId, memberName, communityId) {
    if (!confirm('Remove ' + memberName + ' from this community?')) {
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'nonce=' + encodeURIComponent(getCSRFToken())
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.data && data.data.html) {
                refreshMemberTable(communityId, data.data.html);
            } else {
                window.location.reload();
            }
        } else {
            alert(data.message || 'Failed to remove member');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

/**
 * Refresh the member table with new HTML
 */
function refreshMemberTable(communityId, html) {
    const memberTable = document.getElementById('community-members-table');
    if (memberTable) {
        memberTable.innerHTML = html;
    } else {
        window.location.reload();
    }
}

// ============================================================================
// INVITATION FORM (sending invitations)
// ============================================================================

/**
 * Initialize invitation form submission
 */
function initInvitationForm() {
    const form = document.getElementById('send-invitation-form');
    if (!form) return;

    // Prevent duplicate event listeners
    if (form.dataset.invitationFormInitialized === 'true') return;
    form.dataset.invitationFormInitialized = 'true';

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const entityType = this.getAttribute('data-entity-type');
        const entityId = this.getAttribute('data-entity-id');
        const email = document.getElementById('invitation-email').value;
        const message = document.getElementById('invitation-message').value;

        if (!email) {
            alert('Please enter an email address');
            return;
        }

        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending...';

        const formData = new FormData();
        formData.append('email', email);
        if (message) {
            formData.append('message', message);
        }
        formData.append('nonce', getCSRFToken());

        const entityTypePlural = entityType === 'community' ? 'communities' : 'events';

        fetch(`/api/${entityTypePlural}/${entityId}/invitations`, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;

            // Clear form fields
            document.getElementById('invitation-email').value = '';
            document.getElementById('invitation-message').value = '';

            if (data.success) {
                const payload = data.data || {};
                alert(payload.message || 'Invitation sent successfully!');

                // Reload pending invitations if applicable
                loadPendingInvitations(entityType, entityId);
            } else {
                alert('Error: ' + (data.message || 'Failed to send invitation'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to send invitation. Please try again.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
}

/**
 * Initialize pending invitations loading
 */
function initPendingInvitations() {
    const form = document.getElementById('send-invitation-form');
    if (!form) return;

    const entityType = form.getAttribute('data-entity-type');
    const entityId = form.getAttribute('data-entity-id');

    if (entityType && entityId) {
        loadPendingInvitations(entityType, entityId);
    }
}

/**
 * Load and display pending invitations
 */
function loadPendingInvitations(entityType, entityId) {
    const invitationsList = document.getElementById('invitations-list');
    if (!invitationsList) return;

    const entityTypePlural = entityType === 'community' ? 'communities' : 'events';

    const nonce = getCSRFToken();
    fetch(`/api/${entityTypePlural}/${entityId}/invitations?nonce=${encodeURIComponent(nonce)}`, {
        method: 'GET'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const payload = data.data || {};
            if (payload.html) {
                invitationsList.innerHTML = payload.html;
            } else if (payload.invitations && payload.invitations.length > 0) {
                invitationsList.innerHTML = renderInvitationsList(payload.invitations, entityType);
            } else {
                invitationsList.innerHTML = '<div class="app-text-center app-text-muted">No pending invitations.</div>';
            }

            if (entityType === 'event') {
                updateEventGuestUI(payload.invitations || []);
            }
        } else {
            invitationsList.innerHTML = '<div class="app-text-center app-text-muted">Could not load invitations.</div>';
        }
    })
    .catch(error => {
        console.error('Error loading invitations:', error);
        invitationsList.innerHTML = '<div class="app-text-center app-text-muted">Error loading invitations.</div>';
    });
}

/**
 * Render invitations list HTML (for communities without server-side HTML)
 */
function renderInvitationsList(invitations, entityType) {
    let html = '<div class="app-invitations-list">';

    invitations.forEach(inv => {
        const email = escapeHtml(inv.invited_email || '');
        const token = escapeHtml(inv.invitation_token || '');
        const status = escapeHtml(inv.status || 'pending');
        const createdAt = inv.created_at ? new Date(inv.created_at).toLocaleDateString() : '';

        html += `
            <div class="app-invitation-item">
                <div class="app-invitation-details">
                    <strong>${email}</strong>
                    <span class="app-badge app-badge-${status}">${status}</span>
                    <small class="app-text-muted">Sent ${createdAt}</small>
                </div>
                <div class="app-invitation-actions">
                    <button type="button" class="app-btn app-btn-sm" onclick="copyInvitationUrl('${token}')">Copy Link</button>
                    <button type="button" class="app-btn app-btn-sm app-btn-danger" data-action="cancel" data-invitation-id="${inv.id}">Cancel</button>
                </div>
            </div>
        `;
    });

    html += '</div>';

    // Re-attach event handlers after rendering
    setTimeout(() => {
        attachInvitationActionHandlers(entityType, invitations[0]?.community_id || invitations[0]?.event_id);
    }, 0);

    return html;
}

/**
 * Attach invitation action handlers (cancel/resend)
 */
function attachInvitationActionHandlers(entityType, entityId) {
    const cancelButtons = document.querySelectorAll('[data-action="cancel"]');

    cancelButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const invitationId = this.getAttribute('data-invitation-id');

            if (!confirm('Cancel this invitation?')) {
                return;
            }

            const entityTypePlural = entityType === 'community' ? 'communities' : 'events';

            fetch(`/api/${entityTypePlural}/${entityId}/invitations/${invitationId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'nonce=' + encodeURIComponent(getCSRFToken())
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPendingInvitations(entityType, entityId);
                } else {
                    alert(data.message || 'Failed to cancel invitation');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });
}

/**
 * Copy invitation URL to clipboard
 */
function copyInvitationUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Invitation link copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy link. Please copy manually: ' + url);
    });
}

// ============================================================================
// EVENT GUEST MANAGEMENT
// ============================================================================

/**
 * Initialize event guests section
 */
function initEventGuestsSection() {
    const wrapper = document.getElementById('event-guests-section');
    const tableBody = document.getElementById('event-guests-body');
    if (!wrapper || !tableBody) {
        return;
    }

    const eventId = wrapper.getAttribute('data-event-id');
    if (eventId) {
        loadEventGuests(eventId);
    }
}

/**
 * Load event guests from API
 */
function loadEventGuests(eventId) {
    const tableBody = document.getElementById('event-guests-body');
    if (!tableBody) {
        return;
    }

    const nonce = getCSRFToken();
    fetch(`/api/events/${eventId}/guests?nonce=${encodeURIComponent(nonce)}`, {
        method: 'GET'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateEventGuestUI(data.data.invitations || data.data.guests || []);
        } else {
            showEventGuestError();
        }
    })
    .catch(error => {
        console.error('Error loading guests:', error);
        showEventGuestError();
    });
}

/**
 * Update event guest UI with data
 */
function updateEventGuestUI(guests) {
    const tableBody = document.getElementById('event-guests-body');
    const emptyState = document.getElementById('event-guests-empty');
    const totalDisplay = document.getElementById('event-guest-total');

    if (!tableBody) {
        return;
    }

    if (!Array.isArray(guests) || guests.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" class="app-text-center app-text-muted">No guests yet.</td></tr>';
        if (emptyState) {
            emptyState.style.display = 'block';
        }
        if (totalDisplay) {
            totalDisplay.textContent = '0';
        }
        return;
    }

    if (emptyState) {
        emptyState.style.display = 'none';
    }

    if (totalDisplay) {
        totalDisplay.textContent = String(guests.length);
    }

    const rows = guests.map(renderEventGuestRow).join('');
    tableBody.innerHTML = rows;

    const wrapper = document.getElementById('event-guests-section');
    const eventId = wrapper ? wrapper.getAttribute('data-event-id') : null;
    if (eventId) {
        attachEventGuestActionHandlers(eventId);
    }
}

function renderEventGuestRow(guest) {
    const name = escapeHtml(guest.name || guest.guest_name || guest.user_display_name || 'Guest');
    const email = escapeHtml(guest.email || guest.guest_email || guest.user_email || '');
    const statusValue = guest.status || 'pending';
    const status = mapGuestStatus(statusValue);
    const date = formatGuestDate(guest.rsvp_date || guest.created_at);
    const invitationId = guest.id || '';
    const rsvpToken = guest.rsvp_token || '';

    return `
        <tr>
            <td>${name}</td>
            <td>${email}</td>
            <td><span class="app-badge app-badge-${statusValue}">${status}</span></td>
            <td>${date}</td>
            <td class="app-flex app-gap-2">
                <button type="button" class="app-btn app-btn-sm" data-guest-action="copy" data-rsvp-token="${escapeHtml(rsvpToken)}">Copy Link</button>
                ${['pending', 'maybe'].includes(statusValue) ? `<button type="button" class="app-btn app-btn-sm app-btn-secondary" data-guest-action="resend" data-invitation-id="${invitationId}">Resend Email</button>` : ''}
                ${statusValue === 'pending' ? `<button type="button" class="app-btn app-btn-sm app-btn-danger" data-guest-action="cancel" data-invitation-id="${invitationId}">Remove</button>` : ''}
            </td>
        </tr>
    `;
}

function attachEventGuestActionHandlers(eventId) {
    const tableBody = document.getElementById('event-guests-body');
    if (!tableBody) {
        return;
    }

    tableBody.querySelectorAll('[data-guest-action="copy"]').forEach(button => {
        button.addEventListener('click', () => {
            const token = button.getAttribute('data-rsvp-token');
            if (!token) {
                return;
            }
            const baseUrl = window.location.origin || '';
            copyInvitationUrl(`${baseUrl}/rsvp/${token}`);
        });
    });

    tableBody.querySelectorAll('[data-guest-action="resend"]').forEach(button => {
        button.addEventListener('click', () => {
            const invitationId = button.getAttribute('data-invitation-id');
            if (!invitationId) {
                return;
            }
            resendEventInvitation(eventId, invitationId);
        });
    });

    tableBody.querySelectorAll('[data-guest-action="cancel"]').forEach(button => {
        button.addEventListener('click', () => {
            const invitationId = button.getAttribute('data-invitation-id');
            if (!invitationId) {
                return;
            }
            if (confirm('Remove this guest invitation?')) {
                cancelEventInvitation(eventId, invitationId);
            }
        });
    });
}

function resendEventInvitation(eventId, invitationId) {
    const nonce = getCSRFToken();
    fetch(`/api/events/${eventId}/invitations/${invitationId}/resend`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'nonce=' + encodeURIComponent(nonce)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadEventGuests(eventId);
        } else {
            alert(data.message || 'Unable to resend invitation.');
        }
    })
    .catch(error => {
        console.error('Error resending invitation:', error);
        alert('An error occurred while resending the invitation.');
    });
}

function cancelEventInvitation(eventId, invitationId) {
    const nonce = getCSRFToken();
    fetch(`/api/events/${eventId}/invitations/${invitationId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'nonce=' + encodeURIComponent(nonce)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadEventGuests(eventId);
        } else {
            alert(data.message || 'Unable to remove invitation.');
        }
    })
    .catch(error => {
        console.error('Error removing invitation:', error);
        alert('An error occurred while removing the invitation.');
    });
}

/**
 * Map guest status to display text
 */
function mapGuestStatus(status) {
    const statusMap = {
        'pending': 'Pending',
        'confirmed': 'Confirmed',
        'declined': 'Declined',
        'cancelled': 'Cancelled'
    };
    return statusMap[status] || status;
}

/**
 * Format guest date for display
 */
function formatGuestDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString();
}

/**
 * Show error message in guests section
 */
function showEventGuestError() {
    const tableBody = document.getElementById('event-guests-body');
    if (tableBody) {
        tableBody.innerHTML = '<tr><td colspan="5" class="app-alert app-alert-danger">We couldn\'t load this guest list right now. Please refresh and try again.</td></tr>';
    }
}
