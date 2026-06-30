<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

date_default_timezone_set('Europe/London');
if (!profile_is_admin()) { http_response_code(403); echo 'No access.'; exit; }

function e($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}

$queueFile = profile_data_dir() . '/pfp_requests.json';
$queue = profile_load_json($queueFile);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Profile Picture Moderation</title><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.38),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px;padding-bottom:100px}
.wrap{width:min(1000px,96vw);margin:auto}
h1{font-size:clamp(1.8rem,6vw,3rem);letter-spacing:-.06em;text-transform:uppercase;margin:0 0 18px 0}
a{color:#a2c4ff;font-weight:950;text-decoration:none}
.card{background:rgba(24,30,41,.94);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.32);margin-bottom:16px;display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap}
.card img{width:120px;height:120px;border-radius:18px;object-fit:cover;border:1px solid rgba(255,255,255,.15);flex:0 0 120px;background:#000}
.card .info{flex:1;min-width:200px}
.card .info h2{margin:0 0 6px;font-size:1.2rem;letter-spacing:-.02em}
.card .info .muted{color:rgba(234,240,253,.6);font-weight:800;font-size:.85rem;margin-bottom:12px}
.card .actions{display:flex;gap:10px;flex-wrap:wrap}
.card .actions a{display:inline-block;padding:11px 18px;border-radius:13px;font-weight:950;text-decoration:none;cursor:pointer}
.btn-approve{background:#44d17a;color:#111}
.btn-deny{background:#ff5c6a;color:#fff}
.empty{color:rgba(234,240,253,.55);font-weight:800;text-align:center;padding:40px 0}
.admin-clean-dock{position:fixed;left:50%;bottom:14px;z-index:2147482400;transform:translateX(-50%);width:min(980px,calc(100vw - 24px));display:flex;gap:8px;justify-content:center;flex-wrap:wrap;padding:10px;border-radius:24px;background:rgba(12,18,28,.78);border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 60px rgba(0,0,0,.38);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);font-family:Inter,Arial,Helvetica,sans-serif}
.admin-clean-dock a{color:#eef3ff!important;text-decoration:none!important;font-weight:950;font-size:.86rem;padding:9px 12px;border-radius:999px;background:rgba(38,45,58,.86);border:1px solid rgba(255,255,255,.1);white-space:nowrap}
.admin-clean-dock a:hover{background:rgba(55,67,92,.96)}
@media(max-width:620px){.admin-clean-dock{bottom:8px;justify-content:flex-start;overflow-x:auto;flex-wrap:nowrap}}
</style></head><body><main class="wrap">
<a href="/admin.php">&larr; Admin</a>
<h1>Profile Picture Moderation</h1>
<p style="color:rgba(234,240,253,.6);font-weight:800;margin-bottom:18px;"><?= count($queue) ?> pending request(s). Click Approve to set the picture, or Deny to delete it.</p>

<?php if (empty($queue)): ?>
  <div class="empty">No pending profile picture requests.</div>
<?php else: foreach ($queue as $i => $r): ?>
  <div class="card">
    <img src="<?= e($r['temp_url'] ?? '/favicon.png') ? alt="">" alt="Pending pfp for <?= e($r['account_key'] ?? 'unknown') ?>">
    <div class="info">
      <h2><?= e($r['display_name'] ?? $r['account_key'] ?? 'unknown') ?></h2>
      <div class="muted">Account: <?= e($r['account_key'] ?? '') ?> &middot; Requested: <?= e($r['requested_at_text'] ?? '') ?></div>
      <div class="actions">
        <a class="btn-approve" href="/pfp_upload.php?action=approve&index=<?= (int)$i ?>" onclick="return confirm('Approve this profile picture?')">✓ Approve</a>
        <a class="btn-deny" href="/pfp_upload.php?action=deny&index=<?= (int)$i ?>" onclick="return confirm('Deny and delete this profile picture?')">✗ Deny</a>
      </div>
    </div>
  </div>
<?php endforeach; endif; ?>
</main>

<nav class="admin-clean-dock" aria-label="Admin quick menu">
  <a href="/admin.php">Dashboard</a>
  <a href="/game_admin.php">Games</a>
  <a href="/app_admin.php">Apps</a>
  <a href="/proxy_admin.php">Proxy</a>
  <a href="/account_admin.php">Accounts</a>
  <a href="/ban_admin.php">Bans</a>
  <a href="/popup_admin.php">Popups</a>
  <a href="/profile_admin.php">Profiles</a>
  <a href="/pfp_admin.php">PFP Moderation</a>
  <a href="/user_report_admin.php">Reports</a>
  <a href="/admin_tools.php">Tools</a>
</nav>
<?php include __DIR__ . '/site_popups.php'; ?>
</body></html>
