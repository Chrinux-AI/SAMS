<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Biometric Quick Scan';
$page_icon = 'fingerprint';
$full_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html>

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
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    
    <style>
        .scan-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            position: relative;
        }

        .scan-animation {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 3px solid var(--cyber-cyan);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: pulse 2s ease-in-out infinite;
            background: radial-gradient(circle, rgba(0, 255, 255, 0.1), transparent);
        }

        .scan-animation::before {
            content: '';
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            border: 2px solid var(--cyber-cyan);
            opacity: 0.3;
            animation: pulse 2s ease-in-out infinite 0.5s;
        }

        .scan-animation i {
            font-size: 5rem;
            color: var(--cyber-cyan);
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.7;
            }
        }

        .scan-status {
            margin-top: 30px;
            text-align: center;
        }

        .scan-status h3 {
            color: var(--cyber-cyan);
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .scan-status p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .student-info-card {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.05), rgba(255, 0, 255, 0.05));
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .student-info-card.show {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .class-selection {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .class-card {
            background: rgba(0, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .class-card:hover {
            border-color: var(--cyber-cyan);
            background: rgba(0, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .class-card.already-marked {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: var(--neon-green);
        }

        .class-card h4 {
            color: var(--cyber-cyan);
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .class-card p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 5px 0;
        }

        .recent-scans {
            margin-top: 30px;
        }

        .scan-entry {
            display: flex;
            align-items: center;
            padding: 12px;
            background: rgba(0, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid var(--neon-green);
        }

        .scan-entry-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--neon-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: bold;
        }

        .scan-entry-info {
            flex: 1;
        }

        .scan-entry-name {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 3px;
        }

        .scan-entry-details {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
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
                    <div class="user-card" style="padding:8px 15px;margin:0;">
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
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-fingerprint"></i>
                            <span>Quick Attendance Marking</span>
                        </div>
                        <span class="cyber-badge success" id="scannerStatus">Scanner Ready</span>
                    </div>
                    <div class="card-body">
                        <div class="scan-container">
                            <div class="scan-animation" id="scanAnimation">
                                <i class="fas fa-fingerprint"></i>
                            </div>
                            <div class="scan-status">
                                <h3 id="scanTitle">Place Your Finger or Face</h3>
                                <p id="scanMessage">Use your device's biometric sensor to authenticate</p>
                                <button class="cyber-btn primary" onclick="startScan()" style="margin-top: 20px;">
                                    <i class="fas fa-play"></i> Start Scan
                                </button>
                            </div>
                        </div>

                        <div class="student-info-card" id="studentInfo">
                            <h3 style="color: var(--cyber-cyan); margin-bottom: 20px;">
                                <i class="fas fa-user-check"></i> Student Identified
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                <div>
                                    <p style="color: var(--text-muted); font-size: 0.85rem;">Student Name</p>
                                    <p style="color: var(--text-primary); font-weight: 600;" id="studentName">-</p>
                                </div>
                                <div>
                                    <p style="color: var(--text-muted); font-size: 0.85rem;">Student ID</p>
                                    <p style="color: var(--text-primary); font-weight: 600;" id="studentCode">-</p>
                                </div>
                            </div>

                            <h4 style="color: var(--cyber-cyan); margin: 20px 0 15px;">Select Class to Mark Attendance</h4>
                            <div class="class-selection" id="classList"></div>
                        </div>
                    </div>
                </div>

                <div class="holo-card recent-scans">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            <span>Recent Scans (Last 24 Hours)</span>
                        </div>
                    </div>
                    <div class="card-body" id="recentScans">
                        <p style="color: var(--text-muted); text-align: center;">Loading recent scans...</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentStudentId = null;
        let currentCredentialId = null;

        async function startScan() {
            const scanTitle = document.getElementById('scanTitle');
            const scanMessage = document.getElementById('scanMessage');
            const scannerStatus = document.getElementById('scannerStatus');

            scanTitle.textContent = 'Scanning...';
            scanMessage.textContent = 'Please authenticate with your biometric sensor';
            scannerStatus.textContent = 'Scanning';
            scannerStatus.className = 'cyber-badge warning';

            try {
                // Check if WebAuthn is available
                if (!window.PublicKeyCredential) {
                    throw new Error('WebAuthn is not supported on this browser');
                }

                // Get available credentials
                const publicKeyCredentialRequestOptions = {
                    challenge: new Uint8Array(32),
                    timeout: 60000,
                    userVerification: 'required'
                };

                const assertion = await navigator.credentials.get({
                    publicKey: publicKeyCredentialRequestOptions
                });

                // Convert credential ID to base64
                const credentialId = btoa(String.fromCharCode(...new Uint8Array(assertion.rawId)));

                // Verify with server and get student info
                const response = await fetch('../api/biometric-quick-scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=get_student_info&credential_id=${encodeURIComponent(credentialId)}`
                });

                const data = await response.json();

                if (data.success) {
                    showStudentInfo(data.student, data.classes, data.today_attendance, credentialId);
                    scanTitle.textContent = 'Authentication Successful';
                    scanMessage.textContent = 'Student identified successfully';
                    scannerStatus.textContent = 'Authenticated';
                    scannerStatus.className = 'cyber-badge success';
                } else {
                    throw new Error(data.message || 'Authentication failed');
                }

            } catch (error) {
                console.error('Biometric scan error:', error);
                scanTitle.textContent = 'Scan Failed';
                scanMessage.textContent = error.message || 'Please try again';
                scannerStatus.textContent = 'Error';
                scannerStatus.className = 'cyber-badge danger';

                setTimeout(() => {
                    scanTitle.textContent = 'Place Your Finger or Face';
                    scanMessage.textContent = 'Use your device\'s biometric sensor to authenticate';
                    scannerStatus.textContent = 'Scanner Ready';
                    scannerStatus.className = 'cyber-badge success';
                }, 3000);
            }
        }

        function showStudentInfo(student, classes, todayAttendance, credentialId) {
            currentStudentId = student.id;
            currentCredentialId = credentialId;

            document.getElementById('studentName').textContent = student.name;
            document.getElementById('studentCode').textContent = student.code;

            const classList = document.getElementById('classList');
            classList.innerHTML = '';

            const markedClassIds = todayAttendance.map(a => a.class_id);

            classes.forEach(cls => {
                const isMarked = markedClassIds.includes(cls.id);
                const card = document.createElement('div');
                card.className = 'class-card' + (isMarked ? ' already-marked' : '');
                card.innerHTML = `
                    <h4>${cls.class_name}</h4>
                    <p><i class="fas fa-code"></i> ${cls.class_code}</p>
                    <p><i class="fas fa-clock"></i> ${cls.start_time} - ${cls.end_time}</p>
                    <p><i class="fas fa-chalkboard-teacher"></i> ${cls.teacher_first || ''} ${cls.teacher_last || ''}</p>
                    ${isMarked ? '<span class="cyber-badge success" style="margin-top: 10px;">Already Marked</span>' : ''}
                `;

                if (!isMarked) {
                    card.onclick = () => markAttendance(cls.id, cls.class_name);
                }

                classList.appendChild(card);
            });

            document.getElementById('studentInfo').classList.add('show');
        }

        async function markAttendance(classId, className) {
            if (!confirm(`Mark attendance for ${className}?`)) return;

            try {
                const response = await fetch('../api/biometric-quick-scan.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=mark_attendance&student_id=${currentStudentId}&class_id=${classId}&credential_id=${encodeURIComponent(currentCredentialId)}`
                });

                const data = await response.json();

                if (data.success) {
                    alert(`✓ Attendance marked successfully!\nStatus: ${data.status.toUpperCase()}\nTime: ${data.time}`);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Mark attendance error:', error);
                alert('Failed to mark attendance. Please try again.');
            }
        }

        async function loadRecentScans() {
            try {
                const response = await fetch('../api/biometric-quick-scan.php?action=get_recent_scans');
                const data = await response.json();

                if (data.success && data.scans.length > 0) {
                    const container = document.getElementById('recentScans');
                    container.innerHTML = '';

                    data.scans.forEach(scan => {
                        const initials = scan.full_name.split(' ').map(n => n[0]).join('').toUpperCase();
                        const timeAgo = getTimeAgo(new Date(scan.created_at));

                        const entry = document.createElement('div');
                        entry.className = 'scan-entry';
                        entry.innerHTML = `
                            <div class="scan-entry-icon">${initials}</div>
                            <div class="scan-entry-info">
                                <div class="scan-entry-name">${scan.full_name}</div>
                                <div class="scan-entry-details">
                                    <span class="cyber-badge ${scan.role}">${scan.role}</span>
                                    ${scan.person_code ? `<span style="margin-left: 10px;">${scan.person_code}</span>` : ''}
                                    <span style="margin-left: 10px;">${timeAgo}</span>
                                </div>
                            </div>
                        `;
                        container.appendChild(entry);
                    });
                } else {
                    document.getElementById('recentScans').innerHTML = '<p style="color: var(--text-muted); text-align: center;">No recent scans</p>';
                }
            } catch (error) {
                console.error('Load recent scans error:', error);
                document.getElementById('recentScans').innerHTML = '<p style="color: var(--text-danger); text-align: center;">Failed to load recent scans</p>';
            }
        }

        function getTimeAgo(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' mins ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
            return Math.floor(seconds / 86400) + ' days ago';
        }

        // Load recent scans on page load
        loadRecentScans();

        // Refresh recent scans every 30 seconds
        setInterval(loadRecentScans, 30000);
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>