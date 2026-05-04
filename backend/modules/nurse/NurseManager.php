<?php

class NurseManager
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
        if (!$this->tableExists('health_incidents')) {
            return [
                'status' => 'unavailable',
                'role' => 'nurse',
                'tenant' => $this->tenant_id,
                'incidents_today' => 0,
                'open_parent_notices' => 0,
            ];
        }

        $summary = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN DATE(incident_date) = CURDATE() THEN 1 ELSE 0 END), 0) AS incidents_today,
                COALESCE(SUM(CASE WHEN requires_parental_notice = 1 THEN 1 ELSE 0 END), 0) AS open_parent_notices
             FROM health_incidents
             WHERE tenant_id = ?",
            [$this->tenant_id]
        ) ?: [];

        return [
            'status' => 'operational',
            'role' => 'nurse',
            'tenant' => $this->tenant_id,
            'incidents_today' => (int)($summary['incidents_today'] ?? 0),
            'open_parent_notices' => (int)($summary['open_parent_notices'] ?? 0),
        ];
    }

    public function logIncident($data)
    {
        $this->requireTable('health_incidents');

        $studentId = (int)($data['student_id'] ?? 0);
        $nurseId = (int)($_SESSION['user_id'] ?? 0);
        $symptoms = trim((string)($data['symptoms'] ?? ''));
        $treatment = trim((string)($data['treatment'] ?? ''));
        $requiresParentalNotice = !empty($data['requires_parental_notice']) ? 1 : 0;

        if ($studentId <= 0) {
            throw new InvalidArgumentException('A valid student is required.');
        }
        if ($nurseId <= 0) {
            throw new RuntimeException('A logged-in nurse session is required.');
        }
        if ($symptoms === '') {
            throw new InvalidArgumentException('Symptoms are required.');
        }

        $incidentId = $this->db->insert('health_incidents', [
            'student_id' => $studentId,
            'nurse_id' => $nurseId,
            'symptoms' => $symptoms,
            'treatment_provided' => $treatment !== '' ? $treatment : null,
            'requires_parental_notice' => $requiresParentalNotice,
            'tenant_id' => $this->tenant_id,
        ]);

        if (!$incidentId) {
            throw new RuntimeException('Unable to log medical incident.');
        }

        return ['incident_id' => (int)$incidentId];
    }

    private function requireTable($table)
    {
        if (!$this->tableExists($table)) {
            throw new RuntimeException("Required table '{$table}' is missing.");
        }
    }

    private function tableExists($table)
    {
        return (bool)$this->db->fetchOne("SHOW TABLES LIKE ?", [$table]);
    }
}
