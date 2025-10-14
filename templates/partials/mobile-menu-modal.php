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
<div id="mobile-menu-modal" class="app-mobile-menu-modal" style="display: none;">
  <div class="app-modal-overlay" data-close-mobile-menu></div>
  <div class="app-modal-content">
    <div class="app-modal-header">
      <h3 class="app-modal-title">Menu</h3>
      <button type="button" class="app-btn app-btn-sm" data-close-mobile-menu>&times;</button>
    </div>
    <div class="app-modal-body">
      <?= $sidebar_content ?>
    </div>
  </div>
</div>
