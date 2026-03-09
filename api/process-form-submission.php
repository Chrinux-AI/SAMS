<?php
/**
 * Process Google Form / external form submissions for AI onboarding.
 * Expected JSON payload:
 * {
 *   "source": "google_form",
 *   "records": [ ... ] OR "raw_data": "...",
 *   "admin_id": 1
 * }
 * Header:
 *   X-Attendance-Webhook-Key: <secret>
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/email-helper.php';
require_once __DIR__ . '/../includes/system-log.php';
require_once __DIR__ . '/../admin/ai-user-creator.php';
require_once __DIR__ . '/../includes/api-response.php';

function process_form_webhook_secret(): string
{
    $env = getenv('SAMS_WEBHOOK_KEY');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    return defined('GOOGLE_FORM_WEBHOOK_KEY') ? (string)GOOGLE_FORM_WEBHOOK_KEY : '';
}

function process_form_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$serverKey] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed', 405);
}

$secret = process_form_webhook_secret();
if ($secret === '') {
    system_log('ERROR', 'Webhook called without configured secret');
    api_error('Webhook key not configured', 500);
}

$provided = process_form_header('X-Attendance-Webhook-Key');
if (!hash_equals($secret, $provided)) {
    system_log('WARNING', 'Invalid webhook key attempt', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    api_error('Unauthorized', 401);
}

$body = file_get_contents('php://input') ?: '';
if ($body === '') {
    api_error('Empty payload', 400);
}

$payload = json_decode($body, true);
if (!is_array($payload)) {
    api_error('Payload must be valid JSON', 400);
}

$source = trim((string)($payload['source'] ?? 'google_form'));
$rawData = trim((string)($payload['raw_data'] ?? ''));
$adminId = (int)($payload['admin_id'] ?? 0);

if ($rawData === '' && isset($payload['records']) && is_array($payload['records'])) {
    $rawData = json_encode($payload['records']);
}

if ($rawData === '') {
    api_error('raw_data or records is required', 422);
}

try {
    $creator = new AI_User_Creator();
    $result = $creator->processRaw($rawData, $adminId);

    system_log('INFO', 'AI form submission processed', [
        'source' => $source,
        'created' => count($result['created']),
        'failed' => count($result['failed']),
        'parse_errors' => count($result['parse_errors'])
    ]);

    api_success([
        'source' => $source,
        'created' => $result['created'],
        'failed' => $result['failed'],
        'parse_errors' => $result['parse_errors']
    ]);
} catch (Throwable $e) {
    system_log('ERROR', 'AI form submission failed', [
        'source' => $source,
        'message' => $e->getMessage()
    ]);
    api_error('Processing failed', 500);
}

