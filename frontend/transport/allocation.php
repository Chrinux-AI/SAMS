<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_role('transport', '../login.php');
header('Location: student-allocation.php');
exit;
