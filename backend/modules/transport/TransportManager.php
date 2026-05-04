<?php

class TransportManager
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
        if (!$this->tableExists('transport_fleet')) {
            return [
                'status' => 'unavailable',
                'role' => 'transport',
                'tenant' => $this->tenant_id,
                'vehicles' => 0,
                'routes' => 0,
            ];
        }

        $vehicleSummary = $this->db->fetchOne(
            "SELECT COUNT(*) AS vehicles, COALESCE(SUM(capacity), 0) AS capacity
             FROM transport_fleet
             WHERE tenant_id = ?",
            [$this->tenant_id]
        ) ?: [];

        $routes = 0;
        if ($this->tableExists('transport_routes_extended')) {
            $routeSummary = $this->db->fetchOne(
                "SELECT COUNT(*) AS routes
                 FROM transport_routes_extended
                 WHERE tenant_id = ?",
                [$this->tenant_id]
            ) ?: [];
            $routes = (int)($routeSummary['routes'] ?? 0);
        } elseif ($this->tableExists('transport_routes')) {
            $routeSummary = $this->db->fetchOne("SELECT COUNT(*) AS routes FROM transport_routes") ?: [];
            $routes = (int)($routeSummary['routes'] ?? 0);
        }

        return [
            'status' => 'operational',
            'role' => 'transport',
            'tenant' => $this->tenant_id,
            'vehicles' => (int)($vehicleSummary['vehicles'] ?? 0),
            'fleet_capacity' => (int)($vehicleSummary['capacity'] ?? 0),
            'routes' => $routes,
        ];
    }

    public function addVehicle($data)
    {
        $this->requireTable('transport_fleet');

        $plate = strtoupper(trim((string)($data['plate'] ?? $data['vehicle_plate'] ?? '')));
        $capacity = (int)($data['capacity'] ?? 0);
        $driverName = trim((string)($data['driver_name'] ?? ''));

        if ($plate === '') {
            throw new InvalidArgumentException('Vehicle plate is required.');
        }
        if ($capacity <= 0) {
            throw new InvalidArgumentException('Vehicle capacity must be greater than zero.');
        }

        $vehicleId = $this->db->insert('transport_fleet', [
            'vehicle_plate' => $plate,
            'capacity' => $capacity,
            'driver_name' => $driverName !== '' ? $driverName : null,
            'tenant_id' => $this->tenant_id,
        ]);

        if (!$vehicleId) {
            throw new RuntimeException('Unable to register vehicle.');
        }

        return ['vehicle_id' => (int)$vehicleId];
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
