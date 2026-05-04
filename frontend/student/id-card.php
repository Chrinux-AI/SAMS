<?php

/**
 * Digital ID Card for Students
 * Virtual student ID with QR code for campus verification
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

require_student();

$student_id = $_SESSION['user_id'];
$tenantId = current_tenant_id();

// Get student data
$student_data = db()->fetchOne("
    SELECT u.*, s.admission_number as student_id, s.assigned_student_id, c.grade_level as grade, s.is_active
    FROM users u
    JOIN students s ON u.id = s.user_id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE u.id = ?
", [$student_id]);

if (!$student_data) {
    die('Student data not found');
}

// Format student ID for display
$display_id = 'STU' . ($student_data['student_id'] ?? $student_data['assigned_student_id']);

// Get current academic year
$current_year = date('Y');
$academic_year = $current_year . '-' . ($current_year + 1);

$unread_messages = get_unread_message_count((int)$student_id, $tenantId ? (int)$tenantId : null);
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
    <title>Digital ID Card - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        .id-card {
            max-width: 400px;
            margin: 30px auto;
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.1), rgba(138, 43, 226, 0.1));
            border: 2px solid var(--cyber-cyan);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 0 40px rgba(0, 191, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .id-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(0, 191, 255, 0.1), transparent);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% {
                transform: translateX(-100%) translateY(-100%) rotate(45deg);
            }

            100% {
                transform: translateX(100%) translateY(100%) rotate(45deg);
            }
        }

        .id-header {
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .id-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 0 30px rgba(0, 191, 255, 0.5);
        }

        .id-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.5rem;
            color: var(--cyber-cyan);
            margin: 0;
        }

        .id-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .id-photo {
            width: 150px;
            height: 150px;
            margin: 20px auto;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            box-shadow: 0 0 30px rgba(0, 191, 255, 0.3);
            position: relative;
            z-index: 1;
        }

        .id-info {
            position: relative;
            z-index: 1;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0, 191, 255, 0.2);
        }

        .info-label {
            color: var(--text-muted);
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .info-value {
            color: var(--cyber-cyan);
            font-weight: bold;
            font-size: 1rem;
        }

        .qr-section {
            margin-top: 30px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .qr-container {
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 191, 255, 0.3);
        }

        .validity-badge {
            display: inline-block;
            padding: 8px 20px;
            background: var(--cyber-green);
            color: white;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 15px;
            box-shadow: 0 0 20px rgba(0, 255, 127, 0.3);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
            position: relative;
            z-index: 1;
        }
    </style>
</head>

<body>
    <div class="starfield"></div>

    </div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-id-card"></i></div>
                    <h1 class="page-title">Digital ID Card</h1>
                </div>
                <div class="header-actions">
                    <button onclick="downloadCard()" class="cyber-btn">
                        <i class="fas fa-download"></i> Download
                    </button>
                    <button onclick="window.print()" class="cyber-btn cyber-btn-outline">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </header>

            <div class="app-layout">
                <div class="id-card" id="idCard">
                    <div class="id-header">
                        <div class="id-logo">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h2 class="id-title"><?php echo APP_NAME; ?></h2>
                        <p class="id-subtitle">Student Identification Card</p>
                    </div>

                    <div class="id-photo">
                        <?php echo strtoupper(substr($student_data['first_name'], 0, 1) . substr($student_data['last_name'], 0, 1)); ?>
                    </div>

                    <div class="id-info">
                        <div class="info-row">
                            <span class="info-label">Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Student ID</span>
                            <span class="info-value" style="font-family: 'Orbitron', monospace; font-size: 1.2rem;"><?php echo htmlspecialchars($display_id); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Grade/Section</span>
                            <span class="info-value"><?php echo htmlspecialchars(($student_data['grade'] ?? 'N/A') . ' / ' . ($student_data['section'] ?? 'N/A')); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($student_data['email']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Academic Year</span>
                            <span class="info-value"><?php echo $academic_year; ?></span>
                        </div>
                        <div class="info-row" style="border-bottom: none;">
                            <span class="info-label">Status</span>
                            <span class="info-value" style="color: var(--cyber-green);">
                                <i class="fas fa-check-circle"></i> <?php echo ucfirst($student_data['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="qr-section">
                        <p style="color: var(--text-muted); margin-bottom: 15px; font-size: 0.9rem;">
                            <i class="fas fa-qrcode"></i> Scan for Verification
                        </p>
                        <div class="qr-container" id="qrcode"></div>
                        <div class="validity-badge">
                            <i class="fas fa-shield-check"></i> Valid ID
                        </div>
                    </div>
                </div>

                <div class="action-buttons" style="max-width: 400px; margin: 0 auto;">
                    <button onclick="shareCard()" class="cyber-btn cyber-btn-outline">
                        <i class="fas fa-share-alt"></i> Share
                    </button>
                    <button onclick="addToWallet()" class="cyber-btn cyber-btn-outline">
                        <i class="fas fa-wallet"></i> Add to Wallet
                    </button>
                </div>

                <div class="holo-card" style="margin-top: 40px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-info-circle"></i> About Your Digital ID</div>
                    </div>
                    <div class="card-body">
                        <p style="line-height: 1.8; color: var(--text-muted);">
                            Your Digital ID Card serves as your official identification within the campus. It can be used for:
                        </p>
                        <ul style="color: var(--text-muted); line-height: 2;">
                            <li><i class="fas fa-check" style="color: var(--cyber-green);"></i> Library access and book borrowing</li>
                            <li><i class="fas fa-check" style="color: var(--cyber-green);"></i> Campus facility entry verification</li>
                            <li><i class="fas fa-check" style="color: var(--cyber-green);"></i> Event registration and attendance</li>
                            <li><i class="fas fa-check" style="color: var(--cyber-green);"></i> Quick check-in at classes</li>
                            <li><i class="fas fa-check" style="color: var(--cyber-green);"></i> Cafeteria and meal plan verification</li>
                        </ul>
                        <div style="background: rgba(0, 191, 255, 0.1); padding: 15px; border-radius: 10px; margin-top: 20px; border-left: 4px solid var(--cyber-cyan);">
                            <strong style="color: var(--cyber-cyan);"><i class="fas fa-exclamation-triangle"></i> Important:</strong>
                            <p style="margin: 10px 0 0 0; color: var(--text-muted);">
                                Keep your Student ID confidential. Do not share your QR code with unauthorized individuals.
                                Report lost or stolen IDs immediately to administration.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Generate QR Code
        const qrData = JSON.stringify({
            id: '<?php echo $display_id; ?>',
            name: '<?php echo addslashes($student_data['first_name'] . ' ' . $student_data['last_name']); ?>',
            email: '<?php echo $student_data['email']; ?>',
            type: 'student',
            timestamp: new Date().toISOString()
        });

        new QRCode(document.getElementById("qrcode"), {
            text: qrData,
            width: 180,
            height: 180,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });

        // Download card as image
        function downloadCard() {
            // Simple implementation - in production, use html2canvas library
            alert('Download feature requires html2canvas library integration. Your ID can be printed using the Print button.');
        }

        // Share card
        function shareCard() {
            if (navigator.share) {
                navigator.share({
                    title: 'My Student ID Card',
                    text: 'Student ID: <?php echo $display_id; ?>',
                    url: window.location.href
                }).catch(err => console.log('Error sharing:', err));
            } else {
                // Fallback - copy to clipboard
                const idText = 'Student ID: <?php echo $display_id; ?>\nName: <?php echo addslashes($student_data['first_name'] . ' ' . $student_data['last_name']); ?>';
                navigator.clipboard.writeText(idText).then(() => {
                    alert('ID details copied to clipboard!');
                });
            }
        }

        // Add to wallet (placeholder)
        function addToWallet() {
            alert('Mobile wallet integration coming soon! For now, you can save this page as a bookmark for quick access.');
        }

        // Print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                .cyber-bg, .cyber-grid, .cyber-header, .cyber-nav, .action-buttons, .holo-card { display: none !important; }
                .cyber-main { margin: 0; padding: 20px; }
                .id-card { box-shadow: none; border: 2px solid #000; page-break-inside: avoid; }
            }
        `;
        document.head.appendChild(style);
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
