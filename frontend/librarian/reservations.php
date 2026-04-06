<?php
/**
 * SAMS Library - Reservations
 * Track and manage book reservations by students
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

// Handle new reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reservation'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('reservations.php', 'Invalid security token.', 'error');
    }
    $book_id = (int)($_POST['book_id'] ?? 0);
    $student_id = (int)($_POST['student_id'] ?? 0);

    if ($book_id < 1 || $student_id < 1) {
        redirect('reservations.php', 'Please select a student and a book.', 'error');
    }

    try {
        // Check if reservation table exists, create if not
        if (!table_exists('library_reservations')) {
            db()->query("CREATE TABLE IF NOT EXISTS library_reservations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL DEFAULT 1,
                book_id INT NOT NULL,
                student_id INT NOT NULL,
                reservation_date DATE NOT NULL,
                status ENUM('pending','fulfilled','cancelled') DEFAULT 'pending',
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Check for duplicate pending reservation
        $existing = db()->fetch("SELECT id FROM library_reservations WHERE book_id = ? AND student_id = ? AND status = 'pending' AND tenant_id = ?", [$book_id, $student_id, $tenantId]);
        if ($existing) {
            redirect('reservations.php', 'Student already has a pending reservation for this book.', 'error');
        }

        insert_flexible('library_reservations', [
            'tenant_id'        => $tenantId,
            'book_id'          => $book_id,
            'student_id'       => $student_id,
            'reservation_date' => date('Y-m-d'),
            'status'           => 'pending',
            'created_by'       => $user_id,
        ]);
        redirect('reservations.php', 'Reservation created successfully.', 'success');
    } catch (Throwable $e) {
        redirect('reservations.php', 'Error creating reservation: ' . $e->getMessage(), 'error');
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('reservations.php', 'Invalid security token.', 'error');
    }
    $res_id = (int)($_POST['res_id'] ?? 0);
    $new_status = sanitize($_POST['new_status'] ?? '');
    if (!in_array($new_status, ['fulfilled', 'cancelled'], true)) {
        redirect('reservations.php', 'Invalid status.', 'error');
    }
    try {
        if (table_exists('library_reservations')) {
            db()->update('library_reservations', ['status' => $new_status], 'id = ? AND tenant_id = ?', [$res_id, $tenantId]);
        }
        redirect('reservations.php', 'Reservation updated.', 'success');
    } catch (Throwable $e) {
        redirect('reservations.php', 'Error updating reservation.', 'error');
    }
}

// Fetch reservations
$reservations = [];
try {
    if (table_exists('library_reservations')) {
        $reservations = db()->fetchAll("
            SELECT lr.*, lb.title, lb.author, lb.available_copies,
                   u.first_name, u.last_name, u.assigned_id
            FROM library_reservations lr
            JOIN library_books lb ON lr.book_id = lb.id
            JOIN users u ON lr.student_id = u.id
            WHERE lr.tenant_id = ?
            ORDER BY FIELD(lr.status, 'pending', 'fulfilled', 'cancelled'), lr.reservation_date DESC
        ", [$tenantId]);
    }
} catch (Throwable $e) {}

// Fetch books and students for the form
$books = [];
$students = [];
try {
    if (table_exists('library_books')) {
        $books = db()->fetchAll("SELECT id, title, author FROM library_books WHERE tenant_id = ? ORDER BY title", [$tenantId]);
    }
    $students = db()->fetchAll("SELECT id, first_name, last_name, assigned_id FROM users WHERE role = 'student' ORDER BY first_name, last_name") ?: [];
} catch (Throwable $e) {}

$pending_count = count(array_filter($reservations, fn($r) => $r['status'] === 'pending'));

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - SAMS</title>
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
            <div class="page-icon-orb"><i class="fas fa-bookmark"></i></div>
            <div>
                <h1>Reservations</h1>
                <p>Manage book reservations and hold requests</p>
            </div>
        </div>
        <div class="cyber-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Add Reservation -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus"></i> New Reservation</h5></div>
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
                                        <?php foreach ($books as $b): ?>
                                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['title']) ?> — <?= htmlspecialchars($b['author'] ?? 'Unknown') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="add_reservation" class="btn btn-primary w-100"><i class="fas fa-bookmark"></i> Create Reservation</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Reservations List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-list"></i> All Reservations</h5>
                            <span class="badge bg-warning text-dark"><?= $pending_count ?> pending</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Student</th>
                                        <th>Book</th>
                                        <th>Available</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reservations)): ?>
                                        <tr><td colspan="7" class="text-center py-4">No reservations yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($reservations as $r): ?>
                                            <tr>
                                                <td><?= (int)$r['id'] ?></td>
                                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                                <td><?= htmlspecialchars($r['title']) ?></td>
                                                <td>
                                                    <?php $avail = (int)($r['available_copies'] ?? 0); ?>
                                                    <span class="badge <?= $avail > 0 ? 'bg-success' : 'bg-danger' ?>"><?= $avail ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($r['reservation_date']) ?></td>
                                                <td>
                                                    <?php
                                                    $badge = match($r['status']) {
                                                        'pending' => 'bg-warning text-dark',
                                                        'fulfilled' => 'bg-success',
                                                        'cancelled' => 'bg-secondary',
                                                        default => 'bg-info',
                                                    };
                                                    ?>
                                                    <span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($r['status'] === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                            <input type="hidden" name="res_id" value="<?= (int)$r['id'] ?>">
                                                            <input type="hidden" name="new_status" value="fulfilled">
                                                            <button type="submit" name="update_status" class="btn btn-sm btn-success" title="Mark Fulfilled"><i class="fas fa-check"></i></button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                            <input type="hidden" name="res_id" value="<?= (int)$r['id'] ?>">
                                                            <input type="hidden" name="new_status" value="cancelled">
                                                            <button type="submit" name="update_status" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="fas fa-times"></i></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
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
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
