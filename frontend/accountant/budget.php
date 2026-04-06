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
$category_filter = $_GET['category'] ?? '';

$nowMonth = (int)date('n');
$nowYear = (int)date('Y');
$defaultFiscalYear = $nowMonth >= 7 ? ($nowYear . '-' . ($nowYear + 1)) : (($nowYear - 1) . '-' . $nowYear);

// Handle add budget item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_budget'])) {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid CSRF token.';
  } else {
    $category = trim($_POST['category'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $fiscalYear = trim((string)($_POST['fiscal_year'] ?? $defaultFiscalYear));

    if (empty($category) || $amount <= 0) {
      $error = 'Category and a positive amount are required.';
    } else {
      try {
        if (table_exists('budget_items')) {
          $data = [
            'tenant_id' => $_SESSION['tenant_id'] ?? 1,
            'fiscal_year' => $fiscalYear,
            'category' => $category,
            'description' => $description,
            'budgeted_amount' => $amount,
            'actual_amount' => 0,
            'created_at' => date('Y-m-d H:i:s')
          ];
          insert_flexible('budget_items', $data);
          $success = 'Budget item added successfully.';
        } else {
          $error = 'Budget items table does not exist.';
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
  if (table_exists('budget_items')) {
    $db = db();
    $sql = "SELECT * FROM budget_items WHERE (tenant_id = :tid OR tenant_id IS NULL)";
    $params = [':tid' => $_SESSION['tenant_id'] ?? 1];
    if (!empty($category_filter)) {
      $sql .= " AND category = :cat";
      $params[':cat'] = $category_filter;
    }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($budgets as $b) {
      $total_budget += floatval($b['budgeted_amount'] ?? 0);
      $total_spent += floatval($b['actual_amount'] ?? 0);
    }
  }
} catch (Exception $e) {
  $budgets = [];
}

// Demo data if empty
if (empty($budgets)) {
  $budgets = [
    ['id' => 1, 'fiscal_year' => $defaultFiscalYear, 'category' => 'Textbooks', 'budgeted_amount' => 50000, 'actual_amount' => 32000, 'description' => 'Student textbooks for all grades'],
    ['id' => 2, 'fiscal_year' => $defaultFiscalYear, 'category' => 'Lab Equipment', 'budgeted_amount' => 30000, 'actual_amount' => 28500, 'description' => 'Laboratory supplies and equipment'],
    ['id' => 3, 'fiscal_year' => $defaultFiscalYear, 'category' => 'Sports', 'budgeted_amount' => 20000, 'actual_amount' => 12000, 'description' => 'Sports equipment and uniforms'],
    ['id' => 4, 'fiscal_year' => $defaultFiscalYear, 'category' => 'IT Infrastructure', 'budgeted_amount' => 45000, 'actual_amount' => 41000, 'description' => 'Computers and networking'],
    ['id' => 5, 'fiscal_year' => $defaultFiscalYear, 'category' => 'Maintenance', 'budgeted_amount' => 25000, 'actual_amount' => 15000, 'description' => 'Building and grounds maintenance'],
  ];
  $total_budget = 170000;
  $total_spent = 128500;
}

$remaining = $total_budget - $total_spent;
$pct_used = $total_budget > 0 ? round(($total_spent / $total_budget) * 100, 1) : 0;

$page_title = 'Budget Planning';
$page_icon = 'request_quote';
$page_subtitle = 'Plan, allocate, and monitor department budgets.';

ob_start();
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
    <p class="text-2xl font-extrabold text-primary">$<?php echo number_format($total_budget, 2); ?></p>
  </div>
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
    <p class="text-[11px] uppercase tracking-wider text-outline font-bold mb-2">Total Spent</p>
    <p class="text-2xl font-extrabold text-tertiary">$<?php echo number_format($total_spent, 2); ?></p>
  </div>
  <div class="bg-surface-container-low rounded-xl border border-outline-variant/10 p-5">
    <p class="text-[11px] uppercase tracking-wider text-outline font-bold mb-2">Remaining</p>
    <p class="text-2xl font-extrabold <?php echo $remaining < 0 ? 'text-error' : 'text-secondary'; ?>">$<?php echo number_format($remaining, 2); ?></p>
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
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Amount ($)</label>
        <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Fiscal Year</label>
        <input type="text" name="fiscal_year" value="<?php echo htmlspecialchars($defaultFiscalYear); ?>" class="w-full rounded-lg border border-outline-variant/20 bg-surface-container-lowest px-3 py-2 text-sm" placeholder="e.g. 2024-2025" required>
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
    <label class="block text-xs font-bold uppercase tracking-wider text-outline mb-2">Filter by Category</label>
    <select name="category" class="rounded-lg border border-outline-variant/20 bg-surface-container-low px-3 py-2 text-sm">
      <option value="">All Categories</option>
      <?php foreach (['Textbooks', 'Lab Equipment', 'Sports', 'IT Infrastructure', 'Maintenance', 'Administration'] as $category): ?>
        <option value="<?php echo $category; ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>><?php echo $category; ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="rounded-lg border border-outline-variant/30 px-4 py-2 text-sm font-semibold">Apply</button>
  <a href="budget.php" class="rounded-lg border border-outline-variant/30 px-4 py-2 text-sm font-semibold">Reset</a>
</form>

<div class="bg-surface-container-low rounded-xl border border-outline-variant/10 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-surface-container-high text-on-surface-variant">
        <tr>
          <th class="px-4 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Fiscal Year</th>
          <th class="px-4 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Category</th>
          <th class="px-4 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Allocated</th>
          <th class="px-4 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Spent</th>
          <th class="px-4 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Remaining</th>
          <th class="px-4 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Usage</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-outline-variant/10">
        <?php if (empty($budgets)): ?>
          <tr>
            <td colspan="7" class="px-4 py-8 text-center text-outline">No budget items found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($budgets as $b):
            $alloc = floatval($b['budgeted_amount'] ?? 0);
            $spent = floatval($b['actual_amount'] ?? 0);
            $rem = $alloc - $spent;
            $usage = $alloc > 0 ? round(($spent / $alloc) * 100, 1) : 0;
          ?>
            <tr>
              <td class="px-4 py-3"><?php echo htmlspecialchars((string)($b['fiscal_year'] ?? $defaultFiscalYear)); ?></td>
              <td class="px-4 py-3">
                <p class="font-semibold"><?php echo htmlspecialchars($b['category']); ?></p>
                <p class="text-xs text-outline mt-1"><?php echo htmlspecialchars($b['description'] ?? ''); ?></p>
              </td>
              <td class="px-4 py-3 text-right">$<?php echo number_format($alloc, 2); ?></td>
              <td class="px-4 py-3 text-right">$<?php echo number_format($spent, 2); ?></td>
              <td class="px-4 py-3 text-right <?php echo $rem < 0 ? 'text-error' : 'text-secondary'; ?>">$<?php echo number_format($rem, 2); ?></td>
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
$page_content = ob_get_clean();
require_once __DIR__ . '/partials/atlas-shell.php';
render_accountant_atlas_shell($page_title, 'reports', $page_content, $_SESSION['full_name'] ?? 'Accountant');
