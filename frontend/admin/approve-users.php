<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once PROJECT_ROOT . '/backend/modules/admin/AdminUserManager.php';
require_admin();

$full_name = $_SESSION['full_name'];
$adminUserManager = new AdminUserManager(current_tenant_id(), (int)($_SESSION['user_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = null;

    if (isset($_POST['bulk_action'])) {
        $selectedUserIds = array_values(array_filter(array_map('intval', $_POST['selected_user_ids'] ?? []), static fn($value) => $value > 0));
        if (!empty($selectedUserIds)) {
            if ($_POST['bulk_action'] === 'approve_selected') {
                $result = $adminUserManager->bulkApproveUsers($selectedUserIds, $_POST['bulk_assigned_ids'] ?? []);
            } elseif ($_POST['bulk_action'] === 'reject_selected') {
                $result = $adminUserManager->bulkRejectUsers($selectedUserIds);
            }
        } else {
            $result = ['success' => false, 'message' => 'Select at least one user first.'];
        }
    } elseif (isset($_POST['approve_unverified'])) {
        $result = $adminUserManager->approveUser((int)($_POST['user_id'] ?? 0), sanitize($_POST['assigned_id'] ?? ''), true);
    } elseif (isset($_POST['approve_user'])) {
        $result = $adminUserManager->approveUser((int)($_POST['user_id'] ?? 0), sanitize($_POST['assigned_id'] ?? ''));
    } elseif (isset($_POST['disapprove_user'])) {
        $result = $adminUserManager->disapproveUser((int)($_POST['user_id'] ?? 0));
    } elseif (isset($_POST['reject_user'])) {
        $result = $adminUserManager->rejectUser((int)($_POST['user_id'] ?? 0));
    }

    if (is_array($result)) {
        if (!empty($result['success'])) {
            $success_msg = (string)($result['message'] ?? 'Action completed successfully.');
        } else {
            $error_msg = (string)($result['message'] ?? $result['error'] ?? 'Unable to complete the action.');
        }
    }
}

$approvalData = $adminUserManager->getApprovalScreenData();
$pending_users = $approvalData['pending_users'] ?? [];
$unverified_users = $approvalData['unverified_users'] ?? [];

$page_title = 'Approve Users';
$page_icon = 'person_check'; // Material Symbols icon
$full_name = $_SESSION['full_name'];

// Start output buffering for master layout
ob_start();
?>

<!-- User Approval Interface -->
<section class="cyber-header">
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
</section>
<div class="cyber-content slide-in">

                <?php if (isset($success_msg)): ?>
                    <div style="padding:15px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:12px;background:rgba(0,255,127,0.1);border:1px solid var(--neon-green);color:var(--neon-green);">
                        <i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($success_msg); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_msg)): ?>
                    <div style="padding:15px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:12px;background:rgba(255,99,71,0.12);border:1px solid rgba(255,99,71,0.5);color:#ff7f7f;">
                        <i class="fas fa-exclamation-triangle"></i><span><?php echo htmlspecialchars($error_msg); ?></span>
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

    <script>
        const APPROVALS_API = '../../backend/api/admin/approvals.php';

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
                const response = await fetch(`${APPROVALS_API}?action=resend_single`, {
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
                const response = await fetch(`${APPROVALS_API}?action=resend_bulk`, {
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
                const response = await fetch(`${APPROVALS_API}?action=resend_all`, {
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

    <script>
        function approvalApiMessage(result, fallback = 'Request failed.') {
            return result?.message || result?.error || fallback;
        }

        async function resendVerification(userId, email) {
            if (!confirm(`Resend verification email to ${email}?`)) {
                return;
            }

            try {
                const response = await fetch(`${APPROVALS_API}?action=resend_single`, {
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
                    alert(`Verification email sent successfully to ${result.email || email}.`);
                } else {
                    alert(approvalApiMessage(result, 'Unable to resend verification email.'));
                }
            } catch (error) {
                alert(`Network error: ${error.message}`);
            }
        }

        async function resendToSelected() {
            if (selectedResendUsers.length === 0) {
                return;
            }

            if (!confirm(`Resend verification emails to ${selectedResendUsers.length} users?`)) {
                return;
            }

            try {
                const response = await fetch(`${APPROVALS_API}?action=resend_bulk`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_ids: selectedResendUsers.map(user => user.id)
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert(`Successfully sent ${result.sent} verification email(s).`);
                } else {
                    const details = Array.isArray(result.errors) && result.errors.length > 0
                        ? `\n\n${result.errors.join('\n')}`
                        : '';
                    alert(`${approvalApiMessage(result, 'Some verification emails failed.')}${details}`);
                }
                location.reload();
            } catch (error) {
                alert(`Network error: ${error.message}`);
            }
        }

        async function resendToAll() {
            const confirmation = prompt('RESEND TO ALL UNVERIFIED USERS?\n\nThis will send verification emails to all users who have not verified.\nType "RESEND_ALL" to confirm:');

            if (confirmation !== 'RESEND_ALL') {
                return;
            }

            try {
                const response = await fetch(`${APPROVALS_API}?action=resend_all`, {
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
                    alert(`Successfully sent ${result.sent} verification email(s).`);
                    location.reload();
                } else {
                    alert(approvalApiMessage(result, 'Unable to resend verification emails.'));
                }
            } catch (error) {
                alert(`Network error: ${error.message}`);
            }
        }

        async function approveUnverified(userId, email, role) {
            if (!confirm(`Approve without email verification?\n\nUser: ${email}\nRole: ${role.toUpperCase()}\n\nThis will approve the user even though the email is still unverified.\n\nContinue?`)) {
                return;
            }

            const year = new Date().getFullYear();
            const randomNum = Math.floor(Math.random() * 10000);
            const normalizedRole = String(role || '').toLowerCase();
            let suggestedId = '';

            if (normalizedRole === 'student') {
                suggestedId = `STU${year}${String(randomNum).padStart(4, '0')}`;
            } else if (normalizedRole === 'teacher') {
                suggestedId = `TCH${year}${String(randomNum).padStart(4, '0')}`;
            }

            const assignedId = prompt(`Enter ID to assign to this user:\n\nSuggested: ${suggestedId}`, suggestedId);
            if (!assignedId || assignedId.trim() === '') {
                alert('An assigned ID is required to approve this user.');
                return;
            }

            try {
                const response = await fetch(`${APPROVALS_API}?action=approve_unverified`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        assigned_id: assignedId.trim()
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(approvalApiMessage(result, 'User approved successfully.'));
                    location.reload();
                } else {
                    alert(approvalApiMessage(result, 'Failed to approve user.'));
                }
            } catch (error) {
                alert(`Network error: ${error.message}`);
            }
        }
    </script>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>
<?php
// Capture output and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
