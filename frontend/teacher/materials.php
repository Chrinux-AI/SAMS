<?php

/**
 * Materials Upload & Management System
 * Teachers can upload/share resources (notes, videos, PDFs), organize by topic, track downloads
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher('../login.php');

$teacher_id = $_SESSION['assigned_id'];
$full_name = $_SESSION['full_name'];
$message = '';
$message_type = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
    $class_id = (int)$_POST['class_id'];
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $topic = sanitize($_POST['topic']);
    $material_type = sanitize($_POST['material_type']);

    // Verify teacher owns this class
    $class = db()->fetchOne("SELECT * FROM classes WHERE id = ? AND teacher_id = ?", [$class_id, $teacher_id]);

    if (!$class) {
        $message = 'You do not have permission to upload materials to this class!';
        $message_type = 'error';
    } else {
        // Handle file upload
        if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/materials/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_info = pathinfo($_FILES['material_file']['name']);
            $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'mp4', 'mp3', 'zip'];

            if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                $message = 'File type not allowed. Allowed: PDF, DOC, PPT, images, videos, ZIP';
                $message_type = 'error';
            } elseif ($_FILES['material_file']['size'] > 50 * 1024 * 1024) { // 50MB limit
                $message = 'File size exceeds 50MB limit!';
                $message_type = 'error';
            } else {
                $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_info['basename']);
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['material_file']['tmp_name'], $filepath)) {
                    db()->insert('class_materials', [
                        'class_id' => $class_id,
                        'teacher_id' => $teacher_id,
                        'title' => $title,
                        'description' => $description,
                        'topic' => $topic,
                        'file_name' => $filename,
                        'file_path' => $filepath,
                        'file_size' => $_FILES['material_file']['size'],
                        'file_type' => $file_info['extension'],
                        'material_type' => $material_type,
                        'uploaded_at' => date('Y-m-d H:i:s'),
                        'version' => 1
                    ]);

                    log_activity($_SESSION['user_id'], 'upload', 'class_materials', db()->lastInsertId(), "Uploaded material: {$title}");

                    $message = 'Material uploaded successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to upload file. Check permissions.';
                    $message_type = 'error';
                }
            }
        } else {
            $message = 'No file selected or upload error occurred.';
            $message_type = 'error';
        }
    }
}

// Handle material deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_material'])) {
    $material_id = (int)$_POST['material_id'];

    $material = db()->fetchOne("SELECT cm.* FROM class_materials cm JOIN classes c ON cm.class_id = c.id WHERE cm.id = ? AND c.class_teacher_id = ?", [$material_id, $teacher_id]);

    if ($material) {
        if (file_exists($material['file_path'])) {
            unlink($material['file_path']);
        }
        db()->delete('class_materials', 'id = ?', [$material_id]);

        $message = 'Material deleted successfully!';
        $message_type = 'success';
    }
}

// Get teacher's classes
$classes = db()->fetchAll("SELECT * FROM classes WHERE class_teacher_id = ? ORDER BY class_name", [$teacher_id]);

// Get selected class materials
$selected_class_id = isset($_GET['class']) ? (int)$_GET['class'] : null;
$materials = [];
$selected_class = null;

if ($selected_class_id) {
    $selected_class = db()->fetchOne("SELECT * FROM classes WHERE id = ? AND class_teacher_id = ?", [$selected_class_id, $teacher_id]);

    if ($selected_class) {
        $materials = db()->fetchAll("
            SELECT cm.*,
                   (SELECT COUNT(*) FROM material_downloads WHERE material_id = cm.id) as download_count,
                   (SELECT COUNT(DISTINCT student_id) FROM material_downloads WHERE material_id = cm.id) as unique_downloads
            FROM class_materials cm
            WHERE cm.class_id = ?
            ORDER BY cm.uploaded_at DESC
        ", [$selected_class_id]);
    }
}

// Get statistics
$total_materials = db()->count('class_materials cm JOIN classes c ON cm.class_id = c.id WHERE c.class_teacher_id = ?', [$teacher_id]);
$total_downloads = db()->fetchOne("
    SELECT COUNT(*) as count FROM material_downloads md
    JOIN class_materials cm ON md.material_id = cm.id
    JOIN classes c ON cm.class_id = c.id
    WHERE c.class_teacher_id = ?
", [$teacher_id])['count'];

$page_title = 'Class Materials';
$page_icon = 'folder-open';
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
        .material-card {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .material-card:hover {
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 25px rgba(0, 191, 255, 0.3);
            transform: translateX(5px);
        }

        .file-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            flex-shrink: 0;
        }

        .upload-zone {
            border: 2px dashed var(--cyber-cyan);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            background: rgba(0, 191, 255, 0.05);
            cursor: pointer;
            transition: all 0.3s;
        }

        .upload-zone:hover {
            background: rgba(0, 191, 255, 0.1);
            border-color: var(--neon-green);
        }

        .topic-badge {
            display: inline-block;
            padding: 5px 15px;
            background: rgba(0, 191, 255, 0.2);
            border-radius: 20px;
            font-size: 0.85rem;
            color: var(--cyber-cyan);
        }
    </style>
</head>

<body>
    <div class="starfield"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <?php if ($selected_class): ?>
                        <button onclick="document.getElementById('uploadModal').style.display='flex'" class="cyber-btn primary">
                            <i class="fas fa-upload"></i> Upload Material
                        </button>
                    <?php endif; ?>
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Teacher</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="app-layout">
                <?php if ($message): ?>
                    <div class="app-layout">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="holo-card" style="text-align:center;">
                        <i class="fas fa-folder-open" style="font-size:2.5rem;color:var(--cyber-cyan);margin-bottom:10px;"></i>
                        <div style="font-size:2.5rem;font-weight:900;color:var(--cyber-cyan);"><?php echo $total_materials; ?></div>
                        <div style="color:var(--text-muted);">Total Materials</div>
                    </div>
                    <div class="holo-card" style="text-align:center;">
                        <i class="fas fa-download" style="font-size:2.5rem;color:var(--neon-green);margin-bottom:10px;"></i>
                        <div style="font-size:2.5rem;font-weight:900;color:var(--neon-green);"><?php echo $total_downloads; ?></div>
                        <div style="color:var(--text-muted);">Total Downloads</div>
                    </div>
                    <div class="holo-card" style="text-align:center;">
                        <i class="fas fa-chalkboard-teacher" style="font-size:2.5rem;color:var(--hologram-purple);margin-bottom:10px;"></i>
                        <div style="font-size:2.5rem;font-weight:900;color:var(--hologram-purple);"><?php echo count($classes); ?></div>
                        <div style="color:var(--text-muted);">My Classes</div>
                    </div>
                </div>

                <!-- Class Selection -->
                <div class="holo-card" style="margin-bottom:30px;">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-school"></i> Select Class</h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:15px;">
                        <?php foreach ($classes as $class): ?>
                            <a href="?class=<?php echo $class['id']; ?>" class="cyber-btn <?php echo $selected_class_id === $class['id'] ? 'primary' : ''; ?>" style="display:block;padding:20px;text-align:left;height:auto;">
                                <div style="font-size:1.2rem;font-weight:700;margin-bottom:8px;"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                <div style="font-size:0.85rem;opacity:0.8;">
                                    <i class="fas fa-graduation-cap"></i> Grade <?php echo $class['grade']; ?> •
                                    <i class="fas fa-clock"></i> <?php echo htmlspecialchars($class['schedule']); ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($selected_class): ?>
                    <!-- Materials List -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;">
                            <i class="fas fa-books"></i> Materials for <?php echo htmlspecialchars($selected_class['class_name']); ?>
                            <span style="color:var(--text-muted);font-size:0.9rem;font-weight:400;margin-left:10px;">(<?php echo count($materials); ?> files)</span>
                        </h3>

                        <?php if (empty($materials)): ?>
                            <div style="text-align:center;padding:50px;color:var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:15px;opacity:0.3;"></i>
                                <div style="margin-bottom:20px;">No materials uploaded yet for this class</div>
                                <button onclick="document.getElementById('uploadModal').style.display='flex'" class="cyber-btn primary">
                                    <i class="fas fa-upload"></i> Upload First Material
                                </button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <div class="material-card">
                                    <div class="file-icon">
                                        <i class="fas fa-<?php
                                                            echo in_array($material['file_type'], ['pdf']) ? 'file-pdf' : (in_array($material['file_type'], ['doc', 'docx']) ? 'file-word' : (in_array($material['file_type'], ['ppt', 'pptx']) ? 'file-powerpoint' : (in_array($material['file_type'], ['jpg', 'jpeg', 'png']) ? 'file-image' : (in_array($material['file_type'], ['mp4', 'mp3']) ? 'file-video' : 'file'))));
                                                            ?>"></i>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                                            <div>
                                                <h4 style="color:var(--text-primary);margin-bottom:5px;"><?php echo htmlspecialchars($material['title']); ?></h4>
                                                <span class="topic-badge"><?php echo htmlspecialchars($material['topic']); ?></span>
                                                <span class="cyber-badge" style="margin-left:10px;background:rgba(138,43,226,0.2);">
                                                    <?php echo ucfirst($material['material_type']); ?>
                                                </span>
                                            </div>
                                            <div style="display:flex;gap:10px;">
                                                <a href="../uploads/materials/<?php echo htmlspecialchars($material['file_name']); ?>" download class="cyber-btn" title="Download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this material?');">
                                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                                    <button type="submit" name="delete_material" class="cyber-btn danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <p style="color:var(--text-muted);margin-bottom:10px;font-size:0.9rem;"><?php echo htmlspecialchars($material['description']); ?></p>
                                        <div style="display:flex;gap:20px;font-size:0.85rem;color:var(--text-muted);">
                                            <span><i class="fas fa-file"></i> <?php echo strtoupper($material['file_type']); ?> • <?php echo round($material['file_size'] / 1024 / 1024, 2); ?> MB</span>
                                            <span><i class="fas fa-download"></i> <?php echo $material['download_count']; ?> downloads (<?php echo $material['unique_downloads']; ?> students)</span>
                                            <span><i class="fas fa-clock"></i> Uploaded <?php echo format_datetime($material['uploaded_at']); ?></span>
                                            <span><i class="fas fa-code-branch"></i> Version <?php echo $material['version']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Upload Modal -->
    <?php if ($selected_class): ?>
        <div id="uploadModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
            <div class="holo-card" style="max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3><i class="fas fa-upload"></i> Upload Material</h3>
                    <button onclick="document.getElementById('uploadModal').style.display='none'" class="cyber-btn danger">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">

                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="cyber-label">Material Title *</label>
                        <input type="text" name="title" class="cyber-input" required placeholder="e.g., Chapter 5 Notes">
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="cyber-label">Description</label>
                        <textarea name="description" class="cyber-input" rows="3" placeholder="Brief description of the material"></textarea>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:20px;">
                        <div class="form-group">
                            <label class="cyber-label">Topic *</label>
                            <input type="text" name="topic" class="cyber-input" required placeholder="e.g., Mathematics">
                        </div>
                        <div class="form-group">
                            <label class="cyber-label">Type *</label>
                            <select name="material_type" class="cyber-input" required>
                                <option value="notes">Notes</option>
                                <option value="assignment">Assignment</option>
                                <option value="reference">Reference Material</option>
                                <option value="video">Video</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="cyber-label">File * (Max 50MB)</label>
                        <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size:3rem;color:var(--cyber-cyan);margin-bottom:10px;"></i>
                            <div style="color:var(--text-primary);margin-bottom:5px;">Click to select file</div>
                            <div style="color:var(--text-muted);font-size:0.85rem;">Allowed: PDF, DOC, PPT, Images, Videos, ZIP</div>
                            <div id="fileName" style="color:var(--neon-green);margin-top:10px;font-weight:600;"></div>
                        </div>
                        <input type="file" id="fileInput" name="material_file" style="display:none;" required onchange="document.getElementById('fileName').textContent = this.files[0]?.name || ''">
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="cyber-btn">Cancel</button>
                        <button type="submit" name="upload_material" class="cyber-btn primary">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
