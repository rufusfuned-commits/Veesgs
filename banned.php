<?php
session_start();
require_once __DIR__ . '/ban_helpers.php';

function e($text) {
  return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['username'] ?? 'guest';
// Login check bypassed - auth_check.php now sets default session values

$ban = get_active_ban($username);
if (!$ban) {
  header('Location: /');
  exit;
}

$banMessage = $ban['message'] ?? 'You are banned from this website.';
$bannedUntil = (int)($ban['banned_until'] ?? time());
$secondsLeft = max(0, $bannedUntil - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>No Access</title>
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1819490803357082"
     crossorigin="anonymous"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
      min-height: 100%;
      font-family: Inter, Arial, Helvetica, sans-serif;
      background: #080d14;
      color: #eaf0fd;
    }
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      background:
        radial-gradient(circle at 10% 20%, rgba(220,70,90,.38), transparent 28%),
        radial-gradient(circle at 90% 18%, rgba(220,230,255,.18), transparent 26%),
        linear-gradient(120deg,#080d14 0%,#141a24 45%,#222936 100%);
    }
    .box {
      width: min(520px, 94vw);
      padding: 32px;
      border-radius: 26px;
      background: rgba(24,30,41,.96);
      border: 1px solid rgba(255,255,255,.13);
      box-shadow: 0 20px 70px rgba(0,0,0,.42);
      text-align: center;
    }
    h1 {
      font-size: clamp(2.4rem, 9vw, 4.5rem);
      line-height: .9;
      letter-spacing: -.08em;
      text-transform: uppercase;
      margin-bottom: 14px;
      color: #fff;
    }
    .message {
      margin: 16px 0;
      padding: 16px;
      border-radius: 18px;
      background: rgba(10,15,24,.58);
      border: 1px solid rgba(255,255,255,.1);
      color: rgba(238,243,252,.92);
      font-weight: 850;
      line-height: 1.45;
    }
    .timebox {
      margin-top: 16px;
      padding: 14px;
      border-radius: 18px;
      background: rgba(220,70,90,.14);
      border: 1px solid rgba(255,120,140,.25);
      color: #ffd7dc;
      font-weight: 950;
      font-size: 1.08rem;
    }
    .small {
      margin-top: 12px;
      color: rgba(234,240,253,.58);
      font-weight: 800;
      font-size: .85rem;
    }
  </style>
</head>
<body>
  <main class="box">
    <h1>No Access</h1>
    <div class="message"><?= e($banMessage) ?></div>
    <div class="timebox">Ban ends in: <span id="countdown"><?= e(format_seconds_left($secondsLeft)) ?></span></div>
    <div class="small">Account: <?= e($username) ?></div>
  </main>

  <script>
    let secondsLeft = <?= (int)$secondsLeft ?>;
    const countdown = document.getElementById('countdown');

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
      secondsLeft--;
      countdown.textContent = formatTime(secondsLeft);

      if (secondsLeft <= 0) {
        countdown.textContent = 'Expired. Reloading...';
        setTimeout(() => window.location.href = '/', 1200);
      }
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
</body>
</html>
