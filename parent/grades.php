<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent('../login.php');

// Get parent's linked children
$children = db()->fetchAll("
    SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as child_name
    FROM guardians g
    LEFT JOIN students s ON g.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE g.parent_id = (SELECT id FROM users WHERE id = ?)
", [$_SESSION['user_id']]);

// Get selected child
$selected_child_id = isset($_GET['child']) ? (int)$_GET['child'] : (count($children) > 0 ? $children[0]['id'] : null);
$selected_child = null;

if ($selected_child_id) {
    foreach ($children as $child) {
        if ($child['id'] == $selected_child_id) {
            $selected_child = $child;
            break;
        }
    }
}

// Get grades for selected child
$grades = [];
$overall_percentage = 0;
$overall_letter = 'N/A';

if ($selected_child) {
    $grades = db()->fetchAll("
        SELECT g.*, a.title as assignment_title, a.assignment_type,
               c.class_name, c.grade as class_grade,
               CONCAT(u.first_name, ' ', u.last_name) as teacher_name
        FROM grades g
        LEFT JOIN assignments a ON g.assignment_id = a.id
        LEFT JOIN classes c ON g.class_id = c.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE g.student_id = ?
        ORDER BY g.grade_date DESC
    ", [$selected_child['id']]);

    // Calculate overall
    $total_points = array_sum(array_column($grades, 'max_points'));
    $earned_points = array_sum(array_column($grades, 'points_earned'));

    if ($total_points > 0) {
        $overall_percentage = ($earned_points / $total_points) * 100;
        if ($overall_percentage >= 90) $overall_letter = 'A';
        elseif ($overall_percentage >= 80) $overall_letter = 'B';
        elseif ($overall_percentage >= 70) $overall_letter = 'C';
        elseif ($overall_percentage >= 60) $overall_letter = 'D';
        else $overall_letter = 'F';
    }
}

$page_title = "Children's Grades";
$page_icon = 'chart-bar';
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
                            <div class="user-role">Parent</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="app-layout">
                <?php if (empty($children)): ?>
                    <div class="holo-card">
                        <div class="card-body" style="text-align:center;padding:40px;">
                            <i class="fas fa-user-friends" style="font-size:3rem;color:rgba(255,255,255,0.3);margin-bottom:15px;"></i>
                            <div style="color:rgba(255,255,255,0.6);">No children linked to your account</div>
                            <div style="color:rgba(255,255,255,0.4);font-size:0.9rem;margin-top:10px;">Please contact the administrator</div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Child Selection -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-child"></i> <span>Select Child</span></div>
                        </div>
                        <div class="card-body">
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:15px;">
                                <?php foreach ($children as $child): ?>
                                    <a href="?child=<?php echo $child['id']; ?>" class="cyber-btn <?php echo $selected_child_id === $child['id'] ? 'primary' : ''; ?>" style="display:block;padding:15px;text-align:center;">
                                        <div style="font-weight:700;margin-bottom:5px;"><?php echo htmlspecialchars($child['child_name']); ?></div>
                                        <div style="font-size:0.85rem;opacity:0.8;">Grade <?php echo $child['grade']; ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($selected_child): ?>
                        <!-- Statistics -->
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:25px;">
                            <div class="holo-card" style="background:linear-gradient(135deg,rgba(0,191,255,0.1),rgba(138,43,226,0.1));">
                                <div class="card-body" style="text-align:center;padding:25px;">
                                    <div style="font-size:3rem;font-weight:700;color:var(--cyber-cyan);margin-bottom:5px;"><?php echo number_format($overall_percentage, 1); ?>%</div>
                                    <div style="font-size:1.5rem;color:var(--cyber-pink);font-weight:700;margin-bottom:5px;"><?php echo $overall_letter; ?></div>
                                    <div style="color:rgba(255,255,255,0.6);">Overall Grade</div>
                                </div>
                            </div>
                            <div class="holo-card">
                                <div class="card-body" style="text-align:center;padding:25px;">
                                    <i class="fas fa-clipboard-check" style="font-size:2rem;color:var(--cyber-cyan);margin-bottom:10px;"></i>
                                    <div style="font-size:2rem;font-weight:700;color:var(--cyber-cyan);margin-bottom:5px;"><?php echo count($grades); ?></div>
                                    <div style="color:rgba(255,255,255,0.6);">Total Grades</div>
                                </div>
                            </div>
                        </div>

                        <!-- Grades List -->
                        <div class="holo-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fas fa-list-alt"></i>
                                    <span><?php echo htmlspecialchars($selected_child['child_name']); ?>'s Grades</span>
                                </div>
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
                                                            Teacher: <?php echo htmlspecialchars($grade['teacher_name']); ?>
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
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>