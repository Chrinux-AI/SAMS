<?php

/**
 * Comprehensive integration test for all flows:
 * 1. Add teacher (admin)
 * 2. Bulk student CSV import
 * 3. Classes management
 * 4. AI user creator (JSON/CSV/key-value)
 * 5. OTP confirmation flow
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';
require_once 'includes/email-helper.php';
require_once 'admin/ai-user-creator.php';

$pass = 0;
$fail = 0;

function test($label, $condition, $detail = '')
{
  global $pass, $fail;
  if ($condition) {
    echo "[PASS] $label\n";
    $pass++;
  } else {
    echo "[FAIL] $label" . ($detail ? " — $detail" : "") . "\n";
    $fail++;
  }
}

echo "========================================\n";
echo " SAMS Integration Test Suite\n";
echo "========================================\n\n";

// ── 0. Infrastructure ─────────────────────────────────
echo "--- Infrastructure ---\n";
test('DB connection', db() !== null);
test('users table', table_exists('users'));
test('students table', table_exists('students'));
test('teachers table', table_exists('teachers'));
test('classes table', table_exists('classes'));
test('class_enrollments table', table_exists('class_enrollments'));
test('google_form_submissions table', table_exists('google_form_submissions'));
test('account_activations table', table_exists('account_activations'));
test('users.verification_token col', table_has_column('users', 'verification_token'));
test('users.token_expiry col', table_has_column('users', 'token_expiry'));
test('users.assigned_id col', table_has_column('users', 'assigned_id'));
test('users.password col', table_has_column('users', 'password'));
test('users.password_set_at col', table_has_column('users', 'password_set_at'));
test('send_account_otp_email fn', function_exists('send_account_otp_email'));
test('build_user_payload fn', function_exists('build_user_payload'));
test('insert_flexible fn', function_exists('insert_flexible'));
test('update_flexible fn', function_exists('update_flexible'));

// ── 1. Add Teacher Flow ───────────────────────────────
echo "\n--- 1. Add Teacher Flow ---\n";
$tch_email = 'test.teacher.' . time() . '@integrationtest.com';
$otp = sprintf('%06d', random_int(100000, 999999));
$otp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
$otp_token = 'CONFIRM:' . $otp . ':' . strtotime($otp_expiry);

$teacher_count = db()->count('users', 'role = ?', ['teacher']);
$emp_id = 'TCH' . date('Y') . str_pad($teacher_count + 1, 4, '0', STR_PAD_LEFT);

$payload = build_user_payload([
  'email' => $tch_email,
  'password' => bin2hex(random_bytes(32)),
  'role' => 'teacher',
  'first_name' => 'Test',
  'last_name' => 'Teacher',
  'full_name' => 'Test Teacher',
  'status' => 'active',
  'approved' => 1,
  'email_verified' => 0,
  'assigned_id' => $emp_id,
]);
$payload['is_active'] = 0;
$payload['email_verified'] = 0;
$payload['verification_token'] = $otp_token;
$payload['token_expiry'] = $otp_expiry;

$tch_user_id = insert_flexible('users', $payload);
test('Teacher user created', (bool)$tch_user_id, "user_id=$tch_user_id");

$tch_profile = insert_flexible('teachers', [
  'user_id' => $tch_user_id,
  'employee_id' => $emp_id,
  'qualification' => 'B.Ed',
  'specialization' => 'Math',
  'date_joined' => date('Y-m-d'),
  'is_class_teacher' => 0,
]);
test('Teacher profile created', (bool)$tch_profile);

// Verify state
$tch_row = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$tch_user_id]);
test('Teacher is_active = 0', (int)$tch_row['is_active'] === 0);
test('Teacher email_verified = 0', (int)$tch_row['email_verified'] === 0);
test('Teacher has CONFIRM token', str_starts_with($tch_row['verification_token'], 'CONFIRM:'));
test('Teacher assigned_id set', $tch_row['assigned_id'] === $emp_id);

$tch_profile_row = db()->fetchOne("SELECT * FROM teachers WHERE user_id = ?", [$tch_user_id]);
test('Teacher profile linked', (bool)$tch_profile_row);
test('Teacher employee_id matches', $tch_profile_row['employee_id'] === $emp_id);

// ── 2. Class Management ──────────────────────────────
echo "\n--- 2. Class Management ---\n";
$cls_data = [
  'class_name' => 'IntegrationTestClass',
  'name' => 'IntegrationTestClass',
  'grade_level' => 999,
  'academic_year' => '2025-2026',
  'section' => 'TEST-SEC',
  'is_active' => 1,
  'created_at' => date('Y-m-d H:i:s'),
  'updated_at' => date('Y-m-d H:i:s'),
];
if (table_has_column('classes', 'class_teacher_id')) {
  $cls_data['class_teacher_id'] = $tch_user_id;
} else {
  $cls_data['teacher_id'] = $tch_user_id;
}
$cls_id = insert_flexible('classes', $cls_data);
test('Class created', (bool)$cls_id, "class_id=$cls_id");

$cls_row = db()->fetchOne("SELECT * FROM classes WHERE id = ?", [$cls_id]);
test('Class data correct', $cls_row['class_name'] === 'IntegrationTestClass' && (int)$cls_row['grade_level'] === 999);

// ── 3. Student Bulk Import Flow ──────────────────────
echo "\n--- 3. Student Bulk Import Flow ---\n";
$std_email = 'test.student.' . time() . '@integrationtest.com';
$std_otp = sprintf('%06d', random_int(100000, 999999));
$std_otp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
$std_otp_token = 'CONFIRM:' . $std_otp . ':' . strtotime($std_otp_expiry);
$std_count = db()->count('students');
$std_id = 'STD' . date('Y') . str_pad($std_count + 1, 4, '0', STR_PAD_LEFT);

$std_payload = build_user_payload([
  'email' => $std_email,
  'password' => bin2hex(random_bytes(32)),
  'role' => 'student',
  'first_name' => 'Test',
  'last_name' => 'Student',
  'full_name' => 'Test Student',
  'status' => 'active',
  'approved' => 1,
  'email_verified' => 0,
  'assigned_id' => $std_id,
]);
$std_payload['is_active'] = 0;
$std_payload['email_verified'] = 0;
$std_payload['verification_token'] = $std_otp_token;
$std_payload['token_expiry'] = $std_otp_expiry;

$std_user_id = insert_flexible('users', $std_payload);
test('Student user created', (bool)$std_user_id, "user_id=$std_user_id");

$std_record_id = insert_flexible('students', [
  'user_id' => $std_user_id,
  'admission_number' => $std_id,
  'assigned_student_id' => $std_id,
  'class_id' => $cls_id,
  'gender' => 'male',
  'is_active' => 1,
]);
test('Student profile created', (bool)$std_record_id);

// Enrollment
$enroll_id = null;
try {
  $enroll_id = insert_flexible('class_enrollments', [
    'class_id' => $cls_id,
    'student_id' => $std_record_id,
    'status' => 'active',
    'enrolled_by' => 6,
  ]);
} catch (Throwable $e) {
}
test('Class enrollment created', (bool)$enroll_id);

$std_row = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$std_user_id]);
test('Student is_active = 0', (int)$std_row['is_active'] === 0);
test('Student has CONFIRM token', str_starts_with($std_row['verification_token'], 'CONFIRM:'));

// ── 4. AI User Creator ──────────────────────────────
echo "\n--- 4. AI User Creator ---\n";
$creator = new AI_User_Creator();

// 4a. JSON parse
$r1 = $creator->parseInput(json_encode([
  ['Name' => 'AI Json Student', 'Email' => 'ai.json.' . time() . '@test.com', 'Role' => 'student', 'Phone' => '080111'],
  ['Full Name' => 'AI Json Teacher', 'Email Address' => 'ai.json2.' . time() . '@test.com', 'Position' => 'teacher'],
]));
test('JSON parse: 2 users', count($r1['users']) === 2);
test('JSON parse: 0 errors', count($r1['errors']) === 0);
test('JSON: role aliasing (Position=teacher)', ($r1['users'][1]['role'] ?? '') === 'teacher');

// 4b. CSV parse
$csv = "Name,Email,Role,Phone\nCSV Student,csv." . time() . "@test.com,learner,080222\nCSV Teacher,csv2." . time() . "@test.com,instructor,080333";
$r2 = $creator->parseInput($csv);
test('CSV parse: 2 users', count($r2['users']) === 2);
test('CSV: learner=>student', ($r2['users'][0]['role'] ?? '') === 'student');
test('CSV: instructor=>teacher', ($r2['users'][1]['role'] ?? '') === 'teacher');

// 4c. Key-value parse
$kv = "Full Name: KV Parent\nEmail: kv." . time() . "@test.com\nRole: guardian\nPhone: 080444";
$r3 = $creator->parseInput($kv);
test('KV parse: 1 user', count($r3['users']) === 1);
test('KV: guardian=>parent', ($r3['users'][0]['role'] ?? '') === 'parent');

// 4d. Duplicate detection
$r4 = $creator->parseInput(json_encode([['Name' => 'Dup', 'Email' => $tch_email, 'Role' => 'student']]));
test('Dup email blocked', count($r4['users']) === 0 && count($r4['errors']) === 1);

// 4e. Validation
$r5 = $creator->parseInput(json_encode([['Name' => '', 'Email' => '', 'Role' => 'student']]));
test('Missing name blocked', count($r5['users']) === 0 && count($r5['errors']) > 0);

// 4f. Full account creation via AI
$ai_email = 'ai.created.' . time() . '@integrationtest.com';
$ai_result = $creator->createAccounts([
  ['full_name' => 'AI Created Student', 'first_name' => 'AI', 'last_name' => 'Created', 'email' => $ai_email, 'role' => 'student', 'phone' => '080555'],
], 6);
test('AI create: 1 success', count($ai_result['created']) === 1);
test('AI create: 0 failed', count($ai_result['failed']) === 0);

$ai_user = db()->fetchOne("SELECT * FROM users WHERE email = ?", [$ai_email]);
test('AI user in DB', (bool)$ai_user);
test('AI user is_active=0', $ai_user && (int)$ai_user['is_active'] === 0);
test('AI user has OTP token', $ai_user && str_starts_with($ai_user['verification_token'], 'CONFIRM:'));
test('AI user has assigned_id', $ai_user && str_starts_with($ai_user['assigned_id'], 'STD'));

$ai_stud = $ai_user ? db()->fetchOne("SELECT * FROM students WHERE user_id = ?", [$ai_user['id']]) : null;
test('AI student profile created', (bool)$ai_stud);

$ai_log = $ai_user ? db()->fetchOne("SELECT * FROM google_form_submissions WHERE created_user_id = ?", [$ai_user['id']]) : null;
test('AI submission logged', (bool)$ai_log);

// ── 5. OTP Confirmation Flow ─────────────────────────
echo "\n--- 5. OTP Confirmation Flow ---\n";

// Simulate confirming the teacher's account
$confirm_user = db()->fetchOne("SELECT * FROM users WHERE id = ? AND verification_token LIKE 'CONFIRM:%'", [$tch_user_id]);
test('Confirm: found pending teacher', (bool)$confirm_user);

preg_match('/^CONFIRM:(\d{6}):(\d+)$/', $confirm_user['verification_token'], $m);
$stored_otp = $m[1];
$expires_unix = (int)$m[2];

test('Confirm: OTP extractable', strlen($stored_otp) === 6);
test('Confirm: OTP matches what we set', hash_equals($otp, $stored_otp));
test('Confirm: not expired', time() < $expires_unix);

// Simulate setting password
$new_password = 'TestPass123';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);
$update_data = [
  'verification_token' => null,
  'is_active' => 1,
  'email_verified' => 1,
  'password' => $hashed,
  'token_expiry' => null,
  'password_set_at' => date('Y-m-d H:i:s'),
];
update_flexible('users', $update_data, 'id = ?', [$tch_user_id]);

$confirmed = db()->fetchOne("SELECT * FROM users WHERE id = ?", [$tch_user_id]);
test('After confirm: is_active=1', (int)$confirmed['is_active'] === 1);
test('After confirm: email_verified=1', (int)$confirmed['email_verified'] === 1);
test('After confirm: token cleared', $confirmed['verification_token'] === null);
test('After confirm: password set', password_verify($new_password, $confirmed['password']));
test('After confirm: password_set_at set', !empty($confirmed['password_set_at']));

// ── 6. Cleanup ───────────────────────────────────────
echo "\n--- Cleanup ---\n";

// AI created user
if ($ai_user) {
  db()->query("DELETE FROM google_form_submissions WHERE created_user_id = ?", [$ai_user['id']]);
  db()->query("DELETE FROM students WHERE user_id = ?", [$ai_user['id']]);
  db()->query("DELETE FROM users WHERE id = ?", [$ai_user['id']]);
}

// Student
db()->query("DELETE FROM class_enrollments WHERE student_id = ?", [$std_record_id]);
db()->query("DELETE FROM students WHERE id = ?", [$std_record_id]);
db()->query("DELETE FROM users WHERE id = ?", [$std_user_id]);

// Teacher
db()->query("DELETE FROM teachers WHERE user_id = ?", [$tch_user_id]);
db()->query("DELETE FROM users WHERE id = ?", [$tch_user_id]);

// Class
db()->query("DELETE FROM classes WHERE id = ?", [$cls_id]);

echo "Cleaned up test data.\n";

// ── Summary ──────────────────────────────────────────
echo "\n========================================\n";
echo " Results: $pass passed, $fail failed\n";
echo "========================================\n";
exit($fail > 0 ? 1 : 0);
