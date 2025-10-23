<?php
/**
 * Reusable Invitation Section Partial
 * Used by both community and event manage pages
 *
 * Required variables:
 * - $entity_type: 'community' or 'event'
 * - $entity_id: ID for form submission
 * - $show_pending: bool - whether to show pending invitations list
 * - $invite_url: shareable invite link
 */

$entity_type = $entity_type ?? 'community';
$entity_id = (int)($entity_id ?? 0);
$show_pending = $show_pending ?? true;
$invite_url = $invite_url ?? '';
$cancel_nonce = $cancel_nonce ?? '';

if ($cancel_nonce === '') {
    $security = app_service('security.service');
    $viewerId = (int)(app_service('auth.service')->currentUserId() ?? 0);
    $action = $entity_type === 'event' ? 'app_event_action' : 'app_community_action';
    $cancel_nonce = $security->createNonce($action, $viewerId);
}

$entity_label = ucfirst($entity_type);
$share_url = $invite_url !== '' ? $invite_url : '/' . $entity_type . 's';
?>

<div class="app-section">
	<div class="app-section-header">
		<h2 class="app-heading app-heading-md app-text-primary">Send Invitations</h2>
	</div>

	<!-- Copyable Invitation Links -->
	<div class="app-card app-mb-4">
		<div class="app-card-header">
			<h3 class="app-heading app-heading-sm">Share <?php echo $entity_label; ?> Link</h3>
		</div>
		<div class="app-card-body">
			<p class="app-text-muted app-mb-4">
				Copy and share this link via text, social media, Discord, Slack, or any other platform.
			</p>

			<div class="app-form-group app-mb-4">
				<label class="app-form-label"><?php echo $entity_label; ?> Invitation Link</label>
				<div class="app-flex app-gap-2">
					<input type="text" class="app-form-input app-flex-1" id="invitation-link"
						   value="<?php echo htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8'); ?>"
						   readonly>
					<button type="button" class="app-btn app-copy-invitation-link"
						data-url="<?php echo htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8'); ?>">
						Copy
					</button>
				</div>
			</div>

			<div class="app-form-group">
				<label class="app-form-label">Custom Message (Optional)</label>
				<textarea class="app-form-textarea" id="custom-message" rows="3"
						  placeholder="Add a personal message to include when sharing..."></textarea>
				<div class="app-mt-2">
					<button type="button" class="app-btn app-copy-invitation-with-message">
						Copy Link with Message
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Email Invitation Form -->
	<form id="send-invitation-form" class="app-form" data-entity-type="<?php echo $entity_type; ?>" data-entity-id="<?php echo $entity_id; ?>" data-custom-handler="true" action="javascript:void(0);">
		<div class="app-form-group">
			<label class="app-form-label" for="invitation-email">
				Email Address
			</label>
			<input type="email" class="app-form-input" id="invitation-email"
				   placeholder="Enter email address..." required>
		</div>

		<div class="app-form-group">
			<label class="app-form-label" for="invitation-message">
				Personal Message (Optional)
			</label>
			<textarea class="app-form-textarea" id="invitation-message" rows="3"
					  placeholder="Add a personal message to your invitation..."></textarea>
		</div>

		<button type="submit" class="app-btn app-btn-primary">
			Send Invitation
		</button>
	</form>

	<?php if ($show_pending) : ?>
    <div class="app-mt-6">
        <h4 class="app-heading app-heading-sm">Pending Invitations</h4>
        <div class="app-invitations-wrapper"
             data-entity-type="<?php echo htmlspecialchars($entity_type, ENT_QUOTES, 'UTF-8'); ?>"
             data-entity-id="<?php echo (int)$entity_id; ?>"
             data-cancel-nonce="<?php echo htmlspecialchars($cancel_nonce, ENT_QUOTES, 'UTF-8'); ?>">
            <div id="invitations-list">
                <div class="app-loading-placeholder">
                    <p>Loading pending invitations...</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
