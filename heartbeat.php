<?php
// Heartbeat endpoint — keeps the online-users list fresh while a user is
// actively on the page. The homepage polls this every 60 seconds.
//
// InfinityFree hardening (same as message_api.php / online_count.php):
//   * NO Access-Control-Allow-* headers (same-origin only)
//   * NO session_start() — but we DO need session here to identify the user.
//     InfinityFree's WAF was OK with session_start() on this endpoint in
//     testing because the user already has a browser session cookie from
//     loading index.php (so it's not a "cold" session bootstrap).
//   * All file writes are @-guarded.

if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

require_once __DIR__ . '/profile_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$key = profile_account_key();

// Only update for logged-in users (skip guests — they have no persistent key
// and we don't want to bloat online_users.json with guest entries).
if ($key === '' || $key === 'guest') {
  echo json_encode(['ok' => true, 'online' => false]);
  exit;
}

// Touch the user's last_seen timestamp.
profile_touch_online();

// Return the current online count so the homepage can update the pill without
// a separate request to online_count.php.
$online = profile_online_map();
echo json_encode([
  'ok' => true,
  'online' => true,
  'count' => count($online),
]);
