<?php

/**
 * Public Updates Page — No Login Required
 *
 * Displays school announcements, holidays, events, and news.
 * Accessible to parents and general public without authentication.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

$page_title = 'School Updates';

// Fetch announcements
$announcements = [];
try {
  if (table_exists('announcements')) {
    $announcements = db()->fetchAll(
      "SELECT * FROM announcements
       WHERE (audience = 'all' OR audience = 'public')
         AND (expires_at IS NULL OR expires_at > NOW())
       ORDER BY created_at DESC
       LIMIT 20"
    );
  }
} catch (\Throwable $e) {
  // silently fail
}

// Fetch upcoming events
$events = [];
try {
  if (table_exists('events')) {
    $events = db()->fetchAll(
      "SELECT * FROM events
       WHERE event_date >= CURDATE()
       ORDER BY event_date ASC
       LIMIT 10"
    );
  }
} catch (\Throwable $e) {
  // silently fail
}

// Fetch holidays
$holidays = [];
try {
  if (table_exists('holidays')) {
    $holidays = db()->fetchAll(
      "SELECT * FROM holidays
       WHERE holiday_date >= CURDATE()
       ORDER BY holiday_date ASC
       LIMIT 10"
    );
  }
} catch (\Throwable $e) {
  // silently fail
}

$appName = defined('APP_NAME') ? APP_NAME : 'School Attendance System';
$appUrl  = defined('APP_URL') ? APP_URL : '/attendance';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title . ' — ' . $appName) ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f0f2f5;
      color: #1a1a2e;
      min-height: 100vh;
    }

    .updates-header {
      background: linear-gradient(135deg, #1a1a2e, #16213e);
      color: #fff;
      padding: 2rem 1rem;
      text-align: center;
    }

    .updates-header h1 {
      font-size: 2rem;
      font-weight: 700;
    }

    .updates-header p {
      opacity: .8;
      margin-top: .5rem;
    }

    .updates-nav {
      display: flex;
      justify-content: center;
      gap: 1rem;
      margin-top: 1.5rem;
      flex-wrap: wrap;
    }

    .updates-nav a {
      color: #fff;
      text-decoration: none;
      padding: .5rem 1.2rem;
      border: 1px solid rgba(255, 255, 255, .3);
      border-radius: 25px;
      font-size: .9rem;
      transition: all .3s;
    }

    .updates-nav a:hover,
    .updates-nav a.active {
      background: rgba(255, 255, 255, .15);
      border-color: #00e5ff;
    }

    .container {
      max-width: 900px;
      margin: 2rem auto;
      padding: 0 1rem;
    }

    .section {
      margin-bottom: 2rem;
    }

    .section h2 {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .section h2 i {
      color: #00e5ff;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
      margin-bottom: 1rem;
      border-left: 4px solid #00e5ff;
    }

    .card.event {
      border-left-color: #e040fb;
    }

    .card.holiday {
      border-left-color: #ff6b35;
    }

    .card h3 {
      font-size: 1.1rem;
      margin-bottom: .5rem;
    }

    .card .meta {
      font-size: .8rem;
      opacity: .6;
      margin-bottom: .5rem;
    }

    .card .body {
      font-size: .95rem;
      line-height: 1.6;
    }

    .empty {
      text-align: center;
      padding: 3rem;
      opacity: .5;
      font-size: 1rem;
    }

    .priority-high {
      border-left-color: #f44336;
    }

    .priority-urgent {
      border-left-color: #f44336;
      background: #fff5f5;
    }

    .back-link {
      text-align: center;
      padding: 2rem;
    }

    .back-link a {
      color: #1a1a2e;
      text-decoration: none;
      font-size: .9rem;
    }

    .badge {
      display: inline-block;
      font-size: .7rem;
      padding: .2rem .6rem;
      border-radius: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .badge-normal {
      background: #e3f2fd;
      color: #1565c0;
    }

    .badge-high {
      background: #fce4ec;
      color: #c62828;
    }

    .badge-urgent {
      background: #f44336;
      color: #fff;
    }
  </style>
</head>

<body>

  <div class="updates-header">
    <h1><i class="fas fa-school"></i> <?= htmlspecialchars($appName) ?></h1>
    <p>School Updates, Announcements & Events</p>
    <div class="updates-nav">
      <a href="#announcements" class="active"><i class="fas fa-bullhorn"></i> Announcements</a>
      <a href="#events"><i class="fas fa-calendar"></i> Events</a>
      <a href="#holidays"><i class="fas fa-umbrella-beach"></i> Holidays</a>
      <a href="<?= htmlspecialchars($appUrl) ?>/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
    </div>
  </div>

  <div class="container">

    <!-- Announcements -->
    <div class="section" id="announcements">
      <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
      <?php if (empty($announcements)): ?>
        <div class="empty"><i class="fas fa-info-circle"></i> No announcements at this time.</div>
      <?php else: ?>
        <?php foreach ($announcements as $a): ?>
          <?php
          $priority = $a['priority'] ?? 'normal';
          $cardClass = 'card';
          if ($priority === 'high') $cardClass .= ' priority-high';
          if ($priority === 'urgent') $cardClass .= ' priority-urgent';
          ?>
          <div class="<?= $cardClass ?>">
            <h3><?= htmlspecialchars($a['title']) ?></h3>
            <div class="meta">
              <?= date('M j, Y g:i A', strtotime($a['created_at'])) ?>
              <?php if ($priority !== 'normal'): ?>
                <span class="badge badge-<?= $priority ?>"><?= $priority ?></span>
              <?php endif; ?>
            </div>
            <div class="body"><?= nl2br(htmlspecialchars($a['content'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Events -->
    <div class="section" id="events">
      <h2><i class="fas fa-calendar-alt"></i> Upcoming Events</h2>
      <?php if (empty($events)): ?>
        <div class="empty"><i class="fas fa-calendar-times"></i> No upcoming events.</div>
      <?php else: ?>
        <?php foreach ($events as $ev): ?>
          <div class="card event">
            <h3><?= htmlspecialchars($ev['title'] ?? $ev['name'] ?? 'Event') ?></h3>
            <div class="meta">
              <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['event_date'])) ?>
            </div>
            <div class="body"><?= nl2br(htmlspecialchars($ev['description'] ?? '')) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Holidays -->
    <div class="section" id="holidays">
      <h2><i class="fas fa-umbrella-beach"></i> Upcoming Holidays</h2>
      <?php if (empty($holidays)): ?>
        <div class="empty"><i class="fas fa-sun"></i> No holidays announced yet.</div>
      <?php else: ?>
        <?php foreach ($holidays as $h): ?>
          <div class="card holiday">
            <h3><?= htmlspecialchars($h['name'] ?? $h['title'] ?? 'Holiday') ?></h3>
            <div class="meta">
              <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($h['holiday_date'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <div class="back-link">
    <a href="<?= htmlspecialchars($appUrl) ?>"><i class="fas fa-arrow-left"></i> Back to Home</a>
  </div>

</body>

</html>
