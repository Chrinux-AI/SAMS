<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin();

$full_name = $_SESSION['full_name'];
$admin_id = $_SESSION['user_id'];

function generate_bulk_assigned_id(string $role): string
{
    $year = date('Y');
    $suffix = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    if ($role === 'teacher') {
        return 'TCH' . $year . $suffix;
    }
    if ($role === 'student') {
        return 'STU' . $year . $suffix;
    }
    return strtoupper(substr($role, 0, 3)) . $year . $suffix;
}

// Handle bulk approval/rejection for pending users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_user_ids = array_values(array_filter(array_map('intval', $_POST['selected_user_ids'] ?? []), static fn($v) => $v > 0));

    if (!empty($selected_user_ids)) {
        $processed = 0;
        foreach ($selected_user_ids as $user_id) {
            $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
            if (!$user) {
                continue;
            }

            if ($bulk_action === 'approve_selected') {
                $assigned_id = trim((string)($_POST['bulk_assigned_ids'][$user_id] ?? ''));
                if ($assigned_id === '') {
                    $assigned_id = generate_bulk_assigned_id((string)$user['role']);
                }

                db()->update(
                    'users',
                    [
                        'approved' => 1,
                        'approved_by' => $admin_id,
                        'approved_at' => date('Y-m-d H:i:s'),
                        'status' => 'active'
                    ],
                    'id = ?',
                    [$user_id]
                );

                if ($user['role'] === 'student') {
                    db()->update('students', ['student_id' => $assigned_id, 'status' => 'active'], 'user_id = ?', [$user_id]);
                } elseif ($user['role'] === 'teacher') {
                    db()->update('teachers', ['teacher_id' => $assigned_id, 'status' => 'active'], 'user_id = ?', [$user_id]);
                }

                send_approval_notification(
                    $user_id,
                    $user['email'],
                    $user['first_name'] . ' ' . $user['last_name'],
                    $user['role'],
                    $assigned_id,
                    $user['username']
                );

                log_activity($admin_id, 'bulk_approve_user', 'users', $user_id, "Bulk approved user: {$user['email']} with ID: {$assigned_id}");
                $processed++;
            }

            if ($bulk_action === 'reject_selected') {
                log_activity($admin_id, 'bulk_reject_user', 'users', $user_id, "Bulk rejected user: {$user['email']}");
                db()->delete('users', 'id = ?', [$user_id]);
                $processed++;
            }
        }

        if ($bulk_action === 'approve_selected') {
            $success_msg = "Bulk approval completed: {$processed} user(s) approved.";
        } elseif ($bulk_action === 'reject_selected') {
            $success_msg = "Bulk rejection completed: {$processed} user(s) rejected/deleted.";
        }
    }
}

// Handle approval for unverified users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_unverified'])) {
    $user_id = intval($_POST['user_id']);
    $assigned_id = sanitize($_POST['assigned_id']);

    // Get user details
    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if ($user) {
        // Approve and verify in one go
        db()->update(
            'users',
            [
                'email_verified' => 1,
                'approved' => 1,
                'approved_by' => $admin_id,
                'approved_at' => date('Y-m-d H:i:s'),
                'status' => 'active',
                'email_verification_token' => null,
                'token_expires_at' => null
            ],
            'id = ?',
            [$user_id]
        );

        // If student, update student record with assigned ID
        if ($user['role'] === 'student') {
            db()->update(
                'students',
                [
                    'student_id' => $assigned_id,
                    'status' => 'active'
                ],
                'user_id = ?',
                [$user_id]
            );
        }

        // If teacher, update teacher record with assigned ID
        if ($user['role'] === 'teacher') {
            db()->update(
                'teachers',
                [
                    'teacher_id' => $assigned_id,
                    'status' => 'active'
                ],
                'user_id = ?',
                [$user_id]
            );
        }

        // Send approval email with assigned ID
        $email_sent = send_approval_notification(
            $user_id,
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $user['role'],
            $assigned_id,
            $user['username']
        );

        if (!$email_sent) {
            error_log("Failed to send approval email to {$user['email']}");
        }

        log_activity($admin_id, 'approve_unverified_user', 'users', $user_id, "Approved unverified user: {$user['email']} with ID: {$assigned_id}");
        try {
            AuditLogger::log('approve_unverified_user', 'users', "Approved unverified user #{$user_id}: {$user['email']} with ID: {$assigned_id}", $_SESSION['user_id'] ?? null);
        } catch (\Throwable $e) {
        }

        $success_msg = "User approved successfully (email verification bypassed)! Email sent to {$user['email']}";
    }
}

