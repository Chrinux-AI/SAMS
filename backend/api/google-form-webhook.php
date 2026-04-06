<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/email-helper.php';
require_once __DIR__ . '/../admin/ai-user-creator.php';

header('Content-Type: application/json');

function webhook_json(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function get_webhook_secret(): string
{
    $env = getenv('SAMS_WEBHOOK_KEY');
    if (is_string($env) && $env !== '') {
        return $env;
    }

    if (defined('GOOGLE_FORM_WEBHOOK_KEY')) {
        return (string)GOOGLE_FORM_WEBHOOK_KEY;
    }

    return '';
}

function get_request_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$serverKey] ?? ''));
}

function ensure_google_form_submissions_table(): void
{
    if (table_exists('google_form_submissions')) {
        return;
    }

    db()->query("
        CREATE TABLE google_form_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(100) NULL,
            raw_data LONGTEXT NULL,
            extracted_data LONGTEXT NULL,
            processing_status VARCHAR(40) NOT NULL DEFAULT 'pending',
            created_user_id INT NULL,
            processed_by INT NULL,
            processed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_processing_status (processing_status),
            INDEX idx_created_user_id (created_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhook_json(405, ['success' => false, 'error' => 'Method not allowed']);
}

$secret = get_webhook_secret();
if ($secret === '') {
    webhook_json(500, ['success' => false, 'error' => 'Webhook key not configured']);
}

$provided = get_request_header('X-Attendance-Webhook-Key');
if (!hash_equals($secret, $provided)) {
    webhook_json(401, ['success' => false, 'error' => 'Invalid webhook key']);
}

$rawBody = file_get_contents('php://input') ?: '';
if ($rawBody === '') {
    webhook_json(400, ['success' => false, 'error' => 'Empty payload']);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    webhook_json(400, ['success' => false, 'error' => 'Payload must be JSON']);
}

$source = trim((string)($payload['source'] ?? 'google_form'));
$rawData = trim((string)($payload['raw_data'] ?? ''));
$adminId = (int)($payload['admin_id'] ?? 0);

if ($rawData === '' && isset($payload['records']) && is_array($payload['records'])) {
    $rawData = json_encode($payload['records']);
}

if ($rawData === '') {
    webhook_json(400, ['success' => false, 'error' => 'raw_data or records is required']);
}

ensure_google_form_submissions_table();

try {
    $creator = new AI_User_Creator();
    $result = $creator->processRaw($rawData, $adminId);

    insert_flexible('google_form_submissions', [
        'source' => $source,
        'raw_data' => $rawData,
        'extracted_data' => json_encode([
            'created_count' => count($result['created']),
            'failed_count' => count($result['failed']),
            'parse_errors' => $result['parse_errors'],
        ]),
        'processing_status' => empty($result['failed']) ? 'completed' : 'partial',
        'processed_by' => $adminId > 0 ? $adminId : null,
        'processed_at' => date('Y-m-d H:i:s'),
    ]);

    webhook_json(200, [
        'success' => true,
        'created' => $result['created'],
        'failed' => $result['failed'],
        'parse_errors' => $result['parse_errors'],
    ]);
} catch (Throwable $e) {
    webhook_json(500, ['success' => false, 'error' => $e->getMessage()]);
}

