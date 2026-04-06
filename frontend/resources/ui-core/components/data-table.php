<?php

/**
 * Data Table Component — Reusable sortable table.
 *
 * Usage:
 *   <?php
 *   $table_columns = [
 *     ['key' => 'name',  'label' => 'Student Name'],
 *     ['key' => 'class', 'label' => 'Class'],
 *     ['key' => 'rate',  'label' => 'Attendance %', 'align' => 'center'],
 *   ];
 *   $table_rows = [
 *     ['name' => 'John Doe', 'class' => '10A', 'rate' => '95%'],
 *   ];
 *   $table_empty = 'No students found.';
 *   include BASE_PATH . '/resources/ui-core/components/data-table.php';
 *   ?>
 */

$table_columns = $table_columns ?? [];
$table_rows    = $table_rows ?? [];
$table_empty   = $table_empty ?? 'No data available.';
$table_class   = $table_class ?? '';

if (empty($table_columns)) return;
?>
<div class="table-responsive" style="overflow-x:auto;">
  <table class="data-table <?php echo htmlspecialchars($table_class); ?>" style="width:100%;border-collapse:collapse;">
    <thead>
      <tr>
        <?php foreach ($table_columns as $col): ?>
          <th style="text-align:<?php echo $col['align'] ?? 'left'; ?>;padding:var(--space-sm) var(--space-md);font-size:var(--text-xs);text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);border-bottom:2px solid var(--card-border);font-weight:var(--font-semibold);">
            <?php echo htmlspecialchars($col['label']); ?>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($table_rows)): ?>
        <tr>
          <td colspan="<?php echo count($table_columns); ?>" style="text-align:center;padding:var(--space-xl);color:var(--text-muted);font-size:var(--text-sm);"><?php echo htmlspecialchars($table_empty); ?></td>
        </tr>
      <?php else: ?>
        <?php foreach ($table_rows as $row): ?>
          <tr>
            <?php foreach ($table_columns as $col):
              $val = $row[$col['key']] ?? '';
            ?>
              <td style="text-align:<?php echo $col['align'] ?? 'left'; ?>;padding:var(--space-sm) var(--space-md);font-size:var(--text-sm);border-bottom:1px solid var(--card-border);">
                <?php echo is_string($val) ? $val : htmlspecialchars((string) $val); ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$table_columns = $table_rows = [];
$table_empty = $table_class = '';
?>
