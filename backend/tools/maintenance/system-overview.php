<?php
/**
 * System Overview - Premium Multi-Tenant Command Center
 * Enterprise-grade SaaS administration hub
 */
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

function safe_count($table, $where = '1=1', $params = []) {
    try { if (!table_exists($table)) return 0; return (int)db()->count($table, $where, $params); } catch (Throwable $e) { return 0; }
}

// --- Role registry with categories ---
$role_categories = [
    'Administration' => [
        'admin'      => ['label'=>'Administrator','icon'=>'user-shield','color'=>'#ef4444','gradient'=>'linear-gradient(135deg,#ef4444,#dc2626)','path'=>'/attendance/admin/dashboard.php','desc'=>'Full system control, user management, reports, analytics, and institutional governance','modules'=>['User Management','Reports','Analytics','System Config','Audit Logs','Backup']],
        'superadmin' => ['label'=>'Super Admin','icon'=>'crown','color'=>'#b91c1c','gradient'=>'linear-gradient(135deg,#b91c1c,#991b1b)','path'=>'/attendance/admin/dashboard.php','desc'=>'Platform-wide super administration across all tenants','modules'=>['All Admin + Multi-Tenant Control']],
        'principal'  => ['label'=>'Principal','icon'=>'landmark','color'=>'#7c3aed','gradient'=>'linear-gradient(135deg,#7c3aed,#6d28d9)','path'=>'/attendance/admin/dashboard.php','desc'=>'School-level oversight, staff management, academic strategy','modules'=>['Staff Overview','Academic Calendar','Policy Management']],
    ],
    'Academic Staff' => [
        'teacher'    => ['label'=>'Teacher','icon'=>'chalkboard-teacher','color'=>'#0ea5e9','gradient'=>'linear-gradient(135deg,#0ea5e9,#0284c7)','path'=>'/attendance/teacher/dashboard.php','desc'=>'Class management, attendance marking, assignments, grading, and parent communication','modules'=>['My Classes','Attendance','Assignments','Grades','Materials','Parent Comms']],
        'class_teacher' => ['label'=>'Class Teacher','icon'=>'people-roof','color'=>'#0369a1','gradient'=>'linear-gradient(135deg,#0369a1,#075985)','path'=>'/attendance/teacher/dashboard.php','desc'=>'Homeroom class oversight with pastoral responsibility','modules'=>['Homeroom','Behavior Logs','Meeting Hours']],
        'subject_coordinator' => ['label'=>'Subject Coordinator','icon'=>'sitemap','color'=>'#0891b2','gradient'=>'linear-gradient(135deg,#0891b2,#0e7490)','path'=>'/attendance/teacher/dashboard.php','desc'=>'Subject-level curriculum coordination and teacher mentoring','modules'=>['Curriculum','Teacher Mentoring','Subject Reports']],
        'counselor'  => ['label'=>'Counselor','icon'=>'hand-holding-heart','color'=>'#ec4899','gradient'=>'linear-gradient(135deg,#ec4899,#db2777)','path'=>'/attendance/student/dashboard.php','desc'=>'Student welfare, behavioral counseling, and wellness programs','modules'=>['Student Wellness','Behavior Tracking','Referrals']],
    ],
    'Students & Parents' => [
        'student'    => ['label'=>'Student','icon'=>'user-graduate','color'=>'#10b981','gradient'=>'linear-gradient(135deg,#10b981,#059669)','path'=>'/attendance/student/dashboard.php','desc'=>'Personal attendance, schedules, assignments, grades, LMS portal, and peer chat','modules'=>['Dashboard','Attendance','Assignments','Grades','LMS Portal','ID Card']],
        'parent'     => ['label'=>'Parent/Guardian','icon'=>'users','color'=>'#f59e0b','gradient'=>'linear-gradient(135deg,#f59e0b,#d97706)','path'=>'/attendance/parent/dashboard.php','desc'=>'Monitor children\'s attendance, grades, fees, and schedule teacher meetings','modules'=>['Children','Attendance','Grades','Fees','Meetings','Reports']],
    ],
    'Finance & Accounting' => [
        'bursar'     => ['label'=>'Bursar','icon'=>'money-check-dollar','color'=>'#14b8a6','gradient'=>'linear-gradient(135deg,#14b8a6,#0d9488)','path'=>'/attendance/bursar/dashboard.php','desc'=>'Fee collection, invoicing, payment plans, receipt generation, and financial summaries','modules'=>['Fee Collection','Invoices','Payment Plans','Receipts','Defaulters','Daily Summary']],
        'accountant' => ['label'=>'Accountant','icon'=>'calculator','color'=>'#6366f1','gradient'=>'linear-gradient(135deg,#6366f1,#4f46e5)','path'=>'/attendance/accountant/index.php?page=dashboard','desc'=>'General ledger, expense tracking, payroll integration, and financial reporting','modules'=>['Ledger','Expenses','Payroll','Balance Sheet','P&L','Tax Reports']],
    ],
    'Operations' => [
        'librarian'  => ['label'=>'Librarian','icon'=>'book-open','color'=>'#8b5cf6','gradient'=>'linear-gradient(135deg,#8b5cf6,#7c3aed)','path'=>'/attendance/librarian/dashboard.php','desc'=>'Book catalog management, lending workflow, fines, and digital resource tracking','modules'=>['Catalog','Issue/Return','Fines','Digital Resources','Reports','Inventory']],
        'transport'  => ['label'=>'Transport Officer','icon'=>'bus','color'=>'#f97316','gradient'=>'linear-gradient(135deg,#f97316,#ea580c)','path'=>'/attendance/transport/dashboard.php','desc'=>'Bus routes, fleet management, driver logs, student pickup/drop tracking','modules'=>['Routes','Vehicles','Drivers','Student Allocation','GPS Tracking','Reports']],
        'nurse'      => ['label'=>'School Nurse','icon'=>'heart-pulse','color'=>'#f43f5e','gradient'=>'linear-gradient(135deg,#f43f5e,#e11d48)','path'=>'/attendance/student/dashboard.php','desc'=>'Student health records, first aid logs, medication tracking, and wellness alerts','modules'=>['Health Records','First Aid Log','Medications','Wellness Alerts']],
    ],
    'Community' => [
        'forum_moderator' => ['label'=>'Forum Moderator','icon'=>'comments','color'=>'#06b6d4','gradient'=>'linear-gradient(135deg,#06b6d4,#0891b2)','path'=>'/attendance/forum-moderator/dashboard.php','desc'=>'Community forum moderation, thread management, reports, and safety enforcement','modules'=>['Forum Home','Reported Posts','User Warnings','Thread Management']],
    ],
];

