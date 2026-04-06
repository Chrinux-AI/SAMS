<?php
/**
 * SAMS Definition of Done
 * Measurable completion criteria for the entire project
 */

class SAMS_DefinitionOfDone {
    
    private $criteria = [];
    
    public function __construct() {
        $this->initializeCriteria();
    }
    
    /**
     * Initialize all completion criteria
     */
    private function initializeCriteria() {
        $this->criteria = [
            'core_functionality' => [
                'category' => 'Core Functionality',
                'items' => [
                    [
                        'id' => 'CF001',
                        'criterion' => 'Admin can add teachers easily',
                        'measurement' => 'Teacher creation workflow completes in < 30 seconds with zero errors',
                        'test_method' => 'Automated workflow test with 10 teacher creations',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'CF002',
                        'criterion' => 'Students can be bulk imported without errors',
                        'measurement' => '100+ students imported in < 2 minutes with < 1% error rate',
                        'test_method' => 'CSV import test with validation report',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'CF003',
                        'criterion' => 'Classes can be created and managed easily',
                        'measurement' => 'Class creation workflow completes in < 1 minute',
                        'test_method' => 'End-to-end class management test',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'CF004',
                        'criterion' => 'AI form onboarding works automatically',
                        'measurement' => 'Form submission processed in < 10 seconds with 99%+ accuracy',
                        'test_method' => 'Webhook simulation with 50 form submissions',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'CF005',
                        'criterion' => 'Users activate accounts via OTP',
                        'measurement' => 'OTP delivered in < 30 seconds, activation rate > 95%',
                        'test_method' => 'OTP delivery test with 20 users',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'CF006',
                        'criterion' => 'Users create passwords securely',
                        'measurement' => 'Password strength validation enforces all rules',
                        'test_method' => 'Password validation test',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ]
                ]
            ],
            
            'stability' => [
                'category' => 'Stability & Reliability',
                'items' => [
                    [
                        'id' => 'SR001',
                        'criterion' => 'No blank pages exist',
                        'measurement' => 'Zero HTTP 500 errors, all pages return 200',
                        'test_method' => 'Automated crawler test of all PHP files',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'SR002',
                        'criterion' => 'No fatal errors appear',
                        'measurement' => 'Zero PHP fatal errors in error logs',
                        'test_method' => 'Review error logs for 7 days',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'SR003',
                        'criterion' => 'All navigation links work',
                        'measurement' => '100% of menu links resolve to valid pages',
                        'test_method' => 'ZeroFlawEnforcer link validation',
                        'priority' => 'High',
                        'status' => 'pending'
                    ]
                ]
            ],
            
            'ui_ux' => [
                'category' => 'UI/UX',
                'items' => [
                    [
                        'id' => 'UI001',
                        'criterion' => 'UI is organized and consistent',
                        'measurement' => 'All pages use LayoutSystem, CSS variables consistent',
                        'test_method' => 'Visual regression testing',
                        'priority' => 'High',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'UI002',
                        'criterion' => 'All roles have working dashboards',
                        'measurement' => '9 roles load dashboards in < 2 seconds',
                        'test_method' => 'Role-based page load testing',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ]
                ]
            ],
            
            'security' => [
                'category' => 'Security',
                'items' => [
                    [
                        'id' => 'SEC001',
                        'criterion' => 'Authentication secure',
                        'measurement' => 'Passwords hashed (bcrypt), sessions secure',
                        'test_method' => 'Security audit and code review',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ],
                    [
                        'id' => 'SEC002',
                        'criterion' => 'OTP system secure',
                        'measurement' => 'Rate limiting active, 5 attempt limit',
                        'test_method' => 'OTP penetration testing',
                        'priority' => 'Critical',
                        'status' => 'pending'
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Get all criteria
     */
    public function getAllCriteria() {
        return $this->criteria;
    }
    
    /**
     * Get critical criteria
     */
    public function getCriticalCriteria() {
        $critical = [];
        foreach ($this->criteria as $category => $data) {
            foreach ($data['items'] as $item) {
                if ($item['priority'] === 'Critical') {
                    $critical[] = $item;
                }
            }
        }
        return $critical;
    }
    
    /**
     * Calculate completion percentage
     */
    public function getCompletionPercentage() {
        $total = 0;
        $completed = 0;
        
        foreach ($this->criteria as $category => $data) {
            foreach ($data['items'] as $item) {
                $total++;
                if ($item['status'] === 'completed') {
                    $completed++;
                }
            }
        }
        
        return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
    }
    
    /**
     * Generate completion report
     */
    public function generateReport() {
        $report = "# SAMS Definition of Done\n\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "**Overall Completion: " . $this->getCompletionPercentage() . "%**\n\n";
        
        foreach ($this->criteria as $category => $data) {
            $completed = count(array_filter($data['items'], function($item) {
                return $item['status'] === 'completed';
            }));
            $total = count($data['items']);
            $percentage = round(($completed / $total) * 100);
            
            $report .= "## {$data['category']} ({$completed}/{$total} - {$percentage}%)\n\n";
            
            foreach ($data['items'] as $item) {
                $statusEmoji = $item['status'] === 'completed' ? '✅' : '⏳';
                $priority = $item['priority'] === 'Critical' ? '🔴' : '🟡';
                
                $report .= "### {$item['id']}: {$item['criterion']} {$statusEmoji}\n\n";
                $report .= "**Priority:** {$priority} {$item['priority']}  \n";
                $report .= "**Measurement:** {$item['measurement']}\n\n";
                $report .= "---\n\n";
            }
        }
        
        return $report;
    }
    
    /**
     * Save report to file
     */
    public function saveReport($path = null) {
        if (!$path) {
            $path = __DIR__ . '/../../docs/DEFINITION_OF_DONE.md';
        }
        
        $report = $this->generateReport();
        file_put_contents($path, $report);
        
        return "Definition of Done report saved to: $path";
    }
}

// Generate report when called directly
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'DefinitionOfDone.php') {
    $dod = new SAMS_DefinitionOfDone();
    echo $dod->saveReport() . "\n";
}
