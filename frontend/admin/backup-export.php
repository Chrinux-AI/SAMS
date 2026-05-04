<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Backup & Export';
$page_icon = 'database';
$full_name = $_SESSION['full_name'];

$message = '';
$message_type = '';

// Ensure lightweight schedule settings exist in settings table
if (table_exists('settings')) {
    foreach (['backup_schedule_enabled' => '0', 'backup_schedule_frequency' => 'weekly', 'backup_schedule_time' => '02:00'] as $k => $v) {
        $exists = db()->fetchOne("SELECT id FROM settings WHERE `key` = ?", [$k]);
        if (!$exists) {
            db()->insert('settings', [
                'key' => $k,
                'value' => $v,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}

// Handle database backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_database'])) {
    try {
        $backup_dir = BASE_PATH . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;

        // Get all tables
        $tables = [];
        $result = db()->fetchAll("SHOW TABLES");
        foreach ($result as $row) {
            $tables[] = array_values($row)[0];
        }

        $backup_content = "-- Database Backup\n";
        $backup_content .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Database: attendance\n\n";

        foreach ($tables as $table) {
            // Get table structure
            $create_table = db()->fetchOne("SHOW CREATE TABLE `$table`");
            $backup_content .= "\n\n-- Table: $table\n";
            $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
            $backup_content .= $create_table['Create Table'] . ";\n\n";

            // Get table data
            $rows = db()->fetchAll("SELECT * FROM `$table`");
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $values = array_map(function ($v) {
                        return "'" . addslashes($v) . "'";
                    }, array_values($row));
                    $backup_content .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                }
            }
        }

        file_put_contents($filepath, $backup_content);

        log_activity($_SESSION['user_id'], 'backup_database', 'system', 0, "Created database backup: $filename");
        $message = "Database backup created successfully: $filename";
        $message_type = 'success';
    } catch (Exception $e) {
        $message = 'Backup failed: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle restore backup (best-effort SQL import)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    $selected_backup = basename((string)($_POST['selected_backup'] ?? ''));
    $confirm = (string)($_POST['restore_confirm'] ?? '');

    try {
        if ($confirm !== 'RESTORE') {
            throw new Exception('Restore cancelled. Type RESTORE to confirm.');
        }

        $backup_path = BASE_PATH . '/backups/' . $selected_backup;
        if (!$selected_backup || !is_file($backup_path) || pathinfo($backup_path, PATHINFO_EXTENSION) !== 'sql') {
            throw new Exception('Invalid backup file selected.');
        }

        $sql = file_get_contents($backup_path);
        if ($sql === false || trim($sql) === '') {
            throw new Exception('Backup file is empty or unreadable.');
        }

        // Execute statements one-by-one for compatibility
        $connection = db()->getConnection();
        $connection->beginTransaction();
        try {
            $statements = preg_split('/;\s*\n/', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement === '' || str_starts_with($statement, '--')) {
                    continue;
                }
                $connection->exec($statement);
            }
            $connection->commit();
        } catch (Throwable $restoreErr) {
            $connection->rollBack();
            throw $restoreErr;
        }

        log_activity($_SESSION['user_id'], 'restore_database_backup', 'system', 0, "Restored database backup: $selected_backup");
        $message = "Database restored successfully from $selected_backup";
        $message_type = 'success';
    } catch (Throwable $e) {
        $message = 'Restore failed: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle schedule save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_backup_schedule'])) {
    try {
        $enabled = isset($_POST['schedule_enabled']) ? '1' : '0';
        $frequency = $_POST['schedule_frequency'] ?? 'weekly';
        $time = $_POST['schedule_time'] ?? '02:00';

        if (!in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            $frequency = 'weekly';
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '02:00';
        }

        foreach (
            [
                'backup_schedule_enabled' => $enabled,
                'backup_schedule_frequency' => $frequency,
                'backup_schedule_time' => $time,
            ] as $k => $v
        ) {
            $exists = db()->fetchOne("SELECT id FROM settings WHERE `key` = ?", [$k]);
            if ($exists) {
                db()->update('settings', ['value' => $v, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$exists['id']]);
            } else {
                db()->insert('settings', ['key' => $k, 'value' => $v, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            }
        }

        log_activity($_SESSION['user_id'], 'save_backup_schedule', 'settings', 0, "Backup schedule updated: $frequency at $time");
        $message = 'Backup schedule saved successfully.';
        $message_type = 'success';
    } catch (Throwable $e) {
        $message = 'Failed to save backup schedule: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle export users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_users'])) {
    $role_filter = $_POST['role_filter'] ?? 'all';

    $where = '1=1';
    $params = [];
    if ($role_filter !== 'all') {
        $where = 'role = ?';
        $params[] = $role_filter;
    }

    $users = db()->fetchAll("SELECT * FROM users WHERE $where ORDER BY created_at DESC", $params);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Username', 'Email', 'Full Name', 'Role', 'Status', 'Email Verified', 'Approved', 'Created At']);

    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['username'],
            $user['email'],
            $user['full_name'] ?? ($user['first_name'] . ' ' . $user['last_name']),
            $user['role'],
            $user['status'],
            $user['email_verified'] ? 'Yes' : 'No',
            $user['approved'] ? 'Yes' : 'No',
            $user['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// Handle tenant-specific export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_tenant_users'])) {
    $tenant_id = (int)($_POST['tenant_id'] ?? 0);
    if ($tenant_id <= 0) {
        $message = 'Please select a school to export.';
        $message_type = 'error';
    } else {
        $tenant = db()->fetchOne("SELECT id, institution_name FROM tenants WHERE id = ?", [$tenant_id]);
        if (!$tenant) {
            $message = 'Selected school was not found.';
            $message_type = 'error';
        } else {
            $users = db()->fetchAll("SELECT id, username, email, role, status, created_at FROM users WHERE tenant_id = ? ORDER BY created_at DESC", [$tenant_id]);
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="tenant_users_' . $tenant_id . '_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Tenant ID', 'Tenant Name', 'User ID', 'Username', 'Email', 'Role', 'Status', 'Created At']);
            foreach ($users as $user) {
                fputcsv($output, [
                    $tenant['id'],
                    $tenant['institution_name'],
                    $user['id'],
                    $user['username'],
                    $user['email'],
                    $user['role'],
                    $user['status'],
                    $user['created_at'],
                ]);
            }
            fclose($output);
            exit;
        }
    }
}

