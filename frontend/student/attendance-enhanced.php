<?php

/**
 * Enhanced Attendance History - With Correction Requests
 * Version: 2.1.0
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$class_filter = $_GET['class_id'] ?? '';
$status_filter = $_GET['status'] ?? '';

$page_title = 'Attendance History';
$page_icon = 'clipboard-check';
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
    <?php include '../includes/sams-head-bootstrap.php'; ?>


    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: rgba(0, 191, 255, 0.05);
            border: 2px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-box.success {
            border-color: rgba(0, 255, 127, 0.5);
            background: rgba(0, 255, 127, 0.05);
        }

        .stat-box.danger {
            border-color: rgba(255, 69, 0, 0.5);
            background: rgba(255, 69, 0, 0.05);
        }

        .stat-box.warning {
            border-color: rgba(255, 165, 0, 0.5);
            background: rgba(255, 165, 0, 0.05);
        }

        .stat-number {
            font-size: 42px;
            font-weight: 700;
            font-family: 'Orbitron', monospace;
            color: #00BFFF;
            margin-bottom: 5px;
        }

        .stat-box.success .stat-number {
            color: #00FF7F;
        }

        .stat-box.danger .stat-number {
            color: #FF4500;
        }

        .stat-box.warning .stat-number {
            color: #FFA500;
        }

        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid rgba(0, 191, 255, 0.2);
            padding-bottom: 10px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: transparent;
            border: 2px solid rgba(0, 191, 255, 0.3);
            border-radius: 8px;
            color: #00BFFF;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .tab-btn:hover {
            background: rgba(0, 191, 255, 0.1);
            border-color: #00BFFF;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #00BFFF, #8A2BE2);
            border-color: transparent;
            color: #000;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            background: rgba(0, 191, 255, 0.1);
            padding: 15px;
            text-align: left;
            color: #00BFFF;
            font-weight: 700;
            border-bottom: 2px solid rgba(0, 191, 255, 0.3);
        }

        .attendance-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .attendance-table tr:hover {
            background: rgba(0, 191, 255, 0.05);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-present {
            background: rgba(0, 255, 127, 0.2);
            color: #00FF7F;
            border: 1px solid #00FF7F;
        }

        .status-absent {
            background: rgba(255, 69, 0, 0.2);
            color: #FF4500;
            border: 1px solid #FF4500;
        }

        .status-late {
            background: rgba(255, 165, 0, 0.2);
            color: #FFA500;
            border: 1px solid #FFA500;
        }

        .status-excused {
            background: rgba(138, 43, 226, 0.2);
            color: #8A2BE2;
            border: 1px solid #8A2BE2;
        }

        .correction-badge {
            background: rgba(255, 165, 0, 0.1);
            border: 1px solid #FFA500;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            color: #FFA500;
        }

        .correction-badge.approved {
            background: rgba(0, 255, 127, 0.1);
            border-color: #00FF7F;
            color: #00FF7F;
        }

        .correction-badge.rejected {
            background: rgba(255, 69, 0, 0.1);
            border-color: #FF4500;
            color: #FF4500;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(10, 25, 47, 0.95), rgba(15, 32, 60, 0.95));
            border: 2px solid #00BFFF;
            border-radius: 15px;
            max-width: 600px;
            width: 100%;
            padding: 30px;
            box-shadow: 0 10px 50px rgba(0, 191, 255, 0.3);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
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
                    <button class="cyber-btn btn-sm" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="cyber-btn btn-sm" onclick="refreshData()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </header>

            <div class="app-layout">
                <!-- Statistics -->
                <div id="statsGrid" class="stats-grid">
                    <!-- Populated via JavaScript -->
                </div>

                <!-- Tabs -->
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="switchTab('records')">
                        <i class="fas fa-list"></i> Attendance Records
                    </button>
                    <button class="tab-btn" onclick="switchTab('corrections')">
                        <i class="fas fa-edit"></i> Correction Requests
                    </button>
                    <button class="tab-btn" onclick="switchTab('analytics')">
                        <i class="fas fa-chart-line"></i> Analytics
                    </button>
                </div>

                <!-- Records Tab -->
                <div id="recordsTab" class="tab-content">
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-filter"></i>
                                <span>Filter Records</span>
                            </div>
                            <button class="cyber-btn btn-sm" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="filter-form">
                                <div class="form-group">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" id="filterStartDate" class="cyber-input" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">End Date</label>
                                    <input type="date" id="filterEndDate" class="cyber-input" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Class</label>
                                    <select id="filterClass" class="cyber-input">
                                        <option value="">All Classes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select id="filterStatus" class="cyber-input">
                                        <option value="">All Statuses</option>
                                        <option value="present">Present</option>
                                        <option value="absent">Absent</option>
                                        <option value="late">Late</option>
                                        <option value="excused">Excused</option>
                                    </select>
                                </div>
                                <button class="cyber-btn" onclick="applyFilters()">
                                    <i class="fas fa-search"></i> Apply
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="holo-card" style="margin-top: 20px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-history"></i>
                                <span>Attendance History</span>
                            </div>
                            <span class="cyber-badge" id="recordCount">0 Records</span>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x: auto;">
                                <table class="attendance-table" id="attendanceTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Method</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendanceRecords">
                                        <!-- Populated via JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Corrections Tab -->
                <div id="correctionsTab" class="tab-content" style="display: none;">
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-list-alt"></i>
                                <span>My Correction Requests</span>
                            </div>
                            <button class="cyber-btn btn-sm" onclick="openCorrectionModal()">
                                <i class="fas fa-plus"></i> New Request
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="correctionsList">
                                <!-- Populated via JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Tab -->
                <div id="analyticsTab" class="tab-content" style="display: none;">
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-chart-bar"></i>
                                <span>Attendance Analytics</span>
                            </div>
                            <select class="cyber-input" style="width: 150px;" onchange="loadAnalytics(this.value)">
                                <option value="month">This Month</option>
                                <option value="semester">This Semester</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                        <div class="card-body">
                            <div id="analyticsContent">
                                <!-- Populated via JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Correction Request Modal -->
    <div id="correctionModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #00BFFF;">
                    <i class="fas fa-edit"></i> Request Attendance Correction
                </h2>
                <button class="cyber-btn btn-sm btn-secondary" onclick="closeCorrectionModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form id="correctionForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Class *</label>
                    <select id="correctionClass" name="class_id" class="cyber-input" required>
                        <option value="">Select class...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" id="correctionDate" name="attendance_date" class="cyber-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Requested Status *</label>
                    <select name="requested_status" class="cyber-input" required>
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="excused">Excused</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason for Correction *</label>
                    <textarea name="reason" class="cyber-input" rows="4" placeholder="Explain why this correction is needed..." required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Supporting Document (Optional)</label>
                    <input type="file" name="document" class="cyber-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small style="color: #888; display: block; margin-top: 5px;">
                        Max 5MB. Accepted: PDF, JPG, PNG, DOC, DOCX
                    </small>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="cyber-btn" style="flex: 1;">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                    <button type="button" class="cyber-btn btn-secondary" onclick="closeCorrectionModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentTab = 'records';
        let enrolledClasses = [];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadEnrolledClasses();
            loadStats();
            loadRecords();

            // Form submission
            document.getElementById('correctionForm').addEventListener('submit', submitCorrection);
        });

        // Switch tabs
        function switchTab(tab) {
            currentTab = tab;

            // Update button states
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.tab-btn').classList.add('active');

            // Show/hide content
            document.getElementById('recordsTab').style.display = tab === 'records' ? 'block' : 'none';
            document.getElementById('correctionsTab').style.display = tab === 'corrections' ? 'block' : 'none';
            document.getElementById('analyticsTab').style.display = tab === 'analytics' ? 'block' : 'none';

            // Load data
            if (tab === 'corrections') {
                loadCorrections();
            } else if (tab === 'analytics') {
                loadAnalytics('month');
            }
        }

        // Load enrolled classes
        function loadEnrolledClasses() {
            fetch('api/attendance-enhanced.php?action=get_today_classes')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        enrolledClasses = data.classes;
                        populateClassFilters();
                    }
                });
        }

        // Populate class filter dropdowns
        function populateClassFilters() {
            const filterSelect = document.getElementById('filterClass');
            const correctionSelect = document.getElementById('correctionClass');

            enrolledClasses.forEach(cls => {
                const option = new Option(cls.name + ' (' + cls.class_code + ')', cls.id);
                filterSelect.add(option.cloneNode(true));
                correctionSelect.add(option);
            });
        }

        // Load statistics
        function loadStats() {
            const startDate = document.getElementById('filterStartDate').value;
            const endDate = document.getElementById('filterEndDate').value;

            fetch(`api/attendance-enhanced.php?action=get_analytics&period=custom&start=${startDate}&end=${endDate}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.analytics.overall;
                        const statsGrid = document.getElementById('statsGrid');
                        statsGrid.innerHTML = `
                            <div class="stat-box success">
                                <div class="stat-number">${stats.present || 0}</div>
                                <div class="stat-label">Present</div>
                            </div>
                            <div class="stat-box danger">
                                <div class="stat-number">${stats.absent || 0}</div>
                                <div class="stat-label">Absent</div>
                            </div>
                            <div class="stat-box warning">
                                <div class="stat-number">${stats.late || 0}</div>
                                <div class="stat-label">Late</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-number">${stats.attendance_rate || 0}%</div>
                                <div class="stat-label">Attendance Rate</div>
                            </div>
                        `;
                    }
                });
        }

        // Load attendance records
        function loadRecords() {
            const startDate = document.getElementById('filterStartDate').value;
            const endDate = document.getElementById('filterEndDate').value;
            const classId = document.getElementById('filterClass').value;
            const status = document.getElementById('filterStatus').value;

            let url = `api/attendance-enhanced.php?action=get_attendance_history&start_date=${startDate}&end_date=${endDate}`;
            if (classId) url += `&class_id=${classId}`;
            if (status) url += `&status=${status}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderRecords(data.records);
                        document.getElementById('recordCount').textContent = data.count + ' Records';
                    }
                });
        }

        // Render attendance records
        function renderRecords(records) {
            const tbody = document.getElementById('attendanceRecords');

            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:#888;"><i class="fas fa-inbox"></i> No records found</td></tr>';
                return;
            }

            tbody.innerHTML = records.map(record => `
                <tr>
                    <td>${new Date(record.check_in_time).toLocaleDateString()}</td>
                    <td>
                        <strong>${escapeHtml(record.class_name)}</strong><br>
                        <small style="color:#888;">${escapeHtml(record.class_code)}</small>
                    </td>
                    <td>${new Date(record.check_in_time).toLocaleTimeString()}</td>
                    <td><span class="status-badge status-${record.status}">${record.status.toUpperCase()}</span></td>
                    <td>${record.method || 'Manual'}</td>
                    <td>
                        ${record.status === 'absent' ?
                            `<button class="cyber-btn btn-sm" onclick="requestCorrection(${record.id}, '${record.class_id}', '${record.check_in_time.split(' ')[0]}')">
                                <i class="fas fa-edit"></i> Request Correction
                            </button>` :
                            '<span style="color:#888;">-</span>'
                        }
                    </td>
                </tr>
            `).join('');
        }

        // Load correction requests
        function loadCorrections() {
            fetch('api/attendance-enhanced.php?action=get_corrections')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderCorrections(data.corrections);
                    }
                });
        }

        // Render corrections
        function renderCorrections(corrections) {
            const container = document.getElementById('correctionsList');

            if (corrections.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fas fa-clipboard"></i><p>No correction requests yet</p></div>';
                return;
            }

            container.innerHTML = corrections.map(correction => `
                <div style="background: rgba(0, 191, 255, 0.05); border: 2px solid rgba(0, 191, 255, 0.2); border-radius: 12px; padding: 20px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                            <strong style="color: #00BFFF; font-size: 16px;">${escapeHtml(correction.class_name)}</strong>
                            <div style="color: #888; font-size: 13px; margin-top: 3px;">
                                Date: ${new Date(correction.attendance_date).toLocaleDateString()} •
                                Requested: ${correction.requested_status}
                            </div>
                        </div>
                        <span class="correction-badge ${correction.status}">
                            ${correction.status.toUpperCase()}
                        </span>
                    </div>
                    <div style="margin-bottom: 10px; color: #E0E0E0;">
                        <strong>Reason:</strong> ${escapeHtml(correction.reason)}
                    </div>
                    ${correction.review_notes ? `
                        <div style="padding: 10px; background: rgba(0, 255, 127, 0.05); border-left: 3px solid ${correction.status === 'approved' ? '#00FF7F' : '#FF4500'}; border-radius: 5px; margin-top: 10px;">
                            <strong>Review Notes:</strong> ${escapeHtml(correction.review_notes)}
                        </div>
                    ` : ''}
                    ${correction.status === 'pending' ? `
                        <button class="cyber-btn btn-sm btn-secondary" style="margin-top: 10px;" onclick="cancelCorrection(${correction.id})">
                            <i class="fas fa-times"></i> Cancel Request
                        </button>
                    ` : ''}
                </div>
            `).join('');
        }

        // Load analytics
        function loadAnalytics(period) {
            fetch(`api/attendance-enhanced.php?action=get_analytics&period=${period}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderAnalytics(data.analytics);
                    }
                });
        }

        // Render analytics
        function renderAnalytics(analytics) {
            const container = document.getElementById('analyticsContent');

            // Class breakdown
            const classBreakdown = analytics.class_breakdown.map(cls => `
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>${escapeHtml(cls.class_name)}</span>
                        <strong style="color: #00FF7F;">${cls.percentage}%</strong>
                    </div>
                    <div style="background: rgba(0, 191, 255, 0.1); height: 10px; border-radius: 5px; overflow: hidden;">
                        <div style="background: linear-gradient(90deg, #00BFFF, #00FF7F); height: 100%; width: ${cls.percentage}%;"></div>
                    </div>
                    <div style="font-size: 12px; color: #888; margin-top: 3px;">
                        ${cls.present} present / ${cls.total} total
                    </div>
                </div>
            `).join('');

            container.innerHTML = `
                <h3 style="color: #00BFFF; margin-bottom: 20px;">Class-wise Attendance</h3>
                ${classBreakdown || '<p style="color: #888;">No data available</p>'}
            `;
        }

        // Apply filters
        function applyFilters() {
            loadStats();
            loadRecords();
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('filterStartDate').value = '<?php echo date('Y-m-01'); ?>';
            document.getElementById('filterEndDate').value = '<?php echo date('Y-m-t'); ?>';
            document.getElementById('filterClass').value = '';
            document.getElementById('filterStatus').value = '';
            applyFilters();
        }

        // Refresh data
        function refreshData() {
            loadStats();
            loadRecords();
            if (currentTab === 'corrections') loadCorrections();
            if (currentTab === 'analytics') loadAnalytics('month');
        }

        // Export data
        function exportData() {
            // Would implement export to CSV/PDF
            alert('Export feature coming soon!');
        }

        // Open correction modal
        function openCorrectionModal(recordId = null, classId = null, date = null) {
            if (classId) {
                document.getElementById('correctionClass').value = classId;
                document.getElementById('correctionDate').value = date;
            }
            document.getElementById('correctionModal').classList.add('active');
        }

        // Close correction modal
        function closeCorrectionModal() {
            document.getElementById('correctionModal').classList.remove('active');
            document.getElementById('correctionForm').reset();
        }

        // Request correction shortcut
        function requestCorrection(recordId, classId, date) {
            openCorrectionModal(recordId, classId, date);
        }

        // Submit correction
        function submitCorrection(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'submit_correction');

            fetch('api/attendance-enhanced.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ Correction request submitted successfully!');
                        closeCorrectionModal();
                        if (currentTab === 'corrections') loadCorrections();
                    } else {
                        alert('✗ ' + data.error);
                    }
                })
                .catch(err => {
                    alert('✗ Failed to submit request');
                    console.error(err);
                });
        }

        // Cancel correction
        function cancelCorrection(correctionId) {
            if (!confirm('Cancel this correction request?')) return;

            const formData = new FormData();
            formData.append('action', 'cancel_correction');
            formData.append('correction_id', correctionId);

            fetch('api/attendance-enhanced.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ Request cancelled');
                        loadCorrections();
                    } else {
                        alert('✗ ' + data.error);
                    }
                });
        }

        // Utility
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>