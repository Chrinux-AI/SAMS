<?php

/**
 * SAMS JSON Memory Cache Service
 * Lightweight in-memory caching for frequently accessed data
 * Reduces database calls with automatic expiration
 * Small footprint with safe database fallback
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

class JsonMemoryCache
{
    private $cache = [];
    private $ttl = [];
    private $maxSize = 1000; // Maximum number of cached items
    private $maxMemory = 1048576; // 1MB maximum memory usage
    private $defaultTtl = 300; // 5 minutes default TTL
    private $hitCount = 0;
    private $missCount = 0;
    private $db;

    public function __construct()
    {
        $this->db = db();
        $this->initCache();
    }

    /**
     * Initialize cache with default settings
     */
    private function initCache()
    {
        // Set default TTL values for different data types
        $this->ttl = [
            'roles' => 1800,        // 30 minutes
            'classes' => 600,        // 10 minutes
            'attendance_summaries' => 900, // 15 minutes
            'users' => 300,         // 5 minutes
            'students' => 600,       // 10 minutes
            'teachers' => 600,       // 10 minutes
            'settings' => 3600,      // 1 hour
            'statistics' => 1800     // 30 minutes
        ];
    }

    /**
     * Get cached data or fetch from database
     */
    public function get($key, $callback = null, $ttl = null)
    {
        $this->cleanupExpired();

        // Check if data exists and is not expired
        if (isset($this->cache[$key]) && !$this->isExpired($key)) {
            $this->hitCount++;
            return $this->cache[$key]['data'];
        }

        $this->missCount++;

        // If no callback provided, return null
        if (!$callback) {
            return null;
        }

        try {
            // Fetch data using callback
            $data = $callback();

            // Cache the data
            $this->set($key, $data, $ttl);

            return $data;
        } catch (Exception $e) {
            // Log error and return null
            error_log("Cache callback error for key '$key': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set data in cache
     */
    public function set($key, $data, $ttl = null)
    {
        // Check memory usage
        if ($this->getMemoryUsage() > $this->maxMemory) {
            $this->evictOldest();
        }

        // Check cache size
        if (count($this->cache) >= $this->maxSize) {
            $this->evictOldest();
        }

        // Determine TTL
        $effectiveTtl = $ttl ?? $this->getTtlForKey($key) ?? $this->defaultTtl;

        // Store data with metadata
        $this->cache[$key] = [
            'data' => $data,
            'created_at' => time(),
            'expires_at' => time() + $effectiveTtl,
            'ttl' => $effectiveTtl,
            'size' => strlen(serialize($data))
        ];

        return true;
    }

    /**
     * Delete cached data
     */
    public function delete($key)
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
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
        return true;
    }

    /**
     * Get cached roles
     */
    public function getRoles()
    {
        return $this->get('roles', function () {
            return $this->db->fetchAll("
                SELECT id, role_name, description, permissions, created_at, updated_at
                FROM roles
                WHERE is_active = 1
                ORDER BY role_name
            ");
        });
    }

    /**
     * Get cached classes
     */
    public function getClasses()
    {
        return $this->get('classes', function () {
            return $this->db->fetchAll("
                SELECT c.*,
                       u.first_name as teacher_first_name,
                       u.last_name as teacher_last_name
                FROM classes c
                LEFT JOIN users u ON c.class_teacher_id = u.id
                WHERE c.is_active = 1
                ORDER BY c.grade_level, c.class_name
            ");
        });
    }

    /**
     * Get cached attendance summaries
     */
    public function getAttendanceSummaries($date = null, $classId = null)
    {
        $cacheKey = 'attendance_summaries_' . ($date ?? 'all') . '_' . ($classId ?? 'all');

        return $this->get($cacheKey, function () use ($date, $classId) {
            $where = ['1=1'];
            $params = [];

            if ($date) {
                $where[] = 'date = ?';
                $params[] = $date;
            }

            if ($classId) {
                $where[] = 'class_id = ?';
                $params[] = $classId;
            }

            $whereClause = implode(' AND ', $where);

            $results = $this->db->fetchAll("
                SELECT
                    date,
                    class_id,
                    COUNT(*) as total_students,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                    ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*) * 100), 2) as attendance_rate
                FROM attendance_records
                WHERE $whereClause
                GROUP BY date, class_id
                ORDER BY date DESC, class_id
            ", $params);

            // Format results for easier use
            $formatted = [];
            foreach ($results as $row) {
                $formatted[] = [
                    'date' => $row['date'],
                    'class_id' => $row['class_id'],
                    'total_students' => (int)$row['total_students'],
                    'present_count' => (int)$row['present_count'],
                    'absent_count' => (int)$row['absent_count'],
                    'late_count' => (int)$row['late_count'],
                    'attendance_rate' => (float)$row['attendance_rate']
                ];
            }

            return $formatted;
        });
    }

    /**
     * Get cached user by ID
     */
    public function getUser($userId)
    {
        $cacheKey = 'user_' . $userId;

        return $this->get($cacheKey, function () use ($userId) {
            return $this->db->fetchOne("
                SELECT id, email, first_name, last_name, role, is_active,
                       last_login, created_at, updated_at
                FROM users
                WHERE id = ?
            ", [$userId]);
        });
    }

    /**
     * Get cached students by class
     */
    public function getStudentsByClass($classId)
    {
        $cacheKey = 'students_class_' . $classId;

        return $this->get($cacheKey, function () use ($classId) {
            return $this->db->fetchAll("
                SELECT s.*,
                       u.first_name, u.last_name, u.email as parent_email
                FROM students s
                LEFT JOIN users u ON s.parent_id = u.id
                WHERE s.class_id = ? AND s.is_active = 1
                ORDER BY s.first_name, s.last_name
            ", [$classId]);
        });
    }

    /**
     * Get cached teachers
     */
    public function getTeachers()
    {
        return $this->get('teachers', function () {
            return $this->db->fetchAll("
                SELECT id, first_name, last_name, email, phone, department,
                       qualification, experience, is_active, created_at, updated_at
                FROM teachers
                WHERE is_active = 1
                ORDER BY last_name, first_name
            ");
        });
    }

    /**
     * Get cached system settings
     */
    public function getSettings()
    {
        return $this->get('settings', function () {
            // Get settings from database or config
            $settings = [];

            // Try to get from database first
            try {
                $dbSettings = $this->db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE is_active = 1");

                foreach ($dbSettings as $setting) {
                    $settings[$setting['setting_key']] = $setting['setting_value'];
                }
            } catch (Exception $e) {
                // Fallback to config constants
                $settings = [
                    'app_name' => defined('APP_NAME') ? APP_NAME : 'SAMS',
                    'app_version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
                    'timezone' => defined('TIMEZONE') ? TIMEZONE : 'UTC',
                    'session_timeout' => defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600,
                    'max_login_attempts' => defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5
                ];
            }

            return $settings;
        });
    }

    /**
     * Get cached statistics
     */
    public function getStatistics()
    {
        return $this->get('statistics', function () {
            $stats = [];

            // User statistics
            $stats['users'] = [
                'total' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
                'active' => $this->db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
                'by_role' => $this->db->fetchAll("
                    SELECT role, COUNT(*) as count
                    FROM users
                    GROUP BY role
                    ORDER BY count DESC
                ")
            ];

            // Class statistics
            $stats['classes'] = [
                'total' => $this->db->fetchOne("SELECT COUNT(*) as count FROM classes WHERE is_active = 1")['count'],
                'by_grade' => $this->db->fetchAll("
                    SELECT grade_level, COUNT(*) as count
                    FROM classes
                    WHERE is_active = 1
                    GROUP BY grade_level
                    ORDER BY grade_level
                ")
            ];

            // Student statistics
            $stats['students'] = [
                'total' => $this->db->fetchOne("SELECT COUNT(*) as count FROM students WHERE is_active = 1")['count'],
                'by_grade' => $this->db->fetchAll("
                    SELECT c.grade_level, COUNT(*) as count
                    FROM students s
                    JOIN classes c ON s.class_id = c.id
                    WHERE s.is_active = 1 AND c.is_active = 1
                    GROUP BY c.grade_level
                    ORDER BY c.grade_level
                ")
            ];

            // Teacher statistics
            $stats['teachers'] = [
                'total' => $this->db->fetchOne("SELECT COUNT(*) as count FROM teachers WHERE is_active = 1")['count'],
                'by_department' => $this->db->fetchAll("
                    SELECT department, COUNT(*) as count
                    FROM teachers
                    WHERE is_active = 1
                    GROUP BY department
                    ORDER BY count DESC
                ")
            ];

            // Attendance statistics
            $stats['attendance'] = [
                'today' => $this->db->fetchOne("
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                        ROUND(AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END), 2) as avg_rate
                    FROM attendance_records
                    WHERE date = CURDATE()
                "),
                'this_week' => $this->db->fetchOne("
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                        ROUND(AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END), 2) as avg_rate
                    FROM attendance_records
                    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                "),
                'this_month' => $this->db->fetchOne("
                    SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                        ROUND(AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END), 2) as avg_rate
                    FROM attendance_records
                    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ")
            ];

            return $stats;
        });
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidate($pattern)
    {
        $keys = array_keys($this->cache);

        foreach ($keys as $key) {
            if (fnmatch($pattern, $key)) {
                unset($this->cache[$key]);
            }
        }

        return true;
    }

    /**
     * Invalidate cache by key prefix
     */
    public function invalidatePrefix($prefix)
    {
        $keys = array_keys($this->cache);

        foreach ($keys as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->cache[$key]);
            }
        }

        return true;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStatistics()
    {
        $this->cleanupExpired();

        $totalRequests = $this->hitCount + $this->missCount;
        $hitRate = $totalRequests > 0 ? round(($this->hitCount / $totalRequests) * 100, 2) : 0;

        $cacheSize = count($this->cache);
        $memoryUsage = $this->getMemoryUsage();

        $expiredCount = 0;
        $totalSize = 0;

        foreach ($this->cache as $key => $item) {
            if ($this->isExpired($key)) {
                $expiredCount++;
            }
            $totalSize += $item['size'];
        }

        return [
            'total_requests' => $totalRequests,
            'hit_count' => $this->hitCount,
            'miss_count' => $this->missCount,
            'hit_rate' => $hitRate,
            'cache_size' => $cacheSize,
            'memory_usage' => $memoryUsage,
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
            'expired_items' => $expiredCount,
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'max_size' => $this->maxSize,
            'max_memory_mb' => round($this->maxMemory / 1024 / 1024, 2)
        ];
    }

    /**
     * Get cache statistics (alias)
     */
    public function getStatistics()
    {
        return $this->getCacheStatistics();
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUp()
    {
        $warmed = [];

        // Warm up roles
        try {
            $this->getRoles();
            $warmed[] = 'roles';
        } catch (Exception $e) {
            // Skip if error
        }

        // Warm up classes
        try {
            $this->getClasses();
            $warmed[] = 'classes';
        } catch (Exception $e) {
            // Skip if error
        }

        // Warm up settings
        try {
            $this->getSettings();
            $warmed[] = 'settings';
        } catch (Exception $e) {
            // Skip if error
        }

        // Warm up teachers
        try {
            $this->getTeachers();
            $warmed[] = 'teachers';
        } catch (Exception $e) {
            // Skip if error
        }

        return $warmed;
    }

    /**
     * Check if cache item is expired
     */
    private function isExpired($key)
    {
        if (!isset($this->cache[$key])) {
            return true;
        }

        return time() > $this->cache[$key]['expires_at'];
    }

    /**
     * Clean up expired items
     */
    private function cleanupExpired()
    {
        $keys = array_keys($this->cache);

        foreach ($keys as $key) {
            if ($this->isExpired($key)) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Get TTL for a specific key
     */
    private function getTtlForKey($key)
    {
        // Extract data type from key
        if (strpos($key, 'user_') === 0) {
            return $this->ttl['users'];
        } elseif (strpos($key, 'students_class_') === 0) {
            return $this->ttl['students'];
        } elseif (strpos($key, 'attendance_summaries_') === 0) {
            return $this->ttl['attendance_summaries'];
        }

        // Check direct match
        if (isset($this->ttl[$key])) {
            return $this->ttl[$key];
        }

        return null;
    }

    /**
     * Get current memory usage
     */
    private function getMemoryUsage()
    {
        $totalSize = 0;

        foreach ($this->cache as $item) {
            $totalSize += $item['size'];
        }

        return $totalSize;
    }

    /**
     * Evict oldest items from cache
     */
    private function evictOldest()
    {
        if (empty($this->cache)) {
            return;
        }

        // Sort by creation time
        uasort($this->cache, function ($a, $b) {
            return $a['created_at'] - $b['created_at'];
        });

        // Remove oldest 10% of items
        $removeCount = max(1, intval(count($this->cache) * 0.1));
        $keys = array_keys($this->cache);

        for ($i = 0; $i < $removeCount && $i < count($keys); $i++) {
            unset($this->cache[$keys[$i]]);
        }
    }

    /**
     * Get hit rate
     */
    public function getHitRate()
    {
        $totalRequests = $this->hitCount + $this->missCount;
        return $totalRequests > 0 ? round(($this->hitCount / $totalRequests) * 100, 2) : 0;
    }

    /**
     * Check if key exists in cache
     */
    public function has($key)
    {
        $this->cleanupExpired();
        return isset($this->cache[$key]) && !$this->isExpired($key);
    }

    /**
     * Get multiple keys at once
     */
    public function getMultiple($keys, $callbacks = [])
    {
        $results = [];

        foreach ($keys as $key) {
            $callback = $callbacks[$key] ?? null;
            $results[$key] = $this->get($key, $callback);
        }

        return $results;
    }

    /**
     * Set multiple keys at once
     */
    public function setMultiple($items, $ttl = null)
    {
        foreach ($items as $key => $data) {
            $this->set($key, $data, $ttl);
        }

        return true;
    }

    /**
     * Delete multiple keys at once
     */
    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Get all cache keys
     */
    public function getKeys()
    {
        $this->cleanupExpired();
        return array_keys($this->cache);
    }

    /**
     * Get cache item count
     */
    public function getItemCount()
    {
        $this->cleanupExpired();
        return count($this->cache);
    }

    /**
     * Check if cache is empty
     */
    public function isEmpty()
    {
        $this->cleanupExpired();
        return empty($this->cache);
    }

    /**
     * Check if cache is healthy
     */
    public function isHealthy()
    {
        $this->cleanupExpired();

        // Check memory usage
        if ($this->getMemoryUsage() > $this->maxMemory) {
            return false;
        }

        // Check cache size
        if (count($this->cache) > $this->maxSize) {
            return false;
        }

        // Check hit rate (should be reasonable)
        $totalRequests = $this->hitCount + $this->missCount;
        if ($totalRequests > 100 && $this->getHitRate() < 50) {
            return false;
        }

        return true;
    }

    /**
     * Reset statistics
     */
    public function resetStatistics()
    {
        $this->hitCount = 0;
        $this->missCount = 0;
        return true;
    }

    /**
     * Set custom TTL for a key
     */
    public function setTtl($key, $ttl)
    {
        $this->ttl[$key] = $ttl;

        // Update existing cache item if it exists
        if (isset($this->cache[$key])) {
            $this->cache[$key]['ttl'] = $ttl;
            $this->cache[$key]['expires_at'] = time() + $ttl;
        }

        return true;
    }

    /**
     * Get cache efficiency score (0-100)
     */
    public function getEfficiencyScore()
    {
        $stats = $this->getCacheStatistics();

        $score = 0;

        // Hit rate (40% weight)
        $score += ($stats['hit_rate'] / 100) * 40;

        // Memory efficiency (30% weight) - lower is better
        $memoryEfficiency = max(0, 100 - ($stats['memory_usage_mb'] / 10)); // 10MB = 0 points
        $score += $memoryEfficiency * 0.3;

        // Size efficiency (30% weight) - lower is better
        $sizeEfficiency = max(0, 100 - ($stats['cache_size'] / 10)); // 10 items = 0 points
        $score += $sizeEfficiency * 0.3;

        return round($score, 2);
    }

    /**
     * Get cache debug information
     */
    public function debugInfo()
    {
        $this->cleanupExpired();

        $info = [
            'cache_keys' => array_keys($this->cache),
            'cache_size' => count($this->cache),
            'memory_usage' => $this->getMemoryUsage(),
            'memory_usage_mb' => round($this->getMemoryUsage() / 1024 / 1024, 2),
            'hit_count' => $this->hitCount,
            'miss_count' => $this->missCount,
            'hit_rate' => $this->getHitRate(),
            'ttl_settings' => $this->ttl,
            'max_size' => $this->maxSize,
            'max_memory_mb' => round($this->maxMemory / 1024 / 1024, 2)
        ];

        // Add details for each cache item
        $info['items'] = [];

        foreach ($this->cache as $key => $item) {
            $info['items'][$key] = [
                'created_at' => date('Y-m-d H:i:s', $item['created_at']),
                'expires_at' => date('Y-m-d H:i:s', $item['expires_at']),
                'ttl' => $item['ttl'],
                'size' => $item['size'],
                'size_kb' => round($item['size'] / 1024, 2),
                'expired' => $this->isExpired($key)
            ];
        }

        return $info;
    }
}