// Get existing backups
$backup_dir = BASE_PATH . '/backups';
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'filename' => $file,
                'size' => filesize($backup_dir . '/' . $file),
                'created' => filemtime($backup_dir . '/' . $file)
            ];
        }
    }
    usort($backups, function ($a, $b) {
        return $b['created'] - $a['created'];
    });
}

// Get database stats
$db_stats = [
    'total_users' => db()->count('users'),
    'total_students' => db()->count('students'),
    'total_teachers' => db()->count('teachers'),
    'total_classes' => db()->count('classes'),
    'total_attendance' => db()->count('attendance_records'),
    'total_logs' => db()->count('activity_logs')
];

$schedule = [
    'enabled' => '0',
    'frequency' => 'weekly',
    'time' => '02:00'
];
if (table_exists('settings')) {
    $settingsRows = db()->fetchAll("SELECT `key`, `value` FROM settings WHERE `key` IN ('backup_schedule_enabled','backup_schedule_frequency','backup_schedule_time')") ?: [];
    foreach ($settingsRows as $row) {
        if ($row['key'] === 'backup_schedule_enabled') $schedule['enabled'] = (string)$row['value'];
        if ($row['key'] === 'backup_schedule_frequency') $schedule['frequency'] = (string)$row['value'];
        if ($row['key'] === 'backup_schedule_time') $schedule['time'] = (string)$row['value'];
    }
}

$tenants = table_exists('tenants') ? (db()->fetchAll("SELECT id, institution_name FROM tenants ORDER BY institution_name") ?: []) : [];
?>
<!DOCTYPE html>
<html>

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

</head>

