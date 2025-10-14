<?php
/**
 * Mobile Menu Modal Partial
 *
 * Reusable modal for displaying sidebar navigation on mobile devices
 *
 * Required variables:
 * - $sidebar_content: HTML content from sidebar
 */

$sidebar_content = $sidebar_content ?? '';
?>

<!-- Mobile Menu Modal -->
<div id="mobile-menu-modal" class="vt-mobile-menu-modal" style="display: none;">
  <div class="vt-modal-overlay" data-close-mobile-menu></div>
  <div class="vt-modal-content">
    <div class="vt-modal-header">
      <h3 class="vt-modal-title">Menu</h3>
      <button type="button" class="vt-btn vt-btn-sm" data-close-mobile-menu>&times;</button>
    </div>
    <div class="vt-modal-body">
      <?= $sidebar_content ?>
    </div>
  </div>
</div>
