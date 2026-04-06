<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_role('forum_moderator', '../login.php');

$threadId = (int)($_GET['id'] ?? 0);
if ($threadId <= 0) {
  header('Location: threads.php');
  exit;
}

header('Location: ../forum/thread.php?id=' . $threadId);
exit;
