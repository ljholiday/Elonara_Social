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

function getInvitationsWrapper() {
    return document.querySelector('.app-invitations-wrapper');
}

function getEntityActionNonce(entityType) {
    if (entityType === 'community') {
        const section = document.querySelector('.app-community-manage');
        if (section && section.dataset.communityActionNonce) {
            return section.dataset.communityActionNonce;
        }
    }
    if (entityType === 'event') {
        const section = document.querySelector('.app-event-manage');
        if (section && section.dataset.eventActionNonce) {
            return section.dataset.eventActionNonce;
        }
    }
    return '';
}

function getInvitationActionNonce(entityType) {
    const wrapper = getInvitationsWrapper();
    if (wrapper && wrapper.dataset.entityType === entityType && wrapper.dataset.cancelNonce) {
        return wrapper.dataset.cancelNonce;
    }
    return getEntityActionNonce(entityType);
}

function ensureActionNonce(entityType) {
    const nonce = getInvitationActionNonce(entityType);
    if (!nonce) {
        const message = 'Security token missing. Please refresh the page and try again.';
        console.error(`[nonces] ${message}`, { entityType });
        alert(message);
        throw new Error('Missing action nonce');
    }
    return nonce;
}

function updateInvitationsWrapperMeta(entityType, entityId, nonce) {
    const wrapper = getInvitationsWrapper();
    if (wrapper) {
        if (entityType) {
            wrapper.dataset.entityType = entityType;
        }
        if (entityId) {
            wrapper.dataset.entityId = entityId;
        }
        if (nonce) {
            wrapper.dataset.cancelNonce = nonce;
        }
    }

    if (nonce && entityType) {
        const section = entityType === 'community'
            ? document.querySelector('.app-community-manage')
            : document.querySelector('.app-event-manage');
        if (section) {
            if (entityType === 'community') {
                section.dataset.communityActionNonce = nonce;
            } else if (entityType === 'event') {
                section.dataset.eventActionNonce = nonce;
            }
        }
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeAttr(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function renderInviteCard(options = {}) {
    const {
        title = '',
        titleUrl = null,
        subtitle = '',
        meta = '',
        bodyHtml = '',
        badges = [],
        actions = [],
        attributes = {},
        className = ''
    } = options;

    const attrString = Object.entries(attributes)
        .filter(([key, val]) => val !== null && val !== undefined)
        .map(([key, val]) => ` ${escapeAttr(key)}="${escapeAttr(val)}"`)
        .join('');

    const badgeHtml = badges
        .map((badge) => {
            if (!badge || !badge.label) {
                return '';
            }
            const badgeLabel = escapeHtml(String(badge.label));
            const badgeClass = badge.class ? escapeHtml(String(badge.class)) : 'app-badge-secondary';
            return `<span class="${badgeClass}">${badgeLabel}</span>`;
        })
        .filter(Boolean)
        .join('');

    const titleHtml = title !== ''
        ? (titleUrl ? `<a href="${escapeAttr(titleUrl)}" class="app-text-primary">${escapeHtml(title)}</a>` : escapeHtml(title))
        : '';

    const subtitleHtml = subtitle !== '' ? `<div class="app-text-muted app-text-sm">${escapeHtml(subtitle)}</div>` : '';
    const metaHtml = meta !== '' ? `<small class="app-text-muted">${escapeHtml(meta)}</small>` : '';
    const actionsHtml = actions.filter(Boolean).join('');
    const cardClass = className ? ' ' + escapeHtml(className) : '';

    return `
        <div class="app-invitation-item${cardClass}"${attrString}>
            <div class="app-invitation-main">
                ${badgeHtml ? `<div class="app-invitation-badges">${badgeHtml}</div>` : ''}
                <div class="app-invitation-details">
                    ${titleHtml ? `<strong>${titleHtml}</strong>` : ''}
                    ${subtitleHtml}
                    ${metaHtml}
                    ${bodyHtml || ''}
                </div>
            </div>
            ${actionsHtml ? `<div class="app-invitation-actions">${actionsHtml}</div>` : ''}
        </div>
    `;
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

    let nonce;
    try {
        nonce = ensureActionNonce('community');
    } catch (error) {
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId + '/role', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'role=' + encodeURIComponent(newRole) + '&nonce=' + encodeURIComponent(nonce)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.data && data.data.nonce) {
                updateInvitationsWrapperMeta('community', communityId, data.data.nonce);
            }
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

    let nonce;
    try {
        nonce = ensureActionNonce('community');
    } catch (error) {
        return;
    }

    fetch('/api/communities/' + communityId + '/members/' + memberId, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'nonce=' + encodeURIComponent(nonce)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.data && data.data.nonce) {
                updateInvitationsWrapperMeta('community', communityId, data.data.nonce);
            }
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

        let nonce;
        try {
            nonce = ensureActionNonce(entityType);
        } catch (error) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }

        formData.append('nonce', nonce);

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
                if (payload.nonce) {
                    updateInvitationsWrapperMeta(entityType, entityId, payload.nonce);
                }
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
    const wrapper = getInvitationsWrapper();
    if (wrapper && wrapper.dataset.entityType && wrapper.dataset.entityId) {
        loadPendingInvitations(wrapper.dataset.entityType, wrapper.dataset.entityId);
        return;
    }

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
    const wrapper = getInvitationsWrapper();
    if (!invitationsList) return;

    const resolvedEntityType = wrapper?.dataset.entityType || entityType;
    const resolvedEntityId = wrapper?.dataset.entityId || entityId;
    if (!resolvedEntityType || !resolvedEntityId) return;

    const entityTypePlural = resolvedEntityType === 'community' ? 'communities' : 'events';

    let actionNonce;
    try {
        actionNonce = ensureActionNonce(resolvedEntityType);
    } catch (error) {
        invitationsList.innerHTML = '<div class="app-alert app-alert-error">Security token missing. Refresh the page and try again.</div>';
        return;
    }

    updateInvitationsWrapperMeta(resolvedEntityType, resolvedEntityId, actionNonce);

    fetch(`/api/${entityTypePlural}/${resolvedEntityId}/invitations?nonce=${encodeURIComponent(actionNonce)}`, {
        method: 'GET'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const payload = data.data || {};
            const nextNonce = payload.nonce || actionNonce;
            updateInvitationsWrapperMeta(resolvedEntityType, resolvedEntityId, nextNonce);

            if (payload.html) {
                invitationsList.innerHTML = payload.html;
            } else if (payload.invitations && payload.invitations.length > 0) {
                invitationsList.innerHTML = renderInvitationsList(payload.invitations, resolvedEntityType);
            } else {
                invitationsList.innerHTML = '<div class="app-text-center app-text-muted">No pending invitations.</div>';
            }

            attachInvitationActionHandlers(resolvedEntityType, resolvedEntityId, nextNonce);

            if (resolvedEntityType === 'event') {
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
    const cards = (invitations || []).map(inv => {
        const emailRaw = String(inv.invited_email || '');
        const memberName = inv.member_name ? String(inv.member_name) : '';
        const title = memberName !== '' ? memberName : emailRaw;
        const subtitle = memberName !== '' ? emailRaw : '';
        const statusValue = String(inv.status || 'pending').toLowerCase();
        const statusLabel = statusValue.charAt(0).toUpperCase() + statusValue.slice(1);
        const createdAt = inv.created_at ? new Date(inv.created_at).toLocaleDateString() : '';
        const invitationId = String(inv.id ?? '');
        const tokenRaw = String(inv.invitation_token || '');
        const isBluesky = emailRaw.startsWith('bsky:') || String(inv.is_bluesky || '').toLowerCase() === 'true';

        const badges = [
            { label: statusLabel, class: 'app-badge-' + statusValue },
        ];
        if (isBluesky) {
            badges.push({ label: 'Bluesky', class: 'app-badge-secondary' });
        }

        const actions = [
            `<button type="button" class="app-btn app-btn-sm" data-action="copy" data-invitation-token="${escapeAttr(tokenRaw)}">Copy Link</button>`
        ];

        const resendLabel = isBluesky ? 'Resend Invite' : 'Resend Email';
        const canResend = entityType === 'event'
            ? ['pending', 'maybe'].includes(statusValue)
            : statusValue === 'pending';
        if (canResend) {
            actions.push(`<button type="button" class="app-btn app-btn-sm app-btn-secondary" data-action="resend" data-invitation-id="${escapeAttr(invitationId)}">${resendLabel}</button>`);
        }

        if (statusValue === 'pending') {
            actions.push(`<button type="button" class="app-btn app-btn-sm app-btn-danger" data-action="cancel" data-invitation-id="${escapeAttr(invitationId)}">Cancel</button>`);
        }

        return renderInviteCard({
            attributes: {
                'data-invitation-id': invitationId
            },
            badges,
            title,
            subtitle: subtitle !== '' ? subtitle : null,
            meta: createdAt ? `Sent ${createdAt}` : '',
            actions
        });
    }).join('');

    return `<div class="app-invitations-list">${cards}</div>`;
}

/**
 * Attach invitation action handlers (cancel/resend)
 */
function attachInvitationActionHandlers(entityType, entityId, cancelNonce = '') {
    const containers = document.querySelectorAll('.app-invitations-list');
    if (!containers.length) {
        return;
    }

    let effectiveNonce = cancelNonce || getInvitationActionNonce(entityType);
    if (!effectiveNonce) {
        try {
            effectiveNonce = ensureActionNonce(entityType);
        } catch (error) {
            return;
        }
    }

    updateInvitationsWrapperMeta(entityType, entityId, effectiveNonce);

    containers.forEach(container => {
        container.querySelectorAll('[data-action="copy"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const token = this.getAttribute('data-invitation-token');
                if (!token) {
                    return;
                }
                const baseUrl = window.location.origin || '';
                const link = entityType === 'event'
                    ? `${baseUrl}/rsvp/${token}`
                    : `${baseUrl}/invitation/accept?token=${token}`;
                copyInvitationUrl(link);
            });
        });

        container.querySelectorAll('[data-action="resend"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const invitationId = this.getAttribute('data-invitation-id');
                if (!invitationId) {
                    return;
                }

                if (entityType === 'event') {
                    resendEventInvitation(entityId, invitationId, true);
                } else {
                    resendCommunityInvitation(entityId, invitationId);
                }
            });
        });

        container.querySelectorAll('[data-action="cancel"]').forEach(button => {
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
                    body: 'nonce=' + encodeURIComponent(effectiveNonce)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data && data.data.nonce) {
                        effectiveNonce = data.data.nonce;
                        updateInvitationsWrapperMeta(entityType, entityId, data.data.nonce);
                    }
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

    let nonce;
    try {
        nonce = ensureActionNonce('event');
    } catch (error) {
        showEventGuestError();
        return;
    }
    fetch(`/api/events/${eventId}/guests?nonce=${encodeURIComponent(nonce)}`, {
        method: 'GET'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.data && data.data.nonce) {
                updateInvitationsWrapperMeta('event', eventId, data.data.nonce);
            }
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
        tableBody.innerHTML = '<div class="app-text-center app-text-muted">No guests yet.</div>';
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
    const name = String(guest.name || guest.guest_name || guest.user_display_name || 'Guest');
    const emailRaw = String(guest.email || guest.guest_email || guest.user_email || '');
    const statusValue = String(guest.status || 'pending').toLowerCase();
    const statusLabel = mapGuestStatus(statusValue);
    const date = formatGuestDate(guest.rsvp_date || guest.created_at);
    const invitationId = String(guest.id || '');
    const rsvpToken = String(guest.rsvp_token || '');
    const isBluesky = emailRaw.startsWith('bsky:') || String(guest.source || '').toLowerCase() === 'bluesky';

    const badges = [
        { label: statusLabel, class: 'app-badge-' + statusValue },
    ];
    if (isBluesky) {
        badges.push({ label: 'Bluesky', class: 'app-badge-secondary' });
    }

    const actions = [
        `<button type=\"button\" class=\"app-btn app-btn-sm\" data-guest-action=\"copy\" data-rsvp-token=\"${escapeAttr(rsvpToken)}\">Copy Link</button>`
    ];
    if (['pending', 'maybe'].includes(statusValue)) {
        actions.push(`<button type=\"button\" class=\"app-btn app-btn-sm app-btn-secondary\" data-guest-action=\"resend\" data-invitation-id=\"${escapeAttr(invitationId)}\">Resend Invite</button>`);
    }
    if (statusValue === 'pending') {
        actions.push(`<button type=\"button\" class=\"app-btn app-btn-sm app-btn-danger\" data-guest-action=\"cancel\" data-invitation-id=\"${escapeAttr(invitationId)}\">Remove</button>`);
    }

    return renderInviteCard({
        attributes: {
            'data-invitation-id': invitationId
        },
        badges,
        title: name,
        subtitle: emailRaw !== '' ? emailRaw : null,
        meta: date ? `Invited on ${date}` : '',
        actions
    });
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

function resendEventInvitation(eventId, invitationId, refreshPending = false) {
    let nonce;
    try {
        nonce = ensureActionNonce('event');
    } catch (error) {
        return;
    }
    fetch(`/api/events/${eventId}/invitations/${invitationId}/resend`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'nonce=' + encodeURIComponent(nonce)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.nonce) {
            updateInvitationsWrapperMeta('event', eventId, data.data.nonce);
            nonce = data.data.nonce;
        }
        if (data.success) {
            if (refreshPending) {
                loadPendingInvitations('event', eventId);
            } else {
                loadEventGuests(eventId);
            }
        } else {
            alert(data.message || 'Unable to resend invitation.');
        }
    })
    .catch(error => {
        console.error('Error resending invitation:', error);
        alert('An error occurred while resending the invitation.');
    });
}

function resendCommunityInvitation(communityId, invitationId) {
    let nonce;
    try {
        nonce = ensureActionNonce('community');
    } catch (error) {
        return;
    }
    fetch(`/api/communities/${communityId}/invitations/${invitationId}/resend`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'nonce=' + encodeURIComponent(nonce)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.nonce) {
            updateInvitationsWrapperMeta('community', communityId, data.data.nonce);
            nonce = data.data.nonce;
        }
        if (data.success) {
            loadPendingInvitations('community', communityId);
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
    let nonce;
    try {
        nonce = ensureActionNonce('event');
    } catch (error) {
        return;
    }
    fetch(`/api/events/${eventId}/invitations/${invitationId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'nonce=' + encodeURIComponent(nonce)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.nonce) {
            updateInvitationsWrapperMeta('event', eventId, data.data.nonce);
            nonce = data.data.nonce;
        }
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
