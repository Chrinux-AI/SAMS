<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api-response.php';
require_once __DIR__ . '/../includes/advanced-sams.php';

api_require_auth();

[$accessAllowed, $accessMessage] = AdvancedSAMS::userCanAccess();
if (!$accessAllowed) {
    api_error($accessMessage ?? 'Access denied', 403);
}

$tenantId = AdvancedSAMS::currentTenantId();
if (!$tenantId) {
    api_error('Tenant context could not be resolved', 422);
}

$action = trim((string) ($_REQUEST['action'] ?? 'overview'));
$actorId = (int) ($_SESSION['user_id'] ?? 0);

try {
    switch ($action) {
        case 'overview':
            $classRanking = AdvancedSAMS::tableExists('class_point_accounts')
                ? db()->fetchAll(
                    "SELECT cpa.*, c.class_name
                     FROM class_point_accounts cpa
                     LEFT JOIN classes c ON c.id = cpa.class_id
                     WHERE cpa.tenant_id = ?
                     ORDER BY cpa.current_balance DESC, cpa.updated_at DESC
                     LIMIT 10",
                    [$tenantId]
                )
                : [];

            $recentMeritEvents = AdvancedSAMS::tableExists('merit_events')
                ? db()->fetchAll(
                    "SELECT *
                     FROM merit_events
                     WHERE tenant_id = ?
                     ORDER BY created_at DESC
                     LIMIT 10",
                    [$tenantId]
                )
                : [];

            $recentEnforcement = AdvancedSAMS::tableExists('enforcement_actions')
                ? db()->fetchAll(
                    "SELECT *
                     FROM enforcement_actions
                     WHERE tenant_id = ?
                     ORDER BY created_at DESC
                     LIMIT 10",
                    [$tenantId]
                )
                : [];

            api_success([
                'tenant_id' => $tenantId,
                'class_ranking' => $classRanking,
                'recent_merit_events' => $recentMeritEvents,
                'recent_enforcement_actions' => $recentEnforcement
            ]);
            break;

        case 'post_class_points':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            $ledgerId = AdvancedSAMS::postClassPointLedger([
                'tenant_id' => $tenantId,
                'class_id' => (int) ($_POST['class_id'] ?? 0),
                'academic_session' => trim((string) ($_POST['academic_session'] ?? date('Y') . '/' . (date('Y') + 1))),
                'academic_term' => trim((string) ($_POST['academic_term'] ?? 'Term 1')),
                'source_type' => trim((string) ($_POST['source_type'] ?? 'manual')),
                'rule_code' => trim((string) ($_POST['rule_code'] ?? 'manual_adjustment')),
                'delta' => (int) ($_POST['delta'] ?? 0),
                'actor_id' => $actorId,
                'reason' => trim((string) ($_POST['reason'] ?? 'Manual class-point adjustment')),
                'correlation_key' => trim((string) ($_POST['correlation_key'] ?? ('manual:' . $tenantId . ':' . uniqid('', true))))
            ]);

            api_success(['ledger_id' => $ledgerId], 201);
            break;

        case 'run_monthly_allowance':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            $result = AdvancedSAMS::runMonthlyAllowance(
                $tenantId,
                (int) ($_POST['class_id'] ?? 0),
                trim((string) ($_POST['academic_session'] ?? date('Y') . '/' . (date('Y') + 1))),
                trim((string) ($_POST['academic_term'] ?? 'Term 1')),
                trim((string) ($_POST['run_month'] ?? date('Y-m'))),
                $actorId
            );

            api_success($result, 201);
            break;

        case 'wallet_summary':
            $studentId = (int) ($_GET['student_id'] ?? 0);
            if ($studentId <= 0) {
                api_error('student_id is required', 422);
            }

            $account = AdvancedSAMS::tableExists('private_point_accounts')
                ? db()->fetchOne(
                    'SELECT * FROM private_point_accounts WHERE tenant_id = ? AND student_id = ? LIMIT 1',
                    [$tenantId, $studentId]
                )
                : null;

            $ledger = AdvancedSAMS::tableExists('private_point_ledger')
                ? db()->fetchAll(
                    'SELECT * FROM private_point_ledger WHERE tenant_id = ? AND student_id = ? ORDER BY created_at DESC LIMIT 20',
                    [$tenantId, $studentId]
                )
                : [];

            api_success([
                'account' => $account,
                'ledger' => $ledger
            ]);
            break;

        case 'create_special_exam':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            $examId = AdvancedSAMS::createSpecialExam([
                'tenant_id' => $tenantId,
                'exam_name' => trim((string) ($_POST['exam_name'] ?? '')),
                'scope_type' => trim((string) ($_POST['scope_type'] ?? 'class')),
                'eligibility_scope' => json_decode((string) ($_POST['eligibility_scope'] ?? '[]'), true) ?: [],
                'rule_version' => trim((string) ($_POST['rule_version'] ?? 'v1')),
                'stakes_summary' => trim((string) ($_POST['stakes_summary'] ?? '')),
                'starts_at' => trim((string) ($_POST['starts_at'] ?? date('Y-m-d H:i:s'))),
                'ends_at' => trim((string) ($_POST['ends_at'] ?? date('Y-m-d H:i:s', strtotime('+7 days')))),
                'created_by' => $actorId
            ]);

            api_success(['special_exam_id' => $examId], 201);
            break;

        case 'enforce':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            $actionId = AdvancedSAMS::recordEnforcementAction([
                'tenant_id' => $tenantId,
                'student_id' => !empty($_POST['student_id']) ? (int) $_POST['student_id'] : null,
                'user_id' => !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null,
                'source_type' => trim((string) ($_POST['source_type'] ?? 'manual')),
                'source_id' => !empty($_POST['source_id']) ? (int) $_POST['source_id'] : null,
                'action_type' => trim((string) ($_POST['action_type'] ?? 'restriction')),
                'reason' => trim((string) ($_POST['reason'] ?? 'Manual enforcement action')),
                'review_notes' => trim((string) ($_POST['review_notes'] ?? '')),
                'reviewed_by' => $actorId
            ]);

            api_success(['enforcement_action_id' => $actionId], 201);
            break;

        case 'restore':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_error('Method not allowed', 405);
            }

            AdvancedSAMS::restoreEnforcementAction((int) ($_POST['action_id'] ?? 0), $actorId);
            api_success(['restored' => true]);
            break;

        default:
            api_error('Unknown merit action', 404);
    }
} catch (Throwable $e) {
    api_error($e->getMessage(), 500);
}
