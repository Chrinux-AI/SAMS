<?php
if (!isset($GLOBALS['accountant_shell_buffer_active']) || $GLOBALS['accountant_shell_buffer_active'] !== true) {
  return;
}

$page_content = ob_get_clean();
$GLOBALS['accountant_shell_buffer_active'] = false;

$page_title = (string)($page_title ?? 'Accountant');
$page_subtitle = (string)($page_subtitle ?? 'Finance command center');
$page_icon = (string)($page_icon ?? 'account_balance');

require_once dirname(__DIR__, 2) . '/resources/ui-core/layouts/master-dashboard.php';
