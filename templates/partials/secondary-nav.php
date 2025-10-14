<?php
/**
 * Elonara Social Secondary Navigation Partial
 * Reusable tab navigation component for Events, Conversations, and Communities
 *
 * @param array $tabs Array of tab objects with 'label', 'url', 'active' properties
 *                    Example: [
 *                      ['label' => 'Overview', 'url' => '/events/slug', 'active' => true],
 *                      ['label' => 'Manage', 'url' => '/events/slug/manage', 'active' => false]
 *                    ]
 * @param string $current_tab Current active tab identifier (optional, for manual control)
 */

// Ensure $tabs is set and is an array
$tabs = $tabs ?? [];
if (empty($tabs) || !is_array($tabs)) {
    return;
}
?>

<div class="vt-tab-nav vt-flex vt-gap-4 vt-flex-wrap">
    <?php foreach ($tabs as $tab) : ?>
        <?php
        $is_active = isset($tab['active']) ? $tab['active'] : false;
        $label = $tab['label'] ?? '';
        $url = $tab['url'] ?? '#';
        $badge_count = $tab['badge_count'] ?? null;
        ?>
        <a href="<?php echo htmlspecialchars($url); ?>"
           class="vt-btn <?php echo $is_active ? 'is-active' : ''; ?>"
           role="tab"
           aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>">
            <?php echo htmlspecialchars($label); ?>
            <?php if ($badge_count !== null) : ?>
                <span class="vt-badge vt-badge-sm"><?php echo intval($badge_count); ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
