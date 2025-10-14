/**
 * Elonara Social Communities
 * Handles community tab switching and join functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    initCommunityTabs();
    initJoinButtons();
    initInvitationCopy();
    initInvitationForm();
    initPendingInvitations();
    initEventGuestsSection();
});

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
        tab.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');

            // Update active tab
            communityTabs.forEach(t => {
                t.classList.remove('is-active');
                t.setAttribute('aria-selected', 'false');
            });
            this.classList.add('is-active');
            this.setAttribute('aria-selected', 'true');

            // Show/hide content
            communityTabContents.forEach(content => {
                const tab = content.getAttribute('data-tab');
                content.style.display = (tab === filter) ? '' : 'none';
            });
        });
    });
}

/**
 * Initialize join button functionality
 */
function initJoinButtons() {
    const joinBtns = document.querySelectorAll('.join-community-btn');
    joinBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            const communityId = this.getAttribute('data-community-id');
            const communityName = this.getAttribute('data-community-name');
            const isPersonalCommunity = this.textContent.trim() === 'Connect';

            const action = isPersonalCommunity ? 'Connect to' : 'Join';
            if (!confirm(`${action} community "${communityName}"?`)) {
                return;
            }

            this.disabled = true;
            this.textContent = 'Joining...';

            fetch('/api/communities/' + communityId + '/join', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'community_id=' + communityId + '&nonce=' + getCSRFToken()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.textContent = 'Member';
                    this.disabled = false;
                    this.onclick = () => window.location.href = '/communities/' + data.community_slug;

                    vtShowSuccess(`Welcome to ${communityName}!`);
                } else {
                    throw new Error(data.message || 'Failed to join community');
                }
            })
            .catch(error => {
                console.error('Error joining community:', error);
                vtShowError('Failed to join community: ' + error.message);
                this.disabled = false;
                this.textContent = isPersonalCommunity ? 'Connect' : 'Join';
            });
        });
    });
}

/**
 * Get CSRF token from meta tag
 */
function getCSRFToken() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    return csrfToken ? csrfToken.getAttribute('content') : '';
}

/**
 * Escape HTML for safe insertion
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Initialize invitation link copy functionality
 */
function initInvitationCopy() {
    const copyLinkBtn = document.querySelector('.app-copy-invitation-link');
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function() {
            const linkInput = document.getElementById('invitation-link');
            linkInput.select();
            document.execCommand('copy');
            this.textContent = 'Copied!';
            setTimeout(() => {
                this.textContent = 'Copy';
            }, 2000);
        });
    }

    const copyWithMessageBtn = document.querySelector('.app-copy-invitation-with-message');
    if (copyWithMessageBtn) {
        copyWithMessageBtn.addEventListener('click', function() {
            const link = document.getElementById('invitation-link').value;
            const message = document.getElementById('custom-message').value;
            const fullText = message ? message + '\n\n' + link : link;

            navigator.clipboard.writeText(fullText).then(() => {
                this.textContent = 'Copied!';
                setTimeout(() => {
                    this.textContent = 'Copy Link with Message';
                }, 2000);
            });
        });
    }
}

/**
 * Change member role (called from template)
 */
function changeMemberRole(memberId, newRole, communityId) {
    if (!confirm('Are you sure you want to change this member\'s role to ' + newRole + '?')) {
        location.reload();
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId + '/role', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            role: newRole,
            nonce: getCSRFToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const payload = data.data || {};
            refreshMemberTable(communityId, payload.html);
        } else {
            alert('Error: ' + (data.message || 'Failed to update member role'));
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update member role. Please try again.');
        location.reload();
    });
}

/**
 * Remove member from community (called from template)
 */
function removeMember(memberId, memberName, communityId) {
    if (!confirm('Are you sure you want to remove ' + memberName + ' from this community? This action cannot be undone.')) {
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId + '?nonce=' + encodeURIComponent(getCSRFToken()), {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const payload = data.data || {};
            refreshMemberTable(communityId, payload.html);
        } else {
            alert('Error: ' + (data.message || 'Failed to remove member'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to remove member. Please try again.');
    });
}

function refreshMemberTable(communityId, html) {
    const tbody = document.getElementById('members-table-body');
    if (!tbody) {
        return;
    }

    if (html) {
        tbody.innerHTML = html;
        return;
    }

    fetch('/api/communities/' + communityId + '/members?nonce=' + encodeURIComponent(getCSRFToken()))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.html) {
                tbody.innerHTML = data.data.html;
            }
        })
        .catch(error => {
            console.error('Error refreshing members:', error);
        });
}

