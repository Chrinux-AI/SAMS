<?php
/**
 * SAMS Risk Register
 * 15 major risks with probability, impact, warning signals, and mitigation strategies
 */

class SAMS_RiskRegister {
    
    private $risks = [];
    
    public function __construct() {
        $this->initializeRisks();
    }
    
    /**
     * Initialize the 15 major risks
     */
    private function initializeRisks() {
        $this->risks = [
            [
                'id' => 'R001',
                'category' => 'Technical',
                'risk' => 'Database Schema Drift',
                'description' => 'Database structure diverges from code expectations causing runtime errors',
                'probability' => 'High',
                'impact' => 'High',
                'risk_score' => 'Critical',
                'warning_signals' => [
                    'Column not found errors in logs',
                    'Blank pages after deployment',
                    'Missing field errors in UI'
                ],
                'mitigation' => [
                    'Implement schema governance system',
                    'Use SchemaGovernance class for drift detection',
                    'Automated migration system',
                    'Regular schema audits'
                ],
                'owner' => 'Database Team',
                'review_date' => 'Weekly'
            ],
            [
                'id' => 'R002',
                'category' => 'Technical',
                'risk' => 'Duplicate and Legacy Files',
                'description' => 'Multiple versions of same functionality cause confusion and bugs',
                'probability' => 'High',
                'impact' => 'Medium',
                'risk_score' => 'High',
                'warning_signals' => [
                    'Multiple files with similar names',
                    'Inconsistent behavior across pages',
                    'Backup files in production'
                ],
                'mitigation' => [
                    'File structure cleanup script',
                    'Single source of truth enforcement',
                    'Version control discipline',
                    'Automated duplicate detection'
                ],
                'owner' => 'Development Team',
                'review_date' => 'Bi-weekly'
            ],
            [
                'id' => 'R003',
                'category' => 'Security',
                'risk' => 'Broken Authentication Flows',
                'description' => 'Login, logout, or session management failures',
                'probability' => 'Medium',
                'impact' => 'Critical',
                'risk_score' => 'Critical',
                'warning_signals' => [
                    'Users unable to login',
                    'Session timeout issues',
                    'Unauthorized access reports'
                ],
                'mitigation' => [
                    'Comprehensive auth testing',
                    'AuthService implementation',
                    'Session security hardening',
                    'Regular penetration testing'
                ],
                'owner' => 'Security Team',
                'review_date' => 'Daily'
            ],
            [
                'id' => 'R004',
                'category' => 'Operational',
                'risk' => 'SMTP/Email Delivery Failures',
                'description' => 'Activation emails and OTP not reaching users',
                'probability' => 'Medium',
                'impact' => 'High',
                'risk_score' => 'High',
                'warning_signals' => [
                    'Email queue backlog',
                    'Bounce rate increase',
                    'User complaints about not receiving emails'
                ],
                'mitigation' => [
                    'SMTP redundancy configuration',
                    'Email delivery monitoring',
                    'Fallback email provider',
                    'Queue-based email system'
                ],
                'owner' => 'DevOps Team',
                'review_date' => 'Daily'
            ],
            [
                'id' => 'R005',
                'category' => 'Security',
                'risk' => 'Tenant Data Leakage',
                'description' => 'Cross-tenant data access due to isolation failure',
                'probability' => 'Low',
                'impact' => 'Critical',
                'risk_score' => 'High',
                'warning_signals' => [
                    'Tenant sees wrong data',
                    'Database queries missing tenant_id',
                    'Cross-school data reports'
                ],
                'mitigation' => [
                    'TenantService isolation enforcement',
                    'All queries include tenant_id',
                    'Regular tenant isolation audits',
                    'Database row-level security'
                ],
                'owner' => 'Security Team',
                'review_date' => 'Weekly'
            ],
            [
                'id' => 'R006',
                'category' => 'Technical',
                'risk' => 'Blank Pages and Fatal Errors',
                'description' => 'Users see blank pages or PHP fatal errors',
                'probability' => 'High',
                'impact' => 'High',
                'risk_score' => 'Critical',
                'warning_signals' => [
                    'PHP error logs increasing',
                    'User reports of blank screens',
                    'Page load failures'
                ],
                'mitigation' => [
                    'ZeroFlawEnforcer system',
                    'PHP syntax checking',
                    'Graceful error handling',
                    'Production error display disabled'
                ],
                'owner' => 'Development Team',
                'review_date' => 'Daily'
            ],
            [
                'id' => 'R007',
                'category' => 'Operational',
                'risk' => 'AI Form Processing Failures',
                'description' => 'Google Form submissions not creating accounts',
                'probability' => 'Medium',
                'impact' => 'Medium',
                'risk_score' => 'Medium',
                'warning_signals' => [
                    'Webhook errors in logs',
                    'Form submissions not appearing',
                    'Validation failures'
                ],
                'mitigation' => [
                    'AIAccountCreator with validation',
                    'Webhook error handling',
                    'Manual fallback process',
                    'Monitoring dashboard'
                ],
                'owner' => 'AI Team',
                'review_date' => 'Daily'
            ],
            [
                'id' => 'R008',
                'category' => 'Security',
                'risk' => 'OTP Bypass or Brute Force',
                'description' => 'Attackers bypass OTP verification or guess codes',
                'probability' => 'Low',
                'impact' => 'Critical',
                'risk_score' => 'High',
                'warning_signals' => [
                    'Multiple failed OTP attempts',
                    'Rate limit triggers',
                    'Suspicious login patterns'
                ],
                'mitigation' => [
                    'OTPService rate limiting',
                    'Account lockout after attempts',
                    'IP-based throttling',
                    'Audit logging'
                ],
                'owner' => 'Security Team',
                'review_date' => 'Daily'
            ],
            [
                'id' => 'R009',
                'category' => 'Operational',
                'risk' => 'Bulk Import Data Corruption',
                'description' => 'CSV import creates incorrect or duplicate records',
                'probability' => 'Medium',
                'impact' => 'High',
                'risk_score' => 'High',
                'warning_signals' => [
                    'Duplicate user accounts',
                    'Invalid data in database',
                    'Import error reports'
                ],
                'mitigation' => [
                    'AdminWorkflow validation',
                    'Pre-import data preview',
                    'Duplicate detection',
                    'Rollback capability'
                ],
                'owner' => 'Data Team',
                'review_date' => 'Per-import'
            ],
            [
                'id' => 'R010',
                'category' => 'Technical',
                'risk' => 'Inconsistent UI/UX Across Modules',
                'description' => 'Different look and feel across role dashboards',
                'probability' => 'High',
                'impact' => 'Medium',
                'risk_score' => 'Medium',
                'warning_signals' => [
                    'User confusion about navigation',
                    'Different sidebar behaviors',
                    'Inconsistent color schemes'
                ],
                'mitigation' => [
                    'LayoutSystem standardization',
                    'Global CSS variables',
                    'Component library',
                    'UI audit checklist'
                ],
                'owner' => 'UX Team',
                'review_date' => 'Weekly'
            ],
            [
                'id' => 'R011',
                'category' => 'Security',
                'risk' => 'Insufficient Input Validation',
                'description' => 'SQL injection or XSS through forms',
                'probability' => 'Medium',
                'impact' => 'Critical',
                'risk_score' => 'High',
                'warning_signals' => [
                    'Suspicious input patterns',
                    'Database error logs',
                    'Script tags in data'
                ],
                'mitigation' => [
                    'Input sanitization on all forms',
                    'Prepared statements',
                    'CSRF tokens',
                    'Output encoding'
                ],
                'owner' => 'Security Team',
                'review_date' => 'Weekly'
            ],
            [
                'id' => 'R012',
                'category' => 'Operational',
                'risk' => 'Incomplete Role Permissions',
                'description' => 'Users access functions they should not',
                'probability' => 'Medium',
                'impact' => 'High',
                'risk_score' => 'High',
                'warning_signals' => [
                    'Access control violations',
                    'Role escalation reports',
                    'Unauthorized data access'
                ],
                'mitigation' => [
                    'Role-based access control (RBAC)',
                    'Regular permission audits',
                    'Automated role testing',
                    'Access logging'
                ],
                'owner' => 'Security Team',
                'review_date' => 'Weekly'
            ],
            [
                'id' => 'R013',
                'category' => 'Technical',
                'risk' => 'Missing Documentation',
                'description' => 'System changes not documented',
                'probability' => 'High',
                'impact' => 'Medium',
                'risk_score' => 'Medium',
                'warning_signals' => [
                    'Outdated README',
                    'Missing API docs',
                    'Undocumented features'
                ],
                'mitigation' => [
                    'ReadmeAutoDoc system',
                    'Documentation requirements in PRs',
                    'Auto-generated API docs',
                    'Regular doc reviews'
                ],
                'owner' => 'Documentation Team',
                'review_date' => 'Per-release'
            ],
            [
                'id' => 'R014',
                'category' => 'Performance',
                'risk' => 'Database Query Performance Degradation',
                'description' => 'Slow queries causing page load issues',
                'probability' => 'Medium',
                'impact' => 'Medium',
                'risk_score' => 'Medium',
                'warning_signals' => [
                    'Query execution time > 1s',
                    'Page load time > 3s',
                    'Database CPU spikes'
                ],
                'mitigation' => [
                    'Query optimization',
                    'Database indexing strategy',
                    'Query caching',
                    'Performance monitoring'
                ],
                'owner' => 'Database Team',
                'review_date' => 'Weekly'
            ],
            [
                'id' => 'R015',
                'category' => 'Business',
                'risk' => 'Admin Workflow Failures',
                'description' => 'Critical admin operations fail (add teacher, import students, create class)',
                'probability' => 'Medium',
                'impact' => 'Critical',
                'risk_score' => 'Critical',
                'warning_signals' => [
                    'Admin error reports',
                    'Failed operation logs',
                    'Incomplete user onboarding'
                ],
                'mitigation' => [
                    'AdminWorkflow zero-stress design',
                    'Comprehensive error handling',
                    'Transaction safety',
                    'Workflow validation testing'
                ],
                'owner' => 'Product Team',
                'review_date' => 'Daily'
            ]
        ];
    }
    
