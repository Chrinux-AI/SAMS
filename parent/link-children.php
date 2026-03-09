<?php

/**
 * Parent-Student Linking with Verification System
 * As specified in Attendance AI Complete Project Overview - Parent Linking Process
 *
 * Features:
 * - Secure linking via Student ID validation
 * - Verification code system (email to student/admin)
 * - Multi-child support with relationship types
 * - Email notifications to all parties
 * - Admin approval workflow (if enabled)
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$message = '';
$message_type = '';

// Handle Add Child Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_child') {
    $student_id_input = trim($_POST['student_id']);
    $relationship = $_POST['relationship'] ?? 'parent';
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;

    // Format Student ID (prepend STU if not present)
    if (!str_starts_with(strtoupper($student_id_input), 'STU')) {
        $student_id = 'STU' . $student_id_input;
    } else {
        $student_id = strtoupper($student_id_input);
    }

    // Remove STU prefix for DB query (stored as numeric)
    $student_id_numeric = str_replace('STU', '', $student_id);

    // Validate student exists and is active
    $student = db()->fetchOne(
        "SELECT s.id as student_db_id, s.user_id, s.admission_number AS student_id, u.first_name, u.last_name, u.email, u.status
         FROM students s
         JOIN users u ON s.user_id = u.id
         WHERE s.admission_number = ? OR s.assigned_student_id = ?",
        [$student_id_numeric, $student_id_numeric]
    );

    if (!$student) {
        $message = 'Invalid Student ID. Please check and try again.';
        $message_type = 'error';
    } elseif ($student['status'] !== 'active') {
        $message = 'Student account is not active. Contact administration.';
        $message_type = 'error';
    } else {
        // Check if already linked
        $existing_link = db()->fetchOne(
            "SELECT id FROM parent_student_links WHERE parent_id = ? AND student_id = ?",
            [$parent_id, $student['user_id']]
        );

        if ($existing_link) {
            $message = 'This child is already linked to your account.';
            $message_type = 'warning';
        } else {
            // Generate verification code
            $verification_code = strtoupper(bin2hex(random_bytes(3))); // 6-character code

            // Insert pending link
            db()->execute(
                "INSERT INTO parent_student_links
                 (parent_id, student_id, relationship, is_primary, verification_code, verified_at, created_at)
                 VALUES (?, ?, ?, ?, ?, NULL, NOW())",
                [$parent_id, $student['user_id'], $relationship, $is_primary, $verification_code]
            );

            // Send verification email to student
            $student_email_subject = "Parent Link Request - Verification Required";
            $student_email_message = "
                <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0;'>🔗 Parent Link Request</h1>
                    </div>
                    <div style='background: white; padding: 30px; border: 1px solid #e0e0e0;'>
                        <p>Hello <strong>{$student['first_name']} {$student['last_name']}</strong>,</p>
                        <p>A parent account has requested to link with your student profile:</p>

                        <div style='background: #f0f7ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0ea5e9;'>
                            <h3 style='margin: 0 0 10px 0; color: #0369a1;'>Link Request Details</h3>
                            <strong>Parent Name:</strong> $full_name<br>
                            <strong>Student ID:</strong> $student_id<br>
                            <strong>Relationship:</strong> " . ucfirst($relationship) . "<br>
                            <strong>Requested:</strong> " . date('F d, Y H:i') . "
                        </div>

                        <div style='background: #fef3c7; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b;'>
                            <h3 style='margin: 0 0 10px 0; color: #92400e;'>Verification Code</h3>
                            <div style='font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 4px;'>
                                $verification_code
                            </div>
                            <p style='margin: 10px 0 0 0; color: #92400e; font-size: 14px;'>
                                Please confirm this link request with school administration or provide this code to verify the connection.
                            </p>
                        </div>

                        <p><strong>⚠️ Security Notice:</strong> If you did not expect this request, please contact school administration immediately.</p>
                    </div>
                    <div style='background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px;'>
                        <p style='margin: 0;'>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                    </div>
                </div>
            ";

            send_email($student['email'], $student_email_subject, $student_email_message);

            // Send notification to parent
            $parent_email = db()->fetchOne("SELECT email FROM users WHERE id = ?", [$parent_id])['email'];
            $parent_email_subject = "Child Link Request Submitted";
            $parent_email_message = "
                <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center;'>
                        <h1 style='margin: 0;'>✅ Link Request Submitted</h1>
                    </div>
                    <div style='background: white; padding: 30px; border: 1px solid #e0e0e0;'>
                        <p>Hello <strong>$full_name</strong>,</p>
                        <p>Your request to link with student <strong>{$student['first_name']} {$student['last_name']}</strong> (ID: $student_id) has been submitted.</p>

                        <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981;'>
                            <h3 style='margin: 0 0 10px 0; color: #166534;'>Next Steps</h3>
                            <ol style='margin: 0; padding-left: 20px; color: #166534;'>
                                <li>Verification code has been sent to the student</li>
                                <li>School administration has been notified</li>
                                <li>Link will be activated after verification</li>
                            </ol>
                        </div>

                        <p>You will receive a confirmation email once the link is verified and approved.</p>
                    </div>
                </div>
            ";

            send_email($parent_email, $parent_email_subject, $parent_email_message);

            // Notify admin
            $admin_email = 'christolabiyi35@gmail.com'; // From config
            $admin_subject = "Parent-Student Link Request Pending";
            $admin_message = "
                <h2>🔗 New Parent-Student Link Request</h2>
                <div style='background: #f0f7ff; padding: 20px; border-radius: 8px; border-left: 4px solid #0ea5e9;'>
                    <strong>Parent:</strong> $full_name (ID: $parent_id)<br>
                    <strong>Student:</strong> {$student['first_name']} {$student['last_name']} (ID: $student_id)<br>
                    <strong>Relationship:</strong> " . ucfirst($relationship) . "<br>
                    <strong>Primary Contact:</strong> " . ($is_primary ? 'Yes' : 'No') . "<br>
                    <strong>Verification Code:</strong> <strong style='color:#667eea;'>$verification_code</strong><br>
                    <strong>Requested:</strong> " . date('F d, Y H:i:s') . "
                </div>
                <p><strong>Action Required:</strong> Review and approve/reject this link in the admin panel.</p>
            ";

            send_email($admin_email, $admin_subject, $admin_message);

            $message = "Link request submitted successfully! Verification email sent to student. Awaiting approval.";
            $message_type = 'success';

            // Log activity
            log_activity(
                $parent_id,
                'parent_child_link_request',
                'parent_student_links',
                $student['user_id'],
                "Requested link to student: {$student['first_name']} {$student['last_name']} ($student_id)"
            );
        }
    }
}

// Handle Remove Child Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_child') {
    $student_user_id = $_POST['student_user_id'] ?? 0;
    $removal_reason = $_POST['removal_reason'] ?? 'No reason provided';

    $student = db()->fetchOne(
        "SELECT u.first_name, u.last_name, u.email, s.admission_number AS student_id
         FROM users u
         JOIN students s ON u.id = s.user_id
         WHERE u.id = ?",
        [$student_user_id]
    );

    if ($student) {
        // Remove link
        db()->execute(
            "DELETE FROM parent_student_links WHERE parent_id = ? AND student_id = ?",
            [$parent_id, $student_user_id]
        );

        // Notify student
        $subject = "Parent Link Removed";
        $email_message = "
            <p>Hello <strong>{$student['first_name']} {$student['last_name']}</strong>,</p>
            <p>The parent link with <strong>$full_name</strong> has been removed.</p>
            <div style='background: #fef3c7; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <strong>Reason:</strong> $removal_reason
            </div>
            <p>If this was done in error, please contact administration.</p>
        ";
        send_email($student['email'], $subject, $email_message);

        // Notify admin
        $admin_email = 'christolabiyi35@gmail.com';
        $admin_subject = "Parent-Student Link Removed";
        $admin_message = "
            <h3>Parent removed child link</h3>
            <p><strong>Parent:</strong> $full_name<br>
            <strong>Student:</strong> {$student['first_name']} {$student['last_name']}<br>
            <strong>Reason:</strong> $removal_reason<br>
            <strong>Removed:</strong> " . date('Y-m-d H:i:s') . "</p>
        ";
        send_email($admin_email, $admin_subject, $admin_message);

        $message = 'Child removed from your account successfully.';
        $message_type = 'success';

        log_activity(
            $parent_id,
            'parent_child_unlink',
            'parent_student_links',
            $student_user_id,
            "Removed link to student: {$student['first_name']} {$student['last_name']}. Reason: $removal_reason"
        );
    }
}

// Get linked children
$linked_children = db()->fetchAll("
    SELECT u.id as user_id, u.first_name, u.last_name, u.email,
           s.admission_number as student_id, c.grade_level, s.date_of_birth,
           psl.relationship, psl.is_primary, psl.verified_at,
           psl.verification_code,
           COUNT(DISTINCT ce.class_id) as class_count
    FROM parent_student_links psl
    JOIN users u ON psl.student_id = u.id
    JOIN students s ON u.id = s.user_id
    LEFT JOIN class_enrollments ce ON s.user_id = ce.student_id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE psl.parent_id = ? AND u.status = 'active'
    GROUP BY u.id
    ORDER BY psl.is_primary DESC, u.first_name
", [$parent_id]);

$unread_messages = db()->fetchOne(
    "SELECT COUNT(*) as count FROM message_recipients WHERE recipient_id = ? AND is_read = 0",
    [$parent_id]
)['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
        <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Children - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">

</head>
<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-link"></i></div>
                    <h1 class="page-title">Link My Children</h1>
                </div>
                <div class="header-actions">
                    <button onclick="showAddChildModal()" class="cyber-btn">
                        <i class="fas fa-plus"></i> Add Child
                    </button>
                </div>
            </header>

            <div class="app-layout">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" style="background:rgba(<?php echo $message_type === 'success' ? '0,255,127' : ($message_type === 'warning' ? '255,255,0' : '255,69,0'); ?>,0.1);border:1px solid var(--cyber-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>);color:var(--cyber-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>);padding:15px;border-radius:8px;margin-bottom:20px;">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Linked Children -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-users"></i> My Linked Children (<?php echo count($linked_children); ?>)</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($linked_children)): ?>
                            <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                                <i class="fas fa-user-plus" style="font-size:4rem;margin-bottom:20px;opacity:0.3;"></i>
                                <p style="font-size:1.2rem;margin-bottom:10px;">No Children Linked</p>
                                <p>Click "Add Child" to link your children's student accounts.</p>
                            </div>
                        <?php else: ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;">
                                <?php foreach ($linked_children as $child): ?>
                                    <div class="holo-card">
                                        <div style="padding:20px;">
                                            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;">
                                                <div>
                                                    <h3 style="margin:0 0 5px 0;color:var(--cyber-cyan);">
                                                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                                        <?php if ($child['is_primary']): ?>
                                                            <span class="cyber-badge" style="background:var(--cyber-purple);font-size:0.7rem;">PRIMARY</span>
                                                        <?php endif; ?>
                                                    </h3>
                                                    <p style="margin:0;color:var(--text-muted);font-size:0.9rem;">
                                                        <i class="fas fa-id-card"></i> STU<?php echo htmlspecialchars($child['student_id']); ?>
                                                    </p>
                                                </div>
                                                <button onclick="showRemoveModal(<?php echo $child['user_id']; ?>, '<?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>')"
                                                    class="cyber-btn cyber-btn-sm" style="background:var(--cyber-red);">
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            </div>

                                            <div style="background:rgba(0,243,255,0.05);padding:12px;border-radius:6px;margin-bottom:10px;">
                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:0.9rem;">
                                                    <div>
                                                        <span style="color:var(--text-muted);">Grade:</span>
                                                        <strong><?php echo $child['grade_level']; ?></strong>
                                                    </div>
                                                    <div>
                                                        <span style="color:var(--text-muted);">Classes:</span>
                                                        <strong><?php echo $child['class_count']; ?></strong>
                                                    </div>
                                                    <div style="grid-column:1/-1;">
                                                        <span style="color:var(--text-muted);">Relationship:</span>
                                                        <strong><?php echo ucfirst($child['relationship']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if ($child['verified_at']): ?>
                                                <div style="color:var(--cyber-green);font-size:0.85rem;">
                                                    <i class="fas fa-check-circle"></i> Verified <?php echo date('M d, Y', strtotime($child['verified_at'])); ?>
                                                </div>
                                            <?php else: ?>
                                                <div style="background:rgba(255,255,0,0.1);padding:10px;border-radius:5px;font-size:0.85rem;color:var(--cyber-yellow);">
                                                    <i class="fas fa-clock"></i> Pending Verification
                                                    <?php if ($child['verification_code']): ?>
                                                        <div style="margin-top:5px;">Code: <strong><?php echo $child['verification_code']; ?></strong></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <div style="display:flex;gap:8px;margin-top:15px;">
                                                <a href="attendance.php?child=<?php echo $child['user_id']; ?>" class="cyber-btn cyber-btn-sm cyber-btn-outline" style="flex:1;">
                                                    <i class="fas fa-calendar-check"></i> Attendance
                                                </a>
                                                <a href="grades.php?child=<?php echo $child['user_id']; ?>" class="cyber-btn cyber-btn-sm cyber-btn-outline" style="flex:1;">
                                                    <i class="fas fa-star"></i> Grades
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="holo-card" style="margin-top:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-question-circle"></i> How to Link Children</div>
                    </div>
                    <div class="card-body">
                        <ol style="line-height:2;color:var(--text-muted);">
                            <li>Click "Add Child" and enter the <strong>Student ID</strong> (e.g., STU20250001)</li>
                            <li>Select your relationship and whether you're the primary contact</li>
                            <li>Submit the request - a <strong>verification code</strong> will be sent to the student</li>
                            <li>School administration will <strong>review and approve</strong> the link</li>
                            <li>Once approved, you'll receive confirmation and can access the child's data</li>
                        </ol>
                        <div style="background:rgba(255,255,0,0.1);padding:15px;border-radius:8px;border-left:4px solid var(--cyber-yellow);margin-top:15px;">
                            <p style="margin:0;color:var(--cyber-yellow);"><strong>⚠️ Security Note:</strong> All link requests are verified for security. Contact administration if you encounter issues.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Child Modal -->
    <div id="addChildModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:9999;padding:20px;overflow:auto;">
        <div class="holo-card" style="max-width:600px;margin:50px auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-plus"></i> Add Child to Account</div>
                <button onclick="hideAddChildModal()" style="background:none;border:none;color:var(--cyber-red);font-size:1.5rem;cursor:pointer;">×</button>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_child">

                    <div class="form-group">
                        <label class="form-label">Student ID *</label>
                        <input type="text" name="student_id" class="cyber-input" required
                            placeholder="STU20250001 or 20250001"
                            pattern="(STU)?[0-9]{8}"
                            title="Enter Student ID (8 digits, STU prefix optional)">
                        <small style="color:var(--text-muted);display:block;margin-top:5px;">
                            Enter the student's ID. You can include or omit the "STU" prefix.
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Relationship *</label>
                        <select name="relationship" class="cyber-input" required>
                            <option value="mother">Mother</option>
                            <option value="father">Father</option>
                            <option value="guardian">Guardian</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" name="is_primary" value="1">
                            <span>Set as primary contact for this child</span>
                        </label>
                        <small style="color:var(--text-muted);display:block;margin-top:5px;">
                            Primary contacts receive all official communications
                        </small>
                    </div>

                    <div style="background:rgba(0,243,255,0.1);padding:15px;border-radius:8px;margin:20px 0;">
                        <p style="margin:0;font-size:0.9rem;color:var(--cyber-cyan);">
                            <i class="fas fa-info-circle"></i> <strong>What happens next:</strong><br>
                            1. Verification email sent to student<br>
                            2. Admin notification for approval<br>
                            3. You'll be notified when approved
                        </p>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="cyber-btn"><i class="fas fa-link"></i> Submit Link Request</button>
                        <button type="button" onclick="hideAddChildModal()" class="cyber-btn cyber-btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Child Modal -->
    <div id="removeChildModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:9999;padding:20px;overflow:auto;">
        <div class="holo-card" style="max-width:500px;margin:50px auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-unlink"></i> Remove Child Link</div>
                <button onclick="hideRemoveModal()" style="background:none;border:none;color:var(--cyber-red);font-size:1.5rem;cursor:pointer;">×</button>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="remove_child">
                    <input type="hidden" name="student_user_id" id="remove_student_id">

                    <p>Are you sure you want to remove <strong id="remove_student_name"></strong> from your linked children?</p>

                    <div class="form-group">
                        <label class="form-label">Reason for Removal *</label>
                        <textarea name="removal_reason" class="cyber-input" rows="3" required placeholder="Please provide a reason..."></textarea>
                    </div>

                    <div style="background:rgba(255,69,0,0.1);padding:15px;border-radius:8px;margin:20px 0;border-left:4px solid var(--cyber-red);">
                        <p style="margin:0;color:var(--cyber-red);font-size:0.9rem;">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> This will remove your access to this child's attendance and academic data. Both the student and administration will be notified.
                        </p>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="cyber-btn" style="background:var(--cyber-red);"><i class="fas fa-unlink"></i> Remove Link</button>
                        <button type="button" onclick="hideRemoveModal()" class="cyber-btn cyber-btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAddChildModal() {
            document.getElementById('addChildModal').style.display = 'block';
        }

        function hideAddChildModal() {
            document.getElementById('addChildModal').style.display = 'none';
        }

        function showRemoveModal(userId, name) {
            document.getElementById('remove_student_id').value = userId;
            document.getElementById('remove_student_name').textContent = name;
            document.getElementById('removeChildModal').style.display = 'block';
        }

        function hideRemoveModal() {
            document.getElementById('removeChildModal').style.display = 'none';
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>