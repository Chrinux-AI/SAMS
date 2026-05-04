<?php

class PrincipalManager
{
    private $db;
    private $tenant_id;

    public function __construct()
    {
        $this->db = db();
        $this->tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    }

    public function getDashboardStats()
    {
        $studentCount = $this->countByTenant('students');
        $classCount = $this->countByTenant('classes');
        $teacherCount = $this->countUsersByRole('teacher');
        $pendingApprovals = $this->countPendingApprovals();

        return [
            'status' => 'operational',
            'role' => 'principal',
            'tenant' => $this->tenant_id,
            'students' => $studentCount,
            'classes' => $classCount,
            'teachers' => $teacherCount,
            'pending_approvals' => $pendingApprovals,
        ];
    }

    public function getSchoolAnalytics()
    {
        $attendanceRate = 0;
        if ($this->tableExists('attendance') && $this->tableExists('students')) {
            $attendanceWhere = $this->tableHasColumn('students', 'tenant_id')
                ? 'WHERE s.tenant_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'
                : 'WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
            $attendanceParams = $this->tableHasColumn('students', 'tenant_id') ? [$this->tenant_id] : [];

            $attendance = $this->db->fetchOne(
                "SELECT
                    COUNT(*) AS total_marks,
                    COALESCE(SUM(CASE WHEN a.status IN ('present', 'late', 'excused') THEN 1 ELSE 0 END), 0) AS present_marks
                 FROM attendance a
                 INNER JOIN students s ON s.id = a.student_id
                 {$attendanceWhere}",
                $attendanceParams
            ) ?: [];

            $totalMarks = (int)($attendance['total_marks'] ?? 0);
            $presentMarks = (int)($attendance['present_marks'] ?? 0);
            if ($totalMarks > 0) {
                $attendanceRate = round(($presentMarks / $totalMarks) * 100, 2);
            }
        }

        return [
            'total_students' => $this->countByTenant('students'),
            'total_teachers' => $this->countUsersByRole('teacher'),
            'total_classes' => $this->countByTenant('classes'),
            'pending_approvals' => $this->countPendingApprovals(),
            'attendance_rate_30d' => $attendanceRate,
            'operational_status' => 'Operational',
        ];
    }

    private function countByTenant($table)
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        if ($this->tableHasColumn($table, 'tenant_id')) {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS aggregate_count FROM {$table} WHERE tenant_id = ?",
                [$this->tenant_id]
            ) ?: [];
        } else {
            $row = $this->db->fetchOne("SELECT COUNT(*) AS aggregate_count FROM {$table}") ?: [];
        }

        return (int)($row['aggregate_count'] ?? 0);
    }

    private function countUsersByRole($role)
    {
        if (!$this->tableExists('users')) {
            return 0;
        }

        $where = 'role = ?';
        $params = [$role];

        if ($this->tableHasColumn('users', 'tenant_id')) {
            $where .= ' AND tenant_id = ?';
            $params[] = $this->tenant_id;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS aggregate_count FROM users WHERE {$where}",
            $params
        ) ?: [];

        return (int)($row['aggregate_count'] ?? 0);
    }

    private function countPendingApprovals()
    {
        if (!$this->tableExists('users') || !$this->tableHasColumn('users', 'approved')) {
            return 0;
        }

        $where = 'approved = 0';
        $params = [];

        if ($this->tableHasColumn('users', 'tenant_id')) {
            $where .= ' AND tenant_id = ?';
            $params[] = $this->tenant_id;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS aggregate_count FROM users WHERE {$where}",
            $params
        ) ?: [];

        return (int)($row['aggregate_count'] ?? 0);
    }

    private function tableExists($table)
    {
        return (bool)$this->db->fetchOne("SHOW TABLES LIKE ?", [$table]);
    }

    private function tableHasColumn($table, $column)
    {
        return (bool)$this->db->fetchOne("SHOW COLUMNS FROM {$table} LIKE ?", [$column]);
    }
}
