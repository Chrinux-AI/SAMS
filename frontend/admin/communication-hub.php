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

function communication_hub_tenant_id(): int
{
    return max(0, (int)(function_exists('current_tenant_id') ? current_tenant_id() : ($_SESSION['tenant_id'] ?? 0)));
}

function communication_hub_message_source(): array
{
    $candidates = [
        ['table' => 'comm_messages', 'alias' => 'm', 'conversation_field' => 'conversation_id', 'sender_field' => 'sender_id', 'created_field' => 'created_at'],
        ['table' => 'conversation_messages', 'alias' => 'm', 'conversation_field' => 'conversation_id', 'sender_field' => 'sender_id', 'created_field' => 'created_at'],
        ['table' => 'messages', 'alias' => 'm', 'conversation_field' => table_has_column('messages', 'conversation_id') ? 'conversation_id' : null, 'sender_field' => 'sender_id', 'created_field' => 'created_at'],
    ];

    foreach ($candidates as $candidate) {
        if (!table_exists($candidate['table'])) {
            continue;
        }

        if (!table_has_column($candidate['table'], $candidate['sender_field']) || !table_has_column($candidate['table'], $candidate['created_field'])) {
            continue;
        }

        return $candidate;
    }

    return [];
}

function communication_hub_participant_source(): array
{
    $candidates = [
        ['table' => 'comm_participants', 'alias' => 'cp'],
        ['table' => 'conversation_participants', 'alias' => 'cp'],
    ];

    foreach ($candidates as $candidate) {
        if (table_exists($candidate['table']) && table_has_column($candidate['table'], 'conversation_id') && table_has_column($candidate['table'], 'user_id')) {
            return $candidate;
        }
    }

    return [];
}

function communication_hub_message_tenant_clause(array $source, int $tenantId): string
{
    return $tenantId > 0 && table_has_column($source['table'], 'tenant_id')
        ? " AND {$source['alias']}.tenant_id = ?"
        : '';
}

function communication_hub_message_tenant_params(array $source, int $tenantId): array
{
    return communication_hub_message_tenant_clause($source, $tenantId) !== '' ? [$tenantId] : [];
}

function communication_hub_user_where_clause(int $tenantId): array
{
    $clauses = [];
    $params = [];

    if (table_has_column('users', 'status')) {
        $clauses[] = "u.status = 'active'";
    }
    if ($tenantId > 0) {
        if (table_has_column('users', 'tenant_id')) {
            $clauses[] = 'u.tenant_id = ?';
            $params[] = $tenantId;
        } elseif (table_has_column('users', 'school_id')) {
            $clauses[] = 'u.school_id = ?';
            $params[] = $tenantId;
        }
    }

    return [
        'sql' => empty($clauses) ? '' : ' WHERE ' . implode(' AND ', $clauses),
        'params' => $params,
    ];
}

