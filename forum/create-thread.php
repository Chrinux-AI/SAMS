<?php

/**
 * Create New Forum Thread
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$page_title = "Create Thread";

// Get categories accessible to user
$categories = db()->fetchAll("
    SELECT * FROM forum_categories
    WHERE is_active = 1
    AND (allowed_roles IS NULL OR FIND_IN_SET(?, allowed_roles) > 0)
    ORDER BY display_order
", [$user_role]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    $errors = [];

    if (empty($title)) {
        $errors[] = "Title is required";
    } elseif (strlen($title) < 5) {
        $errors[] = "Title must be at least 5 characters";
    }

    if (empty($content)) {
        $errors[] = "Content is required";
    } elseif (strlen($content) < 10) {
        $errors[] = "Content must be at least 10 characters";
    }

    if (empty($category_id)) {
        $errors[] = "Please select a category";
    }

    // Profanity filter
    $profanity_words = ['damn', 'hell', 'crap', 'stupid', 'idiot', 'dumb'];
    $check_text = strtolower($title . ' ' . $content);
    foreach ($profanity_words as $word) {
        if (strpos($check_text, $word) !== false) {
            $errors[] = "Please keep language appropriate and professional";
            break;
        }
    }

    if (empty($errors)) {
        try {
            $thread_id = db()->insert('forum_threads', [
                'category_id' => $category_id,
                'user_id' => $user_id,
                'title' => $title,
                'content' => $content,
                'last_activity_at' => date('Y-m-d H:i:s')
            ]);

            if ($thread_id) {
                $_SESSION['success_message'] = "Thread created successfully!";
                header("Location: thread.php?id=" . $thread_id);
                exit;
            } else {
                $errors[] = "Failed to create thread. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "Failed to create thread. Please try again.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <title><?php echo $page_title; ?> - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <style>
    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--cyber-cyan);
        font-weight: 600;
    }

    .required {
        color: var(--danger-color);
    }

    .form-help {
        display: block;
        margin-top: 5px;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border-left: 4px solid var(--danger-color);
        color: var(--danger-color);
    }
    </style>
</head>
<body class="cyber-bg">
<?php include '../includes/cyber-nav.php'; ?>
<div class="cyber-layout">
<div class="cyber-main">
<div class="cyber-content" style="padding:24px;">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Create New Thread</h1>
        <p class="subtitle">Start a new discussion</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="holo-card">
        <div class="card-body">
            <form method="POST" action="" id="threadForm">
                <div class="form-group">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select name="category_id" id="category_id" class="cyber-input" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?> - <?php echo htmlspecialchars($cat['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Thread Title <span class="required">*</span></label>
                    <input type="text"
                        name="title"
                        id="title"
                        class="cyber-input"
                        maxlength="255"
                        placeholder="Enter a clear, descriptive title"
                        value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                        required>
                    <small class="form-help">Min. 5 characters, max. 255 characters</small>
                </div>

                <div class="form-group">
                    <label for="content">Content <span class="required">*</span></label>
                    <textarea name="content"
                        id="content"
                        class="cyber-input"
                        rows="8"
                        placeholder="Share your thoughts, questions, or information..."
                        required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    <small class="form-help">Min. 10 characters - Be clear and respectful</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="cyber-btn">
                        <i class="fas fa-paper-plane"></i> Post Thread
                    </button>
                    <a href="index.php" class="cyber-btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Posting Guidelines -->
    <div class="holo-card" style="margin-top: 20px;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-lightbulb"></i> Posting Tips</div>
        </div>
        <div class="card-body">
            <ul style="color: var(--text-muted); line-height: 1.8;">
                <li>Choose the right category for your thread</li>
                <li>Use a clear, descriptive title that summarizes your topic</li>
                <li>Provide enough context in your post</li>
                <li>Be respectful and follow community guidelines</li>
                <li>Check if a similar thread already exists before posting</li>
            </ul>
        </div>
    </div>
</div>
</div>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>
</body>
</html>
