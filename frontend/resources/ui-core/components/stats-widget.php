<?php

/**
 * Stats Widget Component — Reusable stat card grid.
 *
 * Usage:
 *   <?php
 *   $stats = [
 *     ['label' => 'Students', 'value' => 1250, 'icon' => 'fas fa-user-graduate', 'color' => 'blue', 'trend' => '+5%'],
 *     ['label' => 'Teachers', 'value' => 48,   'icon' => 'fas fa-chalkboard-teacher', 'color' => 'green'],
 *   ];
 *   include BASE_PATH . '/resources/ui-core/components/stats-widget.php';
 *   ?>
 *
 * Colors: blue, green, yellow, red, purple, indigo, pink, cyan
 */

$stats = $stats ?? [];
if (empty($stats)) return;

$color_map = [
  'blue'   => ['bg' => 'rgba(59,130,246,.12)',  'fg' => '#3B82F6'],
  'green'  => ['bg' => 'rgba(16,185,129,.12)',   'fg' => '#10B981'],
  'yellow' => ['bg' => 'rgba(245,158,11,.12)',   'fg' => '#F59E0B'],
  'red'    => ['bg' => 'rgba(239,68,68,.12)',    'fg' => '#EF4444'],
  'purple' => ['bg' => 'rgba(139,92,246,.12)',   'fg' => '#8B5CF6'],
  'indigo' => ['bg' => 'rgba(79,70,229,.12)',    'fg' => '#4F46E5'],
  'pink'   => ['bg' => 'rgba(236,72,153,.12)',   'fg' => '#EC4899'],
  'cyan'   => ['bg' => 'rgba(6,182,212,.12)',    'fg' => '#06B6D4'],
];
?>
<div class="stats-grid">
  <?php foreach ($stats as $s):
    $c = $color_map[$s['color'] ?? 'blue'] ?? $color_map['blue'];
  ?>
    <div class="stat-card" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--card-radius);padding:var(--space-lg);">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--space-md);">
        <div style="width:var(--stat-icon-size);height:var(--stat-icon-size);border-radius:var(--stat-icon-radius);background:<?php echo $c['bg']; ?>;display:flex;align-items:center;justify-content:center;">
          <i class="<?php echo htmlspecialchars($s['icon'] ?? 'fas fa-chart-bar'); ?>" style="color:<?php echo $c['fg']; ?>;font-size:1.2rem;"></i>
        </div>
        <?php if (!empty($s['trend'])): ?>
          <span style="font-size:var(--text-sm);font-weight:var(--font-semibold);color:<?php echo str_starts_with($s['trend'], '+') ? 'var(--success)' : 'var(--danger)'; ?>;">
            <?php echo htmlspecialchars($s['trend']); ?>
          </span>
        <?php endif; ?>
      </div>
      <div style="font-size:var(--text-sm);color:var(--text-muted);margin-bottom:var(--space-xs);"><?php echo htmlspecialchars($s['label']); ?></div>
      <div style="font-size:var(--text-2xl);font-weight:var(--font-extrabold);color:var(--text-primary);"><?php echo htmlspecialchars($s['value']); ?></div>
    </div>
  <?php endforeach; ?>
</div>
