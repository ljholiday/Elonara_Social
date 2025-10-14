<?php
/**
 * Elonara Social Danger Zone Partial
 * Reusable danger zone component for destructive actions (delete)
 *
 * @param string $entity_type Type of entity ('event', 'conversation', 'community')
 * @param int $entity_id ID of the entity to delete
 * @param string $entity_name Name of the entity (for confirmation)
 * @param bool $can_delete Whether user has permission to delete
 * @param string $delete_message Custom message explaining deletion consequences
 * @param string $nonce_action Nonce action for security
 * @param string $confirmation_type Type of confirmation ('confirm' or 'type_name')
 * @param int $blocker_count Optional count of blocking items (e.g., confirmed guests)
 * @param string $blocker_message Optional message about why deletion is blocked
 */

// Required parameters
$entity_type = $entity_type ?? '';
$entity_id = $entity_id ?? 0;
$entity_name = $entity_name ?? '';
$can_delete = $can_delete ?? false;

// Optional parameters with defaults
$delete_message = $delete_message ?? 'Once you delete this ' . $entity_type . ', there is no going back. This action cannot be undone.';
$nonce_action = $nonce_action ?? 'vt_delete_' . $entity_type;
$confirmation_type = $confirmation_type ?? 'confirm'; // 'confirm' or 'type_name'
$blocker_count = $blocker_count ?? 0;
$blocker_message = $blocker_message ?? '';

// Don't render if essential parameters are missing
if (empty($entity_type) || empty($entity_id)) {
    return;
}
?>

<!-- Danger Zone -->
<div class="vt-danger-zone vt-mt-6">
    <h4 class="vt-danger-zone-title vt-heading vt-heading-sm vt-mb-2">Danger Zone</h4>
    <p class="vt-text-muted vt-mb-4">
        <?php echo htmlspecialchars($delete_message); ?>
        <?php if ($blocker_count > 0 && $blocker_message) : ?>
            <br><strong>Note:</strong> <?php echo htmlspecialchars($blocker_message); ?>
        <?php endif; ?>
    </p>

    <?php if ($can_delete && $blocker_count == 0) : ?>
        <form method="post" class="vt-danger-zone-form" onsubmit="return confirmDeletion(this, '<?php echo htmlspecialchars($entity_name); ?>', '<?php echo $confirmation_type; ?>');">
            <?php echo vt_service('security.service')->nonceField($nonce_action, 'delete_nonce'); ?>
            <input type="hidden" name="<?php echo $entity_type; ?>_id" value="<?php echo intval($entity_id); ?>">
            <input type="hidden" name="action" value="delete_<?php echo $entity_type; ?>">

            <?php if ($confirmation_type === 'type_name') : ?>
                <div class="vt-form-group vt-mb-4">
                    <label class="vt-form-label" for="confirm_name">
                        Type <strong><?php echo htmlspecialchars($entity_name); ?></strong> to confirm deletion:
                    </label>
                    <input type="text"
                           id="confirm_name"
                           name="confirm_name"
                           class="vt-form-input"
                           placeholder="<?php echo htmlspecialchars($entity_name); ?>"
                           required>
                </div>
            <?php endif; ?>

            <button type="submit" class="vt-btn vt-btn-danger">
                Delete <?php echo ucfirst($entity_type); ?>
            </button>
        </form>
    <?php elseif (!$can_delete) : ?>
        <p class="vt-text-muted"><em>You do not have permission to delete this <?php echo $entity_type; ?>.</em></p>
    <?php endif; ?>
</div>

<script>
function confirmDeletion(form, entityName, confirmationType) {
    if (confirmationType === 'type_name') {
        const confirmInput = form.querySelector('#confirm_name');
        if (confirmInput && confirmInput.value !== entityName) {
            alert('The name you entered does not match. Deletion cancelled.');
            return false;
        }
    }

    return confirm('Are you sure you want to delete "' + entityName + '"? This action cannot be undone.');
}
</script>
