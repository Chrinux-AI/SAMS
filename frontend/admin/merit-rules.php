<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once PROJECT_ROOT . '/backend/includes/advanced-sams.php';
require_once PROJECT_ROOT . '/backend/includes/tenant-context.php';

require_admin('../login.php');

$tenantId = current_tenant_id();
$tenant = active_tenant_context($tenantId);
$scopeColumn = table_has_column('merit_rules', 'tenant_id') ? 'tenant_id' : 'school_id';
$feedback = null;
$feedbackType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ruleId = (int) ($_POST['rule_id'] ?? 0);
    $payload = [
        'rule_code' => strtolower(trim((string) ($_POST['rule_code'] ?? ''))),
        'rule_name' => trim((string) ($_POST['rule_name'] ?? '')),
        'rule_category' => trim((string) ($_POST['rule_category'] ?? 'attendance')),
        'target_scope' => trim((string) ($_POST['target_scope'] ?? 'class')),
        'point_delta' => (int) ($_POST['point_delta'] ?? 0),
        'needs_approval' => isset($_POST['needs_approval']) ? 1 : 0,
        'rule_status' => trim((string) ($_POST['rule_status'] ?? 'active')),
        'created_by' => (int) ($_SESSION['user_id'] ?? 0),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $payload[$scopeColumn] = $tenantId;

    if ($payload['rule_code'] !== '' && $payload['rule_name'] !== '') {
        if ($ruleId > 0) {
            db()->update(
                'merit_rules',
                $payload,
                "id = ? AND {$scopeColumn} = ?",
                [$ruleId, $tenantId]
            );
            $feedback = 'Merit rule updated successfully.';
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            db()->insert('merit_rules', $payload);
            $feedback = 'Merit rule created successfully.';
        }
    } else {
        $feedback = 'Rule code and rule name are required.';
        $feedbackType = 'error';
    }
}

$editingRule = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editingRule = db()->fetchOne(
        "SELECT * FROM merit_rules WHERE id = ? AND {$scopeColumn} = ? LIMIT 1",
        [$editId, $tenantId]
    );
}

$rules = db()->fetchAll(
    "SELECT * FROM merit_rules WHERE {$scopeColumn} = ? ORDER BY rule_category, rule_code, id DESC",
    [$tenantId]
);

$page_title = 'Merit Rules';
$page_icon = 'rule';
$page_subtitle = 'Tune attendance, conduct, and academic point logic inside the main attendance project';
ob_start();
?>

<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 lg:col-span-5 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Rule Control</p>
        <h2 class="text-2xl font-extrabold text-primary mt-2"><?php echo htmlspecialchars($tenant['name'] ?? 'Current School'); ?></h2>
        <p class="text-sm text-slate-500 mt-2">Override donor defaults with school-specific scoring.</p>
      </div>
      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
        <?php echo count($rules); ?> rules
      </span>
    </div>

    <?php if ($feedback): ?>
      <div class="mt-5 rounded-lg px-4 py-3 text-sm <?php echo $feedbackType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
        <?php echo htmlspecialchars($feedback); ?>
      </div>
    <?php endif; ?>

    <form method="post" class="mt-6 space-y-4">
      <input type="hidden" name="rule_id" value="<?php echo (int) ($editingRule['id'] ?? 0); ?>">

      <div>
        <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Rule Code</label>
        <input type="text" name="rule_code" value="<?php echo htmlspecialchars((string) ($editingRule['rule_code'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="attendance_present" required>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Rule Name</label>
        <input type="text" name="rule_name" value="<?php echo htmlspecialchars((string) ($editingRule['rule_name'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Present attendance" required>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Category</label>
          <select name="rule_category" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach (['attendance', 'behavior', 'academic', 'special_exam', 'manual'] as $category): ?>
              <option value="<?php echo $category; ?>" <?php echo (($editingRule['rule_category'] ?? 'attendance') === $category) ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $category)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Scope</label>
          <select name="target_scope" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach (['class', 'student'] as $scope): ?>
              <option value="<?php echo $scope; ?>" <?php echo (($editingRule['target_scope'] ?? 'class') === $scope) ? 'selected' : ''; ?>><?php echo ucfirst($scope); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Point Delta</label>
          <input type="number" name="point_delta" value="<?php echo (int) ($editingRule['point_delta'] ?? 0); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Status</label>
          <select name="rule_status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <?php foreach (['active', 'inactive'] as $status): ?>
              <option value="<?php echo $status; ?>" <?php echo (($editingRule['rule_status'] ?? 'active') === $status) ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label class="flex items-center gap-3 text-sm text-slate-600">
        <input type="checkbox" name="needs_approval" value="1" <?php echo !empty($editingRule['needs_approval']) ? 'checked' : ''; ?>>
        <span>Require manual approval before posting</span>
      </label>

      <div class="flex gap-3">
        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-bold"><?php echo $editingRule ? 'Update Rule' : 'Create Rule'; ?></button>
        <a href="<?php echo base_url('admin/merit-rules.php'); ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-bold text-primary">Reset Form</a>
      </div>
    </form>
  </div>

  <div class="col-span-12 lg:col-span-7 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-lg font-bold text-primary">Configured Merit Rules</h3>
        <p class="text-xs text-slate-500">Attendance, behavior, and academic flows will read these before using fallback defaults.</p>
      </div>
      <a href="<?php echo base_url('admin/advanced-sams-setup.php'); ?>" class="px-4 py-2 rounded-lg border border-slate-200 text-xs font-bold text-primary">Back to Setup</a>
    </div>

    <?php if (empty($rules)): ?>
      <div class="rounded-lg border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
        No merit rules configured yet. The system is still using default attendance, behavior, and academic scoring.
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-left text-slate-500 uppercase text-[11px] tracking-widest">
              <th class="py-3 pr-4">Rule</th>
              <th class="py-3 pr-4">Category</th>
              <th class="py-3 pr-4">Scope</th>
              <th class="py-3 pr-4">Delta</th>
              <th class="py-3 pr-4">Status</th>
              <th class="py-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rules as $rule): ?>
              <tr class="border-b border-slate-100">
                <td class="py-3 pr-4">
                  <div class="font-semibold text-primary"><?php echo htmlspecialchars((string) $rule['rule_name']); ?></div>
                  <div class="text-xs text-slate-500"><?php echo htmlspecialchars((string) $rule['rule_code']); ?></div>
                </td>
                <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst((string) $rule['rule_category'])); ?></td>
                <td class="py-3 pr-4"><?php echo htmlspecialchars(ucfirst((string) $rule['target_scope'])); ?></td>
                <td class="py-3 pr-4 font-bold <?php echo ((int) $rule['point_delta']) >= 0 ? 'text-emerald-600' : 'text-rose-600'; ?>">
                  <?php echo ((int) $rule['point_delta']) >= 0 ? '+' : ''; ?><?php echo (int) $rule['point_delta']; ?>
                </td>
                <td class="py-3 pr-4"><?php echo htmlspecialchars((string) $rule['rule_status']); ?></td>
                <td class="py-3">
                  <a href="<?php echo base_url('admin/merit-rules.php?edit=' . (int) $rule['id']); ?>" class="text-primary font-semibold">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
