<?php

/**
 * Logout Script
 */

session_start();

require_once 'frontend/includes/config.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/database.php';

$logoutUserId = (int)($_SESSION['user_id'] ?? 0);
$logoutEmail = (string)($_SESSION['email'] ?? '');

// Log activity before destroying session
if ($logoutUserId > 0) {
    log_activity($logoutUserId, 'logout', 'user', $logoutUserId);
}

// Destroy session completely so all tabs lose the shared browser session.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

// Broadcast the logout to any currently open tabs in the same browser.
$logoutPayload = json_encode([
    'event' => 'logout',
    'user_id' => $logoutUserId,
    'email' => $logoutEmail,
    'timestamp' => time(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$loginUrl = rtrim(APP_URL, '/') . '/login.php?logged_out=1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($loginUrl); ?>">
    <title>Logging out...</title>
    <script>
        (function() {
            var payload = <?php echo $logoutPayload ?: 'null'; ?>;
            try {
                localStorage.setItem('sams-auth-event', JSON.stringify(payload));
            } catch (e) {}

            try {
                if ('BroadcastChannel' in window) {
                    var channel = new BroadcastChannel('sams-auth');
                    channel.postMessage(payload);
                    channel.close();
                }
            } catch (e) {}

            window.location.replace(<?php echo json_encode($loginUrl); ?>);
        })();
    </script>
</head>

<body>
    <p>Logging out...</p>
</body>

</html>
