<?php

/**
 * Parent - Book Teacher Meeting
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/database.php';
require_parent();

$user_id = $_SESSION['user_id'];
$page_title = "Book Meeting";

// Get parent's children
$children = db()->fetchAll("
    SELECT s.id, s.user_id, u.first_name, u.last_name, s.grade, s.section
    FROM parent_student_links psl
    JOIN students s ON psl.student_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE psl.parent_id = ? AND psl.status = 'approved'
", [$user_id]);

// Get teachers with available slots
$teachers = db()->fetchAll("
    SELECT DISTINCT u.id, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM meeting_slots WHERE teacher_id = u.id AND is_active = 1) as slot_count
    FROM users u
    JOIN meeting_slots ms ON u.id = ms.teacher_id
    WHERE u.role = 'teacher' AND ms.is_active = 1
    ORDER BY u.last_name, u.first_name
");

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $student_id = intval($_POST['student_id'] ?? 0);
    $slot_id = intval($_POST['slot_id'] ?? 0);
    $booking_date = $_POST['booking_date'] ?? '';
    $notes = trim($_POST['parent_notes'] ?? '');

    $errors = [];

    if (!$teacher_id || !$student_id || !$slot_id || !$booking_date) {
        $errors[] = "All fields are required";
    }

    // Check if slot exists and is available
    $slot = db()->fetchOne("SELECT * FROM meeting_slots WHERE id = ? AND teacher_id = ? AND is_active = 1", [$slot_id, $teacher_id]);

    if (!$slot) {
        $errors[] = "Invalid time slot selected";
    }

    if (empty($errors)) {
        try {
            db()->execute("
                INSERT INTO meeting_bookings (slot_id, parent_id, student_id, booking_date, parent_notes)
                VALUES (?, ?, ?, ?, ?)
            ", [$slot_id, $user_id, $student_id, $booking_date, $notes]);

            $_SESSION['success_message'] = "Meeting booked successfully! Teacher will confirm shortly.";
            header("Location: my-meetings.php");
            exit;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errors[] = "You already have a booking for this time slot on this date";
            } else {
                $errors[] = "Failed to book meeting. Please try again.";
            }
        }
    }
}

include '../includes/cyber-header.php';
include '../includes/cyber-nav.php';
include '../includes/sidebar-nav.php';
?>

<div class="cyber-content">
    <div class="content-header">
        <h1><i class="fas fa-calendar-plus"></i> Book Teacher Meeting</h1>
        <p class="subtitle">Schedule a meeting with your child's teacher</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <ul style="margin:10px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="holo-card">
        <div class="card-body">
            <form method="POST" action="" id="bookingForm">
                <div class="form-group">
                    <label>Select Child <span class="required">*</span></label>
                    <select name="student_id" class="cyber-input" required>
                        <option value="">Choose a child</option>
                        <?php foreach ($children as $child): ?>
                            <option value="<?php echo $child['id']; ?>">
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                (Grade <?php echo $child['grade']; ?>-<?php echo $child['section']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Teacher <span class="required">*</span></label>
                    <select name="teacher_id" class="cyber-input" id="teacherSelect" required onchange="loadTeacherSlots(this.value)">
                        <option value="">Choose a teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                (<?php echo $teacher['slot_count']; ?> available slots)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="slotsSection" style="display:none;">
                    <div class="form-group">
                        <label>Available Time Slots <span class="required">*</span></label>
                        <div id="slotsList" class="slots-grid">
                            <p style="text-align:center; color:var(--text-muted);">Loading...</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Meeting Date <span class="required">*</span></label>
                        <input type="date" name="booking_date" class="cyber-input" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Notes for Teacher (Optional)</label>
                        <textarea name="parent_notes" class="cyber-input" rows="4" placeholder="What would you like to discuss?"></textarea>
                    </div>

                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-check"></i> Book Meeting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .slots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .slot-option {
        padding: 15px;
        background: rgba(0, 243, 255, 0.05);
        border: 2px solid rgba(0, 243, 255, 0.2);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
    }

    .slot-option:hover {
        background: rgba(0, 243, 255, 0.1);
        border-color: var(--cyber-cyan);
    }

    .slot-option input[type="radio"] {
        display: none;
    }

    .slot-option input[type="radio"]:checked+label {
        background: var(--cyber-cyan);
        color: var(--bg-dark);
    }

    .slot-label {
        cursor: pointer;
        display: block;
    }

    .slot-day {
        font-weight: 700;
        color: var(--cyber-cyan);
        margin-bottom: 5px;
    }

    .slot-time {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
</style>

<script>
    function loadTeacherSlots(teacherId) {
        if (!teacherId) {
            document.getElementById('slotsSection').style.display = 'none';
            return;
        }

        document.getElementById('slotsSection').style.display = 'block';

        fetch('../api/get-teacher-slots.php?teacher_id=' + teacherId)
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('slotsList');

                if (data.success && data.slots.length > 0) {
                    let html = '';
                    data.slots.forEach(slot => {
                        html += `
                        <div class="slot-option">
                            <input type="radio" name="slot_id" value="${slot.id}" id="slot_${slot.id}" required>
                            <label for="slot_${slot.id}" class="slot-label">
                                <div class="slot-day">${slot.day_of_week}</div>
                                <div class="slot-time">${slot.start_time} - ${slot.end_time}</div>
                            </label>
                        </div>
                    `;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p style="text-align:center; color:var(--text-muted);">No available slots</p>';
                }
            })
            .catch(e => {
                document.getElementById('slotsList').innerHTML = '<p style="color:var(--danger-color);">Error loading slots</p>';
            });
    }
</script>

<?php include '../includes/cyber-footer.php'; ?>

