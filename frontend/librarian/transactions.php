<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_role('librarian', '../login.php');
header('Location: active-loans.php');
exit;
