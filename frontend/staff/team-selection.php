<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_role('staff', '../login.php');
header('Location: dashboard.php');
exit;
