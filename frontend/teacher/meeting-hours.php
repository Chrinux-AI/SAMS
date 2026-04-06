<?php

/**
 * Teacher Meeting Hours Management
 * Set availability for parent meetings
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/database.php';
require_teacher();

$user_id = $_SESSION['user_id'];
$page_title = "Meeting Hours";

// Handle slot creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_slot') {
    $day = $_POST['day_of_week'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';

    if ($day && $start_time && $end_time) {
        db()->query("
            INSERT INTO meeting_slots (teacher_id, day_of_week, start_time, end_time)
            VALUES (?, ?, ?, ?)
        ", [$user_id, $day, $start_time, $end_time]);

        $_SESSION['success_message'] = "Meeting slot added successfully";
        header("Location: meeting-hours.php");
        exit;
    }
}

// Handle slot deletion
if (isset($_GET['delete'])) {
    $slot_id = intval($_GET['delete']);
    db()->query("DELETE FROM meeting_slots WHERE id = ? AND teacher_id = ?", [$slot_id, $user_id]);
    $_SESSION['success_message'] = "Slot deleted successfully";
    header("Location: meeting-hours.php");
    exit;
}

// Get teacher's slots
$slots = db()->fetchAll("
    SELECT * FROM meeting_slots
    WHERE teacher_id = ? AND is_active = 1
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), start_time
", [$user_id]);

// Get upcoming bookings
$bookings = db()->fetchAll("
    SELECT b.*, s.day_of_week, s.start_time, s.end_time,
           u.first_name as parent_first, u.last_name as parent_last,
           st.first_name as student_first, st.last_name as student_last
    FROM meeting_bookings b
    JOIN meeting_slots s ON b.slot_id = s.id
    JOIN users u ON b.parent_id = u.id
    JOIN students stud ON b.student_id = stud.id
    JOIN users st ON stud.user_id = st.id
    WHERE s.teacher_id = ? AND b.booking_date >= CURDATE()
    AND b.status != 'cancelled'
    ORDER BY b.booking_date, s.start_time
", [$user_id]);

include '../includes/cyber-header.php';
include '../includes/sidebar-nav.php';
?>

<div class="app-layout">
    <div class="content-header">
        <h1><i class="fas fa-calendar-alt"></i> Meeting Hours</h1>
        <p class="subtitle">Manage your availability for parent meetings</p>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <div class="grid-2-col">
        <!-- Available Slots -->
        <div class="holo-card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-clock"></i> My Availability</div>
            </div>
            <div class="card-body">
                <?php if (empty($slots)): ?>
                    <p style="text-align:center; color:var(--text-muted); padding:40px 20px;">
                        <i class="fas fa-calendar-times" style="font-size:3rem; display:block; margin-bottom:15px; opacity:0.3;"></i>
                        No availability set. Add your first time slot below.
                    </p>
                <?php else: ?>
                    <div class="slots-list">
                        <?php
                        $grouped = [];
                        foreach ($slots as $slot) {
                            $grouped[$slot['day_of_week']][] = $slot;
                        }
                        foreach ($grouped as $day => $day_slots):
                        ?>
                            <div class="day-group">
                                <h4><?php echo $day; ?></h4>
                                <?php foreach ($day_slots as $slot): ?>
                                    <div class="slot-item">
                                        <div class="slot-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?>
                                        </div>
                                        <a href="?delete=<?php echo $slot['id']; ?>" class="delete-btn" onclick="return confirm('Delete this slot?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Slot Form -->
        <div class="holo-card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle"></i> Add Time Slot</div>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_slot">

                    <div class="form-group">
                        <label>Day of Week</label>
                        <select name="day_of_week" class="cyber-input" required>
                            <option value="">Select Day</option>
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" class="cyber-input" required>
                    </div>

                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" class="cyber-input" required>
                    </div>

                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-plus"></i> Add Slot
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Upcoming Bookings -->
    <div class="holo-card" style="margin-top:30px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-calendar-check"></i> Upcoming Meetings (<?php echo count($bookings); ?>)</div>
        </div>
        <div class="card-body">
            <?php if (empty($bookings)): ?>
                <p style="text-align:center; color:var(--text-muted); padding:40px 20px;">
                    No upcoming meetings scheduled
                </p>
            <?php else: ?>
                <div class="bookings-list">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="booking-item">
                            <div class="booking-date">
                                <div class="date-day"><?php echo date('d', strtotime($booking['booking_date'])); ?></div>
                                <div class="date-month"><?php echo date('M', strtotime($booking['booking_date'])); ?></div>
                            </div>
                            <div class="booking-details">
                                <h4>Meeting with <?php echo htmlspecialchars($booking['parent_first'] . ' ' . $booking['parent_last']); ?></h4>
                                <p>Regarding: <?php echo htmlspecialchars($booking['student_first'] . ' ' . $booking['student_last']); ?></p>
                                <div class="booking-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo $booking['day_of_week']; ?>, <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                                    <span class="status-badge <?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                </div>
                                <?php if ($booking['parent_notes']): ?>
                                    <div class="notes">
                                        <strong>Parent Notes:</strong> <?php echo htmlspecialchars($booking['parent_notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .grid-2-col {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .slots-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .day-group h4 {
        color: var(--cyber-cyan);
        margin: 0 0 10px 0;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(0, 243, 255, 0.2);
    }

    .slot-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: rgba(0, 243, 255, 0.05);
        border-radius: 8px;
        margin-bottom: 8px;
    }

    .slot-time {
        color: var(--text-color);
        font-weight: 500;
    }

    .delete-btn {
        background: transparent;
        border: 1px solid var(--danger-color);
        color: var(--danger-color);
        padding: 6px 10px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .delete-btn:hover {
        background: var(--danger-color);
        color: white;
    }

    .bookings-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .booking-item {
        display: flex;
        gap: 20px;
        padding: 20px;
        background: rgba(0, 243, 255, 0.05);
        border-left: 3px solid var(--cyber-cyan);
        border-radius: 8px;
    }

    .booking-date {
        width: 60px;
        text-align: center;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
        border-radius: 8px;
        padding: 10px;
        flex-shrink: 0;
    }

    .date-day {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }

    .date-month {
        font-size: 0.9rem;
        text-transform: uppercase;
    }

    .booking-details {
        flex: 1;
    }

    .booking-details h4 {
        margin: 0 0 5px 0;
        color: var(--cyber-cyan);
    }

    .booking-details p {
        margin: 0 0 10px 0;
        color: var(--text-muted);
    }

    .booking-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 0.85rem;
        align-items: center;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.pending {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning-color);
    }

    .status-badge.confirmed {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success-color);
    }

    .notes {
        margin-top: 10px;
        padding: 10px;
        background: rgba(0, 243, 255, 0.1);
        border-radius: 6px;
        font-size: 0.9rem;
    }

    @media (max-width: 768px) {
        .grid-2-col {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php include '../includes/cyber-footer.php'; ?>