<body>



    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
            </header>
            <div class="cyber-content fade-in">

                <?php if ($message): ?>
                    <div class="cyber-alert <?php echo $message_type; ?>" style="margin-bottom:20px;">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Database Stats -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;">
                    <?php foreach ($db_stats as $label => $value): ?>
                        <div class="stat-card">
                            <div class="stat-icon" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                                <i class="fas fa-<?php echo match ($label) {
                                                        'total_users' => 'users',
                                                        'total_students' => 'user-graduate',
                                                        'total_teachers' => 'chalkboard-teacher',
                                                        'total_classes' => 'door-open',
                                                        'total_attendance' => 'clipboard-check',
                                                        'total_logs' => 'list',
                                                        default => 'database'
                                                    }; ?>"></i>
                            </div>
                            <div class="stat-details">
                                <h3><?php echo number_format($value); ?></h3>
                                <p><?php echo ucwords(str_replace('_', ' ', $label)); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Backup Actions -->
                <div class="cyber-card" style="margin-bottom:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-download"></i> <span>Create Backups</span></div>
                    </div>
                    <div class="card-body">
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">

                            <!-- Full Database Backup -->
                            <div style="padding:20px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:10px;">
                                <h3 style="color:var(--cyber-cyan);margin-bottom:15px;">
                                    <i class="fas fa-database"></i> Full Database Backup
                                </h3>
                                <p style="margin-bottom:15px;font-size:0.9rem;color:var(--text-muted);">
                                    Creates a complete SQL dump of all database tables and data.
                                </p>
                                <form method="POST">
                                    <button type="submit" name="backup_database" class="cyber-btn cyber-btn-primary" style="width:100%;">
                                        <i class="fas fa-download"></i> Create Backup
                                    </button>
                                </form>
                            </div>

                            <!-- Export Users -->
                            <div style="padding:20px;background:rgba(16,185,129,0.05);border:1px solid var(--neon-green);border-radius:10px;">
                                <h3 style="color:var(--neon-green);margin-bottom:15px;">
                                    <i class="fas fa-users"></i> Export Users
                                </h3>
                                <p style="margin-bottom:15px;font-size:0.9rem;color:var(--text-muted);">
                                    Export user data to CSV format.
                                </p>
                                <form method="POST">
                                    <select name="role_filter" style="width:100%;padding:10px;background:rgba(16,185,129,0.05);border:1px solid var(--neon-green);border-radius:8px;color:var(--neon-green);margin-bottom:10px;">
                                        <option value="all">All Roles</option>
                                        <option value="student">Students Only</option>
                                        <option value="teacher">Teachers Only</option>
                                        <option value="parent">Parents Only</option>
                                        <option value="admin">Admins Only</option>
                                    </select>
                                    <button type="submit" name="export_users" class="cyber-btn cyber-btn-primary" style="width:100%;background:var(--neon-green);border-color:var(--neon-green);">
                                        <i class="fas fa-file-csv"></i> Export CSV
                                    </button>
                                </form>
                            </div>

                            <!-- Export Single School -->
                            <div style="padding:20px;background:rgba(245,158,11,0.05);border:1px solid #f59e0b;border-radius:10px;">
                                <h3 style="color:#f59e0b;margin-bottom:15px;">
                                    <i class="fas fa-school"></i> Export Per School
                                </h3>
                                <p style="margin-bottom:15px;font-size:0.9rem;color:var(--text-muted);">
                                    Export users for a specific tenant/school.
                                </p>
                                <form method="POST">
                                    <select name="tenant_id" style="width:100%;padding:10px;background:rgba(245,158,11,0.05);border:1px solid #f59e0b;border-radius:8px;color:#f59e0b;margin-bottom:10px;" required>
                                        <option value="">Select School</option>
                                        <?php foreach ($tenants as $tenant): ?>
                                            <option value="<?php echo (int)$tenant['id']; ?>"><?php echo htmlspecialchars($tenant['institution_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="export_tenant_users" class="cyber-btn cyber-btn-primary" style="width:100%;background:#f59e0b;border-color:#f59e0b;">
                                        <i class="fas fa-file-export"></i> Export School CSV
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Backup Schedule + Restore -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="cyber-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-clock"></i> <span>Backup Schedule</span></div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                                    <input type="checkbox" name="schedule_enabled" <?php echo $schedule['enabled'] === '1' ? 'checked' : ''; ?>>
                                    <span>Enable scheduled backups</span>
                                </label>

                                <label style="display:block;color:var(--cyber-cyan);font-size:0.85rem;margin-bottom:8px;">Frequency</label>
                                <select name="schedule_frequency" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);margin-bottom:12px;">
                                    <option value="daily" <?php echo $schedule['frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="weekly" <?php echo $schedule['frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                    <option value="monthly" <?php echo $schedule['frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>

                                <label style="display:block;color:var(--cyber-cyan);font-size:0.85rem;margin-bottom:8px;">Run Time</label>
                                <input type="time" name="schedule_time" value="<?php echo htmlspecialchars($schedule['time']); ?>" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);margin-bottom:12px;">

                                <button type="submit" name="save_backup_schedule" class="cyber-btn cyber-btn-primary" style="width:100%;">
                                    <i class="fas fa-save"></i> Save Schedule
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="cyber-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-undo"></i> <span>Restore Backup</span></div>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return confirm('This will overwrite current database state. Continue?');">
                                <label style="display:block;color:var(--cyber-cyan);font-size:0.85rem;margin-bottom:8px;">Backup File</label>
                                <select name="selected_backup" required style="width:100%;padding:10px;background:rgba(239,68,68,0.05);border:1px solid #ef4444;border-radius:8px;color:#ef4444;margin-bottom:12px;">
                                    <option value="">Select backup</option>
                                    <?php foreach ($backups as $backup): ?>
                                        <option value="<?php echo htmlspecialchars($backup['filename']); ?>"><?php echo htmlspecialchars($backup['filename']); ?></option>
                                    <?php endforeach; ?>
                                </select>

                                <label style="display:block;color:#ef4444;font-size:0.85rem;margin-bottom:8px;">Type RESTORE to confirm</label>
                                <input type="text" name="restore_confirm" placeholder="RESTORE" required style="width:100%;padding:10px;background:rgba(239,68,68,0.05);border:1px solid #ef4444;border-radius:8px;color:#ef4444;margin-bottom:12px;">

                                <button type="submit" name="restore_backup" class="cyber-btn" style="width:100%;background:#ef4444;border-color:#ef4444;">
                                    <i class="fas fa-exclamation-triangle"></i> Restore Now
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Existing Backups -->
                <div class="cyber-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-history"></i> <span>Backup History (<?php echo count($backups); ?>)</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">No backups found. Create your first backup above.</p>
                        <?php else: ?>
                            <div style="overflow-x:auto;">
                                <table class="holo-table">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td><code style="color:var(--cyber-cyan);"><?php echo $backup['filename']; ?></code></td>
                                                <td><?php echo round($backup['size'] / 1024, 2); ?> KB</td>
                                                <td><?php echo date('M d, Y H:i:s', $backup['created']); ?></td>
                                                <td>
                                                    <a href="../backups/<?php echo $backup['filename']; ?>" download class="cyber-btn cyber-btn-outline" style="padding:6px 12px;font-size:0.85rem;">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
