<?php
/**
 * 90-Day Delivery Tracking System
 * Implementation roadmap tracker and milestone management
 */

class SAMS_DeliveryTracker {
    private $db;
    private $phases = [];
    private $currentPhase = 0;
    
    public function __construct() {
        $this->db = db();
        $this->initializePhases();
        $this->ensureTrackingTable();
    }
    
    /**
     * Initialize 13-week implementation phases
     */
    private function initializePhases() {
        $this->phases = [
            [
                'week' => 1,
                'name' => 'Foundation & Audit',
                'objective' => 'Establish baseline and safety nets',
                'tasks' => [
                    'Complete system audit and error mapping',
                    'Establish development safety protocols',
                    'Create source-of-truth documentation',
                    'Set up monitoring and logging',
                    'Run PHP syntax check on all files',
                    'Document current schema state'
                ],
                'deliverables' => ['Error matrix', 'Schema audit report', 'Safety infrastructure'],
                'acceptance_criteria' => [
                    'Complete error matrix with severity levels',
                    'Source-of-truth schema established',
                    'Development environment with safety nets',
                    'Backup and rollback procedures tested'
                ]
            ],
            [
                'week' => 2,
                'name' => 'Critical Stabilization - Part 1',
                'objective' => 'Eliminate showstopper errors',
                'tasks' => [
                    'Fix all fatal errors and blank pages',
                    'Stabilize authentication system',
                    'Ensure basic navigation works',
                    'Fix database connectivity issues'
                ],
                'deliverables' => ['Stable entry points', 'Working auth system'],
                'acceptance_criteria' => [
                    'Zero fatal errors in critical files',
                    'All users can login',
                    'Navigation works consistently',
                    'Database operations complete without errors'
                ]
            ],
            [
                'week' => 3,
                'name' => 'Critical Stabilization - Part 2',
                'objective' => 'Complete error resolution',
                'tasks' => [
                    'Resolve remaining syntax errors',
                    'Fix all role dashboard issues',
                    'Verify all basic workflows',
                    'Document all fixes made'
                ],
                'deliverables' => ['Error-free core system'],
                'acceptance_criteria' => [
                    'Zero fatal errors across all modules',
                    'All role dashboards load correctly',
                    'Basic workflows functional'
                ]
            ],
            [
                'week' => 4,
                'name' => 'Teacher Management',
                'objective' => 'Implement teacher workflows',
                'tasks' => [
                    'Fix teacher creation form',
                    'Implement CSV bulk import for teachers',
                    'Add validation and error handling',
                    'Create teacher profile management'
                ],
                'deliverables' => ['Teacher management system'],
                'acceptance_criteria' => [
                    'Admin can create teachers individually',
                    'Bulk import works with validation',
                    'All workflows include proper error handling'
                ]
            ],
            [
                'week' => 5,
                'name' => 'Student Management',
                'objective' => 'Implement student workflows',
                'tasks' => [
                    'Fix student registration form',
                    'Implement CSV bulk import for students',
                    'Add grade level assignment',
                    'Create student profile management'
                ],
                'deliverables' => ['Student management system'],
                'acceptance_criteria' => [
                    'Admin can register students',
                    'Bulk import with parent linking',
                    'Grade management functional'
                ]
            ],
            [
                'week' => 6,
                'name' => 'Class Management',
                'objective' => 'Implement class workflows',
                'tasks' => [
                    'Fix class creation workflow',
                    'Add teacher assignment',
                    'Implement student enrollment',
                    'Create class schedule management'
                ],
                'deliverables' => ['Class management system'],
                'acceptance_criteria' => [
                    'Admin can create classes and assign teachers',
                    'Student enrollment functional',
                    'All workflows complete'
                ]
            ],
            [
                'week' => 7,
                'name' => 'Security Implementation - Part 1',
                'objective' => 'Implement OTP and security',
                'tasks' => [
                    'Implement secure OTP generation',
                    'Add rate limiting and cooldowns',
                    'Create OTP verification flow',
                    'Add brute force protection'
                ],
                'deliverables' => ['OTP system', 'Rate limiting'],
                'acceptance_criteria' => [
                    'OTP system works with proper rate limiting',
                    'Brute force protection active'
                ]
            ],
            [
                'week' => 8,
                'name' => 'AI User Creation',
                'objective' => 'Implement AI onboarding',
                'tasks' => [
                    'Fix Google Forms parsing',
                    'Implement secure account creation',
                    'Add email invitation system',
                    'Test onboarding workflow'
                ],
                'deliverables' => ['AI user creation pipeline'],
                'acceptance_criteria' => [
                    'Form parsing works (JSON/CSV/key-value)',
                    'Bulk creation with OTP setup',
                    'Email invitations sent correctly'
                ]
            ],
            [
                'week' => 9,
                'name' => 'UI Standardization',
                'objective' => 'Create consistent UI',
                'tasks' => [
                    'Create master theme CSS',
                    'Implement CSS variables',
                    'Add theme switching',
                    'Fix icon and favicon consistency'
                ],
                'deliverables' => ['Unified theme system'],
                'acceptance_criteria' => [
                    'Consistent theme across all pages',
                    'Navigation works identically across roles',
                    'Mobile-responsive design'
                ]
            ],
            [
                'week' => 10,
                'name' => 'Multi-Tenant Core',
                'objective' => 'Implement tenant management',
                'tasks' => [
                    'Complete tenant creation workflow',
                    'Implement subdomain routing',
                    'Add tenant configuration',
                    'Create tenant switching'
                ],
                'deliverables' => ['Multi-tenant infrastructure'],
                'acceptance_criteria' => [
                    'Complete tenant isolation',
                    'Super admin can manage tenants',
                    'Tenant-specific configuration works'
                ]
            ],
            [
                'week' => 11,
                'name' => 'Multi-Tenant Advanced',
                'objective' => 'Complete multi-tenant features',
                'tasks' => [
                    'Optimize performance',
                    'Add monitoring',
                    'Complete configuration',
                    'Test scalability'
                ],
                'deliverables' => ['Production-ready multi-tenancy'],
                'acceptance_criteria' => [
                    'Performance meets requirements',
                    'Monitoring in place',
                    'Scalability tested'
                ]
            ],
            [
                'week' => 12,
                'name' => 'Quality Assurance',
                'objective' => 'Comprehensive testing',
                'tasks' => [
                    'Create automated test suite',
                    'Implement integration tests',
                    'Add security testing',
                    'Perform load testing'
                ],
                'deliverables' => ['Test coverage >80%'],
                'acceptance_criteria' => [
                    'All tests pass',
                    'Security validated',
                    'Performance optimized'
                ]
            ],
            [
                'week' => 13,
                'name' => 'Production Readiness',
                'objective' => 'Prepare for launch',
                'tasks' => [
                    'Final testing',
                    'Deployment preparation',
                    'Backup procedures',
                    'Go-live preparation'
                ],
                'deliverables' => ['Production-ready system'],
                'acceptance_criteria' => [
                    'Zero critical issues',
                    'Documentation complete',
                    'Production environment ready'
                ]
            ]
        ];
    }
    
