<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

// Redirect to universal messaging page
header('Location: ../messages.php');
exit;
