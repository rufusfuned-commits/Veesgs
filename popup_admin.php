<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';
if (!profile_can_send_popups()) { http_response_code(403); echo 'No access.'; exit; }

if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null) {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}

$file = profile_data_dir() . '/admin_broadcasts.json';
$broadcasts = profile_load_json($file);
$status = '';
$onlineNow = profile_online_map();

function clean_popup_text($text, $max = 200) {
  $text = trim((string)$text);
  $text = preg_replace('/\s+/', ' ', $text);
  return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
}
function normalize_mode($raw) {
  $raw = (string)$raw;
  if ($raw === 'once_on_launch') return 'once_on_launch';
  if ($raw === 'show_now') return 'show_now';
  return 'every_launch';
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  profile_csrf_verify(); // Bug #6 fix: CSRF protection
  $action = $_POST['action'] ?? 'create';

  // DELETE
  if ($action === 'delete') {
    $deleteId = $_POST['delete_id'] ?? '';
    if ($deleteId !== '' && isset($broadcasts[$deleteId])) {
      // Optionally remove the image file
      if (!empty($broadcasts[$deleteId]['image'])) {
        $oldPath = __DIR__ . parse_url($broadcasts[$deleteId]['image'], PHP_URL_PATH);
        if (file_exists($oldPath)) @unlink($oldPath);
      }
      unset($broadcasts[$deleteId]);
      profile_save_json($file, $broadcasts);
      $status = 'Pop-up deleted.';
    } else {
      $status = 'Pop-up not found.';
    }
  }
  // TOGGLE ENABLE/DISABLE
  elseif ($action === 'toggle') {
    $toggleId = $_POST['toggle_id'] ?? '';
    if ($toggleId !== '' && isset($broadcasts[$toggleId])) {
      $broadcasts[$toggleId]['active'] = !($broadcasts[$toggleId]['active'] ?? true);
      $broadcasts[$toggleId]['updated_at'] = time();
      $broadcasts[$toggleId]['updated_at_text'] = date('Y-m-d H:i:s');
      profile_save_json($file, $broadcasts);
      $status = 'Pop-up ' . ($broadcasts[$toggleId]['active'] ? 'enabled.' : 'disabled.');
    } else {
      $status = 'Pop-up not found.';
    }
  }
  // UPDATE
  elseif ($action === 'update') {
    $updateId = $_POST['update_id'] ?? '';
    if ($updateId !== '' && isset($broadcasts[$updateId])) {
      $title      = clean_popup_text($_POST['title'] ?? '', 200);
      $msg        = clean_popup_text($_POST['message'] ?? '', 500);
      $buttonText = clean_popup_text($_POST['button_text'] ?? 'Got it', 40);
      $imageUrl   = $broadcasts[$updateId]['image'] ?? '';
      $mode       = normalize_mode($_POST['popup_mode'] ?? 'every_launch');

      // Image upload
      if (isset($_FILES['popup_image']) && is_uploaded_file($_FILES['popup_image']['tmp_name'])) {
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = mime_content_type($_FILES['popup_image']['tmp_name']);
        if (isset($allowed[$mime])) {
          $dir = __DIR__ . '/popup_images';
          if (!is_dir($dir)) mkdir($dir, 0755, true);
          $name = 'popup_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
          if (move_uploaded_file($_FILES['popup_image']['tmp_name'], $dir . '/' . $name)) {
            // Remove old image
            if (!empty($imageUrl)) {
              $oldPath = __DIR__ . parse_url($imageUrl, PHP_URL_PATH);
              if (file_exists($oldPath)) @unlink($oldPath);
            }
            $imageUrl = '/popup_images/' . rawurlencode($name);
          }
        } else {
          $status = 'Image must be PNG, JPG, WEBP, or GIF.';
        }
      }

      $broadcasts[$updateId]['title']       = $title;
      $broadcasts[$updateId]['message']     = $msg;
      $broadcasts[$updateId]['button_text'] = $buttonText;
      $broadcasts[$updateId]['mode']        = $mode;
      if (isset($_FILES['popup_image']) && $_FILES['popup_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $broadcasts[$updateId]['image'] = $imageUrl;
      }
      $broadcasts[$updateId]['updated_at']      = time();
      $broadcasts[$updateId]['updated_at_text'] = date('Y-m-d H:i:s');

      // Optionally reset seen so every_launch / once_on_launch show again
      if (isset($_POST['reset_seen']) && $_POST['reset_seen'] === '1') {
        $broadcasts[$updateId]['seen']      = [];
        $broadcasts[$updateId]['dismissed'] = [];
      }

      profile_save_json($file, $broadcasts);
      if ($status === '') $status = 'Pop-up updated.';
    } else {
      $status = 'Pop-up not found.';
    }
  }
  // CREATE
  else {
    $title      = clean_popup_text($_POST['title'] ?? '', 200);
    $msg        = clean_popup_text($_POST['message'] ?? '', 500);
    $buttonText = clean_popup_text($_POST['button_text'] ?? 'Got it', 40);
    $imageUrl   = '';
    $mode       = normalize_mode($_POST['popup_mode'] ?? 'every_launch');

    if (isset($_FILES['popup_image']) && is_uploaded_file($_FILES['popup_image']['tmp_name'])) {
      $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
      $mime = mime_content_type($_FILES['popup_image']['tmp_name']);
      if (isset($allowed[$mime])) {
        $dir = __DIR__ . '/popup_images';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = 'popup_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if (move_uploaded_file($_FILES['popup_image']['tmp_name'], $dir . '/' . $name)) {
          $imageUrl = '/popup_images/' . rawurlencode($name);
        }
      } else {
        $status = 'Image must be PNG, JPG, WEBP, or GIF.';
      }
    }

    if ($title !== '' || $msg !== '' || $imageUrl !== '') {
      $online = profile_online_map();
      $id = (string) time() . '_' . bin2hex(random_bytes(3));
      $broadcasts[$id] = [
        'id'             => $id,
        'title'          => $title,
        'message'        => $msg,
        'button_text'    => $buttonText,
        'image'          => $imageUrl,
        'mode'           => $mode,
        'active'         => true,
        'sent_at'        => time(),
        'sent_at_text'   => date('Y-m-d H:i:s'),
        'recipients'     => array_keys($online),
        'seen'           => [],
        'dismissed'      => [],
        'created_at'     => time(),
        'updated_at'     => time(),
        'updated_at_text'=> date('Y-m-d H:i:s'),
      ];
      profile_save_json($file, $broadcasts);
      // Bug #15 fix: Preserve image-upload failure status. Previously, if the
      // image MIME was invalid but title/message were valid, $status was set to
      // 'Image must be PNG...' then overwritten with 'Pop-up created...'. Now
      // we chain the warning onto the success message so the admin knows the
      // image was rejected.
      $successMsg = 'Pop-up created for ' . count($online) . ' online user(s).';
      if ($status !== '') {
        $status = $successMsg . ' WARNING: ' . $status;
      } else {
        $status = $successMsg;
      }
    } elseif ($status === '') {
      $status = 'Type a title or message first.';
    }
  }
}

