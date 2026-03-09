<?php

/**
 * SAMS Library - Issue & Return Books
 * Issue books to students and process returns
 */
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
require_login('../login.php');
if (!has_role('librarian') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Librarian privileges required.', 'error');
}

$csrf = generate_csrf_token();
$tenantId = $_SESSION['tenant_id'] ?? 1;
$user_id = $_SESSION['user_id'];

// Handle Issue Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_book'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('issue-return.php', 'Invalid security token.', 'error');
    }

    $book_id = (int)($_POST['book_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);
    $due_date = sanitize($_POST['due_date'] ?? '');

    if ($book_id < 1 || $student_id < 1 || $due_date === '') {
        redirect('issue-return.php', 'All fields are required.', 'error');
    }

    try {
        // Check book availability
        $book = db()->fetch("SELECT * FROM library_books WHERE id = ? AND tenant_id = ?", [$book_id, $tenantId]);
        if (!$book) {
            redirect('issue-return.php', 'Book not found.', 'error');
        }
        if ((int)($book['available_copies'] ?? 0) < 1) {
            redirect('issue-return.php', 'No copies available for this book.', 'error');
        }

        // Check student doesn't already have this book
        $existing = db()->fetch("SELECT id FROM library_loans WHERE book_id = ? AND student_id = ? AND status = 'active' AND tenant_id = ?", [$book_id, $student_id, $tenantId]);
        if ($existing) {
            redirect('issue-return.php', 'Student already has an active loan for this book.', 'error');
        }

        // Create loan record
        insert_flexible('library_loans', [
            'tenant_id'  => $tenantId,
            'book_id'    => $book_id,
            'student_id' => $student_id,
            'loan_date'  => date('Y-m-d'),
            'due_date'   => $due_date,
            'status'     => 'active',
            'issued_by'  => $user_id,
            'fine_amount' => 0,
        ]);

        // Decrease available copies
        db()->query("UPDATE library_books SET available_copies = available_copies - 1 WHERE id = ? AND tenant_id = ?", [$book_id, $tenantId]);

        redirect('issue-return.php', 'Book issued successfully.', 'success');
    } catch (Throwable $e) {
        redirect('issue-return.php', 'Error issuing book: ' . $e->getMessage(), 'error');
    }
}

// Handle Return Book
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('issue-return.php', 'Invalid security token.', 'error');
    }

    $loan_id = (int)($_POST['loan_id'] ?? 0);
    $fine = max(0, (float)($_POST['fine_amount'] ?? 0));

    if ($loan_id < 1) {
        redirect('issue-return.php', 'Loan ID is required.', 'error');
    }

    try {
        $loan = db()->fetch("SELECT * FROM library_loans WHERE id = ? AND tenant_id = ? AND status = 'active'", [$loan_id, $tenantId]);
        if (!$loan) {
            redirect('issue-return.php', 'Active loan not found.', 'error');
        }

        // Update loan
        db()->update('library_loans', [
            'status'      => 'returned',
            'return_date' => date('Y-m-d'),
            'fine_amount' => $fine,
        ], 'id = ? AND tenant_id = ?', [$loan_id, $tenantId]);

        // Increase available copies
        db()->query("UPDATE library_books SET available_copies = available_copies + 1 WHERE id = ? AND tenant_id = ?", [$loan['book_id'], $tenantId]);

        redirect('issue-return.php', 'Book returned successfully.' . ($fine > 0 ? " Fine: $" . number_format($fine, 2) : ''), 'success');
    } catch (Throwable $e) {
        redirect('issue-return.php', 'Error returning book: ' . $e->getMessage(), 'error');
    }
}

// Fetch available books
$available_books = [];
try {
    if (table_exists('library_books')) {
        $available_books = db()->fetchAll("SELECT id, title, author, isbn, available_copies FROM library_books WHERE tenant_id = ? AND available_copies > 0 ORDER BY title", [$tenantId]);
    }
} catch (Throwable $e) {
}

// Fetch students
$students = [];
try {
    $students = db()->fetchAll("SELECT id, first_name, last_name, assigned_id FROM users WHERE role = 'student' ORDER BY first_name, last_name") ?: [];
} catch (Throwable $e) {
}

