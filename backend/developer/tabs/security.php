<?php

/**
 * Developer Settings — Security Tab
 */

$sessionTimeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
$maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
$lockout = defined('LOCKOUT_DURATION') ? LOCKOUT_DURATION : 900;
$minPw = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12;

// Security checks
$checks = [
  ['name' => 'HTTPS', 'status' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'desc' => 'Encrypted connection'],
  ['name' => 'Session Cookie HttpOnly', 'status' => (bool)ini_get('session.cookie_httponly'), 'desc' => 'Prevents JS access to session cookie'],
  ['name' => 'OpenSSL Extension', 'status' => extension_loaded('openssl'), 'desc' => 'Required for encryption'],
  ['name' => 'Error Display Off', 'status' => !ini_get('display_errors'), 'desc' => 'Errors should not be visible in production'],
  ['name' => 'Password Hashing', 'status' => defined('PASSWORD_BCRYPT'), 'desc' => 'BCrypt available'],
];
?>

<h3 style="margin-top:0;margin-bottom:1rem;"><i class="fas fa-shield-alt"></i> Security Settings</h3>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
  <div class="form-group">
    <label>Session Timeout</label>
    <input type="text" value="<?= $sessionTimeout ?>s (<?= round($sessionTimeout / 60) ?> min)" readonly>
  </div>
  <div class="form-group">
    <label>Max Login Attempts</label>
    <input type="text" value="<?= $maxAttempts ?>" readonly>
  </div>
  <div class="form-group">
    <label>Lockout Duration</label>
    <input type="text" value="<?= $lockout ?>s (<?= round($lockout / 60) ?> min)" readonly>
  </div>
  <div class="form-group">
    <label>Min Password Length</label>
    <input type="text" value="<?= $minPw ?> characters" readonly>
  </div>
</div>

<h4 style="margin-bottom:.8rem;">Security Checks</h4>
<table style="width:100%;border-collapse:collapse;font-size:.85rem;">
  <thead>
    <tr style="border-bottom:1px solid rgba(255,255,255,.1);">
      <th style="text-align:left;padding:.5rem;">Check</th>
      <th style="text-align:left;padding:.5rem;">Status</th>
      <th style="text-align:left;padding:.5rem;">Description</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($checks as $c): ?>
      <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
        <td style="padding:.5rem;font-weight:500;"><?= htmlspecialchars($c['name']) ?></td>
        <td style="padding:.5rem;">
          <span class="status-badge <?= $c['status'] ? 'status-ok' : 'status-warn' ?>">
            <?= $c['status'] ? 'PASS' : 'WARN' ?>
          </span>
        </td>
        <td style="padding:.5rem;opacity:.7;"><?= htmlspecialchars($c['desc']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