$editId = $_GET['edit'] ?? '';
$editPopup = null;
if ($editId !== '' && isset($broadcasts[$editId])) {
  $editPopup = $broadcasts[$editId];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pop-up Admin</title>
  <style>
    *{box-sizing:border-box}body{font-family:Inter,Arial,sans-serif;background:#080d14;color:#eaf0fd;padding:24px 24px 108px;margin:0}.wrap{width:min(900px,96vw);margin:auto}.card,.popup-list-card{background:#18202e;border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:22px;margin-bottom:18px;overflow:hidden}form{width:100%;margin:0}textarea,input,select{display:block;width:100%;max-width:100%;min-width:0;background:#0a0f18;color:#fff;border:1px solid #60739b;border-radius:14px;padding:14px;font-weight:800;margin-top:10px;font-family:inherit}textarea{min-height:120px;resize:vertical}select{cursor:pointer}.btn,.btn-sm{display:inline-block;margin-top:12px;margin-right:6px;border:0;border-radius:14px;padding:12px 16px;font-weight:950;text-decoration:none;cursor:pointer;font-family:inherit;font-size:.95rem}.btn{width:100%;font-size:1.05rem;box-shadow:0 14px 34px rgba(0,0,0,.28)}.btn-primary{background:#a2c4ff;color:#111}.btn-danger{background:#ff6b6b;color:#111}.btn-warning{background:#ffd93d;color:#111}.btn-sm{padding:7px 12px;font-size:.82rem;border-radius:10px;margin-top:0}.hint{opacity:.7;font-weight:800;line-height:1.4}.status{font-weight:950;color:#bfffe0;word-break:break-word}.mode-select{display:flex;gap:16px;margin-top:14px;flex-wrap:wrap}.mode-option{flex:1;min-width:180px}.mode-option label{display:block;padding:12px 16px;border-radius:14px;background:#0a0f18;border:2px solid #60739b;cursor:pointer;font-weight:850;transition:.15s;text-align:center}.mode-option input{display:none}.mode-option input:checked+label{border-color:#a2c4ff;background:rgba(162,196,255,.12)}.popup-item{background:rgba(10,15,24,.55);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:16px;margin-bottom:10px}.popup-item:last-child{margin-bottom:0}.popup-header{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}.popup-title-text{font-weight:950;font-size:1.05rem;color:#fff}.popup-meta{color:rgba(234,240,253,.62);font-weight:800;font-size:.82rem;margin-top:4px}.popup-message{color:rgba(238,243,252,.9);font-weight:800;margin-top:8px;line-height:1.35;word-break:break-word}.popup-preview-img{max-width:80px;max-height:80px;border-radius:10px;margin-top:8px;border:1px solid rgba(255,255,255,.12)}.popup-actions{display:flex;gap:6px;margin-top:10px;flex-wrap:wrap}.badge{display:inline-block;padding:3px 8px;border-radius:999px;font-size:.72rem;font-weight:950}.badge-active{background:rgba(100,220,100,.18);color:#6f6}.badge-inactive{background:rgba(220,100,100,.18);color:#f66}.badge-once{background:rgba(100,160,255,.18);color:#6af}.badge-every{background:rgba(220,220,100,.18);color:#ff6}.badge-now{background:rgba(255,160,100,.18);color:#fc6}.badge-shown{background:rgba(255,255,255,.12);color:rgba(234,240,253,.7)}.filter-tabs{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}.filter-tab{display:inline-block;padding:7px 14px;border-radius:999px;background:rgba(38,45,58,.86);border:1px solid rgba(255,255,255,.1);color:#eef3ff;font-weight:950;font-size:.82rem;cursor:pointer;text-decoration:none;transition:.15s}.filter-tab:hover{background:rgba(55,67,92,.96)}.filter-tab.active{background:rgba(162,196,255,.18);border-color:#a2c4ff}.reset-seen{margin-top:12px}.reset-seen label{display:flex;align-items:center;gap:8px;font-weight:850;color:rgba(234,240,253,.8);cursor:pointer}.reset-seen input{width:auto;margin:0}
    .admin-clean-dock{position:fixed;left:50%;bottom:14px;z-index:2147482400;transform:translateX(-50%);width:min(980px,calc(100vw - 24px));display:flex;gap:8px;justify-content:center;flex-wrap:wrap;padding:10px;border-radius:24px;background:rgba(12,18,28,.78);border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 60px rgba(0,0,0,.38);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);font-family:Inter,Arial,Helvetica,sans-serif}.admin-clean-dock a{color:#eef3ff!important;text-decoration:none!important;font-weight:950;font-size:.86rem;padding:9px 12px;border-radius:999px;background:rgba(38,45,58,.86);border:1px solid rgba(255,255,255,.1);white-space:nowrap}.admin-clean-dock a:hover{background:rgba(55,67,92,.96)}
    @media(max-width:620px){body{padding-bottom:92px!important}.admin-clean-dock{bottom:8px;justify-content:flex-start;overflow-x:auto;flex-wrap:nowrap}}
    .field-label{display:block;margin-top:14px;color:rgba(234,240,253,.72);font-weight:900}
  </style>
</head>
<body>
  <main class="wrap">
    <a href="/admin.php">&larr;; Admin</a>
    <h1><?= $editPopup ? 'Edit Pop-up' : 'Pop-up Manager' ?></h1>
    <div class="card">
      <p class="status"><?= profile_e($status) ?></p>
      <p class="hint"><strong><?= count($onlineNow) ?></strong> user(s) online right now.</p>
      <form method="POST" enctype="multipart/form-data" id="popupForm"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
        <?php if ($editPopup): ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="update_id" value="<?= profile_e($editPopup['id']) ?>">
        <?php else: ?>
          <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <label class="field-label" for="popupTitleInput">Title</label>
        <input type="text" name="title" id="popupTitleInput" maxlength="200" placeholder="Title shown at the top of the popup (e.g. 'I heard you')" value="<?= $editPopup ? profile_e($editPopup['title'] ?? '') : '' ?>">

        <label class="field-label" for="popupMessageInput">Message</label>
        <textarea name="message" id="popupMessageInput" placeholder="Message body (e.g. 'Ok no more log-ins')"><?= $editPopup ? profile_e($editPopup['message'] ?? '') : '' ?></textarea>

        <label class="field-label" for="popupButtonInput">Dismiss Button Text</label>
        <input type="text" name="button_text" id="popupButtonInput" maxlength="40" placeholder="Text shown on the dismiss button for modal popups (e.g. 'Got it')" value="<?= $editPopup ? profile_e($editPopup['button_text'] ?? 'Got it') : 'Got it' ?>">
        <p class="hint" style="margin-top:4px;">Used by <strong>Show Every Launch</strong> and <strong>Show Once on Launch</strong> popups (the button users must click to dismiss the modal). <strong>Show Now</strong> popups auto-dismiss after 5 seconds and have no button.</p>

        <label class="field-label" for="popupImageInput">Image (optional)</label>
        <input type="file" name="popup_image" id="popupImageInput" accept="image/png,image/jpeg,image/webp,image/gif">
        <?php if ($editPopup && !empty($editPopup['image'])): ?>
          <p style="margin:8px 0 0;font-weight:800;opacity:.7;">Current image: <?= profile_e(basename($editPopup['image'])) ?> (upload a new one to replace)</p>
        <?php endif; ?>

        <div class="mode-select">
          <div class="mode-option">
            <input type="radio" name="popup_mode" value="every_launch" id="modeEvery" <?= (!$editPopup || ($editPopup['mode'] ?? 'every_launch') === 'every_launch') ? 'checked' : '' ?>>
            <label for="modeEvery">Show Every Launch</label>
          </div>
          <div class="mode-option">
            <input type="radio" name="popup_mode" value="once_on_launch" id="modeOnce" <?= ($editPopup && ($editPopup['mode'] ?? '') === 'once_on_launch') ? 'checked' : '' ?>>
            <label for="modeOnce">Show Once on Launch</label>
          </div>
          <div class="mode-option">
            <input type="radio" name="popup_mode" value="show_now" id="modeNow" <?= ($editPopup && ($editPopup['mode'] ?? '') === 'show_now') ? 'checked' : '' ?>>
            <label for="modeNow">Show Now</label>
          </div>
        </div>

        <?php if ($editPopup): ?>
          <div class="reset-seen">
            <label>
              <input type="checkbox" name="reset_seen" value="1">
              Reset seen status &ndash; show this pop-up to everyone again
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <a href="/popup_admin.php" class="btn btn-danger" style="display:inline-block;width:auto;padding:12px 16px;">Cancel</a>
        <?php else: ?>
          <button type="submit" class="btn btn-primary">Create pop-up</button>
        <?php endif; ?>
      </form>
    </div>

    <!-- ALL POP-UPS LIST -->
    <h2>All Pop-ups</h2>
    <?php
      $currentFilter = $_GET['filter'] ?? 'all';
      $filteredList = [];
      foreach ($broadcasts as $id => $b) {
        // Bug #18 fix: Removed the `if ($mode !== 'every_launch') continue;`
        // filter so ALL popup types (every_launch, once_on_launch, show_now)
        // are listed and editable. Previously, once_on_launch and show_now
        // popups were invisible in the admin UI once created.
        $active = $b['active'] ?? true;
        $hasSeen = !empty($b['seen']);
        if ($currentFilter === 'active' && !$active) continue;
        if ($currentFilter === 'inactive' && $active) continue;
        if ($currentFilter === 'shown' && !$hasSeen) continue;
        if ($currentFilter === 'unshown' && $hasSeen) continue;
        $filteredList[$id] = $b;
      }
      uasort($filteredList, function($a, $b) {
        return ($b['sent_at'] ?? 0) <=> ($a['sent_at'] ?? 0);
      });
      // Count only every_launch popups for the "All" tab counter
      $everyLaunchCount = 0;
      foreach ($broadcasts as $b) {
        if (($b['mode'] ?? 'every_launch') === 'every_launch') $everyLaunchCount++;
      }
    ?>
    <div class="card popup-list-card">
      <div class="filter-tabs">
        <a class="filter-tab <?= $currentFilter === 'all' ? 'active' : '' ?>" href="?filter=all">All (<?= $everyLaunchCount ?>)</a>
        <a class="filter-tab <?= $currentFilter === 'active' ? 'active' : '' ?>" href="?filter=active">Active</a>
        <a class="filter-tab <?= $currentFilter === 'inactive' ? 'active' : '' ?>" href="?filter=inactive">Disabled</a>
        <a class="filter-tab <?= $currentFilter === 'shown' ? 'active' : '' ?>" href="?filter=shown">Already Shown</a>
        <a class="filter-tab <?= $currentFilter === 'unshown' ? 'active' : '' ?>" href="?filter=unshown">Not Yet Shown</a>
      </div>

      <?php if (empty($filteredList)): ?>
        <p style="opacity:.7;font-weight:800;text-align:center;padding:20px 0;">No pop-ups in this filter.</p>
      <?php else: ?>
        <?php foreach ($filteredList as $id => $b):
          $active = $b['active'] ?? true;
          $mode = $b['mode'] ?? 'every_launch';
          $seenCount = count($b['seen'] ?? []);
          $recipientCount = count($b['recipients'] ?? []);
          $modeBadgeClass = $mode === 'once_on_launch' ? 'badge-once' : ($mode === 'show_now' ? 'badge-now' : 'badge-every');
          $modeLabel = $mode === 'once_on_launch' ? 'Once on Launch' : ($mode === 'show_now' ? 'Show Now' : 'Every Launch');
        ?>
        <div class="popup-item">
          <div class="popup-header">
            <div>
              <div class="popup-title-text"><?= profile_e($b['title'] ?? '(no title)') ?></div>
              <div class="popup-meta">
                <span class="badge <?= $active ? 'badge-active' : 'badge-inactive' ?>"><?= $active ? 'Active' : 'Disabled' ?></span>
                <span class="badge <?= $modeBadgeClass ?>"><?= $modeLabel ?></span>
                <span class="badge badge-shown"><?= $seenCount ?> seen / <?= $recipientCount ?> recipients</span>
                <br>
                Created: <?= profile_e($b['sent_at_text'] ?? '') ?>
                <?php if (!empty($b['updated_at_text'])): ?> &middot; Updated: <?= profile_e($b['updated_at_text']) ?><?php endif; ?>
              </div>
            </div>
          </div>
          <?php if (!empty($b['message'])): ?>
            <div class="popup-message"><?= profile_e($b['message']) ?></div>
          <?php endif; ?>
          <?php if (!empty($b['button_text'])): ?>
            <div style="margin-top:6px;font-size:.85rem;font-weight:850;opacity:.7;">Button: "<?= profile_e($b['button_text']) ?>"</div>
          <?php endif; ?>
          <?php if (!empty($b['image'])): ?>
            <img class="popup-preview-img" src="<?= profile_e($b['image']) ? alt="">" alt="Popup image">
          <?php endif; ?>
          <div class="popup-actions">
            <a class="btn-sm btn-primary" href="/popup_admin.php?edit=<?= urlencode($id) ?>">Edit</a>
            <form method="POST" style="display:inline;margin:0;"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="toggle_id" value="<?= profile_e($id) ?>">
              <button type="submit" class="btn-sm <?= $active ? 'btn-warning' : 'btn-primary' ?>"><?= $active ? 'Disable' : 'Enable' ?></button>
            </form>
            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Delete this pop-up? This cannot be undone.')"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="delete_id" value="<?= profile_e($id) ?>">
              <button type="submit" class="btn-sm btn-danger">Delete</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <?php include __DIR__ . '/site_popups.php'; ?>

  <nav class="admin-clean-dock" aria-label="Admin quick menu">
    <a href="/admin.php">Dashboard</a>
    <a href="/game_admin.php">Games</a>
    <a href="/app_admin.php">Apps</a>
    <a href="/account_admin.php">Accounts</a>
    <a href="/ban_admin.php">Bans</a>
    <a href="/popup_admin.php">Popups</a>
    <a href="/profile_admin.php">Profiles</a>
    <a href="/user_report_admin.php">Reports</a>
    <a href="/admin_tools.php">Tools</a>
  </nav>
</body>
</html>