// Fetch active loans for return
$active_loans = [];
try {
    if (table_exists('library_loans')) {
        $active_loans = db()->fetchAll("
            SELECT ll.id, ll.loan_date, ll.due_date, lb.title, lb.isbn,
                   u.first_name, u.last_name, u.assigned_id,
                   DATEDIFF(CURDATE(), ll.due_date) as days_overdue
            FROM library_loans ll
            JOIN library_books lb ON ll.book_id = lb.id
            JOIN users u ON ll.student_id = u.id
            WHERE ll.tenant_id = ? AND ll.status = 'active'
            ORDER BY ll.due_date ASC
        ", [$tenantId]);
    }
} catch (Throwable $e) {
}

$default_due = date('Y-m-d', strtotime('+14 days'));
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue &amp; Return - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
</head>

<body>
    <div class="app-layout">
        <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
        <main class="main-content">
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-exchange-alt"></i></div>
                <div>
                    <h1>Issue &amp; Return Books</h1>
                    <p>Manage book circulation - issue to students and process returns</p>
                </div>
            </div>
            <div class="cyber-content">
                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Issue Book -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-arrow-right"></i> Issue Book</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Student <span class="text-danger">*</span></label>
                                        <select name="student_id" class="form-select" required>
                                            <option value="">Select Student</option>
                                            <?php foreach ($students as $s): ?>
                                                <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?> <?= $s['assigned_id'] ? '(' . htmlspecialchars($s['assigned_id']) . ')' : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Book <span class="text-danger">*</span></label>
                                        <select name="book_id" class="form-select" required>
                                            <option value="">Select Book</option>
                                            <?php foreach ($available_books as $b): ?>
                                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['title']) ?> — <?= htmlspecialchars($b['author'] ?? 'Unknown') ?> (<?= (int)$b['available_copies'] ?> avail.)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Due Date <span class="text-danger">*</span></label>
                                        <input type="date" name="due_date" class="form-control" required value="<?= htmlspecialchars($default_due) ?>" min="<?= date('Y-m-d') ?>">
                                    </div>
                                    <button type="submit" name="issue_book" class="btn btn-primary w-100"><i class="fas fa-hand-holding"></i> Issue Book</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Return Book -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-arrow-left"></i> Return Book</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Select Active Loan <span class="text-danger">*</span></label>
                                        <select name="loan_id" class="form-select" required>
                                            <option value="">Select Loan to Return</option>
                                            <?php foreach ($active_loans as $loan): ?>
                                                <option value="<?= (int)$loan['id'] ?>" <?= $loan['days_overdue'] > 0 ? 'class="text-danger"' : '' ?>>
                                                    #<?= (int)$loan['id'] ?> — <?= htmlspecialchars($loan['title']) ?> → <?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?>
                                                    (Due: <?= htmlspecialchars($loan['due_date']) ?>)
                                                    <?= $loan['days_overdue'] > 0 ? ' [OVERDUE ' . (int)$loan['days_overdue'] . 'd]' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Fine Amount ($)</label>
                                        <input type="number" name="fine_amount" class="form-control" step="0.01" min="0" value="0.00">
                                        <small class="text-muted">Enter fine amount if applicable</small>
                                    </div>
                                    <button type="submit" name="return_book" class="btn btn-success w-100"><i class="fas fa-undo"></i> Process Return</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Active Loans -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> Current Active Loans (<?= count($active_loans) ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Loan #</th>
                                    <th>Student</th>
                                    <th>Book</th>
                                    <th>Loan Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_loans)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No active loans.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($active_loans as $loan): ?>
                                        <tr>
                                            <td>#<?= (int)$loan['id'] ?></td>
                                            <td><?= htmlspecialchars($loan['first_name'] . ' ' . $loan['last_name']) ?></td>
                                            <td><?= htmlspecialchars($loan['title']) ?></td>
                                            <td><?= htmlspecialchars($loan['loan_date']) ?></td>
                                            <td><?= htmlspecialchars($loan['due_date']) ?></td>
                                            <td>
                                                <?php if ($loan['days_overdue'] > 0): ?>
                                                    <span class="badge bg-danger">Overdue <?= (int)$loan['days_overdue'] ?>d</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">On Time</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>

</html>
