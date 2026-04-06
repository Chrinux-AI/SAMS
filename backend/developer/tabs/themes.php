<?php

/**
 * Developer Settings — Themes Tab
 */

$themesDir = BASE_PATH . '/assets/theme';
$themes = [];
if (is_dir($themesDir)) {
  foreach (glob($themesDir . '/*.css') as $f) {
    $themes[] = [
      'name' => basename($f, '.css'),
      'file' => basename($f),
      'size' => filesize($f),
      'modified' => date('Y-m-d H:i', filemtime($f)),
    ];
  }
}

$currentTheme = $_SESSION['theme'] ?? 'light';
?>

<h3 style="margin-top:0;margin-bottom:1rem;"><i class="fas fa-palette"></i> Theme Management</h3>

<div class="form-group">
  <label>Current User Theme</label>
  <input type="text" value="<?= htmlspecialchars($currentTheme) ?>" readonly>
</div>

<h4 style="margin-bottom:.8rem;">Available Theme Files</h4>
<?php if (empty($themes)): ?>
  <p style="opacity:.6;">No theme files found in assets/theme/</p>
<?php else: ?>
  <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
    <thead>
      <tr style="border-bottom:1px solid rgba(255,255,255,.1);">
        <th style="text-align:left;padding:.5rem;">Theme</th>
        <th style="text-align:left;padding:.5rem;">File</th>
        <th style="text-align:right;padding:.5rem;">Size</th>
        <th style="text-align:left;padding:.5rem;">Modified</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($themes as $t): ?>
        <tr style="border-bottom:1px solid rgba(255,255,255,.05);">
          <td style="padding:.5rem;font-weight:500;"><?= htmlspecialchars($t['name']) ?></td>
          <td style="padding:.5rem;opacity:.7;"><?= htmlspecialchars($t['file']) ?></td>
          <td style="padding:.5rem;text-align:right;"><?= number_format($t['size'] / 1024, 1) ?> KB</td>
          <td style="padding:.5rem;opacity:.7;"><?= $t['modified'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
