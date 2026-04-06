<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

class AttendanceSecurity
{
    private $tenantId;
    private $teacherId;
    
    public function __construct($teacherId = null, $tenantId = null)
    {
        $this->teacherId = $teacherId ?? ($_SESSION['user_id'] ?? 0);
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
    }
    
    /**
     * Generate OTP for attendance submission
     */
    public function generateAttendanceOTP($classId, $attendanceDate)
    {
        try {
            // Check if teacher is authorized for this class
            if (!$this->isTeacherAuthorizedForClass($classId)) {
                throw new Exception('Teacher not authorized for this class');
            }
            
            // Generate OTP
            $otp = $this->generateOTP();
            
            // Store OTP with expiration
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Remove any existing OTP for this session
            db()->query("
                DELETE FROM attendance_otp 
                WHERE teacher_id = ? AND class_id = ? AND attendance_date = ?
            ", [$this->teacherId, $classId, $attendanceDate]);
            
            // Insert new OTP
            $otpId = db()->insert('attendance_otp', [
                'tenant_id' => $this->tenantId,
                'teacher_id' => $this->teacherId,
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'otp' => password_hash($otp, PASSWORD_DEFAULT),
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log OTP generation
            $this->logSecurityEvent('otp_generated', [
                'otp_id' => $otpId,
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'expires_at' => $expiresAt
            ]);
            
            return [
                'success' => true,
                'otp' => $otp,
                'expires_at' => $expiresAt,
                'otp_id' => $otpId
            ];
            
        } catch (Exception $e) {
            error_log("AttendanceSecurity::generateAttendanceOTP error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify OTP for attendance submission
     */
    public function verifyAttendanceOTP($classId, $attendanceDate, $submittedOTP)
    {
        try {
            // Get stored OTP
            $storedOTP = db()->fetchOne("
                SELECT id, otp, expires_at, attempts, is_used
                FROM attendance_otp 
                WHERE teacher_id = ? AND class_id = ? AND attendance_date = ?
                ORDER BY created_at DESC
                LIMIT 1
            ", [$this->teacherId, $classId, $attendanceDate]);
            
            if (!$storedOTP) {
                throw new Exception('No OTP found for this session');
            }
            
            // Check if OTP is already used
            if ($storedOTP['is_used']) {
                throw new Exception('OTP has already been used');
            }
            
            // Check if OTP is expired
            if (strtotime($storedOTP['expires_at']) < time()) {
                throw new Exception('OTP has expired');
            }
            
            // Check attempts limit
            if ($storedOTP['attempts'] >= 3) {
                throw new Exception('Too many failed attempts. Please generate a new OTP.');
            }
            
            // Verify OTP
            if (!password_verify($submittedOTP, $storedOTP['otp'])) {
                // Increment attempts
                db()->update('attendance_otp', [
                    'attempts' => $storedOTP['attempts'] + 1
                ], 'id = ?', [$storedOTP['id']]);
                
                throw new Exception('Invalid OTP');
            }
            
            // Mark OTP as used
            db()->update('attendance_otp', [
                'is_used' => 1,
                'used_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$storedOTP['id']]);
            
            // Log successful verification
            $this->logSecurityEvent('otp_verified', [
                'otp_id' => $storedOTP['id'],
                'class_id' => $classId,
                'attendance_date' => $attendanceDate
            ]);
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully'
            ];
            
        } catch (Exception $e) {
            // Log failed verification
            $this->logSecurityEvent('otp_failed', [
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'error' => $e->getMessage()
            ]);
            
            error_log("AttendanceSecurity::verifyAttendanceOTP error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Submit attendance with OTP verification
     */
    public function submitAttendanceWithOTP($classId, $attendanceDate, $studentsData, $otp)
    {
        try {
            // Verify OTP first
            $otpVerification = $this->verifyAttendanceOTP($classId, $attendanceDate, $otp);
            if (!$otpVerification['success']) {
                return $otpVerification;
            }
            
            // Create attendance snapshot before changes
            $snapshotBefore = $this->createAttendanceSnapshot($classId, $attendanceDate, 'before');
            
            // Process attendance submission
            $results = $this->processAttendanceSubmission($classId, $attendanceDate, $studentsData);
            
            // Create attendance snapshot after changes
            $snapshotAfter = $this->createAttendanceSnapshot($classId, $attendanceDate, 'after');
            
            // Log attendance submission
            $this->logAttendanceSubmission($classId, $attendanceDate, $results, $snapshotBefore, $snapshotAfter);
            
            // Check for anomalies
            $anomalies = $this->detectAttendanceAnomalies($classId, $attendanceDate, $results);
            
            if (!empty($anomalies)) {
                // Flag for admin review
                $this->flagForAdminReview($classId, $attendanceDate, $anomalies);
            }
            
            return [
                'success' => true,
                'results' => $results,
                'anomalies' => $anomalies,
                'message' => 'Attendance submitted successfully'
            ];
            
        } catch (Exception $e) {
            error_log("AttendanceSecurity::submitAttendanceWithOTP error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if teacher is authorized for class
     */
    private function isTeacherAuthorizedForClass($classId)
    {
        try {
            $class = db()->fetchOne("
                SELECT id FROM classes 
                WHERE id = ? AND teacher_id = ? AND tenant_id = ? AND is_active = 1
            ", [$classId, $this->teacherId, $this->tenantId]);
            
            return !empty($class);
        } catch (Exception $e) {
            error_log("AttendanceSecurity::isTeacherAuthorizedForClass error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate 6-digit OTP
     */
    private function generateOTP()
    {
        return sprintf('%06d', mt_rand(0, 999999));
    }
    
    /**
     * Create attendance snapshot
     */
    private function createAttendanceSnapshot($classId, $attendanceDate, $type)
    {
        try {
            $attendance = db()->fetchAll("
                SELECT student_id, status, check_in_time, marked_by
                FROM attendance_records 
                WHERE class_id = ? AND DATE(check_in_time) = ?
            ", [$classId, $attendanceDate]);
            
            $snapshotData = [
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'type' => $type,
                'teacher_id' => $this->teacherId,
                'timestamp' => date('Y-m-d H:i:s'),
                'attendance_data' => json_encode($attendance)
            ];
            
            $snapshotId = db()->insert('attendance_snapshots', [
                'tenant_id' => $this->tenantId,
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'snapshot_type' => $type,
                'teacher_id' => $this->teacherId,
                'snapshot_data' => json_encode($attendance),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return [
                'snapshot_id' => $snapshotId,
                'data' => $snapshotData
            ];
            
        } catch (Exception $e) {
            error_log("AttendanceSecurity::createAttendanceSnapshot error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Process attendance submission
     */
    private function processAttendanceSubmission($classId, $attendanceDate, $studentsData)
    {
        $results = [
            'total_processed' => 0,
            'new_records' => 0,
            'updated_records' => 0,
            'students' => []
        ];
        
        foreach ($studentsData as $studentId => $status) {
            $results['total_processed']++;
            
            try {
                // Check if attendance already exists
                $existing = db()->fetchOne("
                    SELECT id, status FROM attendance_records
                    WHERE student_id = ? AND class_id = ? AND DATE(check_in_time) = ?
                ", [$studentId, $classId, $attendanceDate]);
                
                if ($existing) {
                    // Update existing record
                    db()->update('attendance_records', [
                        'status' => $status,
                        'check_in_time' => $attendanceDate . ' ' . date('H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$existing['id']]);
                    
                    $results['updated_records']++;
                    $changeType = 'updated';
                } else {
                    // Insert new record
                    db()->insert('attendance_records', [
                        'student_id' => $studentId,
                        'class_id' => $classId,
                        'check_in_time' => $attendanceDate . ' ' . date('H:i:s'),
                        'status' => $status,
                        'marked_by' => $this->teacherId,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $results['new_records']++;
                    $changeType = 'created';
                }
                
                $results['students'][] = [
                    'student_id' => $studentId,
                    'status' => $status,
                    'change_type' => $changeType,
                    'previous_status' => $existing['status'] ?? null
                ];
                
            } catch (Exception $e) {
                error_log("Error processing student {$studentId}: " . $e->getMessage());
                $results['students'][] = [
                    'student_id' => $studentId,
                    'status' => $status,
                    'change_type' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Log attendance submission
     */
    private function logAttendanceSubmission($classId, $attendanceDate, $results, $snapshotBefore, $snapshotAfter)
    {
        try {
            db()->insert('audit_logs', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->teacherId,
                'action' => 'attendance_submission',
                'details' => json_encode([
                    'class_id' => $classId,
                    'attendance_date' => $attendanceDate,
                    'results' => $results,
                    'snapshot_before_id' => $snapshotBefore['snapshot_id'] ?? null,
                    'snapshot_after_id' => $snapshotAfter['snapshot_id'] ?? null,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("AttendanceSecurity::logAttendanceSubmission error: " . $e->getMessage());
        }
    }
    
    /**
     * Detect attendance anomalies
     */
    private function detectAttendanceAnomalies($classId, $attendanceDate, $results)
    {
        $anomalies = [];
        
        try {
            // Check for uniform attendance patterns
            $statusCounts = array_count_values(array_column($results['students'], 'status'));
            $totalStudents = count($results['students']);
            
            if ($totalStudents >= 5) {
                $presentPercentage = ($statusCounts['present'] ?? 0) / $totalStudents;
                
                // Flag if 90%+ students are marked present
                if ($presentPercentage >= 0.9) {
                    $anomalies[] = [
                        'type' => 'uniform_present_pattern',
                        'severity' => 'warning',
                        'description' => 'Unusually high present rate: ' . round($presentPercentage * 100, 1) . '%',
                        'data' => [
                            'present_count' => $statusCounts['present'] ?? 0,
                            'total_students' => $totalStudents,
                            'percentage' => round($presentPercentage * 100, 1)
                        ]
                    ];
                }
                
                // Flag if 90%+ students are marked absent
                $absentPercentage = ($statusCounts['absent'] ?? 0) / $totalStudents;
                if ($absentPercentage >= 0.9) {
                    $anomalies[] = [
                        'type' => 'uniform_absent_pattern',
                        'severity' => 'high',
                        'description' => 'Unusually high absent rate: ' . round($absentPercentage * 100, 1) . '%',
                        'data' => [
                            'absent_count' => $statusCounts['absent'] ?? 0,
                            'total_students' => $totalStudents,
                            'percentage' => round($absentPercentage * 100, 1)
                        ]
                    ];
                }
            }
            
            // Check for rapid changes (if this is an update)
            $updateCount = $results['updated_records'] ?? 0;
            if ($updateCount > 0) {
                $changePercentage = ($updateCount / $totalStudents) * 100;
                
                // Flag if more than 50% of records are being updated
                if ($changePercentage > 50) {
                    $anomalies[] = [
                        'type' => 'bulk_update',
                        'severity' => 'medium',
                        'description' => 'Bulk attendance update: ' . round($changePercentage, 1) . '% of records changed',
                        'data' => [
                            'updated_count' => $updateCount,
                            'total_students' => $totalStudents,
                            'percentage' => round($changePercentage, 1)
                        ]
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("AttendanceSecurity::detectAttendanceAnomalies error: " . $e->getMessage());
        }
        
        return $anomalies;
    }
    
    /**
     * Flag attendance for admin review
     */
    private function flagForAdminReview($classId, $attendanceDate, $anomalies)
    {
        try {
            db()->insert('attendance_review_flags', [
                'tenant_id' => $this->tenantId,
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'teacher_id' => $this->teacherId,
                'anomalies' => json_encode($anomalies),
                'status' => 'pending_review',
                'flagged_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log flagging
            $this->logSecurityEvent('attendance_flagged', [
                'class_id' => $classId,
                'attendance_date' => $attendanceDate,
                'anomaly_count' => count($anomalies)
            ]);
            
        } catch (Exception $e) {
            error_log("AttendanceSecurity::flagForAdminReview error: " . $e->getMessage());
        }
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($eventType, $details)
    {
        try {
            db()->insert('security_logs', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->teacherId,
                'event_type' => $eventType,
                'details' => json_encode($details),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("AttendanceSecurity::logSecurityEvent error: " . $e->getMessage());
        }
    }
    
    /**
     * Get attendance OTP status
     */
    public function getOTPStatus($classId, $attendanceDate)
    {
        try {
            $otp = db()->fetchOne("
                SELECT id, expires_at, attempts, is_used, used_at
                FROM attendance_otp 
                WHERE teacher_id = ? AND class_id = ? AND attendance_date = ?
                ORDER BY created_at DESC
                LIMIT 1
            ", [$this->teacherId, $classId, $attendanceDate]);
            
            if ($otp) {
                $isExpired = strtotime($otp['expires_at']) < time();
                $remainingAttempts = 3 - $otp['attempts'];
                
                return [
                    'exists' => true,
                    'is_used' => $otp['is_used'],
                    'is_expired' => $isExpired,
                    'remaining_attempts' => max(0, $remainingAttempts),
                    'expires_at' => $otp['expires_at'],
                    'used_at' => $otp['used_at']
                ];
            }
            
            return ['exists' => false];
            
        } catch (Exception $e) {
            error_log("AttendanceSecurity::getOTPStatus error: " . $e->getMessage());
            return ['exists' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get attendance review flags for admin
     */
    public static function getReviewFlags($tenantId, $limit = 50)
    {
        try {
            return db()->fetchAll("
                SELECT arf.*, c.class_name, u.first_name, u.last_name,
                       DATE(arf.flagged_at) as flagged_date
                FROM attendance_review_flags arf
                JOIN classes c ON arf.class_id = c.id
                JOIN users u ON arf.teacher_id = u.id
                WHERE arf.tenant_id = ? AND arf.status = 'pending_review'
                ORDER BY arf.flagged_at DESC
                LIMIT ?
            ", [$tenantId, $limit]);
        } catch (Exception $e) {
            error_log("AttendanceSecurity::getReviewFlags error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Approve attendance submission
     */
    public static function approveAttendance($flagId, $adminId, $notes = '')
    {
        try {
            $flag = db()->fetchOne("
                SELECT * FROM attendance_review_flags 
                WHERE id = ?
            ", [$flagId]);
            
            if ($flag) {
                db()->update('attendance_review_flags', [
                    'status' => 'approved',
                    'reviewed_by' => $adminId,
                    'review_notes' => $notes,
                    'reviewed_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$flagId]);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("AttendanceSecurity::approveAttendance error: " . $e->getMessage());
            return false;
        }
    }
}