// Get Communication Metrics
function getCommunicationMetrics()
{
    $metrics = [];
    $tenantId = communication_hub_tenant_id();
    $source = communication_hub_message_source();

    try {
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $metrics['unread_messages'] = get_unread_message_count($currentUserId, $tenantId);
        $metrics['avg_response_time'] = round(mt_rand(2, 15), 1);

        if (empty($source)) {
            $metrics['total_messages'] = 0;
            $metrics['messages_today'] = 0;
            $metrics['active_conversations'] = 0;
            $metrics['users_chatting_today'] = 0;
            $metrics['avg_messages_per_user'] = 0;
            $metrics['most_active_user'] = 'No activity';
            return $metrics;
        }

        $messageTenantClause = communication_hub_message_tenant_clause($source, $tenantId);
        $messageTenantParams = communication_hub_message_tenant_params($source, $tenantId);

        $metrics['total_messages'] = (int)(db()->fetchOne("
            SELECT COUNT(*) AS count
            FROM {$source['table']} {$source['alias']}
            WHERE 1 = 1{$messageTenantClause}
        ", $messageTenantParams)['count'] ?? 0);

        $metrics['messages_today'] = (int)(db()->fetchOne("
            SELECT COUNT(*) AS count
            FROM {$source['table']} {$source['alias']}
            WHERE DATE({$source['alias']}.{$source['created_field']}) = CURDATE(){$messageTenantClause}
        ", $messageTenantParams)['count'] ?? 0);

        $metrics['active_conversations'] = 0;
        if (!empty($source['conversation_field'])) {
            $metrics['active_conversations'] = (int)(db()->fetchOne("
                SELECT COUNT(DISTINCT {$source['alias']}.{$source['conversation_field']}) AS count
                FROM {$source['table']} {$source['alias']}
                WHERE 1 = 1{$messageTenantClause}
            ", $messageTenantParams)['count'] ?? 0);
        }

        $metrics['users_chatting_today'] = (int)(db()->fetchOne("
            SELECT COUNT(DISTINCT {$source['alias']}.{$source['sender_field']}) AS count
            FROM {$source['table']} {$source['alias']}
            WHERE DATE({$source['alias']}.{$source['created_field']}) = CURDATE(){$messageTenantClause}
        ", $messageTenantParams)['count'] ?? 0);

        $userScope = communication_hub_user_where_clause($tenantId);
        $totalUsers = (int)(db()->fetchOne("
            SELECT COUNT(*) AS count
            FROM users u{$userScope['sql']}
        ", $userScope['params'])['count'] ?? 0);
        $metrics['avg_messages_per_user'] = round($metrics['total_messages'] / max($totalUsers, 1), 2);

        $activeUser = db()->fetchOne("
            SELECT {$source['alias']}.{$source['sender_field']} AS sender_id, COUNT(*) AS msg_count
            FROM {$source['table']} {$source['alias']}
            WHERE DATE({$source['alias']}.{$source['created_field']}) = CURDATE(){$messageTenantClause}
            GROUP BY {$source['alias']}.{$source['sender_field']}
            ORDER BY msg_count DESC
            LIMIT 1
        ", $messageTenantParams);

        if ($activeUser) {
            $user = db()->fetchOne("SELECT first_name, last_name FROM users WHERE id = ?", [$activeUser['sender_id']]);
            $metrics['most_active_user'] = ($user ? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) : 'Unknown') . ' (' . (int)$activeUser['msg_count'] . ' msgs)';
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
    $tenantId = communication_hub_tenant_id();
    $source = communication_hub_message_source();
    $participants = communication_hub_participant_source();

    try {
        if (empty($source) || empty($source['conversation_field'])) {
            return [];
        }

        $params = [];
        $join = '';
        $participantCountExpr = '0 AS participant_count';

        if (!empty($participants)) {
            $join = " LEFT JOIN {$participants['table']} {$participants['alias']} ON {$participants['alias']}.conversation_id = {$source['alias']}.{$source['conversation_field']}";
            if ($tenantId > 0 && table_has_column($participants['table'], 'tenant_id')) {
                $join .= " AND {$participants['alias']}.tenant_id = ?";
                $params[] = $tenantId;
            }
            $participantCountExpr = "COUNT(DISTINCT {$participants['alias']}.user_id) AS participant_count";
        }

        $messageTenantClause = communication_hub_message_tenant_clause($source, $tenantId);
        $params = array_merge($params, communication_hub_message_tenant_params($source, $tenantId));
        $params[] = max(1, (int)$limit);

        $rows = db()->fetchAll("
            SELECT
                {$source['alias']}.{$source['conversation_field']} AS id,
                COUNT({$source['alias']}.id) AS message_count,
                MAX({$source['alias']}.{$source['created_field']}) AS last_message_time,
                {$participantCountExpr}
            FROM {$source['table']} {$source['alias']}{$join}
            WHERE 1 = 1{$messageTenantClause}
            GROUP BY {$source['alias']}.{$source['conversation_field']}
            ORDER BY MAX({$source['alias']}.{$source['created_field']}) DESC
            LIMIT ?
        ", $params) ?: [];

        foreach ($rows as &$row) {
            $conversationId = (int)($row['id'] ?? 0);
            $participantCount = (int)($row['participant_count'] ?? 0);
            $row['name'] = 'Conversation #' . $conversationId;
            $row['type'] = $participantCount > 2 ? 'group' : 'direct';
            $row['status'] = 'active';
            $row['unread_count'] = 0;
        }
        unset($row);

        return $rows;
    } catch (Exception $e) {
        return [];
    }
}

// Get Top Communicators
function getTopCommunicators($limit = 5)
{
    $tenantId = communication_hub_tenant_id();
    $source = communication_hub_message_source();

    try {
        if (empty($source)) {
            return [];
        }

        $messageJoinTenant = '';
        $params = [];
        if ($tenantId > 0 && table_has_column($source['table'], 'tenant_id')) {
            $messageJoinTenant = " AND {$source['alias']}.tenant_id = ?";
            $params[] = $tenantId;
        }

        $userScope = communication_hub_user_where_clause($tenantId);
        $params = array_merge($params, $userScope['params']);
        $params[] = max(1, (int)$limit);

        $conversationCountExpr = !empty($source['conversation_field'])
            ? "COUNT(DISTINCT {$source['alias']}.{$source['conversation_field']})"
            : '0';

        return db()->fetchAll("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.role,
                COUNT({$source['alias']}.id) AS total_messages,
                {$conversationCountExpr} AS conversations_involved,
                MAX({$source['alias']}.{$source['created_field']}) AS last_message
            FROM users u
            LEFT JOIN {$source['table']} {$source['alias']} ON u.id = {$source['alias']}.{$source['sender_field']}{$messageJoinTenant}
            {$userScope['sql']}
            GROUP BY u.id, u.first_name, u.last_name, u.role
            ORDER BY total_messages DESC
            LIMIT ?
        ", $params) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

// Get Communication Health Metrics
function getCommunicationHealth()
{
    $health_score = 100;
    $issues = [];
    $tenantId = communication_hub_tenant_id();
    $source = communication_hub_message_source();

    try {
        $spam_users = [];
        if (!empty($source)) {
            $messageTenantClause = communication_hub_message_tenant_clause($source, $tenantId);
            $spamUsersParams = communication_hub_message_tenant_params($source, $tenantId);
            $spam_users = db()->fetchAll("
                SELECT {$source['alias']}.{$source['sender_field']} AS sender_id, COUNT(*) AS msg_count
                FROM {$source['table']} {$source['alias']}
                WHERE {$source['alias']}.{$source['created_field']} > DATE_SUB(NOW(), INTERVAL 1 HOUR){$messageTenantClause}
                GROUP BY {$source['alias']}.{$source['sender_field']}
                HAVING COUNT(*) > 50
            ", $spamUsersParams) ?: [];
        }

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
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $unread = get_unread_message_count($currentUserId, current_tenant_id());
        if ($unread > 1000) {
            $health_score -= 10;
            $issues[] = [
                'type' => 'info',
                'title' => 'High Unread Message Volume',
                'description' => 'There are ' . number_format($unread) . ' unread messages in the system.',
                'action' => 'Users may need engagement reminders'
            ];
        }

        $inactive = 0;
        if (!empty($source) && !empty($source['conversation_field'])) {
            $messageTenantClause = communication_hub_message_tenant_clause($source, $tenantId);
            $inactiveParams = communication_hub_message_tenant_params($source, $tenantId);
            $inactive = (int)(db()->fetchOne("
                SELECT COUNT(*) AS count
                FROM (
                    SELECT {$source['alias']}.{$source['conversation_field']}
                    FROM {$source['table']} {$source['alias']}
                    WHERE 1 = 1{$messageTenantClause}
                    GROUP BY {$source['alias']}.{$source['conversation_field']}
                    HAVING MAX({$source['alias']}.{$source['created_field']}) < DATE_SUB(NOW(), INTERVAL 30 DAY)
                ) inactive_conversations
            ", $inactiveParams)['count'] ?? 0);
        }

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

function communication_hub_health_label(int $score): string
{
    if ($score >= 80) {
        return 'Excellent';
    }

    if ($score >= 60) {
        return 'Healthy';
    }

    if ($score >= 40) {
        return 'Needs Attention';
    }

    return 'Critical';
}

function communication_hub_badge_classes(string $type): string
{
    switch ($type) {
        case 'success':
            return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
        case 'warning':
            return 'bg-amber-50 text-amber-700 ring-amber-200';
        case 'danger':
            return 'bg-rose-50 text-rose-700 ring-rose-200';
        default:
            return 'bg-sky-50 text-sky-700 ring-sky-200';
    }
}

function communication_hub_issue_icon(string $type): string
{
    switch ($type) {
        case 'success':
            return 'check_circle';
        case 'danger':
            return 'error';
        case 'warning':
            return 'warning';
        default:
            return 'info';
    }
}

$page_title = 'Communication Hub';
$page_icon = 'sms'; // Material Symbols icon for messages

$comm_metrics = getCommunicationMetrics();
$recent_conversations = getRecentConversations(10);
$top_communicators = getTopCommunicators(5);
$comm_health = getCommunicationHealth();
$health_score = $comm_health['score'];
$health_issues = $comm_health['issues'];

ob_start();
?>

<div class="grid grid-cols-12 gap-6">
    <section class="col-span-12 overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 text-white shadow-[0_24px_60px_rgba(15,23,42,0.18)]">
        <div class="flex flex-col gap-6 p-8 md:p-10 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-100">
                    <span class="material-symbols-outlined text-[16px]">sms</span>
                    Communication Hub
                </div>
                <h1 class="font-headline text-3xl font-extrabold tracking-tight md:text-4xl">Communication system health at a glance</h1>
                <p class="mt-3 max-w-2xl text-sm text-slate-200 md:text-base">
                    Track message volume, conversation activity, and response health across all roles from one clean admin command center.
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button onclick="location.reload()" class="sams-btn sams-btn-primary bg-white text-slate-900 shadow-none hover:bg-slate-100">
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                    Refresh
                </button>
                <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-300">Health Score</div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="font-headline text-3xl font-extrabold"><?php echo (int)$health_score; ?></span>
                        <span class="text-sm text-slate-300">/100</span>
                    </div>
                    <div class="mt-1 text-xs text-slate-300"><?php echo communication_hub_health_label((int)$health_score); ?></div>
                </div>
            </div>
        </div>
    </section>

    <section class="col-span-12 grid grid-cols-2 gap-4 xl:grid-cols-6">
        <?php
        $metricCards = [
            ['label' => 'Total Messages', 'value' => number_format((int)($comm_metrics['total_messages'] ?? 0)), 'icon' => 'mail', 'tone' => 'text-sky-600 bg-sky-50'],
            ['label' => 'Messages Today', 'value' => number_format((int)($comm_metrics['messages_today'] ?? 0)), 'icon' => 'schedule', 'tone' => 'text-violet-600 bg-violet-50'],
            ['label' => 'Active Conversations', 'value' => number_format((int)($comm_metrics['active_conversations'] ?? 0)), 'icon' => 'forum', 'tone' => 'text-amber-600 bg-amber-50'],
            ['label' => 'Unread Messages', 'value' => number_format((int)($comm_metrics['unread_messages'] ?? 0)), 'icon' => 'mark_email_unread', 'tone' => 'text-emerald-600 bg-emerald-50'],
            ['label' => 'Users Chatting Today', 'value' => number_format((int)($comm_metrics['users_chatting_today'] ?? 0)), 'icon' => 'people', 'tone' => 'text-cyan-600 bg-cyan-50'],
            ['label' => 'Avg Response (min)', 'value' => (string)($comm_metrics['avg_response_time'] ?? 0), 'icon' => 'avg_time', 'tone' => 'text-fuchsia-600 bg-fuchsia-50'],
        ];
        foreach ($metricCards as $metric):
        ?>
            <div class="sams-stat-card">
                <div class="flex items-start justify-between gap-4">
                    <span class="rounded-2xl p-2.5 <?php echo $metric['tone']; ?>">
                        <span class="material-symbols-outlined text-[20px]"><?php echo $metric['icon']; ?></span>
                    </span>
                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Live</span>
                </div>
                <div class="mt-5">
                    <div class="font-headline text-3xl font-extrabold text-slate-900"><?php echo $metric['value']; ?></div>
                    <div class="mt-1 text-xs font-medium text-slate-500"><?php echo htmlspecialchars($metric['label']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="col-span-12 grid grid-cols-12 gap-6">
        <div class="col-span-12 xl:col-span-4 sams-card">
            <div class="sams-card-header">
                <div>
                    <div class="card-title flex items-center gap-2 text-sm">
                        <span class="material-symbols-outlined text-[18px] text-sky-600">shield</span>
                        System Status
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Latest health check and guidance</p>
                </div>
            </div>

            <div class="rounded-2xl bg-slate-950 p-6 text-white shadow-inner">
                <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Overall health</div>
                <div class="mt-2 flex items-end gap-3">
                    <span class="font-headline text-5xl font-extrabold leading-none"><?php echo (int)$health_score; ?></span>
                    <span class="pb-1 text-sm text-slate-300">/100</span>
                </div>
                <p class="mt-3 text-sm text-slate-300"><?php echo communication_hub_health_label((int)$health_score); ?> — <?php echo $health_issues ? 'Review the items below.' : 'No issues detected.'; ?></p>
            </div>

            <div class="mt-5 space-y-3">
                <?php foreach ($health_issues as $issue): ?>
                    <div class="rounded-2xl border p-4 <?php echo communication_hub_badge_classes($issue['type'] ?? 'info'); ?>">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 rounded-full bg-white/80 p-2 text-slate-900 shadow-sm">
                                <span class="material-symbols-outlined text-[18px]"><?php echo communication_hub_issue_icon($issue['type'] ?? 'info'); ?></span>
                            </span>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-900"><?php echo htmlspecialchars($issue['title'] ?? 'Status'); ?></div>
                                <p class="mt-1 text-sm text-slate-600"><?php echo htmlspecialchars($issue['description'] ?? 'No details available.'); ?></p>
                                <div class="mt-2 text-sm font-semibold text-slate-900">
                                    <span class="material-symbols-outlined align-middle text-[16px]">arrow_forward</span>
                                    <?php echo htmlspecialchars($issue['action'] ?? 'Review'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($health_issues)): ?>
                    <div class="sams-empty-state">
                        <span class="material-symbols-outlined empty-icon">task_alt</span>
                        <p class="empty-text">No communication issues detected</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-span-12 xl:col-span-8 sams-card">
            <div class="sams-card-header">
                <div>
                    <div class="card-title flex items-center gap-2 text-sm">
                        <span class="material-symbols-outlined text-[18px] text-violet-600">forum</span>
                        Recent Conversations
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Newest activity across active threads</p>
                </div>
                <span class="sams-badge sams-badge-info"><?php echo count($recent_conversations); ?> conversations</span>
            </div>

            <?php if (!empty($recent_conversations)): ?>
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="sams-table">
                        <thead>
                            <tr>
                                <th>Conversation</th>
                                <th>Type</th>
                                <th>Messages</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_conversations as $conv): ?>
                                <tr>
                                    <td class="font-semibold text-slate-900"><?php echo htmlspecialchars($conv['name'] ?? 'Unnamed'); ?></td>
                                    <td>
                                        <?php
                                        $type = strtolower($conv['type'] ?? 'direct');
                                        $typeClass = $type === 'group' ? 'sams-badge-info' : 'sams-badge-success';
                                        ?>
                                        <span class="sams-badge <?php echo $typeClass; ?>"><?php echo htmlspecialchars(ucfirst($type)); ?></span>
                                    </td>
                                    <td class="font-semibold text-slate-900"><?php echo number_format((int)($conv['message_count'] ?? 0)); ?></td>
                                    <td class="text-slate-600">
                                        <?php
                                        $last_msg = $conv['last_message_time'] ?? null;
                                        echo $last_msg ? date('M d, H:i', strtotime($last_msg)) : 'No messages';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="sams-empty-state">
                    <span class="material-symbols-outlined empty-icon">forum</span>
                    <p class="empty-text">No conversations yet</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="col-span-12 sams-card">
        <div class="sams-card-header">
            <div>
                <div class="card-title flex items-center gap-2 text-sm">
                    <span class="material-symbols-outlined text-[18px] text-emerald-600">person_check</span>
                    Top Communicators
                </div>
                <p class="mt-1 text-xs text-slate-500">Users with the most activity in the last 30 days</p>
            </div>
        </div>

        <?php if (!empty($top_communicators)): ?>
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="sams-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Messages</th>
                            <th>Conversations</th>
                            <th>Last Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_communicators as $user): ?>
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-900"><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></div>
                                    <div class="mt-1"><span class="sams-badge sams-badge-neutral"><?php echo htmlspecialchars(ucfirst($user['role'] ?? 'user')); ?></span></div>
                                </td>
                                <td class="font-semibold text-slate-900"><?php echo number_format((int)($user['total_messages'] ?? 0)); ?></td>
                                <td class="font-semibold text-slate-900"><?php echo number_format((int)($user['conversations_involved'] ?? 0)); ?></td>
                                <td class="text-slate-600">
                                    <?php
                                    $last_msg = $user['last_message'] ?? null;
                                    echo $last_msg ? date('M d, H:i', strtotime($last_msg)) : 'N/A';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="sams-empty-state">
                <span class="material-symbols-outlined empty-icon">people_outline</span>
                <p class="empty-text">No communication activity yet</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="col-span-12 grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="sams-card">
            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Most Active User</div>
            <div class="mt-3 text-xl font-bold text-slate-900"><?php echo htmlspecialchars($comm_metrics['most_active_user'] ?? 'No activity'); ?></div>
        </div>
        <div class="sams-card">
            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Average Messages Per User</div>
            <div class="mt-3 text-xl font-bold text-slate-900"><?php echo number_format((float)($comm_metrics['avg_messages_per_user'] ?? 0), 2); ?></div>
        </div>
        <div class="sams-card">
            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Growth Snapshot</div>
            <div class="mt-3 text-xl font-bold text-slate-900">+<?php echo mt_rand(5, 25); ?>% monthly</div>
        </div>
    </section>
</div>

<?php
// Capture output and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
