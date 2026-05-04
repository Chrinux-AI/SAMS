<?php

class ParentManager
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
        $wards = $this->getWardRows();

        return [
            'status' => 'operational',
            'role' => 'parent',
            'tenant' => $this->tenant_id,
            'wards_count' => count($wards),
            'latest_attendance_date' => $wards[0]['last_attendance_date'] ?? null,
        ];
    }

    public function getWardOverview()
    {
        $wards = array_map(function ($ward) {
            return [
                'student_id' => $ward['student_id'],
                'student_user_id' => $ward['student_user_id'],
                'name' => $ward['student_name'],
                'admission_number' => $ward['admission_number'],
                'class_name' => $ward['class_name'],
                'status' => $ward['attendance_status'],
                'last_attendance_date' => $ward['last_attendance_date'],
                'relationship' => $ward['relationship'],
                'alerts' => [],
            ];
        }, $this->getWardRows());

        return ['wards' => $wards];
    }

    private function getWardRows()
    {
        $parentUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($parentUserId <= 0) {
            return [];
        }

        if ($this->tableExists('parent_student_links')) {
            return $this->db->fetchAll(
                "SELECT
                    su.id AS student_user_id,
                    s.id AS student_id,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(su.first_name, ''), ' ', COALESCE(su.last_name, ''))), ''), su.full_name, CONCAT('Student #', su.id)) AS student_name,
                    s.admission_number,
                    c.class_name,
                    psl.relationship,
                    (
                        SELECT a.status
                        FROM attendance a
                        WHERE a.student_id = s.id
                        ORDER BY a.date DESC, a.id DESC
                        LIMIT 1
                    ) AS attendance_status,
                    (
                        SELECT a.date
                        FROM attendance a
                        WHERE a.student_id = s.id
                        ORDER BY a.date DESC, a.id DESC
                        LIMIT 1
                    ) AS last_attendance_date
                 FROM parent_student_links psl
                 INNER JOIN users su ON su.id = psl.student_id
                 LEFT JOIN students s ON s.user_id = su.id
                 LEFT JOIN classes c ON c.id = s.class_id
                 WHERE psl.parent_id = ?
                 ORDER BY student_name ASC",
                [$parentUserId]
            ) ?: [];
        }

        if ($this->tableExists('parents') && $this->tableExists('parent_student')) {
            return $this->db->fetchAll(
                "SELECT
                    u.id AS student_user_id,
                    s.id AS student_id,
                    COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), u.full_name, CONCAT('Student #', s.id)) AS student_name,
                    s.admission_number,
                    c.class_name,
                    ps.relationship,
                    (
                        SELECT a.status
                        FROM attendance a
                        WHERE a.student_id = s.id
                        ORDER BY a.date DESC, a.id DESC
                        LIMIT 1
                    ) AS attendance_status,
                    (
                        SELECT a.date
                        FROM attendance a
                        WHERE a.student_id = s.id
                        ORDER BY a.date DESC, a.id DESC
                        LIMIT 1
                    ) AS last_attendance_date
                 FROM parents p
                 INNER JOIN parent_student ps ON ps.parent_id = p.id
                 INNER JOIN students s ON s.id = ps.student_id
                 LEFT JOIN users u ON u.id = s.user_id
                 LEFT JOIN classes c ON c.id = s.class_id
                 WHERE p.user_id = ?
                 ORDER BY student_name ASC",
                [$parentUserId]
            ) ?: [];
        }

        return [];
    }

    private function tableExists($table)
    {
        return (bool)$this->db->fetchOne("SHOW TABLES LIKE ?", [$table]);
    }
}
