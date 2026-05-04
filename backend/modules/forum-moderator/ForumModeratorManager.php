<?php
class ForumModeratorManager {
    private $db;
    private $tenant_id;
    
    public function __construct() {
        $this->db = db();
        $this->tenant_id = $_SESSION['tenant_id'] ?? 1;
    }
    
    public function getDashboardStats() {
        return ['status' => 'operational', 'role' => 'forum-moderator', 'tenant' => $this->tenant_id];
    }
    

    public function getFlaggedPosts() {
        $res = $this->db->query("SELECT * FROM forum_reports WHERE status = 'pending'");
        $reports = [];
        if($res) while($row = $res->fetch_assoc()) $reports[] = $row;
        return $reports;
    }
    public function moderateAction($report_id, $action) {
        $report_id = (int)$report_id;
        $action = mysqli_real_escape_string($this->db, $action);
        $this->db->query("UPDATE forum_reports SET status = '$action' WHERE id = $report_id");
        return true;
    }

}
