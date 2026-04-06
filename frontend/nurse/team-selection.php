<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_role('nurse', '../login.php');
header('Location: dashboard.php');
exit;
