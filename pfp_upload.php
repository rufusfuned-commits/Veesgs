<?php
// ============================================================
// Profile Picture Upload + Moderation Endpoint
// ============================================================
// Users can upload a profile picture. The upload goes to a moderation
// queue (_private/pfp_requests.json). An admin approves or denies it
// via pfp_admin.php. Approved pictures are moved to /profile_pfps/
// and become the user's active pfp.
//
// Actions (all via GET ?action=...):
//   upload    (POST) — user uploads a new picture (multipart/form-data)
//   approve   (GET)  — admin approves a pending request (moves to profile_pfps/)
//   deny      (GET)  — admin denies a pending request (deletes the temp file)
//   status    (GET)  — user checks their pending request status
// ============================================================

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
if ($key === '' || $key === 'guest') {
  echo json_encode(['error' => 'Must be logged in to upload a profile picture.']);
  exit;
}

$dataDir  = __DIR__ . '/_private';
$queueFile = $dataDir . '/pfp_requests.json';
$tempDir   = __DIR__ . '/pfp_temp';
$pfpDir    = __DIR__ . '/profile_pfps';

if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);
if (!is_dir($pfpDir))  @mkdir($pfpDir, 0755, true);

$action = $_GET['action'] ?? '';

// ---------- UPLOAD (user) ----------
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_FILES['pfp']) || !is_uploaded_file($_FILES['pfp']['tmp_name'])) {
    echo json_encode(['error' => 'No file uploaded.']);
    exit;
  }
  $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
  $mime = mime_content_type($_FILES['pfp']['tmp_name']);
  if (!isset($allowed[$mime])) {
    echo json_encode(['error' => 'Image must be PNG, JPG, WEBP, or GIF.']);
    exit;
  }
  // Max 2MB
  if ($_FILES['pfp']['size'] > 2097152) {
    echo json_encode(['error' => 'Image too large (max 2MB).']);
    exit;
  }

  // Save to temp dir with a unique name
  $tempName = 'pfp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
  $tempPath = $tempDir . '/' . $tempName;
  if (!move_uploaded_file($_FILES['pfp']['tmp_name'], $tempPath)) {
    echo json_encode(['error' => 'Failed to save uploaded file.']);
    exit;
  }
  $tempUrl = '/pfp_temp/' . rawurlencode($tempName);

  // Add to moderation queue
  $queue = profile_load_json($queueFile);
  // Remove any previous pending request from this user
  foreach ($queue as $i => $r) {
    if (($r['account_key'] ?? '') === $key) {
      // Delete old temp file
      if (!empty($r['temp_file'])) {
        $oldPath = $tempDir . '/' . basename($r['temp_file']);
        if (file_exists($oldPath)) @unlink($oldPath);
      }
      unset($queue[$i]);
    }
  }
  $queue[] = [
    'account_key'  => $key,
    'display_name' => profile_display_name($key),
    'temp_file'    => $tempName,
    'temp_url'     => $tempUrl,
    'requested_at' => time(),
    'requested_at_text' => date('Y-m-d H:i:s'),
  ];
  $queue = array_values($queue);
  profile_save_json($queueFile, $queue);

  echo json_encode(['ok' => true, 'message' => 'Profile picture uploaded. An admin will review it shortly.']);
  exit;
}

// ---------- APPROVE (admin) ----------
if ($action === 'approve') {
  if (!profile_is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin only.']);
    exit;
  }
  $index = (int)($_GET['index'] ?? -1);
  $queue = profile_load_json($queueFile);
  if ($index < 0 || $index >= count($queue)) {
    echo json_encode(['error' => 'Invalid request index.']);
    exit;
  }
  $req = $queue[$index];
  $tempPath = $tempDir . '/' . basename($req['temp_file'] ?? '');
  if (!file_exists($tempPath)) {
    echo json_encode(['error' => 'Temp file no longer exists.']);
    exit;
  }
  // Move to profile_pfps/ with the user's safe key name
  $safeKey = preg_replace('/[^a-z0-9_-]/i', '-', $req['account_key']);
  $finalPath = $pfpDir . '/' . $safeKey . '.png';
  // Delete old pfp if exists, then move
  if (file_exists($finalPath)) @unlink($finalPath);
  if (!rename($tempPath, $finalPath)) {
    // If rename fails (cross-device), try copy + unlink
    if (!copy($tempPath, $finalPath)) {
      echo json_encode(['error' => 'Failed to move approved file.']);
      exit;
    }
    @unlink($tempPath);
  }
  // Remove from queue
  unset($queue[$index]);
  $queue = array_values($queue);
  profile_save_json($queueFile, $queue);

  echo json_encode(['ok' => true, 'message' => 'Profile picture approved.']);
  exit;
}

// ---------- DENY (admin) ----------
if ($action === 'deny') {
  if (!profile_is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin only.']);
    exit;
  }
  $index = (int)($_GET['index'] ?? -1);
  $queue = profile_load_json($queueFile);
  if ($index < 0 || $index >= count($queue)) {
    echo json_encode(['error' => 'Invalid request index.']);
    exit;
  }
  $req = $queue[$index];
  // Delete temp file
  if (!empty($req['temp_file'])) {
    $tempPath = $tempDir . '/' . basename($req['temp_file']);
    if (file_exists($tempPath)) @unlink($tempPath);
  }
  // Remove from queue
  unset($queue[$index]);
  $queue = array_values($queue);
  profile_save_json($queueFile, $queue);

  echo json_encode(['ok' => true, 'message' => 'Profile picture denied and removed.']);
  exit;
}

// ---------- STATUS (user checks their own request) ----------
if ($action === 'status') {
  $queue = profile_load_json($queueFile);
  $pending = null;
  foreach ($queue as $r) {
    if (($r['account_key'] ?? '') === $key) {
      $pending = $r;
      break;
    }
  }
  $currentPfp = profile_pfp_url($key);
  echo json_encode([
    'ok' => true,
    'pending' => $pending,
    'current_pfp' => $currentPfp,
  ]);
  exit;
}

echo json_encode(['error' => 'Invalid action.']);
