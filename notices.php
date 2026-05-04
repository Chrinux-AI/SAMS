<?php

/**
 * Public Notices View
 * All users can view active notices here
 */

session_start();
require_once 'frontend/includes/config.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get notices
$category_filter = $_GET['category'] ?? 'all';
$where_category = $category_filter !== 'all' ? "AND n.category = ?" : "";
$params = [$role];
if ($category_filter !== 'all') {
    $params[] = $category_filter;
}

$notices = db()->fetchAll("
    SELECT n.*, u.first_name, u.last_name
    FROM notices n
    LEFT JOIN users u ON n.created_by = u.id
    WHERE n.status = 'active'
    AND (n.expires_at IS NULL OR n.expires_at > NOW())
    AND (n.target_roles IS NULL OR FIND_IN_SET(?, n.target_roles) > 0)
    $where_category
    ORDER BY n.is_pinned DESC, n.created_at DESC
", $params);

$unread_messages = get_unread_message_count((int)$user_id, (int)($_SESSION['tenant_id'] ?? 0));

$isAtlasSupport = has_role('accountant') || has_role('admin');
if ($isAtlasSupport) {
    $categoryMeta = [
        'all' => ['icon' => 'widgets', 'label' => 'All'],
        'academic' => ['icon' => 'school', 'label' => 'Academic'],
        'sports' => ['icon' => 'sports_soccer', 'label' => 'Sports'],
        'event' => ['icon' => 'event', 'label' => 'Events'],
        'emergency' => ['icon' => 'warning', 'label' => 'Emergency'],
        'maintenance' => ['icon' => 'construction', 'label' => 'Maintenance'],
        'general' => ['icon' => 'info', 'label' => 'General'],
    ];

    $page_title = 'Notice Board';
    $activeTab = 'support';
    require_once __DIR__ . '/accountant/partials/header.php';
?>
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-3 mb-8">
            <div>
                <h2 class="text-3xl font-headline font-extrabold tracking-tight text-on-surface">Notice Board</h2>
                <p class="text-on-surface-variant mt-1">Broadcast center for academic, operational, and emergency updates.</p>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full bg-surface-container-low px-4 py-2 text-xs font-bold text-on-surface-variant">
                <span class="material-symbols-outlined text-sm text-primary">mark_email_unread</span>
                <?php echo (int)$unread_messages; ?> unread messages
            </div>
        </div>

        <div class="flex flex-wrap gap-3 mb-8">
            <?php foreach ($categoryMeta as $key => $meta):
                $active = $category_filter === $key;
            ?>
                <a href="?category=<?php echo urlencode($key); ?>" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-bold transition-all <?php echo $active ? 'bg-primary text-white shadow-sm' : 'bg-surface-container-low text-on-surface hover:bg-surface-container-high'; ?>">
                    <span class="material-symbols-outlined text-base"><?php echo htmlspecialchars($meta['icon']); ?></span>
                    <?php echo htmlspecialchars($meta['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($notices)): ?>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/20 p-14 text-center">
                <span class="material-symbols-outlined text-6xl text-outline/40">inbox</span>
                <h3 class="text-xl font-bold mt-3">No Notices Available</h3>
                <p class="text-on-surface-variant mt-1">There are no active notices in this category right now.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($notices as $notice):
                    $priority = strtolower((string)($notice['priority'] ?? 'normal'));
                    $cat = strtolower((string)($notice['category'] ?? 'general'));
                    $catInfo = $categoryMeta[$cat] ?? $categoryMeta['general'];
                ?>
                    <article class="bg-surface-container-lowest rounded-2xl border border-outline-variant/20 shadow-sm p-6">
                        <div class="flex gap-4">
                            <div class="w-14 h-14 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-3xl"><?php echo htmlspecialchars($catInfo['icon']); ?></span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <h3 class="text-3xl font-headline font-extrabold text-[#C04900] leading-tight"><?php echo htmlspecialchars((string)$notice['title']); ?></h3>
                                    <?php if (!empty($notice['is_pinned'])): ?>
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary-fixed text-primary uppercase tracking-wider">Pinned</span>
                                    <?php endif; ?>
                                    <?php if ($priority === 'high' || $priority === 'urgent'): ?>
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $priority === 'urgent' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'; ?> uppercase tracking-wider"><?php echo htmlspecialchars($priority === 'urgent' ? 'Urgent' : 'High Priority'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="bg-[#EAF6FF] border-l-4 border-[#C04900] rounded-xl p-5 mb-4">
                                    <p class="text-lg text-[#1f3b5b] leading-relaxed whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars((string)$notice['content'])); ?></p>
                                </div>

                                <div class="flex flex-wrap items-center gap-5 text-sm text-on-surface-variant">
                                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-base">person</span>Posted by: <strong><?php echo htmlspecialchars(trim((string)($notice['first_name'] . ' ' . $notice['last_name']))); ?></strong></span>
                                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-base">schedule</span><?php echo date('F d, Y @ H:i', strtotime((string)$notice['created_at'])); ?></span>
                                    <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-base">sell</span><?php echo htmlspecialchars(ucfirst((string)$notice['category'])); ?></span>
                                    <?php if (!empty($notice['expires_at'])): ?>
                                        <span class="inline-flex items-center gap-1.5 text-amber-700"><span class="material-symbols-outlined text-base">hourglass_bottom</span>Valid until: <?php echo date('F d, Y', strtotime((string)$notice['expires_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php
    require_once __DIR__ . '/accountant/partials/footer.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head><?php include_once "frontend/includes/favicon-loader.php"; ?>
    <script src="frontend/assets/js/theme-loader.js"></script>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notice Board - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="frontend/assets/css/cyberpunk-ui.css" rel="stylesheet">
</head>

<body class="cyber-bg">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

    <div class="cyber-layout">
        <?php include 'includes/cyber-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-bullhorn"></i></div>
                    <h1 class="page-title">Notice Board</h1>
                </div>
            </header>

            <div class="cyber-content fade-in">
                <!-- Category Filters -->
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:30px;">
                    <a href="?category=all" class="cyber-btn <?php echo $category_filter === 'all' ? '' : 'cyber-btn-outline'; ?> cyber-btn-sm">
                        <i class="fas fa-th"></i> All
                    </a>
                    <a href="?category=academic" class="cyber-btn <?php echo $category_filter === 'academic' ? '' : 'cyber-btn-outline'; ?> cyber-btn-sm">
                        <i class="fas fa-graduation-cap"></i> Academic
                    </a>
                    <a href="?category=sports" class="cyber-btn <?php echo $category_filter === 'sports' ? '' : 'cyber-btn-outline'; ?> cyber-btn-sm">
                        <i class="fas fa-futbol"></i> Sports
                    </a>
                    <a href="?category=event" class="cyber-btn <?php echo $category_filter === 'event' ? '' : 'cyber-btn-outline'; ?> cyber-btn-sm">
                        <i class="fas fa-calendar-alt"></i> Events
                    </a>
                    <a href="?category=emergency" class="cyber-btn <?php echo $category_filter === 'emergency' ? '' : 'cyber-btn-outline'; ?> cyber-btn-sm">
                        <i class="fas fa-exclamation-triangle"></i> Emergency
                    </a>
                    <a href="?category=maintenance" class="cyber-btn <?php echo $category_filter === 'maintenance' ? '' : 'cyber-btn-outline'; ?> cyber-btn-sm">
                        <i class="fas fa-tools"></i> Maintenance
                    </a>
                    <a href="?category=general" class="cyber-btn <?php echo $category_filter === 'general' ? '' : 'cyber-btn-outline'; ?> cyber-btn-sm">
                        <i class="fas fa-info-circle"></i> General
                    </a>
                </div>

                <?php if (empty($notices)): ?>
                    <div class="holo-card">
                        <div class="card-body" style="text-align:center;padding:80px 20px;">
                            <i class="fas fa-inbox" style="font-size:5rem;margin-bottom:20px;opacity:0.2;color:var(--cyber-cyan);"></i>
                            <h3 style="margin-bottom:10px;">No Notices Available</h3>
                            <p style="color:var(--text-muted);">
                                <?php if ($category_filter !== 'all'): ?>
                                    No <?php echo ucfirst($category_filter); ?> notices at this time.
                                <?php else: ?>
                                    There are currently no active notices to display.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($notices as $notice): ?>
                        <div class="holo-card" style="margin-bottom:25px;">
                            <div class="card-body">
                                <div style="display:flex;align-items:start;gap:20px;">
                                    <?php
                                    // Category config
                                    $category_config = [
                                        'academic' => ['icon' => 'graduation-cap', 'color' => 'var(--cyber-purple)'],
                                        'sports' => ['icon' => 'futbol', 'color' => 'var(--cyber-green)'],
                                        'emergency' => ['icon' => 'exclamation-triangle', 'color' => 'var(--cyber-red)'],
                                        'event' => ['icon' => 'calendar-alt', 'color' => 'var(--cyber-cyan)'],
                                        'maintenance' => ['icon' => 'tools', 'color' => 'var(--cyber-yellow)'],
                                        'general' => ['icon' => 'info-circle', 'color' => 'var(--cyber-blue)']
                                    ];
                                    $config = $category_config[$notice['category']] ?? $category_config['general'];
                                    ?>
                                    <div style="flex-shrink:0;">
                                        <div style="width:60px;height:60px;border-radius:12px;background:<?php echo $config['color']; ?>;display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-<?php echo $config['icon']; ?>" style="color:white;font-size:1.8rem;"></i>
                                        </div>
                                    </div>
                                    <div style="flex-grow:1;">
                                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                                            <h2 style="margin:0;color:var(--cyber-cyan);font-size:1.5rem;">
                                                <?php echo htmlspecialchars($notice['title']); ?>
                                            </h2>
                                            <?php if ($notice['is_pinned']): ?>
                                                <span class="cyber-badge" style="background:var(--cyber-yellow);">
                                                    <i class="fas fa-thumbtack"></i> PINNED
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($notice['priority'] === 'urgent'): ?>
                                                <span class="cyber-badge" style="background:var(--cyber-red);animation:pulse 2s infinite;">
                                                    <i class="fas fa-bolt"></i> URGENT
                                                </span>
                                            <?php elseif ($notice['priority'] === 'high'): ?>
                                                <span class="cyber-badge" style="background:var(--cyber-yellow);">
                                                    <i class="fas fa-exclamation"></i> HIGH PRIORITY
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div style="background:rgba(0,191,255,0.05);padding:20px;border-radius:10px;border-left:4px solid var(--cyber-cyan);margin:15px 0;">
                                            <p style="margin:0;color:var(--text-body);line-height:1.8;white-space:pre-wrap;">
                                                <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                                            </p>
                                        </div>

                                        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:20px;margin-top:15px;font-size:0.9rem;color:var(--text-muted);">
                                            <span><i class="fas fa-user"></i> Posted by: <strong><?php echo htmlspecialchars($notice['first_name'] . ' ' . $notice['last_name']); ?></strong></span>
                                            <span><i class="fas fa-clock"></i> <?php echo date('F d, Y @ H:i', strtotime($notice['created_at'])); ?></span>
                                            <span><i class="fas fa-tag"></i> <?php echo ucfirst($notice['category']); ?></span>
                                            <?php if ($notice['expires_at']): ?>
                                                <span style="color:var(--cyber-yellow);">
                                                    <i class="fas fa-hourglass-end"></i> Valid until: <?php echo date('F d, Y', strtotime($notice['expires_at'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <style>
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.6;
            }
        }
    </style>

    <script src="frontend/assets/js/main.js"></script>
    <script src="frontend/assets/js/pwa-manager.js"></script>
    <script src="frontend/assets/js/pwa-analytics.js"></script>
</body>

</html>
