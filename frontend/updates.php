<?php

/**
 * General Updates Page
 * Public-facing page showing notices marked as 'public' visibility.
 * Authenticated users see more notices based on their role.
 */
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? null);

// Category filter
$category = $_GET['category'] ?? 'all';
$valid_categories = ['all', 'academic', 'sports', 'event', 'emergency', 'maintenance', 'general'];
if (!in_array($category, $valid_categories)) $category = 'all';

// Build query based on authentication status
$params = [];
$where_parts = ["n.status = 'active'"];

// Only show notices that aren't scheduled for the future
$where_parts[] = "(n.scheduled_at IS NULL OR n.scheduled_at <= NOW())";

// Only show notices that haven't expired
$where_parts[] = "(n.expires_at IS NULL OR n.expires_at > NOW())";

if ($is_logged_in) {
  // Logged-in: show public + authenticated + role-specific (matching their role)
  $where_parts[] = "(n.visibility IN ('public', 'authenticated') OR (n.visibility = 'role_specific' AND (n.target_roles IS NULL OR FIND_IN_SET(?, n.target_roles))))";
  $params[] = $user_role;
} else {
  // Public: only show public visibility notices
  $where_parts[] = "n.visibility = 'public'";
}

if ($category !== 'all') {
  $where_parts[] = "n.category = ?";
  $params[] = $category;
}

$where = implode(' AND ', $where_parts);

$notices = db()->fetchAll("
    SELECT n.*, u.first_name, u.last_name
    FROM notices n
    LEFT JOIN users u ON n.created_by = u.id
    WHERE $where
    ORDER BY n.is_pinned DESC, n.priority = 'urgent' DESC, n.priority = 'high' DESC, n.created_at DESC
    LIMIT 50
", $params);

// Category icons and colors
$category_map = [
  'academic' => ['icon' => 'fas fa-graduation-cap', 'color' => '#4F46E5'],
  'sports' => ['icon' => 'fas fa-futbol', 'color' => '#059669'],
  'event' => ['icon' => 'fas fa-calendar-star', 'color' => '#D97706'],
  'emergency' => ['icon' => 'fas fa-exclamation-triangle', 'color' => '#DC2626'],
  'maintenance' => ['icon' => 'fas fa-tools', 'color' => '#7C3AED'],
  'general' => ['icon' => 'fas fa-info-circle', 'color' => '#0EA5E9'],
];

$priority_colors = ['normal' => '#22c55e', 'high' => '#f59e0b', 'urgent' => '#ef4444'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php if (file_exists(__DIR__ . '/includes/favicon-loader.php')) include_once 'includes/favicon-loader.php'; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Updates & Notices - <?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/professional-ui.css">
  <script src="assets/js/theme-loader.js"></script>
  <style>
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: var(--bg-primary);
      color: var(--text-primary);
    }

    .updates-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 24px 20px 60px;
    }

    .updates-header {
      text-align: center;
      margin-bottom: 32px;
      padding: 40px 20px;
      background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, #000));
      border-radius: 16px;
      color: #fff;
    }

    .updates-header h1 {
      font-size: clamp(1.5rem, 4vw, 2rem);
      margin: 0 0 8px;
      font-weight: 700;
    }

    .updates-header p {
      margin: 0;
      opacity: 0.85;
      font-size: 1rem;
    }

    .updates-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 24px;
      background: var(--bg-white);
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      border: 1px solid var(--border-color);
      flex-wrap: wrap;
      gap: 12px;
    }

    .updates-nav a {
      color: var(--text-primary);
      text-decoration: none;
      font-weight: 500;
      font-size: 14px;
    }

    .updates-nav a:hover {
      color: var(--primary);
    }

    .category-pills {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 24px;
    }

    .category-pill {
      padding: 8px 18px;
      border-radius: 24px;
      font-size: 13px;
      font-weight: 500;
      text-decoration: none;
      border: 1px solid var(--border-color);
      background: var(--bg-white);
      color: var(--text-secondary);
      transition: all 0.2s;
    }

    .category-pill:hover,
    .category-pill.active {
      background: var(--primary);
      color: #fff;
      border-color: var(--primary);
    }

    .notice-card {
      background: var(--bg-white);
      border-radius: 12px;
      padding: 24px;
      margin-bottom: 16px;
      border: 1px solid var(--border-color);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
      transition: box-shadow 0.2s;
    }

    .notice-card:hover {
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    }

    .notice-card.pinned {
      border-left: 4px solid var(--primary);
    }

    .notice-card.urgent {
      border-left: 4px solid #ef4444;
    }

    .notice-meta {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
      flex-wrap: wrap;
    }

    .notice-category-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      color: #fff;
    }

    .notice-priority-badge {
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      color: #fff;
    }

    .notice-pin-badge {
      color: var(--primary);
      font-size: 12px;
      font-weight: 600;
    }

    .notice-title {
      font-size: 1.15rem;
      font-weight: 600;
      margin: 0 0 10px;
      color: var(--text-primary);
    }

    .notice-content {
      font-size: 0.95rem;
      line-height: 1.7;
      color: var(--text-secondary);
      white-space: pre-line;
    }

    .notice-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 16px;
      padding-top: 12px;
      border-top: 1px solid var(--border-color);
      font-size: 0.82rem;
      color: var(--text-muted);
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--text-muted);
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 16px;
      opacity: 0.3;
    }

    @media (max-width: 600px) {
      .updates-container {
        padding: 12px;
      }
    }
  </style>