// Handle approval action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user'])) {
    $user_id = intval($_POST['user_id']);
    $assigned_id = sanitize($_POST['assigned_id']);

    // Get user details
    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if ($user) {
        // Update user approval status
        db()->update(
            'users',
            [
                'approved' => 1,
                'approved_by' => $admin_id,
                'approved_at' => date('Y-m-d H:i:s'),
                'status' => 'active'
            ],
            'id = ?',
            [$user_id]
        );

        // If student, update student record with assigned ID
        if ($user['role'] === 'student') {
            db()->update(
                'students',
                [
                    'student_id' => $assigned_id,
                    'status' => 'active'
                ],
                'user_id = ?',
                [$user_id]
            );
        }

        // If teacher, update teacher record with assigned ID
        if ($user['role'] === 'teacher') {
            db()->update(
                'teachers',
                [
                    'teacher_id' => $assigned_id,
                    'status' => 'active'
                ],
                'user_id = ?',
                [$user_id]
            );
        }

        // Send approval email with assigned ID
        $email_sent = send_approval_notification(
            $user_id,
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $user['role'],
            $assigned_id,
            $user['username']
        );

        if (!$email_sent) {
            error_log("Failed to send approval email to {$user['email']}");
        }

        log_activity($admin_id, 'approve_user', 'users', $user_id, "Approved user: {$user['email']} with ID: {$assigned_id}");
        try {
            AuditLogger::log('approve_user', 'users', "Approved user #{$user_id}: {$user['email']} with ID: {$assigned_id}", $_SESSION['user_id'] ?? null);
        } catch (\Throwable $e) {
        }

        $success_msg = "User approved successfully! Email sent to {$user['email']} with ID: {$assigned_id}";
    }
}

// Handle disapproval (set to unapproved status)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disapprove_user'])) {
    $user_id = intval($_POST['user_id']);

    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if ($user) {
        db()->update(
            'users',
            [
                'approved' => 0,
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null
            ],
            'id = ?',
            [$user_id]
        );

        // Update role-specific tables
        if ($user['role'] === 'student') {
            db()->update('students', ['status' => 'pending'], 'user_id = ?', [$user_id]);
        } elseif ($user['role'] === 'teacher') {
            db()->update('teachers', ['status' => 'pending'], 'user_id = ?', [$user_id]);
        }

        log_activity($admin_id, 'disapprove_user', 'users', $user_id, "Disapproved user: {$user['email']}");
        try {
            AuditLogger::log('disapprove_user', 'users', "Disapproved user #{$user_id}: {$user['email']}", $_SESSION['user_id'] ?? null);
        } catch (\Throwable $e) {
        }

        $success_msg = "User disapproved and set to pending status.";
    }
}

// Handle rejection (delete user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user'])) {
    $user_id = intval($_POST['user_id']);

    $user = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);

    if ($user) {
        // Log before deletion
        log_activity($admin_id, 'reject_user', 'users', $user_id, "Rejected and deleted user: {$user['email']}");
        try {
            AuditLogger::log('reject_user', 'users', "Rejected and deleted user #{$user_id}: {$user['email']}", $_SESSION['user_id'] ?? null);
        } catch (\Throwable $e) {
        }

        // Delete user (cascade will handle related records)
        db()->delete('users', 'id = ?', [$user_id]);

        $success_msg = "User registration rejected and deleted.";
    }
}

