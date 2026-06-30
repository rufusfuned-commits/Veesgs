<?php
// ============================================================
// Lunaach Online Count API — InfinityFree-safe version
// ============================================================
// Returns: {"count": <int>, "names": [<string>, ...]}
//
// Reads the online_users.json file written by profile_touch_online()
// in profile_helpers.php. A user is "online" if their last_seen
// timestamp is within the last 120 seconds.
//
// InfinityFree hardening:
//   * NO session_start() — same reason as message_api.php.
//   * NO CORS headers — same-origin only.
//   * All file reads are @-guarded so an unwritable _private never
//     crashes the API.
// ============================================================

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$onlineFile = __DIR__ . '/_private/online_users.json';

// Load online users map. Default to empty array on any failure.
$online = [];
if (is_file($onlineFile)) {
  $raw  = (string)@file_get_contents($onlineFile);
  $data = json_decode($raw, true);
  if (is_array($data)) $online = $data;
}

// A user is online if last_seen is within 120s of now.
$now    = time();
$cutoff = $now - 120;
$count  = 0;
$names  = [];
foreach ($online as $key => $row) {
  if (!is_array($row)) continue;
  $lastSeen = (int)($row['last_seen'] ?? 0);
  if ($lastSeen >= $cutoff) {
    $count++;
    $names[] = (string)($row['name'] ?? $key);
  }
}

echo json_encode([
  'count' => $count,
  'names' => $names,
]);