/**
 * Initialize invitation form submission
 */
function initInvitationForm() {
    const form = document.getElementById('send-invitation-form');
    if (!form) return;

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
    const invitationsList = document.getElementById('invitations-list');

    if (!form || !invitationsList) return;

    const entityType = form.getAttribute('data-entity-type');
    const entityId = form.getAttribute('data-entity-id');

    attachInvitationActionHandlers(entityType, entityId);
    loadPendingInvitations(entityType, entityId);
}

/**
 * Load pending invitations
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
        const statusClass = inv.status === 'accepted' ? 'success' : (inv.status === 'declined' ? 'danger' : 'secondary');
        const statusText = inv.status.charAt(0).toUpperCase() + inv.status.slice(1);

        // Handle different field names between communities and events
        const email = inv.invited_email || inv.email;
        const dateField = inv.created_at || inv.rsvp_date;

        // Check if this is a Bluesky invitation
        const isBluesky = email && email.startsWith('bsky:');
        let displayEmail = email;

        if (isBluesky) {
            const did = email.substring(5); // Remove 'bsky:' prefix
            displayEmail = did.substring(0, 30) + '...'; // Truncate DID for display
        }

        html += '<div class="app-invitation-item">';
        html += '<div class="app-flex app-flex-between">';
        html += '<div>';
        html += '<strong>' + escapeHtml(displayEmail) + '</strong>';
        if (isBluesky) {
            html += ' <span class="app-badge app-badge-secondary">Bluesky</span>';
        }
        html += '<div class="app-text-muted app-text-sm">Sent ' + new Date(dateField).toLocaleDateString() + '</div>';
        html += '</div>';
        html += '<div class="app-flex app-gap-2">';
        html += '<span class="app-badge app-badge-' + statusClass + '">' + statusText + '</span>';
        if (inv.status === 'pending' && !isBluesky) {
            html += '<button class="app-btn app-btn-sm app-btn-danger cancel-invitation" data-invitation-id="' + inv.id + '" data-invitation-action="cancel">Cancel</button>';
        }
        html += '</div>';
        html += '</div>';
        html += '</div>';
    });

    html += '</div>';
    return html;
}

/**
 * Attach invitation action handlers (cancel/resend)
 */
function attachInvitationActionHandlers(entityType, entityId) {
    const container = document.getElementById('invitations-list');
    if (!container) {
        return;
    }

    if (container.dataset.actionsBound === 'true') {
        return;
    }
    container.dataset.actionsBound = 'true';

    container.addEventListener('click', function(event) {
        const target = event.target.closest('button[data-invitation-action]');
        if (!target) {
            return;
        }

        const action = target.getAttribute('data-invitation-action');
        const invitationId = target.getAttribute('data-invitation-id');
        if (!invitationId || !action) {
            return;
        }

        if (action === 'cancel' && !confirm('Are you sure you want to cancel this invitation?')) {
            return;
        }

        const entityTypePlural = entityType === 'community' ? 'communities' : 'events';
        const nonce = getCSRFToken();
        let url = `/api/${entityTypePlural}/${entityId}/invitations/${invitationId}`;
        const fetchOptions = {};

        if (action === 'cancel') {
            url += `?nonce=${encodeURIComponent(nonce)}`;
            fetchOptions.method = 'DELETE';
        } else if (action === 'resend') {
            url += `/resend?nonce=${encodeURIComponent(nonce)}`;
            fetchOptions.method = 'POST';
        } else {
            return;
        }

        const originalText = target.textContent;
        target.disabled = true;
        target.textContent = action === 'cancel' ? 'Removing...' : 'Resending...';

        fetch(url, fetchOptions)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const payload = data.data || {};
                    alert(payload.message || (action === 'cancel'
                        ? 'Invitation cancelled successfully'
                        : 'Invitation resent successfully'));
                    loadPendingInvitations(entityType, entityId);
                } else {
                    alert('Error: ' + (data.message || 'Request failed'));
                }
            })
            .catch(error => {
                console.error('Error handling invitation action:', error);
                alert('Failed to process the invitation. Please try again.');
            })
            .finally(() => {
                target.disabled = false;
                target.textContent = originalText;
            });
    });
}

/**
 * Copy invitation URL to clipboard (called from event invitations HTML)
 */
function copyInvitationUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Invitation link copied to clipboard!');
    }).catch(error => {
        console.error('Error copying to clipboard:', error);
        alert('Failed to copy link. Please try again.');
    });
}

/**
 * Initialize event guests section (Guests tab)
 */
