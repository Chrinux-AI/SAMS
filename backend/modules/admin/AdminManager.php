<?php

class AdminManager
{
    private $db;
    private $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->db = db();
        $this->tenantId = $tenantId ?? (int)($_SESSION['tenant_id'] ?? 1);
    }

    public function getDashboardPayload(int $recentLimit = 10, int $riskLimit = 5): array
    {
        return [
            'summary' => $this->getSummaryStats(),
            'attendance_today' => $this->getTodayAttendanceStats(),
            'recent_records' => $this->getRecentAttendanceRecords($recentLimit),
            'risk_students' => $this->getRiskStudents($riskLimit),
        ];
    }

    public function getSummaryStats(): array
    {
        return [
            'students' => $this->countRows('students', ['is_active' => 1]),
            'teachers' => $this->countUsersByRole('teacher', ['is_active' => 1]),
            'classes' => $this->countRows('classes', ['is_active' => 1]),
            'parents' => $this->countUsersByRole('parent', ['is_active' => 1]),
        ];
    }

    public function getTodayAttendanceStats(): array
    {
        if (!$this->tableExists('attendance')) {
            return [
                'total' => 0,
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'rate' => 0,
            ];
        }

        $sql = "
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END), 0) AS present,
                COALESCE(SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END), 0) AS late,
                COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) AS absent
            FROM attendance a
        ";

        [$joinSql, $whereSql, $params] = $this->buildAttendanceTenantScope('a');
        $sql .= $joinSql . " WHERE a.date = ?" . $whereSql;
        array_unshift($params, date('Y-m-d'));

        $row = $this->db->fetchOne($sql, $params) ?: [];

        $total = (int)($row['total'] ?? 0);
        $present = (int)($row['present'] ?? 0);
        $late = (int)($row['late'] ?? 0);
        $absent = (int)($row['absent'] ?? 0);

        return [
            'total' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'rate' => $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0,
        ];
    }

    public function getRecentAttendanceRecords(int $limit = 10): array
    {
        if (!$this->tableExists('attendance')) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $sql = "
            SELECT
                a.status,
                a.date,
                u.first_name,
                u.last_name,
                c.class_name,
                CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS student_name
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN classes c ON a.class_id = c.id
        ";

        [$joinSql, $whereSql, $params] = $this->buildAttendanceTenantScope('a');
        $sql .= $joinSql . " WHERE 1=1" . $whereSql . " ORDER BY a.created_at DESC LIMIT {$limit}";

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    public function getRiskStudents(int $limit = 5): array
    {
        if (!$this->tableExists('students') || !$this->tableExists('attendance')) {
            return [];
        }

        $limit = max(1, min(20, $limit));
        $sql = "
            SELECT
                s.id,
                s.admission_number,
                u.first_name,
                u.last_name,
                COUNT(a.id) AS total_days,
                COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) AS absent_days
            FROM students s
            INNER JOIN users u ON s.user_id = u.id
            INNER JOIN attendance a ON a.student_id = s.id
            WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ";

        $params = [];
        if ($this->tableHasColumn('students', 'tenant_id')) {
            $sql .= " AND s.tenant_id = ?";
            $params[] = $this->tenantId;
        }

        $sql .= "
            GROUP BY s.id, s.admission_number, u.first_name, u.last_name
            HAVING (COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) / COUNT(a.id)) > 0.2
            ORDER BY (COALESCE(SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END), 0) / COUNT(a.id)) DESC
            LIMIT {$limit}
        ";

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    private function countRows(string $table, array $filters = []): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        $whereParts = ['1=1'];
        $params = [];

        if ($this->tableHasColumn($table, 'tenant_id')) {
            $whereParts[] = 'tenant_id = ?';
            $params[] = $this->tenantId;
        }

        foreach ($filters as $column => $value) {
            if ($this->tableHasColumn($table, $column)) {
                $whereParts[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS aggregate_count FROM {$table} WHERE " . implode(' AND ', $whereParts),
            $params
        ) ?: [];

        return (int)($row['aggregate_count'] ?? 0);
    }

    private function countUsersByRole(string $role, array $filters = []): int
    {
        if (!$this->tableExists('users')) {
            return 0;
        }

        $whereParts = ['role = ?'];
        $params = [$role];

        if ($this->tableHasColumn('users', 'tenant_id')) {
            $whereParts[] = 'tenant_id = ?';
            $params[] = $this->tenantId;
        }

        foreach ($filters as $column => $value) {
            if ($this->tableHasColumn('users', $column)) {
                $whereParts[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS aggregate_count FROM users WHERE " . implode(' AND ', $whereParts),
            $params
        ) ?: [];

        return (int)($row['aggregate_count'] ?? 0);
    }

    private function buildAttendanceTenantScope(string $attendanceAlias): array
    {
        if ($this->tableHasColumn('attendance', 'tenant_id')) {
            return ['', " AND {$attendanceAlias}.tenant_id = ?", [$this->tenantId]];
        }

        if ($this->tableHasColumn('students', 'tenant_id')) {
            return [
                " LEFT JOIN students s_scope ON s_scope.id = {$attendanceAlias}.student_id",
                ' AND s_scope.tenant_id = ?',
                [$this->tenantId],
            ];
        }

        if ($this->tableHasColumn('classes', 'tenant_id')) {
            return [
                " LEFT JOIN classes c_scope ON c_scope.id = {$attendanceAlias}.class_id",
                ' AND c_scope.tenant_id = ?',
                [$this->tenantId],
            ];
        }

        return ['', '', []];
    }

    private function tableExists(string $table): bool
    {
        return (bool)$this->db->fetchOne("SHOW TABLES LIKE ?", [$table]);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        return (bool)$this->db->fetchOne("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
    }
}
