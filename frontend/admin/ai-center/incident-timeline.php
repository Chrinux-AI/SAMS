<?php
/**
 * AI Incident Timeline
 * Chronological view of all critical system events
 */
require_once __DIR__ . '/../../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

$csrf = generate_csrf_token();
$message = '';

// Filters
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['to'] ?? date('Y-m-d');
$event_type = $_GET['type'] ?? '';

$type_filter = '';
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];
if ($event_type) {
    $type_filter = ' AND action LIKE ?';
    $params[] = '%' . $event_type . '%';
}

// Fetch events
$events = [];
try {
    if (table_exists('audit_logs')) {
        $events = db()->fetchAll(
            "SELECT al.*, u.full_name as actor_name, u.role as actor_role
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             WHERE al.created_at BETWEEN ? AND ? {$type_filter}
             ORDER BY al.created_at DESC LIMIT 200",
            $params
        );
    }
} catch (Exception $e) {
    $events = [];
}

// Handle CSV export
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'export-csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=incident-timeline-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Action', 'Actor', 'Role', 'IP Address', 'Details']);
        foreach ($events as $evt) {
            fputcsv($output, [
                $evt['created_at'] ?? '',
                $evt['action'] ?? '',
                $evt['actor_name'] ?? 'System',
                $evt['actor_role'] ?? '',
                $evt['ip_address'] ?? '',
                $evt['details'] ?? ''
            ]);
        }
        fclose($output);
        exit;
    }
}

// Categorize severity
function get_event_severity($action) {
    $critical = ['delete', 'reset', 'block', 'lock', 'bulk_edit', 'financial_override', 'role_change'];
    $warning = ['edit', 'update', 'attendance_change', 'otp_fail', 'login_fail', 'anomaly'];
    $info = ['view', 'login', 'export', 'search', 'generate'];
    $action_lower = strtolower($action);
    foreach ($critical as $kw) {
        if (str_contains($action_lower, $kw)) return 'critical';
    }
    foreach ($warning as $kw) {
        if (str_contains($action_lower, $kw)) return 'warning';
    }
    foreach ($info as $kw) {
        if (str_contains($action_lower, $kw)) return 'info';
    }
    return 'success';
}

$event_types = ['attendance', 'login', 'security', 'financial', 'user', 'system', 'ai', 'edit', 'delete'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Incident Timeline - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/professional-ui.css">
    <?php include '../../includes/sams-head-bootstrap.php'; ?>

    <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../../assets/css/sams-layout.css">
    <style>
        .timeline { position: relative; padding-left: 32px; }
        .timeline::before { content: ''; position: absolute; left: 12px; top: 0; bottom: 0; width: 2px; background: var(--border-color, #e2e8f0); }
        .timeline-item { position: relative; margin-bottom: 20px; padding: 16px; border-radius: 12px; background: var(--card-bg, #fff); border: 1px solid var(--border-color, #e2e8f0); }
        .timeline-dot { position: absolute; left: -26px; top: 20px; width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--card-bg, #fff); }
        .dot-critical { background: #ef4444; }
        .dot-warning { background: #f59e0b; }
        .dot-info { background: #3b82f6; }
        .dot-success { background: #22c55e; }
        .timeline-meta { display: flex; gap: 16px; font-size: 0.78rem; color: var(--text-secondary); margin-top: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>

    <main class="main-content">
        <div class="cyber-header">
            <div class="page-icon-orb"><i class="fas fa-stream"></i></div>
            <div>
                <h1>AI Incident Timeline</h1>
                <p>Chronological view of <?= count($events) ?> critical system events</p>
            </div>
        </div>

        <div class="cyber-content">
            <!-- Filters -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <form method="GET" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>From</label>
                            <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>To</label>
                            <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Event Type</label>
                            <select name="type" class="form-control">
                                <option value="">All Types</option>
                                <?php foreach ($event_types as $t): ?>
                                    <option value="<?= $t ?>" <?= $event_type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                        <a href="incident-timeline.php" class="btn btn-outline">Reset</a>
                    </form>
                </div>
            </div>

            <!-- Export -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div style="display:flex; gap:12px;">
                    <span class="badge badge-danger">● Critical</span>
                    <span class="badge badge-warning">● Warning</span>
                    <span class="badge badge-info">● Info</span>
                    <span class="badge badge-success">● Success</span>
                </div>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="export-csv">
                    <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> Export CSV</button>
                </form>
            </div>

            <!-- Timeline -->
            <?php if (empty($events)): ?>
                <div class="card"><div class="card-body" style="text-align:center; padding:48px; color:var(--text-secondary);">
                    <i class="fas fa-stream" style="font-size:3rem; margin-bottom:16px; display:block;"></i>
                    <h3>No Events Found</h3>
                    <p>No incidents recorded for the selected period.</p>
                </div></div>
            <?php else: ?>
                <div class="timeline">
                    <?php
                    $current_date = '';
                    foreach ($events as $evt):
                        $severity = get_event_severity($evt['action'] ?? '');
                        $evt_date = date('F j, Y', strtotime($evt['created_at']));
                        if ($evt_date !== $current_date):
                            $current_date = $evt_date;
                    ?>
                        <div style="font-weight:600; font-size:0.9rem; margin: 20px 0 8px -32px; padding-left:32px; color:var(--text-primary);">
                            <i class="fas fa-calendar-day"></i> <?= $evt_date ?>
                        </div>
                    <?php endif; ?>
                    <div class="timeline-item">
                        <div class="timeline-dot dot-<?= $severity ?>"></div>
                        <div style="display:flex; justify-content:space-between; align-items:start;">
                            <div>
                                <strong style="font-size:0.9rem;"><?= htmlspecialchars($evt['action'] ?? 'Unknown Action') ?></strong>
                                <?php if (!empty($evt['details'])): ?>
                                    <p style="margin:4px 0 0; font-size:0.85rem; color:var(--text-secondary);">
                                        <?= htmlspecialchars(mb_substr($evt['details'], 0, 200)) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-<?= match($severity) { 'critical' => 'danger', 'warning' => 'warning', 'info' => 'info', default => 'success' } ?>"><?= ucfirst($severity) ?></span>
                        </div>
                        <div class="timeline-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($evt['actor_name'] ?? 'System') ?></span>
                            <span><i class="fas fa-shield-alt"></i> <?= ucfirst(htmlspecialchars($evt['actor_role'] ?? 'system')) ?></span>
                            <span><i class="fas fa-clock"></i> <?= date('H:i:s', strtotime($evt['created_at'])) ?></span>
                            <?php if (!empty($evt['ip_address'])): ?>
                                <span><i class="fas fa-globe"></i> <?= htmlspecialchars($evt['ip_address']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="../../assets/js/main.js"></script>
</body>
</html>
