<?php

/**
 * Library Reports
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner', 'librarian'], true)) {
  header('Location: ../login.php');
  exit;
}

$total_books = table_exists('library_books') ? (int)(db()->fetchOne('SELECT COUNT(*) as c FROM library_books')['c'] ?? 0) : 0;
$total_members = table_exists('library_members') ? (int)(db()->fetchOne('SELECT COUNT(*) as c FROM library_members')['c'] ?? 0) : 0;
$active_loans = table_exists('library_loans') ? (int)(db()->fetchOne('SELECT COUNT(*) as c FROM library_loans WHERE returned_date IS NULL')['c'] ?? 0) : 0;
$overdue_loans = table_exists('library_loans') ? (int)(db()->fetchOne('SELECT COUNT(*) as c FROM library_loans WHERE due_date < CURDATE() AND returned_date IS NULL')['c'] ?? 0) : 0;

$recent_books = table_exists('library_books')
  ? (db()->fetchAll('SELECT title, author, isbn, status, added_date FROM library_books ORDER BY added_date DESC LIMIT 50') ?: [])
  : [];

$recent_loans = (table_exists('library_loans') && table_exists('library_members'))
  ? (db()->fetchAll(
    'SELECT ll.book_title, ll.loan_date, ll.due_date, ll.returned_date, ll.status, lm.member_name
         FROM library_loans ll
         LEFT JOIN library_members lm ON ll.member_id = lm.id
         ORDER BY ll.loan_date DESC
         LIMIT 50'
  ) ?: [])
  : [];

$page_title = 'Library Reports';
$page_icon = 'book-open';
$page_subtitle = 'Library activity, circulation and inventory insights';

ob_start();
?>
<div class="grid grid-cols-12 gap-6">
  <div class="col-span-12 bg-white rounded-xl border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-xl font-semibold">Library Reports</h2>
        <p class="text-sm text-gray-500">Snapshot of inventory and loan circulation.</p>
      </div>
      <a href="library-management.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700">Back to Library Management</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Books</p>
        <p class="text-2xl font-bold"><?php echo $total_books; ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Members</p>
        <p class="text-2xl font-bold"><?php echo $total_members; ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Active Loans</p>
        <p class="text-2xl font-bold"><?php echo $active_loans; ?></p>
      </div>
      <div class="rounded-lg border border-gray-100 p-4">
        <p class="text-sm text-gray-500">Overdue</p>
        <p class="text-2xl font-bold"><?php echo $overdue_loans; ?></p>
      </div>
    </div>

    <div class="space-y-6">
      <section>
        <h3 class="font-semibold mb-2">Recent Books</h3>
        <div class="overflow-x-auto border rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-3 py-2 text-left">Title</th>
                <th class="px-3 py-2 text-left">Author</th>
                <th class="px-3 py-2 text-left">ISBN</th>
                <th class="px-3 py-2 text-left">Status</th>
                <th class="px-3 py-2 text-left">Added</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_books as $book): ?>
                <tr class="border-t">
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($book['title'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($book['author'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($book['isbn'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($book['status'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($book['added_date'] ?? '-')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section>
        <h3 class="font-semibold mb-2">Recent Loans</h3>
        <div class="overflow-x-auto border rounded-lg">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50">
                <th class="px-3 py-2 text-left">Member</th>
                <th class="px-3 py-2 text-left">Book</th>
                <th class="px-3 py-2 text-left">Loan Date</th>
                <th class="px-3 py-2 text-left">Due Date</th>
                <th class="px-3 py-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_loans as $loan): ?>
                <tr class="border-t">
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($loan['member_name'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($loan['book_title'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($loan['loan_date'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($loan['due_date'] ?? '-')); ?></td>
                  <td class="px-3 py-2"><?php echo htmlspecialchars((string)($loan['status'] ?? '-')); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
