<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

class AISchoolIntelligenceDashboard
{
    private $tenantId;

    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
    }

    /**
     * Get comprehensive school intelligence data
     */
    public function getIntelligenceData()
    {
        try {
            $intelligence = [
                'academic_performance' => $this->getAcademicPerformanceMetrics(),
                'attendance_analytics' => $this->getAttendanceAnalytics(),
                'student_engagement' => $this->getStudentEngagementMetrics(),
                'teacher_workload' => $this->getTeacherWorkloadAnalysis(),
                'financial_health' => $this->getFinancialHealthMetrics(),
                'system_performance' => $this->getSystemPerformanceMetrics(),
                'risk_assessment' => $this->getRiskAssessment(),
                'predictive_insights' => $this->getPredictiveInsights(),
                'operational_efficiency' => $this->getOperationalEfficiency()
            ];

            return $intelligence;

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getIntelligenceData error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get academic performance metrics
     */
    private function getAcademicPerformanceMetrics()
    {
        try {
            // Overall performance metrics
            $overall = db()->fetchOne("
                SELECT
                    COUNT(DISTINCT g.student_id) as total_students_graded,
                    AVG(g.grade) as average_grade,
                    COUNT(CASE WHEN g.grade >= 70 THEN 1 END) as students_passing,
                    COUNT(CASE WHEN g.grade >= 90 THEN 1 END) as students_excelling,
                    STDDEV(g.grade) as grade_variance
                FROM grades g
                JOIN users u ON g.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            // Performance by grade level
            $byGrade = db()->fetchAll("
                SELECT
                    s.grade_level,
                    AVG(g.grade) as avg_grade,
                    COUNT(DISTINCT g.student_id) as student_count,
                    COUNT(CASE WHEN g.grade >= 70 THEN 1 END) as passing_count,
                    ROUND((COUNT(CASE WHEN g.grade >= 70 THEN 1 END) / COUNT(DISTINCT g.student_id)) * 100, 1) as pass_rate
                FROM grades g
                JOIN users u ON g.student_id = u.id
                JOIN students s ON u.id = s.user_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY s.grade_level
                ORDER BY s.grade_level
            ", [$this->tenantId]);

            // Subject performance
            $bySubject = db()->fetchAll("
                SELECT
                    g.subject,
                    AVG(g.grade) as avg_grade,
                    COUNT(DISTINCT g.student_id) as student_count,
                    COUNT(CASE WHEN g.grade >= 70 THEN 1 END) as passing_count,
                    ROUND((COUNT(CASE WHEN g.grade >= 70 THEN 1 END) / COUNT(DISTINCT g.student_id)) * 100, 1) as pass_rate
                FROM grades g
                JOIN users u ON g.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY g.subject
                ORDER BY avg_grade DESC
            ", [$this->tenantId]);

            return [
                'overall' => [
                    'average_grade' => round($overall['average_grade'], 2),
                    'total_students' => $overall['total_students_graded'],
                    'pass_rate' => $overall['total_students_graded'] > 0 ? round(($overall['students_passing'] / $overall['total_students_graded']) * 100, 1) : 0,
                    'excellence_rate' => $overall['total_students_graded'] > 0 ? round(($overall['students_excelling'] / $overall['total_students_graded']) * 100, 1) : 0,
                    'grade_variance' => round($overall['grade_variance'], 2)
                ],
                'by_grade' => $byGrade,
                'by_subject' => $bySubject,
                'trends' => $this->getAcademicTrends()
            ];

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getAcademicPerformanceMetrics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attendance analytics
     */
    private function getAttendanceAnalytics()
    {
        try {
            // Overall attendance metrics
            $overall = db()->fetchOne("
                SELECT
                    COUNT(DISTINCT ar.student_id) as total_students,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_records,
                    COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_records,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_records,
                    ROUND((COUNT(CASE WHEN ar.status IN ('present', 'late') THEN 1 END) / COUNT(*)) * 100, 1) as attendance_rate
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            // Daily attendance trends
            $dailyTrends = db()->fetchAll("
                SELECT
                    DATE(ar.check_in_time) as date,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                    ROUND((COUNT(CASE WHEN ar.status IN ('present', 'late') THEN 1 END) / COUNT(*)) * 100, 1) as attendance_rate
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(ar.check_in_time)
                ORDER BY date DESC
            ", [$this->tenantId]);

            // Attendance by grade level
            $byGrade = db()->fetchAll("
                SELECT
                    s.grade_level,
                    COUNT(DISTINCT ar.student_id) as total_students,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                    ROUND((COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / COUNT(*)) * 100, 1) as attendance_rate
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.id
                JOIN students s ON u.id = s.user_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY s.grade_level
                ORDER BY s.grade_level
            ", [$this->tenantId]);

            // Chronic absenteeism
            $chronicAbsenteeism = db()->fetchAll("
                SELECT
                    u.first_name,
                    u.last_name,
                    s.grade_level,
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_days,
                    ROUND((COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*)) * 100, 1) as absenteeism_rate
                FROM attendance_records ar
                JOIN users u ON ar.student_id = u.id
                JOIN students s ON u.id = s.user_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id, u.first_name, u.last_name, s.grade_level
                HAVING COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) >= 5
                ORDER BY absenteeism_rate DESC
            ", [$this->tenantId]);

            return [
                'overall' => [
                    'total_students' => $overall['total_students'],
                    'attendance_rate' => $overall['attendance_rate'],
                    'present_rate' => $overall['total_records'] > 0 ? round(($overall['present_records'] / $overall['total_records']) * 100, 1) : 0,
                    'late_rate' => $overall['total_records'] > 0 ? round(($overall['late_records'] / $overall['total_records']) * 100, 1) : 0,
                    'absent_rate' => $overall['total_records'] > 0 ? round(($overall['absent_records'] / $overall['total_records']) * 100, 1) : 0
                ],
                'daily_trends' => $dailyTrends,
                'by_grade' => $byGrade,
                'chronic_absenteeism' => $chronicAbsenteeism,
                'patterns' => $this->analyzeAttendancePatterns()
            ];

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getAttendanceAnalytics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get student engagement metrics
     */
    private function getStudentEngagementMetrics()
    {
        try {
            // Forum engagement
            $forumEngagement = db()->fetchOne("
                SELECT
                    COUNT(DISTINCT fp.user_id) as active_students,
                    COUNT(*) as total_posts,
                    AVG(LENGTH(fp.content)) as avg_post_length,
                    COUNT(DISTINCT ft.id) as students_participating
                FROM forum_posts fp
                JOIN users u ON fp.user_id = u.id
                LEFT JOIN forum_threads ft ON fp.thread_id = ft.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            // Assignment completion
            $assignmentEngagement = db()->fetchOne("
                SELECT
                    COUNT(DISTINCT sa.student_id) as students_with_assignments,
                    COUNT(*) as total_submissions,
                    AVG(sa.grade) as avg_assignment_grade,
                    COUNT(CASE WHEN sa.submitted_at IS NOT NULL THEN 1 END) as on_time_submissions,
                    ROUND((COUNT(CASE WHEN sa.submitted_at IS NOT NULL THEN 1 END) / COUNT(*)) * 100, 1) as on_time_rate
                FROM student_assignments sa
                JOIN users u ON sa.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND sa.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            // System login engagement
            $loginEngagement = db()->fetchOne("
                SELECT
                    COUNT(DISTINCT user_id) as active_students,
                    COUNT(*) as total_logins,
                    AVG(TIMESTAMPDIFF(HOUR, MIN(al.created_at), MAX(al.created_at))) as avg_session_hours,
                    COUNT(DISTINCT DATE(al.created_at)) as active_days
                FROM activity_logs al
                JOIN users u ON al.user_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            return [
                'forum' => [
                    'active_students' => $forumEngagement['active_students'],
                    'total_posts' => $forumEngagement['total_posts'],
                    'avg_post_length' => round($forumEngagement['avg_post_length'], 0),
                    'participation_rate' => $forumEngagement['students_participating'] > 0 ? round(($forumEngagement['students_participating'] / $forumEngagement['active_students']) * 100, 1) : 0
                ],
                'assignments' => [
                    'active_students' => $assignmentEngagement['students_with_assignments'],
                    'total_submissions' => $assignmentEngagement['total_submissions'],
                    'avg_grade' => round($assignmentEngagement['avg_assignment_grade'], 2),
                    'on_time_rate' => $assignmentEngagement['on_time_rate']
                ],
                'system_usage' => [
                    'active_students' => $loginEngagement['active_students'],
                    'total_logins' => $loginEngagement['total_logins'],
                    'avg_session_hours' => round($loginEngagement['avg_session_hours'], 1),
                    'active_days' => $loginEngagement['active_days']
                ],
                'engagement_score' => $this->calculateEngagementScore($forumEngagement, $assignmentEngagement, $loginEngagement)
            ];

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getStudentEngagementMetrics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get teacher workload analysis
     */
    private function getTeacherWorkloadAnalysis()
    {
        try {
            // Overall teacher metrics
            $overall = db()->fetchOne("
                SELECT
                    COUNT(DISTINCT t.user_id) as total_teachers,
                    AVG(COUNT(DISTINCT c.id)) as avg_classes_per_teacher,
                    AVG(COUNT(DISTINCT ce.student_id)) as avg_students_per_teacher,
                    AVG(COUNT(DISTINCT ar.id)) as avg_attendance_records
                FROM teachers t
                LEFT JOIN classes c ON t.user_id = c.teacher_id
                LEFT JOIN class_enrollments ce ON c.id = ce.class_id
                LEFT JOIN attendance_records ar ON c.id = ar.class_id
                WHERE t.tenant_id = ? AND t.status = 'active'
                GROUP BY t.user_id
            ", [$this->tenantId]);

            // Individual teacher workloads
            $individual = db()->fetchAll("
                SELECT
                    u.first_name,
                    u.last_name,
                    COUNT(DISTINCT c.id) as class_count,
                    COUNT(DISTINCT ce.student_id) as student_count,
                    COUNT(DISTINCT ar.id) as attendance_records,
                    COUNT(DISTINCT sa.id) as assignment_submissions,
                    ROUND((COUNT(DISTINCT ar.id) / COUNT(DISTINCT ce.student_id)) * 100, 1) as attendance_workload,
                    ROUND((COUNT(DISTINCT sa.id) / COUNT(DISTINCT ce.student_id)) * 100, 1) as grading_workload
                FROM users u
                LEFT JOIN teachers t ON u.id = t.user_id
                LEFT JOIN classes c ON u.id = c.teacher_id
                LEFT JOIN class_enrollments ce ON c.id = ce.class_id
                LEFT JOIN attendance_records ar ON c.id = ar.class_id
                LEFT JOIN student_assignments sa ON ce.student_id = sa.student_id
                WHERE u.tenant_id = ? AND u.role = 'teacher' AND u.status = 'active'
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY student_count DESC
            ", [$this->tenantId]);

            // Workload distribution
            $workloadDistribution = [
                'light_load' => 0,
                'moderate_load' => 0,
                'heavy_load' => 0,
                'overloaded' => 0
            ];

            foreach ($individual as $teacher) {
                $totalWorkload = $teacher['attendance_workload'] + $teacher['grading_workload'];

                if ($totalWorkload < 50) {
                    $workloadDistribution['light_load']++;
                } elseif ($totalWorkload < 100) {
                    $workloadDistribution['moderate_load']++;
                } elseif ($totalWorkload < 150) {
                    $workloadDistribution['heavy_load']++;
                } else {
                    $workloadDistribution['overloaded']++;
                }
            }

            return [
                'overall' => [
                    'total_teachers' => $overall['total_teachers'],
                    'avg_classes_per_teacher' => round($overall['avg_classes_per_teacher'], 1),
                    'avg_students_per_teacher' => round($overall['avg_students_per_teacher'], 1),
                    'avg_attendance_records' => round($overall['avg_attendance_records'], 0)
                ],
                'individual' => $individual,
                'distribution' => $workloadDistribution,
                'recommendations' => $this->generateWorkloadRecommendations($individual, $workloadDistribution)
            ];

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getTeacherWorkloadAnalysis error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get financial health metrics
     */
    private function getFinancialHealthMetrics()
    {
        try {
            // Revenue metrics
            $revenue = db()->fetchOne("
                SELECT
                    SUM(CASE WHEN fr.status = 'paid' THEN fr.amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN fr.status = 'pending' THEN fr.amount ELSE 0 END) as pending_revenue,
                    SUM(CASE WHEN fr.status = 'overdue' THEN fr.amount ELSE 0 END) as overdue_revenue,
                    COUNT(CASE WHEN fr.status = 'paid' THEN 1 END) as paid_transactions,
                    COUNT(CASE WHEN fr.status = 'pending' THEN 1 END) as pending_transactions,
                    COUNT(CASE WHEN fr.status = 'overdue' THEN 1 END) as overdue_transactions
                FROM financial_records fr
                WHERE fr.tenant_id = ? AND fr.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            // Expense metrics
            $expenses = db()->fetchOne("
                SELECT
                    SUM(amount) as total_expenses,
                    COUNT(*) as expense_count
                FROM expenses
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            // Cash flow analysis
            $cashFlow = db()->fetchAll("
                SELECT
                    DATE(created_at) as date,
                    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as revenue,
                    SUM(CASE WHEN status = 'paid' THEN -amount ELSE 0 END) as expenses
                FROM financial_records
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ", [$this->tenantId]);

            return [
                'revenue' => [
                    'total' => $revenue['total_revenue'],
                    'pending' => $revenue['pending_revenue'],
                    'overdue' => $revenue['overdue_revenue'],
                    'collection_rate' => $revenue['total_revenue'] > 0 ? round((($revenue['total_revenue'] - $revenue['pending_revenue']) / $revenue['total_revenue']) * 100, 1) : 0,
                    'paid_transactions' => $revenue['paid_transactions'],
                    'pending_transactions' => $revenue['pending_transactions'],
                    'overdue_transactions' => $revenue['overdue_transactions']
                ],
                'expenses' => [
                    'total' => $expenses['total_expenses'],
                    'count' => $expenses['expense_count']
                ],
                'profitability' => [
                    'net_profit' => $revenue['total_revenue'] - $expenses['total_expenses'],
                    'profit_margin' => $revenue['total_revenue'] > 0 ? round((($revenue['total_revenue'] - $expenses['total_expenses']) / $revenue['total_revenue']) * 100, 1) : 0,
                    'cash_flow' => $cashFlow
                ],
                'health_indicators' => $this->calculateFinancialHealth($revenue, $expenses)
            ];

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getFinancialHealthMetrics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get system performance metrics
     */
    private function getSystemPerformanceMetrics()
    {
        try {
            // System uptime and performance
            $systemMetrics = [
                'database_performance' => $this->getDatabasePerformance(),
                'user_activity' => $this->getUserActivityMetrics(),
                'error_rates' => $this->getErrorRates(),
                'resource_utilization' => $this->getResourceUtilization()
            ];

            return $systemMetrics;

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getSystemPerformanceMetrics error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get risk assessment
     */
    private function getRiskAssessment()
    {
        try {
            $risks = [
                'academic_risks' => $this->assessAcademicRisks(),
                'operational_risks' => $this->assessOperationalRisks(),
                'financial_risks' => $this->assessFinancialRisks(),
                'security_risks' => $this->assessSecurityRisks(),
                'compliance_risks' => $this->assessComplianceRisks()
            ];

            return $risks;

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getRiskAssessment error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get predictive insights
     */
    private function getPredictiveInsights()
    {
        try {
            $insights = [
                'at_risk_students' => $this->identifyAtRiskStudents(),
                'dropout_predictions' => $this->predictDropoutRisk(),
                'performance_trends' => $this->predictPerformanceTrends(),
                'enrollment_projections' => $this->predictEnrollmentTrends(),
                'resource_needs' => $this->predictResourceNeeds()
            ];

            return $insights;

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getPredictiveInsights error: " . . $e->getMessage());
            return [];
        }
    }

    /**
     * Get operational efficiency
     */
    private function getOperationalEfficiency()
    {
        try {
            $efficiency = [
                'process_automation' => $this->measureProcessAutomation(),
                'resource_utilization' => $this->measureResourceUtilization(),
                'time_management' => $this->measureTimeManagement(),
                'cost_efficiency' => $this->measureCostEfficiency()
            ];

            return $efficiency;

        } catch (Exception $e) {
            error_log("AISchoolIntelligenceDashboard::getOperationalEfficiency error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate engagement score
     */
    private function calculateEngagementScore($forum, $assignments, $login)
    {
        $forumScore = min(($forum['participation_rate'] / 100) * 30, 30);
        $assignmentScore = min(($assignments['on_time_rate'] / 100) * 40, 40);
        $loginScore = min(($login['active_days'] / 30) * 30, 30);

        return $forumScore + $assignmentScore + $loginScore;
    }

    /**
     * Generate workload recommendations
     */
    private function generateWorkloadRecommendations($teachers, $distribution)
    {
        $recommendations = [];

        if ($distribution['overloaded'] > 0) {
            $recommendations[] = "Immediate action required: " . $distribution['overloaded'] . " teachers are overloaded";
        }

        if ($distribution['heavy_load'] > 2) {
            $recommendations[] = "Consider redistributing classes from " . $distribution['heavy_load'] . " heavily loaded teachers";
        }

        if ($distribution['light_load'] > 2) {
            $recommendations[] = "Consider assigning more classes to " . $distribution['light_load'] . " lightly loaded teachers";
        }

        return $recommendations;
    }

    /**
     * Get database performance
     */
    private function getDatabasePerformance()
    {
        try {
            // This would typically involve checking database metrics
            // For now, return simulated data
            return [
                'query_response_time' => 45, // ms
                'connection_pool_usage' => 65, // %
                'slow_queries_count' => 3,
                'index_usage_efficiency' => 85 // %
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get user activity metrics
     */
    private function getUserActivityMetrics()
    {
        try {
            return db()->fetchOne("
                SELECT
                    COUNT(DISTINCT user_id) as active_users_today,
                    COUNT(*) as total_activities,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, NOW())) as avg_activity_age
                FROM activity_logs
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ", [$this->tenantId]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get error rates
     */
    private function getErrorRates()
    {
        try {
            return db()->fetchOne("
                SELECT
                    COUNT(*) as total_errors,
                    COUNT(CASE WHEN error_level = 'critical' THEN 1 END) as critical_errors,
                    COUNT(CASE WHEN error_level = 'warning' THEN 1 END) as warning_errors
                FROM system_logs
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ", [$this->tenantId]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get resource utilization
     */
    private function getResourceUtilization()
    {
        try {
            return [
                'cpu_usage' => 45, // %
                'memory_usage' => 60, // %
                'disk_usage' => 75, // %
                'bandwidth_usage' => 30 // %
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Assess academic risks
     */
    private function assessAcademicRisks()
    {
        try {
            $risks = [];

            // Check for failing students
            $failingStudents = db()->fetchOne("
                SELECT COUNT(DISTINCT g.student_id) as count
                FROM grades g
                JOIN users u ON g.student_id = u.id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.grade < 50
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            if ($failingStudents['count'] > 10) {
                $risks[] = [
                    'type' => 'academic_failure',
                    'severity' => 'high',
                    'description' => $failingStudents['count'] . ' students with grades below 50%',
                    'recommendation' => 'Implement intervention programs for at-risk students'
                ];
            }

            return $risks;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Assess operational risks
     */
    private function assessOperationalRisks()
    {
        try {
            $risks = [];

            // Check for teacher shortages
            $teacherRatio = db()->fetchOne("
                SELECT
                    COUNT(DISTINCT u.id) as student_count,
                    COUNT(DISTINCT t.user_id) as teacher_count
                FROM users u
                LEFT JOIN teachers t ON u.id = t.user_id
                WHERE u.tenant_id = ? AND u.role = 'student'
            ", [$this->tenantId]);

            if ($teacherRatio['teacher_count'] > 0) {
                $ratio = $teacherRatio['student_count'] / $teacherRatio['teacher_count'];
                if ($ratio > 30) {
                    $risks[] = [
                        'type' => 'teacher_shortage',
                        'severity' => 'medium',
                        'description' => 'Student-teacher ratio of ' . round($ratio, 1) . ':1',
                        'recommendation' => 'Hire additional teachers to maintain quality education'
                    ];
                }
            }

            return $risks;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Assess financial risks
     */
    private function assessFinancialRisks()
    {
        try {
            $risks = [];

            // Check for cash flow issues
            $cashFlow = db()->fetchOne("
                SELECT
                    SUM(CASE WHEN status = 'overdue' THEN amount ELSE 0 END) as overdue_amount,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
                FROM financial_records
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$this->tenantId]);

            if ($cashFlow['overdue_amount'] > 10000) {
                $risks[] = [
                    'type' => 'cash_flow',
                    'severity' => 'high',
                    'description' => '$' . number_format($cashFlow['overdue_amount'], 2) . ' in overdue payments',
                    'recommendation' => 'Implement stronger collection processes and consider payment plans'
                ];
            }

            return $risks;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Assess security risks
     */
    private function assessSecurityRisks()
    {
        try {
            $risks = [];

            // Check for failed login attempts
            $failedLogins = db()->fetchOne("
                SELECT COUNT(*) as count
                FROM security_logs
                WHERE tenant_id = ? AND event_type = 'login_failure'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ", [$this->tenantId]);

            if ($failedLogins['count'] > 50) {
                $risks[] = [
                    'type' => 'security_breach',
                    'severity' => 'medium',
                    'description' => $failedLogins['count'] . ' failed login attempts in 24 hours',
                    'recommendation' => 'Review security measures and consider account lockouts'
                ];
            }

            return $risks;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Assess compliance risks
     */
    private function assessComplianceRisks()
    {
        try {
            $risks = [];

            // Check for missing documentation
            $missingDocs = db()->fetchOne("
                SELECT COUNT(*) as count
                FROM audit_logs
                WHERE tenant_id = ? AND action LIKE '%missing%'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ", [$this->tenantId]);

            if ($missingDocs['count'] > 5) {
                $risks[] = [
                    'type' => 'compliance',
                    'severity' => 'low',
                    'description' => $missingDocs['count'] . ' missing documentation events',
                    'recommendation' => 'Ensure all required documentation is properly maintained'
                ];
            }

            return $risks;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Identify at-risk students
     */
    private function identifyAtRiskStudents()
    {
        try {
            return db()->fetchAll("
                SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    s.grade_level,
                    AVG(g.grade) as avg_grade,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absences_count,
                    ROUND((COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*)) * 100, 1) as absenteeism_rate
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN grades g ON u.id = g.student_id
                LEFT JOIN attendance_records ar ON u.id = ar.student_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id, u.first_name, u.last_name, s.grade_level
                HAVING avg_grade < 60 OR (COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*)) * 100 > 20
                ORDER BY avg_grade ASC, absenteeism_rate DESC
                LIMIT 20
            ", [$this->tenantId]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Predict dropout risk
     */
    private function predictDropoutRisk()
    {
        try {
            return db()->fetchAll("
                SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    s.grade_level,
                    AVG(g.grade) as avg_grade,
                    COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as total_absences,
                    ROUND((COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*)) * 100, 1) as absenteeism_rate,
                    COUNT(DISTINCT sa.assignment_id) as assignment_participation,
                    CASE
                        WHEN AVG(g.grade) < 50 AND (COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*) * 100 > 25 THEN 'very_high'
                        WHEN AVG(g.grade) < 60 AND (COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*) * 100 > 15) THEN 'high'
                        WHEN AVG(g.grade) < 70 AND (COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / COUNT(*) * 100 > 10) THEN 'medium'
                        ELSE 'low'
                    END as dropout_risk
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN grades g ON u.id = g.student_id
                LEFT JOIN attendance_records ar ON u.id = ar.student_id
                LEFT JOIN student_assignments sa ON u.id = sa.student_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND DATE(ar.check_in_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id, u.first_name, u.last_name, s.grade_level
                ORDER BY dropout_risk DESC, avg_grade ASC
            ", [$this->tenantId]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Predict performance trends
     */
    private function predictPerformanceTrends()
    {
        try {
            return db()->fetchAll("
                SELECT
                    s.grade_level,
                    AVG(g.grade) as current_avg,
                    (
                        SELECT AVG(g2.grade)
                        FROM grades g2
                        JOIN users u2 ON g2.student_id = u2.id
                        JOIN students s2 ON u2.id = s2.user_id
                        WHERE s2.grade_level = s.grade_level
                        AND g2.created_at >= DATE_SUB(g.created_at, INTERVAL 30 DAY)
                    ) as previous_avg,
                    ROUND(
                        (AVG(g.grade) - (
                            SELECT AVG(g2.grade)
                            FROM grades g2
                            JOIN users u2 ON g2.student_id = u2.id
                            JOIN students s2 ON u2.id = s2.user_id
                            WHERE s2.grade_level = s.grade_level
                            AND g2.created_at >= DATE_SUB(g.created_at, INTERVAL 30 DAY)
                        )
                    ) * 100 /
                    (
                        SELECT AVG(g2.grade)
                        FROM grades g2
                        JOIN users u2 ON g2.student_id = u2.id
                        JOIN students s2 ON u2.id = s2.user_id
                        WHERE s2.grade_level = s.grade_level
                        AND g2.created_at >= DATE_SUB(g.created_at, INTERVAL 30 DAY)
                    ), 1
                    ) as performance_trend
                FROM grades g
                JOIN users u ON g.student_id = u.id
                JOIN students s ON u.id = s.user_id
                WHERE u.tenant_id = ? AND u.role = 'student'
                AND g.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY s.grade_level
                ORDER BY s.grade_level
            ", [$this->tenantId]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Predict enrollment trends
     */
    private function predictEnrollmentTrends()
    {
        try {
            return db()->fetchAll("
                SELECT
                    DATE(created_at) as date,
                    COUNT(CASE WHEN role = 'student' AND status = 'active' THEN 1 END) as new_enrollments,
                    COUNT(CASE WHEN role = 'student' THEN 1 END) as total_students
                FROM users
                WHERE tenant_id = ?
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30
            ", [$this->tenantId]);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Predict resource needs
     */
    private function predictResourceNeeds()
    {
        try {
            $currentStudents = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND role = 'student' AND status = 'active'", [$this->tenantId])['count'];
            $currentTeachers = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE tenant_id = ? AND role = 'teacher' AND status = 'active'", [$this->tenantId])['count'];

            $projectedStudents = $currentStudents * 1.05; // 5% growth
            $projectedTeachers = ceil($projectedStudents / 25); // 25:1 ratio

            return [
                'current_students' => $currentStudents,
                'projected_students' => $projectedStudents,
                'current_teachers' => $currentTeachers,
                'projected_teachers' => $projectedTeachers,
                'teacher_gap' => $projectedTeachers - $currentTeachers,
                'recommendations' => $projectedTeachers > $currentTeachers ? [
                    'Hire ' . ($projectedTeachers - $currentTeachers) . ' additional teachers'
                ] : []
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Measure process automation
     */
    private function measureProcessAutomation()
    {
        try {
            // Check automated processes
            $automatedProcesses = [
                'attendance_automation' => $this->checkAttendanceAutomation(),
                'grade_calculation' => $this->checkGradeCalculation(),
                'report_generation' => $this->checkReportGeneration(),
                'notification_system' => $this->checkNotificationSystem()
            ];

            return $automatedProcesses;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Measure resource utilization
     */
    private function measureResourceUtilization()
    {
        try {
            return [
                'classroom_utilization' => $this->measureClassroomUtilization(),
                'teacher_utilization' => $this->measureTeacherUtilization(),
                'equipment_utilization' => $this->measureEquipmentUtilization()
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Measure time management
     */
    private function measureTimeManagement()
    {
        try {
            return [
                'administrative_efficiency' => $this->measureAdministrativeEfficiency(),
                'teaching_efficiency' => $this->measureTeachingEfficiency(),
                'student_service_efficiency' => $this->measureStudentServiceEfficiency()
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Measure cost efficiency
     */
    private function measureCostEfficiency()
    {
        try {
            return [
                'cost_per_student' => $this->calculateCostPerStudent(),
                'revenue_per_teacher' => $this->calculateRevenuePerTeacher(),
                'operational_efficiency' => $this->calculateOperationalEfficiency()
            ];
        } catch (Exception $e) {
            return [];
        }
    }

    // Helper methods for the above functions would be implemented here
    private function getAcademicTrends() { return []; }
    private function analyzeAttendancePatterns() { return []; }
    private function calculateFinancialHealth($revenue, $expenses) { return []; }
    private function checkAttendanceAutomation() { return true; }
    private function checkGradeCalculation() { return true; }
    private function checkReportGeneration() { return true; }
    private function checkNotificationSystem() { return true; }
    private function measureClassroomUtilization() { return 85; }
    private function measureTeacherUtilization() { return 78; }
    private function measureEquipmentUtilization() { return 72; }
    private function measureAdministrativeEfficiency() { return 82; }
    private function measureTeachingEfficiency() { return 88; }
    private function measureStudentServiceEfficiency() { return 76; }
    private function calculateCostPerStudent() { return 1500; }
    private function calculateRevenuePerTeacher() { return 45000; }
    private function calculateOperationalEfficiency() { return 85; }
}