function initEventGuestsSection() {
    const section = document.getElementById('event-guests-section');
    if (!section) {
        return;
    }

    const eventId = section.getAttribute('data-event-id');
    if (!eventId) {
        return;
    }

    loadEventGuests(eventId);
}

/**
 * Load event guests for the Guests tab
 */
function loadEventGuests(eventId) {
    const nonce = getCSRFToken();
    fetch(`/api/events/${eventId}/invitations?nonce=${encodeURIComponent(nonce)}`, {
        method: 'GET'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const payload = data.data || {};
            updateEventGuestUI(payload.invitations || []);
        } else {
            console.error('Failed to load event guests:', data.message);
            showEventGuestError();
        }
    })
    .catch(error => {
        console.error('Error loading event guests:', error);
        showEventGuestError();
    });
}

/**
 * Update event guests UI elements (table, counts, empty state)
 */
function updateEventGuestUI(guests) {
    const section = document.getElementById('event-guests-section');
    const tbody = document.getElementById('event-guests-body');
    const emptyState = document.getElementById('event-guests-empty');
    const totalCountEl = document.getElementById('event-guest-total');
    const confirmedCountEl = document.getElementById('confirmed-guest-count');

    const guestList = Array.isArray(guests) ? guests : [];
    const totalCount = guestList.length;
    const confirmedCount = guestList.filter(guest => {
        const status = (guest.status || '').toLowerCase();
        return status === 'confirmed' || status === 'yes';
    }).length;

    if (totalCountEl) {
        totalCountEl.textContent = totalCount.toString();
    }
    if (confirmedCountEl) {
        confirmedCountEl.textContent = confirmedCount.toString();
    }

    if (!tbody) {
        return;
    }

    if (guestList.length === 0) {
        if (section) {
            section.style.display = 'none';
        }
        if (emptyState) {
            emptyState.style.display = '';
        }
        tbody.innerHTML = '';
        return;
    }

    if (section) {
        section.style.display = '';
    }
    if (emptyState) {
        emptyState.style.display = 'none';
    }

    tbody.innerHTML = renderEventGuestsTable(guestList);
}

/**
 * Render event guests table rows
 */
function renderEventGuestsTable(guests) {
    return guests.map(guest => {
        const hasName = Boolean(guest.name);
        const displayName = hasName ? escapeHtml(guest.name) : escapeHtml(guest.email || 'Guest');
        const nameHtml = hasName
            ? `<strong>${displayName}</strong>`
            : `<strong class="app-text-muted">${displayName}</strong>`;
        const plusOne = guest.plus_one && Number(guest.plus_one) > 0
            ? `<br><small class="app-text-muted">+1: ${escapeHtml(guest.plus_one_name || 'Guest')}</small>`
            : '';
        const email = escapeHtml(guest.email || '');
        const statusInfo = mapGuestStatus(guest.status);
        const rsvpDate = guest.rsvp_date ? formatGuestDate(guest.rsvp_date) : '—';
        const guestIdRaw = Number(guest.id || guest.guest_id || 0);
        const guestId = Number.isNaN(guestIdRaw) ? 0 : guestIdRaw;

        return `
            <tr>
                <td>
                    ${nameHtml}
                    ${plusOne}
                </td>
                <td>${email}</td>
                <td>
                    <span class="app-badge app-badge-${statusInfo.badge}">${statusInfo.label}</span>
                </td>
                <td>${rsvpDate}</td>
                <td><span class="app-text-muted">—</span></td>
            </tr>
        `;
    }).join('');
}

/**
 * Map guest status to display label and badge class
 */
function mapGuestStatus(status) {
    const normalized = (status || 'pending').toLowerCase();
    switch (normalized) {
        case 'confirmed':
        case 'yes':
            return { label: 'Confirmed', badge: 'success' };
        case 'declined':
        case 'no':
            return { label: 'Declined', badge: 'danger' };
        case 'maybe':
            return { label: 'Maybe', badge: 'warning' };
        default:
            return { label: 'Pending', badge: 'secondary' };
    }
}

/**
 * Format RSVP date for table display
 */
function formatGuestDate(dateString) {
    const parsed = new Date(dateString);
    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }
    return parsed.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

/**
 * Show error message when guests fail to load
 */
function showEventGuestError() {
    const section = document.getElementById('event-guests-section');
    const tbody = document.getElementById('event-guests-body');
    const emptyState = document.getElementById('event-guests-empty');

    if (section) {
        section.style.display = '';
    }
    if (emptyState) {
        emptyState.style.display = 'none';
    }
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="app-text-center app-text-danger">
                    Could not load guests. Please refresh to try again.
                </td>
            </tr>
        `;
    }
}
