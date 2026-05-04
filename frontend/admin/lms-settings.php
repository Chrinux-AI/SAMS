<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

require_admin();

$page_title = 'LMS Integration Settings';
$page_icon = 'graduation-cap';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_config') {
        $config_id = $_POST['config_id'] ?? 0;
        $lms_platform = $_POST['lms_platform'] ?? '';
        $lms_name = $_POST['lms_name'] ?? '';
        $client_id = $_POST['client_id'] ?? '';
        $issuer = $_POST['issuer'] ?? '';
        $deployment_id = $_POST['deployment_id'] ?? '';
        $public_key = $_POST['public_key'] ?? '';
        $private_key = $_POST['private_key'] ?? '';
        $auth_login_url = $_POST['auth_login_url'] ?? '';
        $auth_token_url = $_POST['auth_token_url'] ?? '';
        $keyset_url = $_POST['keyset_url'] ?? '';
        $is_active = $_POST['is_active'] ?? 0;
        $auto_sync_enabled = $_POST['auto_sync_enabled'] ?? 0;
        $sync_frequency = $_POST['sync_frequency'] ?? 3600;

        if ($config_id > 0) {
            // Update existing
            db()->query(
                "UPDATE lti_configurations SET
                 lms_platform=?, lms_name=?, client_id=?, issuer=?, deployment_id=?,
                 public_key=?, private_key=?, auth_login_url=?, auth_token_url=?, keyset_url=?,
                 is_active=?, auto_sync_enabled=?, sync_frequency=?, updated_at=NOW()
                 WHERE id=?",
                [
                    $lms_platform,
                    $lms_name,
                    $client_id,
                    $issuer,
                    $deployment_id,
                    $public_key,
                    $private_key,
                    $auth_login_url,
                    $auth_token_url,
                    $keyset_url,
                    $is_active,
                    $auto_sync_enabled,
                    $sync_frequency,
                    $config_id
                ]
            );
            $_SESSION['success_message'] = 'LMS configuration updated successfully!';
        } else {
            // Insert new
            db()->query(
                "INSERT INTO lti_configurations
                 (lms_platform, lms_name, client_id, issuer, deployment_id, public_key, private_key,
                  auth_login_url, auth_token_url, keyset_url, is_active, auto_sync_enabled, sync_frequency)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $lms_platform,
                    $lms_name,
                    $client_id,
                    $issuer,
                    $deployment_id,
                    $public_key,
                    $private_key,
                    $auth_login_url,
                    $auth_token_url,
                    $keyset_url,
                    $is_active,
                    $auto_sync_enabled,
                    $sync_frequency
                ]
            );
            $_SESSION['success_message'] = 'LMS configuration created successfully!';
        }

        header('Location: lms-settings.php');
        exit;
    }

    if ($action === 'delete_config') {
        $config_id = $_POST['config_id'] ?? 0;
        db()->query("DELETE FROM lti_configurations WHERE id=?", [$config_id]);
        $_SESSION['success_message'] = 'LMS configuration deleted!';
        header('Location: lms-settings.php');
        exit;
    }
}

// Get all LTI configurations
$configs = db()->fetchAll(
    "SELECT * FROM lti_configurations ORDER BY is_active DESC, lms_name"
);

