<?php
$page_title = $page_title ?? 'Accountant';
$page_subtitle = $page_subtitle ?? '';
$page_icon = $page_icon ?? 'account_balance';
$activeTab = $activeTab ?? 'dashboard';

if (!function_exists('accountant_currency_symbol')) {
  function accountant_currency_symbol(): string
  {
    return '&#8358;';
  }
}

if (!function_exists('accountant_currency')) {
  function accountant_currency($amount, int $decimals = 2): string
  {
    return accountant_currency_symbol() . number_format((float)$amount, $decimals);
  }
}

if (!isset($GLOBALS['accountant_shell_buffer_active']) || $GLOBALS['accountant_shell_buffer_active'] !== true) {
  $GLOBALS['accountant_shell_buffer_active'] = true;
  ob_start();
}
