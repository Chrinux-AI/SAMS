<?php

/**
 * Common Functions
 */

/**
 * Sanitize input data
 */
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Hash password
 */
function hash_password($password)
{
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password
 */
function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get base URL for frontend routing
 * Returns the application base URL for use in links and redirects
 */
function base_url($path = '')
{
    $base = rtrim(APP_URL, '/');

    // In split-folder deployments, frontend lives under /frontend.
    // Keep compatibility for existing APP_URL values that still point to /attendance.
    if (!preg_match('#/frontend$#', $base)) {
        $base .= '/frontend';
    }

    if (!empty($path)) {
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }
    return $base;
}

/**
 * Site-wide absolute URL builder.
 * Produces domain-anchored links based on APP_URL and preserves query strings.
 * Example: site_url('admin/index.php?page=profile_settings') => https://host/base/admin/index.php?page=profile_settings
 */
function site_url($path = '')
{
    $base = rtrim(APP_URL, '/');
    $path = trim((string)$path);
    if ($path === '') {
        return $base . '/';
    }

    // If already an absolute URL, return as-is
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // Preserve query string when path contains ?
    return $base . '/' . ltrim($path, '/');
}

/**
 * Get API URL for backend API calls
 */
function api_url($endpoint = '')
{
    $base = rtrim(APP_URL, '/') . '/api';
    if (!empty($endpoint)) {
        $endpoint = ltrim($endpoint, '/');
        return $base . '/' . $endpoint;
    }
    return $base;
}

/**
 * Normalize any login redirect to the real root login page.
 */
function auth_login_url($redirect_url = '')
{
    $rootLogin = rtrim(APP_URL, '/') . '/login.php';
    $redirect_url = trim((string)$redirect_url);

    if ($redirect_url === '') {
        return $rootLogin;
    }

    if (strpos($redirect_url, 'login.php') !== false) {
        return $rootLogin;
    }

    return $redirect_url;
}

/**
 * Get URL for asset files (CSS, JS, images)
 */
function asset_url($path)
{
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Check if user is logged in
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

/**
 * Check user role
 */
function has_role($role)
{
    $sessionRole = $_SESSION['user_role'] ?? ($_SESSION['role'] ?? null);
    return isset($sessionRole) && strtolower((string)$sessionRole) === strtolower((string)$role);
}

/**
 * Check if table exists
 */
function table_exists($table_name)
{
    static $table_cache = [];
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table_name);
    if ($safe === '') {
        return false;
    }
    if (array_key_exists($safe, $table_cache)) {
        return $table_cache[$safe];
    }
    $row = db()->fetchOne("SHOW TABLES LIKE '{$safe}'");
    $table_cache[$safe] = (bool)$row;
    return $table_cache[$safe];
}

/**
 * Check if a table column exists.
 */
function table_has_column($table_name, $column_name)
{
    static $column_cache = [];
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table_name);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column_name);
    if ($table === '' || $column === '') {
        return false;
    }
    $key = $table . '.' . $column;
    if (array_key_exists($key, $column_cache)) {
        return $column_cache[$key];
    }
    $exists = false;
    if (table_exists($table)) {
        $cols = db()->fetchAll("SHOW COLUMNS FROM {$table}");
        foreach ($cols as $col) {
            if (($col['Field'] ?? '') === $column) {
                $exists = true;
                break;
            }
        }
    }
    $column_cache[$key] = $exists;
    return $exists;
}

/**
 * Keep only columns available in the target table.
 */
function filter_data_for_table($table_name, array $data)
{
    $filtered = [];
    foreach ($data as $key => $value) {
        if (table_has_column($table_name, $key)) {
            $filtered[$key] = $value;
        }
    }
    return $filtered;
}

/**
 * Insert into table using only existing columns.
 */
function insert_flexible($table_name, array $data)
{
    $filtered = filter_data_for_table($table_name, $data);
    if (empty($filtered)) {
        return false;
    }
    return db()->insert($table_name, $filtered);
}

/**
 * Update table using only existing columns.
 */
function update_flexible($table_name, array $data, $where, array $whereParams = [])
{
    $filtered = filter_data_for_table($table_name, $data);
    if (empty($filtered)) {
        return false;
    }
    return db()->update($table_name, $filtered, $where, $whereParams);
}

/**
 * Build schema-compatible user payload for current users table.
 */
