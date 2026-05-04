<?php

/**
 * Modal Component — Reusable modal dialog.
 *
 * Usage:
 *   <?php
 *   $modal_id    = 'confirm-delete';
 *   $modal_title = 'Confirm Deletion';
 *   $modal_size  = 'md';  // sm, md, lg
 *   ob_start();
 *   ?>
 *   <p>Are you sure you want to delete this record?</p>
 *   <?php
 *   $modal_body = ob_get_clean();
 *   $modal_footer = '<button class="btn btn-secondary" onclick="closeModal(\'confirm-delete\')">Cancel</button>
 *                     <button class="btn btn-danger">Delete</button>';
 *   include BASE_PATH . '/resources/ui-core/components/modal.php';
 *   ?>
 *
 * Open with: openModal('confirm-delete')
 * Close with: closeModal('confirm-delete')
 */

$modal_id     = $modal_id ?? 'modal-' . uniqid();
$modal_title  = $modal_title ?? '';
$modal_body   = $modal_body ?? '';
$modal_footer = $modal_footer ?? '';
$modal_size   = $modal_size ?? 'md';

$size_map = ['sm' => '400px', 'md' => '560px', 'lg' => '780px'];
$max_width = $size_map[$modal_size] ?? $size_map['md'];
?>
<div id="<?php echo htmlspecialchars($modal_id); ?>" class="sams-modal" style="display:none;position:fixed;inset:0;z-index:var(--z-modal);align-items:center;justify-content:center;background:rgba(0,0,0,.5);">
  <div class="sams-modal-dialog" style="background:var(--card-bg);border-radius:var(--card-radius);width:92%;max-width:<?php echo $max_width; ?>;max-height:85vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg);animation:modalIn var(--duration-base) var(--ease-out);">
    <?php if ($modal_title): ?>
      <div class="sams-modal-header" style="padding:var(--space-lg);border-bottom:1px solid var(--card-border);display:flex;align-items:center;justify-content:space-between;">
        <h3 style="font-size:1.1rem;font-weight:var(--font-bold);"><?php echo htmlspecialchars($modal_title); ?></h3>
        <button onclick="closeModal('<?php echo htmlspecialchars($modal_id); ?>')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--text-muted);padding:4px;">&times;</button>
      </div>
    <?php endif; ?>
    <div class="sams-modal-body" style="padding:var(--space-lg);overflow-y:auto;flex:1;">
      <?php echo $modal_body; ?>
    </div>
    <?php if ($modal_footer): ?>
      <div class="sams-modal-footer" style="padding:var(--space-md) var(--space-lg);border-top:1px solid var(--card-border);display:flex;justify-content:flex-end;gap:var(--space-sm);">
        <?php echo $modal_footer; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
  function openModal(id) {
    var m = document.getElementById(id);
    if (m) {
      m.style.display = 'flex';
    }
  }

  function closeModal(id) {
    var m = document.getElementById(id);
    if (m) {
      m.style.display = 'none';
    }
  }
</script>
<?php
$modal_id = $modal_title = $modal_body = $modal_footer = '';
$modal_size = 'md';
?>
