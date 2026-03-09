<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student('../login.php');

// Get student info
$student = db()->fetchOne("
    SELECT * FROM students WHERE user_id = ?
", [$_SESSION['user_id']]);

// Get events for students or all users
$events = db()->fetchAll("
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) as organizer_name
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.id
    WHERE (e.target_audience = 'students' OR e.target_audience = 'all')
    AND e.status = 'scheduled'
    AND e.start_date >= CURDATE() - INTERVAL 30 DAY
    ORDER BY e.start_date ASC
");

$page_title = 'School Events';
$page_icon = 'calendar-check';
$full_name = $_SESSION['full_name'];
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
    
</head>
<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Student</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="app-layout">
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-calendar-alt"></i> <span>Upcoming Events</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events)): ?>
                            <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                <i class="fas fa-calendar-times" style="font-size:3rem;margin-bottom:15px;"></i>
                                <div>No upcoming events</div>
                            </div>
                        <?php else: ?>
                            <div style="display:grid;gap:20px;">
                                <?php
                                $today = date('Y-m-d');
                                foreach ($events as $event):
                                    $event_date = date('Y-m-d', strtotime($event['start_date']));
                                    $is_today = $event_date === $today;
                                    $is_past = $event_date < $today;
                                ?>
                                    <div style="background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border:1px solid <?php echo $is_today ? 'var(--cyber-pink)' : 'rgba(0,191,255,0.2)'; ?>;border-radius:12px;padding:20px;<?php echo $is_past ? 'opacity:0.6;' : ''; ?>">
                                        <div style="display:flex;gap:20px;">
                                            <div style="text-align:center;min-width:80px;padding:15px;background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));border:1px solid var(--cyber-cyan);border-radius:10px;">
                                                <div style="font-size:2rem;font-weight:700;color:var(--cyber-cyan);"><?php echo date('d', strtotime($event['start_date'])); ?></div>
                                                <div style="font-size:0.85rem;color:var(--cyber-pink);font-weight:600;"><?php echo strtoupper(date('M', strtotime($event['start_date']))); ?></div>
                                                <div style="font-size:0.75rem;color:rgba(255,255,255,0.5);"><?php echo date('Y', strtotime($event['start_date'])); ?></div>
                                            </div>
                                            <div style="flex:1;">
                                                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:10px;">
                                                    <h3 style="color:var(--cyber-cyan);font-size:1.3rem;"><?php echo htmlspecialchars($event['title']); ?></h3>
                                                    <?php if ($is_today): ?>
                                                        <span style="padding:5px 12px;background:rgba(255,20,147,0.2);border:1px solid var(--cyber-pink);border-radius:15px;font-size:0.75rem;font-weight:700;color:var(--cyber-pink);">TODAY</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($event['description'])): ?>
                                                    <p style="color:rgba(255,255,255,0.7);margin-bottom:15px;"><?php echo htmlspecialchars($event['description']); ?></p>
                                                <?php endif; ?>
                                                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                    <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                        <i class="fas fa-tag"></i> <?php echo ucfirst($event['event_type']); ?>
                                                    </span>
                                                    <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($event['start_date'])); ?>
                                                    </span>
                                                    <?php if (!empty($event['location'])): ?>
                                                        <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($event['organizer_name']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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