function build_user_payload(array $input)
{
    $email = trim((string)($input['email'] ?? ''));
    $first = trim((string)($input['first_name'] ?? ''));
    $last = trim((string)($input['last_name'] ?? ''));
    $fullName = trim((string)($input['full_name'] ?? ($first . ' ' . $last)));
    $role = trim((string)($input['role'] ?? 'student'));
    $status = trim((string)($input['status'] ?? 'active'));
    $approved = (int)($input['approved'] ?? 1);
    $emailVerified = (int)($input['email_verified'] ?? 1);
    $assignedId = trim((string)($input['assigned_id'] ?? ''));
    $token = trim((string)($input['email_verification_token'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $rawPassword = (string)($input['password'] ?? '');
    if ($rawPassword === '') {
        $rawPassword = bin2hex(random_bytes(8)) . 'Aa!';
    }
    $hash = password_hash($rawPassword, PASSWORD_DEFAULT);

    $payload = [
        'email' => $email,
        'role' => $role,
        'status' => $status,
        'approved' => $approved,
        'email_verified' => $emailVerified,
        'first_name' => $first,
        'last_name' => $last,
        'full_name' => $fullName,
        'assigned_id' => $assignedId !== '' ? $assignedId : null,
        'email_verification_token' => $token !== '' ? $token : null,
        'is_active' => $status === 'active' ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($username !== '') {
        $payload['username'] = $username;
    }
    if (table_has_column('users', 'password_hash')) {
        $payload['password_hash'] = $hash;
    }
    if (table_has_column('users', 'password')) {
        $payload['password'] = $hash;
    }

    return filter_data_for_table('users', $payload);
}

/**
 * Resolve tenant id for a user from tenant mapping.
 */
function resolve_user_tenant_id($user_id)
{
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        return 1;
    }

    if (table_exists('tenant_users')) {
        $row = db()->fetchOne(
            "SELECT tenant_id FROM tenant_users WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1",
            [$user_id]
        );
        if ($row && !empty($row['tenant_id'])) {
            return (int)$row['tenant_id'];
        }
    }

    if (table_exists('users')) {
        $columns = db()->fetchAll("SHOW COLUMNS FROM users");
        $has_tenant_column = false;
        foreach ($columns as $col) {
            if (($col['Field'] ?? '') === 'tenant_id') {
                $has_tenant_column = true;
                break;
            }
        }
        if ($has_tenant_column) {
            $user = db()->fetchOne("SELECT tenant_id FROM users WHERE id = ?", [$user_id]);
            if ($user && !empty($user['tenant_id'])) {
                return (int)$user['tenant_id'];
            }
        }
    }

    return 1;
}

/**
 * Resolve tenant display name.
 */
function resolve_tenant_name($tenant_id)
{
    $tenant_id = (int)$tenant_id;
    if ($tenant_id <= 0) {
        return 'Default School';
    }

    if (table_exists('school_tenants')) {
        $row = db()->fetchOne("SELECT name FROM school_tenants WHERE id = ?", [$tenant_id]);
        if ($row && !empty($row['name'])) {
            return (string)$row['name'];
        }
    }

    return 'Default School';
}

/**
 * Set tenant context into session for current user.
 */
function set_user_tenant_session($user_id)
{
    $tenant_id = resolve_user_tenant_id($user_id);
    $_SESSION['tenant_id'] = $tenant_id;
    $_SESSION['school_id'] = $tenant_id;
    $_SESSION['tenant_name'] = resolve_tenant_name($tenant_id);
    return $tenant_id;
}

/**
 * Link a user to tenant in tenant_users mapping table.
 */
function attach_user_to_tenant($user_id, $tenant_id = null)
{
    $user_id = (int)$user_id;
    if ($user_id <= 0 || !table_exists('tenant_users')) {
        return false;
    }

    $tenant_id = $tenant_id === null ? current_tenant_id() : (int)$tenant_id;
    if ($tenant_id <= 0) {
        $tenant_id = 1;
    }

    $existing = db()->fetchOne(
        "SELECT id FROM tenant_users WHERE user_id = ? AND tenant_id = ? LIMIT 1",
        [$user_id, $tenant_id]
    );
    if ($existing) {
        db()->query("UPDATE tenant_users SET is_active = 1, updated_at = NOW() WHERE id = ?", [$existing['id']]);
        return true;
    }

    return (bool)db()->insert('tenant_users', [
        'tenant_id' => $tenant_id,
        'user_id' => $user_id,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get active tenant id from session.
 */
function current_tenant_id()
{
    return (int)($_SESSION['tenant_id'] ?? 1);
}

/**
 * Check whether user belongs to active tenant.
 */
function user_in_current_tenant($user_id)
{
    $user_id = (int)$user_id;
    $tenant_id = current_tenant_id();
    if ($user_id <= 0 || $tenant_id <= 0) {
        return false;
    }

    if (table_exists('tenant_users')) {
        $row = db()->fetchOne(
            "SELECT id FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1",
            [$user_id, $tenant_id]
        );
        if ($row) {
            return true;
        }
    }

    if (table_exists('users')) {
        $user = db()->fetchOne(
            "SELECT tenant_id, school_id FROM users WHERE id = ? LIMIT 1",
            [$user_id]
        );
        if ($user) {
            $candidateTenantIds = array_filter([
                (int)($user['tenant_id'] ?? 0),
                (int)($user['school_id'] ?? 0)
            ]);
            if (in_array($tenant_id, $candidateTenantIds, true)) {
                return true;
            }
        }
    }

    if (table_exists('students')) {
        $student = db()->fetchOne(
            "SELECT tenant_id, school_id FROM students WHERE user_id = ? LIMIT 1",
            [$user_id]
        );
        if ($student) {
            $candidateTenantIds = array_filter([
                (int)($student['tenant_id'] ?? 0),
                (int)($student['school_id'] ?? 0)
            ]);
            if (in_array($tenant_id, $candidateTenantIds, true)) {
                return true;
            }
        }
    }

    return true;
}

/**
 * Enforce tenant session integrity.
 */
function enforce_tenant_access($redirect_url = '../login.php')
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    if (!isset($_SESSION['tenant_id'])) {
        set_user_tenant_session((int)$_SESSION['user_id']);
    }

    if (!user_in_current_tenant((int)$_SESSION['user_id'])) {
        error_log('Tenant access denied for user ' . (int)$_SESSION['user_id'] . ' in tenant ' . (int)($_SESSION['tenant_id'] ?? 0));
        session_destroy();
        redirect($redirect_url, 'Tenant access denied. Please login again.', 'error');
    }
}

/**
 * Require login - redirect if not logged in
 */
function require_login($redirect_url = '../login.php')
{
    $redirect_url = auth_login_url($redirect_url);
    if (!is_logged_in()) {
        redirect($redirect_url, 'Please login to access this page', 'error');
    }
    enforce_tenant_access($redirect_url);
}

/**
 * Alias for require_login for compatibility
 */
function check_login($redirect_url = '../login.php')
{
    require_login($redirect_url);
}

/**
 * Require admin role - redirect if not admin
 */
function require_admin($redirect_url = '../login.php')
{
    $redirect_url = auth_login_url($redirect_url);
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!is_logged_in()) {
        // Clear any existing session data for security
        session_destroy();
        redirect($redirect_url, 'Please login to access this page', 'error');
    }

    enforce_tenant_access($redirect_url);

    $sessionRole = strtolower((string)($_SESSION['user_role'] ?? ($_SESSION['role'] ?? '')));
    $allowedAdminLikeRoles = ['admin', 'super_admin', 'superadmin', 'owner', 'principal', 'vice_principal', 'admin_officer'];

    // Double-check privileged role from session first
    if (!in_array($sessionRole, $allowedAdminLikeRoles, true)) {
        // Log unauthorized access attempt
        error_log("Unauthorized admin access attempt by user ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        redirect($redirect_url, 'Access denied. Admin privileges required.', 'error');
    }

    // Verify admin status in database (prevent session hijacking)
    if (isset($_SESSION['user_id'])) {
        $user = db()->fetch("SELECT role, status FROM users WHERE id = ?", [$_SESSION['user_id']]);
        $dbRole = strtolower((string)($user['role'] ?? ''));
        if (!$user || !in_array($dbRole, $allowedAdminLikeRoles, true) || ($user['status'] ?? '') !== 'active') {
            // Clear compromised session
            session_destroy();
            error_log("Admin session validation failed for user ID: " . $_SESSION['user_id']);
            redirect($redirect_url, 'Session invalid. Please login again.', 'error');
        }
    }
}

/**
 * Require specific role
 */
function require_role($role, $redirect_url = '../login.php')
{
    $redirect_url = auth_login_url($redirect_url);
    if (!is_logged_in()) {
        redirect($redirect_url, 'Please login to access this page', 'error');
    }
    enforce_tenant_access($redirect_url);
    if (!has_role($role)) {
        redirect($redirect_url, 'Access denied. Insufficient privileges.', 'error');
    }
}

/**
 * Require teacher role - redirect if not teacher
 */
function require_teacher($redirect_url = '../login.php')
{
    $redirect_url = auth_login_url($redirect_url);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!is_logged_in()) {
        session_destroy();
        redirect($redirect_url, 'Please login to access this page', 'error');
    }
    enforce_tenant_access($redirect_url);
    if (!has_role('teacher')) {
        error_log("Unauthorized teacher access attempt by user ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        redirect($redirect_url, 'Access denied. Teacher privileges required.', 'error');
    }
    if (isset($_SESSION['user_id'])) {
        $user = db()->fetchOne("SELECT role, status FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if (!$user || $user['role'] !== 'teacher' || $user['status'] !== 'active') {
            session_destroy();
            error_log("Teacher session validation failed for user ID: " . $_SESSION['user_id']);
            redirect($redirect_url, 'Session invalid. Please login again.', 'error');
        }
    }
}

/**
 * Require student role - redirect if not student
 */
function require_student($redirect_url = '../login.php')
{
    $redirect_url = auth_login_url($redirect_url);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!is_logged_in()) {
        session_destroy();
        redirect($redirect_url, 'Please login to access this page', 'error');
    }
    enforce_tenant_access($redirect_url);
    if (!has_role('student')) {
        error_log("Unauthorized student access attempt by user ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        redirect($redirect_url, 'Access denied. Student privileges required.', 'error');
    }
    if (isset($_SESSION['user_id'])) {
        $user = db()->fetchOne("SELECT role, status FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if (!$user || $user['role'] !== 'student' || $user['status'] !== 'active') {
            session_destroy();
            error_log("Student session validation failed for user ID: " . $_SESSION['user_id']);
            redirect($redirect_url, 'Session invalid. Please login again.', 'error');
        }
    }
}

/**
 * Require parent role - redirect if not parent
 */
function require_parent($redirect_url = '../login.php')
{
    $redirect_url = auth_login_url($redirect_url);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!is_logged_in()) {
        session_destroy();
        redirect($redirect_url, 'Please login to access this page', 'error');
    }
    enforce_tenant_access($redirect_url);
    if (!has_role('parent')) {
        error_log("Unauthorized parent access attempt by user ID: " . ($_SESSION['user_id'] ?? 'unknown'));
        redirect($redirect_url, 'Access denied. Parent privileges required.', 'error');
    }
    if (isset($_SESSION['user_id'])) {
        $user = db()->fetchOne("SELECT role, status FROM users WHERE id = ?", [$_SESSION['user_id']]);
        if (!$user || $user['role'] !== 'parent' || $user['status'] !== 'active') {
            session_destroy();
            error_log("Parent session validation failed for user ID: " . $_SESSION['user_id']);
            redirect($redirect_url, 'Session invalid. Please login again.', 'error');
        }
    }
}

/**
 * Resolve role dashboard path.
 * Keeps all role routing in one place.
 */
function get_role_dashboard_path($role)
{
    $role = strtolower(trim((string)$role));
    $map = [
        'admin' => 'frontend/admin/dashboard.php',
        'superadmin' => 'frontend/admin/dashboard.php',
        'super_admin' => 'frontend/admin/dashboard.php',
        'owner' => 'frontend/owner/dashboard.php',
        'principal' => 'frontend/principal/dashboard.php',
        'vice_principal' => 'frontend/principal/dashboard.php',
        'admin_officer' => 'frontend/admin/dashboard.php',
        'teacher' => 'frontend/teacher/dashboard.php',
        'class_teacher' => 'frontend/teacher/dashboard.php',
        'subject_coordinator' => 'frontend/teacher/dashboard.php',
        'student' => 'frontend/student/dashboard.php',
        'parent' => 'frontend/parent/dashboard.php',
        'librarian' => 'frontend/librarian/dashboard.php',
        'bursar' => 'frontend/bursar/dashboard.php',
        'accountant' => 'frontend/accountant/index.php?page=dashboard',
        'transport' => 'frontend/transport/dashboard.php',
        'forum_moderator' => 'frontend/forum-moderator/dashboard.php',
        'counselor' => 'frontend/student/dashboard.php',
        'nurse' => 'frontend/nurse/dashboard.php',
        'staff' => 'frontend/staff/dashboard.php',
    ];
    $candidate = $map[$role] ?? 'login.php';
    // Return an absolute, domain-anchored URL for all role routes
    return site_url($candidate);
}

/**
 * Optional CSRF check for backward-compatible migration.
 * If token is provided it must be valid; if omitted it allows the request.
 */
function validate_post_csrf_if_present()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    if (!isset($_POST['csrf_token'])) {
        return true;
    }
    return verify_csrf_token((string)$_POST['csrf_token']);
}

/**
 * Redirect with message
 */
function redirect($url, $message = '', $type = 'info')
{
    // If target is empty or explicitly points to login, normalize to canonical login URL
    if ($url === '' || strpos((string)$url, 'login.php') !== false) {
        $url = auth_login_url($url);
    } else {
        // Convert relative (non-absolute) URLs to site-anchored absolute URLs
        if (!preg_match('#^https?://#i', $url)) {
            $url = site_url($url);
        }
    }
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit();
}

/**
 * Get and clear flash message
 */
function get_flash_message()
{
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Format date
 */
function format_date($date, $format = 'Y-m-d')
{
    if (!$date) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function format_datetime($datetime, $format = 'M j, Y g:i A')
{
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Resolve tenant settings JSON payload.
 */
function get_tenant_settings($tenant_id = null)
{
    $tenant_id = (int)($tenant_id ?? current_tenant_id());
    if ($tenant_id <= 0 || !table_exists('school_tenants') || !table_has_column('school_tenants', 'settings_json')) {
        return [];
    }

    try {
        $row = db()->fetchOne("SELECT settings_json FROM school_tenants WHERE id = ? LIMIT 1", [$tenant_id]);
        $raw = (string)($row['settings_json'] ?? '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable $e) {
        error_log('Tenant settings read error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Resolve location-aware currency context for tenant.
 */
function resolve_tenant_currency_context($tenant_id = null)
{
    static $cache = [];
    $tenant_id = (int)($tenant_id ?? current_tenant_id());
    if ($tenant_id <= 0) {
        $tenant_id = 1;
    }

    if (isset($cache[$tenant_id])) {
        return $cache[$tenant_id];
    }

    $settings = get_tenant_settings($tenant_id);
    $flat = [];
    foreach ($settings as $key => $value) {
        if (is_scalar($value)) {
            $flat[strtolower((string)$key)] = (string)$value;
        }
    }

    $currencyCode = strtoupper(trim((string)($flat['currency_code'] ?? $flat['currency'] ?? '')));
    $locale = trim((string)($flat['locale'] ?? ''));
    $country = strtoupper(trim((string)($flat['country_code'] ?? $flat['country'] ?? '')));
    $currencySymbol = trim((string)($flat['currency_symbol'] ?? ''));

    $countryMap = [
        'NG' => ['locale' => 'en-NG', 'currency' => 'NGN', 'symbol' => '₦'],
        'US' => ['locale' => 'en-US', 'currency' => 'USD', 'symbol' => '$'],
        'GB' => ['locale' => 'en-GB', 'currency' => 'GBP', 'symbol' => '£'],
        'EU' => ['locale' => 'en-IE', 'currency' => 'EUR', 'symbol' => '€'],
        'CA' => ['locale' => 'en-CA', 'currency' => 'CAD', 'symbol' => 'C$'],
        'GH' => ['locale' => 'en-GH', 'currency' => 'GHS', 'symbol' => 'GH₵'],
        'KE' => ['locale' => 'en-KE', 'currency' => 'KES', 'symbol' => 'KSh'],
        'ZA' => ['locale' => 'en-ZA', 'currency' => 'ZAR', 'symbol' => 'R'],
    ];

    if ($country !== '' && isset($countryMap[$country])) {
        $locale = $locale !== '' ? $locale : $countryMap[$country]['locale'];
        $currencyCode = $currencyCode !== '' ? $currencyCode : $countryMap[$country]['currency'];
        $currencySymbol = $currencySymbol !== '' ? $currencySymbol : $countryMap[$country]['symbol'];
    }

    if ($currencyCode === 'NGN' && $currencySymbol === '') {
        $currencySymbol = '₦';
    }

    if ($currencyCode === '') {
        $currencyCode = 'NGN';
    }
    if ($locale === '') {
        $locale = 'en-NG';
    }
    if ($currencySymbol === '') {
        $currencySymbol = $currencyCode . ' ';
    }

    $cache[$tenant_id] = [
        'tenant_id' => $tenant_id,
        'locale' => $locale,
        'currency_code' => $currencyCode,
        'currency_symbol' => $currencySymbol,
    ];

    return $cache[$tenant_id];
}

/**
 * Format currency based on tenant location settings.
 */
function format_local_currency($amount, int $decimals = 2, $tenant_id = null)
{
    $ctx = resolve_tenant_currency_context($tenant_id);
    $value = (float)$amount;

    if (class_exists('NumberFormatter')) {
        try {
            $fmt = new NumberFormatter($ctx['locale'], NumberFormatter::CURRENCY);
            $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, max(0, $decimals));
            $formatted = $fmt->formatCurrency($value, $ctx['currency_code']);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        } catch (Throwable $e) {
            // Fallback below
        }
    }

    return $ctx['currency_symbol'] . number_format($value, max(0, $decimals));
}

/**
 * Calculate attendance percentage
 */
function calculate_attendance_percentage($present, $total)
{
    if ($total == 0) return 0;
    return round(($present / $total) * 100, 2);
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $entity_type = null, $entity_id = null, $details = null)
{
    $data = [
        'user_id' => $user_id,
        'action' => $action,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'details' => $details ? json_encode($details) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'created_at' => date('Y-m-d H:i:s')
    ];

    return db()->insert('audit_logs', $data);
}

/**
 * Send notification
 */
function send_notification($user_id, $title, $message, $type = 'info', $channels = ['in-app'])
{
    $data = [
        'user_id' => $user_id,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'channels' => json_encode($channels),
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];

    if (function_exists('current_tenant_id') && function_exists('table_has_column')) {
        $tenantId = (int)current_tenant_id();
        if ($tenantId > 0 && table_has_column('notifications', 'tenant_id')) {
            $data['tenant_id'] = $tenantId;
        } elseif ($tenantId > 0 && table_has_column('notifications', 'school_id')) {
            $data['school_id'] = $tenantId;
        }
    }

    return db()->insert('notifications', $data);
}

/**
 * Get student attendance summary
 */
function get_student_attendance_summary($student_id, $start_date = null, $end_date = null)
{
    $where = 'student_id = :student_id';
    $params = ['student_id' => $student_id];

    if ($start_date) {
        $where .= ' AND attendance_date >= :start_date';
        $params['start_date'] = $start_date;
    }

    if ($end_date) {
        $where .= ' AND attendance_date <= :end_date';
        $params['end_date'] = $end_date;
    }

    $sql = "SELECT
                status,
                COUNT(*) as count
            FROM attendance_records
            WHERE {$where}
            GROUP BY status";

    $results = db()->fetchAll($sql, $params);

    $summary = [
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'excused' => 0,
        'total' => 0
    ];

    foreach ($results as $row) {
        $summary[$row['status']] = (int)$row['count'];
        $summary['total'] += (int)$row['count'];
    }

    $summary['attendance_rate'] = calculate_attendance_percentage(
        $summary['present'] + $summary['late'],
        $summary['total']
    );

    return $summary;
}

/**
 * Check for chronic absenteeism
 */
function is_chronically_absent($student_id, $days = 90)
{
    $end_date = date('Y-m-d');
    $start_date = date('Y-m-d', strtotime("-{$days} days"));

    $summary = get_student_attendance_summary($student_id, $start_date, $end_date);

    return $summary['total'] > 0 &&
        $summary['attendance_rate'] < (100 - CHRONIC_ABSENTEEISM_THRESHOLD);
}

/**
 * Generate secure random string
 */
function generate_random_string($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check file upload
 */
function validate_file_upload($file)
{
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error';
        return $errors;
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File size exceeds maximum allowed';
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = 'File type not allowed';
    }

    return $errors;
}

/**
 * Upload file
 */
function upload_file($file, $subfolder = '')
{
    $errors = validate_file_upload($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    $upload_dir = UPLOAD_PATH . '/' . $subfolder;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = generate_random_string() . '.' . $extension;
    $filepath = $upload_dir . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'relative_path' => $subfolder . '/' . $filename
        ];
    }

    return ['success' => false, 'errors' => ['Failed to move uploaded file']];
}

/**
 * Send email notification
 */
/**
 * Send email using Gmail SMTP via PHPMailer
 * Requires: composer require phpmailer/phpmailer
 * Configuration in config.php: SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD
 */
function send_email($to, $subject, $message, $from_name = null)
{
    // Use from_name from config if not provided
    if ($from_name === null) {
        $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'School Attendance System';
    }

    // Check if PHPMailer is installed
    $autoload_path = BASE_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        // Fallback to basic PHP mail() if PHPMailer not installed
        error_log('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
        return send_email_basic($to, $subject, $message, $from_name);
    }

    require_once $autoload_path;

    // Use PHPMailer classes - must be after require_once
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $mail->SMTPSecure = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';
        $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;

        // Check if password is set
        if (empty($mail->Password)) {
            error_log('SMTP_PASSWORD not set in config.php. See EMAIL-SMTP-SETUP.md for instructions.');
            return false;
        }

        // Recipients
        $mail->setFrom(
            defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'christolabiyi35@gmail.com',
            $from_name
        );
        $mail->addAddress($to);
        $mail->addReplyTo(
            defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'christolabiyi35@gmail.com',
            $from_name
        );

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->CharSet = 'UTF-8';

        // Wrap message in HTML template
        $html_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>School Attendance System</h1>
                </div>
                <div class='content'>
                    $message
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " School Attendance System. All rights reserved.</p>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body = $html_message;
        $mail->AltBody = strip_tags($message); // Plain text version

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Fallback email function using basic PHP mail() if PHPMailer not available
 */
function send_email_basic($to, $subject, $message, $from_name = 'School Attendance System')
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . $from_name . ' <noreply@school.com>',
        'Reply-To: noreply@school.com',
        'X-Mailer: PHP/' . phpversion()
    ];

    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>School Attendance System</h1>
            </div>
            <div class='content'>
                $message
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " School Attendance System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return mail($to, $subject, $html_message, implode("\r\n", $headers));
}

/**
 * Send WhatsApp notification via Twilio API
 * Falls back to logging if Twilio not configured
 * Configuration in config.php: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM
 */
function send_whatsapp($phone, $message)
{
    // WhatsApp notifications disabled by admin request
    error_log("WhatsApp disabled: Would have sent to $phone - $message");
    return false;

    // Clean phone number (remove spaces, dashes, etc.)
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    // Check if Twilio is configured
    if (!defined('TWILIO_ACCOUNT_SID') || empty(TWILIO_ACCOUNT_SID)) {
        // Fallback to logging if Twilio not configured
        $log_file = BASE_PATH . '/logs/whatsapp.log';

        // Ensure logs directory exists
        $log_dir = dirname($log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0777, true);
        }

        $log_message = "\n[" . date('Y-m-d H:i:s') . "] To: $phone\n";
        $log_message .= "Message: $message\n";
        $log_message .= "---\n";

        file_put_contents($log_file, $log_message, FILE_APPEND);
        error_log("WhatsApp: Twilio not configured. Message logged to file.");
        return true;
    }

    // Check if Twilio SDK is installed
    $autoload_path = BASE_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload_path)) {
        error_log('Twilio SDK not installed. Run: composer require twilio/sdk');
        // Fallback to logging
        return send_whatsapp_log_only($phone, $message);
    }

    require_once $autoload_path;

    try {
        // Initialize Twilio client
        $twilio = new \Twilio\Rest\Client(
            TWILIO_ACCOUNT_SID,
            TWILIO_AUTH_TOKEN
        );

        // Ensure phone number has 'whatsapp:' prefix
        if (strpos($phone, 'whatsapp:') === false) {
            $phone = 'whatsapp:' . $phone;
        }

        // Get from number (Twilio WhatsApp sandbox or approved number)
        $from = defined('TWILIO_WHATSAPP_FROM') ? TWILIO_WHATSAPP_FROM : 'whatsapp:+14155238886';

        // Send message
        $sent_message = $twilio->messages->create(
            $phone,  // To
            [
                'from' => $from,
                'body' => $message
            ]
        );

        // Log successful send
        error_log("WhatsApp sent successfully. SID: {$sent_message->sid} To: $phone");

        // Also log to file for record keeping
        $log_file = BASE_PATH . '/logs/whatsapp.log';
        $log_message = "\n[" . date('Y-m-d H:i:s') . "] ✅ SENT via Twilio\n";
        $log_message .= "To: $phone\n";
        $log_message .= "SID: {$sent_message->sid}\n";
        $log_message .= "Message: $message\n";
        $log_message .= "---\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);

        return true;
    } catch (\Exception $e) {
        error_log("WhatsApp sending failed: " . $e->getMessage());

        // Log failed attempt
        $log_file = BASE_PATH . '/logs/whatsapp.log';
        $log_message = "\n[" . date('Y-m-d H:i:s') . "] ❌ FAILED\n";
        $log_message .= "To: $phone\n";
        $log_message .= "Error: " . $e->getMessage() . "\n";
        $log_message .= "Message: $message\n";
        $log_message .= "---\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);

        return false;
    }
}

/**
 * Fallback function to log WhatsApp messages when Twilio not configured
 */
function send_whatsapp_log_only($phone, $message)
{
    $log_file = BASE_PATH . '/logs/whatsapp.log';

    // Ensure logs directory exists
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    $log_message = "\n[" . date('Y-m-d H:i:s') . "] To: $phone\n";
    $log_message .= "Message: $message\n";
    $log_message .= "Note: Twilio SDK not installed or not configured\n";
    $log_message .= "---\n";

    file_put_contents($log_file, $log_message, FILE_APPEND);
    return true;
}

/**
 * Send registration notification
 */
function send_registration_notification($user_id, $email, $name, $role)
{
    // Generate temporary registration ID for tracking
    $temp_id = 'REG' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

    // Email to user
    $subject = "Registration Received - Awaiting Approval";
    $message = "
        <h2>Hello $name,</h2>
        <p>Thank you for registering with the School Attendance System.</p>
        <div class='info-box'>
            <strong>Your registration details:</strong><br>
            Name: $name<br>
            Email: $email<br>
            Role: " . ucfirst($role) . "<br>
            <strong>Registration ID: <span style='font-size: 18px; color: #667eea;'>$temp_id</span></strong><br>
            Status: <strong style='color: orange;'>Pending Approval</strong>
        </div>
        <p><strong>Important Information:</strong></p>
        <ul>
            <li>Your account is currently pending approval by an administrator</li>
            <li>Please save your Registration ID: <strong>$temp_id</strong></li>
            <li>You will receive your official Student/Employee ID once approved</li>
            <li>Login credentials will be provided after approval</li>
        </ul>
        <p>We will notify you via email once your account has been approved.</p>
        <p>If you have any questions, please contact the school administration.</p>
    ";
    send_email($email, $subject, $message);

    // Email to admin
    $admin_email = 'christolabiyi35@gmail.com';
    $admin_subject = "New Registration - Pending Approval";
    $admin_message = "
        <h2>🔔 New User Registration</h2>
            <strong>Registration Details:</strong><br>
            Name: $name<br>
            Email: $email<br>
            Role: " . ucfirst($role) . "<br>
            <strong>Registration ID: <span style='font-size: 18px; color: #667eea;'>$temp_id</span></strong><br>
            User ID: $user_id<br>
            Registration Time: " . date('Y-m-d H:i:s') . "
        </div>
        <p><strong>Action Required:</strong> Please review and approve/reject this registration.</p>
        <p><strong>Access:</strong> Login to the attendance system and navigate to the admin section.</p>

        <div style='margin-top: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6;'>
            <strong>Next Steps:</strong><br>
            • Review complete application details<br>
            • Assign official Student/Employee ID<br>
            • Send approval notification with login credentials<br>
            • User will receive their permanent ID via email
        </div>
    ";
    send_email($admin_email, $admin_subject, $admin_message);

    // WhatsApp to admin
    $whatsapp_message = "🔔 New Registration\n\n" .
        "Name: $name\n" .
        "Email: $email\n" .
        "Role: " . ucfirst($role) . "\n\n" .
        "Please review pending registrations in the admin panel.";
    send_whatsapp('+2348167714860', $whatsapp_message);
}

/**
 * Send approval notification with ID
 */
function send_approval_notification($user_id, $email, $name, $role, $assigned_id, $username)
{
    $id_type = $role === 'student' ? 'Student ID' : ($role === 'teacher' ? 'Employee ID' : 'User ID');

    // Email to user with enhanced design
    $subject = "🎉 Account Approved - Welcome to " . APP_NAME . "!";
    $message = "
        <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; font-size: 28px;'>🎉 Congratulations $name!</h1>
                <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Your account has been approved and activated</p>
            </div>

            <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);'>
                <div style='background: #f8fafc; padding: 25px; border-radius: 10px; border-left: 5px solid #10b981; margin-bottom: 25px;'>
                    <h3 style='color: #1e293b; margin: 0 0 15px 0; font-size: 18px;'>
                        <i class='fas fa-id-card'></i> Your Official Account Details
                    </h3>
                    <div style='line-height: 1.8; color: #374151;'>
                        <div style='font-size: 24px; font-weight: bold; color: #667eea; margin-bottom: 10px;'>
                            <strong>$id_type: $assigned_id</strong>
                        </div>
                        <strong>Username:</strong> $username<br>
                        <strong>Email:</strong> $email<br>
                        <strong>Role:</strong> " . ucfirst($role) . "<br>
                        <strong>Status:</strong> <span style='color: #10b981; font-weight: bold;'>✅ ACTIVE</span>
                    </div>
                </div>

                <div style='background: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 25px;'>
                    <h4 style='color: #92400e; margin: 0 0 10px 0;'>📝 Important Information</h4>
                    <ul style='color: #92400e; margin: 0; padding-left: 20px;'>
                        <li><strong>Save your $id_type: $assigned_id</strong> - You'll need this for attendance</li>
                        <li>Use your <strong>username ($username)</strong> to login</li>
                        <li>Your password remains the same as when you registered</li>
                    </ul>
                </div>

                <div style='text-align: center; margin-top: 30px;'>
                    <div style='background: #e0f2fe; padding: 20px; border-radius: 8px;'>
                        <h4 style='color: #0277bd; margin: 0 0 10px 0;'>🚀 Ready to Get Started?</h4>
                        <p style='color: #0277bd; margin: 0;'>Login to the attendance system to access your dashboard and start marking attendance.</p>
                    </div>
                </div>

                <div style='margin-top: 25px; text-align: center; color: #64748b; font-size: 14px;'>
                    <p>Welcome to " . APP_NAME . "! If you have any questions, contact the administration.</p>
                </div>
            </div>
        </div>
    ";
    send_email($email, $subject, $message);

    // Enhanced admin notification
    $admin_email = 'christolabiyi35@gmail.com';
    $admin_subject = "✅ User Approved - $name ($assigned_id)";
    $admin_message = "
        <h2>✅ User Account Approved</h2>
        <div style='background: #f0fdf4; padding: 20px; border-radius: 8px; border-left: 4px solid #10b981;'>
            <h3 style='color: #166534; margin: 0 0 15px 0;'>Account Successfully Activated</h3>
            <div style='line-height: 1.6; color: #374151;'>
                <strong>User:</strong> $name<br>
                <strong>$id_type:</strong> <span style='font-size: 18px; color: #667eea; font-weight: bold;'>$assigned_id</span><br>
                <strong>Username:</strong> $username<br>
                <strong>Email:</strong> $email<br>
                <strong>Role:</strong> " . ucfirst($role) . "<br>
                <strong>Approved by:</strong> " . $_SESSION['full_name'] . "<br>
                <strong>Approval Time:</strong> " . date('Y-m-d H:i:s') . "
            </div>
        </div>

        <p><strong>✅ Actions Completed:</strong></p>
        <ul>
            <li>User account status changed to ACTIVE</li>
            <li>Official $id_type assigned: <strong>$assigned_id</strong></li>
            <li>Welcome email sent to user with all details</li>
            <li>User can now login and access the system</li>
        </ul>
    ";
    send_email($admin_email, $admin_subject, $admin_message);

    // WhatsApp notification
    $whatsapp_message = "✅ Account Approved!\n\n" .
        "Hello $name,\n\n" .
        "Your account has been activated.\n\n" .
        "Your $id_type: *$assigned_id*\n" .
        "Username: $username\n\n" .
        "Please save this ID for future use.\n\n" .
        "Access the attendance system to login.";
    send_whatsapp('+2348167714860', $whatsapp_message);
}

/**
 * Send rejection notification
 */
function send_rejection_notification($email, $name, $reason = '')
{
    $subject = "Registration Not Approved";
    $message = "
        <h2>Hello $name,</h2>
        <p>We regret to inform you that your registration could not be approved at this time.</p>
        " . ($reason ? "<div class='info-box'><strong>Reason:</strong> $reason</div>" : "") . "
        <p>If you believe this is an error or would like to discuss this decision, please contact the school administration.</p>
    ";
    send_email($email, $subject, $message);
}

/**
 * Send email verification link
 */
function send_verification_email($email, $name, $verification_token, $assigned_id = null, $role = null)
{
    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/attendance/verify-email.php?token=" . $verification_token;

    // Determine ID type based on role
    $id_display = '';
    if ($assigned_id && $role) {
        $id_type = $role === 'student' ? 'Student ID' : ($role === 'teacher' ? 'Employee ID' : 'User ID');
        $id_display = "
            <div style='background: #e0f2fe; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0ea5e9;'>
                <h4 style='color: #0369a1; margin: 0 0 10px 0;'>📋 Your Assigned ID</h4>
                <div style='font-size: 24px; font-weight: bold; color: #667eea; margin-bottom: 5px;'>
                    $id_type: $assigned_id
                </div>
                <p style='margin: 5px 0 0 0; color: #0369a1; font-size: 14px;'>
                    <strong>Please save this ID!</strong> You'll need it for attendance tracking and system access.
                </p>
            </div>
        ";
    }

    $subject = "Verify Your Email - Attendance System";
    $message = "
        <div style='max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0;'>📧 Email Verification</h1>
            </div>
            <div style='background: white; padding: 30px; border: 1px solid #e0e0e0;'>
                <p>Hello <strong>$name</strong>,</p>
                <p>Thank you for registering with the Attendance Management System!</p>

                $id_display

                <p>Please verify your email address by clicking the button below:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='$verification_link' style='display: inline-block; background: #00BFFF; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Verify My Email</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='background: #f9f9f9; padding: 15px; border-radius: 5px; word-break: break-all; font-size: 12px; border-left: 3px solid #00BFFF;'>$verification_link</p>
                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0;'><strong>⚠️ Important:</strong> After email verification, your account must be approved by an administrator before you can login.</p>
                </div>
            </div>
            <div style='background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; border-radius: 0 0 10px 10px;'>
                <p style='margin: 0;'>If you didn't register for this account, please ignore this email.</p>
                <p style='margin: 10px 0 0 0;'>&copy; " . date('Y') . " Attendance System. All rights reserved.</p>
            </div>
        </div>
    ";

    return send_email($email, $subject, $message);
}

/**
 * Get recent attendance records for a student
 */
function get_recent_attendance($student_id, $limit = 10)
{
    global $pdo;

    try {
        $sql = "SELECT a.*, c.class_name as class_name
                FROM attendance a
                LEFT JOIN classes c ON a.class_id = c.id
                WHERE a.student_id = :student_id
                ORDER BY a.date DESC, a.created_at DESC
                LIMIT :limit";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':student_id', $student_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching recent attendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Dashboard Helper Functions
 */

/**
 * Calculate attendance trend for AI analytics
 */
function calculateAttendanceTrend()
{
    try {
        // Mock AI calculation - replace with actual ML model
        $trend_data = [
            'overall_trend' => 'increasing',
            'trend_percentage' => 5.2,
            'prediction_confidence' => 0.94,
            'risk_factors' => ['Monday absences', 'Weather correlation']
        ];
        return $trend_data;
    } catch (Exception $e) {
        error_log("Error calculating attendance trend: " . $e->getMessage());
        return ['overall_trend' => 'stable', 'trend_percentage' => 0];
    }
}

/**
 * Identify students at risk of chronic absenteeism
 */
function identifyRiskStudents()
{
    try {
        global $pdo;

        // Check if PDO connection is available
        if (!$pdo) {
            error_log("Database connection not available for identifyRiskStudents()");
            return [];
        }

        $sql = "SELECT s.id, s.admission_number as student_id, u.first_name, u.last_name,
                COUNT(ar.id) as total_days,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                ROUND((SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) / COUNT(ar.id)) * 100, 1) as risk_score
                FROM students s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN attendance_records ar ON s.user_id = ar.student_id
                WHERE ar.check_in_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY s.id
                HAVING risk_score > 15
                ORDER BY risk_score DESC
                LIMIT 10";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error identifying risk students: " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("General error in identifyRiskStudents: " . $e->getMessage());
        return [];
    }
}
/**
 * Predict performance based on attendance patterns
 */
function predictPerformance()
{
    // Mock AI prediction - replace with actual ML model
    return [
        'predicted_grade_improvement' => 12.5,
        'confidence_level' => 0.89,
        'factors' => ['attendance_consistency', 'early_arrival_pattern'],
        'recommendations' => ['Encourage morning routine', 'Reward perfect attendance']
    ];
}

/**
 * Analyze optimal scheduling patterns
 */
function analyzeOptimalSchedule()
{
    // Mock AI analysis - replace with actual ML model
    return [
        'optimal_start_time' => '08:30',
        'peak_attention_periods' => ['09:00-10:30', '14:00-15:30'],
        'recommended_break_intervals' => 90,
        'subject_optimization' => [
            'math' => 'morning',
            'arts' => 'afternoon',
            'physical_education' => 'late_morning'
        ]
    ];
}

/**
 * Get number of connected devices for real-time sync
 */
function getConnectedDevices()
{
    // Mock data - replace with actual device tracking
    return rand(15, 25);
}

/**
 * Get real-time data packets count
 */
function getRealTimePackets()
{
    // Mock data - replace with actual packet monitoring
    return rand(450, 650);
}

/**
 * Generate smart insights from data
 */
function generateSmartInsights()
{
    // Mock insights - replace with actual data analysis
    return [
        'attendance_peak_day' => 'Tuesday',
        'common_absence_reasons' => ['illness', 'family_events', 'transportation'],
        'improvement_suggestions' => [
            'Send reminder notifications on Sunday evening',
            'Implement early warning system for at-risk students',
            'Create incentive programs for perfect attendance'
        ],
        'seasonal_patterns' => [
            'winter_months' => 'Higher absence rate due to illness',
            'spring_months' => 'Improved attendance with better weather'
        ]
    ];
}

/**
 * Get active mobile sessions count
 */
function getMobileActiveSessions()
{
    // Mock data - replace with actual session tracking
    return rand(85, 120);
}

/**
 * Get API requests count for today
 */
function getApiRequestsToday()
{
    // Mock data - replace with actual API monitoring
    return rand(1200, 1800);
}

/**
 * Get blocked security attempts count
 */
function getBlockedAttempts()
{
    // Mock data - replace with actual security monitoring
    return rand(3, 12);
}

/**
 * Get active authentication tokens count
 */
function getActiveTokens()
{
    // Mock data - replace with actual token management
    return rand(45, 85);
}

/**
 * Time ago helper function
 */
function timeAgo($datetime)
{
    if (empty($datetime)) {
        return 'Never';
    }

    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $difference = time() - $timestamp;

    if ($difference < 1) {
        return 'Just now';
    }

    $periods = [
        'year' => 31536000,
        'month' => 2592000,
        'week' => 604800,
        'day' => 86400,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1
    ];

    foreach ($periods as $period => $seconds) {
        $count = floor($difference / $seconds);
        if ($count > 0) {
            return $count . ' ' . $period . ($count > 1 ? 's' : '') . ' ago';
        }
    }

    return 'Just now';
}

/**
 * Format number with suffix (K, M, B)
 */
function formatNumber($number)
{
    if ($number < 1000) {
        return number_format($number);
    } elseif ($number < 1000000) {
        return round($number / 1000, 1) . 'K';
    } elseif ($number < 1000000000) {
        return round($number / 1000000, 1) . 'M';
    } else {
        return round($number / 1000000000, 1) . 'B';
    }
}

/**
 * Get percentage change
 */
function getPercentageChange($current, $previous)
{
    if ($previous == 0) {
        return $current > 0 ? '+100%' : '0%';
    }

    $change = (($current - $previous) / $previous) * 100;
    $sign = $change >= 0 ? '+' : '';

    return $sign . round($change, 1) . '%';
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status)
{
    $badges = [
        'present' => '<span class="badge badge-success">Present</span>',
        'absent' => '<span class="badge badge-danger">Absent</span>',
        'late' => '<span class="badge badge-warning">Late</span>',
        'excused' => '<span class="badge badge-info">Excused</span>',
        'active' => '<span class="badge badge-success">Active</span>',
        'inactive' => '<span class="badge badge-secondary">Inactive</span>',
    ];

    return $badges[strtolower($status)] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Get unread message count using the active communication schema.
 */
function get_unread_message_count(int $userId, ?int $tenantId = null): int
{
    if ($userId <= 0) {
        return 0;
    }

    $tenantId = $tenantId ?? current_tenant_id();
    $tenantId = $tenantId > 0 ? $tenantId : 0;

    if (table_exists('comm_messages') && table_exists('comm_participants')) {
        $messageTenantClause = table_has_column('comm_messages', 'tenant_id') ? ' AND m.tenant_id = ?' : '';
        $participantTenantClause = table_has_column('comm_participants', 'tenant_id') ? ' AND cp.tenant_id = ?' : '';
        $readTenantClause = table_has_column('comm_reads', 'tenant_id') ? ' AND cr.tenant_id = ?' : '';

        $params = [$userId, $userId, $userId];
        if ($readTenantClause !== '') {
            $params[] = $tenantId;
        }
        if ($messageTenantClause !== '') {
            $params[] = $tenantId;
        }
        if ($participantTenantClause !== '') {
            $params[] = $tenantId;
        }

        $row = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM comm_messages m
            JOIN comm_participants cp ON m.conversation_id = cp.conversation_id
            LEFT JOIN comm_reads cr ON m.id = cr.message_id AND cr.user_id = ?{$readTenantClause}
            WHERE cp.user_id = ? AND m.sender_id != ?{$messageTenantClause}{$participantTenantClause}
              AND cr.id IS NULL
        ", $params);

        return (int)($row['count'] ?? 0);
    }

    if (table_exists('conversation_messages') && table_exists('conversation_participants')) {
        $messageTenantClause = table_has_column('conversation_messages', 'tenant_id') ? ' AND cm.tenant_id = ?' : '';
        $participantTenantClause = table_has_column('conversation_participants', 'tenant_id') ? ' AND cp.tenant_id = ?' : '';
        $hasLastReadAt = table_has_column('conversation_participants', 'last_read_at');

        $params = [$userId, $userId];
        if ($messageTenantClause !== '') {
            $params[] = $tenantId;
        }
        if ($participantTenantClause !== '') {
            $params[] = $tenantId;
        }

        $query = $hasLastReadAt
            ? "
                SELECT COUNT(*) as count
                FROM conversation_messages cm
                JOIN conversation_participants cp ON cm.conversation_id = cp.conversation_id
                WHERE cp.user_id = ? AND cm.sender_id != ?
                  AND (cp.last_read_at IS NULL OR cm.created_at > cp.last_read_at)
                  {$messageTenantClause}{$participantTenantClause}
            "
            : "
                SELECT COUNT(*) as count
                FROM conversation_messages cm
                JOIN conversation_participants cp ON cm.conversation_id = cp.conversation_id
                WHERE cp.user_id = ? AND cm.sender_id != ?{$messageTenantClause}{$participantTenantClause}
            ";

        $row = db()->fetchOne($query, $params);
        return (int)($row['count'] ?? 0);
    }

    if (table_exists('message_recipients')) {
        $recipientTenantClause = '';
        if (table_has_column('message_recipients', 'tenant_id')) {
            $recipientTenantClause = ' AND tenant_id = ?';
        } elseif (table_has_column('message_recipients', 'recipient_tenant_id')) {
            $recipientTenantClause = ' AND recipient_tenant_id = ?';
        }

        $params = [$userId];
        if ($recipientTenantClause !== '') {
            $params[] = $tenantId;
        }

        $row = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM message_recipients
            WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL{$recipientTenantClause}
        ", $params);

        return (int)($row['count'] ?? 0);
    }

    return 0;
}

/**
 * Get recent received communications using the active communication schema.
 */
function get_recent_received_communications(int $userId, ?int $tenantId = null, int $limit = 5): array
{
    if ($userId <= 0) {
        return [];
    }

    $tenantId = $tenantId ?? current_tenant_id();
    $tenantId = $tenantId > 0 ? $tenantId : 0;
    $limit = max(1, min($limit, 20));

    if (table_exists('comm_messages') && table_exists('comm_participants')) {
        $messageTenantClause = table_has_column('comm_messages', 'tenant_id') ? ' AND m.tenant_id = ?' : '';
        $participantTenantClause = table_has_column('comm_participants', 'tenant_id') ? ' AND cp.tenant_id = ?' : '';
        $readTenantClause = table_has_column('comm_reads', 'tenant_id') ? ' AND cr.tenant_id = ?' : '';
        $messagePreviewField = table_has_column('comm_messages', 'message_text')
            ? 'm.message_text'
            : (table_has_column('comm_messages', 'body') ? 'm.body' : "''");

        $params = [$userId, $userId, $userId];
        if ($readTenantClause !== '') {
            $params[] = $tenantId;
        }
        if ($messageTenantClause !== '') {
            $params[] = $tenantId;
        }
        if ($participantTenantClause !== '') {
            $params[] = $tenantId;
        }
        $params[] = $limit;

        return db()->fetchAll("
            SELECT
                m.id,
                m.conversation_id,
                m.created_at,
                LEFT({$messagePreviewField}, 120) AS subject,
                u.first_name AS sender_first,
                u.last_name AS sender_last,
                CASE WHEN cr.id IS NULL THEN 0 ELSE 1 END AS is_read,
                cr.read_at
            FROM comm_messages m
            JOIN comm_participants cp ON m.conversation_id = cp.conversation_id
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN comm_reads cr ON m.id = cr.message_id AND cr.user_id = ?{$readTenantClause}
            WHERE cp.user_id = ? AND m.sender_id != ?{$messageTenantClause}{$participantTenantClause}
            ORDER BY m.created_at DESC
            LIMIT ?
        ", $params) ?: [];
    }

    if (table_exists('messages') && table_exists('message_recipients')) {
        $recipientTenantClause = '';
        if (table_has_column('message_recipients', 'tenant_id')) {
            $recipientTenantClause = ' AND mr.tenant_id = ?';
        } elseif (table_has_column('message_recipients', 'recipient_tenant_id')) {
            $recipientTenantClause = ' AND mr.recipient_tenant_id = ?';
        }
        $messageTenantClause = table_has_column('messages', 'tenant_id') ? ' AND m.tenant_id = ?' : '';

        $params = [$userId];
        if ($recipientTenantClause !== '') {
            $params[] = $tenantId;
        }
        if ($messageTenantClause !== '') {
            $params[] = $tenantId;
        }
        $params[] = $limit;

        return db()->fetchAll("
            SELECT
                m.*,
                u.first_name AS sender_first,
                u.last_name AS sender_last,
                mr.is_read,
                mr.read_at
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            JOIN message_recipients mr ON m.id = mr.message_id
            WHERE mr.recipient_id = ?{$recipientTenantClause}{$messageTenantClause}
            ORDER BY m.created_at DESC
            LIMIT ?
        ", $params) ?: [];
    }

    return [];
}

/**
 * Get children linked to a parent account with tenant-aware fallback support.
 */
function get_parent_linked_children(int $parentId, ?int $tenantId = null): array
{
    if ($parentId <= 0) {
        return [];
    }

    $tenantId = $tenantId ?? current_tenant_id();
    $tenantId = $tenantId > 0 ? $tenantId : 0;

    $emailSelect = table_has_column('users', 'email') ? 'u.email' : 'NULL AS email';
    $userStatusClause = table_has_column('users', 'status') ? " AND u.status = 'active'" : '';
    $userTenantClause = '';
    $userTenantParams = [];
    if ($tenantId > 0) {
        if (table_has_column('users', 'tenant_id')) {
            $userTenantClause = ' AND u.tenant_id = ?';
            $userTenantParams[] = $tenantId;
        } elseif (table_has_column('users', 'school_id')) {
            $userTenantClause = ' AND u.school_id = ?';
            $userTenantParams[] = $tenantId;
        }
    }

    $classJoin = (table_exists('classes') && table_has_column('students', 'class_id'))
        ? ' LEFT JOIN classes c ON s.class_id = c.id'
        : '';
    $gradeLevelSelect = ($classJoin !== '' && table_has_column('classes', 'grade_level'))
        ? 'c.grade_level'
        : 'NULL AS grade_level';
    $classNameSelect = ($classJoin !== '' && table_has_column('classes', 'class_name'))
        ? 'c.class_name'
        : 'NULL AS class_name';
    $classCountExpr = '0 AS class_count';
    if (table_exists('class_enrollments') && table_has_column('class_enrollments', 'student_id') && table_has_column('class_enrollments', 'class_id')) {
        $classCountExpr = '(SELECT COUNT(DISTINCT ce.class_id) FROM class_enrollments ce WHERE ce.student_id IN (s.user_id, s.id)) AS class_count';
    }

    $select = "
        SELECT
            u.id,
            u.id AS user_id,
            s.id AS student_profile_id,
            CONCAT(u.first_name, ' ', u.last_name) AS child_name,
            u.first_name,
            u.last_name,
            {$emailSelect},
            s.admission_number AS student_id,
            {$gradeLevelSelect},
            {$classNameSelect},
            {$classCountExpr}
        FROM users u
        JOIN students s ON u.id = s.user_id{$classJoin}
    ";

    if (table_exists('parent_student_links')) {
        return db()->fetchAll(
            $select . "
        JOIN parent_student_links psl ON s.user_id = psl.student_id
        WHERE psl.parent_id = ?{$userStatusClause}{$userTenantClause}
        ORDER BY u.first_name, u.last_name
    ",
            array_merge([$parentId], $userTenantParams)
        ) ?: [];
    }

    if (table_has_column('users', 'parent_id')) {
        return db()->fetchAll(
            $select . "
        WHERE u.parent_id = ? AND u.role = 'student'{$userStatusClause}{$userTenantClause}
        ORDER BY u.first_name, u.last_name
    ",
            array_merge([$parentId], $userTenantParams)
        ) ?: [];
    }

    return [];
}

/**
 * Format an amount to the user preferred currency.
 */
function format_currency($amount)
{
    $c = $_SESSION['currency'] ?? 'USD';
    $sym = ['USD' => '$', 'EUR' => '�', 'GBP' => '�', 'NGN' => '?', 'KES' => 'KSh'];
    return ($sym[$c] ?? $c . ' ') . number_format((float)$amount, 2);
}
