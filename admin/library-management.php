<?php

/**
 * Library Management System
 * Comprehensive library management with books, catalog, and circulation
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Only admins can access this
$_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner', 'librarian'])) {
    header('Location: ../login.php');
    exit;
}

// Get library statistics
$total_books = db()->fetchOne("SELECT COUNT(*) as count FROM library_books")['count'] ?? 0;
$total_members = db()->fetchOne("SELECT COUNT(*) as count FROM library_members")['count'] ?? 0;
$total_loans = db()->fetchOne("SELECT COUNT(*) as count FROM library_loans WHERE returned_date IS NULL")['count'] ?? 0;
$overdue_books = db()->fetchOne("SELECT COUNT(*) as count FROM library_loans WHERE due_date < CURDATE() AND returned_date IS NULL")['count'] ?? 0;

// Get recent library data
$recent_books = db()->fetchAll("SELECT * FROM library_books ORDER BY added_date DESC LIMIT 5");
$recent_loans = db()->fetchAll("SELECT ll.*, lm.member_name FROM library_loans ll JOIN library_members lm ON ll.member_id = lm.id ORDER BY loan_date DESC LIMIT 10");
$categories = db()->fetchAll("SELECT * FROM book_categories ORDER BY category_name");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_book') {
        $book_data = [
            'title' => $_POST['title'],
            'author' => $_POST['author'],
            'isbn' => $_POST['isbn'],
            'publisher' => $_POST['publisher'],
            'category_id' => $_POST['category_id'],
            'language' => $_POST['language'],
            'pages' => $_POST['pages'],
            'publication_year' => $_POST['publication_year'],
            'price' => $_POST['price'],
            'quantity' => $_POST['quantity'],
            'available_quantity' => $_POST['quantity'],
            'location' => $_POST['location'],
            'description' => $_POST['description'],
            'status' => 'available',
            'added_date' => date('Y-m-d H:i:s')
        ];

        db()->insert('library_books', $book_data);
        header('Location: library-management.php?success=book_added');
        exit;
    }

    if ($action === 'add_category') {
        $category_data = [
            'category_name' => $_POST['category_name'],
            'description' => $_POST['description'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('book_categories', $category_data);
        header('Location: library-management.php?success=category_added');
        exit;
    }

    if ($action === 'add_member') {
        $member_data = [
            'member_name' => $_POST['member_name'],
            'member_type' => $_POST['member_type'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address'],
            'member_id_number' => $_POST['member_id_number'],
            'membership_date' => date('Y-m-d'),
            'expiry_date' => date('Y-m-d', strtotime('+1 year')),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];

        db()->insert('library_members', $member_data);
        header('Location: library-management.php?success=member_added');
        exit;
    }
}

// Success message
$success_message = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'book_added':
            $success_message = 'Book added successfully!';
            break;
        case 'category_added':
            $success_message = 'Category added successfully!';
            break;
        case 'member_added':
            $success_message = 'Member added successfully!';
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - SAMS Platform</title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <style>
        .library-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.3);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .library-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #10B981;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }

        .stat-label {
            color: #6B7280;
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .panel {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #10B981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #10B981;
            color: white;
        }

        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6B7280;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #F9FAFB;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #E5E7EB;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #F3F4F6;
        }

        .data-table tr:hover {
            background: #F9FAFB;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-available {
            background: #D1FAE5;
            color: #065F46;
        }

        .status-borrowed {
            background: #FEF3C7;
            color: #92400E;
        }

        .status-overdue {
            background: #FEE2E2;
            color: #991B1B;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #E5E7EB;
        }

        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #10B981;
            border-bottom-color: #10B981;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .book-cover {
            width: 40px;
            height: 60px;
            background: linear-gradient(135deg, #10B981, #059669);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
            margin-right: 15px;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .library-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="library-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">Library Management</div>
            <div class="page-subtitle">Comprehensive library system with catalog management, circulation, and member services</div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Library Statistics -->
        <div class="library-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $total_books; ?></div>
                <div class="stat-label">Total Books</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_members; ?></div>
                <div class="stat-label">Library Members</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <div class="stat-value"><?php echo $total_loans; ?></div>
                <div class="stat-label">Books on Loan</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $overdue_books; ?></div>
                <div class="stat-label">Overdue Books</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">
            <!-- Library Forms -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Library Operations</h2>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="showTab('books')">Books</button>
                    <button class="tab" onclick="showTab('categories')">Categories</button>
                    <button class="tab" onclick="showTab('members')">Members</button>
                </div>

                <!-- Books Tab -->
                <div id="books-tab" class="tab-content active">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_book">

                        <div class="form-group">
                            <label for="title">Book Title *</label>
                            <input type="text" id="title" name="title" required>
                        </div>

                        <div class="form-group">
                            <label for="author">Author *</label>
                            <input type="text" id="author" name="author" required>
                        </div>

                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn">
                        </div>

                        <div class="form-group">
                            <label for="publisher">Publisher</label>
                            <input type="text" id="publisher" name="publisher">
                        </div>

                        <div class="form-group">
                            <label for="category_id">Category *</label>
                            <select id="category_id" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="language">Language</label>
                            <select id="language" name="language">
                                <option value="English">English</option>
                                <option value="Spanish">Spanish</option>
                                <option value="French">French</option>
                                <option value="German">German</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="pages">Pages</label>
                            <input type="number" id="pages" name="pages" min="1">
                        </div>

                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" id="publication_year" name="publication_year" min="1900" max="<?php echo date('Y'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="price">Price</label>
                            <input type="number" id="price" name="price" step="0.01">
                        </div>

                        <div class="form-group">
                            <label for="quantity">Quantity *</label>
                            <input type="number" id="quantity" name="quantity" min="1" required>
                        </div>

                        <div class="form-group">
                            <label for="location">Location/Shelf</label>
                            <input type="text" id="location" name="location">
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Book
                        </button>
                    </form>
                </div>

                <!-- Categories Tab -->
                <div id="categories-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_category">

                        <div class="form-group">
                            <label for="category_name">Category Name *</label>
                            <input type="text" id="category_name" name="category_name" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Category
                        </button>
                    </form>
                </div>

                <!-- Members Tab -->
                <div id="members-tab" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_member">

                        <div class="form-group">
                            <label for="member_name">Member Name *</label>
                            <input type="text" id="member_name" name="member_name" required>
                        </div>

                        <div class="form-group">
                            <label for="member_type">Member Type *</label>
                            <select id="member_type" name="member_type" required>
                                <option value="">Select Type</option>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="staff">Staff</option>
                                <option value="external">External Member</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="member_id_number">ID Number *</label>
                            <input type="text" id="member_id_number" name="member_id_number" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Member
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Library Activity -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Library Activity</h2>
                    <a href="library-reports.php" class="btn btn-primary">View All</a>
                </div>

                <!-- Recent Books Added -->
                <h3 style="margin-bottom: 15px; color: #374151;">Recent Books Added</h3>
                <table class="data-table" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_books as $book): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <div class="book-cover">BOOK</div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($book['author']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($book['category_id'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo $book['available_quantity']; ?>/<?php echo $book['quantity']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $book['status']; ?>">
                                        <?php echo ucfirst($book['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Recent Loans -->
                <h3 style="margin-bottom: 15px; color: #374151;">Recent Loans</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Book</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_loans as $loan): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($loan['member_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($loan['book_title'] ?? 'Unknown'); ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($loan['due_date'])); ?>
                                </td>
                                <td>
                                    <?php
                                    $status = 'returned';
                                    $badge_class = 'status-returned';
                                    if (!$loan['returned_date']) {
                                        if (strtotime($loan['due_date']) < time()) {
                                            $status = 'overdue';
                                            $badge_class = 'status-overdue';
                                        } else {
                                            $status = 'borrowed';
                                            $badge_class = 'status-borrowed';
                                        }
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked tab button
            event.target.classList.add('active');
        }
    </script>
</body>

</html>