</head>

<body>
  <div class="updates-container">
    <div class="updates-nav">
      <a href="index.php"><i class="fas fa-arrow-left"></i> Home</a>
      <div style="display:flex;gap:16px;align-items:center;">
        <?php if ($is_logged_in): ?>
          <a href="notices.php"><i class="fas fa-bell"></i> My Notices</a>
          <a href="<?php echo htmlspecialchars($user_role); ?>/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <?php else: ?>
          <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
          <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="updates-header">
      <h1><i class="fas fa-bullhorn"></i> Updates & Notices</h1>
      <p>Stay informed with the latest announcements<?php echo !$is_logged_in ? ' — login to see more' : ''; ?></p>
    </div>

    <div class="category-pills">
      <a href="?category=all" class="category-pill <?php echo $category === 'all' ? 'active' : ''; ?>">All</a>
      <a href="?category=academic" class="category-pill <?php echo $category === 'academic' ? 'active' : ''; ?>"><i class="fas fa-graduation-cap"></i> Academic</a>
      <a href="?category=sports" class="category-pill <?php echo $category === 'sports' ? 'active' : ''; ?>"><i class="fas fa-futbol"></i> Sports</a>
      <a href="?category=event" class="category-pill <?php echo $category === 'event' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> Events</a>
      <a href="?category=emergency" class="category-pill <?php echo $category === 'emergency' ? 'active' : ''; ?>"><i class="fas fa-exclamation-triangle"></i> Emergency</a>
      <a href="?category=maintenance" class="category-pill <?php echo $category === 'maintenance' ? 'active' : ''; ?>"><i class="fas fa-tools"></i> Maintenance</a>
      <a href="?category=general" class="category-pill <?php echo $category === 'general' ? 'active' : ''; ?>"><i class="fas fa-info-circle"></i> General</a>
    </div>

    <?php if (empty($notices)): ?>
      <div class="empty-state">
        <div><i class="fas fa-inbox"></i></div>
        <h3>No updates available</h3>
        <p>Check back later for new announcements and notices.</p>
      </div>
    <?php else: ?>
      <?php foreach ($notices as $notice): ?>
        <?php
        $cat = $notice['category'] ?? 'general';
        $catInfo = $category_map[$cat] ?? $category_map['general'];
        $pri = $notice['priority'] ?? 'normal';
        $priColor = $priority_colors[$pri] ?? $priority_colors['normal'];
        $isPinned = !empty($notice['is_pinned']);
        $isUrgent = $pri === 'urgent';
        $classes = 'notice-card';
        if ($isPinned) $classes .= ' pinned';
        if ($isUrgent) $classes .= ' urgent';
        ?>
        <div class="<?php echo $classes; ?>">
          <div class="notice-meta">
            <span class="notice-category-badge" style="background:<?php echo $catInfo['color']; ?>;">
              <i class="<?php echo $catInfo['icon']; ?>"></i> <?php echo ucfirst($cat); ?>
            </span>
            <?php if ($pri !== 'normal'): ?>
              <span class="notice-priority-badge" style="background:<?php echo $priColor; ?>;">
                <?php echo ucfirst($pri); ?>
              </span>
            <?php endif; ?>
            <?php if ($isPinned): ?>
              <span class="notice-pin-badge"><i class="fas fa-thumbtack"></i> Pinned</span>
            <?php endif; ?>
          </div>

          <h2 class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></h2>
          <div class="notice-content"><?php echo nl2br(htmlspecialchars($notice['content'])); ?></div>

          <div class="notice-footer">
            <span>
              <i class="fas fa-user"></i>
              <?php echo htmlspecialchars(($notice['first_name'] ?? 'Admin') . ' ' . ($notice['last_name'] ?? '')); ?>
            </span>
            <span>
              <i class="fas fa-clock"></i>
              <?php echo date('M d, Y \a\t h:i A', strtotime($notice['created_at'])); ?>
              <?php if ($notice['expires_at']): ?>
                &middot; Expires <?php echo date('M d, Y', strtotime($notice['expires_at'])); ?>
              <?php endif; ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>

</html>
