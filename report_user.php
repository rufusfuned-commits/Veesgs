<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

date_default_timezone_set('Europe/London');

function collect_report_context($room, $reported, $reporter) {
  $file = __DIR__ . '/_private/messages.txt';
  $context = [];
  if (!file_exists($file)) return $context;

  $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  foreach ($lines as $line) {
    $parts = explode('|', $line, 7);
    if (count($parts) < 7) continue;
    $msgRoom = profile_clean($parts[4] ?? '', 120);
    if ($msgRoom !== $room) continue;
    $key = strtolower(profile_clean($parts[2] ?? '', 40));
    if ($key !== strtolower($reported) && $key !== strtolower($reporter)) continue;
    $context[] = [
      'time' => date('Y-m-d H:i:s', (int)($parts[0] ?? time())),
      'user' => $key,
      'display_name' => profile_clean($parts[3] ?? $key, 40),
      'message' => profile_clean($parts[5] ?? '', 800),
      'image' => profile_clean($parts[6] ?? '', 240)
    ];
  }
  return array_slice($context, -30);
}

$file = profile_data_dir() . '/user_reports.json';
$reports = profile_load_json($file);
$reported = strtolower(profile_clean($_POST['reported_user'] ?? '', 60));
$room = profile_clean($_POST['room'] ?? 'group', 120);
$from = strtolower(profile_account_key());

if ($reported !== '' && $reported !== $from) {
  $reports[] = [
    'id' => time() . '_' . bin2hex(random_bytes(4)),
    'date' => date('Y-m-d'),
    'time' => date('H:i:s'),
    'from' => $from,
    'from_display' => profile_display_name($from),
    'reported_user' => $reported,
    'reported_display' => profile_display_name($reported),
    'room' => $room,
    'status' => 'new',
    'messages' => collect_report_context($room, $reported, $from)
  ];
  profile_save_json($file, $reports);
}
header('Location: /message-board.php?room=' . urlencode($room));
exit;
?>