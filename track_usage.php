<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['error' => 'POST only']);
  exit;
}

$type = $_POST['type'] ?? '';
$name = $_POST['name'] ?? '';

if ($type === '' || $name === '') {
  echo json_encode(['error' => 'Missing type or name']);
  exit;
}

$usageFile = profile_data_dir() . '/usage_data.json';

// Bug #8 fix: flock-protected read-modify-write to prevent lost increments
// when two users open the same game simultaneously.
$lockFile = $usageFile . '.lock';
$lockHandle = @fopen($lockFile, 'c');
if ($lockHandle) {
  flock($lockHandle, LOCK_EX);
}
$usageData = profile_load_json($usageFile);

$key = $type . ':' . $name;
$usageData[$key] = ($usageData[$key] ?? 0) + 1;

profile_save_json($usageFile, $usageData);

if ($lockHandle) {
  flock($lockHandle, LOCK_UN);
  fclose($lockHandle);
}

echo json_encode(['ok' => true]);
?>