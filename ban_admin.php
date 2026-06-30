<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/ban_helpers.php';

date_default_timezone_set('Europe/London');

function e($text) {
  return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Edit this list if you want more admin accounts.
$adminUsers = ['rufus'];
$currentUser = $_SESSION['username'] ?? '';

if (!in_array(strtolower($currentUser), array_map('strtolower', $adminUsers), true)) {
  http_response_code(403);
  echo 'No access.';
  exit;
}

$status = '';
$statusType = '';
$chatOffencesFile = __DIR__ . '/_private/chat_filter_offences.json';
$deletedLogFile = __DIR__ . '/_private/deleted_message_log.txt';

function load_chat_offences($file) {
  if (!file_exists($file)) file_put_contents($file, '{}');
  $data = json_decode(@file_get_contents($file), true);
  return is_array($data) ? $data : [];
}

function save_chat_offences($file, $offences) {
  file_put_contents($file, json_encode($offences, JSON_PRETTY_PRINT), LOCK_EX);
}

function latest_deleted_message_for_user($file, $username) {
  if (!file_exists($file)) return '';
  $key = strtolower(trim((string)$username));
  $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
  for ($i = count($lines) - 1; $i >= 0; $i--) {
    $parts = explode(' | ', $lines[$i], 4);
    if (count($parts) < 4) continue;
    if (strtolower(trim($parts[1])) === $key) {
      return $parts[3];
    }
  }
  return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $bans = prune_expired_bans();

  if ($action === 'ban') {
    $accountName = clean_ban_text($_POST['account_name'] ?? '', 40);
    $message = clean_ban_text($_POST['ban_message'] ?? '', 500);
    $durationAmount = (int)($_POST['duration_amount'] ?? 0);
    $durationUnit = $_POST['duration_unit'] ?? 'days';

    if ($accountName === '') {
      $status = 'Put the account name first.';
      $statusType = 'bad';
    } elseif ($durationAmount <= 0) {
      $status = 'Put a ban length bigger than 0.';
      $statusType = 'bad';
    } else {
      $seconds = $durationAmount * 86400;
      if ($durationUnit === 'minutes') $seconds = $durationAmount * 60;
      if ($durationUnit === 'hours') $seconds = $durationAmount * 3600;
      if ($durationUnit === 'days') $seconds = $durationAmount * 86400;

      if ($message === '') {
        $message = 'You are banned from this website.';
      }

      $key = ban_key($accountName);
      $bans[$key] = [
        'name' => $accountName,
        'message' => $message,
        'banned_at' => time(),
        'banned_at_text' => date('Y-m-d H:i:s'),
        'banned_until' => time() + $seconds,
        'banned_until_text' => date('Y-m-d H:i:s', time() + $seconds),
        'banned_by' => $currentUser
      ];

      save_banned_accounts($bans);
      $status = $accountName . ' has been banned.';
      $statusType = 'good';
    }
  }

  if ($action === 'unban') {
    $accountName = clean_ban_text($_POST['account_name'] ?? '', 40);
    $key = ban_key($accountName);

    if (isset($bans[$key])) {
      unset($bans[$key]);
      save_banned_accounts($bans);
      $status = $accountName . ' has been unbanned.';
      $statusType = 'good';
    }
  }

  if ($action === 'update_offence') {
    $accountName = clean_ban_text($_POST['account_name'] ?? '', 40);
    $newCount = max(0, (int)($_POST['offence_count'] ?? 0));
    $key = ban_key($accountName);

    if ($key === '') {
      $status = 'Missing account name.';
      $statusType = 'bad';
    } else {
      $offences = load_chat_offences($chatOffencesFile);

      if ($newCount === 0) {
        unset($offences[$key]);
      } else {
        $existing = $offences[$key] ?? [];
        $offences[$key] = array_merge($existing, [
          'name' => $accountName,
          'count' => $newCount,
          'edited_at' => time(),
          'edited_at_text' => date('Y-m-d H:i:s'),
          'edited_by' => $currentUser
        ]);
      }

      save_chat_offences($chatOffencesFile, $offences);

      if (isset($bans[$key])) {
        $bans[$key]['chat_filter_offence_count'] = $newCount;
        $bans[$key]['offence_edited_at_text'] = date('Y-m-d H:i:s');
        $bans[$key]['offence_edited_by'] = $currentUser;
        save_banned_accounts($bans);
      }

      $status = 'Offence count updated for ' . $accountName . '.';
      $statusType = 'good';
    }
  }
}

$bans = prune_expired_bans();
$chatOffences = load_chat_offences($chatOffencesFile);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ban Admin</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { min-height: 100%; font-family: Inter, Arial, Helvetica, sans-serif; background: #080d14; color: #eaf0fd; }
    body { padding: 22px; background: radial-gradient(circle at 10% 20%, rgba(95,130,220,.38), transparent 28%), linear-gradient(120deg,#080d14 0%,#141a24 45%,#222936 100%); }
    .wrap { width: min(920px, 96vw); margin: 0 auto; }
    .top { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 18px; flex-wrap: wrap; }
    h1 { font-size: clamp(2rem, 7vw, 4rem); letter-spacing: -.08em; text-transform: uppercase; }
    a { color: #a2c4ff; font-weight: 950; }
    .card { background: rgba(24,30,41,.94); border: 1px solid rgba(255,255,255,.13); border-radius: 24px; padding: 22px; box-shadow: 0 18px 60px rgba(0,0,0,.32); margin-bottom: 18px; }
    label { display: block; margin-top: 12px; color: rgba(234,240,253,.72); font-weight: 900; }
    input, textarea, select { width: 100%; margin-top: 7px; padding: 13px 14px; border-radius: 14px; border: 1.5px solid rgba(160,195,255,.32); background: rgba(10,15,24,.72); color: #eef3ff; font-family: inherit; font-weight: 850; outline: none; }
    textarea { min-height: 90px; resize: vertical; }
    .duration { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    button { border: none; border-radius: 14px; padding: 13px 16px; font-weight: 950; cursor: pointer; font-family: inherit; background: linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%); color: #111; margin-top: 14px; }
    .unban-btn { background: rgba(220,80,90,.95); color: #fff; margin-top: 8px; }
    .mini-form { display: flex; gap: 8px; align-items: end; flex-wrap: wrap; margin-top: 10px; }
    .mini-form input { max-width: 120px; }
    .mini-form button { margin-top: 7px; }
    .said-box { margin-top: 9px; padding: 10px; border-radius: 14px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.09); color: rgba(234,240,253,.86); font-weight: 800; line-height: 1.38; }
    .said-label { color: rgba(234,240,253,.55); font-size: .82rem; font-weight: 950; margin-bottom: 4px; }
    .status { margin-bottom: 12px; font-weight: 950; }
    .status.bad { color: #ffd0d0; }
    .status.good { color: #bfffe0; }
    .ban-list { display: grid; gap: 12px; }
    .ban-item { padding: 15px; border-radius: 18px; background: rgba(10,15,24,.5); border: 1px solid rgba(255,255,255,.1); }
    .ban-name { font-size: 1.2rem; font-weight: 950; }
    .ban-msg { margin-top: 7px; color: rgba(234,240,253,.82); font-weight: 800; line-height: 1.4; }
    .ban-meta { margin-top: 7px; color: rgba(234,240,253,.55); font-weight: 850; font-size: .88rem; }
    .empty { color: rgba(234,240,253,.65); font-weight: 850; }
  </style>
</head>
<body>
  <main class="wrap">
    <div class="top">
      <h1>Ban Admin</h1>
      <div><a href="/admin.php">Admin</a> · <a href="/account_admin.php">Accounts</a> · <a href="/game_admin.php">Games</a> · <a href="/">Home</a> · <a href="/logout.php">Logout</a></div>
    </div>

    <section class="card">
      <?php if ($status !== ''): ?><div class="status <?= e($statusType) ?>"><?= e($status) ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="ban">
        <label>Account name to ban</label>
        <input name="account_name" placeholder="Example: rufus1" required>

        <label>Ban message shown on no access screen</label>
        <textarea name="ban_message" placeholder="Example: You are banned for breaking the chat rules."></textarea>

        <label>Ban length</label>
        <div class="duration">
          <input name="duration_amount" type="number" min="1" value="3" required>
          <select name="duration_unit">
            <option value="minutes">Minutes</option>
            <option value="hours">Hours</option>
            <option value="days" selected>Days</option>
          </select>
        </div>

        <button type="submit">Ban account</button>
      </form>
    </section>

    <section class="card">
      <h2>Active bans</h2>
      <div class="ban-list">
        <?php if (count($bans) === 0): ?>
          <div class="empty">No active bans.</div>
        <?php else: ?>
          <?php foreach ($bans as $ban): ?>
            <?php $left = max(0, (int)$ban['banned_until'] - time()); ?>
            <div class="ban-item">
              <div class="ban-name"><?= e($ban['name'] ?? 'Unknown') ?></div>
              <div class="ban-msg"><?= e($ban['message'] ?? '') ?></div>
              <?php
                $source = $ban['source'] ?? $ban['banned_by'] ?? 'manual';
                $reason = $ban['reason'] ?? '';
                $blockedTerm = $ban['blocked_term'] ?? '';
                $banName = $ban['name'] ?? '';
                $banKey = ban_key($banName);
                $offenceCount = $ban['chat_filter_offence_count'] ?? ($chatOffences[$banKey]['count'] ?? 0);
                $banMinutes = $ban['ban_minutes'] ?? '';
                $saidMessage = $ban['blocked_message'] ?? latest_deleted_message_for_user($deletedLogFile, $banName);
              ?>
              <div class="ban-meta">Ends: <?= e($ban['banned_until_text'] ?? '') ?> · Time left: <span class="countdown" data-left="<?= (int)$left ?>"><?= e(format_seconds_left($left)) ?></span></div>
              <div class="ban-meta">Source: <?= e($source) ?><?= $reason !== '' ? ' · Reason: ' . e($reason) : '' ?><?= $blockedTerm !== '' ? ' · Blocked term: ' . e($blockedTerm) : '' ?><?= $offenceCount !== '' ? ' · Offence #' . e($offenceCount) : '' ?><?= $banMinutes !== '' ? ' · Ban length: ' . e($banMinutes) . ' min' : '' ?></div>
              <?php if ($saidMessage !== ''): ?>
                <div class="said-box">
                  <div class="said-label">What they said</div>
                  <?= e($saidMessage) ?>
                </div>
              <?php endif; ?>
              <form method="POST" class="mini-form">
                <input type="hidden" name="action" value="update_offence">
                <input type="hidden" name="account_name" value="<?= e($banName) ?>">
                <label>Offence count
                  <input name="offence_count" type="number" min="0" value="<?= e($offenceCount) ?>">
                </label>
                <button type="submit">Save offence</button>
              </form>
              <form method="POST">
                <input type="hidden" name="action" value="unban">
                <input type="hidden" name="account_name" value="<?= e($banName) ?>">
                <button class="unban-btn" type="submit">Unban</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>


    <section class="card">
      <h2>Chat-filter offence counts</h2>
      <div class="ban-list">
        <?php if (count($chatOffences) === 0): ?>
          <div class="empty">No offence history yet.</div>
        <?php else: ?>
          <?php foreach ($chatOffences as $key => $offence): ?>
            <?php
              $name = $offence['name'] ?? $key;
              $count = (int)($offence['count'] ?? 0);
              $lastTerm = $offence['last_blocked_term'] ?? '';
              $lastMessage = $offence['last_message'] ?? latest_deleted_message_for_user($deletedLogFile, $name);
              $lastTime = $offence['last_offence_at_text'] ?? '';
            ?>
            <div class="ban-item">
              <div class="ban-name"><?= e($name) ?></div>
              <div class="ban-meta">Offence count: <?= e($count) ?><?= $lastTerm !== '' ? ' · Last blocked term: ' . e($lastTerm) : '' ?><?= $lastTime !== '' ? ' · Last offence: ' . e($lastTime) : '' ?></div>
              <?php if ($lastMessage !== ''): ?>
                <div class="said-box">
                  <div class="said-label">Last thing they said</div>
                  <?= e($lastMessage) ?>
                </div>
              <?php endif; ?>
              <form method="POST" class="mini-form">
                <input type="hidden" name="action" value="update_offence">
                <input type="hidden" name="account_name" value="<?= e($name) ?>">
                <label>Edit offence count
                  <input name="offence_count" type="number" min="0" value="<?= e($count) ?>">
                </label>
                <button type="submit">Save offence</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

  </main>

  <script>
    function formatTime(total) {
      total = Math.max(0, Number(total) || 0);
      const days = Math.floor(total / 86400);
      total %= 86400;
      const hours = Math.floor(total / 3600);
      total %= 3600;
      const minutes = Math.floor(total / 60);
      const seconds = total % 60;
      if (days > 0) return `${days}d ${hours}h ${minutes}m`;
      if (hours > 0) return `${hours}h ${minutes}m ${seconds}s`;
      if (minutes > 0) return `${minutes}m ${seconds}s`;
      return `${seconds}s`;
    }

    setInterval(() => {
      document.querySelectorAll('.countdown').forEach(el => {
        let left = Number(el.dataset.left || 0) - 1;
        el.dataset.left = left;
        el.textContent = left <= 0 ? 'Expired' : formatTime(left);
      });
    }, 1000);
  </script>


<style>
  .global-admin-popup-backdrop {
    position: fixed;
    inset: 0;
    z-index: 2147482500;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(15,22,34,0.77);
    padding: 18px;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
  }
  .global-admin-popup-box {
    background: rgba(24,30,41,0.98);
    border-radius: 22px;
    box-shadow: 0 12px 38px rgba(0,0,0,0.28);
    border: 1px solid rgba(255,255,255,0.12);
    padding: 38px 34px 26px;
    max-width: min(92vw, 420px);
    color: #eaf0fd;
    text-align: center;
    font-size: 1.08rem;
    font-weight: 800;
    line-height: 1.35;
    font-family: Inter, Arial, Helvetica, sans-serif;
  }
</style>
<div id="globalAdminPopup" class="global-admin-popup-backdrop" role="dialog" aria-modal="true">
  <div id="globalAdminPopupText" class="global-admin-popup-box"></div>
</div>
<script>
(function () {
  async function checkGlobalAdminPopup() {
    try {
      const res = await fetch('/popup_api.php?action=check&cache=' + Date.now());
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.message) return;
      const modal = document.getElementById('globalAdminPopup');
      const text = document.getElementById('globalAdminPopupText');
      if (!modal || !text) return;
      text.textContent = String(data.message || '');
      modal.style.display = 'flex';
      setTimeout(async function () {
        modal.style.display = 'none';
        await fetch('/popup_api.php?action=seen&id=' + encodeURIComponent(data.id || ''));
      }, 3000);
    } catch (e) {}
  }
  window.addEventListener('DOMContentLoaded', function () {
    checkGlobalAdminPopup();
    setInterval(checkGlobalAdminPopup, 30000);
  });
})();
</script>

<?php include __DIR__ . '/site_popups.php'; ?>


<style>
  .admin-clean-dock {
    position: fixed;
    left: 50%;
    bottom: 14px;
    z-index: 2147482400;
    transform: translateX(-50%);
    width: min(980px, calc(100vw - 24px));
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
    padding: 10px;
    border-radius: 24px;
    background: rgba(12,18,28,.78);
    border: 1px solid rgba(255,255,255,.12);
    box-shadow: 0 18px 60px rgba(0,0,0,.38);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    font-family: Inter, Arial, Helvetica, sans-serif;
  }
  .admin-clean-dock a {
    color: #eef3ff !important;
    text-decoration: none !important;
    font-weight: 950;
    font-size: .86rem;
    padding: 9px 12px;
    border-radius: 999px;
    background: rgba(38,45,58,.86);
    border: 1px solid rgba(255,255,255,.1);
    white-space: nowrap;
  }
  .admin-clean-dock a:hover { background: rgba(55,67,92,.96); }
  @media (max-width: 620px) {
    .admin-clean-dock { bottom: 8px; justify-content: flex-start; overflow-x: auto; flex-wrap: nowrap; }
    body { padding-bottom: 92px !important; }
  }
</style>
<nav class="admin-clean-dock" aria-label="Admin quick menu">
  <a href="/admin.php">Dashboard</a>
  <a href="/ban_admin.php">Bans</a>
  <a href="/account_admin.php">Accounts</a>
  <a href="/game_admin.php">Games</a>
  <a href="/profile_admin.php">Approvals</a>
  <a href="/popup_admin.php">Popups</a>
  <a href="/admin_tools.php">Tools</a>
  <a href="/">Home</a>
</nav>

</body>
</html>
