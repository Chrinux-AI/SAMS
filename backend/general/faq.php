<?php

/**
 * FAQ Page - Frequently Asked Questions
 * Accessible by all roles
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

$page_title = 'Frequently Asked Questions';
$role = $_SESSION['role'] ?? 'guest';

$faqs = [
    'General' => [
        [
            'q' => 'How do I reset my password?',
            'a' => 'Click "Forgot Password" on the login page and follow the email instructions.'
        ],
        [
            'q' => 'How do I update my profile?',
            'a' => 'Navigate to Settings from your dashboard and edit your information.'
        ],
        [
            'q' => 'Who can I contact for support?',
            'a' => 'Email support@sams.edu or use the chatbot assistant.'
        ]
    ],
    'Students' => [
        [
            'q' => 'How do I check in for attendance?',
            'a' => 'Go to Dashboard and click "Check In" or scan the QR code at your classroom.'
        ],
        [
            'q' => 'Where can I view my grades?',
            'a' => 'Navigate to Academic → My Grades to see all your course grades.'
        ]
    ],
    'Teachers' => [
        [
            'q' => 'How do I mark attendance?',
            'a' => 'Go to Mark Attendance, select your class, and mark students as Present/Absent/Late.'
        ],
        [
            'q' => 'How do I communicate with parents?',
            'a' => 'Use Parent Communication under Communication menu to send messages.'
        ]
    ],
    'Parents' => [
        [
            'q' => 'How do I link my children?',
            'a' => 'Go to Link Children and enter your child\'s student ID for verification.'
        ],
        [
            'q' => 'How do I pay fees online?',
            'a' => 'Navigate to Fees & Payments, select outstanding fees, and click Pay Now.'
        ]
    ]
];

include '../includes/cyber-header.php';
include '../includes/cyber-nav.php';
?>

<div class="cyber-content">
    <div class="content-header">
        <h1><i class="fas fa-question-circle"></i> Frequently Asked Questions</h1>
        <p class="subtitle">Find answers to common questions</p>
    </div>

    <?php foreach ($faqs as $category => $questions): ?>
        <div class="holo-card" style="margin-bottom:30px;">
            <div class="card-header">
                <div class="card-title"><?php echo $category; ?></div>
            </div>
            <div class="card-body">
                <?php foreach ($questions as $faq): ?>
                    <div style="margin-bottom:20px;">
                        <h4 style="color:var(--cyber-cyan);margin-bottom:8px;">
                            <i class="fas fa-chevron-right"></i> <?php echo htmlspecialchars($faq['q']); ?>
                        </h4>
                        <p style="color:var(--text-muted);padding-left:25px;">
                            <?php echo htmlspecialchars($faq['a']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="holo-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-headset"></i> Still Need Help?</div>
        </div>
        <div class="card-body">
            <p>If you couldn't find the answer you're looking for:</p>
            <ul>
                <li>Use the <strong>Attendance AI Bot</strong> (bottom-right corner) for instant AI assistance</li>
                <li>Contact our support team at <a href="mailto:support@sams.edu">support@sams.edu</a></li>
                <li>Call us at (555) 123-4567</li>
                <li>Visit the Help Center for detailed guides</li>
            </ul>
        </div>
    </div>
</div>

<?php include '../includes/cyber-footer.php'; ?>
