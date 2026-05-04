<?php

/**
 * System Reset and Database Management Tool
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_system'])) {
        try {
            db()->beginTransaction();

            // Reset all tables with proper foreign key handling
            db()->query('SET FOREIGN_KEY_CHECKS = 0');

            // Clear data but keep structure
            $tables = [
                'attendance_records',
                'class_enrollments',
                'students',
                'teachers',
                'classes',
                'activity_logs'
            ];

            foreach ($tables as $table) {
                db()->query("DELETE FROM $table");
                db()->query("ALTER TABLE $table AUTO_INCREMENT = 1");
            }

            // Reset users but keep admin
            db()->query("DELETE FROM users WHERE role != 'admin'");
            db()->query("ALTER TABLE users AUTO_INCREMENT = 2"); // Start from 2 since admin is 1

            db()->query('SET FOREIGN_KEY_CHECKS = 1');

            db()->commit();

            log_activity($_SESSION['user_id'], 'system_reset', 'system', 0);
            $message = 'System reset successfully! All data cleared and IDs reset to start from 1.';
            $message_type = 'success';
        } catch (Exception $e) {
            db()->rollback();
            $message = 'System reset failed: ' . $e->getMessage();
            $message_type = 'error';
        }
    } elseif (isset($_POST['backup_system'])) {
        try {
            $backup_file = '../backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_dir = dirname($backup_file);

            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }

            $command = "/opt/lampp/bin/mysqldump -u root attendance_system > $backup_file";
            exec($command, $output, $return_var);

            if ($return_var === 0) {
                $message = 'Database backup created successfully: ' . basename($backup_file);
                $message_type = 'success';
            } else {
                $message = 'Backup failed. Please check permissions.';
                $message_type = 'error';
            }
        } catch (Exception $e) {
            $message = 'Backup error: ' . $e->getMessage();
            $message_type = 'error';
        }
    } elseif (isset($_POST['reset_ids'])) {
        try {
            db()->beginTransaction();

            // Reset auto increment counters
            $tables = ['students', 'teachers', 'classes', 'users', 'attendance_records'];
            foreach ($tables as $table) {
                $max_id_result = db()->fetchOne("SELECT COALESCE(MAX(id), 0) as max_id FROM $table");
                $max_id = $max_id_result['max_id'] ?? 0;
                $next_id = $max_id + 1;
                db()->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
            }

            db()->commit();
            log_activity($_SESSION['user_id'], 'reset_ids', 'system', 0);
            $message = 'ID sequences reset successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            db()->rollback();
            $message = 'ID reset failed: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get system stats
$stats = [
    'total_users' => db()->count('users'),
    'total_students' => db()->count('students'),
    'total_teachers' => db()->count('teachers'),
    'total_classes' => db()->count('classes'),
    'total_attendance' => db()->count('attendance_records'),
    'backup_files' => []
];

// Get backup files
$backup_dir = '../backups/';
if (is_dir($backup_dir)) {
    $files = glob($backup_dir . '*.sql');
    foreach ($files as $file) {
        $stats['backup_files'][] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
}

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
    <title>System Management - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <style>
        .danger-zone {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
        }

        .danger-header {
            color: #dc2626;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .danger-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .danger-btn {
            background: #dc2626;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .danger-btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }

        .system-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .stat-orb {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .backup-list {
            max-height: 300px;
            overflow-y: auto;
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
        }

        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="app-layout">
        <div class="cyber-main">

    <div class="container">
            <div class="cyber-header" style="padding: 24px 32px; border-bottom: 1px solid var(--border-color);">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:44px;height:44px;background:var(--primary-50);color:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary);">System Management</h1>
                        <p style="color:var(--text-secondary);font-size:0.85rem;">Database and system tools</p>
                    </div>
                </div>
            </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- System Statistics -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-chart-bar"></i> System Statistics</h2>
            </div>
            <div class="system-stats">
                <div class="stat-orb">
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <div>Total Users</div>
                </div>
                <div class="stat-orb">
                    <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                    <div>Students</div>
                </div>
                <div class="stat-orb">
                    <div class="stat-number"><?php echo $stats['total_teachers']; ?></div>
                    <div>Teachers</div>
                </div>
                <div class="stat-orb">
                    <div class="stat-number"><?php echo $stats['total_classes']; ?></div>
                    <div>Classes</div>
                </div>
                <div class="stat-orb">
                    <div class="stat-number"><?php echo $stats['total_attendance']; ?></div>
                    <div>Attendance Records</div>
                </div>
            </div>
        </div>

        <!-- Backup Management -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-save"></i> Backup Management</h2>
            </div>

            <form method="POST" style="margin-bottom: 20px;">
                <button type="submit" name="backup_system" class="btn btn-primary">
                    <i class="fas fa-download"></i> Create Full Backup
                </button>
            </form>

            <div class="backup-list">
                <h4>Existing Backups (<?php echo count($stats['backup_files']); ?>)</h4>
                <?php if (empty($stats['backup_files'])): ?>
                    <p style="color: #64748b; text-align: center; padding: 20px;">No backups found</p>
                <?php else: ?>
                    <?php foreach ($stats['backup_files'] as $backup): ?>
                        <div class="backup-item">
                            <div>
                                <strong><?php echo $backup['name']; ?></strong><br>
                                <small><?php echo $backup['date']; ?> • <?php echo number_format($backup['size'] / 1024, 2); ?> KB</small>
                            </div>
                            <a href="../backups/<?php echo $backup['name']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="danger-zone">
            <div class="danger-header">
                <i class="fas fa-exclamation-triangle"></i>
                Danger Zone
            </div>
            <p style="color: #7f1d1d; margin-bottom: 20px;">
                These actions are irreversible. Please create a backup before proceeding.
            </p>

            <div class="danger-actions">
                <form method="POST" onsubmit="return confirm('⚠️ WARNING: This will reset ALL ID sequences. Continue?')">
                    <button type="submit" name="reset_ids" class="danger-btn">
                        <i class="fas fa-sort-numeric-up"></i> Reset ID Sequences
                    </button>
                </form>

                <form method="POST" onsubmit="return confirm('🚨 CRITICAL WARNING: This will DELETE ALL DATA except admin account! Type CONFIRM below to proceed.') && prompt('Type CONFIRM:') === 'CONFIRM'">
                    <button type="submit" name="reset_system" class="danger-btn">
                        <i class="fas fa-skull-crossbones"></i> Complete System Reset
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
        </div>
    </div>
</body>

</html>