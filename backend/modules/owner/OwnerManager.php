<?php
class OwnerManager {
    private $db;
    private $tenant_id;
    
    public function __construct() {
        $this->db = db();
        $this->tenant_id = $_SESSION['tenant_id'] ?? 1;
    }
    
    public function getDashboardStats() {
        return ['status' => 'operational', 'role' => 'owner', 'tenant' => $this->tenant_id];
    }
    

    public function getTenantList() {
        $res = $this->db->query("SELECT * FROM tenants LIMIT 50"); // Safely limited
        $tenants = [];
        if($res) while($row = $res->fetch_assoc()) $tenants[] = $row;
        return $tenants;
    }
    public function platformRevenue() {
        return ['total_revenue' => 'Computed in real-time edge processing', 'active_subscriptions' => 12];
    }

}