    /**
     * Ensure tracking table exists
     */
    private function ensureTrackingTable() {
        $sql = "CREATE TABLE IF NOT EXISTS implementation_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            week INT NOT NULL,
            phase_name VARCHAR(100),
            task_name VARCHAR(255),
            status ENUM('pending', 'in_progress', 'completed', 'blocked') DEFAULT 'pending',
            completed_at TIMESTAMP NULL,
            notes TEXT,
            completed_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->db->query($sql);
    }
    
    /**
     * Get current phase
     */
    public function getCurrentPhase() {
        $result = $this->db->query("SELECT MAX(week) as current_week FROM implementation_tracking WHERE status = 'completed'");
        
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $currentWeek = (int)$row['current_week'];
            
            foreach ($this->phases as $index => $phase) {
                if ($phase['week'] === $currentWeek + 1) {
                    return ['index' => $index, 'phase' => $phase];
                }
            }
        }
        
        return ['index' => 0, 'phase' => $this->phases[0]];
    }
    
    /**
     * Get phase by week
     */
    public function getPhase($week) {
        $week = (int)$week;
        
        foreach ($this->phases as $phase) {
            if ($phase['week'] === $week) {
                return $phase;
            }
        }
        
        return null;
    }
    
    /**
     * Get all phases
     */
    public function getAllPhases() {
        return $this->phases;
    }
    
    /**
     * Update task status
     */
    public function updateTaskStatus($week, $task, $status, $notes = '') {
        $week = (int)$week;
        $task = mysqli_real_escape_string($this->db, $task);
        $status = mysqli_real_escape_string($this->db, $status);
        $notes = mysqli_real_escape_string($this->db, $notes);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        // Check if record exists
        $result = $this->db->query("SELECT id FROM implementation_tracking 
            WHERE week = $week AND task_name = '$task'");
        
        if ($result && mysqli_num_rows($result) > 0) {
            // Update
            $row = mysqli_fetch_assoc($result);
            $id = (int)$row['id'];
            
            $completedAt = ($status === 'completed') ? 'NOW()' : 'NULL';
            
            $sql = "UPDATE implementation_tracking 
                    SET status = '$status', 
                        notes = '$notes',
                        completed_at = $completedAt,
                        completed_by = $userId
                    WHERE id = $id";
        } else {
            // Insert
            $completedAt = ($status === 'completed') ? 'NOW()' : 'NULL';
            
            $sql = "INSERT INTO implementation_tracking 
                    (week, task_name, status, notes, completed_at, completed_by) 
                    VALUES ($week, '$task', '$status', '$notes', $completedAt, $userId)";
        }
        
        return $this->db->query($sql);
    }
    
    /**
     * Get progress summary
     */
    public function getProgress() {
        $totalTasks = 0;
        $completedTasks = 0;
        
        foreach ($this->phases as $phase) {
            $totalTasks += count($phase['tasks']);
        }
        
        $result = $this->db->query("SELECT COUNT(*) as completed FROM implementation_tracking WHERE status = 'completed'");
        
        if ($result) {
            $completedTasks = (int)mysqli_fetch_assoc($result)['completed'];
        }
        
        $percentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        
        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'percentage' => $percentage,
            'current_phase' => $this->getCurrentPhase(),
            'phases' => $this->getPhaseProgress()
        ];
    }
    
    /**
     * Get progress for each phase
     */
    private function getPhaseProgress() {
        $phaseProgress = [];
        
        foreach ($this->phases as $phase) {
            $week = $phase['week'];
            $total = count($phase['tasks']);
            
            $result = $this->db->query("SELECT COUNT(*) as completed FROM implementation_tracking 
                WHERE week = $week AND status = 'completed'");
            
            $completed = $result ? (int)mysqli_fetch_assoc($result)['completed'] : 0;
            
            $phaseProgress[] = [
                'week' => $week,
                'name' => $phase['name'],
                'total' => $total,
                'completed' => $completed,
                'percentage' => $total > 0 ? round(($completed / $total) * 100, 1) : 0
            ];
        }
        
        return $phaseProgress;
    }
    
    /**
     * Generate progress report
     */
    public function generateReport() {
        $progress = $this->getProgress();
        
        $report = "# SAMS 90-Day Implementation Report\n\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "## Overall Progress\n\n";
        $report .= "- **Total Tasks:** {$progress['total_tasks']}\n";
        $report .= "- **Completed:** {$progress['completed_tasks']}\n";
        $report .= "- **Progress:** {$progress['percentage']}%\n\n";
        $report .= "## Phase Progress\n\n";
        
        foreach ($progress['phases'] as $phase) {
            $status = $phase['percentage'] >= 100 ? '✅' : ($phase['percentage'] > 0 ? '🔄' : '⏳');
            $report .= "### Week {$phase['week']}: {$phase['name']} {$status}\n";
            $report .= "- Progress: {$phase['completed']}/{$phase['total']} ({$phase['percentage']}%)\n\n";
        }
        
        $report .= "## Current Phase\n\n";
        $current = $progress['current_phase']['phase'];
        $report .= "**Week {$current['week']}: {$current['name']}\n\n";
        $report .= "**Objective:** {$current['objective']}\n\n";
        $report .= "### Tasks:\n";
        
        foreach ($current['tasks'] as $task) {
            $report .= "- [ ] $task\n";
        }
        
        $report .= "\n### Acceptance Criteria:\n";
        foreach ($current['acceptance_criteria'] as $criterion) {
            $report .= "- [ ] $criterion\n";
        }
        
        return $report;
    }
    
    /**
     * Get immediate next actions
     */
    public function getNextActions($count = 10) {
        $current = $this->getCurrentPhase();
        $phase = $current['phase'];
        
        $actions = [];
        
        // Check which tasks from current phase are not completed
        $week = $phase['week'];
        $result = $this->db->query("SELECT task_name FROM implementation_tracking 
            WHERE week = $week AND status = 'completed'");
        
        $completedTasks = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $completedTasks[] = $row['task_name'];
            }
        }
        
        // Get pending tasks
        foreach ($phase['tasks'] as $task) {
            if (!in_array($task, $completedTasks)) {
                $actions[] = [
                    'week' => $week,
                    'phase' => $phase['name'],
                    'task' => $task,
                    'priority' => 'high'
                ];
                
                if (count($actions) >= $count) {
                    break;
                }
            }
        }
        
        return $actions;
    }
}

/**
 * CLI usage
 */
if (php_sapi_name() === 'cli') {
    $tracker = new SAMS_DeliveryTracker();
    
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'report':
                echo $tracker->generateReport();
                break;
            case 'progress':
                $progress = $tracker->getProgress();
                echo "Progress: {$progress['percentage']}% ({$progress['completed_tasks']}/{$progress['total_tasks']})\n";
                break;
            case 'next':
                $actions = $tracker->getNextActions();
                echo "Next Actions:\n";
                foreach ($actions as $i => $action) {
                    echo ($i + 1) . ". [Week {$action['week']}] {$action['task']}\n";
                }
                break;
            default:
                echo "Usage: php DeliveryTracker.php [report|progress|next]\n";
        }
    } else {
        echo $tracker->generateReport();
    }
}
