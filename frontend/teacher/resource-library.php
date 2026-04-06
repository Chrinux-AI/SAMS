<?php

/**
 * Public Resource Library
 * Browse resources shared by other teachers
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/database.php';
require_teacher();

$page_title = "Resource Library";
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = "
    SELECT r.*, u.first_name, u.last_name
    FROM teacher_resources r
    JOIN users u ON r.teacher_id = u.id
    WHERE r.is_public = 1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (r.title LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND r.category = ?";
    $params[] = $category;
}

$query .= " ORDER BY r.created_at DESC";

$resources = db()->fetchAll($query, $params);

include '../includes/cyber-header.php';
include '../includes/sidebar-nav.php';
?>

<div class="app-layout">
    <div class="content-header">
        <h1><i class="fas fa-globe"></i> Public Resource Library</h1>
        <p class="subtitle">Resources shared by teachers</p>
    </div>

    <!-- Search and Filter -->
    <div class="holo-card" style="margin-bottom: 30px;">
        <div class="card-body">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <input type="text"
                        name="search"
                        class="cyber-input"
                        placeholder="Search resources..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <select name="category" class="cyber-input">
                        <option value="">All Categories</option>
                        <option value="lesson_plan" <?php echo $category === 'lesson_plan' ? 'selected' : ''; ?>>Lesson Plans</option>
                        <option value="worksheet" <?php echo $category === 'worksheet' ? 'selected' : ''; ?>>Worksheets</option>
                        <option value="presentation" <?php echo $category === 'presentation' ? 'selected' : ''; ?>>Presentations</option>
                        <option value="assignment" <?php echo $category === 'assignment' ? 'selected' : ''; ?>>Assignments</option>
                        <option value="study_guide" <?php echo $category === 'study_guide' ? 'selected' : ''; ?>>Study Guides</option>
                        <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <button type="submit" class="cyber-btn">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
    </div>

    <!-- Resources List -->
    <div class="holo-card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-folder"></i>
                <?php echo count($resources); ?> Resources Found
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($resources)): ?>
                <div class="empty-state">
                    <i class="fas fa-search" style="font-size: 4rem; opacity: 0.3;"></i>
                    <h3>No resources found</h3>
                    <p>Try adjusting your search criteria</p>
                </div>
            <?php else: ?>
                <div class="resources-list">
                    <?php foreach ($resources as $resource): ?>
                        <div class="resource-item">
                            <div class="resource-icon">
                                <i class="fas fa-<?php echo getFileIcon($resource['file_type']); ?>"></i>
                            </div>
                            <div class="resource-details">
                                <h3><?php echo htmlspecialchars($resource['title']); ?></h3>
                                <?php if ($resource['description']): ?>
                                    <p><?php echo htmlspecialchars($resource['description']); ?></p>
                                <?php endif; ?>
                                <div class="resource-meta">
                                    <span class="author"><i class="fas fa-user"></i> <?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?></span>
                                    <span class="category-badge"><?php echo str_replace('_', ' ', ucfirst($resource['category'])); ?></span>
                                    <span><i class="fas fa-download"></i> <?php echo $resource['download_count']; ?> downloads</span>
                                    <span><?php echo formatFileSize($resource['file_size']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="resource-action">
                                <a href="../api/download-resource.php?id=<?php echo $resource['id']; ?>" class="cyber-btn">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .filter-form {
        display: grid;
        grid-template-columns: 1fr 200px auto;
        gap: 15px;
        align-items: center;
    }

    .resources-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .resource-item {
        display: flex;
        gap: 20px;
        padding: 20px;
        background: rgba(0, 243, 255, 0.05);
        border-left: 3px solid var(--cyber-cyan);
        border-radius: 8px;
        transition: all 0.3s ease;
        align-items: center;
    }

    .resource-item:hover {
        background: rgba(0, 243, 255, 0.1);
        transform: translateX(5px);
    }

    .resource-icon {
        width: 70px;
        height: 70px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--cyber-purple));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        flex-shrink: 0;
    }

    .resource-details {
        flex: 1;
    }

    .resource-details h3 {
        margin: 0 0 8px 0;
        color: var(--cyber-cyan);
        font-size: 1.2rem;
    }

    .resource-details p {
        margin: 0 0 12px 0;
        color: var(--text-muted);
        line-height: 1.5;
    }

    .resource-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 0.85rem;
        color: var(--text-muted);
        align-items: center;
    }

    .author {
        font-weight: 600;
        color: var(--cyber-cyan);
    }

    @media (max-width: 768px) {
        .filter-form {
            grid-template-columns: 1fr;
        }

        .resource-item {
            flex-direction: column;
            text-align: center;
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

