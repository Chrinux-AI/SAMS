<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class MemoryCache
{
    private static $instance = null;
    private $cache = [];
    private $cacheFile;
    private $cacheTimeout = 300; // 5 minutes
    
    private function __construct()
    {
        $this->cacheFile = __DIR__ . '/../cache/memory_cache.json';
        $this->loadCache();
        
        // Auto cleanup expired entries
        $this->cleanupExpired();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new MemoryCache();
        }
        return self::$instance;
    }
    
    /**
     * Store data in cache
     */
    public function set($key, $data, $ttl = null)
    {
        $ttl = $ttl ?? $this->cacheTimeout;
        $this->cache[$key] = [
            'data' => $data,
            'timestamp' => time(),
            'ttl' => $ttl
        ];
        
        $this->saveCache();
        return true;
    }
    
    /**
     * Get data from cache
     */
    public function get($key)
    {
        if (!isset($this->cache[$key])) {
            return null;
        }
        
        $item = $this->cache[$key];
        
        // Check if expired
        if (time() - $item['timestamp'] > $item['ttl']) {
            unset($this->cache[$key]);
            $this->saveCache();
            return null;
        }
        
        return $item['data'];
    }
    
    /**
     * Delete cache entry
     */
    public function delete($key)
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            $this->saveCache();
            return true;
        }
        return false;
    }
    
    /**
     * Clear all cache
     */
    public function clear()
    {
        $this->cache = [];
        $this->saveCache();
        return true;
    }
    
    /**
     * Cache active classes
     */
    public function cacheActiveClasses($tenantId)
    {
        $cacheKey = "active_classes_{$tenantId}";
        
        try {
            $classes = db()->fetchAll("
                SELECT c.id, c.class_name, c.class_code, c.teacher_id,
                       u.first_name, u.last_name,
                       COUNT(ce.student_id) as student_count
                FROM classes c
                LEFT JOIN users u ON c.teacher_id = u.id
                LEFT JOIN class_enrollments ce ON c.id = ce.class_id
                WHERE c.tenant_id = ? AND c.is_active = 1
                GROUP BY c.id, c.class_name, c.class_code, c.teacher_id, u.first_name, u.last_name
                ORDER BY c.class_name
            ", [$tenantId]);
            
            $this->set($cacheKey, $classes);
            return $classes;
        } catch (Exception $e) {
            error_log("MemoryCache::cacheActiveClasses error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get cached active classes
     */
    public function getActiveClasses($tenantId)
    {
        $cacheKey = "active_classes_{$tenantId}";
        $classes = $this->get($cacheKey);
        
        if ($classes === null) {
            return $this->cacheActiveClasses($tenantId);
        }
        
        return $classes;
    }
    
    /**
     * Cache today's attendance summary
     */
    public function cacheTodayAttendance($tenantId)
    {
        $cacheKey = "today_attendance_{$tenantId}_" . date('Y-m-d');
        
        try {
            $today = date('Y-m-d');
            $attendance = db()->fetchAll("
                SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    ROUND(
                        (SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 
                        2
                    ) as attendance_rate
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.id
                WHERE DATE(ar.check_in_time) = ? AND u.tenant_id = ?
            ", [$today, $tenantId]);
            
            $summary = $attendance[0] ?? [
                'total_records' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'attendance_rate' => 0
            ];
            
            $this->set($cacheKey, $summary, 600); // Cache for 10 minutes
            return $summary;
        } catch (Exception $e) {
            error_log("MemoryCache::cacheTodayAttendance error: " . $e->getMessage());
            return [
                'total_records' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'attendance_rate' => 0
            ];
        }
    }
    
    /**
     * Get cached today's attendance
     */
    public function getTodayAttendance($tenantId)
    {
        $cacheKey = "today_attendance_{$tenantId}_" . date('Y-m-d');
        $attendance = $this->get($cacheKey);
        
        if ($attendance === null) {
            return $this->cacheTodayAttendance($tenantId);
        }
        
        return $attendance;
    }
    
    /**
     * Cache dashboard summary for user
     */
    public function cacheDashboardSummary($userId, $role, $tenantId)
    {
        $cacheKey = "dashboard_summary_{$userId}_{$role}_{$tenantId}";
        
        try {
            $summary = [];
            
            switch ($role) {
                case 'teacher':
                    $summary = $this->getTeacherDashboardSummary($userId, $tenantId);
                    break;
                case 'student':
                    $summary = $this->getStudentDashboardSummary($userId, $tenantId);
                    break;
                case 'parent':
                    $summary = $this->getParentDashboardSummary($userId, $tenantId);
                    break;
                case 'admin':
                    $summary = $this->getAdminDashboardSummary($tenantId);
                    break;
            }
            
            $this->set($cacheKey, $summary, 300); // Cache for 5 minutes
            return $summary;
        } catch (Exception $e) {
            error_log("MemoryCache::cacheDashboardSummary error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get teacher dashboard summary
     */
    private function getTeacherDashboardSummary($teacherId, $tenantId)
    {
        $today = date('Y-m-d');
        
        // Get teacher's classes
        $classes = db()->fetchAll("
            SELECT COUNT(*) as class_count,
                   COUNT(DISTINCT ce.student_id) as student_count
            FROM classes c
            LEFT JOIN class_enrollments ce ON c.id = ce.class_id
            WHERE c.teacher_id = ? AND c.tenant_id = ?
        ", [$teacherId, $tenantId]);
        
        // Get today's attendance
        $attendance = db()->fetchOne("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance_records ar
            JOIN classes c ON ar.class_id = c.id
            WHERE c.teacher_id = ? AND DATE(ar.check_in_time) = ? AND c.tenant_id = ?
        ", [$teacherId, $today, $tenantId]);
        
        // Get unread messages
        $messages = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM message_recipients mr
            WHERE mr.recipient_id = ? AND mr.is_read = 0
        ", [$teacherId]);
        
        return [
            'classes_count' => $classes[0]['class_count'] ?? 0,
            'students_count' => $classes[0]['student_count'] ?? 0,
            'today_attendance_total' => $attendance['total'] ?? 0,
            'today_attendance_present' => $attendance['present'] ?? 0,
            'unread_messages' => $messages['count'] ?? 0,
            'attendance_rate' => $attendance['total'] > 0 ? 
                round(($attendance['present'] / $attendance['total']) * 100, 1) : 0
        ];
    }
    
    /**
     * Get student dashboard summary
     */
    private function getStudentDashboardSummary($studentId, $tenantId)
    {
        $today = date('Y-m-d');
        
        // Get attendance stats
        $attendance = db()->fetchOne("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                   SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            FROM attendance_records
            WHERE student_id = ? AND tenant_id = ? AND DATE(check_in_time) = ?
        ", [$studentId, $tenantId, $today]);
        
        // Get enrolled classes
        $classes = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM class_enrollments ce
            JOIN classes c ON ce.class_id = c.id
            WHERE ce.student_id = ? AND c.tenant_id = ? AND c.is_active = 1
        ", [$studentId, $tenantId]);
        
        // Get unread messages
        $messages = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM message_recipients mr
            WHERE mr.recipient_id = ? AND mr.is_read = 0
        ", [$studentId]);
        
        return [
            'today_status' => $attendance['total'] > 0 ? 
                ($attendance['present'] > 0 ? 'present' : ($attendance['late'] > 0 ? 'late' : 'absent')) : 'not_marked',
            'enrolled_classes' => $classes['count'] ?? 0,
            'unread_messages' => $messages['count'] ?? 0
        ];
    }
    
    /**
     * Get parent dashboard summary
     */
    private function getParentDashboardSummary($parentId, $tenantId)
    {
        // Get children count
        $children = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM parent_student_links psl
            JOIN users u ON psl.student_id = u.id
            WHERE psl.parent_id = ? AND u.tenant_id = ? AND u.status = 'active'
        ", [$parentId, $tenantId]);
        
        // Get unread messages
        $messages = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM message_recipients mr
            WHERE mr.recipient_id = ? AND mr.is_read = 0
        ", [$parentId]);
        
        return [
            'children_count' => $children['count'] ?? 0,
            'unread_messages' => $messages['count'] ?? 0
        ];
    }
    
    /**
     * Get admin dashboard summary
     */
    private function getAdminDashboardSummary($tenantId)
    {
        $today = date('Y-m-d');
        
        // Get system stats
        $stats = db()->fetchOne("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active') as active_users,
                (SELECT COUNT(*) FROM classes WHERE tenant_id = ? AND is_active = 1) as active_classes,
                (SELECT COUNT(*) FROM attendance_records WHERE DATE(check_in_time) = ?) as today_attendance
        ", [$tenantId, $tenantId, $today]);
        
        return [
            'active_users' => $stats['active_users'] ?? 0,
            'active_classes' => $stats['active_classes'] ?? 0,
            'today_attendance' => $stats['today_attendance'] ?? 0
        ];
    }
    
    /**
     * Load cache from file
     */
    private function loadCache()
    {
        if (file_exists($this->cacheFile)) {
            $data = json_decode(file_get_contents($this->cacheFile), true);
            if ($data) {
                $this->cache = $data;
            }
        }
    }
    
    /**
     * Save cache to file
     */
    private function saveCache()
    {
        $json = json_encode($this->cache);
        file_put_contents($this->cacheFile, $json, LOCK_EX);
    }
    
    /**
     * Clean up expired entries
     */
    private function cleanupExpired()
    {
        $currentTime = time();
        $removed = false;
        
        foreach ($this->cache as $key => $item) {
            if ($currentTime - $item['timestamp'] > $item['ttl']) {
                unset($this->cache[$key]);
                $removed = true;
            }
        }
        
        if ($removed) {
            $this->saveCache();
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $total = count($this->cache);
        $expired = 0;
        $currentTime = time();
        
        foreach ($this->cache as $item) {
            if ($currentTime - $item['timestamp'] > $item['ttl']) {
                $expired++;
            }
        }
        
        return [
            'total_entries' => $total,
            'expired_entries' => $expired,
            'valid_entries' => $total - $expired,
            'cache_file_size' => file_exists($this->cacheFile) ? filesize($this->cacheFile) : 0
        ];
    }
}
