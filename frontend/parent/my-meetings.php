<?php

/**
 * Parent - My Meetings
 * View booked meetings
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/database.php';
require_parent();

$user_id = $_SESSION['user_id'];
$page_title = "My Meetings";

// Get all bookings
$bookings = db()->fetchAll("
    SELECT b.*, s.day_of_week, s.start_time, s.end_time,
           t.first_name as teacher_first, t.last_name as teacher_last,
           st.first_name as student_first, st.last_name as student_last
    FROM meeting_bookings b
    JOIN meeting_slots s ON b.slot_id = s.id
    JOIN users t ON s.teacher_id = t.id
    JOIN students stud ON b.student_id = stud.id
    JOIN users st ON stud.user_id = st.id
    WHERE b.parent_id = ?
    ORDER BY b.booking_date DESC, s.start_time DESC
", [$user_id]);

include '../includes/cyber-header.php';
include '../includes/sidebar-nav.php';
?>

<div class="app-layout">
    <div class="content-header">
        <h1><i class="fas fa-calendar-check"></i> My Meetings</h1>
        <p class="subtitle">View and manage your scheduled meetings</p>
        <a href="book-meeting.php" class="cyber-btn">
            <i class="fas fa-plus"></i> Book New Meeting
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <div class="holo-card">
        <div class="card-body">
            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times" style="font-size: 4rem; opacity: 0.3;"></i>
                    <h3>No meetings scheduled</h3>
                    <p>Book a meeting with your child's teacher</p>
                    <a href="book-meeting.php" class="cyber-btn">
                        <i class="fas fa-plus"></i> Book Meeting
                    </a>
                </div>
            <?php else: ?>
                <div class="meetings-timeline">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="meeting-card status-<?php echo $booking['status']; ?>">
                            <div class="meeting-date">
                                <div class="date-day"><?php echo date('d', strtotime($booking['booking_date'])); ?></div>
                                <div class="date-month"><?php echo date('M', strtotime($booking['booking_date'])); ?></div>
                                <div class="date-year"><?php echo date('Y', strtotime($booking['booking_date'])); ?></div>
                            </div>
                            <div class="meeting-info">
                                <div class="meeting-header">
                                    <h3>Meeting with <?php echo htmlspecialchars($booking['teacher_first'] . ' ' . $booking['teacher_last']); ?></h3>
                                    <span class="status-badge <?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                                <p><strong>Student:</strong> <?php echo htmlspecialchars($booking['student_first'] . ' ' . $booking['student_last']); ?></p>
                                <div class="meeting-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo $booking['day_of_week']; ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                                </div>
                                <?php if ($booking['parent_notes']): ?>
                                    <div class="notes-section">
                                        <strong>Your Notes:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($booking['parent_notes'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($booking['teacher_notes']): ?>
                                    <div class="notes-section teacher-notes">
                                        <strong>Teacher Notes:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($booking['teacher_notes'])); ?></p>
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
    .meetings-timeline {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .meeting-card {
        display: flex;
        gap: 20px;
        padding: 25px;
        background: rgba(0, 243, 255, 0.05);
        border-left: 4px solid var(--cyber-cyan);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .meeting-card:hover {
        background: rgba(0, 243, 255, 0.1);
        transform: translateX(5px);
    }

    .meeting-card.status-confirmed {
        border-left-color: var(--success-color);
    }

    .meeting-card.status-pending {
        border-left-color: var(--warning-color);
    }

    .meeting-card.status-cancelled {
        border-left-color: var(--danger-color);
        opacity: 0.6;
    }

    .meeting-date {
        width: 80px;
        text-align: center;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
        border-radius: 12px;
        padding: 15px 10px;
        flex-shrink: 0;
    }

    .date-day {
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
    }

    .date-month {
        font-size: 1rem;
        text-transform: uppercase;
        margin-top: 5px;
    }

    .date-year {
        font-size: 0.8rem;
        opacity: 0.8;
        margin-top: 3px;
    }

    .meeting-info {
        flex: 1;
    }

    .meeting-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .meeting-header h3 {
        margin: 0;
        color: var(--cyber-cyan);
    }

    .meeting-meta {
        display: flex;
        gap: 20px;
        margin: 10px 0;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .notes-section {
        margin-top: 15px;
        padding: 12px;
        background: rgba(0, 243, 255, 0.1);
        border-radius: 6px;
    }

    .notes-section strong {
        color: var(--cyber-cyan);
    }

    .notes-section p {
        margin: 8px 0 0 0;
        color: var(--text-color);
    }

    .teacher-notes {
        background: rgba(168, 85, 247, 0.1);
    }

    .teacher-notes strong {
        color: var(--cyber-purple);
    }
</style>

<?php include '../includes/cyber-footer.php'; ?>

