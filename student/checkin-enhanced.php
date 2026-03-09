<?php

/**
 * Enhanced Student Check-in - With QR Code & Geolocation
 * Version: 2.1.0
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$page_title = 'Smart Check-in';
$page_icon = 'qrcode';
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

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <style>
        .checkin-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .method-card {
            background: rgba(0, 191, 255, 0.05);
            border: 2px solid rgba(0, 191, 255, 0.2);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .method-card:hover {
            border-color: #00BFFF;
            background: rgba(0, 191, 255, 0.1);
            transform: translateY(-5px);
        }

        .method-card.active {
            border-color: #00FF7F;
            background: rgba(0, 255, 127, 0.1);
        }

        .method-icon {
            font-size: 48px;
            color: #00BFFF;
            margin-bottom: 15px;
        }

        .method-card.active .method-icon {
            color: #00FF7F;
        }

        #qr-video-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            border: 2px solid #00BFFF;
        }

        #qr-video {
            width: 100%;
            height: auto;
            display: block;
        }

        .qr-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 3px solid #00FF7F;
            border-radius: 10px;
            pointer-events: none;
        }

        .qr-overlay::before,
        .qr-overlay::after {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            border: 4px solid #00FF7F;
        }

        .qr-overlay::before {
            top: -4px;
            left: -4px;
            border-right: none;
            border-bottom: none;
        }

        .qr-overlay::after {
            bottom: -4px;
            right: -4px;
            border-left: none;
            border-top: none;
        }

        .manual-checkin-grid {
            display: grid;
            gap: 15px;
        }

        .class-checkin-card {
            background: rgba(0, 191, 255, 0.05);
            border: 2px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }

        .class-checkin-card:hover {
            border-color: #00BFFF;
            background: rgba(0, 191, 255, 0.1);
        }

        .class-checkin-card.checked {
            border-color: #00FF7F;
            background: rgba(0, 255, 127, 0.05);
        }

        .location-status {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .location-status.success {
            background: rgba(0, 255, 127, 0.1);
            border: 2px solid #00FF7F;
            color: #00FF7F;
        }

        .location-status.error {
            background: rgba(255, 69, 0, 0.1);
            border: 2px solid #FF4500;
            color: #FF4500;
        }

        .location-status.pending {
            background: rgba(255, 165, 0, 0.1);
            border: 2px solid #FFA500;
            color: #FFA500;
        }

        .today-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-box {
            background: rgba(0, 255, 127, 0.05);
            border: 2px solid rgba(0, 255, 127, 0.3);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }

        .summary-number {
            font-size: 36px;
            font-weight: 700;
            color: #00FF7F;
            font-family: 'Orbitron', monospace;
        }

        .summary-label {
            font-size: 14px;
            color: #888;
            margin-top: 5px;
        }

        .camera-controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }

        @keyframes scan-line {
            0% {
                top: 0;
            }

            100% {
                top: 100%;
            }
        }

        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #00FF7F, transparent);
            animation: scan-line 2s linear infinite;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar-nav.php'; ?>

    <div class="app-layout">
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <button class="cyber-btn btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </header>

            <div class="app-layout">
                <!-- Today's Summary -->
                <div id="todaySummary" class="today-summary">
                    <div class="summary-box">
                        <div class="summary-number" id="checkedInCount">-</div>
                        <div class="summary-label">Checked In</div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-number" id="remainingCount">-</div>
                        <div class="summary-label">Remaining</div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-number" id="currentStreak">-</div>
                        <div class="summary-label">Day Streak</div>
                    </div>
                </div>

                <!-- Check-in Methods -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-fingerprint"></i>
                            <span>Choose Check-in Method</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="checkin-methods">
                            <div class="method-card active" data-method="qr">
                                <div class="method-icon"><i class="fas fa-qrcode"></i></div>
                                <h3>QR Code Scan</h3>
                                <p>Scan classroom QR code</p>
                            </div>
                            <div class="method-card" data-method="manual">
                                <div class="method-icon"><i class="fas fa-mouse-pointer"></i></div>
                                <h3>Manual Selection</h3>
                                <p>Choose class from list</p>
                            </div>
                            <div class="method-card" data-method="location">
                                <div class="method-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <h3>Auto Location</h3>
                                <p>Auto-detect via GPS</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Scanner Section -->
                <div id="qrSection" class="holo-card" style="margin-top: 20px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-camera"></i>
                            <span>QR Code Scanner</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="qr-video-container" style="display:none;">
                            <video id="qr-video" autoplay playsinline></video>
                            <div class="qr-overlay"></div>
                            <div class="scan-line"></div>
                        </div>
                        <canvas id="qr-canvas" style="display:none;"></canvas>

                        <div class="camera-controls">
                            <button id="startScanBtn" class="cyber-btn">
                                <i class="fas fa-play"></i> Start Scanning
                            </button>
                            <button id="stopScanBtn" class="cyber-btn btn-secondary" style="display:none;">
                                <i class="fas fa-stop"></i> Stop Scanning
                            </button>
                        </div>

                        <div id="qrStatus" style="margin-top: 20px; text-align: center; color: #888;">
                            <i class="fas fa-info-circle"></i> Position QR code within the frame
                        </div>
                    </div>
                </div>

                <!-- Manual Check-in Section -->
                <div id="manualSection" class="holo-card" style="margin-top: 20px; display: none;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list"></i>
                            <span>Today's Classes</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="manualClassList" class="manual-checkin-grid">
                            <!-- Populated via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Location-based Section -->
                <div id="locationSection" class="holo-card" style="margin-top: 20px; display: none;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Location-Based Check-in</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="locationStatus" class="location-status pending">
                            <i class="fas fa-spinner fa-spin"></i> Detecting your location...
                        </div>
                        <div id="nearbyClassList">
                            <!-- Populated via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Recent Check-ins -->
                <div class="holo-card" style="margin-top: 20px;">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-history"></i>
                            <span>Today's Check-ins</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="recentCheckins">
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list"></i>
                                <p>No check-ins yet today</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentMethod = 'qr';
        let qrScanning = false;
        let videoStream = null;
        let todayClasses = [];
        let userLocation = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadTodayClasses();
            loadStreak();
            loadRecentCheckins();

            // Method switching
            document.querySelectorAll('.method-card').forEach(card => {
                card.addEventListener('click', function() {
                    switchMethod(this.dataset.method);
                });
            });

            // QR Scanner controls
            document.getElementById('startScanBtn').addEventListener('click', startQRScanner);
            document.getElementById('stopScanBtn').addEventListener('click', stopQRScanner);
        });

        // Switch check-in method
        function switchMethod(method) {
            currentMethod = method;

            // Update UI
            document.querySelectorAll('.method-card').forEach(card => {
                card.classList.toggle('active', card.dataset.method === method);
            });

            // Show/hide sections
            document.getElementById('qrSection').style.display = method === 'qr' ? 'block' : 'none';
            document.getElementById('manualSection').style.display = method === 'manual' ? 'block' : 'none';
            document.getElementById('locationSection').style.display = method === 'location' ? 'block' : 'none';

            // Stop QR if switching away
            if (method !== 'qr' && qrScanning) {
                stopQRScanner();
            }

            // Start location detection
            if (method === 'location') {
                detectLocation();
            }
        }

        // Load today's classes
        function loadTodayClasses() {
            fetch('api/attendance-enhanced.php?action=get_today_classes')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        todayClasses = data.classes;
                        updateSummary();
                        renderManualList();
                    }
                })
                .catch(err => console.error('Error loading classes:', err));
        }

        // Update summary stats
        function updateSummary() {
            const checkedIn = todayClasses.filter(c => c.attendance_id).length;
            const remaining = todayClasses.length - checkedIn;

            document.getElementById('checkedInCount').textContent = checkedIn;
            document.getElementById('remainingCount').textContent = remaining;
        }

        // Load attendance streak
        function loadStreak() {
            fetch('api/attendance-enhanced.php?action=get_streak')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('currentStreak').textContent = data.streak.current;
                    }
                })
                .catch(err => console.error('Error loading streak:', err));
        }

        // Start QR scanner
        function startQRScanner() {
            navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment'
                    }
                })
                .then(stream => {
                    videoStream = stream;
                    const video = document.getElementById('qr-video');
                    const container = document.getElementById('qr-video-container');

                    video.srcObject = stream;
                    container.style.display = 'block';
                    document.getElementById('startScanBtn').style.display = 'none';
                    document.getElementById('stopScanBtn').style.display = 'inline-block';

                    qrScanning = true;
                    scanQRCode();
                })
                .catch(err => {
                    alert('Camera access denied. Please enable camera permissions.');
                    console.error('Camera error:', err);
                });
        }

        // Stop QR scanner
        function stopQRScanner() {
            qrScanning = false;
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
            }
            document.getElementById('qr-video-container').style.display = 'none';
            document.getElementById('startScanBtn').style.display = 'inline-block';
            document.getElementById('stopScanBtn').style.display = 'none';
        }

        // Scan QR code from video
        function scanQRCode() {
            if (!qrScanning) return;

            const video = document.getElementById('qr-video');
            const canvas = document.getElementById('qr-canvas');
            const context = canvas.getContext('2d');

            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);

                const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);

                if (code) {
                    handleQRCode(code.data);
                    return;
                }
            }

            requestAnimationFrame(scanQRCode);
        }

        // Handle scanned QR code
        function handleQRCode(qrData) {
            stopQRScanner();
            document.getElementById('qrStatus').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing QR code...';

            // Get geolocation for verification
            navigator.geolocation.getCurrentPosition(
                position => {
                    processQRCheckin(qrData, position.coords.latitude, position.coords.longitude);
                },
                () => {
                    processQRCheckin(qrData, null, null);
                }
            );
        }

        // Process QR check-in
        function processQRCheckin(qrCode, latitude, longitude) {
            const formData = new FormData();
            formData.append('action', 'qr_checkin');
            formData.append('qr_code', qrCode);
            if (latitude) formData.append('latitude', latitude);
            if (longitude) formData.append('longitude', longitude);

            fetch('api/attendance-enhanced.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message);
                        loadTodayClasses();
                        loadRecentCheckins();
                    } else {
                        showError(data.error);
                    }
                    document.getElementById('qrStatus').innerHTML = '<i class="fas fa-info-circle"></i> Position QR code within the frame';
                })
                .catch(err => {
                    showError('Failed to process check-in');
                    console.error(err);
                });
        }

        // Render manual class list
        function renderManualList() {
            const container = document.getElementById('manualClassList');
            container.innerHTML = '';

            if (todayClasses.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-times"></i><p>No classes scheduled today</p></div>';
                return;
            }

            todayClasses.forEach(cls => {
                const isChecked = cls.attendance_id !== null;
                const card = document.createElement('div');
                card.className = 'class-checkin-card' + (isChecked ? ' checked' : '');
                card.innerHTML = `
                    <div>
                        <div style="font-size: 18px; font-weight: 700; color: #00BFFF; margin-bottom: 5px;">
                            ${escapeHtml(cls.name)}
                        </div>
                        <div style="font-size: 14px; color: #888;">
                            ${escapeHtml(cls.class_code)} • ${cls.start_time} - ${cls.end_time}
                            ${cls.room ? ' • Room ' + escapeHtml(cls.room) : ''}
                        </div>
                        ${isChecked ? `<div style="margin-top: 5px; color: #00FF7F;"><i class="fas fa-check"></i> Checked in at ${new Date(cls.check_in_time).toLocaleTimeString()}</div>` : ''}
                    </div>
                    <div>
                        ${isChecked
                            ? '<span style="color: #00FF7F; font-size: 24px;"><i class="fas fa-check-circle"></i></span>'
                            : `<button class="cyber-btn btn-sm" onclick="manualCheckin(${cls.id})"><i class="fas fa-fingerprint"></i> Check In</button>`
                        }
                    </div>
                `;
                container.appendChild(card);
            });
        }

        // Manual check-in
        function manualCheckin(classId) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    verifyAndCheckin(classId, position.coords.latitude, position.coords.longitude);
                },
                () => {
                    if (confirm('Location access denied. Check in anyway?')) {
                        verifyAndCheckin(classId, null, null);
                    }
                }
            );
        }

        // Verify location and check in
        function verifyAndCheckin(classId, latitude, longitude) {
            const formData = new FormData();
            formData.append('action', 'verify_location');
            formData.append('class_id', classId);
            if (latitude) formData.append('latitude', latitude);
            if (longitude) formData.append('longitude', longitude);

            fetch('api/attendance-enhanced.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.valid) {
                        // Location verified, proceed with check-in
                        recordAttendance(classId);
                    } else if (data.success && !data.valid) {
                        if (confirm(`You are ${data.distance}m away from the class location. Check in anyway?`)) {
                            recordAttendance(classId);
                        }
                    } else {
                        // No location requirement, check in directly
                        recordAttendance(classId);
                    }
                })
                .catch(err => {
                    console.error(err);
                    recordAttendance(classId);
                });
        }

        // Record attendance
        function recordAttendance(classId) {
            const formData = new FormData();
            formData.append('action', 'manual_checkin');
            formData.append('class_id', classId);

            fetch('../api/attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showSuccess('Successfully checked in!');
                        loadTodayClasses();
                        loadRecentCheckins();
                    } else {
                        showError(data.error || 'Failed to check in');
                    }
                })
                .catch(err => {
                    showError('Network error. Please try again.');
                    console.error(err);
                });
        }

        // Detect user location
        function detectLocation() {
            const statusDiv = document.getElementById('locationStatus');
            statusDiv.className = 'location-status pending';
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting your location...';

            navigator.geolocation.getCurrentPosition(
                position => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    statusDiv.className = 'location-status success';
                    statusDiv.innerHTML = '<i class="fas fa-check-circle"></i> Location detected successfully';
                    findNearbyClasses();
                },
                error => {
                    statusDiv.className = 'location-status error';
                    statusDiv.innerHTML = '<i class="fas fa-times-circle"></i> Unable to detect location. Please enable location services.';
                }
            );
        }

        // Find nearby classes
        function findNearbyClasses() {
            // This would compare user location with class locations
            // For now, just show all today's classes
            const container = document.getElementById('nearbyClassList');
            container.innerHTML = '<p style="color: #888; text-align: center;">Select a class to check in:</p>';

            todayClasses.forEach(cls => {
                if (!cls.attendance_id) {
                    const btn = document.createElement('button');
                    btn.className = 'cyber-btn';
                    btn.style.width = '100%';
                    btn.style.marginTop = '10px';
                    btn.innerHTML = `<i class="fas fa-fingerprint"></i> Check in to ${escapeHtml(cls.name)}`;
                    btn.onclick = () => manualCheckin(cls.id);
                    container.appendChild(btn);
                }
            });
        }

        // Load recent check-ins
        function loadRecentCheckins() {
            fetch('api/attendance-enhanced.php?action=get_attendance_history&start_date=' + new Date().toISOString().split('T')[0] + '&end_date=' + new Date().toISOString().split('T')[0])
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.records.length > 0) {
                        const container = document.getElementById('recentCheckins');
                        container.innerHTML = '';

                        data.records.forEach(record => {
                            const div = document.createElement('div');
                            div.style.cssText = 'padding: 15px; background: rgba(0,255,127,0.05); border-left: 3px solid #00FF7F; margin-bottom: 10px; border-radius: 8px;';
                            div.innerHTML = `
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: #00BFFF;">${escapeHtml(record.class_name)}</strong>
                                        <div style="font-size: 13px; color: #888; margin-top: 3px;">
                                            ${record.class_code} ${record.room ? '• Room ' + escapeHtml(record.room) : ''}
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="color: #00FF7F; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> ${record.status.toUpperCase()}
                                        </div>
                                        <div style="font-size: 13px; color: #888; margin-top: 3px;">
                                            ${new Date(record.check_in_time).toLocaleTimeString()}
                                        </div>
                                    </div>
                                </div>
                            `;
                            container.appendChild(div);
                        });
                    }
                });
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showSuccess(message) {
            alert('✓ ' + message);
        }

        function showError(message) {
            alert('✗ ' + message);
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>