// Get pending users (email verified but not approved)
$pending_users = db()->fetchAll("SELECT u.*,
    CASE
        WHEN u.role = 'student' THEN s.admission_number
        ELSE NULL
    END as generated_id
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    WHERE u.email_verified = 1 AND u.approved = 0
    ORDER BY u.created_at DESC");
if (!$pending_users) $pending_users = [];

// Get unverified users
$unverified_users = db()->fetchAll("SELECT * FROM users WHERE email_verified = 0 ORDER BY created_at DESC");
if (!$unverified_users) $unverified_users = [];

$page_title = 'Approve Users';
$page_icon = 'person_check'; // Material Symbols icon
$full_name = $_SESSION['full_name'];

// Start output buffering for master layout
ob_start();
?>

<!-- User Approval Interface -->

<body>



    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <a href="unapproved-users.php" class="cyber-btn secondary" style="margin-right: 10px;">
                        <i class="fas fa-user-times"></i> View Unapproved Users
                    </a>
                    <div class="stat-badge" style="background:rgba(255,165,0,0.1);border:1px solid orange;padding:8px 15px;border-radius:8px;">
                        <i class="fas fa-clock"></i> <?php echo count($pending_users); ?> Pending
                    </div>
                </div>
            </header>
            <div class="cyber-content slide-in">

                <?php if (isset($success_msg)): ?>
                    <div style="padding:15px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:12px;background:rgba(0,255,127,0.1);border:1px solid var(--neon-green);color:var(--neon-green);">
                        <i class="fas fa-check-circle"></i><span><?php echo $success_msg; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Pending Approvals -->
                <div class="holo-card" style="margin-bottom:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-user-clock"></i> <span>Pending Approvals (Email Verified)</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (count($pending_users) > 0): ?>
                            <form method="POST" id="bulkPendingForm" style="margin-bottom:15px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <input type="hidden" name="bulk_action" id="bulkActionField" value="">
                                <button type="button" class="cyber-btn primary sm" onclick="submitPendingBulk('approve_selected')"><i class="fas fa-check-double"></i> Approve Selected</button>
                                <button type="button" class="cyber-btn danger sm" onclick="submitPendingBulk('reject_selected')"><i class="fas fa-user-times"></i> Reject Selected</button>
                                <span style="color:var(--text-muted);font-size:0.85rem;">Select users and optionally edit ID values before bulk approval.</span>
                            </form>
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"><input type="checkbox" id="selectAllPending" onclick="togglePendingSelection()"></th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Generated ID</th>
                                        <th>Assign ID</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_users as $user):
                                        $suggested_id = $user['generated_id'] ?? '';
                                    ?>
                                        <tr>
                                            <td><input type="checkbox" class="pending-checkbox" form="bulkPendingForm" name="selected_user_ids[]" value="<?php echo (int)$user['id']; ?>"></td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="status-badge <?php echo $user['role'] === 'student' ? 'active' : ($user['role'] === 'teacher' ? 'warning' : 'info'); ?>"><?php echo strtoupper($user['role']); ?></span></td>
                                            <td><code style="color:var(--cyber-cyan);"><?php echo $suggested_id; ?></code></td>
                                            <td>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <input type="text" name="assigned_id" value="<?php echo $suggested_id; ?>"
                                                        style="width:150px;padding:8px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:6px;color:var(--cyber-cyan);font-family:monospace;" required>
                                                    <input type="hidden" form="bulkPendingForm" name="bulk_assigned_ids[<?php echo (int)$user['id']; ?>]" value="<?php echo htmlspecialchars($suggested_id); ?>" class="bulk-assigned-id-mirror" data-user-id="<?php echo (int)$user['id']; ?>">
                                            </td>
                                            <td style="font-size:0.85rem;"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <button type="submit" name="approve_user" class="cyber-btn primary sm" style="margin-right:5px;">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="reject_user" class="cyber-btn danger sm" onclick="return confirm('Are you sure you want to reject this user?');">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">No pending approvals at this time.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Unverified Email -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-envelope"></i> <span>Unverified Email Addresses (<?php echo count($unverified_users); ?>)</span></div>
                        <?php if (count($unverified_users) > 0): ?>
                            <div style="display: flex; gap: 10px;">
                                <button onclick="resendToSelected()" id="resendSelectedBtn" class="cyber-btn cyber-btn-outline" style="display: none; border-color: var(--cyber-cyan); color: var(--cyber-cyan);">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Resend to Selected (<span id="selectedResendCount">0</span>)</span>
                                </button>
                                <button onclick="resendToAll()" class="cyber-btn cyber-btn-outline" style="border-color: var(--golden-pulse); color: var(--golden-pulse);">
                                    <i class="fas fa-envelope-open-text"></i>
                                    <span>Resend to All</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (count($unverified_users) > 0): ?>
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllResend" onclick="toggleSelectAllResend()" style="width: 18px; height: 18px; cursor: pointer;">
                                        </th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registered</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unverified_users as $user): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="resend-checkbox" data-user-id="<?php echo $user['id']; ?>" data-user-email="<?php echo htmlspecialchars($user['email']); ?>" onclick="updateResendSelection()" style="width: 18px; height: 18px; cursor: pointer;">
                                            </td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="status-badge"><?php echo strtoupper($user['role']); ?></span></td>
                                            <td style="font-size:0.85rem;"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></td>
                                            <td><span class="status-badge" style="background:rgba(255,165,0,0.2);color:orange;">Awaiting Email Verification</span></td>
                                            <td style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                <button onclick="resendVerification(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')" class="cyber-btn cyber-btn-outline" style="padding: 6px 12px; font-size: 0.85rem; border-color: var(--cyber-cyan); color: var(--cyber-cyan);">
                                                    <i class="fas fa-paper-plane"></i> Resend
                                                </button>
                                                <button onclick="approveUnverified(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>')" class="cyber-btn" style="padding: 6px 12px; font-size: 0.85rem; background: var(--neon-green);">
                                                    <i class="fas fa-check"></i> Approve Anyway
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">All registered users have verified their emails.</p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function togglePendingSelection() {
            const check = document.getElementById('selectAllPending');
            document.querySelectorAll('.pending-checkbox').forEach(cb => {
                cb.checked = check.checked;
            });
        }

        function submitPendingBulk(action) {
            const selected = document.querySelectorAll('.pending-checkbox:checked');
            if (selected.length === 0) {
                alert('Select at least one pending user.');
                return;
            }

            if (action === 'reject_selected' && !confirm('Reject/delete selected users?')) {
                return;
            }

            document.querySelectorAll('.bulk-assigned-id-mirror').forEach(input => {
                const row = input.closest('tr');
                const localAssigned = row ? row.querySelector('input[name="assigned_id"]') : null;
                if (localAssigned) {
                    input.value = localAssigned.value;
                }
            });

            document.getElementById('bulkActionField').value = action;
            document.getElementById('bulkPendingForm').submit();
        }

        let selectedResendUsers = [];

        // Resend verification to a single user
        async function resendVerification(userId, email) {
            if (!confirm(`Resend verification email to ${email}?`)) {
                return;
            }

            try {
                const response = await fetch('../api/resend-verification.php?action=resend_single', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Verification email sent successfully to ' + result.email);
                } else {
                    alert('❌ Error: ' + result.error);
                }
            } catch (error) {
                alert('❌ Network error: ' + error.message);
            }
        }

        // Update resend selection
        function updateResendSelection() {
            const checkboxes = document.querySelectorAll('.resend-checkbox:checked');
            selectedResendUsers = Array.from(checkboxes).map(cb => ({
                id: parseInt(cb.dataset.userId),
                email: cb.dataset.userEmail
            }));

            document.getElementById('selectedResendCount').textContent = selectedResendUsers.length;
            document.getElementById('resendSelectedBtn').style.display =
                selectedResendUsers.length > 0 ? 'block' : 'none';
        }

        // Toggle select all for resend
        function toggleSelectAllResend() {
            const selectAllCheckbox = document.getElementById('selectAllResend');
            const checkboxes = document.querySelectorAll('.resend-checkbox');

            checkboxes.forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
            });

            updateResendSelection();
        }

        // Resend to selected users
        async function resendToSelected() {
            if (selectedResendUsers.length === 0) return;

            if (!confirm(`Resend verification emails to ${selectedResendUsers.length} users?`)) {
                return;
            }

            try {
                const response = await fetch('../api/resend-verification.php?action=resend_bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_ids: selectedResendUsers.map(u => u.id)
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Successfully sent ${result.sent} verification emails!`);
                    location.reload();
                } else {
                    alert('❌ Some emails failed:\n' + result.errors.join('\n'));
                    location.reload();
                }
            } catch (error) {
                alert('❌ Network error: ' + error.message);
            }
        }

        // Resend to all unverified users
        async function resendToAll() {
            const confirmation = prompt('⚠️ RESEND TO ALL UNVERIFIED USERS?\n\nThis will send verification emails to all users who haven\'t verified.\nType "RESEND_ALL" to confirm:');

            if (confirmation !== 'RESEND_ALL') {
                return;
            }

            try {
                const response = await fetch('../api/resend-verification.php?action=resend_all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        confirm: 'RESEND_ALL'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`✅ Successfully sent ${result.sent} verification emails!`);
                    location.reload();
                } else {
                    alert('❌ Error: ' + result.error);
                }
            } catch (error) {
                alert('❌ Network error: ' + error.message);
            }
        }

        // Approve unverified user anyway
        async function approveUnverified(userId, email, role) {
            if (!confirm(`⚠️ APPROVE WITHOUT EMAIL VERIFICATION?\n\nUser: ${email}\nRole: ${role.toUpperCase()}\n\nThis will approve the user even though they haven't verified their email.\n\nContinue?`)) {
                return;
            }

            // Generate a suggested ID based on role
            const year = new Date().getFullYear();
            const randomNum = Math.floor(Math.random() * 10000);
            let suggestedId = '';

            if (role === 'student') {
                suggestedId = `STU${year}${String(randomNum).padStart(4, '0')}`;
            } else if (role === 'teacher') {
                suggestedId = `TCH${year}${String(randomNum).padStart(4, '0')}`;
            }

            const assignedId = prompt(`Enter ID to assign to this user:\n\nSuggested: ${suggestedId}`, suggestedId);

            if (!assignedId || assignedId.trim() === '') {
                alert('❌ ID is required to approve user');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('approve_unverified', '1');
                formData.append('user_id', userId);
                formData.append('assigned_id', assignedId.trim());

                const response = await fetch('approve-users.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();

                // Check if response is JSON or HTML with success
                if (text.includes('success') || response.ok) {
                    alert('✅ User approved successfully!');
                    location.reload();
                } else {
                    alert('❌ Failed to approve user. Please try again.');
                }
            } catch (error) {
                alert('❌ Network error: ' + error.message);
            }
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
    </div><!-- End app-layout content -->
    <?php
    // Capture output and use master layout
    $page_content = ob_get_clean();
    require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
    ?>