$all_roles = [];
foreach ($role_categories as $cat => $roles_in_cat) { $all_roles = array_merge($all_roles, $roles_in_cat); }

// --- Database status ---
$db_status = 'Connected'; $db_version = '';
try { $r = db()->fetchOne('SELECT VERSION() AS v'); $db_version = $r['v'] ?? ''; } catch (Throwable $e) { $db_status = 'Disconnected'; }

// --- Stats ---
$stats = [
    'users_total'   => safe_count('users'),
    'students'      => safe_count('students'),
    'classes'        => safe_count('classes'),
    'messages'       => safe_count('messages'),
    'forum_threads'  => safe_count('forum_threads'),
    'attendance_today' => safe_count('attendance', 'date = CURDATE()'),
    'tenants'        => table_exists('school_tenants') ? safe_count('school_tenants') : 1,
    'active_users_7d'=> safe_count('users', 'last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
];

$role_counts = [];
foreach ($all_roles as $rk => $rm) { $role_counts[$rk] = safe_count('users', 'role = :r', ['r' => $rk]); }
$total_role_users = array_sum($role_counts);

// --- Recent activity ---
$recent_users = [];
try {
    $recent_users = db()->fetchAll("SELECT first_name, last_name, role, last_login, is_active FROM users ORDER BY last_login DESC LIMIT 8") ?: [];
} catch (Throwable $e) {}

// --- Module counts ---
$module_stats = [
    'library_books' => safe_count('library_books'),
    'fee_payments'  => safe_count('fee_payments'),
    'transport_routes' => safe_count('transport_routes'),
    'forum_posts'   => safe_count('forum_posts'),
    'notifications' => safe_count('notifications'),
];

$logged_in = isset($_SESSION['user_id']);
$current_role = $_SESSION['role'] ?? 'guest';
$current_user = $_SESSION['full_name'] ?? 'Guest';
$current_tenant = $_SESSION['tenant_name'] ?? 'Default School';
$current_tenant_id = (int)($_SESSION['tenant_id'] ?? 1);

$role_dashboards = [
    'admin'=>'/attendance/admin/dashboard.php','superadmin'=>'/attendance/admin/dashboard.php',
    'owner'=>'/attendance/admin/dashboard.php','principal'=>'/attendance/admin/dashboard.php',
    'teacher'=>'/attendance/teacher/dashboard.php','class_teacher'=>'/attendance/teacher/dashboard.php',
    'student'=>'/attendance/student/dashboard.php','parent'=>'/attendance/parent/dashboard.php',
    'librarian'=>'/attendance/librarian/dashboard.php','bursar'=>'/attendance/bursar/dashboard.php',
    'accountant'=>'/attendance/accountant/index.php?page=dashboard','transport'=>'/attendance/transport/dashboard.php',
    'forum_moderator'=>'/attendance/forum-moderator/dashboard.php',
];
$my_dashboard = $role_dashboards[$current_role] ?? '/attendance/login.php';

$php_ver = phpversion();
$server_time = date('Y-m-d H:i T');
$uptime = @file_get_contents('/proc/uptime') ? explode(' ', file_get_contents('/proc/uptime'))[0] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once 'includes/favicon-loader.php'; ?>
    <script src="assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <title>Command Center - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/professional-ui.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',system-ui,-apple-system,sans-serif;background:#0b1120;color:#e2e8f0;min-height:100vh;overflow-x:hidden}

        /* --- Animated Background --- */
        .bg-pattern{position:fixed;inset:0;z-index:0;overflow:hidden}
        .bg-pattern::before{content:'';position:absolute;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(59,130,246,0.08),transparent 70%);top:-200px;right:-100px;animation:floatOrb 20s ease-in-out infinite}
        .bg-pattern::after{content:'';position:absolute;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,0.06),transparent 70%);bottom:-100px;left:-50px;animation:floatOrb 15s ease-in-out infinite reverse}
        @keyframes floatOrb{0%,100%{transform:translate(0,0)}50%{transform:translate(30px,-30px)}}

        .cmd-center{position:relative;z-index:1;max-width:1440px;margin:0 auto;padding:24px 28px 60px}

        /* --- Top Bar --- */
        .top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px}
        .top-bar-left{display:flex;align-items:center;gap:14px}
        .top-bar-logo{width:48px;height:48px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;box-shadow:0 4px 20px rgba(59,130,246,0.3)}
        .top-bar h1{font-size:1.6rem;font-weight:800;background:linear-gradient(135deg,#60a5fa,#a78bfa);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.02em}
        .top-bar-sub{font-size:.78rem;color:#64748b;font-weight:500}
        .top-bar-right{display:flex;gap:10px;align-items:center}
        .tb-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:12px;text-decoration:none;font-weight:600;font-size:.85rem;border:1px solid rgba(255,255,255,0.08);color:#cbd5e1;background:rgba(255,255,255,0.04);backdrop-filter:blur(8px);transition:all .2s}
        .tb-btn:hover{background:rgba(255,255,255,0.08);color:#fff;border-color:rgba(255,255,255,0.15)}
        .tb-btn.primary{background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;border-color:transparent;box-shadow:0 4px 16px rgba(59,130,246,0.3)}
        .tb-btn.primary:hover{box-shadow:0 6px 24px rgba(59,130,246,0.45);transform:translateY(-1px)}

        /* --- Hero Section --- */
        .hero-section{display:grid;grid-template-columns:1.4fr 1fr;gap:20px;margin-bottom:24px}
        .hero-card{border-radius:20px;padding:32px;position:relative;overflow:hidden;border:1px solid rgba(255,255,255,0.06)}
        .hero-main{background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f172a 100%);border:1px solid rgba(59,130,246,0.15)}
        .hero-main::before{content:'';position:absolute;top:0;right:0;width:300px;height:300px;background:radial-gradient(circle,rgba(59,130,246,0.12),transparent 70%);pointer-events:none}
        .hero-main h2{font-size:2rem;font-weight:900;margin-bottom:8px;background:linear-gradient(135deg,#fff,#94a3b8);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .hero-main p{color:#94a3b8;font-size:.95rem;line-height:1.6;max-width:520px}
        .tenant-badge{display:inline-flex;align-items:center;gap:8px;margin-top:16px;padding:10px 16px;border-radius:999px;background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.2);color:#60a5fa;font-size:.82rem;font-weight:600}
        .hero-stats-card{background:rgba(15,23,42,0.6);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.06)}
        .stat-title{font-size:.82rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px}
        .kpi-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
        .kpi-item{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:14px;padding:16px;transition:all .2s}
        .kpi-item:hover{background:rgba(255,255,255,0.06);border-color:rgba(59,130,246,0.2)}
        .kpi-val{font-size:1.5rem;font-weight:800;color:#f8fafc}
        .kpi-label{font-size:.72rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-top:2px}
        .kpi-item .kpi-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.85rem;margin-bottom:8px}

        /* --- Session Bar --- */
        .session-bar{display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-radius:16px;background:rgba(15,23,42,0.6);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.06);margin-bottom:24px;flex-wrap:wrap;gap:12px}
        .session-info{display:flex;align-items:center;gap:12px}
        .session-avatar{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem}
        .session-meta .sm-name{font-weight:700;color:#f1f5f9;font-size:.9rem}
        .session-meta .sm-role{font-size:.75rem;color:#64748b}
        .session-actions{display:flex;gap:8px}

        /* --- Section Headers --- */
        .sec-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
        .sec-header h2{font-size:1.15rem;font-weight:800;color:#f1f5f9;display:flex;align-items:center;gap:10px}
        .sec-header h2 i{color:#3b82f6;font-size:.9rem}
        .sec-badge{font-size:.72rem;background:rgba(59,130,246,0.12);color:#60a5fa;padding:4px 10px;border-radius:99px;font-weight:600}

        /* --- Role Category Section --- */
        .category-section{margin-bottom:32px}
        .cat-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#475569;margin-bottom:14px;padding-left:4px;display:flex;align-items:center;gap:8px}
        .cat-label::after{content:'';flex:1;height:1px;background:rgba(255,255,255,0.06)}
        .role-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px}

        /* --- Role Cards --- */
        .role-card{border-radius:18px;padding:24px;background:rgba(15,23,42,0.5);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.06);transition:all .3s ease;position:relative;overflow:hidden}
        .role-card:hover{border-color:rgba(59,130,246,0.2);transform:translateY(-3px);box-shadow:0 12px 40px rgba(0,0,0,0.3)}
        .role-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;opacity:0;transition:opacity .3s}
        .role-card:hover::before{opacity:1}
        .rc-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
        .rc-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;box-shadow:0 4px 16px rgba(0,0,0,0.2)}
        .rc-count{text-align:right}
        .rc-count .num{font-size:1.6rem;font-weight:900;color:#f8fafc}
        .rc-count .users-label{font-size:.65rem;color:#64748b;text-transform:uppercase;letter-spacing:.06em}
        .rc-title{font-size:1.05rem;font-weight:800;color:#f1f5f9;margin-bottom:6px}
        .rc-desc{font-size:.82rem;color:#94a3b8;line-height:1.5;margin-bottom:14px;min-height:40px}
        .rc-modules{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px}
        .rc-mod-tag{font-size:.68rem;padding:3px 8px;border-radius:6px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);color:#94a3b8;font-weight:500}
        .rc-actions{display:flex;gap:8px}
        .rc-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:.78rem;font-weight:600;text-decoration:none;transition:all .2s}
        .rc-btn.open{background:rgba(255,255,255,0.06);color:#e2e8f0;border:1px solid rgba(255,255,255,0.1)}
        .rc-btn.open:hover{background:rgba(255,255,255,0.1)}
        .rc-btn.status{border:1px dashed;font-size:.72rem;cursor:default}
        .rc-btn.ready{color:#22c55e;border-color:rgba(34,197,94,0.3)}
        .rc-btn.scaffold{color:#f59e0b;border-color:rgba(245,158,11,0.3)}

        /* --- Activity Panel --- */
        .panels-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:32px}
        .info-panel{border-radius:18px;padding:24px;background:rgba(15,23,42,0.5);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.06)}
        .info-panel h3{font-size:.95rem;font-weight:700;color:#f1f5f9;margin-bottom:16px;display:flex;align-items:center;gap:8px}
        .info-panel h3 i{color:#3b82f6}
        .act-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04)}
        .act-row:last-child{border-bottom:none}
        .act-avatar{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0}
        .act-info{flex:1;min-width:0}
        .act-name{font-size:.82rem;font-weight:600;color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .act-meta{font-size:.7rem;color:#64748b}
        .act-badge{font-size:.65rem;padding:3px 8px;border-radius:6px;font-weight:600}

        .sys-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:.85rem}
        .sys-row:last-child{border-bottom:none}
        .sys-row .sys-k{color:#64748b}
        .sys-row .sys-v{color:#e2e8f0;font-weight:600}
        .sys-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:6px}
        .sys-dot.green{background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,0.4)}
        .sys-dot.red{background:#ef4444;box-shadow:0 0 8px rgba(239,68,68,0.4)}

        /* --- Multi-Tenant Section --- */
        .mt-section{border-radius:18px;padding:28px;background:linear-gradient(135deg,rgba(59,130,246,0.06),rgba(139,92,246,0.04));border:1px solid rgba(59,130,246,0.12);margin-bottom:32px}
        .mt-section h2{font-size:1.2rem;font-weight:800;color:#f1f5f9;margin-bottom:6px}
        .mt-section p{color:#94a3b8;font-size:.88rem;margin-bottom:20px;max-width:600px}
        .mt-features{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
        .mt-feat{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px;text-align:center}
        .mt-feat i{font-size:1.4rem;margin-bottom:8px;display:block}
        .mt-feat .mf-title{font-size:.85rem;font-weight:700;color:#e2e8f0}
        .mt-feat .mf-desc{font-size:.72rem;color:#64748b;margin-top:4px}

        /* --- Footer --- */
        .cmd-footer{text-align:center;padding:20px 0;color:#475569;font-size:.78rem;border-top:1px solid rgba(255,255,255,0.04)}
        .cmd-footer a{color:#60a5fa;text-decoration:none}

        /* --- Responsive --- */
        @media(max-width:1024px){.hero-section{grid-template-columns:1fr}.panels-grid{grid-template-columns:1fr}}
        @media(max-width:768px){.role-grid{grid-template-columns:1fr}.kpi-grid{grid-template-columns:1fr}.top-bar{flex-direction:column;align-items:flex-start}}
        @media(max-width:480px){.cmd-center{padding:16px 12px 40px}.hero-card{padding:20px}.mt-features{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="bg-pattern"></div>

<div class="cmd-center">
    <!-- Top Bar -->
    <header class="top-bar">
        <div class="top-bar-left">
            <div class="top-bar-logo"><i class="fas fa-graduation-cap"></i></div>
            <div>
                <h1><?php echo APP_NAME; ?></h1>
                <div class="top-bar-sub">Multi-Tenant School Management Platform &bull; v<?php echo APP_VERSION; ?></div>
            </div>
        </div>
        <div class="top-bar-right">
            <?php if ($logged_in): ?>
                <a class="tb-btn primary" href="<?php echo htmlspecialchars($my_dashboard); ?>"><i class="fas fa-gauge-high"></i> My Dashboard</a>
                <a class="tb-btn" href="system-overview.php"><i class="fas fa-rotate"></i> Refresh</a>
                <a class="tb-btn" href="logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
            <?php else: ?>
                <a class="tb-btn primary" href="login.php"><i class="fas fa-right-to-bracket"></i> Sign In</a>
                <a class="tb-btn" href="register.php"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Hero -->
    <section class="hero-section">
        <article class="hero-card hero-main">
            <h2>Command Center</h2>
            <p>Enterprise-grade school management platform supporting multiple institutions, <?php echo count($all_roles); ?> distinct role workspaces, and comprehensive operational modules — from academics and finance to transportation and community.</p>
            <div class="tenant-badge">
                <i class="fas fa-building"></i>
                <?php echo htmlspecialchars($current_tenant); ?> &bull; Tenant #<?php echo $current_tenant_id; ?>
                &bull; <?php echo number_format($stats['tenants']); ?> institution<?php echo $stats['tenants'] !== 1 ? 's' : ''; ?> registered
            </div>
        </article>
        <article class="hero-card hero-stats-card">
            <div class="stat-title">Platform Metrics</div>
            <div class="kpi-grid">
                <div class="kpi-item">
                    <div class="kpi-icon" style="background:rgba(59,130,246,0.15);color:#60a5fa"><i class="fas fa-users"></i></div>
                    <div class="kpi-val"><?php echo number_format($stats['users_total']); ?></div>
                    <div class="kpi-label">Total Users</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-icon" style="background:rgba(16,185,129,0.15);color:#34d399"><i class="fas fa-user-graduate"></i></div>
                    <div class="kpi-val"><?php echo number_format($stats['students']); ?></div>
                    <div class="kpi-label">Enrolled Students</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-icon" style="background:rgba(245,158,11,0.15);color:#fbbf24"><i class="fas fa-clipboard-check"></i></div>
                    <div class="kpi-val"><?php echo number_format($stats['attendance_today']); ?></div>
                    <div class="kpi-label">Attendance Today</div>
                </div>
                <div class="kpi-item">
                    <div class="kpi-icon" style="background:rgba(139,92,246,0.15);color:#a78bfa"><i class="fas fa-bolt"></i></div>
                    <div class="kpi-val"><?php echo number_format($stats['active_users_7d']); ?></div>
                    <div class="kpi-label">Active (7 days)</div>
                </div>
            </div>
        </article>
    </section>

    <!-- Session Bar -->
    <div class="session-bar">
        <div class="session-info">
            <div class="session-avatar"><?php echo strtoupper(substr($current_user, 0, 2)); ?></div>
            <div class="session-meta">
                <div class="sm-name"><?php echo htmlspecialchars($current_user); ?></div>
                <div class="sm-role"><?php echo $logged_in ? ucfirst(str_replace('_', ' ', $current_role)) . ' Session' : 'Guest — not signed in'; ?></div>
            </div>
        </div>
        <div class="session-actions">
            <span class="tb-btn" style="cursor:default;gap:6px"><i class="fas fa-building"></i> <?php echo htmlspecialchars($current_tenant); ?></span>
            <span class="tb-btn" style="cursor:default;gap:6px"><i class="fas fa-users"></i> <?php echo number_format($total_role_users); ?> users across <?php echo count($all_roles); ?> roles</span>
        </div>
    </div>

    <!-- Multi-Tenant Feature Highlight -->
    <section class="mt-section">
        <h2><i class="fas fa-network-wired" style="color:#60a5fa;margin-right:8px"></i> Multi-Tenant Architecture</h2>
        <p>Each school operates in its own isolated environment with separate data, configurations, and user base — while sharing the same powerful platform.</p>
        <div class="mt-features">
            <div class="mt-feat"><i class="fas fa-shield-halved" style="color:#22c55e"></i><div class="mf-title">Data Isolation</div><div class="mf-desc">Each tenant's data is fully segregated</div></div>
            <div class="mt-feat"><i class="fas fa-palette" style="color:#a78bfa"></i><div class="mf-title">Custom Branding</div><div class="mf-desc">Logo, colors, and identity per school</div></div>
            <div class="mt-feat"><i class="fas fa-sliders" style="color:#fbbf24"></i><div class="mf-title">Config Isolation</div><div class="mf-desc">Academic terms, grading scales per tenant</div></div>
            <div class="mt-feat"><i class="fas fa-user-gear" style="color:#f472b6"></i><div class="mf-title"><?php echo count($all_roles); ?> Role Types</div><div class="mf-desc">From admin to transport officer</div></div>
            <div class="mt-feat"><i class="fas fa-plug" style="color:#60a5fa"></i><div class="mf-title">Modular Design</div><div class="mf-desc">Enable/disable modules per tenant</div></div>
            <div class="mt-feat"><i class="fas fa-chart-column" style="color:#fb923c"></i><div class="mf-title">Cross-Tenant Analytics</div><div class="mf-desc">Super admin oversight across all schools</div></div>
        </div>
    </section>

    <!-- Role Workspaces by Category -->
    <div class="sec-header">
        <h2><i class="fas fa-grid-2"></i> Role Workspaces</h2>
        <span class="sec-badge"><?php echo count($all_roles); ?> Roles &bull; <?php echo number_format($total_role_users); ?> Users</span>
    </div>

    <?php foreach ($role_categories as $category => $cat_roles): ?>
        <section class="category-section">
            <div class="cat-label"><?php echo $category; ?></div>
            <div class="role-grid">
                <?php foreach ($cat_roles as $roleKey => $meta): ?>
                    <?php
                        $filePath = BASE_PATH . str_replace('/attendance', '', $meta['path']);
                        $exists = file_exists($filePath);
                        $count = $role_counts[$roleKey] ?? 0;
                    ?>
                    <article class="role-card" style="--card-color:<?php echo $meta['color']; ?>">
                        <style>.role-card[style*="<?php echo $meta['color']; ?>"]::before{background:<?php echo $meta['gradient']; ?>}</style>
                        <div class="rc-top">
                            <div class="rc-icon" style="background:<?php echo $meta['gradient']; ?>">
                                <i class="fas fa-<?php echo $meta['icon']; ?>"></i>
                            </div>
                            <div class="rc-count">
                                <div class="num"><?php echo number_format($count); ?></div>
                                <div class="users-label">user<?php echo $count !== 1 ? 's' : ''; ?></div>
                            </div>
                        </div>
                        <div class="rc-title"><?php echo htmlspecialchars($meta['label']); ?></div>
                        <div class="rc-desc"><?php echo htmlspecialchars($meta['desc']); ?></div>
                        <div class="rc-modules">
                            <?php foreach (array_slice($meta['modules'], 0, 5) as $mod): ?>
                                <span class="rc-mod-tag"><?php echo htmlspecialchars($mod); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($meta['modules']) > 5): ?>
                                <span class="rc-mod-tag">+<?php echo count($meta['modules']) - 5; ?> more</span>
                            <?php endif; ?>
                        </div>
                        <div class="rc-actions">
                            <a class="rc-btn open" href="<?php echo htmlspecialchars($meta['path']); ?>"><i class="fas fa-arrow-up-right-from-square"></i> Launch</a>
                            <span class="rc-btn status <?php echo $exists ? 'ready' : 'scaffold'; ?>">
                                <i class="fas fa-<?php echo $exists ? 'circle-check' : 'hammer'; ?>"></i>
                                <?php echo $exists ? 'Active' : 'Coming Soon'; ?>
                            </span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <!-- Info Panels -->
    <div class="panels-grid">
        <!-- Recent Activity -->
        <div class="info-panel">
            <h3><i class="fas fa-clock-rotate-left"></i> Recent User Activity</h3>
            <?php if (empty($recent_users)): ?>
                <p style="color:#64748b;font-size:.85rem">No recent activity recorded.</p>
            <?php else: ?>
                <?php
                $role_colors = ['admin'=>'#ef4444','teacher'=>'#0ea5e9','student'=>'#10b981','parent'=>'#f59e0b','librarian'=>'#8b5cf6','bursar'=>'#14b8a6','accountant'=>'#6366f1','transport'=>'#f97316','forum_moderator'=>'#06b6d4'];
                foreach ($recent_users as $ru):
                    $rc = $role_colors[$ru['role']] ?? '#64748b';
                    $initials = strtoupper(substr($ru['first_name'] ?? 'U', 0, 1) . substr($ru['last_name'] ?? '', 0, 1));
                    $last_login = $ru['last_login'] ? date('M j, g:ia', strtotime($ru['last_login'])) : 'Never';
                ?>
                <div class="act-row">
                    <div class="act-avatar" style="background:<?php echo $rc; ?>"><?php echo $initials; ?></div>
                    <div class="act-info">
                        <div class="act-name"><?php echo htmlspecialchars(($ru['first_name'] ?? '') . ' ' . ($ru['last_name'] ?? '')); ?></div>
                        <div class="act-meta"><?php echo $last_login; ?></div>
                    </div>
                    <span class="act-badge" style="background:<?php echo $rc; ?>22;color:<?php echo $rc; ?>"><?php echo ucfirst($ru['role']); ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- System Info -->
        <div class="info-panel">
            <h3><i class="fas fa-server"></i> System Information</h3>
            <div class="sys-row"><span class="sys-k">Database</span><span class="sys-v"><span class="sys-dot <?php echo $db_status === 'Connected' ? 'green' : 'red'; ?>"></span><?php echo $db_status; ?></span></div>
            <div class="sys-row"><span class="sys-k">MySQL Version</span><span class="sys-v"><?php echo htmlspecialchars($db_version ?: 'N/A'); ?></span></div>
            <div class="sys-row"><span class="sys-k">PHP Version</span><span class="sys-v"><?php echo htmlspecialchars($php_ver); ?></span></div>
            <div class="sys-row"><span class="sys-k">Server Time</span><span class="sys-v"><?php echo $server_time; ?></span></div>
            <div class="sys-row"><span class="sys-k">Application</span><span class="sys-v"><?php echo APP_NAME; ?> v<?php echo APP_VERSION; ?></span></div>
            <div class="sys-row"><span class="sys-k">Classes</span><span class="sys-v"><?php echo number_format($stats['classes']); ?></span></div>
            <div class="sys-row"><span class="sys-k">Messages</span><span class="sys-v"><?php echo number_format($stats['messages']); ?></span></div>
            <div class="sys-row"><span class="sys-k">Tenants</span><span class="sys-v"><?php echo number_format($stats['tenants']); ?> institution<?php echo $stats['tenants'] !== 1 ? 's' : ''; ?></span></div>
            <div class="sys-row"><span class="sys-k">Total Users</span><span class="sys-v"><?php echo number_format($stats['users_total']); ?></span></div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="cmd-footer">
        <p><?php echo APP_NAME; ?> &copy; <?php echo date('Y'); ?> &bull; <a href="index.php">Landing Page</a> &bull; <a href="login.php">Sign In</a> &bull; Multi-Tenant SaaS Platform</p>
    </footer>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
