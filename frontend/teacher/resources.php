<?php

/**
 * Teacher Resource Repository
 * Upload and share teaching materials
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/database.php';
require_teacher();

$user_id = $_SESSION['user_id'];
$page_title = "Resource Repository";

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $errors = [];

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if (empty($title)) {
        $errors[] = "Title is required";
    }

    if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['resource_file'];
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'image/jpeg', 'image/png'];

        $max_size = 10 * 1024 * 1024; // 10MB

        if ($file['size'] > $max_size) {
            $errors[] = "File size must not exceed 10MB";
        }

        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "File type not allowed. Only PDF, DOC, DOCX, PPT, PPTX, ZIP, JPG, PNG allowed";
        }

        if (empty($errors)) {
            $upload_dir = '../uploads/resources/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $file_name = $file['name'];
            $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                try {
                    db()->query("
                        INSERT INTO teacher_resources (teacher_id, title, description, file_path, file_name, file_size, file_type, category, is_public)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [$user_id, $title, $description, $file_path, $file_name, $file['size'], $file['type'], $category, $is_public]);

                    $_SESSION['success_message'] = "Resource uploaded successfully!";
                    header("Location: resources.php");
                    exit;
                } catch (Exception $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            } else {
                $errors[] = "Failed to upload file";
            }
        }
    } else {
        $errors[] = "Please select a file to upload";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $resource_id = intval($_GET['delete']);
    $resource = db()->fetchOne("SELECT * FROM teacher_resources WHERE id = ? AND teacher_id = ?", [$resource_id, $user_id]);

    if ($resource) {
        if (file_exists($resource['file_path'])) {
            unlink($resource['file_path']);
        }
        db()->query("DELETE FROM teacher_resources WHERE id = ?", [$resource_id]);
        $_SESSION['success_message'] = "Resource deleted successfully";
        header("Location: resources.php");
        exit;
    }
}

// Get user's resources
$my_resources = db()->fetchAll("
    SELECT * FROM teacher_resources
    WHERE teacher_id = ?
    ORDER BY created_at DESC
", [$user_id]);

include '../includes/cyber-header.php';
include '../includes/sidebar-nav.php';
?>

<div class="app-layout">
    <div class="content-header">
        <div>
            <h1><i class="fas fa-book"></i> Resource Repository</h1>
            <p class="subtitle">Upload and manage teaching materials</p>
        </div>
        <button onclick="document.getElementById('uploadModal').style.display='flex'" class="cyber-btn">
            <i class="fas fa-upload"></i> Upload Resource
        </button>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message'];
                                                unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <ul style="margin:10px 0 0 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- My Resources -->
    <div class="holo-card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-folder-open"></i> My Resources (<?php echo count($my_resources); ?>)</div>
        </div>
        <div class="card-body">
            <?php if (empty($my_resources)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open" style="font-size: 4rem; opacity: 0.3;"></i>
                    <h3>No resources yet</h3>
                    <p>Upload your first teaching resource to get started</p>
                    <button onclick="document.getElementById('uploadModal').style.display='flex'" class="cyber-btn">
                        <i class="fas fa-upload"></i> Upload Resource
                    </button>
                </div>
            <?php else: ?>
                <div class="resources-grid">
                    <?php foreach ($my_resources as $resource): ?>
                        <div class="resource-card">
                            <div class="resource-icon">
                                <i class="fas fa-<?php echo getFileIcon($resource['file_type']); ?>"></i>
                            </div>
                            <div class="resource-info">
                                <h4><?php echo htmlspecialchars($resource['title']); ?></h4>
                                <?php if ($resource['description']): ?>
                                    <p><?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?><?php echo strlen($resource['description']) > 100 ? '...' : ''; ?></p>
                                <?php endif; ?>
                                <div class="resource-meta">
                                    <span class="category-badge"><?php echo str_replace('_', ' ', ucfirst($resource['category'])); ?></span>
                                    <span><i class="fas fa-download"></i> <?php echo $resource['download_count']; ?></span>
                                    <span><?php echo formatFileSize($resource['file_size']); ?></span>
                                    <?php if ($resource['is_public']): ?>
                                        <span class="public-badge"><i class="fas fa-globe"></i> Public</span>
                                    <?php else: ?>
                                        <span class="private-badge"><i class="fas fa-lock"></i> Private</span>
                                    <?php endif; ?>
                                </div>
                                <small style="color: var(--text-muted); display: block; margin-top: 8px;">
                                    Uploaded <?php echo date('M d, Y', strtotime($resource['created_at'])); ?>
                                </small>
                            </div>
                            <div class="resource-actions">
                                <a href="../api/download-resource.php?id=<?php echo $resource['id']; ?>" class="action-btn" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="?delete=<?php echo $resource['id']; ?>" class="action-btn danger" onclick="return confirm('Delete this resource?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Browse Public Resources Link -->
    <div style="text-align: center; margin-top: 30px;">
        <a href="resource-library.php" class="cyber-btn-outline">
            <i class="fas fa-search"></i> Browse Public Resources
        </a>
    </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-upload"></i> Upload Resource</h2>
            <button onclick="document.getElementById('uploadModal').style.display='none'" class="close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">

                <div class="form-group">
                    <label>Title <span class="required">*</span></label>
                    <input type="text" name="title" class="cyber-input" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="cyber-input" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select name="category" class="cyber-input" required>
                        <option value="lesson_plan">Lesson Plan</option>
                        <option value="worksheet">Worksheet</option>
                        <option value="presentation">Presentation</option>
                        <option value="assignment">Assignment</option>
                        <option value="study_guide">Study Guide</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>File <span class="required">*</span></label>
                    <input type="file" name="resource_file" class="cyber-input" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.jpg,.jpeg,.png" required>
                    <small class="form-help">Max 10MB. Allowed: PDF, DOC, DOCX, PPT, PPTX, ZIP, JPG, PNG</small>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_public">
                        <span>Share publicly (visible to all teachers)</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                    <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="cyber-btn-outline">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .resources-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }

    .resource-card {
        display: flex;
        gap: 15px;
        padding: 20px;
        background: rgba(0, 243, 255, 0.05);
        border-left: 3px solid var(--cyber-cyan);
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .resource-card:hover {
        background: rgba(0, 243, 255, 0.1);
        transform: translateY(-3px);
    }

    .resource-icon {
        width: 60px;
        height: 60px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        flex-shrink: 0;
    }

    .resource-info {
        flex: 1;
    }

    .resource-info h4 {
        margin: 0 0 8px 0;
        color: var(--cyber-cyan);
    }

    .resource-info p {
        margin: 0 0 10px 0;
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .resource-meta {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 0.85rem;
        color: var(--text-muted);
        align-items: center;
    }

    .category-badge {
        background: rgba(0, 243, 255, 0.2);
        color: var(--cyber-cyan);
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .public-badge {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success-color);
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 0.75rem;
    }

    .private-badge {
        background: rgba(107, 114, 128, 0.2);
        color: var(--text-muted);
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 0.75rem;
    }

    .resource-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .action-btn {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        border: 1px solid var(--cyber-cyan);
        background: transparent;
        color: var(--cyber-cyan);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .action-btn:hover {
        background: var(--cyber-cyan);
        color: var(--bg-dark);
    }

    .action-btn.danger {
        border-color: var(--danger-color);
        color: var(--danger-color);
    }

    .action-btn.danger:hover {
        background: var(--danger-color);
        color: white;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .modal-content {
        background: var(--bg-dark);
        border: 1px solid var(--cyber-cyan);
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid rgba(0, 243, 255, 0.2);
    }

    .modal-header h2 {
        margin: 0;
        color: var(--cyber-cyan);
    }

    .close-btn {
        background: transparent;
        border: none;
        color: var(--text-muted);
        font-size: 1.5rem;
        cursor: pointer;
        transition: color 0.3s ease;
    }

    .close-btn:hover {
        color: var(--cyber-cyan);
    }

    .modal-body {
        padding: 20px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
    }

    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-state i {
        color: var(--text-muted);
    }

    .empty-state h3 {
        margin: 20px 0 10px;
    }

    .empty-state p {
        color: var(--text-muted);
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .resources-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php
function getFileIcon($mimeType)
{
    if (strpos($mimeType, 'pdf') !== false) return 'file-pdf';
    if (strpos($mimeType, 'word') !== false) return 'file-word';
    if (strpos($mimeType, 'powerpoint') !== false || strpos($mimeType, 'presentation') !== false) return 'file-powerpoint';
    if (strpos($mimeType, 'zip') !== false) return 'file-archive';
    if (strpos($mimeType, 'image') !== false) return 'file-image';
    return 'file';
}

function formatFileSize($bytes)
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

include '../includes/cyber-footer.php';
?>

