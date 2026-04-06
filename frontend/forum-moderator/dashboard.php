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

function fm_count($table, $where = '1=1', $params = [])
{
    try {
        if (!table_exists($table)) return 0;
        return (int)db()->count($table, $where, $params);
    } catch (Throwable $e) {
        return 0;
    }
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
} catch (Throwable $e) {
}

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
} catch (Throwable $e) {
}

// Master layout configuration
$page_title = 'Forum Moderator';
$page_icon = 'fas fa-comments';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);

ob_start();
?>

<!-- Bento Grid Dashboard -->
<div class="grid grid-cols-12 gap-6">

    <!-- Welcome Banner & AI Insights (Top Full Width) -->
    <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Welcome Banner (2 cols wide) -->
        <div class="lg:col-span-2 bg-cyan-700 text-white p-8 rounded-xl relative overflow-hidden group shadow-lg" style="background:linear-gradient(135deg, #0891B2 0%, #155E75 100%);">
            <div class="absolute -right-10 -bottom-10 opacity-10 group-hover:scale-110 transition-transform duration-700 pointer-events-none">
                <span class="material-symbols-outlined" style="font-size:180px">forum</span>
            </div>
            <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-cyan-200">Community Safety Central</span>
                    <h1 class="text-3xl font-headline font-bold mt-2">Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>!</h1>
                    <p class="text-cyan-100 mt-2 max-w-lg opacity-90">Oversee community discussions, review reported posts, manage thread categories, and ensure a safe environment for all students and staff.</p>
                </div>
                <div class="mt-6 flex gap-4">
                    <a href="reported-posts.php" class="px-5 py-2.5 bg-white text-cyan-800 font-bold rounded-lg text-sm hover:shadow-lg hover:scale-105 transition-all w-fit flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">gavel</span> Review Reports
                        <?php if ($stats['reported_posts'] > 0): ?>
                            <span class="ml-1 px-1.5 py-0.5 bg-rose-100 text-rose-700 rounded-md text-[10px]"><?php echo $stats['reported_posts']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="categories.php" class="px-5 py-2.5 bg-cyan-800 border border-cyan-500/50 text-white font-bold rounded-lg text-sm hover:bg-cyan-900 transition-all w-fit flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">folder_open</span> Manage Categories
                    </a>
                </div>
            </div>
        </div>

        <!-- AI Moderation Advisor Widget -->
        <div class="col-span-1 lg:col-span-1 bg-gradient-to-br from-cyan-50 to-sky-50 border border-cyan-100 p-6 rounded-xl flex flex-col shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-cyan-600">smart_toy</span>
                <h3 class="font-headline font-bold text-cyan-800">AI Moderation Advisor</h3>
            </div>
            <p class="text-sm font-medium text-cyan-900/80 mb-4 flex-grow"><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Continue regular moderation monitoring and community engagement.'); ?></p>

            <div class="mb-4 space-y-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-cyan-500">Content Quality</span>
                <p class="text-xs text-cyan-800 font-medium leading-tight flex items-center gap-2">
                    <span class="material-symbols-outlined text-[14px] text-cyan-400">verified</span> Forum adherence is <?php echo htmlspecialchars(strtoupper($ai_insights['content_quality'] ?? 'stable')); ?>
                </p>
            </div>

            <div class="space-y-3 pt-3 border-t border-cyan-200/50">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-cyan-700 font-semibold text-xs tracking-wide uppercase">Moderation Health</span>
                    <?php $mh = $ai_insights['moderation_health'] ?? 'good'; ?>
                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                <?php
                echo $mh === 'needs_attention' ? 'bg-rose-100 text-rose-700' : ($mh === 'excellent' ? 'bg-emerald-100 text-emerald-700' : 'bg-cyan-100 text-cyan-700');
                ?>">
                        <?php echo htmlspecialchars(str_replace('_', ' ', $mh)); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards Row 1 -->
    <div class="col-span-12 grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-cyan-500/30 transition-colors group">
            <div class="flex justify-between items-center mb-4">
                <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Threads</span>
                <div class="w-8 h-8 rounded-lg bg-cyan-50 text-cyan-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">forum</span></div>
            </div>
            <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-cyan-600 transition-colors"><?php echo number_format($stats['total_threads']); ?></span>
        </div>

        <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-sky-500/30 transition-colors group">
            <div class="flex justify-between items-center mb-4">
                <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Total Posts</span>
                <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">comment</span></div>
            </div>
            <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-sky-600 transition-colors"><?php echo number_format($stats['total_posts']); ?></span>
        </div>

        <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-rose-500/30 transition-colors group relative">
            <div class="flex justify-between items-center mb-4">
                <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Pending Reports</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">flag</span></div>
            </div>
            <span class="text-3xl font-extrabold font-headline text-rose-600"><?php echo number_format($stats['reported_posts']); ?></span>
            <?php if ($stats['reported_posts'] > 0): ?>
                <span class="absolute top-6 right-16 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-rose-500"></span>
                </span>
            <?php endif; ?>
        </div>

        <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-emerald-500/30 transition-colors group">
            <div class="flex justify-between items-center mb-4">
                <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Active Users</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">group</span></div>
            </div>
            <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-emerald-600 transition-colors"><?php echo number_format($stats['active_users']); ?></span>
        </div>
    </div>

    <!-- Stats Cards Row 2 -->
    <div class="col-span-12 grid grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-amber-500/30 transition-colors group">
            <div class="flex justify-between items-center mb-4">
                <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Categories</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">folder_open</span></div>
            </div>
            <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-amber-600 transition-colors"><?php echo number_format($stats['categories']); ?></span>
        </div>

        <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-orange-500/30 transition-colors group">
            <div class="flex justify-between items-center mb-4">
                <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Warnings Issued</span>
                <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">warning</span></div>
            </div>
            <span class="text-3xl font-extrabold font-headline text-primary group-hover:text-orange-600 transition-colors"><?php echo number_format($stats['warnings_issued']); ?></span>
        </div>

        <div class="bg-white p-6 rounded-xl border border-slate-200 flex flex-col shadow-sm hover:border-rose-500/30 transition-colors group">
            <div class="flex justify-between items-center mb-4">
                <span class="text-slate-400 font-label text-[10px] uppercase tracking-wider font-bold">Banned Users</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center"><span class="material-symbols-outlined text-[16px]">block</span></div>
            </div>
            <span class="text-3xl font-extrabold font-headline text-rose-600"><?php echo number_format($stats['banned_users']); ?></span>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="col-span-12 grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Quick Actions & Pending Reports (Left) -->
        <div class="lg:col-span-1 space-y-6">

            <!-- Quick Actions -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="font-headline font-bold text-sm text-primary mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-cyan-600">bolt</span> Quick Actions
                </h3>
                <div class="flex flex-col gap-3">
                    <a href="threads.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3 group">
                        <span class="material-symbols-outlined text-cyan-600 group-hover:scale-110 transition-transform">forum</span>
                        <span class="text-sm">All Threads</span>
                    </a>

                    <a href="user-warnings.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
                        <span class="material-symbols-outlined text-orange-500">warning</span>
                        <span class="text-sm">User Warnings</span>
                    </a>

                    <a href="banned-users.php" class="px-4 py-3 rounded-lg border border-slate-200 text-slate-700 font-bold hover:bg-slate-50 transition-colors flex items-center gap-3">
                        <span class="material-symbols-outlined text-rose-500">block</span>
                        <span class="text-sm">Banned Users</span>
                    </a>
                </div>
            </div>

            <!-- Pending Reports (Using $recent_reports) -->
            <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-headline font-bold text-sm text-primary flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-rose-500">flag</span> Pending Reports
                    </h3>
                    <a href="reported-posts.php" class="text-[10px] font-bold text-cyan-600 uppercase tracking-wider hover:text-cyan-800">View All</a>
                </div>

                <div class="space-y-4">
                    <?php if (!empty($recent_reports)): ?>
                        <?php foreach (array_slice($recent_reports, 0, 4) as $r): ?>
                            <div class="flex items-start gap-3 group hover:bg-slate-50 p-2 -mx-2 rounded-lg transition-colors cursor-pointer">
                                <div class="w-8 h-8 bg-rose-50 text-rose-600 rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="material-symbols-outlined text-[16px]">flag</span>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-on-surface line-clamp-1"><?php echo htmlspecialchars($r['reason'] ?? 'No reason given'); ?></h4>
                                    <p class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">by <?php echo htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')); ?></p>
                                    <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider">
                                        <?php echo date('M j, Y', strtotime($r['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-6 text-emerald-600">
                            <span class="material-symbols-outlined text-4xl mb-2 opacity-80">check_circle</span>
                            <p class="text-xs font-bold">No pending reports — all clear!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Threads (Right) -->
        <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm flex flex-col">
            <div class="flex justify-between items-center mb-6">
                <h3 class="font-headline font-bold text-sm text-primary flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">history</span> Recent Threads
                </h3>
                <a href="threads.php" class="text-[10px] font-bold text-cyan-600 hover:text-cyan-800 uppercase tracking-wider">View All</a>
            </div>

            <div class="flex-grow">
                <?php if (!empty($recent_threads)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-[10px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                    <th class="pb-3 font-bold pr-4">Thread Title</th>
                                    <th class="pb-3 font-bold">Author</th>
                                    <th class="pb-3 font-bold">Category</th>
                                    <th class="pb-3 font-bold">Created Date</th>
                                    <th class="pb-3 font-bold text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach (array_slice($recent_threads, 0, 8) as $t):
                                    $status = strtolower($t['status'] ?? 'open');
                                    $cat = htmlspecialchars($t['category_name'] ?? 'General');

                                    $badgeClass = 'bg-slate-100 text-slate-600';
                                    $icon = 'circle';

                                    if ($status === 'open') {
                                        $badgeClass = 'bg-emerald-100 text-emerald-700';
                                        $icon = 'check_circle';
                                    } elseif ($status === 'closed' || $status === 'locked') {
                                        $badgeClass = 'bg-slate-100 text-slate-700';
                                        $icon = 'lock';
                                    } elseif ($status === 'archived') {
                                        $badgeClass = 'bg-amber-100 text-amber-700';
                                        $icon = 'archive';
                                    }
                                ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="py-3 pr-4">
                                            <a href="view-thread.php?id=<?php echo $t['id'] ?? ''; ?>" class="font-bold text-primary group-hover:text-cyan-600 transition-colors line-clamp-1 max-w-[250px]"><?php echo htmlspecialchars($t['title'] ?? 'Untitled'); ?></a>
                                        </td>
                                        <td class="py-3 text-slate-600 font-medium whitespace-nowrap text-xs"><?php echo htmlspecialchars(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')); ?></td>
                                        <td class="py-3 text-slate-500 whitespace-nowrap"><span class="px-2 py-1 bg-cyan-50 text-cyan-700 rounded text-[10px] font-bold uppercase tracking-wider"><?php echo $cat; ?></span></td>
                                        <td class="py-3 text-slate-500 text-xs whitespace-nowrap font-medium"><?php echo date('M j, Y - g:i a', strtotime($t['created_at'])); ?></td>
                                        <td class="py-3 text-right">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider <?php echo $badgeClass; ?>">
                                                <span class="material-symbols-outlined text-[12px]"><?php echo $icon; ?></span> <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="flex flex-col items-center justify-center text-center h-full text-slate-400 pb-10 pt-10">
                        <span class="material-symbols-outlined text-5xl mb-4 opacity-20">forum</span>
                        <p class="text-sm max-w-[300px] mb-2 font-medium text-slate-500">No active forum threads yet.</p>
                        <p class="text-xs max-w-[300px]">Forum tables will be created from the forum migration SQL. Students and staff can create threads once setup is complete.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
