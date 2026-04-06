<?php
/**
 * User Service
 * Handles user management, CRUD operations, and user-related workflows
 */

class SAMS_UserService extends SAMS_BaseService {
    
    /**
     * Create a new user with role-specific profile
     */
    public function createUser($data) {
        return $this->transactional(function() use ($data) {
            // Validate required fields
            $validation = $this->validateUserData($data);
            if (!$validation['valid']) {
                return ['success' => false, 'errors' => $validation['errors']];
            }
            
            // Check for duplicate email
            if ($this->emailExists($data['email'])) {
                return ['success' => false, 'error' => 'Email already exists'];
            }
            
            // Prepare user data
            $userData = [
                'email' => strtolower(trim($data['email'])),
                'role' => $data['role'],
                'status' => $data['status'] ?? 'inactive',
                'tenant_id' => $data['tenant_id'] ?? 1,
                'created_by' => $_SESSION['user_id'] ?? null
            ];
            
            // Set password if provided, otherwise mark for OTP setup
            if (!empty($data['password'])) {
                $userData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            } else {
                $userData['password_hash'] = null;
                $userData['requires_setup'] = true;
            }
            
            // Insert main user record
            $userId = $this->insertUser($userData);
            
            if (!$userId) {
                throw new Exception("Failed to create user");
            }
            
            // Create role-specific profile
            $this->createRoleProfile($userId, $data['role'], $data);
            
            // Log creation
            $this->log('USER_CREATED', [
                'user_id' => $userId,
                'role' => $data['role'],
                'created_by' => $userData['created_by']
            ]);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'requires_setup' => !empty($userData['requires_setup'])
            ];
        });
    }
    
    /**
     * Create multiple users in bulk
     */
    public function createUsersBulk($usersData, $defaultRole = 'student') {
        $results = [
            'success' => [],
            'failed' => [],
            'total' => count($usersData)
        ];
        
        foreach ($usersData as $index => $userData) {
            // Set default role if not specified
            if (empty($userData['role'])) {
                $userData['role'] = $defaultRole;
            }
            
            $result = $this->createUser($userData);
            
            if ($result['success']) {
                $results['success'][] = [
                    'index' => $index,
                    'email' => $userData['email'],
                    'user_id' => $result['user_id']
                ];
            } else {
                $results['failed'][] = [
                    'index' => $index,
                    'email' => $userData['email'],
                    'error' => $result['error'] ?? $result['errors']
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Update existing user
     */
    public function updateUser($userId, $data) {
        $userId = (int)$userId;
        
        // Check if user exists
        $existingUser = $this->getUserById($userId);
        if (!$existingUser) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        // Build update fields
        $updates = [];
        
        if (isset($data['email']) && $data['email'] !== $existingUser['email']) {
            if ($this->emailExists($data['email'], $userId)) {
                return ['success' => false, 'error' => 'Email already in use'];
            }
            $updates['email'] = strtolower(trim($data['email']));
        }
        
        if (isset($data['status'])) {
            $updates['status'] = $data['status'];
        }
        
        if (!empty($data['password'])) {
            $updates['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $updates['requires_setup'] = false;
        }
        
        if (empty($updates)) {
            return ['success' => true, 'message' => 'No changes made'];
        }
        
        // Perform update
        $updateFields = [];
        foreach ($updates as $field => $value) {
            $escapedValue = mysqli_real_escape_string($this->db, $value);
            $updateFields[] = "$field = '$escapedValue'";
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = $userId";
        
        if ($this->db->query($sql)) {
            // Update role-specific profile if data provided
            if (isset($data['profile'])) {
                $this->updateRoleProfile($userId, $existingUser['role'], $data['profile']);
            }
            
            $this->log('USER_UPDATED', ['user_id' => $userId, 'fields' => array_keys($updates)]);
            
            return ['success' => true, 'user_id' => $userId];
        }
        
        return ['success' => false, 'error' => 'Update failed'];
    }
    
    /**
     * Delete user (soft delete)
     */
    public function deleteUser($userId) {
        $userId = (int)$userId;
        
        // Soft delete - mark as deleted
        $sql = "UPDATE users SET status = 'deleted', deleted_at = NOW(), deleted_by = " . 
               ($_SESSION['user_id'] ?? 'NULL') . " WHERE id = $userId";
        
        if ($this->db->query($sql)) {
            $this->log('USER_DELETED', ['user_id' => $userId]);
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Delete failed'];
    }
    
    /**
     * Get user by ID with role profile
     */
    public function getUser($userId) {
        $userId = (int)$userId;
        
        $result = $this->db->query("SELECT * FROM users WHERE id = $userId AND status != 'deleted' LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            unset($user['password_hash']);
            
            // Load role-specific profile
            $user['profile'] = $this->getRoleProfile($userId, $user['role']);
            
            return $user;
        }
        
        return null;
    }
    
    /**
     * Search users with filters
     */
    public function searchUsers($filters = [], $limit = 50, $offset = 0) {
        $where = ["status != 'deleted'"];
        
        if (!empty($filters['role'])) {
            $role = mysqli_real_escape_string($this->db, $filters['role']);
            $where[] = "role = '$role'";
        }
        
        if (!empty($filters['status'])) {
            $status = mysqli_real_escape_string($this->db, $filters['status']);
            $where[] = "status = '$status'";
        }
        
        if (!empty($filters['tenant_id'])) {
            $tenantId = (int)$filters['tenant_id'];
            $where[] = "tenant_id = $tenantId";
        }
        
        if (!empty($filters['search'])) {
            $search = mysqli_real_escape_string($this->db, $filters['search']);
            $where[] = "(email LIKE '%$search%' OR id IN (SELECT user_id FROM teachers WHERE full_name LIKE '%$search%') OR id IN (SELECT user_id FROM students WHERE full_name LIKE '%$search%'))";
        }
        
        $whereClause = implode(' AND ', $where);
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "SELECT id, email, role, status, tenant_id, created_at, last_login 
                FROM users 
                WHERE $whereClause 
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $result = $this->db->query($sql);
        $users = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
        }
        
        // Get total count
        $countResult = $this->db->query("SELECT COUNT(*) as total FROM users WHERE $whereClause");
        $total = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;
        
        return [
            'users' => $users,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Validate user data
     */
    private function validateUserData($data) {
        $errors = [];
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($data['role'])) {
            $errors[] = 'Role is required';
        } elseif (!in_array($data['role'], $this->getValidRoles())) {
            $errors[] = 'Invalid role specified';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email, $excludeUserId = null) {
        $email = strtolower(trim($email));
        $escapedEmail = mysqli_real_escape_string($this->db, $email);
        
        $sql = "SELECT id FROM users WHERE email = '$escapedEmail' AND status != 'deleted'";
        
        if ($excludeUserId) {
            $excludeUserId = (int)$excludeUserId;
            $sql .= " AND id != $excludeUserId";
        }
        
        $result = $this->db->query($sql);
        return $result && mysqli_num_rows($result) > 0;
    }
    
    /**
     * Insert user into database
     */
    private function insertUser($data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = $field;
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $escapedValue = mysqli_real_escape_string($this->db, $value);
                $values[] = "'$escapedValue'";
            }
        }
        
        $fields[] = 'created_at';
        $values[] = 'NOW()';
        
        $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        
        if ($this->db->query($sql)) {
            return $this->db->insert_id;
        }
        
        return false;
    }
    
    /**
     * Create role-specific profile
     */
    private function createRoleProfile($userId, $role, $data) {
        $userId = (int)$userId;
        
        $profileTables = [
            'teacher' => 'teachers',
            'student' => 'students',
            'parent' => 'parents'
        ];
        
        if (!isset($profileTables[$role])) {
            return;
        }
        
        $table = $profileTables[$role];
        
        // Extract profile fields based on role
        $profileData = ['user_id' => $userId];
        
        if ($role === 'teacher') {
            $profileData['full_name'] = $data['full_name'] ?? '';
            $profileData['employee_id'] = $data['employee_id'] ?? '';
            $profileData['department'] = $data['department'] ?? '';
            $profileData['qualifications'] = $data['qualifications'] ?? '';
        } elseif ($role === 'student') {
            $profileData['full_name'] = $data['full_name'] ?? '';
            $profileData['admission_no'] = $data['admission_no'] ?? '';
            $profileData['grade_level'] = $data['grade_level'] ?? '';
            $profileData['parent_id'] = $data['parent_id'] ?? null;
        } elseif ($role === 'parent') {
            $profileData['full_name'] = $data['full_name'] ?? '';
            $profileData['relationship'] = $data['relationship'] ?? '';
            $profileData['occupation'] = $data['occupation'] ?? '';
        }
        
        // Build insert query
        $fields = [];
        $values = [];
        
        foreach ($profileData as $field => $value) {
            $fields[] = $field;
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $escapedValue = mysqli_real_escape_string($this->db, $value);
                $values[] = "'$escapedValue'";
            }
        }
        
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
        $this->db->query($sql);
    }
    
    /**
     * Get role profile
     */
    private function getRoleProfile($userId, $role) {
        $userId = (int)$userId;
        
        $profileTables = [
            'teacher' => 'teachers',
            'student' => 'students',
            'parent' => 'parents'
        ];
        
        if (!isset($profileTables[$role])) {
            return null;
        }
        
        $table = $profileTables[$role];
        $result = $this->db->query("SELECT * FROM $table WHERE user_id = $userId LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Update role profile
     */
    private function updateRoleProfile($userId, $role, $profileData) {
        $userId = (int)$userId;
        
        $profileTables = [
            'teacher' => 'teachers',
            'student' => 'students',
            'parent' => 'parents'
        ];
        
        if (!isset($profileTables[$role])) {
            return;
        }
        
        $table = $profileTables[$role];
        
        $updates = [];
        foreach ($profileData as $field => $value) {
            if ($value === null) {
                $updates[] = "$field = NULL";
            } else {
                $escapedValue = mysqli_real_escape_string($this->db, $value);
                $updates[] = "$field = '$escapedValue'";
            }
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE user_id = $userId";
            $this->db->query($sql);
        }
    }
    
    /**
     * Get valid roles
     */
    private function getValidRoles() {
        return [
            'super_admin', 'admin', 'owner', 'principal',
            'teacher', 'student', 'parent', 'staff',
            'librarian', 'bursar', 'accountant', 'transport',
            'forum_moderator'
        ];
    }
}
