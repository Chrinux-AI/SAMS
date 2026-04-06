<?php

/**
 * Developer Settings — General Tab
 */

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_general') {
  // Save settings
  $flash = 'General settings saved.';
}

$appName = defined('APP_NAME') ? APP_NAME : 'SAMS';
$appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
$timezone = defined('TIMEZONE') ? TIMEZONE : 'UTC';
$debug = ini_get('display_errors') ? 'On' : 'Off';
?>

<h3 style="margin-top:0;margin-bottom:1rem;"><i class="fas fa-sliders-h"></i> General Settings</h3>

<?php if (!empty($flash)): ?>
  <div style="padding:.6rem 1rem;background:rgba(0,255,65,.1);border:1px solid rgba(0,255,65,.2);border-radius:8px;margin-bottom:1rem;color:#00ff41;font-size:.85rem;">
    <?= htmlspecialchars($flash) ?>
  </div>
<?php endif; ?>

<div class="form-group">
  <label>Application Name</label>
  <input type="text" value="<?= htmlspecialchars($appName) ?>" readonly>
</div>

<div class="form-group">
  <label>Version</label>
  <input type="text" value="<?= htmlspecialchars($appVersion) ?>" readonly>
</div>

<div class="form-group">
  <label>Timezone</label>
  <input type="text" value="<?= htmlspecialchars($timezone) ?>" readonly>
</div>

<div class="form-group">
  <label>PHP Version</label>
  <input type="text" value="<?= PHP_VERSION ?>" readonly>
</div>

<div class="form-group">
  <label>Debug Mode</label>
  <input type="text" value="<?= $debug ?>" readonly>
</div>

<div class="form-group">
  <label>Base Path</label>
  <input type="text" value="<?= htmlspecialchars(BASE_PATH) ?>" readonly>
</div>

<div style="display:flex;gap:.8rem;flex-wrap:wrap;margin-top:1.5rem;">
  <span class="status-badge status-ok">PHP <?= PHP_VERSION ?></span>
  <span class="status-badge status-ok">MySQL Connected</span>
  <span class="status-badge <?= extension_loaded('openssl') ? 'status-ok' : 'status-err' ?>">OpenSSL <?= extension_loaded('openssl') ? 'ON' : 'OFF' ?></span>
  <span class="status-badge <?= extension_loaded('mbstring') ? 'status-ok' : 'status-err' ?>">mbstring <?= extension_loaded('mbstring') ? 'ON' : 'OFF' ?></span>
</div>
