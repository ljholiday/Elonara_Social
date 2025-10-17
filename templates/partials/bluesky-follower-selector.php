<?php
/**
 * Bluesky Follower Selector Modal
 *
 * Reusable modal for selecting and inviting Bluesky followers
 *
 * Required variables:
 * - $entity_type: 'event' or 'community'
 * - $entity_id: ID of the event or community
 */

$entity_type = $entity_type ?? 'event';
$entity_id = (int)($entity_id ?? 0);

// Check if Bluesky is connected
$blueskyService = function_exists('app_service') ? app_service('bluesky.service') : null;
$authService = function_exists('app_service') ? app_service('auth.service') : null;
$currentUser = $authService ? $authService->getCurrentUser() : null;
$isConnected = $blueskyService && $currentUser && $blueskyService->isConnected((int)$currentUser?->id);

if (!$isConnected) {
    return;
}
?>

<!-- Bluesky Follower Selector Modal -->
<div id="bluesky-follower-modal" class="app-modal app-bluesky-follower-modal" style="display: none;">
    <div class="app-modal-overlay" data-close-bluesky-modal></div>
    <div class="app-modal-content app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">Invite Bluesky Followers</h3>
            <button type="button" class="app-btn app-btn-sm" data-close-bluesky-modal>&times;</button>
        </div>

        <div class="app-modal-body">
            <div class="app-mb-4">
                <div class="app-flex app-items-center app-gap-2 app-mb-3">
                    <input
                        type="text"
                        id="follower-search"
                        class="app-form-input app-flex-1"
                        placeholder="Search followers by name or handle..."
                    >
                    <button
                        type="button"
                        class="app-btn app-btn-sm app-btn-secondary"
                        id="sync-followers-btn"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                        </svg>
                        Sync
                    </button>
                </div>

                <div class="app-flex app-items-center app-justify-between app-text-sm app-text-muted">
                    <div>
                        <span id="selected-count">0</span> selected
                    </div>
                    <div id="last-sync-time"></div>
                </div>
            </div>

            <div id="follower-loading" class="app-text-center app-py-6" style="display: none;">
                <div class="app-spinner app-mb-2"></div>
                <div class="app-text-muted">Loading followers...</div>
            </div>

            <div id="follower-error" class="app-alert app-alert-error" style="display: none;"></div>

            <div id="follower-empty" class="app-text-center app-py-6 app-text-muted" style="display: none;">
                No followers found. Click "Sync" to fetch your Bluesky followers.
            </div>

            <div id="follower-list" class="app-follower-list"></div>
        </div>

        <div class="app-modal-footer">
            <button type="button" class="app-btn app-btn-primary" id="invite-selected-btn" disabled>
                Invite Selected
            </button>
            <button type="button" class="app-btn" data-close-bluesky-modal>Cancel</button>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const modal = document.getElementById('bluesky-follower-modal');
    if (!modal) return;

    const openBtn = document.querySelector('[data-open-bluesky-modal]');
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

    let followers = [];
    let selectedFollowers = new Set();

    // Open modal
    if (openBtn) {
        openBtn.addEventListener('click', function() {
            modal.style.display = 'block';
            document.body.classList.add('app-modal-open');
            loadFollowers();
        });
    }

    // Close modal
    function closeModal() {
        modal.style.display = 'none';
        document.body.classList.remove('app-modal-open');
        selectedFollowers.clear();
        updateSelectedCount();
    }

    closeBtns.forEach(btn => btn.addEventListener('click', closeModal));
    if (overlay) overlay.addEventListener('click', closeModal);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            closeModal();
        }
    });

    // Load followers from API
    async function loadFollowers() {
        followerLoading.style.display = 'block';
        followerError.style.display = 'none';
        followerEmpty.style.display = 'none';
        followerList.innerHTML = '';

        try {
            const response = await fetch('/api/bluesky/followers');
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load followers');
            }

            followers = data.followers || [];

            if (data.synced_at) {
                const syncDate = new Date(data.synced_at);
                lastSyncEl.textContent = 'Last synced: ' + syncDate.toLocaleString();
            }

            followerLoading.style.display = 'none';

            if (followers.length === 0) {
                followerEmpty.style.display = 'block';
            } else {
                renderFollowers(followers);
            }
        } catch (error) {
            followerLoading.style.display = 'none';
            followerError.style.display = 'block';
            followerError.textContent = error.message;
        }
    }

    // Render followers
    function renderFollowers(followersToRender) {
        followerList.innerHTML = '';

        followersToRender.forEach(follower => {
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
                        ${avatar ? `<img src="${escapeHtml(avatar)}" alt="${escapeHtml(displayName)}" class="app-follower-avatar">` : '<div class="app-follower-avatar app-follower-avatar-placeholder"></div>'}
                        <div class="app-follower-details">
                            <div class="app-follower-name">${escapeHtml(displayName)}</div>
                            <div class="app-follower-handle">@${escapeHtml(handle)}</div>
                            ${description ? `<div class="app-follower-description">${escapeHtml(description)}</div>` : ''}
                        </div>
                    </div>
                </label>
            `;

            const checkbox = item.querySelector('input[type="checkbox"]');
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    selectedFollowers.add(did);
                } else {
                    selectedFollowers.delete(did);
                }
                updateSelectedCount();
            });

            followerList.appendChild(item);
        });
    }

    // Search followers
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();

            if (query === '') {
                renderFollowers(followers);
            } else {
                const filtered = followers.filter(f => {
                    const handle = (f.handle || '').toLowerCase();
                    const displayName = (f.displayName || '').toLowerCase();
                    const description = (f.description || '').toLowerCase();
                    return handle.includes(query) || displayName.includes(query) || description.includes(query);
                });
                renderFollowers(filtered);
            }
        });
    }

    // Sync followers
    if (syncBtn) {
        syncBtn.addEventListener('click', async function() {
            syncBtn.disabled = true;
            syncBtn.textContent = 'Syncing...';

            const nonce = document.querySelector('meta[name="csrf-token"]')?.content;

            try {
                const response = await fetch('/api/bluesky/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ nonce })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to sync followers');
                }

                await loadFollowers();
                alert('Followers synced successfully! Found ' + data.count + ' followers.');
            } catch (error) {
                alert('Error syncing followers: ' + error.message);
            } finally {
                syncBtn.disabled = false;
                syncBtn.textContent = 'Sync';
            }
        });
    }

    // Invite selected followers
    if (inviteBtn) {
        inviteBtn.addEventListener('click', async function() {
            if (selectedFollowers.size === 0) {
                return;
            }

            inviteBtn.disabled = true;
            inviteBtn.textContent = 'Sending invitations...';

            const nonce = document.querySelector('meta[name="csrf-token"]')?.content;
            const entityType = '<?= $entity_type ?>';
            const entityId = <?= (int)$entity_id ?>;

            try {
                const response = await fetch(`/api/invitations/bluesky/${entityType}/${entityId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        nonce,
                        follower_dids: Array.from(selectedFollowers)
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to send invitations');
                }

                inviteBtn.disabled = false;
                inviteBtn.textContent = 'Invite Selected';

                alert('Invitations sent successfully to ' + selectedFollowers.size + ' followers!');
                closeModal();

                // Reload invitation list if exists
                if (typeof refreshInvitations === 'function') {
                    refreshInvitations();
                }
            } catch (error) {
                alert('Error sending invitations: ' + error.message);
                inviteBtn.disabled = false;
                inviteBtn.textContent = 'Invite Selected';
            }
        });
    }

    // Update selected count
    function updateSelectedCount() {
        selectedCountEl.textContent = selectedFollowers.size;
        inviteBtn.disabled = selectedFollowers.size === 0;
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
