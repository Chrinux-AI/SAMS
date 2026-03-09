<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student('../login.php');

// Get student info
$student = db()->fetchOne("SELECT * FROM students WHERE user_id = ?", [$_SESSION['user_id']]);

// Get all grades for this student with class and assignment details
$grades = db()->fetchAll("
    SELECT g.*, a.title as assignment_title, a.assignment_type, a.max_points,
           c.class_name, c.grade as class_grade,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM grades g
    LEFT JOIN assignments a ON g.assignment_id = a.id
    LEFT JOIN classes c ON g.class_id = c.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE g.student_id = ?
    ORDER BY g.grade_date DESC
", [$student['id']]);

// Calculate GPA and statistics
$total_points = 0;
$earned_points = 0;
$grade_counts = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];

foreach ($grades as $grade) {
    $total_points += $grade['max_points'];
    $earned_points += $grade['points_earned'];

    if ($grade['letter_grade']) {
        $letter = substr($grade['letter_grade'], 0, 1);
        if (isset($grade_counts[$letter])) {
            $grade_counts[$letter]++;
        }
    }
}

$overall_percentage = $total_points > 0 ? ($earned_points / $total_points) * 100 : 0;

// Calculate letter grade for overall
function get_letter_grade($percentage)
{
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}

$overall_letter = get_letter_grade($overall_percentage);

$page_title = 'My Grades';
$page_icon = 'chart-line';
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
                <!-- Overall Statistics -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:25px;">
                    <div class="holo-card" style="background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));">
                        <div class="card-body" style="text-align:center;padding:25px;">
                            <div style="font-size:3rem;font-weight:700;color:var(--cyber-cyan);margin-bottom:5px;"><?php echo number_format($overall_percentage, 1); ?>%</div>
                            <div style="font-size:1.5rem;color:var(--cyber-pink);font-weight:700;margin-bottom:5px;"><?php echo $overall_letter; ?></div>
                            <div style="color:rgba(255,255,255,0.6);font-size:0.9rem;">Overall Grade</div>
                        </div>
                    </div>
                    <div class="holo-card">
                        <div class="card-body" style="text-align:center;padding:25px;">
                            <i class="fas fa-clipboard-check" style="font-size:2rem;color:var(--cyber-cyan);margin-bottom:10px;"></i>
                            <div style="font-size:2rem;font-weight:700;color:var(--cyber-cyan);margin-bottom:5px;"><?php echo count($grades); ?></div>
                            <div style="color:rgba(255,255,255,0.6);font-size:0.9rem;">Total Grades</div>
                        </div>
                    </div>
                    <div class="holo-card">
                        <div class="card-body" style="padding:20px;">
                            <div style="color:var(--cyber-cyan);font-weight:700;margin-bottom:15px;text-align:center;">Grade Distribution</div>
                            <?php foreach ($grade_counts as $letter => $count): ?>
                                <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                                    <span><?php echo $letter; ?>:</span>
                                    <span style="font-weight:700;color:var(--cyber-cyan);"><?php echo $count; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-list-alt"></i> <span>All Grades</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($grades)): ?>
                            <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                <i class="fas fa-chart-line" style="font-size:3rem;margin-bottom:15px;"></i>
                                <div>No grades recorded yet</div>
                            </div>
                        <?php else: ?>
                            <div style="display:grid;gap:15px;">
                                <?php foreach ($grades as $grade): ?>
                                    <div style="background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border:1px solid rgba(0,191,255,0.2);border-radius:12px;padding:20px;">
                                        <div style="display:flex;justify-content:space-between;align-items:start;">
                                            <div style="flex:1;">
                                                <h3 style="color:var(--cyber-cyan);font-size:1.2rem;margin-bottom:5px;"><?php echo htmlspecialchars($grade['assignment_title'] ?? 'General Grade'); ?></h3>
                                                <div style="color:rgba(255,255,255,0.6);margin-bottom:10px;">
                                                    <?php echo htmlspecialchars($grade['class_name']); ?> •
                                                    <?php echo htmlspecialchars($grade['teacher_name']); ?>
                                                </div>
                                                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                    <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($grade['grade_date'])); ?>
                                                    </span>
                                                    <?php if ($grade['assignment_type']): ?>
                                                        <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                            <i class="fas fa-tag"></i> <?php echo ucfirst($grade['assignment_type']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="text-align:center;padding:20px;background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));border:1px solid var(--cyber-cyan);border-radius:12px;min-width:120px;">
                                                <div style="font-size:2.5rem;font-weight:700;color:var(--cyber-pink);"><?php echo $grade['letter_grade']; ?></div>
                                                <div style="font-size:1.2rem;color:var(--cyber-cyan);margin-top:5px;"><?php echo $grade['points_earned']; ?>/<?php echo $grade['max_points']; ?></div>
                                                <div style="font-size:0.9rem;color:rgba(255,255,255,0.5);"><?php echo number_format($grade['percentage'], 1); ?>%</div>
                                            </div>
                                        </div>
                                        <?php if (!empty($grade['comments'])): ?>
                                            <div style="margin-top:15px;padding:12px;background:rgba(138,43,226,0.1);border-left:3px solid var(--cyber-pink);border-radius:5px;">
                                                <strong style="color:var(--cyber-pink);">Teacher Comments:</strong>
                                                <div style="margin-top:5px;color:rgba(255,255,255,0.8);"><?php echo htmlspecialchars($grade['comments']); ?></div>
                                            </div>
                                        <?php endif; ?>
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