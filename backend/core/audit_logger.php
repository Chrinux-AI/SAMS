<?php
/**
 * SAMS Core Audit Logger
 * Comprehensive audit logging system
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

class AuditLogger {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Log audit entry
     */
    public function log($action, $entityType = null, $entityId = null, $before = null, $after = null) {
        $data = [
            'actor_id' => $_SESSION['user_id'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_state' => $before ? json_encode($before) : null,
            'after_state' => $after ? json_encode($after) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('audit_logs', $data);
    }
    
    /**
     * Log user action
     */
    public function logUserAction($action, $userId, $before = null, $after = null) {
        return $this->log($action, 'user', $userId, $before, $after);
    }
    
    /**
     * Log attendance action
     */
    public function logAttendanceAction($action, $attendanceId, $before = null, $after = null) {
        return $this->log($action, 'attendance', $attendanceId, $before, $after);
    }
    
    /**
     * Log grade action
     */
    public function logGradeAction($action, $gradeId, $before = null, $after = null) {
        return $this->log($action, 'grade', $gradeId, $before, $after);
    }
    
    /**
     * Log financial action
     */
    public function logFinancialAction($action, $transactionId, $before = null, $after = null) {
        return $this->log($action, 'financial', $transactionId, $before, $after);
    }
    
    /**
     * Log system action
     */
    public function logSystemAction($action, $details = null) {
        return $this->log($action, 'system', null, null, $details);
    }
    
    /**
     * Get audit logs
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        $where = [];
        $params = [];
        
        // Build where clause
        if (!empty($filters['actor_id'])) {
            $where[] = "actor_id = ?";
            $params[] = $filters['actor_id'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = "action LIKE ?";
            $params[] = "%{$filters['action']}%";
        }
        
        if (!empty($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "
            SELECT al.*, u.first_name, u.last_name, u.role
            FROM audit_logs al
            LEFT JOIN users u ON al.actor_id = u.id
            $whereClause
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get audit statistics
     */
    public function getStatistics($timeframe = '7 days') {
        $sql = "
            SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT actor_id) as unique_users,
                COUNT(DISTINCT action) as unique_actions,
                COUNT(DISTINCT entity_type) as unique_entities,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL $timeframe) THEN 1 END) as recent_logs
            FROM audit_logs
        ";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * Get user activity summary
     */
    public function getUserActivitySummary($userId, $days = 30) {
        $sql = "
            SELECT 
                action,
                entity_type,
                COUNT(*) as count,
                MAX(created_at) as last_action
            FROM audit_logs
            WHERE actor_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action, entity_type
            ORDER BY count DESC
        ";
        
        return $this->db->fetchAll($sql, [$userId, $days]);
    }
    
    /**
     * Get entity history
     */
    public function getEntityHistory($entityType, $entityId, $limit = 50) {
        $sql = "
            SELECT al.*, u.first_name, u.last_name
            FROM audit_logs al
            LEFT JOIN users u ON al.actor_id = u.id
            WHERE al.entity_type = ? AND al.entity_id = ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ";
        
        return $this->db->fetchAll($sql, [$entityType, $entityId, $limit]);
    }
    
    /**
     * Search audit logs
     */
    public function searchLogs($query, $limit = 100) {
        $sql = "
            SELECT al.*, u.first_name, u.last_name, u.role
            FROM audit_logs al
            LEFT JOIN users u ON al.actor_id = u.id
            WHERE al.action LIKE ? OR al.entity_type LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ";
        
        $searchTerm = "%$query%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    }
    
    /**
     * Export audit logs to CSV
     */
    public function exportToCSV($filters = []) {
        $logs = $this->getLogs($filters, 10000); // Export up to 10k records
        
        $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = UPLOAD_PATH . $filename;
        
        $file = fopen($filepath, 'w');
        
        // CSV header
        fputcsv($file, [
            'ID', 'Actor', 'Role', 'Action', 'Entity Type', 'Entity ID', 
            'IP Address', 'User Agent', 'Created At'
        ]);
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($file, [
                $log['id'],
                ($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''),
                $log['role'] ?? '',
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                $log['ip_address'],
                $log['user_agent'],
                $log['created_at']
            ]);
        }
        
        fclose($file);
        
        return $filename;
    }
    
    /**
     * Clean old audit logs
     */
    public function cleanOldLogs($days = 365) {
        $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        return $this->db->query($sql, [$days]);
    }
    
    /**
     * Get suspicious activity patterns
     */
    public function getSuspiciousActivity($hours = 24) {
        $patterns = [];
        
        // Multiple failed logins
        $failedLogins = $this->db->fetchAll("
            SELECT ip_address, COUNT(*) as failed_attempts
            FROM audit_logs
            WHERE action LIKE '%login_failed%' AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY ip_address
            HAVING failed_attempts >= 5
        ", [$hours]);
        
        if (!empty($failedLogins)) {
            $patterns['multiple_failed_logins'] = $failedLogins;
        }
        
        // Bulk actions
        $bulkActions = $this->db->fetchAll("
            SELECT actor_id, action, COUNT(*) as action_count, 
                   MIN(created_at) as first_action, MAX(created_at) as last_action
            FROM audit_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY actor_id, action
            HAVING action_count >= 10 AND TIMESTAMPDIFF(MINUTE, first_action, last_action) <= 5
        ", [$hours]);
        
        if (!empty($bulkActions)) {
            $patterns['bulk_actions'] = $bulkActions;
        }
        
        // Unusual time activity
        $unusualTime = $this->db->fetchAll("
            SELECT actor_id, COUNT(*) as night_actions
            FROM audit_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            AND HOUR(created_at) BETWEEN 0 AND 5
            GROUP BY actor_id
            HAVING night_actions >= 5
        ", [$hours]);
        
        if (!empty($unusualTime)) {
            $patterns['unusual_time_activity'] = $unusualTime;
        }
        
        return $patterns;
    }
}

// Global audit logger instance
function audit_logger() {
    static $instance = null;
    if ($instance === null) {
        $instance = new AuditLogger();
    }
    return $instance;
}

// Convenience functions
function log_audit($action, $entityType = null, $entityId = null, $before = null, $after = null) {
    return audit_logger()->log($action, $entityType, $entityId, $before, $after);
}

function log_user_action($action, $userId, $before = null, $after = null) {
    return audit_logger()->logUserAction($action, $userId, $before, $after);
}

function log_attendance_action($action, $attendanceId, $before = null, $after = null) {
    return audit_logger()->logAttendanceAction($action, $attendanceId, $before, $after);
}

function log_grade_action($action, $gradeId, $before = null, $after = null) {
    return audit_logger()->logGradeAction($action, $gradeId, $before, $after);
}

function log_financial_action($action, $transactionId, $before = null, $after = null) {
    return audit_logger()->logFinancialAction($action, $transactionId, $before, $after);
}

function log_system_action($action, $details = null) {
    return audit_logger()->logSystemAction($action, $details);
}

// Auto-load audit logger
$GLOBALS['audit_logger'] = audit_logger();
?>
