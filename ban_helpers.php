<?php
if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null) {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}

// Shared account-ban helpers. Bans are by account name, not IP.

function ban_data_dir() {
  return __DIR__ . '/_private';
}

function banned_accounts_file() {
  return ban_data_dir() . '/banned_accounts.json';
}

function ensure_ban_files() {
  $dataDir = ban_data_dir();
  $banFile = banned_accounts_file();
  $htaccessFile = $dataDir . '/.htaccess';

  if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
  }

  if (!file_exists($htaccessFile)) {
    file_put_contents($htaccessFile, "Require all denied\nDeny from all\n");
  }

  if (!file_exists($banFile)) {
    file_put_contents($banFile, '{}');
  }
}

function load_banned_accounts() {
  ensure_ban_files();
  $data = json_decode(@file_get_contents(banned_accounts_file()), true);
  return is_array($data) ? $data : [];
}

function save_banned_accounts($bans) {
  ensure_ban_files();
  file_put_contents(banned_accounts_file(), json_encode($bans, JSON_PRETTY_PRINT), LOCK_EX);
}

function ban_key($name) {
  return strtolower(trim((string) $name));
}

function clean_ban_text($text, $maxLength = 500) {
  $text = trim((string) $text);
  $text = str_replace(["\r", "\n", "|"], ' ', $text);
  $text = preg_replace('/\s+/', ' ', $text);
  return mb_substr($text, 0, $maxLength);
}

function prune_expired_bans() {
  $bans = load_banned_accounts();
  $now = time();
  $changed = false;

  foreach ($bans as $key => $ban) {
    $until = (int)($ban['banned_until'] ?? 0);
    if ($until > 0 && $until <= $now) {
      unset($bans[$key]);
      $changed = true;
    }
  }

  if ($changed) {
    save_banned_accounts($bans);
  }

  return $bans;
}

function get_active_ban($name) {
  $key = ban_key($name);
  if ($key === '') return null;

  $bans = prune_expired_bans();
  return $bans[$key] ?? null;
}

function format_seconds_left($seconds) {
  $seconds = max(0, (int)$seconds);
  $days = intdiv($seconds, 86400);
  $seconds %= 86400;
  $hours = intdiv($seconds, 3600);
  $seconds %= 3600;
  $minutes = intdiv($seconds, 60);
  $seconds %= 60;

  if ($days > 0) {
    return $days . 'd ' . $hours . 'h ' . $minutes . 'm';
  }
  if ($hours > 0) {
    return $hours . 'h ' . $minutes . 'm ' . $seconds . 's';
  }
  if ($minutes > 0) {
    return $minutes . 'm ' . $seconds . 's';
  }
  return $seconds . 's';
}


function save_deleted_chat_log($username, $message, $reason = 'Filtered chat message') {
  ensure_ban_files();
  $logFile = ban_data_dir() . '/deleted_message_log.txt';
  $line = date('Y-m-d H:i:s') . ' | ' . clean_ban_text($username, 40) . ' | ' . clean_ban_text($reason, 200) . ' | ' . clean_ban_text($message, 1000) . PHP_EOL;
  file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function set_account_ban($name, $seconds, $message = 'You are banned from this website.', $bannedBy = 'system', $extra = []) {
  $accountName = clean_ban_text($name, 40);
  $key = ban_key($accountName);
  if ($key === '') return false;

  $seconds = max(1, (int)$seconds);
  $bans = prune_expired_bans();
  $until = time() + $seconds;

  $bans[$key] = array_merge([
    'name' => $accountName,
    'message' => clean_ban_text($message, 500),
    'banned_at' => time(),
    'banned_at_text' => date('Y-m-d H:i:s'),
    'banned_until' => $until,
    'banned_until_text' => date('Y-m-d H:i:s', $until),
    'banned_by' => clean_ban_text($bannedBy, 40)
  ], $extra);

  save_banned_accounts($bans);
  return true;
}

function extend_account_ban($name, $extraSeconds, $extraMessage = '') {
  $key = ban_key($name);
  if ($key === '') return false;

  $bans = prune_expired_bans();
  if (!isset($bans[$key])) return false;

  $extraSeconds = max(1, (int)$extraSeconds);
  $currentUntil = max(time(), (int)($bans[$key]['banned_until'] ?? time()));
  $newUntil = $currentUntil + $extraSeconds;

  $bans[$key]['banned_until'] = $newUntil;
  $bans[$key]['banned_until_text'] = date('Y-m-d H:i:s', $newUntil);
  $bans[$key]['last_extended_at'] = time();
  $bans[$key]['last_extended_at_text'] = date('Y-m-d H:i:s');

  if ($extraMessage !== '') {
    $bans[$key]['message'] = clean_ban_text($extraMessage, 500);
  }

  save_banned_accounts($bans);
  return true;
}

?>
