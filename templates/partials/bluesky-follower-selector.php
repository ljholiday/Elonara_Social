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
$bluesky_nonce = $bluesky_nonce ?? '';

// Check if Bluesky is connected
$blueskyService = function_exists('app_service') ? app_service('bluesky.service') : null;
$authService = function_exists('app_service') ? app_service('auth.service') : null;
$currentUser = $authService ? $authService->getCurrentUser() : null;
$isConnected = $blueskyService && $currentUser && $blueskyService->isConnected((int)$currentUser?->id);
$assetBase = rtrim((string)app_config('asset_url', '/assets'), '/');
?>

<!-- Bluesky Follower Selector Modal -->
<div
    id="bluesky-follower-modal"
    class="app-modal app-bluesky-follower-modal"
    data-connected="<?= $isConnected ? '1' : '0'; ?>"
    data-entity-type="<?= htmlspecialchars($entity_type, ENT_QUOTES, 'UTF-8'); ?>"
    data-entity-id="<?= (int)$entity_id; ?>"
    data-action-nonce="<?= htmlspecialchars($bluesky_nonce, ENT_QUOTES, 'UTF-8'); ?>"
    style="display: none;"
>
    <div class="app-modal-overlay" data-close-bluesky-modal></div>
    <div class="app-modal-content app-modal-lg">
        <div class="app-modal-header">
            <h3 class="app-modal-title">Invite Bluesky Followers</h3>
            <button type="button" class="app-btn app-btn-sm" data-close-bluesky-modal>&times;</button>
        </div>

        <div class="app-modal-body">
            <?php if ($isConnected): ?>
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
            <?php else: ?>
                <div class="app-text-center app-py-6">
                    <p class="app-text-muted app-mb-4">
                        Connect your Bluesky account to load your followers and invite them directly.
                    </p>
                    <button type="button" class="app-btn app-btn-primary" data-bluesky-connect-button>
                        Connect Bluesky
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="app-modal-footer">
            <?php if ($isConnected): ?>
                <div class="app-flex app-gap-3 app-flex-wrap">
                    <button type="button" class="app-btn app-btn-primary" id="invite-selected-btn" disabled>
                        Invite Selected
                    </button>
                    <button type="button" class="app-btn" data-close-bluesky-modal>Cancel</button>
                    <button type="button" class="app-btn app-btn-link app-text-muted" data-bluesky-manage-button>
                        Manage Bluesky Connection
                    </button>
                </div>
            <?php else: ?>
                <div class="app-flex app-gap-3 app-flex-wrap">
                    <button type="button" class="app-btn app-btn-primary" data-bluesky-connect-button>
                        Connect Bluesky
                    </button>
                    <button type="button" class="app-btn" data-close-bluesky-modal>Cancel</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars($assetBase . '/js/bluesky-invitations.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
