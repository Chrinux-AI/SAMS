<?php
/**
 * SAMS Library - Digital Resources
 * Browse digital library resources organized by type
 */
session_start();
require_once '../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';
require_login('../login.php');
if (!has_role('librarian') && !has_role('admin')) {
    redirect('../login.php', 'Access denied. Librarian privileges required.', 'error');
}

$csrf = generate_csrf_token();
$tenantId = $_SESSION['tenant_id'] ?? 1;
$user_id = $_SESSION['user_id'];

// Digital resource types with metadata
$resource_types = [
    ['icon' => 'fa-book-open', 'title' => 'eBooks', 'count' => 0, 'color' => '#4f46e5', 'desc' => 'Digital books available for download and online reading'],
    ['icon' => 'fa-newspaper', 'title' => 'Academic Journals', 'count' => 0, 'color' => '#059669', 'desc' => 'Peer-reviewed scholarly articles and publications'],
    ['icon' => 'fa-video', 'title' => 'Video Lectures', 'count' => 0, 'color' => '#dc2626', 'desc' => 'Educational video content and recorded lectures'],
    ['icon' => 'fa-headphones', 'title' => 'Audiobooks', 'count' => 0, 'color' => '#7c3aed', 'desc' => 'Audio versions of popular and academic books'],
    ['icon' => 'fa-file-pdf', 'title' => 'Research Papers', 'count' => 0, 'color' => '#ea580c', 'desc' => 'Published research documents and thesis papers'],
    ['icon' => 'fa-globe', 'title' => 'Online Databases', 'count' => 0, 'color' => '#0284c7', 'desc' => 'Subscribed online databases and reference tools'],
    ['icon' => 'fa-graduation-cap', 'title' => 'Course Materials', 'count' => 0, 'color' => '#b45309', 'desc' => 'Supplementary course materials and study guides'],
    ['icon' => 'fa-images', 'title' => 'Digital Archives', 'count' => 0, 'color' => '#be185d', 'desc' => 'Historical documents and archival collections'],
];

// Try to get counts from digital_resources table if it exists
try {
    if (table_exists('digital_resources')) {
        $counts = db()->fetchAll("SELECT resource_type, COUNT(*) as cnt FROM digital_resources WHERE tenant_id = ? GROUP BY resource_type", [$tenantId]);
        $count_map = [];
        foreach ($counts as $c) {
            $count_map[$c['resource_type']] = (int)$c['cnt'];
        }
        foreach ($resource_types as &$rt) {
            $key = strtolower(str_replace(' ', '_', $rt['title']));
            $rt['count'] = $count_map[$key] ?? $count_map[$rt['title']] ?? 0;
        }
        unset($rt);
    }
} catch (Throwable $e) {}

// Total digital resources
$total_resources = array_sum(array_column($resource_types, 'count'));

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include INCLUDES_PATH . '/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Resources - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <link rel="stylesheet" href="../assets/css/sams-theme-system.css">
    <link rel="stylesheet" href="../assets/css/sams-layout.css">
    <style>
        .resource-card {
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            border: 1px solid rgba(0,0,0,0.08);
            background: var(--card-bg, #fff);
            height: 100%;
        }
        .resource-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .resource-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
            margin-bottom: 1rem;
        }
        .resource-count {
            font-size: 1.8rem;
            font-weight: 700;
            line-height: 1;
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include INCLUDES_PATH . '/sidebar-nav.php'; ?>
    <main class="main-content">
        <div class="cyber-header">
            <div class="page-icon-orb"><i class="fas fa-laptop-code"></i></div>
            <div>
                <h1>Digital Resources</h1>
                <p>Access and manage the library's digital collection</p>
            </div>
        </div>
        <div class="cyber-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body text-center py-4">
                    <h3><i class="fas fa-cloud"></i> Digital Library Portal</h3>
                    <p class="text-muted mb-0">Browse <?= $total_resources ?> digital resources across <?= count($resource_types) ?> categories</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($resource_types as $rt): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="resource-card">
                            <div class="resource-icon" style="background: <?= htmlspecialchars($rt['color']) ?>">
                                <i class="fas <?= htmlspecialchars($rt['icon']) ?>"></i>
                            </div>
                            <div class="resource-count mb-1"><?= (int)$rt['count'] ?></div>
                            <h5 class="mb-1"><?= htmlspecialchars($rt['title']) ?></h5>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($rt['desc']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card mt-4">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <i class="fas fa-rocket fa-3x text-muted"></i>
                    </div>
                    <h4>Coming Soon</h4>
                    <p class="text-muted mb-0">Full digital resource management with upload, cataloging, and student access controls is under active development. Stay tuned for updates.</p>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
