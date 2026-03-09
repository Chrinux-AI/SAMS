<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$message = '';
$message_type = '';

// Handle event creation/updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_event'])) {
        $data = [
            'title' => sanitize($_POST['title']),
            'description' => sanitize($_POST['description']),
            'event_type' => sanitize($_POST['event_type']),
            'start_date' => sanitize($_POST['start_date']),
            'end_date' => sanitize($_POST['end_date']),
            'location' => sanitize($_POST['location']),
            'organizer_id' => $_SESSION['user_id'],
            'target_audience' => sanitize($_POST['target_audience']),
            'status' => 'scheduled'
        ];
        $id = db()->insert('events', $data);
        if ($id) {
            log_activity($_SESSION['user_id'], 'create', 'events', $id);
            $message = 'Event created successfully!';
            $message_type = 'success';
        }
    } elseif (isset($_POST['delete_event'])) {
        $id = (int)$_POST['event_id'];
        db()->delete('events', 'id = ?', [$id]);
        log_activity($_SESSION['user_id'], 'delete', 'events', $id);
        $message = 'Event deleted successfully!';
        $message_type = 'success';
    }
}

// Get all events
$events = db()->fetchAll("
    SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) as organizer_name
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.id
    ORDER BY e.start_date DESC
");

$page_title = 'Events Management';
$page_icon = 'calendar-alt';
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
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <button onclick="document.getElementById('addEventModal').style.display='flex'" class="cyber-btn primary">
                        <i class="fas fa-plus-circle"></i> Create Event
                    </button>
                    <div class="user-card" style="padding:8px 15px;margin:0;margin-left:15px;">
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
                <?php if ($message): ?>
                    <div class="cyber-alert <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-calendar-check"></i> <span>All Events</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events)): ?>
                            <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                <i class="fas fa-calendar-times" style="font-size:3rem;margin-bottom:15px;"></i>
                                <div>No events scheduled</div>
                            </div>
                        <?php else: ?>
                            <div style="display:grid;gap:20px;">
                                <?php foreach ($events as $event): ?>
                                    <div style="background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border:1px solid rgba(0,191,255,0.2);border-radius:12px;padding:20px;">
                                        <div style="display:flex;justify-content:space-between;align-items:start;">
                                            <div style="flex:1;">
                                                <h3 style="color:var(--cyber-cyan);font-size:1.3rem;margin-bottom:8px;"><?php echo htmlspecialchars($event['title']); ?></h3>
                                                <p style="color:rgba(255,255,255,0.7);margin-bottom:15px;"><?php echo htmlspecialchars($event['description']); ?></p>
                                                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                    <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                        <i class="fas fa-tag"></i> <?php echo ucfirst($event['event_type']); ?>
                                                    </span>
                                                    <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                        <i class="fas fa-users"></i> <?php echo ucfirst($event['target_audience']); ?>
                                                    </span>
                                                    <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($event['start_date'])); ?>
                                                    </span>
                                                    <?php if (!empty($event['location'])): ?>
                                                        <span style="padding:5px 12px;background:rgba(0,191,255,0.1);border:1px solid var(--cyber-cyan);border-radius:15px;font-size:0.85rem;">
                                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event?');">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" name="delete_event" class="cyber-btn danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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

    <!-- Create Event Modal -->
    <div id="addEventModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle"></i> <span>Create New Event</span></div>
                <button onclick="document.getElementById('addEventModal').style.display='none'" class="cyber-btn danger" style="padding:8px 12px;"><i class="fas fa-times"></i></button>
            </div>
            <div class="card-body">
                <form method="POST" style="display:grid;gap:15px;">
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">EVENT TITLE *</label>
                        <input type="text" name="title" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">DESCRIPTION</label>
                        <textarea name="description" rows="3" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">EVENT TYPE *</label>
                            <select name="event_type" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <option value="holiday">Holiday</option>
                                <option value="exam">Exam</option>
                                <option value="sports">Sports</option>
                                <option value="cultural">Cultural</option>
                                <option value="meeting">Meeting</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">TARGET AUDIENCE *</label>
                            <select name="target_audience" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                <option value="all">All Users</option>
                                <option value="students">Students Only</option>
                                <option value="teachers">Teachers Only</option>
                                <option value="parents">Parents Only</option>
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">START DATE *</label>
                            <input type="datetime-local" name="start_date" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                        <div>
                            <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">END DATE *</label>
                            <input type="datetime-local" name="end_date" required style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                        </div>
                    </div>
                    <div>
                        <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;">LOCATION</label>
                        <input type="text" name="location" placeholder="e.g. Main Hall" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                    </div>
                    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                        <button type="button" onclick="document.getElementById('addEventModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="create_event" class="cyber-btn primary"><i class="fas fa-save"></i> Create Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>