<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';
header('Content-Type: application/json');
$key = profile_account_key();
$file = profile_notifications_file();
$notes = profile_load_json($file);
if (($_GET['action'] ?? '') === 'clear') {
  unset($notes[$key]);
  profile_save_json($file, $notes);
  echo json_encode(['ok' => true]);
  exit;
}
$list = $notes[$key] ?? [];
echo json_encode(['message' => $list[0]['message'] ?? null]);
?>
