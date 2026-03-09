<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

class AIPredictiveAnalytics
{
    private $tenantId;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
    }
    
    /**
     * Get comprehensive predictive analytics
     */
    public function getPredictiveAnalytics()
    {
        try {
            $analytics = [
                'student_performance' => $this->predictStudentPerformance(),
                'attendance_patterns' => $this->predictAttendancePatterns(),
                'dropout_risk' => $this->predictDropoutRisk(),
                'grade_trends' => $this->predictGradeTrends(),
                'enrollment_projections' => $this->predictEnrollmentProjections(),
                'resource_needs' => $this->predictResourceNeeds(),
                'financial_forecasts' => $this->predictFinancialForecasts(),
                'system_capacity' => $this->predictSystemCapacity()
            ];
            
            return $analytics;
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::getPredictiveAnalytics error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict student performance
     */
    public function predictStudentPerformance()
    {
        try {
            $predictions = [];
            
            // Get students with sufficient data for prediction
            $students = db()->fetchAll("
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    s.grade_level,
                    AVG(g.grade) as current_avg,
                    COUNT(g.grade) as grade_count,
                    COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as attendance_count,
                    COUNT(*) as total_attendance_records
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN grades g ON u.id = g.student_id
                LEFT JOIN attendance_records ar ON u.id = ar.student_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                GROUP BY u.id, u.first_name, u.last_name, s.grade_level
                HAVING grade_count >= 3 AND total_attendance_records >= 10
            ", [$this->tenantId]);
            
            foreach ($students as $student) {
                $prediction = $this->calculatePerformancePrediction($student);
                $predictions[] = array_merge($student, $prediction);
            }
            
            // Sort by risk level (highest risk first)
            usort($predictions, function($a, $b) {
                $riskLevels = ['very_high' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
                return $riskLevels[$b['performance_risk']] - $riskLevels[$a['performance_risk']];
            });
            
            return [
                'predictions' => $predictions,
                'summary' => $this->summarizePerformancePredictions($predictions),
                'recommendations' => $this->generatePerformanceRecommendations($predictions)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictStudentPerformance error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate performance prediction for individual student
     */
    private function calculatePerformancePrediction($student)
    {
        $currentAvg = $student['current_avg'];
        $attendanceRate = $student['total_attendance_records'] > 0 ? 
            ($student['attendance_count'] / $student['total_attendance_records']) * 100 : 0;
        
        // Calculate performance trend
        $trend = $this->calculatePerformanceTrend($student['id']);
        
        // Calculate risk factors
        $riskFactors = [
            'low_grades' => $currentAvg < 60 ? 3 : ($currentAvg < 70 ? 2 : ($currentAvg < 80 ? 1 : 0)),
            'poor_attendance' => $attendanceRate < 80 ? 2 : ($attendanceRate < 90 ? 1 : 0),
            'declining_trend' => $trend < -5 ? 2 : ($trend < -2 ? 1 : 0),
            'insufficient_data' => $student['grade_count'] < 5 ? 1 : 0
        ];
        
        $totalRisk = array_sum($riskFactors);
        
        // Determine risk level
        if ($totalRisk >= 6) {
            $riskLevel = 'very_high';
        } elseif ($totalRisk >= 4) {
            $riskLevel = 'high';
        } elseif ($totalRisk >= 2) {
            $riskLevel = 'medium';
        } else {
            $riskLevel = 'low';
        }
        
        // Predict next grade
        $predictedGrade = $currentAvg + ($trend * 0.5);
        $predictedGrade = max(0, min(100, $predictedGrade));
        
        return [
            'current_average' => round($currentAvg, 2),
            'attendance_rate' => round($attendanceRate, 1),
            'performance_trend' => round($trend, 2),
            'predicted_next_grade' => round($predictedGrade, 2),
            'performance_risk' => $riskLevel,
            'risk_factors' => $riskFactors,
            'confidence_level' => $this->calculateConfidenceLevel($student),
            'recommendations' => $this->generateStudentRecommendations($student, $riskLevel)
        ];
    }
    
    /**
     * Calculate performance trend
     */
    private function calculatePerformanceTrend($studentId)
    {
        try {
            $grades = db()->fetchAll("
                SELECT grade, created_at
                FROM grades
                WHERE student_id = ? AND tenant_id = ?
                ORDER BY created_at ASC
            ", [$studentId, $this->tenantId]);
            
            if (count($grades) < 2) {
                return 0;
            }
            
            // Simple linear regression
            $n = count($grades);
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;
            
            foreach ($grades as $i => $grade) {
                $x = $i;
                $y = $grade['grade'];
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }
            
            $trend = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
            
            return $trend;
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::calculatePerformanceTrend error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Calculate confidence level
     */
    private function calculateConfidenceLevel($student)
    {
        $dataPoints = $student['grade_count'];
        $attendanceRecords = $student['total_attendance_records'];
        
        if ($dataPoints >= 10 && $attendanceRecords >= 30) {
            return 'high';
        } elseif ($dataPoints >= 5 && $attendanceRecords >= 15) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Generate student recommendations
     */
    private function generateStudentRecommendations($student, $riskLevel)
    {
        $recommendations = [];
        
        switch ($riskLevel) {
            case 'very_high':
                $recommendations[] = 'Immediate intervention required';
                $recommendations[] = 'Schedule parent-teacher conference';
                $recommendations[] = 'Consider tutoring program';
                $recommendations[] = 'Monitor attendance closely';
                break;
                
            case 'high':
                $recommendations[] = 'Schedule academic counseling';
                $recommendations[] = 'Implement study plan';
                $recommendations[] = 'Regular progress monitoring';
                break;
                
            case 'medium':
                $recommendations[] = 'Provide additional support resources';
                $recommendations[] = 'Encourage peer study groups';
                break;
                
            case 'low':
                $recommendations[] = 'Continue current academic path';
                $recommendations[] = 'Consider advanced coursework';
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Predict attendance patterns
     */
    public function predictAttendancePatterns()
    {
        try {
            $patterns = [];
            
            // Get attendance data by day of week
            $dailyPatterns = db()->fetchAll("
                SELECT 
                    DAYOFWEEK(ar.check_in_time) as day_of_week,
                    DAYNAME(ar.check_in_time) as day_name,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                    ROUND((COUNT(CASE WHEN ar.status IN ('present', 'late') THEN 1 END) / COUNT(*)) * 100, 1) as attendance_rate
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                GROUP BY DAYOFWEEK(ar.check_in_time), DAYNAME(ar.check_in_time)
                ORDER BY day_of_week
            ", [$this->tenantId]);
            
            // Predict future attendance
            $futurePredictions = [];
            foreach ($dailyPatterns as $pattern) {
                $prediction = $this->predictAttendanceForDay($pattern);
                $futurePredictions[] = array_merge($pattern, $prediction);
            }
            
            // Identify at-risk students
            $atRiskStudents = $this->identifyAttendanceAtRiskStudents();
            
            return [
                'daily_patterns' => $dailyPatterns,
                'future_predictions' => $futurePredictions,
                'at_risk_students' => $atRiskStudents,
                'trends' => $this->analyzeAttendanceTrends(),
                'recommendations' => $this->generateAttendanceRecommendations($atRiskStudents)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictAttendancePatterns error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict attendance for specific day
     */
    private function predictAttendanceForDay($pattern)
    {
        $currentRate = $pattern['attendance_rate'];
        
        // Calculate trend based on recent weeks
        $trend = $this->calculateAttendanceTrend($pattern['day_of_week']);
        
        // Predict future rate
        $predictedRate = $currentRate + ($trend * 0.3);
        $predictedRate = max(0, min(100, $predictedRate));
        
        return [
            'predicted_rate' => round($predictedRate, 1),
            'trend' => round($trend, 2),
            'confidence' => 'medium',
            'risk_level' => $predictedRate < 85 ? 'high' : ($predictedRate < 90 ? 'medium' : 'low')
        ];
    }
    
    /**
     * Calculate attendance trend
     */
    private function calculateAttendanceTrend($dayOfWeek)
    {
        try {
            $attendance = db()->fetchAll("
                SELECT 
                    DATE(ar.check_in_time) as date,
                    ROUND((COUNT(CASE WHEN ar.status IN ('present', 'late') THEN 1 END) / COUNT(*)) * 100, 1) as rate
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND DAYOFWEEK(ar.check_in_time) = ?
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(ar.check_in_time)
                ORDER BY date ASC
            ", [$this->tenantId, $dayOfWeek]);
            
            if (count($attendance) < 2) {
                return 0;
            }
            
            // Simple trend calculation
            $firstWeek = array_slice($attendance, 0, 4);
            $lastWeek = array_slice($attendance, -4);
            
            $firstAvg = array_sum(array_column($firstWeek, 'rate')) / count($firstWeek);
            $lastAvg = array_sum(array_column($lastWeek, 'rate')) / count($lastWeek);
            
            return $lastAvg - $firstAvg;
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::calculateAttendanceTrend error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Identify attendance at-risk students
     */
    private function identifyAttendanceAtRiskStudents()
    {
        try {
            return db()->fetchAll("
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    s.grade_level,
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_days,
                    ROUND((COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*)) * 100, 1) as absenteeism_rate,
                    ROUND((COUNT(CASE WHEN ar.status IN ('present', 'late') THEN 1 END) / COUNT(*)) * 100, 1) as attendance_rate
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN attendance_records ar ON u.id = ar.student_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id, u.first_name, u.last_name, s.grade_level
                HAVING (COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*)) * 100 > 20
                ORDER BY absenteeism_rate DESC
            ", [$this->tenantId]);
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::identifyAttendanceAtRiskStudents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict dropout risk
     */
    public function predictDropoutRisk()
    {
        try {
            $students = db()->fetchAll("
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    s.grade_level,
                    AVG(g.grade) as avg_grade,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absences,
                    COUNT(*) as total_attendance,
                    COUNT(DISTINCT sa.assignment_id) as assignments_participated,
                    ROUND((COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*)) * 100, 1) as absenteeism_rate
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN grades g ON u.id = g.student_id
                LEFT JOIN attendance_records ar ON u.id = ar.student_id
                LEFT JOIN student_assignments sa ON u.id = sa.student_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                GROUP BY u.id, u.first_name, u.last_name, s.grade_level
                HAVING COUNT(g.grade) >= 3 AND COUNT(ar.id) >= 10
            ", [$this->tenantId]);
            
            $riskAssessments = [];
            foreach ($students as $student) {
                $riskScore = $this->calculateDropoutRiskScore($student);
                $riskAssessments[] = array_merge($student, $riskScore);
            }
            
            // Sort by risk score (highest first)
            usort($riskAssessments, function($a, $b) {
                return $b['dropout_risk_score'] - $a['dropout_risk_score'];
            });
            
            return [
                'risk_assessments' => $riskAssessments,
                'summary' => $this->summarizeDropoutRisks($riskAssessments),
                'intervention_recommendations' => $this->generateDropoutInterventions($riskAssessments)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictDropoutRisk error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate dropout risk score
     */
    private function calculateDropoutRiskScore($student)
    {
        $score = 0;
        $factors = [];
        
        // Academic performance factor
        $avgGrade = $student['avg_grade'];
        if ($avgGrade < 50) {
            $score += 40;
            $factors[] = 'very_poor_grades';
        } elseif ($avgGrade < 60) {
            $score += 30;
            $factors[] = 'poor_grades';
        } elseif ($avgGrade < 70) {
            $score += 20;
            $factors[] = 'below_average_grades';
        }
        
        // Attendance factor
        $absenteeismRate = $student['absenteeism_rate'];
        if ($absenteeismRate > 30) {
            $score += 35;
            $factors[] = 'chronic_absenteeism';
        } elseif ($absenteeismRate > 20) {
            $score += 25;
            $factors[] = 'high_absenteeism';
        } elseif ($absenteeismRate > 10) {
            $score += 15;
            $factors[] = 'moderate_absenteeism';
        }
        
        // Engagement factor
        $assignments = $student['assignments_participated'];
        if ($assignments < 5) {
            $score += 25;
            $factors[] = 'low_engagement';
        } elseif ($assignments < 10) {
            $score += 15;
            $factors[] = 'moderate_engagement';
        }
        
        // Determine risk level
        if ($score >= 80) {
            $riskLevel = 'very_high';
        } elseif ($score >= 60) {
            $riskLevel = 'high';
        } elseif ($score >= 40) {
            $riskLevel = 'medium';
        } else {
            $riskLevel = 'low';
        }
        
        return [
            'dropout_risk_score' => $score,
            'dropout_risk_level' => $riskLevel,
            'risk_factors' => $factors,
            'probability' => round($score / 100, 2),
            'intervention_priority' => $score >= 60 ? 'immediate' : ($score >= 40 ? 'soon' : 'monitor')
        ];
    }
    
    /**
     * Predict grade trends
     */
    public function predictGradeTrends()
    {
        try {
            $trends = [];
            
            // Get grade trends by subject
            $subjectTrends = db()->fetchAll("
                SELECT 
                    g.subject,
                    AVG(g.grade) as current_avg,
                    COUNT(*) as grade_count,
                    (
                        SELECT AVG(g2.grade)
                        FROM grades g2
                        WHERE g2.subject = g.subject
                        AND g2.created_at >= DATE_SUB(g.created_at, INTERVAL 30 DAY)
                    ) as previous_avg,
                    ROUND(
                        (AVG(g.grade) - (
                            SELECT AVG(g2.grade)
                            FROM grades g2
                            WHERE g2.subject = g.subject
                            AND g2.created_at >= DATE_SUB(g.created_at, INTERVAL 30 DAY)
                        )
                    ) * 100 / 
                    (
                        SELECT AVG(g2.grade)
                        FROM grades g2
                        WHERE g2.subject = g.subject
                        AND g2.created_at >= DATE_SUB(g.created_at, INTERVAL 30 DAY)
                    ), 1
                    ) as trend_percentage
                FROM grades g
                WHERE g.tenant_id = ? AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY g.subject
                HAVING grade_count >= 5
                ORDER BY current_avg DESC
            ", [$this->tenantId]);
            
            foreach ($subjectTrends as $trend) {
                $trends[] = [
                    'subject' => $trend['subject'],
                    'current_average' => round($trend['current_avg'], 2),
                    'previous_average' => round($trend['previous_avg'], 2),
                    'trend_percentage' => round($trend['trend_percentage'], 1),
                    'trend_direction' => $trend['trend_percentage'] > 2 ? 'improving' : ($trend['trend_percentage'] < -2 ? 'declining' : 'stable'),
                    'confidence' => $trend['grade_count'] >= 10 ? 'high' : 'medium'
                ];
            }
            
            return [
                'subject_trends' => $trends,
                'overall_trend' => $this->calculateOverallGradeTrend(),
                'predictions' => $this->predictFutureGrades($trends),
                'recommendations' => $this->generateGradeTrendRecommendations($trends)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictGradeTrends error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict enrollment projections
     */
    public function predictEnrollmentProjections()
    {
        try {
            $currentYear = date('Y');
            $projections = [];
            
            // Get historical enrollment data
            $historicalData = db()->fetchAll("
                SELECT 
                    YEAR(created_at) as year,
                    COUNT(CASE WHEN role = 'student' AND status = 'active' THEN 1 END) as students,
                    COUNT(CASE WHEN role = 'teacher' AND status = 'active' THEN 1 END) as teachers,
                    COUNT(CASE WHEN role = 'parent' AND status = 'active' THEN 1 END) as parents
                FROM users
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 YEAR)
                GROUP BY YEAR(created_at)
                ORDER BY year
            ", [$this->tenantId]);
            
            // Calculate growth rates
            $growthRates = $this->calculateGrowthRates($historicalData);
            
            // Project future enrollment
            for ($year = 1; $year <= 5; $year++) {
                $futureYear = $currentYear + $year;
                $projection = $this->projectEnrollmentForYear($historicalData, $growthRates, $futureYear);
                $projections[] = $projection;
            }
            
            return [
                'historical_data' => $historicalData,
                'growth_rates' => $growthRates,
                'projections' => $projections,
                'capacity_analysis' => $this->analyzeCapacityNeeds($projections),
                'recommendations' => $this->generateEnrollmentRecommendations($projections, $growthRates)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictEnrollmentProjections error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict resource needs
     */
    public function predictResourceNeeds()
    {
        try {
            $currentResources = $this->getCurrentResourceUtilization();
            $enrollmentProjections = $this->predictEnrollmentProjections();
            
            $resourceNeeds = [];
            
            foreach ($enrollmentProjections['projections'] as $projection) {
                $year = $projection['year'];
                $projectedStudents = $projection['students'];
                
                // Calculate resource needs
                $teachersNeeded = ceil($projectedStudents / 25); // 25:1 ratio
                $classroomsNeeded = ceil($projectedStudents / 30); // 30:1 ratio
                $budgetNeeded = $projectedStudents * 1500; // $1500 per student
                
                $resourceNeeds[] = [
                    'year' => $year,
                    'projected_students' => $projectedStudents,
                    'teachers_needed' => $teachersNeeded,
                    'classrooms_needed' => $classroomsNeeded,
                    'budget_needed' => $budgetNeeded,
                    'resource_gaps' => [
                        'teachers' => max(0, $teachersNeeded - $currentResources['teachers']),
                        'classrooms' => max(0, $classroomsNeeded - $currentResources['classrooms']),
                        'budget' => max(0, $budgetNeeded - $currentResources['budget'])
                    ]
                ];
            }
            
            return [
                'current_resources' => $currentResources,
                'projected_needs' => $resourceNeeds,
                'investment_recommendations' => $this->generateResourceInvestmentRecommendations($resourceNeeds),
                'timeline' => $this->generateResourceTimeline($resourceNeeds)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictResourceNeeds error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict financial forecasts
     */
    public function predictFinancialForecasts()
    {
        try {
            $forecasts = [];
            
            // Get historical financial data
            $historicalRevenue = db()->fetchAll("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as revenue,
                    COUNT(CASE WHEN status = 'paid' THEN 1 END) as transactions
                FROM financial_records
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ", [$this->tenantId]);
            
            // Calculate growth trends
            $revenueGrowth = $this->calculateRevenueGrowth($historicalRevenue);
            
            // Project future revenue
            for ($month = 1; $month <= 12; $month++) {
                $futureDate = date('Y-m', strtotime("+{$month} months"));
                $forecast = $this->projectRevenueForMonth($historicalRevenue, $revenueGrowth, $futureDate);
                $forecasts[] = $forecast;
            }
            
            return [
                'historical_revenue' => $historicalRevenue,
                'growth_trends' => $revenueGrowth,
                'forecasts' => $forecasts,
                'annual_projection' => $this->calculateAnnualProjection($forecasts),
                'risk_factors' => $this->identifyFinancialRisks($historicalRevenue),
                'recommendations' => $this->generateFinancialRecommendations($forecasts, $revenueGrowth)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictFinancialForecasts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Predict system capacity
     */
    public function predictSystemCapacity()
    {
        try {
            $currentMetrics = $this->getCurrentSystemMetrics();
            $enrollmentGrowth = $this->predictEnrollmentProjections();
            
            $capacityAnalysis = [];
            
            foreach ($enrollmentGrowth['projections'] as $projection) {
                $year = $projection['year'];
                $studentCount = $projection['students'];
                
                // Calculate capacity requirements
                $capacityAnalysis[] = [
                    'year' => $year,
                    'student_count' => $studentCount,
                    'database_size_mb' => $this->estimateDatabaseSize($studentCount),
                    'storage_gb' => $this->estimateStorageNeeds($studentCount),
                    'bandwidth_mbps' => $this->estimateBandwidthNeeds($studentCount),
                    'server_capacity' => $this->estimateServerCapacity($studentCount),
                    'upgrade_needed' => $this->determineUpgradeNeeds($studentCount, $currentMetrics)
                ];
            }
            
            return [
                'current_metrics' => $currentMetrics,
                'capacity_projections' => $capacityAnalysis,
                'upgrade_timeline' => $this->generateUpgradeTimeline($capacityAnalysis),
                'cost_estimates' => $this->estimateUpgradeCosts($capacityAnalysis),
                'recommendations' => $this->generateSystemRecommendations($capacityAnalysis)
            ];
            
        } catch (Exception $e) {
            error_log("AIPredictiveAnalytics::predictSystemCapacity error: " . $e->getMessage());
            return [];
        }
    }
    
    // Helper methods for the above functions
    private function summarizePerformancePredictions($predictions) { return []; }
    private function generatePerformanceRecommendations($predictions) { return []; }
    private function analyzeAttendanceTrends() { return []; }
    private function generateAttendanceRecommendations($students) { return []; }
    private function summarizeDropoutRisks($assessments) { return []; }
    private function generateDropoutInterventions($assessments) { return []; }
    private function calculateOverallGradeTrend() { return 0; }
    private function predictFutureGrades($trends) { return []; }
    private function generateGradeTrendRecommendations($trends) { return []; }
    private function calculateGrowthRates($data) { return []; }
    private function projectEnrollmentForYear($historical, $growthRates, $year) { return []; }
    private function getCurrentResourceUtilization() { return ['teachers' => 10, 'classrooms' => 20, 'budget' => 100000]; }
    private function analyzeCapacityNeeds($projections) { return []; }
    private function generateEnrollmentRecommendations($projections, $growth) { return []; }
    private function generateResourceInvestmentRecommendations($needs) { return []; }
    private function generateResourceTimeline($needs) { return []; }
    private function calculateRevenueGrowth($data) { return []; }
    private function projectRevenueForMonth($historical, $growth, $date) { return []; }
    private function calculateAnnualProjection($forecasts) { return []; }
    private function identifyFinancialRisks($data) { return []; }
    private function generateFinancialRecommendations($forecasts, $growth) { return []; }
    private function getCurrentSystemMetrics() { return []; }
    private function estimateDatabaseSize($students) { return $students * 0.5; }
    private function estimateStorageNeeds($students) { return $students * 0.1; }
    private function estimateBandwidthNeeds($students) { return $students * 0.05; }
    private function estimateServerCapacity($students) { return $students * 0.02; }
    private function determineUpgradeNeeds($students, $current) { return []; }
    private function generateUpgradeTimeline($analysis) { return []; }
    private function estimateUpgradeCosts($analysis) { return []; }
    private function generateSystemRecommendations($analysis) { return []; }
}
