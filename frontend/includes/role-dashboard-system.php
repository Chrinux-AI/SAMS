<?php

if (!function_exists('sams_dashboard_escape')) {
    function sams_dashboard_escape($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sams_dashboard_role_folder')) {
    function sams_dashboard_role_folder($role)
    {
        $role = strtolower(trim((string)$role));
        $map = [
            'super_admin' => 'admin',
            'superadmin' => 'admin',
            'admin_officer' => 'admin',
            'vice_principal' => 'principal',
            'class_teacher' => 'teacher',
            'subject_coordinator' => 'teacher',
            'forum_moderator' => 'forum-moderator',
        ];

        return $map[$role] ?? $role;
    }
}

if (!function_exists('sams_dashboard_role_name')) {
    function sams_dashboard_role_name($role)
    {
        $role = str_replace(['_', '-'], ' ', (string)$role);
        return ucwords($role);
    }
}

if (!function_exists('sams_dashboard_has_table')) {
    function sams_dashboard_has_table($table)
    {
        return function_exists('table_exists') ? table_exists($table) : false;
    }
}

if (!function_exists('sams_dashboard_scoped_where')) {
    function sams_dashboard_scoped_where($table, $tenantId, $extra = '1=1', array $params = [])
    {
        $clauses = [];
        $binds = [];

        if ($extra !== '') {
            $clauses[] = '(' . $extra . ')';
            $binds = $params;
        }

        if (function_exists('table_has_column')) {
            if (table_has_column($table, 'tenant_id')) {
                $clauses[] = 'tenant_id = ?';
                $binds[] = (int)$tenantId;
            } elseif (table_has_column($table, 'school_id')) {
                $clauses[] = 'school_id = ?';
                $binds[] = (int)$tenantId;
            }
        }

        return [
            'sql' => implode(' AND ', $clauses ?: ['1=1']),
            'params' => $binds,
        ];
    }
}

if (!function_exists('sams_dashboard_count')) {
    function sams_dashboard_count($table, $tenantId, $extra = '1=1', array $params = [])
    {
        if (!sams_dashboard_has_table($table)) {
            return 0;
        }

        try {
            $where = sams_dashboard_scoped_where($table, $tenantId, $extra, $params);
            $row = db()->fetchOne("SELECT COUNT(*) AS aggregate_total FROM {$table} WHERE {$where['sql']}", $where['params']);
            return (int)($row['aggregate_total'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('sams_dashboard_sum')) {
    function sams_dashboard_sum($table, $column, $tenantId, $extra = '1=1', array $params = [])
    {
        if (!sams_dashboard_has_table($table) || (function_exists('table_has_column') && !table_has_column($table, $column))) {
            return 0;
        }

        try {
            $where = sams_dashboard_scoped_where($table, $tenantId, $extra, $params);
            $row = db()->fetchOne("SELECT COALESCE(SUM({$column}), 0) AS aggregate_sum FROM {$table} WHERE {$where['sql']}", $where['params']);
            return (float)($row['aggregate_sum'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('sams_dashboard_query_value')) {
    function sams_dashboard_query_value($sql, array $params, $field, $default = 0)
    {
        try {
            $row = db()->fetchOne($sql, $params);
            return $row[$field] ?? $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('sams_dashboard_ngn')) {
    function sams_dashboard_ngn($amount)
    {
        return 'NGN ' . number_format((float)$amount, 2);
    }
}

if (!function_exists('sams_dashboard_metric')) {
    function sams_dashboard_metric($label, $value, $note, $icon, $tone = 'indigo')
    {
        return [
            'label' => $label,
            'value' => $value,
            'note' => $note,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }
}

if (!function_exists('sams_dashboard_panel')) {
    function sams_dashboard_panel($title, $body, $icon, $tone = 'slate')
    {
        return [
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'tone' => $tone,
        ];
    }
}

if (!function_exists('sams_dashboard_recent_activity')) {
    function sams_dashboard_recent_activity($tenantId)
    {
        if (sams_dashboard_has_table('audit_logs')) {
            try {
                $params = [];
                $sql = "SELECT action, created_at FROM audit_logs";
                if (function_exists('table_has_column') && table_has_column('audit_logs', 'tenant_id')) {
                    $sql .= " WHERE tenant_id = ?";
                    $params[] = (int)$tenantId;
                }
                $sql .= " ORDER BY created_at DESC LIMIT 5";
                $rows = db()->fetchAll($sql, $params);
                if (!empty($rows)) {
                    $items = [];
                    foreach ($rows as $row) {
                        $items[] = [
                            'title' => ucwords(str_replace('_', ' ', (string)($row['action'] ?? 'system activity'))),
                            'meta' => (string)($row['created_at'] ?? 'recently'),
                        ];
                    }
                    return $items;
                }
            } catch (Exception $e) {
            }
        }

        if (sams_dashboard_has_table('attendance_records')) {
            try {
                $rows = db()->fetchAll(
                    "SELECT status, check_in_time FROM attendance_records ORDER BY check_in_time DESC LIMIT 5"
                );
                if (!empty($rows)) {
                    $items = [];
                    foreach ($rows as $row) {
                        $items[] = [
                            'title' => 'Attendance marked as ' . ucfirst((string)($row['status'] ?? 'recorded')),
                            'meta' => (string)($row['check_in_time'] ?? 'recently'),
                        ];
                    }
                    return $items;
                }
            } catch (Exception $e) {
            }
        }

        return [
            ['title' => 'Dashboard initialized', 'meta' => 'Ready for live school activity'],
            ['title' => 'Role access confirmed', 'meta' => 'Shared dashboard shell active'],
            ['title' => 'Operational surface loaded', 'meta' => 'Waiting for the next action'],
        ];
    }
}

if (!function_exists('sams_dashboard_action_candidates')) {
    function sams_dashboard_action_candidates()
    {
        return [
            'admin' => [
                ['href' => 'students.php', 'label' => 'Manage Students', 'icon' => 'school', 'note' => 'Admissions, enrollment, and student lifecycle'],
                ['href' => 'attendance.php', 'label' => 'Attendance Command', 'icon' => 'fact_check', 'note' => 'Daily schoolwide attendance oversight'],
                ['href' => 'merit-overview.php', 'label' => 'Merit Board', 'icon' => 'leaderboard', 'note' => 'Class points, private points, and rankings'],
                ['href' => 'invites.php', 'label' => 'Invite Staff', 'icon' => 'mail', 'note' => 'School-first onboarding and controlled access'],
            ],
            'owner' => [
                ['href' => 'users.php', 'label' => 'User Control', 'icon' => 'manage_accounts', 'note' => 'Govern high-level access and approvals'],
                ['href' => 'financial-management.php', 'label' => 'Financial Command', 'icon' => 'payments', 'note' => 'Review finance posture and collections'],
                ['href' => 'backup-export.php', 'label' => 'Backup Center', 'icon' => 'cloud_upload', 'note' => 'Protect exports and continuity'],
                ['href' => 'reports.php', 'label' => 'Executive Reports', 'icon' => 'assessment', 'note' => 'Review institutional performance'],
            ],
            'principal' => [
                ['href' => 'attendance.php', 'label' => 'Attendance Oversight', 'icon' => 'fact_check', 'note' => 'Monitor academic discipline and punctuality'],
                ['href' => 'reports.php', 'label' => 'Academic Reports', 'icon' => 'assessment', 'note' => 'Review schoolwide academic output'],
                ['href' => 'analytics.php', 'label' => 'Analytics', 'icon' => 'bar_chart', 'note' => 'Inspect patterns and intervention signals'],
                ['href' => 'class-enrollment.php', 'label' => 'Enrollment Flow', 'icon' => 'how_to_reg', 'note' => 'Track class-arm distribution'],
            ],
            'staff' => [
                ['href' => 'tasks.php', 'label' => 'Task Board', 'icon' => 'task_alt', 'note' => 'Run assigned operational work'],
                ['href' => 'student-support.php', 'label' => 'Student Support', 'icon' => 'support_agent', 'note' => 'Assist students with school issues'],
                ['href' => 'reports.php', 'label' => 'Operational Reports', 'icon' => 'summarize', 'note' => 'Submit updates and summaries'],
                ['href' => '../notices.php', 'label' => 'School Notices', 'icon' => 'campaign', 'note' => 'Stay aligned with institution-wide directives'],
            ],
            'nurse' => [
                ['href' => 'health-records.php', 'label' => 'Health Records', 'icon' => 'folder_shared', 'note' => 'Review student medical histories'],
                ['href' => 'first-aid.php', 'label' => 'First Aid Desk', 'icon' => 'healing', 'note' => 'Track interventions and incidents'],
                ['href' => 'medications.php', 'label' => 'Medication Log', 'icon' => 'medication', 'note' => 'Monitor treatments and stock'],
                ['href' => 'reports.php', 'label' => 'Health Reports', 'icon' => 'summarize', 'note' => 'Document clinic outcomes and risks'],
            ],
            'teacher' => [
                ['href' => 'my-classes.php', 'label' => 'My Classes', 'icon' => 'meeting_room', 'note' => 'Open class arms and lesson groups'],
                ['href' => 'attendance.php', 'label' => 'Mark Attendance', 'icon' => 'fact_check', 'note' => 'Capture punctuality and class presence'],
                ['href' => 'grades.php', 'label' => 'Gradebook', 'icon' => 'grading', 'note' => 'Track performance and instructional progress'],
                ['href' => 'parent-comms.php', 'label' => 'Parent Comms', 'icon' => 'family_restroom', 'note' => 'Coordinate communication with guardians'],
            ],
            'student' => [
                ['href' => 'checkin.php', 'label' => 'Check-In', 'icon' => 'fingerprint', 'note' => 'Confirm attendance and daily presence'],
                ['href' => 'assignments.php', 'label' => 'Assignments', 'icon' => 'assignment', 'note' => 'Review active academic work'],
                ['href' => 'grades.php', 'label' => 'Grades', 'icon' => 'trending_up', 'note' => 'Track performance and progress'],
                ['href' => 'wallet.php', 'label' => 'Private Points', 'icon' => 'account_balance_wallet', 'note' => 'Review merit-linked wallet balance'],
            ],
            'parent' => [
                ['href' => 'children.php', 'label' => 'Children Overview', 'icon' => 'child_care', 'note' => 'See all linked children at a glance'],
                ['href' => 'attendance.php', 'label' => 'Attendance Watch', 'icon' => 'fact_check', 'note' => 'Track daily punctuality and absences'],
                ['href' => 'fees.php', 'label' => 'Fees', 'icon' => 'account_balance_wallet', 'note' => 'Review school finance obligations'],
                ['href' => 'my-meetings.php', 'label' => 'Meetings', 'icon' => 'calendar_month', 'note' => 'Manage scheduled school conversations'],
            ],
            'librarian' => [
                ['href' => 'books.php', 'label' => 'Book Catalog', 'icon' => 'menu_book', 'note' => 'Oversee titles and availability'],
                ['href' => 'issue-return.php', 'label' => 'Issue / Return', 'icon' => 'swap_horiz', 'note' => 'Process circulation in one place'],
                ['href' => 'overdue.php', 'label' => 'Overdue Queue', 'icon' => 'warning', 'note' => 'Recover delayed resources quickly'],
                ['href' => 'reports.php', 'label' => 'Library Reports', 'icon' => 'bar_chart', 'note' => 'Review circulation trends and stock health'],
            ],
            'bursar' => [
                ['href' => 'fee-collection.php', 'label' => 'Fee Collection', 'icon' => 'point_of_sale', 'note' => 'Capture payments and issue receipts'],
                ['href' => 'invoices.php', 'label' => 'Invoices', 'icon' => 'receipt_long', 'note' => 'Track what is due and billed'],
                ['href' => 'defaulters.php', 'label' => 'Defaulters', 'icon' => 'person_off', 'note' => 'Watch overdue fee accounts'],
                ['href' => 'reports.php', 'label' => 'Financial Reports', 'icon' => 'bar_chart', 'note' => 'Review finance performance quickly'],
            ],
            'accountant' => [
                ['href' => 'ledger.php', 'label' => 'General Ledger', 'icon' => 'menu_book', 'note' => 'Review core financial journal flows'],
                ['href' => 'wallets.php', 'label' => 'Private Points Wallets', 'icon' => 'account_balance_wallet', 'note' => 'Track NGN-denominated internal balances'],
                ['href' => 'audit-trail.php', 'label' => 'Audit Trail', 'icon' => 'history', 'note' => 'Inspect money-impacting events and adjustments'],
                ['href' => 'reports.php', 'label' => 'Finance Reports', 'icon' => 'bar_chart', 'note' => 'See executive-grade summaries and trends'],
            ],
            'transport' => [
                ['href' => 'routes.php', 'label' => 'Routes', 'icon' => 'route', 'note' => 'Manage route design and assignment'],
                ['href' => 'vehicles.php', 'label' => 'Vehicles', 'icon' => 'directions_bus', 'note' => 'Keep fleet assets visible and current'],
                ['href' => 'trip-logs.php', 'label' => 'Trip Logs', 'icon' => 'list_alt', 'note' => 'Review daily transport execution'],
                ['href' => 'reports.php', 'label' => 'Transport Reports', 'icon' => 'bar_chart', 'note' => 'Monitor fleet reliability and service'],
            ],
            'forum_moderator' => [
                ['href' => 'threads.php', 'label' => 'Threads', 'icon' => 'forum', 'note' => 'Open the active forum stream'],
                ['href' => 'reported-posts.php', 'label' => 'Reported Posts', 'icon' => 'flag', 'note' => 'Investigate flagged discussions quickly'],
                ['href' => 'user-warnings.php', 'label' => 'User Warnings', 'icon' => 'report', 'note' => 'Track moderation actions'],
                ['href' => 'analytics.php', 'label' => 'Forum Analytics', 'icon' => 'trending_up', 'note' => 'Measure community health and risk'],
            ],
        ];
    }
}

if (!function_exists('sams_dashboard_filter_actions')) {
    function sams_dashboard_filter_actions($folder, array $items)
    {
        $frontendRoot = dirname(__DIR__);
        $roleRoot = $frontendRoot . DIRECTORY_SEPARATOR . $folder;
        $visible = [];

        foreach ($items as $item) {
            $href = (string)$item['href'];
            $exists = false;

            if (strpos($href, 'http') === 0 || strpos($href, '#') === 0) {
                $exists = true;
            } else {
                $path = $roleRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $href);
                if (strpos($href, '../') === 0) {
                    $path = dirname($roleRoot) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, substr($href, 3));
                }
                $exists = is_file($path);
            }

            if ($exists) {
                $visible[] = $item;
            }
        }

        return array_slice($visible, 0, 4);
    }
}

if (!function_exists('sams_dashboard_base_context')) {
    function sams_dashboard_base_context($role, $userId, $tenantId)
    {
        $studentCount = sams_dashboard_count('students', $tenantId, function_exists('table_has_column') && table_has_column('students', 'is_active') ? 'is_active = 1' : '1=1');
        $classCount = sams_dashboard_count('classes', $tenantId, function_exists('table_has_column') && table_has_column('classes', 'is_active') ? 'is_active = 1' : '1=1');
        $staffCount = sams_dashboard_count('users', $tenantId, "role IN ('teacher','staff','admin','principal','owner','accountant','bursar','librarian','nurse','transport')");
        $todayAttendance = 0;

        if (sams_dashboard_has_table('attendance')) {
            $todayAttendance = sams_dashboard_count('attendance', $tenantId, 'date = ?', [date('Y-m-d')]);
        } elseif (sams_dashboard_has_table('attendance_records')) {
            $todayAttendance = (int)sams_dashboard_query_value(
                "SELECT COUNT(*) AS aggregate_total FROM attendance_records WHERE DATE(check_in_time) = ?",
                [date('Y-m-d')],
                'aggregate_total',
                0
            );
        }

        return [
            'studentCount' => $studentCount,
            'classCount' => $classCount,
            'staffCount' => $staffCount,
            'todayAttendance' => $todayAttendance,
            'recentActivity' => sams_dashboard_recent_activity($tenantId),
            'schoolName' => $_SESSION['tenant_name'] ?? 'Academic Sentinel School',
            'userName' => trim((string)($_SESSION['full_name'] ?? 'User')),
            'roleName' => sams_dashboard_role_name($role),
        ];
    }
}

if (!function_exists('sams_dashboard_role_context')) {
    function sams_dashboard_role_context($role, $userId, $tenantId)
    {
        $base = sams_dashboard_base_context($role, $userId, $tenantId);

        $contexts = [
            'admin' => [
                'title' => 'Admin Command Center',
                'icon' => 'dashboard',
                'subtitle' => 'Run admissions, attendance, invites, and merit operations from one clean command surface.',
                'summary' => 'School-first operations stay centralized here: people, structure, attendance, finance touchpoints, and enforcement-ready oversight.',
                'metrics' => [
                    sams_dashboard_metric('Students', number_format($base['studentCount']), 'Currently active in this tenant', 'school'),
                    sams_dashboard_metric('Classes', number_format($base['classCount']), 'Class-arms under active management', 'meeting_room', 'emerald'),
                    sams_dashboard_metric('Staff Access', number_format($base['staffCount']), 'Operational accounts across the school', 'groups', 'amber'),
                    sams_dashboard_metric('Today\'s Attendance Events', number_format($base['todayAttendance']), 'Live daily attendance signal', 'fact_check', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Enrollment and Access', 'School-first onboarding stays controlled through invites, approvals, and tenant-safe account creation.', 'how_to_reg', 'indigo'),
                    sams_dashboard_panel('Merit Governance', 'Class Points and Private Points should remain auditable, reversible, and human-approved at every critical step.', 'leaderboard', 'emerald'),
                    sams_dashboard_panel('Operational Discipline', 'Use this role to keep attendance, class structure, and approvals clean enough for later automation.', 'shield', 'amber'),
                ],
            ],
            'owner' => [
                'title' => 'Owner Control Room',
                'icon' => 'shield',
                'subtitle' => 'Executive oversight for platform continuity, institutional posture, and high-impact decisions.',
                'summary' => 'This role watches the entire school system from the top without losing visibility into real operational pressure.',
                'metrics' => [
                    sams_dashboard_metric('School Population', number_format($base['studentCount']), 'Students currently onboarded', 'groups'),
                    sams_dashboard_metric('Leadership Surface', number_format($base['staffCount']), 'Accounts influencing delivery', 'manage_accounts', 'emerald'),
                    sams_dashboard_metric('Classes Live', number_format($base['classCount']), 'Active learning structures', 'meeting_room', 'amber'),
                    sams_dashboard_metric('Daily Pulse', number_format($base['todayAttendance']), 'Attendance records today', 'monitor_heart', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Executive Oversight', 'Track institutional health, user governance, finance posture, and resilience from a single shell.', 'visibility', 'indigo'),
                    sams_dashboard_panel('Continuity', 'Backups, exports, and health checks are not side chores here; they are part of school reliability.', 'cloud_upload', 'emerald'),
                    sams_dashboard_panel('Platform Direction', 'Use this role for decisions that shape the school operating model, not just isolated page edits.', 'flag', 'amber'),
                ],
            ],
            'principal' => [
                'title' => 'Principal Overview',
                'icon' => 'workspace_premium',
                'subtitle' => 'Lead academics, discipline, and school performance without wading through disconnected screens.',
                'summary' => 'The principal view focuses on academic signal, class health, attendance pressure, and intervention timing.',
                'metrics' => [
                    sams_dashboard_metric('Students', number_format($base['studentCount']), 'School population in view', 'school'),
                    sams_dashboard_metric('Active Classes', number_format($base['classCount']), 'Teaching groups in circulation', 'meeting_room', 'emerald'),
                    sams_dashboard_metric('Faculty Footprint', number_format($base['staffCount']), 'People supporting academic delivery', 'person', 'amber'),
                    sams_dashboard_metric('Attendance Signal', number_format($base['todayAttendance']), 'Today\'s captured attendance events', 'fact_check', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Academic Quality', 'Use this role to spot where attendance, performance, or class structure needs intervention first.', 'bar_chart', 'indigo'),
                    sams_dashboard_panel('Discipline and Merit', 'Special exams, class points, and behavior-linked controls should feel supervised, not invisible.', 'gavel', 'emerald'),
                    sams_dashboard_panel('School Rhythm', 'The principal dashboard should keep the school calm, current, and hard to derail.', 'event_available', 'amber'),
                ],
            ],
            'staff' => [
                'title' => 'Staff Operations Desk',
                'icon' => 'badge',
                'subtitle' => 'A clean daily workspace for operational tasks, notices, and support work.',
                'summary' => 'This dashboard is built for execution: what needs attention now, where to go next, and how to stay aligned.',
                'metrics' => [
                    sams_dashboard_metric('Open Tasks', number_format(sams_dashboard_count('tasks', $tenantId, function_exists('table_has_column') && table_has_column('tasks', 'status') ? "status NOT IN ('completed','closed')" : '1=1')), 'Pending operational work', 'task_alt'),
                    sams_dashboard_metric('Support Cases', number_format(sams_dashboard_count('student_support_cases', $tenantId)), 'Student-facing support items', 'support_agent', 'emerald'),
                    sams_dashboard_metric('School Notices', number_format(sams_dashboard_count('notices', $tenantId)), 'Active institution notices', 'campaign', 'amber'),
                    sams_dashboard_metric('Today\'s Attendance', number_format($base['todayAttendance']), 'Shared daily school signal', 'fact_check', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Execution First', 'Staff pages should reduce wandering: task, support, report, then move.', 'bolt', 'indigo'),
                    sams_dashboard_panel('School Alignment', 'Notices and messages keep this role in sync with academic and administrative direction.', 'forum', 'emerald'),
                    sams_dashboard_panel('Low Friction', 'The system should feel lighter here than it used to, because operational roles need speed more than ornament.', 'speed', 'amber'),
                ],
            ],
            'nurse' => [
                'title' => 'Clinic and Wellness Desk',
                'icon' => 'medical_services',
                'subtitle' => 'Track student health, incidents, medications, and clinic readiness from one medical workflow.',
                'summary' => 'This role keeps school health services visible, documented, and ready for both routine care and urgent response.',
                'metrics' => [
                    sams_dashboard_metric('Health Records', number_format(sams_dashboard_count('health_records', $tenantId)), 'Stored student health profiles', 'folder_shared'),
                    sams_dashboard_metric('Clinic Cases', number_format(sams_dashboard_count('clinic_visits', $tenantId)), 'Recorded visits and interventions', 'healing', 'emerald'),
                    sams_dashboard_metric('Medication Entries', number_format(sams_dashboard_count('medications', $tenantId)), 'Medication logs and stock items', 'medication', 'amber'),
                    sams_dashboard_metric('Risk Signals', number_format(sams_dashboard_count('incident_reports', $tenantId)), 'Health-related incident records', 'warning', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Student Safety', 'Clinic work should stay clear, documented, and searchable during pressure moments.', 'favorite', 'indigo'),
                    sams_dashboard_panel('Medical Continuity', 'Health records, medications, and first aid activity belong in one coherent medical surface.', 'vaccines', 'emerald'),
                    sams_dashboard_panel('Quiet Confidence', 'A nurse dashboard should feel steady and dependable, not noisy.', 'health_and_safety', 'amber'),
                ],
            ],
            'teacher' => [
                'title' => 'Teacher Teaching Surface',
                'icon' => 'co_present',
                'subtitle' => 'Stay on top of classes, attendance, grade flow, and parent communication without fighting the interface.',
                'summary' => 'Teachers need speed, clarity, and direct access to the academic work that matters this period.',
                'metrics' => [
                    sams_dashboard_metric('My Classes', number_format((int)sams_dashboard_query_value("SELECT COUNT(*) AS aggregate_total FROM classes WHERE class_teacher_id = ?", [(int)$userId], 'aggregate_total', 0)), 'Classes directly assigned to you', 'meeting_room'),
                    sams_dashboard_metric('Linked Students', number_format((int)sams_dashboard_query_value("SELECT COUNT(DISTINCT ce.student_id) AS aggregate_total FROM class_enrollments ce JOIN classes c ON c.id = ce.class_id WHERE c.class_teacher_id = ?", [(int)$userId], 'aggregate_total', 0)), 'Students in your class roster', 'school', 'emerald'),
                    sams_dashboard_metric('Assignments', number_format(sams_dashboard_count('assignments', $tenantId, function_exists('table_has_column') && table_has_column('assignments', 'created_by') ? 'created_by = ?' : '1=1', function_exists('table_has_column') && table_has_column('assignments', 'created_by') ? [(int)$userId] : [])), 'Assignment records available', 'assignment', 'amber'),
                    sams_dashboard_metric('Today\'s Attendance', number_format($base['todayAttendance']), 'Current school attendance signal', 'fact_check', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Instructional Focus', 'Attendance, grading, and parent comms are grouped here to match how teaching work actually unfolds.', 'auto_stories', 'indigo'),
                    sams_dashboard_panel('Student Signal', 'Use attendance and grade movement as early indicators before learners drift.', 'trending_up', 'emerald'),
                    sams_dashboard_panel('Reduced Friction', 'This rebuild puts the teacher role back on classroom work instead of interface recovery.', 'design_services', 'amber'),
                ],
            ],
            'student' => [
                'title' => 'Student Home',
                'icon' => 'school',
                'subtitle' => 'A focused personal space for attendance, class rhythm, assignments, results, and private points.',
                'summary' => 'Students should see what matters right now: attendance standing, active classes, wallet position, and the next academic move.',
                'metrics' => [
                    sams_dashboard_metric('Attendance Records', number_format((int)sams_dashboard_query_value("SELECT COUNT(*) AS aggregate_total FROM attendance_records WHERE student_id = ?", [(int)$userId], 'aggregate_total', 0)), 'Total captured attendance entries', 'fact_check'),
                    sams_dashboard_metric('My Classes', number_format((int)sams_dashboard_query_value("SELECT COUNT(*) AS aggregate_total FROM class_enrollments WHERE student_id = ?", [(int)$userId], 'aggregate_total', 0)), 'Current class registrations', 'meeting_room', 'emerald'),
                    sams_dashboard_metric('Private Points', sams_dashboard_ngn(sams_dashboard_query_value("SELECT current_balance FROM private_point_accounts WHERE user_id = ? OR student_id = ? ORDER BY id DESC LIMIT 1", [(int)$userId, (int)$userId], 'current_balance', 0)), 'Internal merit-linked wallet balance', 'account_balance_wallet', 'amber'),
                    sams_dashboard_metric('Special Exam Outcomes', number_format((int)sams_dashboard_query_value("SELECT COUNT(*) AS aggregate_total FROM special_exam_outcomes WHERE student_id = ?", [(int)$userId], 'aggregate_total', 0)), 'Recorded outcomes in the merit system', 'emoji_events', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Your Standing', 'This role should make progress, absence pressure, and merit position easy to understand.', 'query_stats', 'indigo'),
                    sams_dashboard_panel('Private Points', 'Class points feed the student wallet through controlled monthly snapshots and transparent ledger entries.', 'savings', 'emerald'),
                    sams_dashboard_panel('Next Move', 'Check in, finish assignments, review grades, and stay difficult to catch off guard.', 'rocket_launch', 'amber'),
                ],
            ],
            'parent' => [
                'title' => 'Parent Oversight',
                'icon' => 'family_restroom',
                'subtitle' => 'See child attendance, school obligations, and school communication without unnecessary clutter.',
                'summary' => 'Parents need a calm oversight surface: children, attendance, fees, and meetings in one place.',
                'metrics' => [
                    sams_dashboard_metric('Linked Children', number_format((int)sams_dashboard_query_value("SELECT COUNT(*) AS aggregate_total FROM parent_student_links WHERE parent_id = ?", [(int)$userId], 'aggregate_total', 0)), 'Children attached to this account', 'child_care'),
                    sams_dashboard_metric('Children Present Today', number_format((int)sams_dashboard_query_value("SELECT COUNT(*) AS aggregate_total FROM attendance_records ar JOIN parent_student_links psl ON psl.student_id = ar.student_id WHERE psl.parent_id = ? AND DATE(ar.check_in_time) = ? AND ar.status IN ('present','late')", [(int)$userId, date('Y-m-d')], 'aggregate_total', 0)), 'Presence visibility for today', 'fact_check', 'emerald'),
                    sams_dashboard_metric('Outstanding Fee Items', number_format(sams_dashboard_count('fee_payments', $tenantId, function_exists('table_has_column') && table_has_column('fee_payments', 'payment_status') ? "payment_status IN ('pending','overdue')" : '1=1')), 'School receivable pressure points', 'payments', 'amber'),
                    sams_dashboard_metric('Meetings', number_format(sams_dashboard_count('meetings', $tenantId)), 'Meeting records available', 'calendar_month', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Daily Visibility', 'Parents should know quickly whether children are present, late, or drifting into risk.', 'visibility', 'indigo'),
                    sams_dashboard_panel('School Partnership', 'Communication and meetings belong close to attendance and finance because that is how real school follow-up works.', 'handshake', 'emerald'),
                    sams_dashboard_panel('Less Guesswork', 'This rebuild keeps the parent role readable even during busy school weeks.', 'checklist', 'amber'),
                ],
            ],
            'librarian' => [
                'title' => 'Library Control Desk',
                'icon' => 'menu_book',
                'subtitle' => 'Catalog, circulation, overdue recovery, and stock visibility in one steady librarian workspace.',
                'summary' => 'The library role now centers on circulation flow and collection health rather than scattered pages.',
                'metrics' => [
                    sams_dashboard_metric('Catalog Items', number_format(sams_dashboard_count('books', $tenantId) + sams_dashboard_count('library_books', $tenantId)), 'Combined book inventory across available schemas', 'library_books'),
                    sams_dashboard_metric('Active Loans', number_format(sams_dashboard_count('book_loans', $tenantId, function_exists('table_has_column') && table_has_column('book_loans', 'status') ? "status IN ('borrowed','active')" : '1=1')), 'Resources currently on loan', 'swap_horiz', 'emerald'),
                    sams_dashboard_metric('Overdue Queue', number_format(sams_dashboard_count('book_loans', $tenantId, function_exists('table_has_column') && table_has_column('book_loans', 'due_date') ? 'due_date < CURDATE()' : '1=1')), 'Loans requiring recovery', 'warning', 'amber'),
                    sams_dashboard_metric('Reservations', number_format(sams_dashboard_count('book_reservations', $tenantId)), 'Pending reservation flow', 'bookmark', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Circulation First', 'Issue, return, overdue, and fine workflows should sit close together because the work is continuous.', 'local_library', 'indigo'),
                    sams_dashboard_panel('Collection Health', 'This role needs quick signals about missing, delayed, or high-demand materials.', 'inventory_2', 'emerald'),
                    sams_dashboard_panel('Readable Inventory', 'The librarian surface should feel orderly enough to trust at a glance.', 'category', 'amber'),
                ],
            ],
            'bursar' => [
                'title' => 'Bursar Finance Desk',
                'icon' => 'payments',
                'subtitle' => 'Handle fees, invoices, payment plans, and defaulters from a cleaner bursary workflow.',
                'summary' => 'This role is focused on receivables and payment operations, distinct from the broader accountant command surface.',
                'metrics' => [
                    sams_dashboard_metric('Invoices', number_format(sams_dashboard_count('invoices', $tenantId) + sams_dashboard_count('fee_invoices', $tenantId)), 'Generated billing records', 'receipt_long'),
                    sams_dashboard_metric('Receipts', number_format(sams_dashboard_count('receipts', $tenantId)), 'Issued receipt records', 'receipt', 'emerald'),
                    sams_dashboard_metric('Pending Payments', number_format(sams_dashboard_count('fee_payments', $tenantId, function_exists('table_has_column') && table_has_column('fee_payments', 'payment_status') ? "payment_status IN ('pending','overdue')" : '1=1')), 'Items still awaiting settlement', 'schedule', 'amber'),
                    sams_dashboard_metric('Amount Collected', sams_dashboard_ngn(sams_dashboard_sum('fee_payments', 'amount', $tenantId)), 'Captured payments in the current schema', 'point_of_sale', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Collections Flow', 'Keep fee collection, invoicing, and defaulter review together so follow-up stays tight.', 'account_balance', 'indigo'),
                    sams_dashboard_panel('Student Finance Clarity', 'The bursary role should answer what was billed, what was paid, and what still needs action.', 'request_quote', 'emerald'),
                    sams_dashboard_panel('Receivable Discipline', 'This role is about consistency and traceability, not just taking payments.', 'rule', 'amber'),
                ],
            ],
            'accountant' => [
                'title' => 'Accountant Command Center',
                'icon' => 'account_balance',
                'subtitle' => 'A rebuilt finance surface for ledgers, private points, monthly runs, and audit-grade money visibility.',
                'summary' => 'This UI is now aligned with the rest of SAMS while preserving accountant-specific visibility into journal flows and internal wallets.',
                'metrics' => [
                    sams_dashboard_metric('Ledger Entries', number_format(sams_dashboard_count('private_point_ledger', $tenantId) + sams_dashboard_count('class_point_ledger', $tenantId)), 'Merit and wallet ledgers across the tenant', 'menu_book'),
                    sams_dashboard_metric('Wallet Balances', sams_dashboard_ngn(sams_dashboard_sum('private_point_accounts', 'current_balance', $tenantId)), 'Total internal NGN wallet exposure', 'account_balance_wallet', 'emerald'),
                    sams_dashboard_metric('Monthly Runs', number_format(sams_dashboard_count('monthly_allowance_runs', $tenantId)), 'Allowance snapshots and credit runs', 'calendar_month', 'amber'),
                    sams_dashboard_metric('Audit Events', number_format(sams_dashboard_count('enforcement_actions', $tenantId) + sams_dashboard_count('audit_logs', $tenantId)), 'Finance-adjacent control records', 'history', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Money With Context', 'Private Points live beside accounting visibility without replacing school receivables.', 'savings', 'indigo'),
                    sams_dashboard_panel('Auditability', 'Ledger-backed balances, monthly runs, and reversals need to stay visible and deterministic.', 'verified', 'emerald'),
                    sams_dashboard_panel('Consistent UI', 'This accountant rebuild now speaks the same design language as the rest of the role system.', 'dashboard_customize', 'amber'),
                ],
            ],
            'transport' => [
                'title' => 'Transport Operations Deck',
                'icon' => 'directions_bus',
                'subtitle' => 'Routes, vehicles, trip execution, and fleet reliability in one transport-first workspace.',
                'summary' => 'Transport teams need fleet clarity, assignment awareness, and a clean operational loop.',
                'metrics' => [
                    sams_dashboard_metric('Routes', number_format(sams_dashboard_count('transport_routes', $tenantId) + sams_dashboard_count('routes', $tenantId)), 'Configured transport routes', 'route'),
                    sams_dashboard_metric('Vehicles', number_format(sams_dashboard_count('vehicles', $tenantId)), 'Fleet assets in the system', 'directions_bus', 'emerald'),
                    sams_dashboard_metric('Trip Logs', number_format(sams_dashboard_count('trip_logs', $tenantId)), 'Recorded route execution entries', 'list_alt', 'amber'),
                    sams_dashboard_metric('Maintenance Items', number_format(sams_dashboard_count('maintenance_logs', $tenantId) + sams_dashboard_count('vehicle_maintenance', $tenantId)), 'Fleet upkeep records', 'build', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Fleet Rhythm', 'Transport operations should read like a working schedule, not a broken filing cabinet.', 'travel', 'indigo'),
                    sams_dashboard_panel('Student Movement', 'Routes and allocations matter because students experience the school day before first period begins.', 'commute', 'emerald'),
                    sams_dashboard_panel('Reliability', 'Vehicles, maintenance, and trip logs belong in one glanceable system.', 'construction', 'amber'),
                ],
            ],
            'forum_moderator' => [
                'title' => 'Forum Moderation Desk',
                'icon' => 'forum',
                'subtitle' => 'Moderate threads, reported posts, warnings, and community health through one clean moderation surface.',
                'summary' => 'This role focuses on discourse quality, risk control, and transparent moderation visibility.',
                'metrics' => [
                    sams_dashboard_metric('Threads', number_format(sams_dashboard_count('forum_threads', $tenantId)), 'Open discussion threads', 'forum'),
                    sams_dashboard_metric('Reported Posts', number_format(sams_dashboard_count('reported_posts', $tenantId)), 'Items waiting for moderation attention', 'flag', 'emerald'),
                    sams_dashboard_metric('Warnings', number_format(sams_dashboard_count('user_warnings', $tenantId)), 'Issued moderation warnings', 'report', 'amber'),
                    sams_dashboard_metric('Banned Users', number_format(sams_dashboard_count('banned_users', $tenantId)), 'Restricted community accounts', 'person_off', 'rose'),
                ],
                'panels' => [
                    sams_dashboard_panel('Community Health', 'Moderation should feel deliberate, reviewable, and difficult to game.', 'shield', 'indigo'),
                    sams_dashboard_panel('Risk Control', 'Reported content and user warnings need visibility before small issues become culture problems.', 'gpp_bad', 'emerald'),
                    sams_dashboard_panel('Steady Judgment', 'This dashboard should help a moderator act calmly and consistently.', 'balance', 'amber'),
                ],
            ],
        ];

        $context = $contexts[$role] ?? $contexts['admin'];
        $actions = sams_dashboard_action_candidates();
        $folder = sams_dashboard_role_folder($role);
        $context['actions'] = sams_dashboard_filter_actions($folder, $actions[$role] ?? []);
        $context['activity'] = $base['recentActivity'];
        $context['schoolName'] = $base['schoolName'];
        $context['userName'] = $base['userName'];
        $context['roleName'] = $base['roleName'];
        return $context;
    }
}

if (!function_exists('sams_dashboard_require_roles')) {
    function sams_dashboard_require_roles(array $allowedRoles)
    {
        require_login('../login.php');
        $activeRole = strtolower((string)($_SESSION['role'] ?? ($_SESSION['user_role'] ?? '')));
        $allowed = array_map('strtolower', $allowedRoles);
        if (!in_array($activeRole, $allowed, true)) {
            redirect('../login.php', 'Role access required.', 'error');
        }
        return $activeRole;
    }
}

if (!function_exists('sams_render_role_dashboard')) {
    function sams_render_role_dashboard(array $options)
    {
        $role = sams_dashboard_require_roles($options['allowed_roles']);
        $roleKey = $options['role_key'] ?? $role;
        $tenantId = $_SESSION['tenant_id'] ?? ($_SESSION['school_id'] ?? 1);
        $userId = $_SESSION['user_id'] ?? 0;
        $context = sams_dashboard_role_context($roleKey, $userId, $tenantId);

        $page_title = $context['title'];
        $page_icon = $context['icon'];
        $page_subtitle = $context['subtitle'];

        ob_start();
        ?>
        <div class="grid grid-cols-12 gap-6">
          <section class="col-span-12 rounded-2xl border border-slate-200 bg-gradient-to-br from-primary via-primary-container to-slate-900 px-6 py-7 text-white shadow-sm">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
              <div class="max-w-3xl">
                <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-100">
                  <span class="material-symbols-outlined text-base"><?php echo sams_dashboard_escape($context['icon']); ?></span>
                  <?php echo sams_dashboard_escape($context['roleName']); ?> Surface
                </div>
                <h3 class="font-headline text-3xl font-extrabold leading-tight"><?php echo sams_dashboard_escape($context['title']); ?></h3>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-indigo-100/90"><?php echo sams_dashboard_escape($context['summary']); ?></p>
              </div>
              <div class="grid min-w-[280px] gap-3 md:grid-cols-3 lg:w-[420px]">
                <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                  <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-100/80">School</div>
                  <div class="mt-2 text-sm font-bold"><?php echo sams_dashboard_escape($context['schoolName']); ?></div>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                  <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-100/80">Operator</div>
                  <div class="mt-2 text-sm font-bold"><?php echo sams_dashboard_escape($context['userName']); ?></div>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                  <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-indigo-100/80">Mode</div>
                  <div class="mt-2 text-sm font-bold">Shared role rebuild</div>
                </div>
              </div>
            </div>
          </section>

          <section class="col-span-12 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($context['metrics'] as $metric): ?>
              <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400"><?php echo sams_dashboard_escape($metric['label']); ?></div>
                    <div class="mt-3 font-headline text-3xl font-extrabold text-slate-900"><?php echo sams_dashboard_escape($metric['value']); ?></div>
                    <p class="mt-2 text-sm leading-6 text-slate-500"><?php echo sams_dashboard_escape($metric['note']); ?></p>
                  </div>
                  <div class="rounded-xl bg-slate-100 p-3 text-slate-700">
                    <span class="material-symbols-outlined text-[22px]"><?php echo sams_dashboard_escape($metric['icon']); ?></span>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </section>

          <section class="col-span-12 xl:col-span-7 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex items-center justify-between gap-4">
              <div>
                <h3 class="font-headline text-xl font-extrabold text-slate-900">Operational launchpad</h3>
                <p class="mt-1 text-sm text-slate-500">The cleanest next moves for this role, filtered to pages that actually exist.</p>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <?php if (!empty($context['actions'])): ?>
                <?php foreach ($context['actions'] as $action): ?>
                  <a href="<?php echo sams_dashboard_escape($action['href']); ?>" class="group rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-primary/20 hover:bg-white hover:shadow-sm">
                    <div class="flex items-start gap-4">
                      <div class="rounded-xl bg-white p-3 text-primary shadow-sm ring-1 ring-slate-200">
                        <span class="material-symbols-outlined text-[22px]"><?php echo sams_dashboard_escape($action['icon']); ?></span>
                      </div>
                      <div class="min-w-0">
                        <div class="flex items-center gap-2">
                          <h4 class="font-headline text-base font-bold text-slate-900"><?php echo sams_dashboard_escape($action['label']); ?></h4>
                          <span class="material-symbols-outlined text-base text-slate-400 transition group-hover:text-primary">arrow_forward</span>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-500"><?php echo sams_dashboard_escape($action['note']); ?></p>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500 md:col-span-2">
                  Role pages are being normalized. The dashboard shell is ready, and more operational pages can be attached cleanly from here.
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="col-span-12 xl:col-span-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-headline text-xl font-extrabold text-slate-900">Live activity</h3>
            <p class="mt-1 text-sm text-slate-500">Recent signals from the school environment and the rebuilt platform surface.</p>
            <div class="mt-5 space-y-4">
              <?php foreach ($context['activity'] as $item): ?>
                <div class="flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
                  <div class="mt-1 h-2.5 w-2.5 flex-shrink-0 rounded-full bg-emerald-500"></div>
                  <div class="min-w-0">
                    <div class="text-sm font-semibold text-slate-900"><?php echo sams_dashboard_escape($item['title']); ?></div>
                    <div class="mt-1 text-xs text-slate-500"><?php echo sams_dashboard_escape($item['meta']); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="col-span-12 grid gap-4 lg:grid-cols-3">
            <?php foreach ($context['panels'] as $panel): ?>
              <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-3 text-slate-700">
                  <span class="material-symbols-outlined text-[22px]"><?php echo sams_dashboard_escape($panel['icon']); ?></span>
                </div>
                <h3 class="font-headline text-lg font-extrabold text-slate-900"><?php echo sams_dashboard_escape($panel['title']); ?></h3>
                <p class="mt-3 text-sm leading-7 text-slate-500"><?php echo sams_dashboard_escape($panel['body']); ?></p>
              </article>
            <?php endforeach; ?>
          </section>
        </div>
        <?php
        $page_content = ob_get_clean();
        require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
    }
}
