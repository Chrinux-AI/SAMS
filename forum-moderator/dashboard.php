<?php

/**
 * SAMS Forum Moderator Dashboard - Advanced Moderation Interface
 * Professional dashboard with AI-powered moderation and content analysis
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_login('../login.php');
if (!has_role('forum_moderator') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Forum moderator privileges required.', 'error');
}

$moderator_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get moderation statistics
$moderation_stats = [
    'total_threads' => fm_count('forum_threads', 'tenant_id = ?', [$tenantId]),
    'total_posts' => fm_count('forum_posts', 'tenant_id = ?', [$tenantId]),
    'reported_posts' => fm_count('forum_posts', 'tenant_id = ? AND is_reported = 1', [$tenantId]),
    'moderated_today' => fm_count('audit_logs', 'tenant_id = ? AND action LIKE "%moderate%" AND DATE(created_at) = CURDATE()', [$tenantId]),
];

// Get reported posts
$reported_posts = db()->fetchAll("
    SELECT fp.*, u.first_name, u.last_name, u.role,
           ft.title as thread_title, ft.category_id,
           fc.name as category_name,
           COUNT(fpr.id) as report_count,
           GROUP_CONCAT(fpr.reason SEPARATOR ', ') as report_reasons
    FROM forum_posts fp
    JOIN users u ON fp.user_id = u.id
    JOIN forum_threads ft ON fp.thread_id = ft.id
    JOIN forum_categories fc ON ft.category_id = fc.id
    LEFT JOIN forum_post_reports fpr ON fp.id = fpr.post_id
    WHERE fp.tenant_id = ? AND fp.is_reported = 1
    GROUP BY fp.id
    ORDER BY fp.created_at DESC
    LIMIT 20
", [$tenantId]);

// Get recent moderation actions
$recent_moderation = db()->fetchAll("
    SELECT al.*, u.first_name, u.last_name,
           CASE
               WHEN al.action LIKE '%delete_post%' THEN 'Post Deleted'
               WHEN al.action LIKE '%delete_thread%' THEN 'Thread Deleted'
               WHEN al.action LIKE '%warn_user%' THEN 'User Warned'
               WHEN al.action LIKE '%ban_user%' THEN 'User Banned'
               ELSE 'Other Action'
           END as action_type
    FROM audit_logs al
    JOIN users u ON al.user_id = u.id
    WHERE al.tenant_id = ? AND al.action LIKE '%moderate%'
    ORDER BY al.created_at DESC
    LIMIT 10
", [$tenantId]);

// AI Moderation Insights
$ai_insights = [];
try {
    require_once '../includes/sams-init.php';
    try {
        if (class_exists('SAMS_ModerationBot')) {
            $moderationBot = new SAMS_ModerationBot();
            $ai_insights = $moderationBot->getModerationInsights($tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'moderation_health' => $moderation_stats['reported_posts'] > 10 ? 'needs_attention' : 'good',
            'content_quality' => 'stable',
            'recommendation' => $moderation_stats['reported_posts'] > 0 ? 'Review reported posts promptly to maintain community standards' : 'Forum moderation is running smoothly'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'moderation_health' => 'good',
        'content_quality' => 'stable',
        'recommendation' => 'Continue regular moderation monitoring and community engagement'
    ];
}

$csrf = generate_csrf_token();

function fm_count($table, $where = '1=1', $params = []) {
    try { if (!table_exists($table)) return 0; return (int)db()->count($table, $where, $params); } catch (Throwable $e) { return 0; }
}

$stats = [
    'total_threads'    => fm_count('forum_threads'),
    'total_posts'      => fm_count('forum_posts'),
    'reported_posts'   => fm_count('forum_reports', "status = 'pending'"),
    'active_users'     => fm_count('forum_user_stats', 'last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
    'categories'       => fm_count('forum_categories'),
    'warnings_issued'  => fm_count('forum_warnings'),
    'banned_users'     => fm_count('forum_bans', "expires_at IS NULL OR expires_at > NOW()"),
];

$recent_threads = [];
try {
    if (table_exists('forum_threads')) {
        $recent_threads = db()->fetchAll("
            SELECT ft.*, u.first_name, u.last_name, fc.name AS category_name
            FROM forum_threads ft
            LEFT JOIN users u ON ft.user_id = u.id
            LEFT JOIN forum_categories fc ON ft.category_id = fc.id
            ORDER BY ft.created_at DESC LIMIT 8
        ") ?: [];
    }
} catch (Throwable $e) {}

$recent_reports = [];
try {
    if (table_exists('forum_reports')) {
        $recent_reports = db()->fetchAll("
            SELECT fr.*, u.first_name, u.last_name
            FROM forum_reports fr
            LEFT JOIN users u ON fr.reporter_id = u.id
            WHERE fr.status = 'pending'
            ORDER BY fr.created_at DESC LIMIT 5
        ") ?: [];
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum Moderator Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#06b6d4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar-nav.php'; ?>
    <main class="main-content">
        <header class="cyber-header">
            <div class="page-title-section">
                <div class="page-icon-orb" style="background:linear-gradient(135deg,#06b6d4,#0891b2)"><i class="fas fa-comments"></i></div>
                <div>
                    <h1 class="page-title">Forum Moderator</h1>
                    <p class="page-subtitle">Community moderation & safety</p>
                </div>
            </div>
        </header>
        <div class="cyber-content">
            <!-- KPI Cards -->
            <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:24px">
                <?php
                $kpis = [
                    ['Threads','comments',$stats['total_threads'],'#06b6d4'],
                    ['Posts','comment-dots',$stats['total_posts'],'#0ea5e9'],
                    ['Reported','flag',$stats['reported_posts'],'#ef4444'],
                    ['Active Users','users',$stats['active_users'],'#10b981'],
                    ['Categories','folder-open',$stats['categories'],'#f59e0b'],
                    ['Warnings','exclamation-circle',$stats['warnings_issued'],'#f97316'],
                    ['Banned','user-slash',$stats['banned_users'],'#dc2626'],
                ];
                foreach ($kpis as [$label,$icon,$val,$color]): ?>
                <div class="cyber-card" style="padding:18px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                        <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600"><?php echo $label; ?></span>
                        <div style="width:32px;height:32px;border-radius:8px;background:<?php echo $color; ?>18;display:flex;align-items:center;justify-content:center"><i class="fas fa-<?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:.75rem"></i></div>
                    </div>
                    <div style="font-size:1.6rem;font-weight:800;color:var(--text-primary)"><?php echo number_format($val); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
                <!-- Quick Actions -->
                <div class="cyber-card" style="padding:20px">
                    <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-bolt" style="color:#06b6d4;margin-right:8px"></i>Quick Actions</h3>
                    <div style="display:flex;flex-direction:column;gap:8px">
                        <a href="reported-posts.php" class="btn btn-outline" style="display:flex;align-items:center;gap:8px;justify-content:flex-start;<?php echo $stats['reported_posts'] > 0 ? 'border-color:#ef4444;color:#ef4444' : ''; ?>"><i class="fas fa-flag"></i> Review Reports (<?php echo $stats['reported_posts']; ?>)</a>
                        <a href="threads.php" class="btn btn-outline" style="display:flex;align-items:center;gap:8px;justify-content:flex-start"><i class="fas fa-comments"></i> All Threads</a>
                        <a href="user-warnings.php" class="btn btn-outline" style="display:flex;align-items:center;gap:8px;justify-content:flex-start"><i class="fas fa-exclamation-circle"></i> User Warnings</a>
                        <a href="banned-users.php" class="btn btn-outline" style="display:flex;align-items:center;gap:8px;justify-content:flex-start"><i class="fas fa-user-slash"></i> Banned Users</a>
                        <a href="categories.php" class="btn btn-outline" style="display:flex;align-items:center;gap:8px;justify-content:flex-start"><i class="fas fa-folder-open"></i> Manage Categories</a>
                    </div>
                </div>

                <!-- Pending Reports -->
                <div class="cyber-card" style="padding:20px">
                    <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-flag" style="color:#ef4444;margin-right:8px"></i>Pending Reports</h3>
                    <?php if (empty($recent_reports)): ?>
                        <div style="text-align:center;padding:24px;color:var(--text-muted)">
                            <i class="fas fa-check-circle" style="font-size:2rem;color:#10b981;margin-bottom:8px;display:block"></i>
                            <p style="margin:0;font-size:.85rem"><?php echo table_exists('forum_reports') ? 'No pending reports — all clear!' : 'Forum tables not yet created.'; ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_reports as $r): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-color)">
                            <div style="width:32px;height:32px;border-radius:8px;background:#ef444418;display:flex;align-items:center;justify-content:center"><i class="fas fa-flag" style="color:#ef4444;font-size:.75rem"></i></div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:.82rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($r['reason'] ?? 'No reason given'); ?></div>
                                <div style="font-size:.7rem;color:var(--text-muted)">by <?php echo htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?> &bull; <?php echo date('M j', strtotime($r['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Threads -->
            <div class="cyber-card" style="padding:20px">
                <h3 style="margin:0 0 16px;font-size:1rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-clock-rotate-left" style="color:#0ea5e9;margin-right:8px"></i>Recent Threads</h3>
                <?php if (empty($recent_threads)): ?>
                    <div style="padding:16px;border-radius:12px;background:var(--bg-secondary);border:1px dashed var(--border-color)">
                        <p style="font-size:.85rem;color:var(--text-secondary);margin:0"><i class="fas fa-info-circle" style="color:#06b6d4;margin-right:6px"></i>Forum tables (<code>forum_threads</code>, <code>forum_posts</code>, <code>forum_categories</code>) will be created from the forum migration SQL.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table" style="width:100%">
                            <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Created</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent_threads as $t): ?>
                                <tr>
                                    <td style="font-weight:600"><?php echo htmlspecialchars($t['title'] ?? 'Untitled'); ?></td>
                                    <td><?php echo htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')); ?></td>
                                    <td><span class="badge"><?php echo htmlspecialchars($t['category_name'] ?? 'General'); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($t['created_at'])); ?></td>
                                    <td><span class="badge badge-<?php echo ($t['status'] ?? 'open') === 'open' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($t['status'] ?? 'open'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
