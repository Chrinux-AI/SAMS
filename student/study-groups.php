<?php

/**
 * Student - Study Group Finder
 * Find or create study groups
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/database.php';
require_student();

$user_id = $_SESSION['user_id'];
$page_title = "Study Groups";

// Get student ID
$student = db()->fetchOne("SELECT id FROM students WHERE user_id = ?", [$user_id]);
$student_id = $student['id'];

// Handle create group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $class_id = intval($_POST['class_id'] ?? 0);
    $max_members = intval($_POST['max_members'] ?? 5);
    $schedule = trim($_POST['meeting_schedule'] ?? '');

    if ($name && $class_id) {
        db()->execute("
            INSERT INTO study_groups (name, description, class_id, creator_id, max_members, meeting_schedule)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [$name, $description, $class_id, $user_id, $max_members, $schedule]);

        $group_id = db()->lastInsertId();

        // Auto-join creator
        db()->execute("
            INSERT INTO study_group_members (group_id, student_id, status)
            VALUES (?, ?, 'accepted')
        ", [$group_id, $student_id]);

        $_SESSION['success_message'] = "Study group created successfully!";
        header("Location: study-groups.php");
        exit;
    }
}

// Handle join request
if (isset($_GET['join'])) {
    $group_id = intval($_GET['join']);

    // Check if already member
    $existing = db()->fetchOne("SELECT * FROM study_group_members WHERE group_id = ? AND student_id = ?", [$group_id, $student_id]);

    if (!$existing) {
        db()->execute("
            INSERT INTO study_group_members (group_id, student_id, status)
            VALUES (?, ?, 'pending')
        ", [$group_id, $student_id]);

        $_SESSION['success_message'] = "Join request sent!";
    }
    header("Location: study-groups.php");
    exit;
}

// Get student's enrolled classes
$my_classes = db()->fetchAll("
    SELECT c.id, c.class_name, c.class_code
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    WHERE ce.student_id = ?
", [$student_id]);

// Get available study groups
$available_groups = db()->fetchAll("
    SELECT sg.*, c.class_name, c.class_code, u.first_name, u.last_name,
           COUNT(DISTINCT sgm.id) as member_count,
           (SELECT COUNT(*) FROM study_group_members WHERE group_id = sg.id AND student_id = ?) as is_member
    FROM study_groups sg
    JOIN classes c ON sg.class_id = c.id
    JOIN users u ON sg.creator_id = u.id
    LEFT JOIN study_group_members sgm ON sg.id = sgm.group_id AND sgm.status = 'accepted'
    WHERE sg.class_id IN (SELECT class_id FROM class_enrollments WHERE student_id = ?)
    AND sg.status = 'open'
    GROUP BY sg.id
    HAVING member_count < sg.max_members OR is_member > 0
    ORDER BY sg.created_at DESC
", [$student_id, $student_id]);

// Get my groups
$my_groups = db()->fetchAll("
    SELECT sg.*, c.class_name, c.class_code,
           COUNT(DISTINCT sgm.id) as member_count
    FROM study_groups sg
    JOIN classes c ON sg.class_id = c.id
    JOIN study_group_members sgm ON sg.id = sgm.group_id
    WHERE sgm.student_id = ? AND sgm.status = 'accepted'
    GROUP BY sg.id
    ORDER BY sg.created_at DESC
", [$student_id]);

include '../includes/cyber-header.php';
include '../includes/sidebar-nav.php';
?>

<div class="app-layout">
    <div class="content-header">
        <h1><i class="fas fa-users"></i> Study Groups</h1>
        <p class="subtitle">Find peers for collaborative learning</p>
        <button onclick="document.getElementById('createModal').style.display='flex'" class="cyber-btn">
            <i class="fas fa-plus"></i> Create Group
        </button>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <!-- My Groups -->
    <?php if (!empty($my_groups)): ?>
        <div class="holo-card" style="margin-bottom: 30px;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-friends"></i> My Groups (<?php echo count($my_groups); ?>)</div>
            </div>
            <div class="card-body">
                <div class="groups-grid">
                    <?php foreach ($my_groups as $group): ?>
                        <div class="group-card my-group">
                            <div class="group-header">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <span class="member-count"><?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?> members</span>
                            </div>
                            <p class="group-class"><?php echo htmlspecialchars($group['class_name']); ?> (<?php echo $group['class_code']; ?>)</p>
                            <?php if ($group['description']): ?>
                                <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                            <?php endif; ?>
                            <?php if ($group['meeting_schedule']): ?>
                                <div class="group-schedule">
                                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($group['meeting_schedule']); ?>
                                </div>
                            <?php endif; ?>
                            <a href="study-group-view.php?id=<?php echo $group['id']; ?>" class="cyber-btn-sm">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Available Groups -->
    <div class="holo-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-search"></i> Available Study Groups</div>
        </div>
        <div class="card-body">
            <?php if (empty($available_groups)): ?>
                <div class="empty-state">
                    <i class="fas fa-users" style="font-size: 4rem; opacity: 0.3;"></i>
                    <h3>No study groups available</h3>
                    <p>Be the first to create one for your class!</p>
                </div>
            <?php else: ?>
                <div class="groups-grid">
                    <?php foreach ($available_groups as $group): ?>
                        <div class="group-card">
                            <div class="group-header">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <span class="member-count"><?php echo $group['member_count']; ?>/<?php echo $group['max_members']; ?></span>
                            </div>
                            <p class="group-class"><?php echo htmlspecialchars($group['class_name']); ?> (<?php echo $group['class_code']; ?>)</p>
                            <p class="group-creator">Created by <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?></p>
                            <?php if ($group['description']): ?>
                                <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                            <?php endif; ?>
                            <?php if ($group['meeting_schedule']): ?>
                                <div class="group-schedule">
                                    <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($group['meeting_schedule']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($group['is_member'] > 0): ?>
                                <span class="joined-badge"><i class="fas fa-check-circle"></i> Already Joined</span>
                            <?php elseif ($group['member_count'] < $group['max_members']): ?>
                                <a href="?join=<?php echo $group['id']; ?>" class="cyber-btn-sm">
                                    <i class="fas fa-user-plus"></i> Request to Join
                                </a>
                            <?php else: ?>
                                <span class="full-badge"><i class="fas fa-users"></i> Group Full</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create Group Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-users"></i> Create Study Group</h2>
            <button onclick="document.getElementById('createModal').style.display='none'" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label>Group Name <span class="required">*</span></label>
                    <input type="text" name="name" class="cyber-input" placeholder="e.g., Math Study Group" required>
                </div>

                <div class="form-group">
                    <label>Class <span class="required">*</span></label>
                    <select name="class_id" class="cyber-input" required>
                        <option value="">Select class</option>
                        <?php foreach ($my_classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?> (<?php echo $class['class_code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="cyber-input" rows="3" placeholder="What will you study together?"></textarea>
                </div>

                <div class="form-group">
                    <label>Meeting Schedule</label>
                    <input type="text" name="meeting_schedule" class="cyber-input" placeholder="e.g., Tuesdays 3-5pm in Library">
                </div>

                <div class="form-group">
                    <label>Max Members</label>
                    <input type="number" name="max_members" class="cyber-input" value="5" min="2" max="10">
                </div>

                <div class="form-actions">
                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-plus"></i> Create Group
                    </button>
                    <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="cyber-btn-outline">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .groups-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .group-card {
        padding: 20px;
        background: rgba(0, 243, 255, 0.05);
        border: 1px solid rgba(0, 243, 255, 0.2);
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .group-card:hover {
        background: rgba(0, 243, 255, 0.1);
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 243, 255, 0.2);
    }

    .group-card.my-group {
        border-color: var(--success-color);
        background: rgba(16, 185, 129, 0.05);
    }

    .group-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .group-header h3 {
        margin: 0;
        color: var(--cyber-cyan);
        font-size: 1.2rem;
    }

    .member-count {
        background: rgba(0, 243, 255, 0.2);
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--cyber-cyan);
    }

    .group-class {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin: 8px 0;
    }

    .group-creator {
        color: var(--text-muted);
        font-size: 0.85rem;
        font-style: italic;
        margin: 5px 0;
    }

    .group-description {
        color: var(--text-color);
        margin: 12px 0;
        line-height: 1.5;
    }

    .group-schedule {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: rgba(0, 243, 255, 0.1);
        border-radius: 6px;
        margin: 12px 0;
        font-size: 0.9rem;
        color: var(--cyber-cyan);
    }

    .cyber-btn-sm {
        display: inline-block;
        padding: 8px 16px;
        background: var(--cyber-cyan);
        color: var(--bg-dark);
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 600;
        transition: all 0.3s ease;
        margin-top: 12px;
    }

    .cyber-btn-sm:hover {
        background: var(--cyber-purple);
        transform: scale(1.05);
    }

    .joined-badge,
    .full-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-top: 12px;
    }

    .joined-badge {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success-color);
    }

    .full-badge {
        background: rgba(107, 114, 128, 0.2);
        color: var(--text-muted);
    }
</style>

<?php include '../includes/cyber-footer.php'; ?>

