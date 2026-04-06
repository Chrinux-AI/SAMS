<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_parent('../login.php');

$studentId = (int)($_GET['student_id'] ?? 0);
$target = 'children.php';
if ($studentId > 0) {
  $target .= '?student_id=' . $studentId;
}

header('Location: ' . $target);
exit;
