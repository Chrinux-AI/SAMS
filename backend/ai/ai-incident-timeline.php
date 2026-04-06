<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

class AIIncidentTimeline
{
    private $tenantId;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
    }
    
    /**
     * Record critical event
     */
    public function recordEvent($eventType, $description, $severity = 'medium', $userId = null, $metadata = [])
    {
        try {
            $eventId = db()->insert('ai_incident_timeline', [
                'tenant_id' => $this->tenantId,
                'event_type' => $eventType,
                'description' => $description,
                'severity' => $severity,
                'user_id' => $userId ?? ($_SESSION['user_id'] ?? 0),
                'metadata' => json_encode($metadata),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Trigger AI analysis if critical
            if ($severity === 'critical') {
                $this->triggerAIAnalysis($eventId);
            }
            
            return $eventId;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::recordEvent error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get timeline events
     */
    public function getTimeline($filters = [], $limit = 100, $offset = 0)
    {
        try {
            $where = ['tenant_id = ?'];
            $params = [$this->tenantId];
            
            if (!empty($filters['event_type'])) {
                $where[] = 'event_type = ?';
                $params[] = $filters['event_type'];
            }
            
            if (!empty($filters['severity'])) {
                $where[] = 'severity = ?';
                $params[] = $filters['severity'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = 'created_at >= ?';
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = 'created_at <= ?';
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $sql = "
                SELECT it.*, u.first_name, u.last_name, u.role,
                       CASE 
                           WHEN it.severity = 'critical' THEN 1
                           WHEN it.severity = 'high' THEN 2
                           WHEN it.severity = 'medium' THEN 3
                           ELSE 4
                       END as severity_order
                FROM ai_incident_timeline it
                LEFT JOIN users u ON it.user_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY severity_order ASC, created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            return db()->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getTimeline error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get incident statistics
     */
    public function getIncidentStatistics($timeframe = '30 days')
    {
        try {
            $stats = db()->fetchOne("
                SELECT 
                    COUNT(*) as total_incidents,
                    COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_incidents,
                    COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_incidents,
                    COUNT(CASE WHEN severity = 'medium' THEN 1 END) as medium_incidents,
                    COUNT(CASE WHEN severity = 'low' THEN 1 END) as low_incidents,
                    COUNT(DISTINCT event_type) as unique_event_types,
                    COUNT(DISTINCT user_id) as affected_users
                FROM ai_incident_timeline 
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$timeframe})
            ", [$this->tenantId]);
            
            // Get incident trends
            $trends = db()->fetchAll("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as incident_count,
                    COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_count
                FROM ai_incident_timeline 
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$timeframe})
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ", [$this->tenantId]);
            
            // Get top event types
            $topEvents = db()->fetchAll("
                SELECT event_type, COUNT(*) as count
                FROM ai_incident_timeline 
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL {$timeframe})
                GROUP BY event_type
                ORDER BY count DESC
                LIMIT 10
            ", [$this->tenantId]);
            
            return [
                'overview' => $stats,
                'trends' => $trends,
                'top_events' => $topEvents,
                'timeframe' => $timeframe
            ];
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getIncidentStatistics error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get incident details
     */
    public function getIncidentDetails($incidentId)
    {
        try {
            $incident = db()->fetchOne("
                SELECT it.*, u.first_name, u.last_name, u.role
                FROM ai_incident_timeline it
                LEFT JOIN users u ON it.user_id = u.id
                WHERE it.id = ? AND it.tenant_id = ?
            ", [$incidentId, $this->tenantId]);
            
            if ($incident) {
                $incident['metadata'] = json_decode($incident['metadata'], true);
                
                // Get related incidents
                $related = db()->fetchAll("
                    SELECT * FROM ai_incident_timeline 
                    WHERE tenant_id = ? AND event_type = ? 
                    AND id != ? 
                    AND created_at BETWEEN DATE_SUB(?, INTERVAL 1 HOUR) AND DATE_ADD(?, INTERVAL 1 HOUR)
                    ORDER BY created_at DESC
                    LIMIT 5
                ", [$this->tenantId, $incident['event_type'], $incidentId, $incident['created_at'], $incident['created_at']]);
                
                $incident['related_incidents'] = $related;
            }
            
            return $incident;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getIncidentDetails error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Trigger AI analysis for incident
     */
    private function triggerAIAnalysis($incidentId)
    {
        try {
            // Get incident details
            $incident = $this->getIncidentDetails($incidentId);
            
            if (!$incident) {
                return false;
            }
            
            // Perform AI analysis
            $analysis = $this->analyzeIncident($incident);
            
            // Store analysis results
            db()->insert('ai_incident_analysis', [
                'tenant_id' => $this->tenantId,
                'incident_id' => $incidentId,
                'analysis_type' => 'automatic',
                'analysis_data' => json_encode($analysis),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Generate recommendations
            $recommendations = $this->generateRecommendations($incident, $analysis);
            
            // Store recommendations
            db()->insert('ai_incident_recommendations', [
                'tenant_id' => $this->tenantId,
                'incident_id' => $incidentId,
                'recommendations' => json_encode($recommendations),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::triggerAIAnalysis error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Analyze incident using AI
     */
    private function analyzeIncident($incident)
    {
        try {
            $analysis = [
                'risk_level' => $this->calculateRiskLevel($incident),
                'impact_assessment' => $this->assessImpact($incident),
                'pattern_analysis' => $this->analyzePatterns($incident),
                'root_cause_analysis' => $this->analyzeRootCause($incident),
                'severity_confirmation' => $this->confirmSeverity($incident),
                'related_events' => $this->findRelatedEvents($incident),
                'predicted_outcomes' => $this->predictOutcomes($incident)
            ];
            
            return $analysis;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::analyzeIncident error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate risk level
     */
    private function calculateRiskLevel($incident)
    {
        $riskScore = 0;
        
        // Base risk from severity
        switch ($incident['severity']) {
            case 'critical':
                $riskScore += 80;
                break;
            case 'high':
                $riskScore += 60;
                break;
            case 'medium':
                $riskScore += 40;
                break;
            case 'low':
                $riskScore += 20;
                break;
        }
        
        // Risk from event type
        $highRiskEvents = ['security_breach', 'data_loss', 'system_failure', 'unauthorized_access'];
        if (in_array($incident['event_type'], $highRiskEvents)) {
            $riskScore += 30;
        }
        
        // Risk from frequency
        $recentSimilar = $this->getRecentSimilarIncidents($incident['event_type']);
        $riskScore += min($recentSimilar * 10, 30);
        
        return [
            'score' => min($riskScore, 100),
            'level' => $riskScore >= 70 ? 'high' : ($riskScore >= 40 ? 'medium' : 'low'),
            'factors' => [
                'severity' => $incident['severity'],
                'event_type_risk' => in_array($incident['event_type'], $highRiskEvents),
                'frequency' => $recentSimilar
            ]
        ];
    }
    
    /**
     * Assess impact
     */
    private function assessImpact($incident)
    {
        $impact = [
            'users_affected' => 0,
            'systems_affected' => [],
            'data_integrity' => 'unknown',
            'operational_impact' => 'unknown'
        ];
        
        // Analyze based on event type
        switch ($incident['event_type']) {
            case 'security_breach':
                $impact['users_affected'] = $this->estimateAffectedUsers('security');
                $impact['systems_affected'] = ['authentication', 'authorization', 'data_access'];
                $impact['data_integrity'] = 'compromised';
                $impact['operational_impact'] = 'high';
                break;
                
            case 'system_failure':
                $impact['users_affected'] = $this->estimateAffectedUsers('system');
                $impact['systems_affected'] = ['core_systems'];
                $impact['data_integrity'] = 'unknown';
                $impact['operational_impact'] = 'critical';
                break;
                
            case 'data_loss':
                $impact['users_affected'] = $this->estimateAffectedUsers('data');
                $impact['systems_affected'] = ['data_storage'];
                $impact['data_integrity'] = 'lost';
                $impact['operational_impact'] = 'medium';
                break;
        }
        
        return $impact;
    }
    
    /**
     * Analyze patterns
     */
    private function analyzePatterns($incident)
    {
        $patterns = [];
        
        // Check for time-based patterns
        $timePatterns = $this->analyzeTimePatterns($incident['event_type']);
        if (!empty($timePatterns)) {
            $patterns['time_based'] = $timePatterns;
        }
        
        // Check for user-based patterns
        $userPatterns = $this->analyzeUserPatterns($incident);
        if (!empty($userPatterns)) {
            $patterns['user_based'] = $userPatterns;
        }
        
        // Check for system-based patterns
        $systemPatterns = $this->analyzeSystemPatterns($incident['event_type']);
        if (!empty($systemPatterns)) {
            $patterns['system_based'] = $systemPatterns;
        }
        
        return $patterns;
    }
    
    /**
     * Analyze time patterns
     */
    private function analyzeTimePatterns($eventType)
    {
        try {
            $patterns = db()->fetchAll("
                SELECT 
                    HOUR(created_at) as hour,
                    DAYOFWEEK(created_at) as day_of_week,
                    COUNT(*) as count
                FROM ai_incident_timeline 
                WHERE tenant_id = ? AND event_type = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY HOUR(created_at), DAYOFWEEK(created_at)
                HAVING count > 1
                ORDER BY count DESC
            ", [$this->tenantId, $eventType]);
            
            return $patterns;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::analyzeTimePatterns error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Analyze user patterns
     */
    private function analyzeUserPatterns($incident)
    {
        try {
            $patterns = db()->fetchAll("
                SELECT 
                    user_id,
                    u.first_name,
                    u.last_name,
                    u.role,
                    COUNT(*) as incident_count,
                    GROUP_CONCAT(DISTINCT event_type SEPARATOR ', ') as event_types
                FROM ai_incident_timeline it
                JOIN users u ON it.user_id = u.id
                WHERE it.tenant_id = ? AND it.user_id > 0
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY user_id, u.first_name, u.last_name, u.role
                HAVING incident_count > 1
                ORDER BY incident_count DESC
            ", [$this->tenantId]);
            
            return $patterns;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::analyzeUserPatterns error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Analyze system patterns
     */
    private function analyzeSystemPatterns($eventType)
    {
        // Implementation would depend on specific system monitoring data
        return [];
    }
    
    /**
     * Analyze root cause
     */
    private function analyzeRootCause($incident)
    {
        $causes = [];
        
        // Analyze based on event type and metadata
        switch ($incident['event_type']) {
            case 'security_breach':
                $causes[] = 'Potential weak authentication mechanisms';
                $causes[] = 'Possible unauthorized access attempts';
                $causes[] = 'Security configuration issues';
                break;
                
            case 'system_failure':
                $causes[] = 'Hardware or infrastructure issues';
                $causes[] = 'Software bugs or conflicts';
                $causes[] = 'Resource exhaustion';
                break;
                
            case 'data_loss':
                $causes[] = 'Backup system failures';
                $causes[] = 'Storage media issues';
                $causes[] = 'Human error or malicious activity';
                break;
        }
        
        return $causes;
    }
    
    /**
     * Confirm severity
     */
    private function confirmSeverity($incident)
    {
        $confirmedSeverity = $incident['severity'];
        
        // Adjust severity based on analysis
        $riskLevel = $this->calculateRiskLevel($incident);
        
        if ($riskLevel['score'] >= 80 && $incident['severity'] !== 'critical') {
            $confirmedSeverity = 'critical';
        } elseif ($riskLevel['score'] >= 60 && $incident['severity'] === 'low') {
            $confirmedSeverity = 'medium';
        }
        
        return [
            'original' => $incident['severity'],
            'confirmed' => $confirmedSeverity,
            'reasoning' => 'Adjusted based on risk assessment and pattern analysis'
        ];
    }
    
    /**
     * Find related events
     */
    private function findRelatedEvents($incident)
    {
        try {
            return db()->fetchAll("
                SELECT * FROM ai_incident_timeline 
                WHERE tenant_id = ? 
                AND id != ? 
                AND (
                    event_type = ? 
                    OR (created_at BETWEEN DATE_SUB(?, INTERVAL 24 HOUR) AND DATE_ADD(?, INTERVAL 24 HOUR))
                )
                ORDER BY created_at DESC
                LIMIT 10
            ", [$this->tenantId, $incident['id'], $incident['event_type'], $incident['created_at'], $incident['created_at']]);
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::findRelatedEvents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict outcomes
     */
    private function predictOutcomes($incident)
    {
        $outcomes = [];
        
        // Predict based on historical data
        $historicalData = $this->getHistoricalOutcomes($incident['event_type']);
        
        if (!empty($historicalData)) {
            $outcomes['historical_patterns'] = $historicalData;
        }
        
        // Predict based on current conditions
        $outcomes['current_conditions'] = [
            'likelihood_of_recurrence' => $this->calculateRecurrenceLikelihood($incident),
            'estimated_resolution_time' => $this->estimateResolutionTime($incident),
            'potential_impact' => $this->assessPotentialImpact($incident)
        ];
        
        return $outcomes;
    }
    
    /**
     * Generate recommendations
     */
    private function generateRecommendations($incident, $analysis)
    {
        $recommendations = [];
        
        // Generate based on severity
        switch ($incident['severity']) {
            case 'critical':
                $recommendations[] = 'Immediate investigation required';
                $recommendations[] = 'Notify all stakeholders';
                $recommendations[] = 'Implement emergency response plan';
                break;
                
            case 'high':
                $recommendations[] = 'Investigate within 1 hour';
                $recommendations[] = 'Review related systems';
                $recommendations[] = 'Document all actions taken';
                break;
                
            case 'medium':
                $recommendations[] = 'Investigate within 4 hours';
                $recommendations[] = 'Monitor for related incidents';
                $recommendations[] = 'Update documentation';
                break;
                
            case 'low':
                $recommendations[] = 'Investigate within 24 hours';
                $recommendations[] = 'Log for trend analysis';
                break;
        }
        
        // Generate based on event type
        switch ($incident['event_type']) {
            case 'security_breach':
                $recommendations[] = 'Review security protocols';
                $recommendations[] = 'Audit access logs';
                $recommendations[] = 'Change all affected passwords';
                break;
                
            case 'system_failure':
                $recommendations[] = 'Check system resources';
                $recommendations[] = 'Review error logs';
                $recommendations[] = 'Prepare rollback plan';
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Get recent similar incidents
     */
    private function getRecentSimilarIncidents($eventType, $days = 7)
    {
        try {
            $count = db()->fetchOne("
                SELECT COUNT(*) as count
                FROM ai_incident_timeline 
                WHERE tenant_id = ? AND event_type = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$this->tenantId, $eventType, $days]);
            
            return $count['count'] ?? 0;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getRecentSimilarIncidents error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Estimate affected users
     */
    private function estimateAffectedUsers($type)
    {
        try {
            switch ($type) {
                case 'security':
                    return db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND status = 'active'", [$this->tenantId])['count'] ?? 0;
                    
                case 'system':
                    return db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND status = 'active'", [$this->tenantId])['count'] ?? 0;
                    
                case 'data':
                    return db()->fetchOne("SELECT COUNT(DISTINCT user_id) as count FROM audit_logs WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$this->tenantId])['count'] ?? 0;
                    
                default:
                    return 0;
            }
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::estimateAffectedUsers error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Calculate recurrence likelihood
     */
    private function calculateRecurrenceLikelihood($incident)
    {
        $recentSimilar = $this->getRecentSimilarIncidents($incident['event_type'], 30);
        
        if ($recentSimilar >= 5) {
            return 'high';
        } elseif ($recentSimilar >= 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Estimate resolution time
     */
    private function estimateResolutionTime($incident)
    {
        switch ($incident['severity']) {
            case 'critical':
                return '2-4 hours';
            case 'high':
                return '4-8 hours';
            case 'medium':
                return '1-2 days';
            case 'low':
                return '3-5 days';
            default:
                return 'unknown';
        }
    }
    
    /**
     * Assess potential impact
     */
    private function assessPotentialImpact($incident)
    {
        $impact = [];
        
        switch ($incident['event_type']) {
            case 'security_breach':
                $impact[] = 'Data confidentiality risk';
                $impact[] = 'System availability risk';
                $impact[] = 'Reputation damage';
                break;
                
            case 'system_failure':
                $impact[] = 'Operational disruption';
                $impact[] = 'Data accessibility risk';
                $impact[] = 'Service availability risk';
                break;
                
            case 'data_loss':
                $impact[] = 'Data integrity risk';
                $impact[] = 'Compliance risk';
                $impact[] = 'Recovery time impact';
                break;
        }
        
        return $impact;
    }
    
    /**
     * Get historical outcomes
     */
    private function getHistoricalOutcomes($eventType)
    {
        try {
            return db()->fetchAll("
                SELECT 
                    severity,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_resolution_time
                FROM ai_incident_timeline 
                WHERE tenant_id = ? AND event_type = ? 
                AND status = 'resolved'
                GROUP BY severity
            ", [$this->tenantId, $eventType]);
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getHistoricalOutcomes error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update incident status
     */
    public function updateIncidentStatus($incidentId, $status, $resolutionNotes = '')
    {
        try {
            db()->update('ai_incident_timeline', [
                'status' => $status,
                'resolution_notes' => $resolutionNotes,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ? AND tenant_id = ?', [$incidentId, $this->tenantId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::updateIncidentStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get incident recommendations
     */
    public function getIncidentRecommendations($incidentId)
    {
        try {
            return db()->fetchAll("
                SELECT * FROM ai_incident_recommendations 
                WHERE incident_id = ? AND tenant_id = ?
                ORDER BY created_at DESC
            ", [$incidentId, $this->tenantId]);
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getIncidentRecommendations error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update recommendation status
     */
    public function updateRecommendationStatus($recommendationId, $status, $actionTaken = '')
    {
        try {
            db()->update('ai_incident_recommendations', [
                'status' => $status,
                'action_taken' => $actionTaken,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$recommendationId]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::updateRecommendationStatus error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate incident report
     */
    public function generateIncidentReport($incidentId, $format = 'pdf')
    {
        try {
            $incident = $this->getIncidentDetails($incidentId);
            
            if (!$incident) {
                return ['success' => false, 'error' => 'Incident not found'];
            }
            
            $reportData = [
                'incident' => $incident,
                'analysis' => $this->getIncidentAnalysis($incidentId),
                'recommendations' => $this->getIncidentRecommendations($incidentId),
                'timeline' => $this->getRelatedTimeline($incidentId),
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            // Use AI Documentation Engine to generate report
            if (class_exists('AIDocumentationEngine')) {
                $docEngine = new AIDocumentationEngine();
                $title = "Incident Report #{$incidentId} - {$incident['event_type']}";
                
                if ($format === 'pdf') {
                    return $docEngine->generatePDF($title, $reportData, [
                        'incident_id' => $incidentId,
                        'report_type' => 'incident',
                        'severity' => $incident['severity']
                    ]);
                } else {
                    return $docEngine->generateDOCX($title, $reportData, [
                        'incident_id' => $incidentId,
                        'report_type' => 'incident',
                        'severity' => $incident['severity']
                    ]);
                }
            }
            
            return ['success' => false, 'error' => 'Documentation engine not available'];
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::generateIncidentReport error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get incident analysis
     */
    private function getIncidentAnalysis($incidentId)
    {
        try {
            return db()->fetchAll("
                SELECT * FROM ai_incident_analysis 
                WHERE incident_id = ? AND tenant_id = ?
                ORDER BY created_at DESC
            ", [$incidentId, $this->tenantId]);
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getIncidentAnalysis error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get related timeline
     */
    private function getRelatedTimeline($incidentId)
    {
        try {
            $incident = $this->getIncidentDetails($incidentId);
            
            if (!$incident) {
                return [];
            }
            
            return db()->fetchAll("
                SELECT * FROM ai_incident_timeline 
                WHERE tenant_id = ? 
                AND id != ? 
                AND (
                    event_type = ? 
                    OR created_at BETWEEN DATE_SUB(?, INTERVAL 24 HOUR) AND DATE_ADD(?, INTERVAL 24 HOUR)
                )
                ORDER BY created_at DESC
                LIMIT 20
            ", [$this->tenantId, $incidentId, $incident['event_type'], $incident['created_at'], $incident['created_at']]);
            
        } catch (Exception $e) {
            error_log("AIIncidentTimeline::getRelatedTimeline error: " . $e->getMessage());
            return [];
        }
    }
}
