<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect to new enhanced chat system
header('Location: chat.php');
exit;
?>
