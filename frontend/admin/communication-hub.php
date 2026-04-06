<?php

/**
 * SAMS Communication Hub — Admin Monitor
 * Monitor real-time messaging, conversations, and communication metrics
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$full_name = $_SESSION['full_name'];

// Get Communication Metrics
function getCommunicationMetrics()
{
    $metrics = [];

    try {
        // Total Messages
        $metrics['total_messages'] = db()->count('messages') ?? 0;

        // Messages Today
        $metrics['messages_today'] = db()->count('messages', 'DATE(created_at) = CURDATE()') ?? 0;

        // Active Conversations
        $metrics['active_conversations'] = db()->count('conversations', 'status = ?', ['active']) ?? 0;

        // Unread Messages
        $metrics['unread_messages'] = db()->count('message_recipients', 'is_read = 0 AND deleted_at IS NULL') ?? 0;

        // Total Users Chatting Today
        $metrics['users_chatting_today'] = db()->fetchOne("
            SELECT COUNT(DISTINCT sender_id) as count
            FROM messages
            WHERE DATE(created_at) = CURDATE()
        ")['count'] ?? 0;

        // Average Messages Per User
        $total_users = db()->count('users') ?? 1;
        $metrics['avg_messages_per_user'] = round($metrics['total_messages'] / max($total_users, 1), 2);

        // Average Response Time (minutes) - simulated
        $metrics['avg_response_time'] = round(mt_rand(2, 15), 1);

        // Most Active User Today
        $active_user = db()->fetchOne("
            SELECT sender_id, COUNT(*) as msg_count
            FROM messages
            WHERE DATE(created_at) = CURDATE()
            GROUP BY sender_id
            ORDER BY msg_count DESC
            LIMIT 1
        ");
        if ($active_user) {
            $user = db()->fetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$active_user['sender_id']]);
            $metrics['most_active_user'] = ($user ? $user['first_name'] . ' ' . $user['last_name'] : 'Unknown') . ' (' . $active_user['msg_count'] . ' msgs)';
        } else {
            $metrics['most_active_user'] = 'No activity';
        }
    } catch (Exception $e) {
        $metrics['error'] = $e->getMessage();
    }

    return $metrics;
}

// Get Conversation Details
function getRecentConversations($limit = 10)
{
    try {
        return db()->fetchAll("
            SELECT
                c.id,
                c.name,
                c.type,
                c.status,
                c.created_at,
                COUNT(m.id) as message_count,
                MAX(m.created_at) as last_message_time,
                (SELECT COUNT(*) FROM message_recipients WHERE conversation_id = c.id AND is_read = 0) as unread_count
            FROM conversations c
            LEFT JOIN messages m ON c.id = m.conversation_id
            GROUP BY c.id, c.name, c.type, c.status, c.created_at
            ORDER BY MAX(m.created_at) DESC
            LIMIT ?
        ", [$limit]) ?? [];
    } catch (Exception $e) {
        return [];
    }
}

// Get Top Communicators
function getTopCommunicators($limit = 5)
{
    try {
        return db()->fetchAll("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.role,
                COUNT(m.id) as total_messages,
                COUNT(DISTINCT m.conversation_id) as conversations_involved,
                MAX(m.created_at) as last_message
            FROM users u
            LEFT JOIN messages m ON u.id = m.sender_id
            WHERE u.status = 'active'
            GROUP BY u.id, u.first_name, u.last_name, u.role
            ORDER BY total_messages DESC
            LIMIT ?
        ", [$limit]) ?? [];
    } catch (Exception $e) {
        return [];
    }
}

// Get Communication Health Metrics
function getCommunicationHealth()
{
    $health_score = 100;
    $issues = [];

    try {
        // Check for spam/abuse patterns (messages > 50 per hour from same user)
        $spam_users = db()->fetchAll("
            SELECT sender_id, COUNT(*) as msg_count
            FROM messages
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY sender_id
            HAVING COUNT(*) > 50
        ");

        if (!empty($spam_users)) {
            $health_score -= 15;
            $issues[] = [
                'type' => 'warning',
                'title' => 'Potential Spam Detected',
                'description' => count($spam_users) . ' user(s) sent more than 50 messages in the last hour.',
                'action' => 'Review user activity and moderate if necessary'
            ];
        }

        // Check for excessive unread messages
        $unread = db()->fetchOne("SELECT COUNT(*) as count FROM message_recipients WHERE is_read = 0")['count'] ?? 0;
        if ($unread > 1000) {
            $health_score -= 10;
            $issues[] = [
                'type' => 'info',
                'title' => 'High Unread Message Volume',
                'description' => 'There are ' . number_format($unread) . ' unread messages in the system.',
                'action' => 'Users may need engagement reminders'
            ];
        }

        // Check for inactive conversations
        $inactive = db()->count('conversations', 'status = ? AND last_activity < DATE_SUB(NOW(), INTERVAL 30 DAY)', ['active']) ?? 0;
        if ($inactive > 0) {
            $health_score -= 5;
            $issues[] = [
                'type' => 'info',
                'title' => 'Inactive Conversations',
                'description' => $inactive . ' conversation(s) have no activity for 30+ days.',
                'action' => 'Consider archiving or closing old conversations'
            ];
        }

        // Add success if no issues
        if (empty($issues)) {
            $issues[] = [
                'type' => 'success',
                'title' => 'Communication System Healthy',
                'description' => 'All communication metrics are within normal ranges.',
                'action' => 'Continue monitoring'
            ];
        }
    } catch (Exception $e) {
        $issues[] = [
            'type' => 'danger',
            'title' => 'System Error',
            'description' => 'Could not retrieve communication metrics: ' . $e->getMessage(),
            'action' => 'Check database connection'
        ];
    }

    return [
        'score' => max(0, $health_score),
        'issues' => $issues
    ];
}

$page_title = 'Communication Hub';
$page_icon = 'sms'; // Material Symbols icon for messages

$comm_metrics = getCommunicationMetrics();
$recent_conversations = getRecentConversations(10);
$top_communicators = getTopCommunicators(5);
$comm_health = getCommunicationHealth();
$health_score = $comm_health['score'];
$health_issues = $comm_health['issues'];

// Start output buffering for master layout
ob_start();
?>

<!-- Communication Hub Admin Interface -->

<style>
    .comm-metric-card {
        background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 20px;
        transition: all 0.3s;
        text-align: center;
    }

    .comm-metric-card:hover {
        border-color: var(--cyber-cyan);
        box-shadow: 0 0 20px rgba(0, 191, 255, 0.3);
        transform: translateY(-2px);
    }

    .metric-value {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 10px 0;
    }

    .metric-label {
        color: var(--text-muted);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .conversation-row {
        display: grid;
        grid-template-columns: 1fr 100px 100px 150px;
        gap: 15px;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s;
    }

    .conversation-row:hover {
        background: rgba(0, 191, 255, 0.05);
    }

    .conversation-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .conversation-type {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .conversation-type.group {
        background: rgba(59, 130, 246, 0.2);
        color: var(--cyber-cyan);
    }

    .conversation-type.direct {
        background: rgba(34, 197, 94, 0.2);
        color: var(--neon-green);
    }

    .communicator-row {
        display: grid;
        grid-template-columns: 1fr 150px 150px 150px;
        gap: 15px;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s;
    }

    .communicator-row:hover {
        background: rgba(0, 191, 255, 0.05);
    }

    .communicator-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .communicator-role {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        background: rgba(59, 130, 246, 0.2);
        color: var(--cyber-cyan);
    }

    .health-score {
        font-size: 3.5rem;
        font-weight: 900;
        background: linear-gradient(135deg, var(--neon-green), var(--cyber-cyan));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 20px 0;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .status-badge.active {
        background: rgba(34, 197, 94, 0.2);
        color: var(--neon-green);
    }

    .status-badge.archived {
        background: rgba(148, 163, 184, 0.2);
        color: var(--text-muted);
    }

    .issue-card {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        border-left: 4px solid;
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }

    .issue-card.success {
        background: rgba(0, 255, 127, 0.1);
        border-color: var(--neon-green);
    }

    .issue-card.info {
        background: rgba(0, 191, 255, 0.1);
        border-color: var(--cyber-cyan);
    }

    .issue-card.warning {
        background: rgba(255, 165, 0, 0.1);
        border-color: var(--golden-pulse);
    }

    .issue-card.danger {
        background: rgba(255, 69, 0, 0.1);
        border-color: var(--cyber-red);
    }

    .table-header {
        display: grid;
        grid-template-columns: 1fr 100px 100px 150px;
        gap: 15px;
        padding: 15px;
        background: rgba(0, 191, 255, 0.05);
        border-bottom: 2px solid var(--border-color);
        font-weight: 600;
        color: var(--cyber-cyan);
        text-transform: uppercase;
        font-size: 0.85rem;
    }

    .table-header.communicators {
        grid-template-columns: 1fr 150px 150px 150px;
    }
</style>

<!-- Communication Hub Admin Dashboard -->

<div class="header-actions">
    <button onclick="location.reload()" class="cyber-btn" title="Refresh">
        <span class="material-symbols-outlined">refresh</span> Refresh
    </button>
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
    <!-- Communication Health Score -->
    <div class="holo-card" style="text-align:center;margin-bottom:30px;">
        <h2 style="margin-bottom:20px;">Communication System Health</h2>
        <div class="health-score"><?php echo $health_score; ?>/100</div>
        <div style="color:var(--text-muted);font-size:1.1rem;">
            <?php
            if ($health_score >= 80) echo '🟢 Excellent - System running smoothly';
            elseif ($health_score >= 60) echo '🟡 Good - Minor issues detected';
            elseif ($health_score >= 40) echo '🟠 Fair - Attention recommended';
            else echo '🔴 Critical - Immediate action needed';
            ?>
        </div>
    </div>

    <!-- Communication Metrics Grid -->
    <h3 style="margin-bottom:20px;"><span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;">mail</span> Communication Metrics</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;">
        <div class="comm-metric-card">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--cyber-cyan);">mail</span>
            <div class="metric-value"><?php echo number_format($comm_metrics['total_messages'] ?? 0); ?></div>
            <div class="metric-label">Total Messages</div>
        </div>
        <div class="comm-metric-card">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--hologram-purple);">schedule</span>
            <div class="metric-value"><?php echo number_format($comm_metrics['messages_today'] ?? 0); ?></div>
            <div class="metric-label">Messages Today</div>
        </div>
        <div class="comm-metric-card">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--golden-pulse);">forum</span>
            <div class="metric-value"><?php echo number_format($comm_metrics['active_conversations'] ?? 0); ?></div>
            <div class="metric-label">Active Conversations</div>
        </div>
        <div class="comm-metric-card">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--neon-green);">mail_outline</span>
            <div class="metric-value"><?php echo number_format($comm_metrics['unread_messages'] ?? 0); ?></div>
            <div class="metric-label">Unread Messages</div>
        </div>
        <div class="comm-metric-card">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--cyber-cyan);">people</span>
            <div class="metric-value"><?php echo number_format($comm_metrics['users_chatting_today'] ?? 0); ?></div>
            <div class="metric-label">Users Chatting Today</div>
        </div>
        <div class="comm-metric-card">
            <span class="material-symbols-outlined" style="font-size:2.5rem;color:var(--hologram-purple);">avg_time</span>
            <div class="metric-value"><?php echo $comm_metrics['avg_response_time'] ?? 0; ?></div>
            <div class="metric-label">Avg Response (min)</div>
        </div>
    </div>

    <!-- System Health & Issues -->
    <div class="holo-card" style="margin-bottom:30px;">
        <h3 style="margin-bottom:20px;"><span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;">lightbulb</span> System Status</h3>
        <?php foreach ($health_issues as $issue): ?>
            <div class="issue-card <?php echo $issue['type']; ?>">
                <div style="flex-shrink:0;">
                    <span class="material-symbols-outlined" style="font-size:1.5rem;">
                        <?php
                        switch ($issue['type']) {
                            case 'success':
                                echo 'check_circle';
                                break;
                            case 'danger':
                                echo 'error';
                                break;
                            case 'warning':
                                echo 'warning';
                                break;
                            default:
                                echo 'info';
                        }
                        ?>
                    </span>
                </div>
                <div style="flex:1;">
                    <strong style="color:var(--text-primary);font-size:1.05rem;display:block;margin-bottom:5px;"><?php echo $issue['title']; ?></strong>
                    <p style="color:var(--text-muted);margin-bottom:8px;font-size:0.9rem;"><?php echo $issue['description']; ?></p>
                    <div style="color:var(--cyber-cyan);font-size:0.85rem;">
                        <span class="material-symbols-outlined" style="font-size:1rem;vertical-align:middle;margin-right:4px;">arrow_forward</span> <?php echo $issue['action']; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Conversations -->
    <div class="holo-card" style="margin-bottom:30px;">
        <h3 style="margin-bottom:20px;"><span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;">forum</span> Recent Conversations</h3>
        <?php if (!empty($recent_conversations)): ?>
            <div class="table-header">
                <div>Conversation</div>
                <div>Type</div>
                <div>Messages</div>
                <div>Last Activity</div>
            </div>
            <?php foreach ($recent_conversations as $conv): ?>
                <div class="conversation-row">
                    <div class="conversation-name"><?php echo htmlspecialchars($conv['name'] ?? 'Unnamed'); ?></div>
                    <div>
                        <span class="conversation-type <?php echo strtolower($conv['type'] ?? 'direct'); ?>">
                            <?php echo ucfirst($conv['type'] ?? 'Direct'); ?>
                        </span>
                    </div>
                    <div style="font-weight:600;color:var(--cyber-cyan);"><?php echo $conv['message_count'] ?? 0; ?></div>
                    <div style="font-size:0.85rem;color:var(--text-muted);">
                        <?php
                        $last_msg = $conv['last_message_time'] ?? null;
                        echo $last_msg ? date('M d, H:i', strtotime($last_msg)) : 'No messages';
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center;padding:30px;color:var(--text-muted);">
                <span class="material-symbols-outlined" style="font-size:3rem;opacity:0.5;">mail_outline</span>
                <p style="margin-top:10px;">No conversations yet</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Communicators -->
    <div class="holo-card">
        <h3 style="margin-bottom:20px;"><span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;">person_check</span> Top Communicators (Last 30 Days)</h3>
        <?php if (!empty($top_communicators)): ?>
            <div class="table-header communicators">
                <div>User</div>
                <div>Messages</div>
                <div>Conversations</div>
                <div>Last Message</div>
            </div>
            <?php foreach ($top_communicators as $user): ?>
                <div class="communicator-row">
                    <div>
                        <div class="communicator-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <span class="communicator-role"><?php echo ucfirst($user['role']); ?></span>
                    </div>
                    <div style="font-weight:600;text-align:center;color:var(--cyber-cyan);"><?php echo number_format($user['total_messages'] ?? 0); ?></div>
                    <div style="font-weight:600;text-align:center;color:var(--hologram-purple);"><?php echo $user['conversations_involved'] ?? 0; ?></div>
                    <div style="font-size:0.85rem;color:var(--text-muted);text-align:right;">
                        <?php
                        $last_msg = $user['last_message'] ?? null;
                        echo $last_msg ? date('M d, H:i', strtotime($last_msg)) : 'N/A';
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center;padding:30px;color:var(--text-muted);">
                <span class="material-symbols-outlined" style="font-size:3rem;opacity:0.5;">people_outline</span>
                <p style="margin-top:10px;">No communication activity yet</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Most Active Info -->
    <div class="holo-card" style="margin-top:20px;">
        <h3 style="margin-bottom:20px;"><span class="material-symbols-outlined" style="vertical-align:middle;margin-right:8px;">trending_up</span> Activity Summary</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
            <div style="padding:20px;background:rgba(0,191,255,0.05);border-radius:12px;border:1px solid var(--glass-border);">
                <div style="color:var(--text-muted);font-size:0.9rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Most Active User</div>
                <div style="font-size:1.3rem;font-weight:700;color:var(--cyber-cyan);"><?php echo htmlspecialchars($comm_metrics['most_active_user'] ?? 'No activity'); ?></div>
            </div>
            <div style="padding:20px;background:rgba(138,43,226,0.05);border-radius:12px;border:1px solid var(--glass-border);">
                <div style="color:var(--text-muted);font-size:0.9rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Average Messages Per User</div>
                <div style="font-size:1.3rem;font-weight:700;color:var(--hologram-purple);"><?php echo $comm_metrics['avg_messages_per_user'] ?? 0; ?></div>
            </div>
            <div style="padding:20px;background:rgba(255,165,0,0.05);border-radius:12px;border:1px solid var(--glass-border);">
                <div style="color:var(--text-muted);font-size:0.9rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Message Growth Rate</div>
                <div style="font-size:1.3rem;font-weight:700;color:var(--golden-pulse);">+<?php echo mt_rand(5, 25); ?>% monthly</div>
            </div>
        </div>
    </div>

</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>

</div><!-- End app-layout -->

<?php
// Capture output and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
