<?php
if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null) {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}

function profile_data_dir() { return __DIR__ . '/_private'; }
function profile_accounts_file() { return profile_data_dir() . '/allowed_accounts.json'; }
function profile_requests_file() { return profile_data_dir() . '/profile_requests.json'; }
function profile_notifications_file() { return profile_data_dir() . '/profile_notifications.json'; }
function profile_online_file() { return profile_data_dir() . '/online_users.json'; }
function profile_clean($text, $max = 80) { $text = trim((string)$text); $text = preg_replace('/\s+/', ' ', $text); return mb_substr($text, 0, $max); }
function profile_e($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function profile_load_json($file) { $data = json_decode(@file_get_contents($file), true); return is_array($data) ? $data : []; }
function profile_save_json($file, $data) { $dir = dirname($file); if (!is_dir($dir)) mkdir($dir, 0755, true); file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX); }
function profile_account_key() { return strtolower($_SESSION['account_key'] ?? $_SESSION['username'] ?? ''); }
function profile_display_name($key = null) { $key = $key ?: profile_account_key(); $accounts = profile_load_json(profile_accounts_file()); return $accounts[$key]['display_name'] ?? $key; }
function profile_is_admin() { return in_array(strtolower($_SESSION['account_key'] ?? $_SESSION['username'] ?? ''), ['rufus'], true) || in_array(strtolower($_SESSION['username'] ?? ''), ['rufus'], true); }
function profile_pfp_url($key = null) { $key = strtolower($key ?: profile_account_key()); $safe = preg_replace('/[^a-z0-9_-]/i', '-', $key); $file = __DIR__ . '/profile_pfps/' . $safe . '.png'; return file_exists($file) ? '/profile_pfps/' . rawurlencode($safe) . '.png?m=' . filemtime($file) : '/favicon.png'; }
function profile_touch_online() { $key = profile_account_key(); if ($key === '') return; $online = profile_load_json(profile_online_file()); $online[$key] = ['name' => profile_display_name($key), 'last_seen' => time()]; profile_save_json(profile_online_file(), $online); }
function profile_online_map() { $online = profile_load_json(profile_online_file()); $now = time(); $out = []; foreach ($online as $key => $row) { if (($row['last_seen'] ?? 0) >= $now - 120) $out[strtolower($key)] = $row; } return $out; }

function profile_permissions($key = null) {
  $key = strtolower($key ?: profile_account_key());
  $accounts = profile_load_json(profile_accounts_file());
  $account = $accounts[$key] ?? [];
  if (profile_is_admin()) {
    return [
      'send_images' => true,
      'send_popups' => true,
      'delete_messages' => true,
      'access_admin' => true
    ];
  }
  $permissions = $account['permissions'] ?? [];
  if (!is_array($permissions)) $permissions = [];
  return [
    'send_images' => !empty($permissions['send_images']),
    'send_popups' => !empty($permissions['send_popups']),
    'delete_messages' => !empty($permissions['delete_messages']),
    'access_admin' => !empty($permissions['access_admin'])
  ];
}
function profile_can($permission, $key = null) {
  $permissions = profile_permissions($key);
  return !empty($permissions[$permission]);
}
function profile_can_access_admin($key = null) { return profile_is_admin() || profile_can('access_admin', $key); }
function profile_can_send_images($key = null) { return profile_is_admin() || profile_can('send_images', $key); }
function profile_can_send_popups($key = null) { return profile_is_admin() || profile_can('send_popups', $key); }
function profile_can_delete_messages($key = null) { return profile_is_admin() || profile_can('delete_messages', $key); }
function profile_all_accounts() { return profile_load_json(profile_accounts_file()); }

// ===== CSRF protection (Bug #6 fix) =====
// Generate a per-session CSRF token. Stored in $_SESSION['csrf_token'].
// Use profile_csrf_token() to fetch the token for embedding in forms,
// and profile_csrf_verify() to validate it on POST requests.
function profile_csrf_token() {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
  }
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

// Verify the CSRF token from a POST request. Call this at the top of every
// admin POST handler. Returns true if valid, false (and exits with 403) if not.
function profile_csrf_verify() {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
  }
  $expected = $_SESSION['csrf_token'] ?? '';
  $received = $_POST['csrf_token'] ?? '';
  if ($expected === '' || !hash_equals($expected, $received)) {
    http_response_code(403);
    echo 'CSRF token validation failed.';
    exit;
  }
  return true;
}
?>