<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/system-log.php';
require_admin('../login.php');

$page_title = 'Bulk Student Import';
$page_icon = 'file-import';
$results = [];
$errors = [];
$imported = 0;
$failed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['download']) && $_GET['download'] === 'errors') {
    $reportRows = $_SESSION['bulk_import_failed_rows'] ?? [];
    if (!is_array($reportRows) || empty($reportRows)) {
        header('Location: students-bulk-import.php');
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bulk-import-errors-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'message']);
    foreach ($reportRows as $r) {
        fputcsv($out, [$r['email'] ?? '', $r['message'] ?? '']);
    }
    fclose($out);
    exit;
}

function pick_class_id_for_grade($gradeLevel)
{
    if (!table_exists('classes') || !table_has_column('classes', 'grade_level')) {
        return null;
    }
    $row = db()->fetchOne('SELECT id FROM classes WHERE grade_level = ? ORDER BY id ASC LIMIT 1', [(int)$gradeLevel]);
    return $row ? (int)$row['id'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_students'])) {
    if (!validate_post_csrf_if_present()) {
        $errors[] = 'Security validation failed. Please refresh and try again.';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid CSV file.';
    } else {
        $sendInvite = isset($_POST['send_invite']);
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if ($handle === false) {
            $errors[] = 'Unable to read uploaded CSV.';
        } else {
            $header = fgetcsv($handle);
            if (!$header) {
                $errors[] = 'CSV file is empty.';
            } else {
                $header = array_map(static fn($h) => strtolower(trim((string)$h)), $header);
                while (($row = fgetcsv($handle)) !== false) {
                    $data = [];
                    foreach ($header as $idx => $key) {
                        $data[$key] = trim((string)($row[$idx] ?? ''));
                    }

                    $first = $data['first_name'] ?? '';
                    $last = $data['last_name'] ?? '';
                    $email = $data['email'] ?? '';
                    $phone = $data['phone'] ?? '';
                    $dob = $data['date_of_birth'] ?? '';
                    $gender = strtolower($data['gender'] ?? '');
                    $grade = (int)($data['grade_level'] ?? 0);

                    if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $failed++;
                        $results[] = ['email' => $email, 'status' => 'failed', 'message' => 'Missing required fields'];
                        continue;
                    }

                    if (db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email])) {
                        $failed++;
                        $results[] = ['email' => $email, 'status' => 'failed', 'message' => 'Email already exists'];
                        continue;
                    }

                    try {
                        db()->query('START TRANSACTION');
                        $student_count = db()->count('students');
                        $student_id = 'STD' . date('Y') . str_pad($student_count + 1, 4, '0', STR_PAD_LEFT);

                        // Generate OTP for account confirmation (no password given to admin)
                        $otp = sprintf('%06d', random_int(100000, 999999));
                        $otp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                        $otp_token = 'CONFIRM:' . $otp . ':' . strtotime($otp_expiry);

                        $userPayload = build_user_payload([
                            'email' => $email,
                            'password' => bin2hex(random_bytes(32)),
                            'role' => 'student',
                            'first_name' => $first,
                            'last_name' => $last,
                            'full_name' => $first . ' ' . $last,
                            'status' => 'active',
                            'approved' => 1,
                            'email_verified' => 0,
                            'assigned_id' => $student_id
                        ]);
                        // Override: account stays inactive until OTP confirmed
                        $userPayload['is_active'] = 0;
                        $userPayload['email_verified'] = 0;
                        $userPayload['verification_token'] = $otp_token;
                        if (table_has_column('users', 'token_expiry')) {
                            $userPayload['token_expiry'] = $otp_expiry;
                        }
                        $userPayload['phone'] = $phone !== '' ? $phone : null;

                        $userId = insert_flexible('users', $userPayload);
                        if (!$userId) {
                            throw new Exception('User insert failed');
                        }

                        attach_user_to_tenant((int)$userId, current_tenant_id());

                        $classId = $grade > 0 ? pick_class_id_for_grade($grade) : null;
                        $studentData = [
                            'user_id' => $userId,
                            'admission_number' => $student_id,
                            'assigned_student_id' => $student_id,
                            'class_id' => $classId,
                            'date_of_birth' => $dob !== '' ? $dob : null,
                            'gender' => in_array($gender, ['male', 'female'], true) ? $gender : null,
                            'is_active' => 1,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];

                        $studentRecordId = insert_flexible('students', $studentData);
                        if (!$studentRecordId) {
                            throw new Exception('Student profile insert failed');
                        }

                        // Enroll in class if class assigned
                        if ($classId) {
                            try {
                                insert_flexible('class_enrollments', [
                                    'class_id' => $classId,
                                    'student_id' => $studentRecordId,
                                    'status' => 'active',
                                    'enrolled_by' => $_SESSION['user_id'],
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                            } catch (Throwable $enrollErr) {
                                // Non-fatal, log and continue
                                error_log('Enrollment insert failed: ' . $enrollErr->getMessage());
                            }
                        }

                        db()->query('COMMIT');
                        $imported++;

                        if ($sendInvite) {
                            send_account_otp_email($email, $first . ' ' . $last, $otp, $student_id, 'student');
                        }

                        $results[] = ['email' => $email, 'status' => 'ok', 'message' => $sendInvite ? 'Imported + OTP invite sent' : 'Imported'];
                    } catch (Throwable $e) {
                        db()->query('ROLLBACK');
                        $failed++;
                        $results[] = ['email' => $email, 'status' => 'failed', 'message' => $e->getMessage()];
                    }
                }
            }
            fclose($handle);
        }
    }

    $_SESSION['bulk_import_failed_rows'] = array_values(array_filter($results, static function ($r) {
        return ($r['status'] ?? '') === 'failed';
    }));
    system_log('INFO', 'Bulk student import completed', [
        'admin_user_id' => $_SESSION['user_id'] ?? null,
        'imported' => $imported,
        'failed' => $failed
    ]);
}
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar-nav.php'; ?>
    <main class="main-content">
        <header class="top-header">
            <div class="page-title-area">
                <div class="page-icon"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                <div><h1><?php echo $page_title; ?></h1><p class="page-subtitle">Import many students from CSV in one go</p></div>
            </div>
            <div class="header-actions">
                <a href="students.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </header>

        <div class="cyber-content">
            <?php foreach ($errors as $err): ?>
                <div class="cyber-alert error"><i class="fas fa-triangle-exclamation"></i><span><?php echo htmlspecialchars($err); ?></span></div>
            <?php endforeach; ?>

            <section class="holo-card" style="margin-bottom:16px;">
                <div class="card-header"><div class="card-title"><i class="fas fa-file-csv"></i><span>Upload CSV</span></div></div>
                <div class="card-body">
                    <p>Required columns: <code>first_name,last_name,email</code>. Optional: <code>phone,date_of_birth,gender,grade_level</code></p>
                    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="file" name="csv_file" accept=".csv" required class="form-control" style="max-width:340px;">
                        <label style="display:inline-flex;align-items:center;gap:6px;"><input type="checkbox" name="send_invite" value="1"> Send onboarding email</label>
                        <button type="submit" name="import_students" class="btn btn-primary"><i class="fas fa-upload"></i> Import Students</button>
                    </form>
                </div>
            </section>

            <section class="orb-grid" style="margin-bottom:16px;">
                <div class="stat-orb"><div class="stat-icon green"><i class="fas fa-check"></i></div><div class="stat-content"><div class="stat-value"><?php echo (int)$imported; ?></div><div class="stat-label">Imported</div></div></div>
                <div class="stat-orb"><div class="stat-icon red"><i class="fas fa-xmark"></i></div><div class="stat-content"><div class="stat-value"><?php echo (int)$failed; ?></div><div class="stat-label">Failed</div></div></div>
            </section>

            <?php if (!empty($results)): ?>
                <section class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-list-check"></i><span>Import Results</span></div>
                        <?php if ($failed > 0): ?>
                            <a href="students-bulk-import.php?download=errors" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Download Error Report
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="holo-table-wrapper">
                            <table class="holo-table">
                                <thead><tr><th>Email</th><th>Status</th><th>Details</th></tr></thead>
                                <tbody>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['email']); ?></td>
                                        <td><span class="cyber-badge <?php echo $r['status']==='ok'?'success':'danger'; ?>"><?php echo $r['status']==='ok'?'OK':'FAILED'; ?></span></td>
                                        <td><?php echo htmlspecialchars($r['message']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