// Get sync statistics
$sync_stats = db()->fetchOne(
    "SELECT
        COUNT(*) as total_syncs,
        SUM(CASE WHEN status='success' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed,
        MAX(synced_at) as last_sync
     FROM lti_grade_sync_log"
);

// Get recent sync history
$recent_syncs = db()->fetchAll(
    "SELECT gsl.*, u.first_name, u.last_name, lc.lms_name, lc.lms_platform
     FROM lti_grade_sync_log gsl
     JOIN users u ON gsl.user_id = u.id
     JOIN lti_configurations lc ON gsl.lti_config_id = lc.id
     ORDER BY gsl.synced_at DESC
     LIMIT 20"
);

$unread_messages = db()->fetchOne(
    "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0",
    [$_SESSION['user_id']]
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
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
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
                <div class="header-actions">
                    <button onclick="showAddConfig()" class="cyber-btn">
                        <i class="fas fa-plus"></i> Add LMS Connection
                    </button>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success" style="background:rgba(0,255,127,0.1);border:1px solid var(--cyber-green);color:var(--cyber-green);padding:15px;border-radius:8px;margin-bottom:20px;">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                            unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-orb"><i class="fas fa-link"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Active Connections</div>
                            <div class="stat-value"><?php echo count(array_filter($configs, fn($c) => $c['is_active'])); ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-green);"><i class="fas fa-check"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Successful Syncs</div>
                            <div class="stat-value"><?php echo number_format($sync_stats['successful'] ?? 0); ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-red);"><i class="fas fa-times"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Failed Syncs</div>
                            <div class="stat-value"><?php echo number_format($sync_stats['failed'] ?? 0); ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-purple);"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Last Sync</div>
                            <div class="stat-value" style="font-size:0.9rem;">
                                <?php echo $sync_stats['last_sync'] ? date('M d, H:i', strtotime($sync_stats['last_sync'])) : 'Never'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LMS Configurations -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-server"></i> LMS Connections</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($configs)): ?>
                            <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                                <i class="fas fa-plug" style="font-size:4rem;margin-bottom:20px;opacity:0.3;"></i>
                                <p style="font-size:1.2rem;margin-bottom:10px;">No LMS Connections Configured</p>
                                <p>Click "Add LMS Connection" to set up integration with Moodle, Canvas, or other LMS platforms.</p>
                            </div>
                        <?php else: ?>
                            <div class="cyber-table-container">
                                <table class="cyber-table">
                                    <thead>
                                        <tr>
                                            <th>LMS Platform</th>
                                            <th>Name</th>
                                            <th>Client ID</th>
                                            <th>Status</th>
                                            <th>Auto Sync</th>
                                            <th>Last Sync</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($configs as $config): ?>
                                            <tr>
                                                <td>
                                                    <span class="cyber-badge" style="background:var(--cyber-purple);">
                                                        <?php echo strtoupper(htmlspecialchars($config['lms_platform'])); ?>
                                                    </span>
                                                </td>
                                                <td><strong><?php echo htmlspecialchars($config['lms_name']); ?></strong></td>
                                                <td><code><?php echo htmlspecialchars(substr($config['client_id'], 0, 20)); ?>...</code></td>
                                                <td>
                                                    <?php if ($config['is_active']): ?>
                                                        <span class="cyber-badge" style="background:var(--cyber-green);">
                                                            <i class="fas fa-check-circle"></i> Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cyber-badge" style="background:var(--text-muted);">
                                                            <i class="fas fa-times-circle"></i> Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($config['auto_sync_enabled']): ?>
                                                        <i class="fas fa-sync" style="color:var(--cyber-cyan);"></i>
                                                        Every <?php echo $config['sync_frequency'] / 60; ?>min
                                                    <?php else: ?>
                                                        <span style="color:var(--text-muted);">Manual</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $config['last_sync_at'] ? date('M d, H:i', strtotime($config['last_sync_at'])) : 'Never'; ?>
                                                </td>
                                                <td>
                                                    <button onclick="editConfig(<?php echo htmlspecialchars(json_encode($config)); ?>)"
                                                        class="cyber-btn cyber-btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button onclick="deleteConfig(<?php echo $config['id']; ?>)"
                                                        class="cyber-btn cyber-btn-sm" style="background:var(--cyber-red);">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Sync History -->
                <div class="holo-card" style="margin-top:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-history"></i> Recent Sync History</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_syncs)): ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">No sync history available</p>
                        <?php else: ?>
                            <div class="cyber-table-container">
                                <table class="cyber-table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>LMS</th>
                                            <th>Attendance %</th>
                                            <th>Grade Sent</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Synced At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_syncs as $sync): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sync['first_name'] . ' ' . $sync['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($sync['lms_name']); ?></td>
                                                <td><?php echo number_format($sync['attendance_percentage'], 1); ?>%</td>
                                                <td><strong><?php echo number_format($sync['grade_value'], 1); ?></strong></td>
                                                <td>
                                                    <span class="cyber-badge" style="background:var(--cyber-purple);">
                                                        <?php echo strtoupper($sync['sync_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($sync['status'] === 'success'): ?>
                                                        <span class="cyber-badge" style="background:var(--cyber-green);">
                                                            <i class="fas fa-check"></i> Success
                                                        </span>
                                                    <?php elseif ($sync['status'] === 'failed'): ?>
                                                        <span class="cyber-badge" style="background:var(--cyber-red);">
                                                            <i class="fas fa-times"></i> Failed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cyber-badge" style="background:var(--cyber-yellow);">
                                                            <i class="fas fa-clock"></i> Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, H:i:s', strtotime($sync['synced_at'])); ?></td>
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

    <!-- Add/Edit Config Modal -->
    <div id="configModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:9999;padding:20px;overflow:auto;">
        <div class="holo-card" style="max-width:900px;margin:30px auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-server"></i> <span id="modalTitle">Add LMS Connection</span></div>
                <button onclick="hideConfigModal()" style="background:none;border:none;color:var(--cyber-red);font-size:1.5rem;cursor:pointer;">×</button>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_config">
                    <input type="hidden" name="config_id" id="config_id" value="0">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label class="form-label">LMS Platform *</label>
                            <select name="lms_platform" id="lms_platform" class="cyber-input" required>
                                <option value="moodle">Moodle</option>
                                <option value="canvas">Canvas</option>
                                <option value="blackboard">Blackboard</option>
                                <option value="brightspace">Brightspace D2L</option>
                                <option value="schoology">Schoology</option>
                                <option value="other">Other LMS</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Display Name *</label>
                            <input type="text" name="lms_name" id="lms_name" class="cyber-input" required placeholder="e.g., Main Campus Moodle">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label class="form-label">Client ID *</label>
                            <input type="text" name="client_id" id="client_id" class="cyber-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Deployment ID *</label>
                            <input type="text" name="deployment_id" id="deployment_id" class="cyber-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Issuer URL *</label>
                        <input type="url" name="issuer" id="issuer" class="cyber-input" required placeholder="https://lms.example.edu">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label class="form-label">Auth Login URL</label>
                            <input type="url" name="auth_login_url" id="auth_login_url" class="cyber-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Auth Token URL</label>
                            <input type="url" name="auth_token_url" id="auth_token_url" class="cyber-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Keyset URL</label>
                            <input type="url" name="keyset_url" id="keyset_url" class="cyber-input">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Public Key (RSA PEM Format) *</label>
                        <textarea name="public_key" id="public_key" class="cyber-input" rows="5" required placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Private Key (RSA PEM Format) *</label>
                        <textarea name="private_key" id="private_key" class="cyber-input" rows="5" required placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"></textarea>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label class="form-label">Active</label>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="checkbox" name="is_active" id="is_active" value="1">
                                <span>Enable this connection</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Auto Sync Grades</label>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="checkbox" name="auto_sync_enabled" id="auto_sync_enabled" value="1">
                                <span>Enable automatic sync</span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sync Frequency (seconds)</label>
                            <input type="number" name="sync_frequency" id="sync_frequency" class="cyber-input" value="3600" min="60">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-top:20px;">
                        <button type="submit" class="cyber-btn"><i class="fas fa-save"></i> Save Configuration</button>
                        <button type="button" onclick="hideConfigModal()" class="cyber-btn cyber-btn-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showAddConfig() {
            document.getElementById('modalTitle').textContent = 'Add LMS Connection';
            document.getElementById('config_id').value = '0';
            document.querySelector('form').reset();
            document.getElementById('configModal').style.display = 'block';
        }

        function editConfig(config) {
            document.getElementById('modalTitle').textContent = 'Edit LMS Connection';
            document.getElementById('config_id').value = config.id;
            document.getElementById('lms_platform').value = config.lms_platform;
            document.getElementById('lms_name').value = config.lms_name;
            document.getElementById('client_id').value = config.client_id;
            document.getElementById('deployment_id').value = config.deployment_id;
            document.getElementById('issuer').value = config.issuer;
            document.getElementById('auth_login_url').value = config.auth_login_url || '';
            document.getElementById('auth_token_url').value = config.auth_token_url || '';
            document.getElementById('keyset_url').value = config.keyset_url || '';
            document.getElementById('public_key').value = config.public_key;
            document.getElementById('private_key').value = config.private_key;
            document.getElementById('is_active').checked = config.is_active == 1;
            document.getElementById('auto_sync_enabled').checked = config.auto_sync_enabled == 1;
            document.getElementById('sync_frequency').value = config.sync_frequency;
            document.getElementById('configModal').style.display = 'block';
        }

        function hideConfigModal() {
            document.getElementById('configModal').style.display = 'none';
        }

        function deleteConfig(id) {
            if (!confirm('Are you sure you want to delete this LMS configuration? This will also remove all associated sync history.')) {
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
            <input type="hidden" name="action" value="delete_config">
            <input type="hidden" name="config_id" value="${id}">
        `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>