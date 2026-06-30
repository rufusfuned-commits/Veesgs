<?php
// ============================================================
// Lunaach Chat API — InfinityFree-safe version
// ============================================================
// Hardening notes (InfinityFree-specific):
//   * NO session_start() — InfinityFree's iFastNet WAF often 302/403s
//     API endpoints that start a PHP session without a recognised
//     browser session cookie. The chat is name-based (no login
//     required for guests), so we don't need sessions here.
//   * NO Access-Control-Allow-* headers sent via header() — IF's
//     anti-bot proxy can misinterpret these as suspicious.
//   * All file operations are guarded with @ + is_writable checks.
//   * Daily wipe writes are skipped if the directory isn't writable.
//   * Storage layout (unchanged from v2):
//       unix_ts|HH:MM:SS|sender_id|conversation|display_name|message
//     Legacy lines "HH:MM:SS | name | message" are still readable.
// ============================================================

// mb_substr polyfill in case mbstring extension is not available
if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null) {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}

// Single Content-Type header. No CORS headers — same-origin only.
header('Content-Type: application/json; charset=utf-8');

// Hard-stop on OPTIONS preflight (CORS preflight should never hit this
// same-origin endpoint, but be defensive).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// Storage paths — all under _private/
$dataDir      = __DIR__ . '/_private';
$chatFile     = $dataDir . '/chat_messages.txt';
$chatLogFile  = $dataDir . '/chat_archive.txt';
$lastWipeFile = $dataDir . '/last_chat_wipe.txt';

// Make sure _private exists. If we can't create it, we still respond
// (with empty data) so the frontend doesn't see a 403.
if (!is_dir($dataDir)) {
  @mkdir($dataDir, 0755, true);
}

// ---------- helpers ----------
function clean_text($text, $maxLength = 500) {
  $text = trim((string)$text);
  $text = str_replace(["\r", "\n", "\t", "|"], ' ', $text);
  // Bug #16 fix: Strip '__' from text so usernames can't break DM conversation
  // keys (which use '__' as the separator between the two participants).
  $text = str_replace('__', '_', $text);
  $text = preg_replace('/\s+/', ' ', $text);
  return mb_substr($text, 0, $maxLength);
}

// Daily wipe: archive yesterday's chat and start fresh. All file ops
// are silent-fail so an unwritable _private never crashes the API.
function check_daily_wipe($chatFile, $chatLogFile, $lastWipeFile) {
  if (!is_writable(dirname($chatFile))) return;

  $lastWipe = trim((string)@file_get_contents($lastWipeFile));
  $today    = date('Y-m-d');

  if ($lastWipe !== $today) {
    // Bug #9 fix: Lock the chat file during the archive + truncate sequence
    // so concurrent message writes can't slip in between archive and truncate
    // (which would silently lose those messages).
    $lockHandle = @fopen($chatFile, 'c');
    if ($lockHandle) flock($lockHandle, LOCK_EX);

    if (is_file($chatFile) && filesize($chatFile) > 0) {
      $archiveContent = "\n=== Chat Archive for " . $lastWipe . " ===\n";
      $archiveContent .= (string)@file_get_contents($chatFile);
      @file_put_contents($chatLogFile, $archiveContent, FILE_APPEND | LOCK_EX);
    }
    @file_put_contents($chatFile, '');
    @file_put_contents($lastWipeFile, $today);

    if ($lockHandle) {
      flock($lockHandle, LOCK_UN);
      fclose($lockHandle);
    }
  }
}

// Stable conversation key:
//   "group"                    — shared room
//   "dm:<a>__<b>" (sorted)     — direct message between a and b
function conversation_key($sender, $recipient) {
  $sender    = strtolower(trim((string)$sender));
  $recipient = strtolower(trim((string)$recipient));
  if ($recipient === '' || $recipient === 'group') return 'group';
  if ($recipient === $sender) return 'group';
  $parts = [$sender, $recipient];
  sort($parts);
  return 'dm:' . implode('__', $parts);
}

// Parse one storage line into a structured message.
// Returns null for unparseable lines.
function parse_chat_line($line) {
  $parts = explode('|', $line, 6);
  if (count($parts) === 6) {
    return [
      'ts'      => (int)$parts[0],
      'time'    => $parts[1],
      'sender'  => $parts[2],
      'conv'    => $parts[3],
      'name'    => $parts[4],
      'message' => $parts[5],
    ];
  }
  // Legacy format: "HH:MM:SS | name | message"
  if (preg_match('/^(\d{2}:\d{2}:\d{2}) \| ([^|]+) \| (.+)$/', $line, $m)) {
    return [
      'ts'      => 0,
      'time'    => $m[1],
      'sender'  => strtolower(trim($m[2])),
      'conv'    => 'group',
      'name'    => trim($m[2]),
      'message' => trim($m[3]),
    ];
  }
  return null;
}

// ---------- dispatch ----------
$action = $_GET['action'] ?? '';

