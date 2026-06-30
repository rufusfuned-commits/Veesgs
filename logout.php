<?php
session_start();
require_once __DIR__ . '/ban_helpers.php';

$currentUser = $_SESSION['username'] ?? '';

if ($currentUser !== '' && get_active_ban($currentUser)) {
  header('Location: /banned.php');
  exit;
}

session_destroy();
header('Location: /');
exit;
?>