    /**
     * Get all risks
     */
    public function getAllRisks() {
        return $this->risks;
    }
    
    /**
     * Get risks by category
     */
    public function getRisksByCategory($category) {
        return array_filter($this->risks, function($risk) use ($category) {
            return $risk['category'] === $category;
        });
    }
    
    /**
     * Get critical risks
     */
    public function getCriticalRisks() {
        return array_filter($this->risks, function($risk) {
            return $risk['risk_score'] === 'Critical';
        });
    }
    
    /**
     * Get high priority risks
     */
    public function getHighPriorityRisks() {
        return array_filter($this->risks, function($risk) {
            return in_array($risk['risk_score'], ['Critical', 'High']);
        });
    }
    
    /**
     * Generate risk matrix
     */
    public function generateRiskMatrix() {
        $matrix = [
            'Critical' => ['High' => [], 'Medium' => [], 'Low' => []],
            'High' => ['High' => [], 'Medium' => [], 'Low' => []],
            'Medium' => ['High' => [], 'Medium' => [], 'Low' => []],
            'Low' => ['High' => [], 'Medium' => [], 'Low' => []]
        ];
        
        foreach ($this->risks as $risk) {
            $impact = $risk['impact'];
            $probability = $risk['probability'];
            $matrix[$impact][$probability][] = $risk;
        }
        
        return $matrix;
    }
    