// ===== SEND =====
if ($action === 'send') {
  $name    = isset($_POST['name'])    ? clean_text($_POST['name'], 20)     : '';
  $message = isset($_POST['message']) ? clean_text($_POST['message'], 500) : '';
  $to      = isset($_POST['to'])      ? clean_text($_POST['to'], 20)       : 'group';

  if ($name === '' || $message === '') {
    echo json_encode(['error' => 'Name and message required']);
    exit;
  }

  check_daily_wipe($chatFile, $chatLogFile, $lastWipeFile);

  $conv      = conversation_key($name, $to);
  $timestamp = time();
  $timeStr   = date('H:i:s', $timestamp);
  $senderId  = strtolower($name);

  $line = $timestamp . '|' . $timeStr . '|' . $senderId . '|' . $conv . '|' . $name . '|' . $message . PHP_EOL;

  $result = @file_put_contents($chatFile, $line, FILE_APPEND | LOCK_EX);

  if ($result === false) {
    // Last-ditch: try to create the file with 0644 permissions.
    @touch($chatFile);
    @chmod($chatFile, 0644);
    $result = @file_put_contents($chatFile, $line, FILE_APPEND | LOCK_EX);
  }

  if ($result === false) {
    echo json_encode(['error' => 'Failed to write message']);
    exit;
  }

  echo json_encode(['success' => true, 'conversation' => $conv]);
  exit;
}

// ===== GET (messages in a conversation) =====
if ($action === 'get') {
  check_daily_wipe($chatFile, $chatLogFile, $lastWipeFile);

  $name         = isset($_GET['name']) ? clean_text($_GET['name'], 20) : '';
  $conversation = isset($_GET['conversation']) ? $_GET['conversation'] : 'group';

  // Validate / normalise conversation key
  if ($conversation !== 'group') {
    if (strpos($conversation, 'dm:') === 0) {
      $parts = explode('__', substr($conversation, 3));
      if (count($parts) !== 2) {
        echo json_encode(['messages' => []]);
        exit;
      }
      $postedUser = strtolower($name);
      // Authorise: posted name must be one of the two DM participants
      if ($postedUser === '' || !in_array($postedUser, $parts, true)) {
        echo json_encode(['messages' => []]);
        exit;
      }
    } else {
      $conversation = 'group';
    }
  }

  if (!is_file($chatFile)) {
    echo json_encode(['messages' => []]);
    exit;
  }

  $messages = [];
  $lines = @file($chatFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if ($lines === false) $lines = [];

  foreach ($lines as $line) {
    $parsed = parse_chat_line($line);
    if ($parsed === null) continue;
    if ($parsed['conv'] !== $conversation) continue;
    $messages[] = [
      'ts'      => $parsed['ts'],
      'time'    => $parsed['time'],
      'sender'  => $parsed['sender'],
      'name'    => $parsed['name'],
      'message' => $parsed['message'],
      // Feature 4: include the sender's profile picture URL
      'pfp'     => profile_pfp_url($parsed['sender']),
    ];
  }

  echo json_encode(['messages' => $messages]);
  exit;
}

// ===== CONVERSATIONS (list for current user) =====
if ($action === 'conversations') {
  check_daily_wipe($chatFile, $chatLogFile, $lastWipeFile);

  $name      = isset($_GET['name']) ? clean_text($_GET['name'], 20) : '';
  $userLower = strtolower($name);

  // Bug #4 fix: Security — never leak the DM list when we can't identify the
  // user. Previously an empty name returned ALL DMs with previews. Now we
  // return only the group chat entry.
  if ($userLower === '') {
    echo json_encode([
      'conversations' => [[
        'key'          => 'group',
        'label'        => 'Group Chat',
        'last_time'    => '',
        'last_message' => '',
        'unread'       => 0
      ]],
      'current_user' => ''
    ]);
    exit;
  }

  $groupLast = ['', '', 0]; // [time, message, ts]
  $dmMap     = [];          // conv_key => [label, last_time, last_message, ts]

  if (is_file($chatFile)) {
    $lines = @file($chatFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
      $parsed = parse_chat_line($line);
      if ($parsed === null) continue;
      $ts   = $parsed['ts'];
      $conv = $parsed['conv'];
      $time = $parsed['time'];
      $msg  = $parsed['message'];
      if ($conv === 'group') {
        if ($ts >= $groupLast[2]) {
          $groupLast = [$time, $msg, $ts];
        }
      } elseif (strpos($conv, 'dm:') === 0) {
        $users = explode('__', substr($conv, 3));
        if (count($users) === 2 && ($userLower === '' || in_array($userLower, $users, true))) {
          $other = ($users[0] === $userLower) ? $users[1] : $users[0];
          if (!isset($dmMap[$conv]) || $ts >= $dmMap[$conv]['ts']) {
            $dmMap[$conv] = [
              'key'          => $conv,
              'label'        => $other,
              'last_time'    => $time,
              'last_message' => $msg,
              'ts'           => $ts,
            ];
          }
        }
      }
    }
  }

  $convs = [[
    'key'          => 'group',
    'label'        => 'Group Chat',
    'last_time'    => $groupLast[0],
    'last_message' => $groupLast[1],
    'unread'       => 0,
  ]];

  $dms = array_values($dmMap);
  usort($dms, function($a, $b) { return $b['ts'] - $a['ts']; });
  foreach ($dms as $dm) {
    $convs[] = [
      'key'          => $dm['key'],
      'label'        => $dm['label'],
      'last_time'    => $dm['last_time'],
      'last_message' => $dm['last_message'],
      'pfp'          => profile_pfp_url($dm['label']), // Phase 5: include pfp for DM toast
      'unread'       => 0,
    ];
  }

  echo json_encode([
    'conversations' => $convs,
    'current_user'  => $userLower,
  ]);
  exit;
}

// Unknown action
echo json_encode(['error' => 'Invalid action']);
