<?php

/**
 * Parent Events & Calendar System
 * School events, family calendar, RSVPs, reminders, multi-child consolidated view
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Handle RSVP submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp'])) {
    $event_id = (int)$_POST['event_id'];
    $child_id = (int)$_POST['child_id'];
    $rsvp_status = sanitize($_POST['rsvp_status']);

    // Check if RSVP exists
    $existing = db()->fetchOne("SELECT id FROM event_rsvps WHERE event_id = ? AND student_id = ?", [$event_id, $child_id]);

    if ($existing) {
        db()->update('event_rsvps', $existing['id'], [
            'rsvp_status' => $rsvp_status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        db()->insert('event_rsvps', [
            'event_id' => $event_id,
            'student_id' => $child_id,
            'rsvp_status' => $rsvp_status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    log_activity($parent_id, 'event_rsvp', 'event_rsvps', $event_id, "RSVP: $rsvp_status for child $child_id");

    header('Location: events.php?success=rsvp');
    exit;
}

// Get all linked children
$children = db()->fetchAll("
    SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) as child_name
    FROM parent_student_links psl
    JOIN students s ON psl.student_id = s.user_id
    JOIN users u ON s.user_id = u.id
    WHERE psl.parent_id = ? AND u.status = 'active'
", [$parent_id]);

// Get view mode (calendar or list)
$view = isset($_GET['view']) ? $_GET['view'] : 'upcoming';
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get events based on view
$events = [];
if ($view === 'upcoming') {
    $events = db()->fetchAll("
        SELECT e.*,
               GROUP_CONCAT(DISTINCT c.class_name SEPARATOR ', ') as classes,
               (SELECT COUNT(*) FROM event_rsvps er
                JOIN students s ON er.student_id = s.id
                JOIN parent_student_links psl ON s.user_id = psl.student_id
                WHERE er.event_id = e.id AND psl.parent_id = ?) as child_rsvps
        FROM events e
        LEFT JOIN event_classes ec ON e.id = ec.event_id
        LEFT JOIN classes c ON ec.class_id = c.id
        WHERE e.event_date >= CURDATE()
        GROUP BY e.id
        ORDER BY e.event_date ASC, e.event_time ASC
        LIMIT 50
    ", [$parent_id]);
} elseif ($view === 'month') {
    $month_start = $selected_month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));

    $events = db()->fetchAll("
        SELECT e.*,
               GROUP_CONCAT(DISTINCT c.class_name SEPARATOR ', ') as classes,
               (SELECT COUNT(*) FROM event_rsvps er
                JOIN students s ON er.student_id = s.id
                JOIN parent_student_links psl ON s.user_id = psl.student_id
                WHERE er.event_id = e.id AND psl.parent_id = ?) as child_rsvps
        FROM events e
        LEFT JOIN event_classes ec ON e.id = ec.event_id
        LEFT JOIN classes c ON ec.class_id = c.id
        WHERE e.event_date BETWEEN ? AND ?
        GROUP BY e.id
        ORDER BY e.event_date ASC, e.event_time ASC
    ", [$parent_id, $month_start, $month_end]);
}

// Get RSVP status for each child for each event
foreach ($events as &$event) {
    $event['child_rsvps'] = [];
    foreach ($children as $child) {
        $rsvp = db()->fetchOne("
            SELECT rsvp_status FROM event_rsvps
            WHERE event_id = ? AND student_id = ?
        ", [$event['id'], $child['id']]);

        $event['child_rsvps'][$child['id']] = [
            'name' => $child['child_name'],
            'status' => $rsvp['rsvp_status'] ?? 'pending'
        ];
    }
}

// Calculate statistics
$stats = [
    'upcoming' => db()->fetchOne("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()")['count'] ?? 0,
    'attending' => db()->fetchOne("
        SELECT COUNT(DISTINCT e.id) as count
        FROM events e
        JOIN event_rsvps er ON e.id = er.event_id
        JOIN students s ON er.student_id = s.id
        JOIN parent_student_links psl ON s.user_id = psl.student_id
        WHERE psl.parent_id = ? AND er.rsvp_status = 'attending' AND e.event_date >= CURDATE()
    ", [$parent_id])['count'] ?? 0,
    'this_month' => count($events)
];

$page_title = 'Family Events Calendar';
$page_icon = 'calendar-alt';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">

</head>
<body class="cyber-bg">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

    <div class="cyber-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div style="display:flex;gap:10px;">
                        <a href="?view=upcoming" class="cyber-btn <?php echo $view === 'upcoming' ? 'primary' : ''; ?>">
                            <i class="fas fa-list"></i> Upcoming
                        </a>
                        <a href="?view=month" class="cyber-btn <?php echo $view === 'month' ? 'primary' : ''; ?>">
                            <i class="fas fa-calendar"></i> Month View
                        </a>
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <?php if (isset($_GET['success'])): ?>
                    <div class="cyber-alert success">
                        <i class="fas fa-check-circle"></i>
                        <span>RSVP updated successfully!</span>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb primary">
                        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                        <div class="stat-value"><?php echo $stats['upcoming']; ?></div>
                        <div class="stat-label">Upcoming Events</div>
                    </div>
                    <div class="stat-orb success">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value"><?php echo $stats['attending']; ?></div>
                        <div class="stat-label">Attending</div>
                    </div>
                    <div class="stat-orb warning">
                        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-value"><?php echo $stats['this_month']; ?></div>
                        <div class="stat-label">This Period</div>
                    </div>
                </div>

                <?php if ($view === 'month'): ?>
                    <!-- Month Selector -->
                    <div class="holo-card" style="margin-bottom:25px;">
                        <div style="display:flex;justify-content:center;align-items:center;gap:20px;padding:15px;">
                            <a href="?view=month&month=<?php echo date('Y-m', strtotime($selected_month . '-01 -1 month')); ?>" class="cyber-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            <input type="month" value="<?php echo $selected_month; ?>" onchange="window.location.href='?view=month&month='+this.value" class="cyber-input" style="width:auto;text-align:center;">
                            <a href="?view=month&month=<?php echo date('Y-m', strtotime($selected_month . '-01 +1 month')); ?>" class="cyber-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Events List -->
                <?php if (empty($events)): ?>
                    <div class="holo-card">
                        <div style="text-align:center;padding:60px;">
                            <i class="fas fa-calendar-times" style="font-size:4rem;color:rgba(255,255,255,0.2);margin-bottom:20px;"></i>
                            <h3 style="color:rgba(255,255,255,0.6);margin-bottom:10px;">No Events Found</h3>
                            <p style="color:rgba(255,255,255,0.4);">There are no events scheduled for this period</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="display:grid;gap:20px;">
                        <?php foreach ($events as $event):
                            $event_date = strtotime($event['event_date'] . ' ' . ($event['event_time'] ?? '00:00:00'));
                            $is_today = date('Y-m-d', $event_date) === date('Y-m-d');
                            $is_tomorrow = date('Y-m-d', $event_date) === date('Y-m-d', strtotime('+1 day'));
                        ?>
                            <div class="holo-card" style="<?php echo $is_today ? 'border-left:4px solid #00ff7f;' : ($is_tomorrow ? 'border-left:4px solid #ffff00;' : ''); ?>">
                                <div style="display:flex;gap:25px;align-items:start;">
                                    <!-- Date Badge -->
                                    <div style="text-align:center;padding:15px;background:linear-gradient(135deg,rgba(0,191,255,0.2),rgba(138,43,226,0.2));border:1px solid var(--cyber-cyan);border-radius:12px;min-width:100px;">
                                        <div style="font-size:2rem;font-weight:700;color:var(--cyber-cyan);"><?php echo date('d', $event_date); ?></div>
                                        <div style="font-size:0.9rem;color:rgba(255,255,255,0.7);text-transform:uppercase;"><?php echo date('M', $event_date); ?></div>
                                        <?php if ($event['event_time']): ?>
                                            <div style="margin-top:8px;font-size:0.85rem;color:var(--cyber-pink);">
                                                <i class="fas fa-clock"></i> <?php echo date('g:i A', $event_date); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Event Details -->
                                    <div style="flex:1;">
                                        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                                            <h3 style="color:var(--cyber-cyan);font-size:1.3rem;margin:0;">
                                                <?php echo htmlspecialchars($event['title']); ?>
                                                <?php if ($is_today): ?>
                                                    <span class="cyber-badge success" style="margin-left:10px;">TODAY</span>
                                                <?php elseif ($is_tomorrow): ?>
                                                    <span class="cyber-badge warning" style="margin-left:10px;">TOMORROW</span>
                                                <?php endif; ?>
                                            </h3>
                                            <span class="cyber-badge <?php echo $event['event_type'] === 'mandatory' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($event['event_type']); ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($event['description'])): ?>
                                            <p style="color:rgba(255,255,255,0.7);margin-bottom:15px;line-height:1.6;">
                                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                            </p>
                                        <?php endif; ?>

                                        <div style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:15px;">
                                            <?php if ($event['location']): ?>
                                                <div style="display:flex;align-items:center;gap:8px;color:rgba(255,255,255,0.6);">
                                                    <i class="fas fa-map-marker-alt" style="color:var(--cyber-cyan);"></i>
                                                    <?php echo htmlspecialchars($event['location']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($event['classes']): ?>
                                                <div style="display:flex;align-items:center;gap:8px;color:rgba(255,255,255,0.6);">
                                                    <i class="fas fa-door-open" style="color:var(--cyber-cyan);"></i>
                                                    <?php echo htmlspecialchars($event['classes']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Child RSVPs -->
                                        <?php if (!empty($children)): ?>
                                            <div style="background:rgba(0,191,255,0.05);border:1px solid rgba(0,191,255,0.2);border-radius:8px;padding:15px;">
                                                <h4 style="margin-bottom:12px;color:var(--cyber-cyan);font-size:0.95rem;">
                                                    <i class="fas fa-child"></i> Family RSVP
                                                </h4>
                                                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;">
                                                    <?php foreach ($event['child_rsvps'] as $child_id => $rsvp): ?>
                                                        <div style="padding:12px;background:rgba(0,0,0,0.3);border-radius:6px;">
                                                            <div style="font-weight:600;margin-bottom:8px;color:rgba(255,255,255,0.9);">
                                                                <?php echo htmlspecialchars($rsvp['name']); ?>:
                                                            </div>
                                                            <form method="POST" style="display:flex;gap:5px;">
                                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                                <input type="hidden" name="child_id" value="<?php echo $child_id; ?>">
                                                                <button type="submit" name="rsvp" value="attending" class="cyber-btn btn-sm <?php echo $rsvp['status'] === 'attending' ? 'success' : ''; ?>" style="flex:1;">
                                                                    <i class="fas fa-check"></i> Attending
                                                                </button>
                                                                <button type="submit" name="rsvp" value="not_attending" class="cyber-btn btn-sm <?php echo $rsvp['status'] === 'not_attending' ? 'danger' : ''; ?>" style="flex:1;">
                                                                    <i class="fas fa-times"></i> Not Attending
                                                                </button>
                                                                <input type="hidden" name="rsvp_status" value="">
                                                                <script>
                                                                    document.querySelectorAll('button[name="rsvp"]').forEach(btn => {
                                                                        btn.onclick = function(e) {
                                                                            this.form.querySelector('[name="rsvp_status"]').value = this.value;
                                                                        };
                                                                    });
                                                                </script>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
