<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_student('../login.php');
header('Location: ../communication/conversations.php');
exit;
