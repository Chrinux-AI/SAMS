<?php

/**
 * Help Center - User Guides and Documentation
 * Accessible by all roles
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = 'Help Center';
$role = $_SESSION['role'] ?? 'guest';

$guides = [
    'student' => [
        ['title' => 'Getting Started', 'icon' => 'rocket', 'link' => '#getting-started'],
        ['title' => 'Check-In Guide', 'icon' => 'fingerprint', 'link' => '#checkin'],
        ['title' => 'Viewing Grades', 'icon' => 'star', 'link' => '#grades'],
        ['title' => 'Using the Forum', 'icon' => 'comments', 'link' => '#forum']
    ],
    'teacher' => [
        ['title' => 'Marking Attendance', 'icon' => 'clipboard-check', 'link' => '#attendance'],
        ['title' => 'Grade Management', 'icon' => 'graduation-cap', 'link' => '#grading'],
        ['title' => 'Parent Communication', 'icon' => 'envelope', 'link' => '#parent-comms'],
        ['title' => 'Resource Sharing', 'icon' => 'share-alt', 'link' => '#resources']
    ],
    'parent' => [
        ['title' => 'Linking Children', 'icon' => 'link', 'link' => '#linking'],
        ['title' => 'Monitoring Progress', 'icon' => 'chart-line', 'link' => '#progress'],
        ['title' => 'Fee Payments', 'icon' => 'wallet', 'link' => '#fees'],
        ['title' => 'Booking Meetings', 'icon' => 'calendar-check', 'link' => '#meetings']
    ],
    'admin' => [
        ['title' => 'User Management', 'icon' => 'users-cog', 'link' => '#users'],
        ['title' => 'System Configuration', 'icon' => 'cog', 'link' => '#config'],
        ['title' => 'Reports & Analytics', 'icon' => 'chart-bar', 'link' => '#reports'],
        ['title' => 'Backup & Security', 'icon' => 'shield-alt', 'link' => '#security']
    ]
];

include '../includes/cyber-header.php';
include '../includes/cyber-nav.php';
?>

<div class="cyber-content">
    <div class="content-header">
        <h1><i class="fas fa-life-ring"></i> Help Center</h1>
        <p class="subtitle">Comprehensive guides for all Attendance AI features</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:30px;">
        <?php
        $roleGuides = $guides[$role] ?? $guides['student'];
        foreach ($roleGuides as $guide):
        ?>
            <a href="<?php echo $guide['link']; ?>" class="holo-card" style="text-decoration:none;transition:transform 0.3s;">
                <div class="card-body" style="text-align:center;padding:30px;">
                    <i class="fas fa-<?php echo $guide['icon']; ?>" style="font-size:3rem;color:var(--cyber-cyan);margin-bottom:15px;"></i>
                    <h3 style="color:var(--text-primary);margin:0;"><?php echo $guide['title']; ?></h3>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="holo-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-video"></i> Video Tutorials</div>
        </div>
        <div class="card-body">
            <p>Watch our comprehensive video guides:</p>
            <ul>
                <li><a href="#">Attendance AI Overview (5 minutes)</a></li>
                <li><a href="#">Quick Start Guide (10 minutes)</a></li>
                <li><a href="#">Advanced Features Tour (15 minutes)</a></li>
            </ul>
        </div>
    </div>

    <div class="holo-card" style="margin-top:20px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-book"></i> Documentation</div>
        </div>
        <div class="card-body">
            <p>Access detailed technical documentation:</p>
            <ul>
                <li><a href="/attendance/docs/api-specs.yaml">API Documentation</a></li>
                <li><a href="/attendance/docs/requirements.md">System Requirements</a></li>
                <li><a href="/attendance/PROJECT_OVERVIEW.md">Project Overview</a></li>
            </ul>
        </div>
    </div>
</div>

<style>
    .holo-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 191, 255, 0.3);
    }
</style>

<?php include '../includes/cyber-footer.php'; ?>
