<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Timetable Management';
$page_icon = 'calendar-alt';
$full_name = $_SESSION['full_name'];

// Get all classes with schedule information
$classes = db()->fetchAll(
    "SELECT c.*, t.first_name as teacher_first, t.last_name as teacher_last,
            COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN teachers t ON c.class_teacher_id = t.id
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id AND ce.status = 'active'
    GROUP BY c.id
    ORDER BY c.day_of_week, c.start_time"
);

// Organize by day of week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$schedule = [];
foreach ($days as $index => $day) {
    $schedule[$day] = [];
}

foreach ($classes as $class) {
    if ($class['day_of_week'] >= 1 && $class['day_of_week'] <= 7) {
        $schedule[$days[$class['day_of_week'] - 1]][] = $class;
    }
}

// Get statistics
$total_classes = count($classes);
$classes_today = db()->count('classes', 'day_of_week = ?', ['day_of_week' => date('N')]);
$active_teachers = db()->count('teachers', 'status = ?', ['status' => 'active']);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">

    <style>
        .timetable-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .day-card {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.05), rgba(255, 0, 255, 0.05));
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
        }

        .day-card.today {
            border: 2px solid var(--cyber-cyan);
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.3);
        }

        .day-header {
            color: var(--cyber-cyan);
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .class-entry {
            background: rgba(0, 255, 255, 0.05);
            border-left: 3px solid var(--neon-green);
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
        }

        .class-time {
            color: var(--golden-pulse);
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .class-name {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .class-teacher {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
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
                    <div class="biometric-orb" title="Quick Scan" onclick="window.location.href='biometric-scan.php'">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <!-- Statistics -->
                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_classes); ?></div>
                            <div class="stat-label">Total Classes</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($classes_today); ?></div>
                            <div class="stat-label">Classes Today</div>
                            <div class="stat-trend up"><i class="fas fa-check"></i><span><?php echo $days[date('N') - 1]; ?></span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($active_teachers); ?></div>
                            <div class="stat-label">Active Teachers</div>
                        </div>
                    </div>
                </section>

                <!-- Weekly Timetable -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-calendar-week"></i>
                            <span>Weekly Schedule</span>
                        </div>
                        <a href="classes.php" class="cyber-btn primary">
                            <i class="fas fa-plus"></i> Add Class
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="timetable-grid">
                            <?php foreach ($days as $day):
                                $isToday = $days[date('N') - 1] === $day;
                            ?>
                                <div class="day-card <?php echo $isToday ? 'today' : ''; ?>">
                                    <div class="day-header">
                                        <span>
                                            <i class="fas fa-calendar"></i> <?php echo $day; ?>
                                        </span>
                                        <?php if ($isToday): ?>
                                            <span class="cyber-badge success">Today</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (empty($schedule[$day])): ?>
                                        <p style="color: var(--text-muted); text-align: center; padding: 20px;">
                                            No classes scheduled
                                        </p>
                                    <?php else: ?>
                                        <?php foreach ($schedule[$day] as $class): ?>
                                            <div class="class-entry">
                                                <div class="class-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?php echo date('h:i A', strtotime($class['start_time'])); ?>
                                                    -
                                                    <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                                </div>
                                                <div class="class-name">
                                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                                    <span class="cyber-badge secondary" style="margin-left: 8px;">
                                                        <?php echo htmlspecialchars($class['class_code']); ?>
                                                    </span>
                                                </div>
                                                <div class="class-teacher">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($class['teacher_first'] . ' ' . $class['teacher_last']); ?>
                                                    <span style="margin-left: 10px;">
                                                        <i class="fas fa-users"></i> <?php echo $class['student_count']; ?> students
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
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
