<?php

/**
 * Teacher - Behavior Logging System
 * Track student behavior incidents
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/database.php';
require_teacher();

$user_id = $_SESSION['user_id'];
$page_title = "Behavior Logs";

// Handle log submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $severity = $_POST['severity'] ?? 'minor';
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['incident_description'] ?? '');
    $action = trim($_POST['action_taken'] ?? '');
    $date = $_POST['incident_date'] ?? date('Y-m-d');
    $time = $_POST['incident_time'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $notify_parent = isset($_POST['parent_notified']) ? 1 : 0;

    if ($student_id && $type && $description) {
        db()->execute("
            INSERT INTO behavior_logs (student_id, teacher_id, type, severity, category, incident_description, action_taken, incident_date, incident_time, location, parent_notified)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [$student_id, $user_id, $type, $severity, $category, $description, $action, $date, $time, $location, $notify_parent]);

        $_SESSION['success_message'] = "Behavior log recorded successfully";
        header("Location: behavior-logs.php");
        exit;
    }
}

// Get teacher's classes and students
$students = db()->fetchAll("
    SELECT DISTINCT s.id, u.first_name, u.last_name, s.grade, s.section
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    JOIN students s ON ce.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE c.teacher_id = ?
    ORDER BY u.last_name, u.first_name
", [$user_id]);

// Get recent logs
$logs = db()->fetchAll("
    SELECT bl.*, u.first_name, u.last_name, s.grade, s.section
    FROM behavior_logs bl
    JOIN students s ON bl.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE bl.teacher_id = ?
    ORDER BY bl.incident_date DESC, bl.created_at DESC
    LIMIT 50
", [$user_id]);

include '../includes/cyber-header.php';
include '../includes/sidebar-nav.php';
?>

<div class="app-layout">
    <div class="content-header">
        <h1><i class="fas fa-clipboard-list"></i> Behavior Logs</h1>
        <p class="subtitle">Track student behavior incidents</p>
        <button onclick="document.getElementById('logModal').style.display='flex'" class="cyber-btn">
            <i class="fas fa-plus"></i> New Log
        </button>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Recent Logs -->
    <div class="holo-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-history"></i> Recent Behavior Logs (<?php echo count($logs); ?>)</div>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard" style="font-size: 4rem; opacity: 0.3;"></i>
                    <h3>No behavior logs yet</h3>
                    <p>Record student behavior incidents here</p>
                </div>
            <?php else: ?>
                <div class="logs-list">
                    <?php foreach ($logs as $log): ?>
                        <div class="log-item type-<?php echo $log['type']; ?> severity-<?php echo $log['severity']; ?>">
                            <div class="log-icon">
                                <?php if ($log['type'] === 'positive'): ?>
                                    <i class="fas fa-smile"></i>
                                <?php elseif ($log['type'] === 'negative'): ?>
                                    <i class="fas fa-frown"></i>
                                <?php else: ?>
                                    <i class="fas fa-meh"></i>
                                <?php endif; ?>
                            </div>
                            <div class="log-content">
                                <div class="log-header">
                                    <div>
                                        <h4><?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></h4>
                                        <span class="student-info">Grade <?php echo $log['grade']; ?>-<?php echo $log['section']; ?></span>
                                    </div>
                                    <div class="log-badges">
                                        <span class="type-badge <?php echo $log['type']; ?>"><?php echo ucfirst($log['type']); ?></span>
                                        <span class="severity-badge <?php echo $log['severity']; ?>"><?php echo ucfirst($log['severity']); ?></span>
                                        <?php if ($log['category']): ?>
                                            <span class="category-badge"><?php echo htmlspecialchars($log['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="log-description">
                                    <strong>Incident:</strong> <?php echo nl2br(htmlspecialchars($log['incident_description'])); ?>
                                </div>
                                <?php if ($log['action_taken']): ?>
                                    <div class="log-action">
                                        <strong>Action Taken:</strong> <?php echo nl2br(htmlspecialchars($log['action_taken'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="log-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($log['incident_date'])); ?></span>
                                    <?php if ($log['incident_time']): ?>
                                        <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($log['incident_time'])); ?></span>
                                    <?php endif; ?>
                                    <?php if ($log['location']): ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($log['location']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($log['parent_notified']): ?>
                                        <span class="notified"><i class="fas fa-check-circle"></i> Parent Notified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Log Modal -->
<div id="logModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2><i class="fas fa-clipboard-list"></i> Record Behavior Log</h2>
            <button onclick="document.getElementById('logModal').style.display='none'" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Student <span class="required">*</span></label>
                    <select name="student_id" class="cyber-input" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                (Grade <?php echo $student['grade']; ?>-<?php echo $student['section']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Type <span class="required">*</span></label>
                        <select name="type" class="cyber-input" required>
                            <option value="positive">Positive</option>
                            <option value="negative">Negative</option>
                            <option value="neutral">Neutral</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Severity <span class="required">*</span></label>
                        <select name="severity" class="cyber-input" required>
                            <option value="minor">Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="major">Major</option>
                            <option value="severe">Severe</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" class="cyber-input" placeholder="e.g., Participation, Conduct, Academic">
                </div>

                <div class="form-group">
                    <label>Incident Description <span class="required">*</span></label>
                    <textarea name="incident_description" class="cyber-input" rows="4" required></textarea>
                </div>

                <div class="form-group">
                    <label>Action Taken</label>
                    <textarea name="action_taken" class="cyber-input" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Incident Date</label>
                        <input type="date" name="incident_date" class="cyber-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Incident Time</label>
                        <input type="time" name="incident_time" class="cyber-input">
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="cyber-input" placeholder="e.g., Classroom, Cafeteria, Hallway">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="parent_notified">
                        <span>Parent has been notified</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-save"></i> Save Log
                    </button>
                    <button type="button" onclick="document.getElementById('logModal').style.display='none'" class="cyber-btn-outline">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .logs-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .log-item {
        display: flex;
        gap: 20px;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid;
    }

    .log-item.type-positive {
        background: rgba(16, 185, 129, 0.05);
        border-left-color: var(--success-color);
    }

    .log-item.type-negative {
        background: rgba(239, 68, 68, 0.05);
        border-left-color: var(--danger-color);
    }

    .log-item.type-neutral {
        background: rgba(0, 243, 255, 0.05);
        border-left-color: var(--cyber-cyan);
    }

    .log-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .type-positive .log-icon {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success-color);
    }

    .type-negative .log-icon {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger-color);
    }

    .type-neutral .log-icon {
        background: rgba(0, 243, 255, 0.2);
        color: var(--cyber-cyan);
    }

    .log-content {
        flex: 1;
    }

    .log-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .log-header h4 {
        margin: 0;
        color: var(--cyber-cyan);
    }

    .student-info {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .log-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .type-badge,
    .severity-badge,
    .category-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .type-badge.positive {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success-color);
    }

    .type-badge.negative {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger-color);
    }

    .type-badge.neutral {
        background: rgba(107, 114, 128, 0.2);
        color: var(--text-muted);
    }

    .severity-badge.minor {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }

    .severity-badge.moderate {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning-color);
    }

    .severity-badge.major,
    .severity-badge.severe {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger-color);
    }

    .category-badge {
        background: rgba(0, 243, 255, 0.2);
        color: var(--cyber-cyan);
    }

    .log-description,
    .log-action {
        margin: 10px 0;
        padding: 10px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 6px;
    }

    .log-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-top: 12px;
    }

    .notified {
        color: var(--success-color);
        font-weight: 600;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
</style>

<?php include '../includes/cyber-footer.php'; ?>

