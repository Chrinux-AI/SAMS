<?php

/**
 * Universal Notice Board Widget
 * Read-only announcements visible to all roles
 * Only Admins can create/edit notices
 */

// Get latest notices (5 most recent)
$notices = db()->fetchAll("
    SELECT n.*, u.first_name, u.last_name
    FROM notices n
    LEFT JOIN users u ON n.created_by = u.id
    WHERE n.status = 'active'
    AND (n.expires_at IS NULL OR n.expires_at > NOW())
    AND (n.target_roles IS NULL OR FIND_IN_SET(?, n.target_roles) > 0)
    ORDER BY n.is_pinned DESC, n.created_at DESC
    LIMIT 5
", [$_SESSION['role'] ?? 'guest']);

if (empty($notices)) {
    return; // Don't display widget if no notices
}
?>

<div class="holo-card notice-board-widget" style="margin-bottom: 25px;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-bullhorn"></i> Notice Board
            <span class="cyber-badge" style="background: var(--cyber-cyan); margin-left: 10px;">
                <?php echo count($notices); ?> Active
            </span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php foreach ($notices as $notice): ?>
            <div class="notice-item" style="padding: 15px; border-bottom: 1px solid var(--glass-border); <?php echo $notice['is_pinned'] ? 'background: rgba(0, 243, 255, 0.05);' : ''; ?>">
                <div style="display: flex; align-items: start; gap: 15px;">
                    <div style="flex-shrink: 0;">
                        <?php
                        // Category icons and colors
                        $category_config = [
                            'academic' => ['icon' => 'graduation-cap', 'color' => 'var(--cyber-purple)'],
                            'sports' => ['icon' => 'futbol', 'color' => 'var(--cyber-green)'],
                            'emergency' => ['icon' => 'exclamation-triangle', 'color' => 'var(--cyber-red)'],
                            'event' => ['icon' => 'calendar-alt', 'color' => 'var(--cyber-cyan)'],
                            'maintenance' => ['icon' => 'tools', 'color' => 'var(--cyber-yellow)'],
                            'general' => ['icon' => 'info-circle', 'color' => 'var(--cyber-blue)']
                        ];
                        $config = $category_config[$notice['category']] ?? $category_config['general'];
                        ?>
                        <div style="width: 45px; height: 45px; border-radius: 10px; background: <?php echo $config['color']; ?>; display: flex; align-items: center; justify-content: center; opacity: 0.9;">
                            <i class="fas fa-<?php echo $config['icon']; ?>" style="color: white; font-size: 1.2rem;"></i>
                        </div>
                    </div>
                    <div style="flex-grow: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <h4 style="margin: 0; color: var(--cyber-cyan); font-size: 1rem;">
                                <?php echo htmlspecialchars($notice['title']); ?>
                            </h4>
                            <?php if ($notice['is_pinned']): ?>
                                <span class="cyber-badge" style="background: var(--cyber-yellow); font-size: 0.7rem;">
                                    <i class="fas fa-thumbtack"></i> PINNED
                                </span>
                            <?php endif; ?>
                            <?php if ($notice['priority'] === 'urgent'): ?>
                                <span class="cyber-badge" style="background: var(--cyber-red); font-size: 0.7rem; animation: pulse 2s infinite;">
                                    <i class="fas fa-bolt"></i> URGENT
                                </span>
                            <?php endif; ?>
                        </div>
                        <p style="margin: 8px 0; color: var(--text-body); line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                        </p>
                        <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px; font-size: 0.85rem; color: var(--text-muted);">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($notice['first_name'] . ' ' . $notice['last_name']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($notice['created_at'])); ?></span>
                            <span><i class="fas fa-tag"></i> <?php echo ucfirst($notice['category']); ?></span>
                            <?php if ($notice['expires_at']): ?>
                                <span style="color: var(--cyber-yellow);">
                                    <i class="fas fa-hourglass-end"></i> Expires: <?php echo date('M d', strtotime($notice['expires_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div style="padding: 15px; text-align: center; background: rgba(0, 0, 0, 0.2);">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="/attendance/admin/notices.php" class="cyber-btn cyber-btn-sm">
                    <i class="fas fa-cog"></i> Manage Notices
                </a>
            <?php else: ?>
                <a href="/attendance/notices.php" class="cyber-btn cyber-btn-outline cyber-btn-sm">
                    <i class="fas fa-eye"></i> View All Notices
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }
</style>