<?php
/**
 * SAMS Core Team Selector
 * Global team selection component for all roles
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

class TeamSelector {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Get user's teams
     */
    public function getUserTeams($userId = null) {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        
        if (!$userId) {
            return [];
        }
        
        $sql = "
            SELECT t.id, t.name, t.description, t.type, tm.role as member_role,
                   tm.joined_at, u.first_name as leader_name, u.last_name as leader_last_name
            FROM teams t
            JOIN team_members tm ON t.id = tm.team_id
            LEFT JOIN users u ON t.leader_id = u.id
            WHERE tm.user_id = ? AND t.is_active = 1
            ORDER BY tm.joined_at DESC
        ";
        
        return $this->db->fetchAll($sql, [$userId]);
    }
    
    /**
     * Get team details
     */
    public function getTeamDetails($teamId) {
        $sql = "
            SELECT t.*, u.first_name as leader_first_name, u.last_name as leader_last_name,
                   COUNT(tm.id) as member_count
            FROM teams t
            LEFT JOIN users u ON t.leader_id = u.id
            LEFT JOIN team_members tm ON t.id = tm.team_id
            WHERE t.id = ? AND t.is_active = 1
            GROUP BY t.id
        ";
        
        return $this->db->fetchOne($sql, [$teamId]);
    }
    
    /**
     * Get team members
     */
    public function getTeamMembers($teamId) {
        $sql = "
            SELECT tm.*, u.first_name, u.last_name, u.email, u.role as user_role
            FROM team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ?
            ORDER BY tm.role DESC, u.first_name, u.last_name
        ";
        
        return $this->db->fetchAll($sql, [$teamId]);
    }
    
    /**
     * Create team
     */
    public function createTeam($name, $description, $type, $leaderId = null) {
        $leaderId = $leaderId ?? $_SESSION['user_id'];
        
        $teamId = $this->db->insert('teams', [
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'leader_id' => $leaderId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Add leader as member
        $this->addTeamMember($teamId, $leaderId, 'leader');
        
        // Log team creation
        log_system_action('team_created', [
            'team_id' => $teamId,
            'name' => $name,
            'type' => $type
        ]);
        
        return $teamId;
    }
    
    /**
     * Add team member
     */
    public function addTeamMember($teamId, $userId, $role = 'member') {
        // Check if already a member
        $existing = $this->db->fetchOne(
            "SELECT id FROM team_members WHERE team_id = ? AND user_id = ?",
            [$teamId, $userId]
        );
        
        if ($existing) {
            return false; // Already a member
        }
        
        $memberId = $this->db->insert('team_members', [
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role,
            'joined_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log member addition
        log_system_action('team_member_added', [
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role
        ]);
        
        return $memberId;
    }
    
    /**
     * Remove team member
     */
    public function removeTeamMember($teamId, $userId) {
        $result = $this->db->delete('team_members', 'team_id = ? AND user_id = ?', [$teamId, $userId]);
        
        if ($result) {
            // Log member removal
            log_system_action('team_member_removed', [
                'team_id' => $teamId,
                'user_id' => $userId
            ]);
        }
        
        return $result;
    }
    
    /**
     * Update team member role
     */
    public function updateMemberRole($teamId, $userId, $role) {
        $result = $this->db->update('team_members', ['role' => $role], 'team_id = ? AND user_id = ?', [$teamId, $userId]);
        
        if ($result) {
            // Log role update
            log_system_action('team_member_role_updated', [
                'team_id' => $teamId,
                'user_id' => $userId,
                'new_role' => $role
            ]);
        }
        
        return $result;
    }
    
    /**
     * Update team
     */
    public function updateTeam($teamId, $name, $description, $type, $leaderId = null) {
        $data = [
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($leaderId) {
            $data['leader_id'] = $leaderId;
        }
        
        $result = $this->db->update('teams', $data, 'id = ?', [$teamId]);
        
        if ($result) {
            // Log team update
            log_system_action('team_updated', [
                'team_id' => $teamId,
                'name' => $name,
                'type' => $type
            ]);
        }
        
        return $result;
    }
    
    /**
     * Delete team
     */
    public function deleteTeam($teamId) {
        // Get team details for logging
        $team = $this->getTeamDetails($teamId);
        
        // Delete team members first
        $this->db->delete('team_members', 'team_id = ?', [$teamId]);
        
        // Delete team
        $result = $this->db->delete('teams', 'id = ?', [$teamId]);
        
        if ($result && $team) {
            // Log team deletion
            log_system_action('team_deleted', [
                'team_id' => $teamId,
                'name' => $team['name']
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get all teams
     */
    public function getAllTeams($filters = []) {
        $where = [];
        $params = [];
        
        if (!empty($filters['type'])) {
            $where[] = "t.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "t.name LIKE ?";
            $params[] = "%{$filters['search']}%";
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "
            SELECT t.*, u.first_name as leader_first_name, u.last_name as leader_last_name,
                   COUNT(tm.id) as member_count
            FROM teams t
            LEFT JOIN users u ON t.leader_id = u.id
            LEFT JOIN team_members tm ON t.id = tm.team_id
            $whereClause
            GROUP BY t.id
            ORDER BY t.name
        ";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get available users for team assignment
     */
    public function getAvailableUsers($teamId = null, $search = '') {
        $excludeClause = '';
        $params = [];
        
        if ($teamId) {
            $excludeClause = "AND u.id NOT IN (SELECT user_id FROM team_members WHERE team_id = ?)";
            $params[] = $teamId;
        }
        
        $searchClause = '';
        if ($search) {
            $searchClause = "AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql = "
            SELECT u.id, u.first_name, u.last_name, u.email, u.role
            FROM users u
            WHERE u.is_active = 1 $excludeClause $searchClause
            ORDER BY u.first_name, u.last_name
        ";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Check if user is team leader
     */
    public function isTeamLeader($userId, $teamId) {
        $team = $this->getTeamDetails($teamId);
        return $team && $team['leader_id'] == $userId;
    }
    
    /**
     * Check if user is team member
     */
    public function isTeamMember($userId, $teamId) {
        $member = $this->db->fetchOne(
            "SELECT id FROM team_members WHERE team_id = ? AND user_id = ?",
            [$teamId, $userId]
        );
        
        return !empty($member);
    }
    
    /**
     * Get user's role in team
     */
    public function getUserTeamRole($userId, $teamId) {
        $member = $this->db->fetchOne(
            "SELECT role FROM team_members WHERE team_id = ? AND user_id = ?",
            [$teamId, $userId]
        );
        
        return $member['role'] ?? null;
    }
    
    /**
     * Get team statistics
     */
    public function getTeamStatistics() {
        $sql = "
            SELECT 
                COUNT(*) as total_teams,
                COUNT(CASE WHEN type = 'academic' THEN 1 END) as academic_teams,
                COUNT(CASE WHEN type = 'administrative' THEN 1 END) as administrative_teams,
                COUNT(CASE WHEN type = 'extracurricular' THEN 1 END) as extracurricular_teams,
                COUNT(CASE WHEN type = 'sports' THEN 1 END) as sports_teams,
                COUNT(DISTINCT leader_id) as team_leaders,
                COUNT(tm.id) as total_members
            FROM teams t
            LEFT JOIN team_members tm ON t.id = tm.team_id
            WHERE t.is_active = 1
        ";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * Get team activity
     */
    public function getTeamActivity($teamId, $days = 30) {
        $sql = "
            SELECT al.action, al.created_at, u.first_name, u.last_name
            FROM audit_logs al
            JOIN users u ON al.actor_id = u.id
            WHERE al.entity_type = 'team' AND al.entity_id = ?
            AND al.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY al.created_at DESC
        ";
        
        return $this->db->fetchAll($sql, [$teamId, $days]);
    }
    
    /**
     * Generate team selection HTML
     */
    public function generateTeamSelector($userId = null, $selectedTeamId = null, $name = 'team_id', $class = 'form-control') {
        $teams = $this->getUserTeams($userId);
        
        $html = "<select name='$name' class='$class' id='team-selector'>";
        $html .= "<option value=''>Select Team...</option>";
        
        foreach ($teams as $team) {
            $selected = ($selectedTeamId == $team['id']) ? 'selected' : '';
            $html .= "<option value='{$team['id']}' $selected>{$team['name']} ({$team['type']})</option>";
        }
        
        $html .= "</select>";
        
        return $html;
    }
    
    /**
     * Generate team member badges HTML
     */
    public function generateTeamMemberBadges($teamId) {
        $members = $this->getTeamMembers($teamId);
        $html = '';
        
        foreach ($members as $member) {
            $badgeClass = $member['role'] === 'leader' ? 'badge-primary' : 'badge-secondary';
            $html .= "<span class='badge $badgeClass me-1'>{$member['first_name']} {$member['last_name']} ({$member['role']})</span>";
        }
        
        return $html;
    }
}

// Global team selector instance
function team_selector() {
    static $instance = null;
    if ($instance === null) {
        $instance = new TeamSelector();
    }
    return $instance;
}

// Convenience functions
function get_user_teams($userId = null) {
    return team_selector()->getUserTeams($userId);
}

function get_team_details($teamId) {
    return team_selector()->getTeamDetails($teamId);
}

function create_team($name, $description, $type, $leaderId = null) {
    return team_selector()->createTeam($name, $description, $type, $leaderId);
}

function add_team_member($teamId, $userId, $role = 'member') {
    return team_selector()->addTeamMember($teamId, $userId, $role);
}

function is_team_leader($userId, $teamId) {
    return team_selector()->isTeamLeader($userId, $teamId);
}

function is_team_member($userId, $teamId) {
    return team_selector()->isTeamMember($userId, $teamId);
}

// Auto-load team selector
$GLOBALS['team_selector'] = team_selector();
?>
