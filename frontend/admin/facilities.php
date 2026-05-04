<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Facilities Management';
$page_icon = 'building';
$full_name = $_SESSION['full_name'];

// Handle add/update facility
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_facility'])) {
        $name = sanitize($_POST['name']);
        $type = sanitize($_POST['type']);
        $capacity = intval($_POST['capacity']);
        $location = sanitize($_POST['location']);
        $status = $_POST['status'];

        // Create facilities table if it doesn't exist
        db()->query("CREATE TABLE IF NOT EXISTS facilities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(50) NOT NULL,
            capacity INT DEFAULT 0,
            location VARCHAR(255),
            status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        db()->insert('facilities', [
            'name' => $name,
            'type' => $type,
            'capacity' => $capacity,
            'location' => $location,
            'status' => $status,
            'description' => sanitize($_POST['description'] ?? '')
        ]);

        $success_msg = "Facility added successfully!";
    }
}

// Get all facilities
$facilities = [];
try {
    $facilities = db()->fetchAll("SELECT * FROM facilities ORDER BY type, name");
} catch (Exception $e) {
    // Table doesn't exist yet
}

// Get statistics
$total_facilities = count($facilities);
$available = count(array_filter($facilities, fn($f) => $f['status'] === 'available'));
$occupied = count(array_filter($facilities, fn($f) => $f['status'] === 'occupied'));
$maintenance = count(array_filter($facilities, fn($f) => $f['status'] === 'maintenance'));
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
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .facility-card {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.05), rgba(255, 0, 255, 0.05));
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .facility-card:hover {
            transform: translateY(-5px);
            border-color: var(--cyber-cyan);
        }

        .facility-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .facility-name {
            color: var(--cyber-cyan);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .facility-info {
            margin-top: 15px;
        }

        .facility-info-row {
            display: flex;
            align-items: center;
            padding: 8px 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .facility-info-row i {
            width: 20px;
            color: var(--cyber-cyan);
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
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
                    <div class="biometric-orb" title="Quick Scan" onclick="window.location.href='biometric-scan.php'">
                        <i class="fas fa-fingerprint"></i>
                    </div>
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
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success"><?php echo $success_msg; ?></div>
                <?php endif; ?>

                <!-- Statistics -->
                <section class="orb-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-building"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_facilities; ?></div>
                            <div class="stat-label">Total Facilities</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $available; ?></div>
                            <div class="stat-label">Available</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $occupied; ?></div>
                            <div class="stat-label">Occupied</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon orange"><i class="fas fa-tools"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $maintenance; ?></div>
                            <div class="stat-label">Maintenance</div>
                        </div>
                    </div>
                </section>

                <!-- Facilities List -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list"></i>
                            <span>All Facilities</span>
                        </div>
                        <button class="cyber-btn primary" onclick="document.getElementById('addModal').classList.add('show')">
                            <i class="fas fa-plus"></i> Add Facility
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($facilities)): ?>
                            <p style="color: var(--text-muted); text-align: center; padding: 40px;">
                                <i class="fas fa-building" style="font-size: 3rem; display: block; margin-bottom: 20px;"></i>
                                No facilities added yet. Click "Add Facility" to get started.
                            </p>
                        <?php else: ?>
                            <div class="facilities-grid">
                                <?php foreach ($facilities as $facility): ?>
                                    <div class="facility-card">
                                        <div class="facility-header">
                                            <div class="facility-name"><?php echo htmlspecialchars($facility['name']); ?></div>
                                            <span class="status-badge <?php echo $facility['status']; ?>">
                                                <?php echo ucfirst($facility['status']); ?>
                                            </span>
                                        </div>
                                        <div style="margin: 10px 0;">
                                            <span class="cyber-badge secondary"><?php echo htmlspecialchars($facility['type']); ?></span>
                                        </div>
                                        <div class="facility-info">
                                            <div class="facility-info-row">
                                                <i class="fas fa-users"></i>
                                                <span>Capacity: <?php echo $facility['capacity']; ?> persons</span>
                                            </div>
                                            <div class="facility-info-row">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($facility['location']); ?></span>
                                            </div>
                                            <?php if ($facility['description']): ?>
                                                <div class="facility-info-row">
                                                    <i class="fas fa-info-circle"></i>
                                                    <span><?php echo htmlspecialchars($facility['description']); ?></span>
                                                </div>
                                            <?php endif; ?>
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

    <!-- Add Facility Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3 style="color: var(--cyber-cyan); margin-bottom: 20px;">
                <i class="fas fa-plus-circle"></i> Add New Facility
            </h3>
            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Facility Name</label>
                    <input type="text" name="name" class="cyber-input" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Type</label>
                    <select name="type" class="cyber-input" required>
                        <option value="Classroom">Classroom</option>
                        <option value="Laboratory">Laboratory</option>
                        <option value="Library">Library</option>
                        <option value="Sports">Sports Facility</option>
                        <option value="Auditorium">Auditorium</option>
                        <option value="Office">Office</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Capacity</label>
                    <input type="number" name="capacity" class="cyber-input" required min="1">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Location</label>
                    <input type="text" name="location" class="cyber-input" required placeholder="e.g., Building A, Floor 2">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Status</label>
                    <select name="status" class="cyber-input">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: var(--text-muted); margin-bottom: 5px;">Description (Optional)</label>
                    <textarea name="description" class="cyber-input" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="add_facility" class="cyber-btn primary">
                        <i class="fas fa-save"></i> Add Facility
                    </button>
                    <button type="button" class="cyber-btn secondary" onclick="document.getElementById('addModal').classList.remove('show')">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>