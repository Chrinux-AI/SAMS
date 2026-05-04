<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

require_login('../login.php');
if (!has_role('accountant') && !has_role('admin')) {
  redirect('../login.php', 'Access denied. Accountant privileges required.', 'error');
}

$success = '';
$error = '';
$department_filter = $_GET['department'] ?? '';

// Handle add budget item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_budget'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $category = trim($_POST['category'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if (empty($category) || $amount <= 0) {
      $error = 'Category and a positive amount are required.';
    } else {
      try {
        if (table_exists('budgets')) {
          $data = [
            'category' => $category,
            'allocated_amount' => $amount,
            'spent_amount' => 0,
            'description' => $description,
            'department' => $department,
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'created_by' => $_SESSION['user_id'],
            'created_at' => date('Y-m-d H:i:s')
          ];
          insert_flexible('budgets', $data);
          $success = 'Budget item added successfully.';
        } else {
          $error = 'Budgets table does not exist.';
        }
      } catch (Exception $e) {
        $error = 'Error adding budget item: ' . $e->getMessage();
      }
    }
  }
}

// Fetch budget items
$budgets = [];
$total_budget = 0;
$total_spent = 0;
try {
  if (table_exists('budgets')) {
    $db = db();
    $sql = "SELECT * FROM budgets WHERE (tenant_id = :tid OR tenant_id IS NULL)";
    $params = [':tid' => $_SESSION['tenant_id'] ?? 1];
    if (!empty($department_filter)) {
      $sql .= " AND department = :dept";
      $params[':dept'] = $department_filter;
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($budgets as $b) {
      $total_budget += floatval($b['allocated_amount'] ?? 0);
      $total_spent += floatval($b['spent_amount'] ?? 0);
    }
  }
} catch (Exception $e) {
  $budgets = [];
}

// Demo data if empty
if (empty($budgets)) {
  $budgets = [
    ['id' => 1, 'category' => 'Textbooks', 'department' => 'Academics', 'allocated_amount' => 50000, 'spent_amount' => 32000, 'description' => 'Student textbooks for all grades'],
    ['id' => 2, 'category' => 'Lab Equipment', 'department' => 'Science', 'allocated_amount' => 30000, 'spent_amount' => 28500, 'description' => 'Laboratory supplies and equipment'],
    ['id' => 3, 'category' => 'Sports', 'department' => 'Athletics', 'allocated_amount' => 20000, 'spent_amount' => 12000, 'description' => 'Sports equipment and uniforms'],
    ['id' => 4, 'category' => 'IT Infrastructure', 'department' => 'IT', 'allocated_amount' => 45000, 'spent_amount' => 41000, 'description' => 'Computers and networking'],
    ['id' => 5, 'category' => 'Maintenance', 'department' => 'Facilities', 'allocated_amount' => 25000, 'spent_amount' => 15000, 'description' => 'Building and grounds maintenance'],
  ];
  $total_budget = 170000;
  $total_spent = 128500;
}

$remaining = $total_budget - $total_spent;
$pct_used = $total_budget > 0 ? round(($total_spent / $total_budget) * 100, 1) : 0;

$page_title = 'Budget Planning';
$page_icon = 'request_quote';
$page_subtitle = 'Plan, allocate, and monitor department budgets.';

$activeTab = 'budget';
require_once __DIR__ . '/partials/header.php';
?>

<?php if ($success): ?>
  <div class="mb-4 rounded-xl border border-emerald-300/40 bg-emerald-50 px-4 py-3 text-emerald-700 text-sm"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="mb-4 rounded-xl border border-red-300/40 bg-red-50 px-4 py-3 text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-7">
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
    <p class="text-[11px] uppercase tracking-wider text-outline font-bold mb-2">Total Budget</p>
    <p class="text-2xl font-extrabold text-primary"><?php echo accountant_currency($total_budget); ?></p>
  </div>
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
    <p class="text-[11px] uppercase tracking-wider text-outline font-bold mb-2">Total Spent</p>
    <p class="text-2xl font-extrabold text-tertiary"><?php echo accountant_currency($total_spent); ?></p>
  </div>
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
    <p class="text-[11px] uppercase tracking-wider text-outline font-bold mb-2">Remaining</p>
    <p class="text-2xl font-extrabold <?php echo $remaining < 0 ? 'text-error' : 'text-secondary'; ?>"><?php echo accountant_currency($remaining); ?></p>
  </div>
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
    <p class="text-[11px] uppercase tracking-wider text-outline font-bold mb-2">Budget Used</p>
    <p class="text-2xl font-extrabold <?php echo $pct_used > 90 ? 'text-error' : ($pct_used > 70 ? 'text-tertiary' : 'text-secondary'); ?>"><?php echo $pct_used; ?>%</p>
    <div class="mt-3 h-2 rounded-full bg-surface-container-high overflow-hidden">
      <div class="h-full rounded-full <?php echo $pct_used > 90 ? 'bg-error' : ($pct_used > 70 ? 'bg-tertiary' : 'bg-secondary'); ?>" style="width:<?php echo min(max($pct_used, 0), 100); ?>%"></div>
    </div>
  </div>
</div>

<div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-6 mb-7">
  <h3 class="text-base font-bold mb-4">Add Budget Item</h3>
  <form method="POST" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="add_budget" value="1">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Category</label>
        <input type="text" name="category" required placeholder="e.g. Textbooks" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Amount (₦)</label>
        <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Department</label>
        <select name="department" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest px-3 py-2 text-sm">
          <option value="">Select Department</option>
          <option value="Academics">Academics</option>
          <option value="Science">Science</option>
          <option value="Athletics">Athletics</option>
          <option value="IT">IT</option>
          <option value="Facilities">Facilities</option>
          <option value="Administration">Administration</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Description</label>
        <input type="text" name="description" placeholder="Brief description" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest px-3 py-2 text-sm">
      </div>
    </div>
    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-primary text-on-primary px-4 py-2 text-sm font-semibold">
      <span class="material-symbols-outlined" style="font-size:16px">add</span>
      Add Budget Item
    </button>
  </form>
</div>

<form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
  <div>
    <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Filter by Department</label>
    <select name="department" class="rounded-lg border border-outline-variant/20 bg-surface-container-low px-3 py-2 text-sm">
      <option value="">All Departments</option>
      <?php foreach (['Academics', 'Science', 'Athletics', 'IT', 'Facilities', 'Administration'] as $dept): ?>
        <option value="<?php echo $dept; ?>" <?php echo $department_filter === $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="rounded-lg border border-outline-variant/30 px-4 py-2 text-sm font-semibold">Apply</button>
  <a href="index.php?page=budget" class="rounded-lg border border-outline-variant/30 px-4 py-2 text-sm font-semibold">Reset</a>
</form>

<div class="bg-surface-container-low rounded-xl border border-outline-variant/10 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-surface-container-high text-on-surface-variant">
        <tr>
          <th class="px-4 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Category</th>
          <th class="px-4 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Department</th>
          <th class="px-4 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Allocated</th>
          <th class="px-4 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Spent</th>
          <th class="px-4 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Remaining</th>
          <th class="px-4 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Usage</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant/10">
        <?php if (empty($budgets)): ?>
          <tr>
            <td colspan="6" class="px-4 py-8 text-center text-outline">No budget items found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($budgets as $b):
            $alloc = floatval($b['allocated_amount'] ?? 0);
            $spent = floatval($b['spent_amount'] ?? 0);
            $rem = $alloc - $spent;
            $usage = $alloc > 0 ? round(($spent / $alloc) * 100, 1) : 0;
          ?>
            <tr>
              <td class="px-4 py-3">
                <p class="font-semibold"><?php echo htmlspecialchars($b['category']); ?></p>
                <p class="text-xs text-outline mt-1"><?php echo htmlspecialchars($b['description'] ?? ''); ?></p>
              </td>
              <td class="px-4 py-3"><?php echo htmlspecialchars($b['department'] ?? 'N/A'); ?></td>
              <td class="px-4 py-3 text-right"><?php echo accountant_currency($alloc); ?></td>
              <td class="px-4 py-3 text-right"><?php echo accountant_currency($spent); ?></td>
              <td class="px-4 py-3 text-right <?php echo $rem < 0 ? 'text-error' : 'text-secondary'; ?>"><?php echo accountant_currency($rem); ?></td>
              <td class="px-4 py-3">
                <p class="text-xs mb-1 <?php echo $usage > 90 ? 'text-error' : ($usage > 70 ? 'text-tertiary' : 'text-secondary'); ?>"><?php echo $usage; ?>%</p>
                <div class="h-1.5 rounded-full bg-surface-container-high overflow-hidden">
                  <div class="h-full rounded-full <?php echo $usage > 90 ? 'bg-error' : ($usage > 70 ? 'bg-tertiary' : 'bg-secondary'); ?>" style="width:<?php echo min(max($usage, 0), 100); ?>%"></div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
require_once __DIR__ . '/partials/footer.php';
