<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';
header('Content-Type: application/json; charset=utf-8');

$key = profile_account_key();

$file = profile_data_dir() . '/admin_broadcasts.json';
$data = profile_load_json($file);
$action = $_GET['action'] ?? 'check';

// ---------- DISMISS (mark as seen + dismissed, persistent) ----------
if ($action === 'dismiss_once') {
  $id = $_GET['id'] ?? '';
  if (isset($data[$id])) {
    $data[$id]['dismissed'][$key] = time();
    $data[$id]['seen'][$key] = time();
    profile_save_json($file, $data);
  }
  echo json_encode(['ok' => true]);
  exit;
}

// ---------- SEEN (mark as seen — used for every_launch + show_now) ----------
if ($action === 'seen') {
  $id = $_GET['id'] ?? '';
  if (isset($data[$id])) {
    $data[$id]['seen'][$key] = time();
    profile_save_json($file, $data);
  }
  echo json_encode(['ok' => true]);
  exit;
}

// ---------- CHECK (find a popup to show this user) ----------
// Behaviour per mode:
//   every_launch    -> show every page load (guests too). seen[] still recorded.
//   once_on_launch  -> show only once per user (per session_key). Skipped for guests.
//   show_now        -> show immediately to all users (guests too) who haven't seen it yet.
//
// Iteration order: newest first (array_reverse).
foreach (array_reverse($data, true) as $id => $b) {
  $active = $b['active'] ?? true;
  if (!$active) continue;

  $mode = $b['mode'] ?? 'every_launch';
  $seen = $b['seen'] ?? [];

  if ($mode === 'show_now') {
    // Show Now: show to EVERYONE (including guests) who hasn't seen it.
    // Once dismissed, never show again (use dismissed[] as the persistent block).
    $dismissed = $b['dismissed'] ?? [];
    if (isset($dismissed[$key])) continue;
    echo json_encode([
      'id'          => $id,
      'title'       => $b['title'] ?? '',
      'message'     => $b['message'] ?? '',
      'button_text' => $b['button_text'] ?? 'Got it',
      'image'       => $b['image'] ?? '',
      'mode'        => 'show_now'
    ]);
    exit;
  }

  if ($mode === 'once_on_launch') {
    // Once on Launch: show once per user. Skip guests (no persistent key).
    if ($key === 'guest') continue;
    if (isset($seen[$key])) continue;
    echo json_encode([
      'id'          => $id,
      'title'       => $b['title'] ?? '',
      'message'     => $b['message'] ?? '',
      'button_text' => $b['button_text'] ?? 'Got it',
      'image'       => $b['image'] ?? '',
      'mode'        => 'once_on_launch'
    ]);
    exit;
  }

  // every_launch: show every page load to everyone (including guests).
  // We don't gate on the recipients[] list — that was a bug in the
  // previous version (recipients was only populated at create time
  // with the online list, so users who came online later never saw it).
  echo json_encode([
    'id'          => $id,
    'title'       => $b['title'] ?? '',
    'message'     => $b['message'] ?? '',
    'button_text' => $b['button_text'] ?? 'Got it',
    'image'       => $b['image'] ?? '',
    'mode'        => 'every_launch'
  ]);
  exit;
}

echo json_encode(['message' => null]);
