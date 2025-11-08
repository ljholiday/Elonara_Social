/* eslint-disable no-console */
(function () {
    if (window.__blueskyInvitationsInitialized) {
        return;
    }
    window.__blueskyInvitationsInitialized = true;

    const modal = document.getElementById('bluesky-follower-modal');
    if (!modal) {
        return;
    }

    const isConnected = modal.dataset.connected === '1';
    let needsReauth = modal.dataset.needsReauth === '1';
    const entityType = modal.dataset.entityType || 'community';
    const entityId = parseInt(modal.dataset.entityId || '0', 10);

    function getActionNonce() {
        return modal.dataset.actionNonce || '';
    }

    function setActionNonce(nonce) {
        if (nonce) {
            modal.dataset.actionNonce = nonce;
        }
    }

    function requireActionNonce() {
        const nonce = getActionNonce();
        if (!nonce) {
            const message = 'Security token missing. Please refresh the page and try again.';
            console.error('[bluesky] ' + message, { entityType, entityId });
            alert(message);
            throw new Error('Missing Bluesky nonce');
        }
        return nonce;
    }

    const openButtons = document.querySelectorAll('[data-open-bluesky-modal]');
    const closeBtns = modal.querySelectorAll('[data-close-bluesky-modal]');
    const overlay = modal.querySelector('.app-modal-overlay');
    const searchInput = document.getElementById('follower-search');
    const syncBtn = document.getElementById('sync-followers-btn');
    const inviteBtn = document.getElementById('invite-selected-btn');
    const followerList = document.getElementById('follower-list');
    const followerLoading = document.getElementById('follower-loading');
    const followerError = document.getElementById('follower-error');
    const followerEmpty = document.getElementById('follower-empty');
    const selectedCountEl = document.getElementById('selected-count');
    const lastSyncEl = document.getElementById('last-sync-time');
    const connectButtons = document.querySelectorAll('[data-bluesky-connect-button]');
    const manageButton = document.querySelector('[data-bluesky-manage-button]');

    let followers = [];
    const selectedFollowers = new Set();

    openButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            modal.style.display = 'block';
            document.body.classList.add('app-modal-open');
            if (isConnected) {
                loadFollowers();
            } else {
                if (followerLoading) followerLoading.style.display = 'none';
                if (followerEmpty) followerEmpty.style.display = 'block';
            }
        });
    });

    function closeModal() {
        modal.style.display = 'none';
        document.body.classList.remove('app-modal-open');
        selectedFollowers.clear();
        updateSelectedCount();
    }

    closeBtns.forEach((btn) => btn.addEventListener('click', closeModal));
    if (overlay) overlay.addEventListener('click', closeModal);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });

    async function loadFollowers() {
        if (!isConnected) {
            if (followerLoading) followerLoading.style.display = 'none';
            if (followerEmpty) followerEmpty.style.display = 'block';
            return;
        }

        if (followerLoading) followerLoading.style.display = 'block';
        if (followerError) followerError.style.display = 'none';
        if (followerEmpty) followerEmpty.style.display = 'none';
        if (followerList) followerList.innerHTML = '';

        try {
            const response = await fetch('/api/bluesky/followers');
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to load followers');
            }

            followers = Array.isArray(data.followers) ? data.followers : [];

            if (data.synced_at && lastSyncEl) {
                const syncDate = new Date(data.synced_at);
                lastSyncEl.textContent = `Last synced: ${syncDate.toLocaleString()}`;
            }

            if (followerLoading) followerLoading.style.display = 'none';

            if (followers.length === 0) {
                if (followerEmpty) followerEmpty.style.display = 'block';
            } else {
                renderFollowers(followers);
            }
        } catch (error) {
            if (followerLoading) followerLoading.style.display = 'none';
            if (followerError) {
                followerError.style.display = 'block';
                followerError.textContent = error instanceof Error ? error.message : 'Failed to load followers';
            }
        }
    }

    function renderFollowers(followersToRender) {
        if (!followerList) return;
        followerList.innerHTML = '';

        followersToRender.forEach((follower) => {
            const did = follower.did || '';
            const handle = follower.handle || '';
            const displayName = follower.displayName || handle;
            const avatar = follower.avatar || '';
            const description = follower.description || '';

            const item = document.createElement('div');
            item.className = 'app-follower-item';
            item.innerHTML = `
                <label class="app-follower-checkbox">
                    <input type="checkbox" value="${escapeHtml(did)}" data-handle="${escapeHtml(handle)}">
                    <div class="app-follower-info">
                        ${avatar
                            ? `<img src="${escapeHtml(avatar)}" alt="${escapeHtml(displayName)}" class="app-follower-avatar">`
                            : '<div class="app-follower-avatar app-follower-avatar-placeholder"></div>'}
                        <div class="app-follower-details">
                            <div class="app-follower-name">${escapeHtml(displayName)}</div>
                            <div class="app-follower-handle">@${escapeHtml(handle)}</div>
                            ${description ? `<div class="app-follower-description">${escapeHtml(description)}</div>` : ''}
                        </div>
                    </div>
                </label>
            `;

            const checkbox = item.querySelector('input[type="checkbox"]');
            if (checkbox) {
                checkbox.addEventListener('change', function onChange() {
                    if (this.checked) {
                        selectedFollowers.add(did);
                    } else {
                        selectedFollowers.delete(did);
                    }
                    updateSelectedCount();
                });
            }

            followerList.appendChild(item);
        });
    }

    if (searchInput && isConnected) {
        searchInput.addEventListener('input', function onSearch() {
            const query = this.value.toLowerCase().trim();

            if (query === '') {
                renderFollowers(followers);
                return;
            }

            const filtered = followers.filter((follower) => {
                const handle = (follower.handle || '').toLowerCase();
                const name = (follower.displayName || '').toLowerCase();
                const description = (follower.description || '').toLowerCase();
                return handle.includes(query) || name.includes(query) || description.includes(query);
            });

            renderFollowers(filtered);
        });
    }

    function extractNeedsReauth(payload) {
        if (!payload) {
            return false;
        }
        if (payload.needs_reauth) {
            return true;
        }
        if (payload.data && payload.data.needs_reauth) {
            return true;
        }
        return false;
    }

    function promptReauthorize(message) {
        const fullMessage = `${message}\n\nWould you like to open your profile settings to reauthorize now?`;
        if (window.confirm(fullMessage)) {
            window.open('/profile/edit#bluesky', '_blank');
        }
        needsReauth = true;
    }

    if (syncBtn) {
        syncBtn.addEventListener('click', async () => {
            if (!isConnected) {
                window.open('/profile/edit#bluesky', '_blank');
                return;
            }

            if (needsReauth) {
                promptReauthorize('Please reauthorize your Bluesky account before syncing.');
                return;
            }

            syncBtn.disabled = true;
            const originalText = syncBtn.textContent;
            syncBtn.textContent = 'Syncing...';

            let nonce;
            try {
                nonce = requireActionNonce();
            } catch (error) {
                syncBtn.disabled = false;
                syncBtn.textContent = originalText || 'Sync';
                return;
            }

            try {
                const response = await fetch('/api/bluesky/sync', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nonce }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    if (extractNeedsReauth(data)) {
                        promptReauthorize(data.message || 'Your Bluesky session expired.');
                    }
                    throw new Error(data.message || 'Failed to sync followers');
                }
                if (data.nonce) {
                    setActionNonce(data.nonce);
                }
                await loadFollowers();
                alert(`Followers synced successfully! Found ${data.count || 0} followers.`);
            } catch (error) {
                alert(`Error syncing followers: ${error instanceof Error ? error.message : String(error)}`);
            } finally {
                syncBtn.disabled = false;
                syncBtn.textContent = originalText || 'Sync';
            }
        });
    }

    if (!isConnected && connectButtons.length) {
        connectButtons.forEach((button) => {
            button.addEventListener('click', () => {
                window.open('/profile/edit#bluesky', '_blank');
                closeModal();
            });
        });
    }

    if (manageButton && isConnected) {
        manageButton.addEventListener('click', () => {
            window.open('/profile/edit#bluesky', '_blank');
        });
    }

    if (inviteBtn && isConnected) {
        inviteBtn.addEventListener('click', async () => {
            if (selectedFollowers.size === 0) {
                return;
            }

            inviteBtn.disabled = true;
            const originalText = inviteBtn.textContent;
            inviteBtn.textContent = 'Sending invitations...';

            let nonce;
            try {
                nonce = requireActionNonce();
            } catch (error) {
                inviteBtn.disabled = false;
                inviteBtn.textContent = originalText || 'Invite Selected';
                return;
            }

            try {
                const response = await fetch(`/api/invitations/bluesky/${entityType}/${entityId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        nonce,
                        follower_dids: Array.from(selectedFollowers),
                    }),
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    if (extractNeedsReauth(data)) {
                        promptReauthorize(data.message || 'Please reauthorize your Bluesky account.');
                    }
                    throw new Error(data.message || 'Failed to send invitations');
                }

                if (data.data && data.data.nonce) {
                    setActionNonce(data.data.nonce);
                }

                alert(`Invitations sent successfully to ${selectedFollowers.size} followers!`);
                closeModal();

                if (typeof refreshInvitations === 'function') {
                    refreshInvitations();
                }
            } catch (error) {
                alert(`Error sending invitations: ${error instanceof Error ? error.message : String(error)}`);
            } finally {
                inviteBtn.disabled = false;
                inviteBtn.textContent = originalText || 'Invite Selected';
            }
        });
    }

    function updateSelectedCount() {
        if (selectedCountEl) {
            selectedCountEl.textContent = String(selectedFollowers.size);
        }
        if (inviteBtn) {
            const disabledReason = !isConnected || selectedFollowers.size === 0 || needsReauth;
            inviteBtn.disabled = disabledReason;
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