    /**
     * Generate risk report
     */
    public function generateReport() {
        $report = "# SAMS Risk Register Report\n\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Executive Summary
        $critical = count($this->getCriticalRisks());
        $high = count($this->getHighPriorityRisks()) - $critical;
        
        $report .= "## Executive Summary\n\n";
        $report .= "- **Critical Risks:** $critical\n";
        $report .= "- **High Priority Risks:** $high\n";
        $report .= "- **Total Risks:** " . count($this->risks) . "\n\n";
        
        // Risk Matrix
        $report .= "## Risk Matrix\n\n";
        $report .= "| Impact \ Probability | High | Medium | Low |\n";
        $report .= "|---------------------|------|--------|-----|\n";
        
        $matrix = $this->generateRiskMatrix();
        foreach ($matrix as $impact => $probs) {
            $report .= "| $impact | " . count($probs['High']) . " | " . 
                      count($probs['Medium']) . " | " . count($probs['Low']) . " |\n";
        }
        
        $report .= "\n";
        
        // Detailed Risk List
        $report .= "## Risk Details\n\n";
        
        foreach ($this->risks as $risk) {
            $report .= "### {$risk['id']}: {$risk['risk']}\n\n";
            $report .= "**Category:** {$risk['category']}  \n";
            $report .= "**Risk Score:** {$risk['risk_score']}  \n";
            $report .= "**Probability:** {$risk['probability']}  \n";
            $report .= "**Impact:** {$risk['impact']}  \n";
            $report .= "**Owner:** {$risk['owner']}  \n\n";
            
            $report .= "**Description:**  \n";
            $report .= $risk['description'] . "\n\n";
            
            $report .= "**Warning Signals:**  \n";
            foreach ($risk['warning_signals'] as $signal) {
                $report .= "- $signal\n";
            }
            $report .= "\n";
            
            $report .= "**Mitigation Strategies:**  \n";
            foreach ($risk['mitigation'] as $strategy) {
                $report .= "- $strategy\n";
            }
            $report .= "\n";
            
            $report .= "---\n\n";
        }
        
        return $report;
    }
    
    /**
     * Save report to file
     */
    public function saveReport($path = null) {
        if (!$path) {
            $path = __DIR__ . '/../../docs/RISK_REGISTER.md';
        }
        
        $report = $this->generateReport();
        file_put_contents($path, $report);
        
        return "Risk register saved to: $path";
    }
}

// Generate report when called directly
if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) === 'RiskRegister.php') {
    $register = new SAMS_RiskRegister();
    echo $register->saveReport() . "\n";
}
