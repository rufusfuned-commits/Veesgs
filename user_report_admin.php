<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';
require_once __DIR__ . '/ban_helpers.php';

date_default_timezone_set('Europe/London');
if (!profile_can_access_admin()) { http_response_code(403); echo 'No access.'; exit; }

function e($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function duration_options() {
  return [
    300 => '5 minutes',
    600 => '10 minutes',
    1800 => '30 minutes',
    3600 => '1 hour',
    21600 => '6 hours',
    86400 => '1 day',
    259200 => '3 days',
    604800 => '7 days'
  ];
}

$file = profile_data_dir() . '/user_reports.json';
$reports = profile_load_json($file);
$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = profile_clean($_POST['id'] ?? '', 80);
  $action = $_POST['action'] ?? '';
  foreach ($reports as &$r) {
    if (($r['id'] ?? '') !== $id) continue;
    if ($action === 'ignore') {
      $r['status'] = 'ignored';
      $r['handled_at'] = date('Y-m-d H:i:s');
      $r['handled_by'] = profile_account_key();
      $status = 'Report ignored.';
    }
    if ($action === 'ban') {
      $seconds = (int)($_POST['ban_seconds'] ?? 300);
      if (!array_key_exists($seconds, duration_options())) $seconds = 300;
      $reported = $r['reported_user'] ?? '';
      if ($reported !== '') {
        set_account_ban($reported, $seconds, 'You were banned after a user report was reviewed.', profile_account_key(), [
          'source' => 'user-report',
          'report_id' => $id,
          'reported_message_context' => $r['messages'] ?? []
        ]);
        $r['status'] = 'banned';
        $r['ban_seconds'] = $seconds;
        $r['handled_at'] = date('Y-m-d H:i:s');
        $r['handled_by'] = profile_account_key();
        $status = 'User banned.';
      }
    }
  }
  unset($r);
  profile_save_json($file, $reports);
}

// Opening this page clears the red alert dot for reports by marking new reports as seen.
$changed = false;
foreach ($reports as &$r) {
  if (($r['status'] ?? '') === 'new') {
    $r['status'] = 'seen';
    $r['seen_at'] = date('Y-m-d H:i:s');
    $changed = true;
  }
}
unset($r);
if ($changed) profile_save_json($file, $reports);

$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$dates = [date('Y-m-d')];
foreach ($reports as $r) if (!empty($r['date'])) $dates[] = $r['date'];
$dates = array_values(array_unique($dates)); rsort($dates);
$filtered = array_values(array_filter(array_reverse($reports), fn($r) => ($r['date'] ?? '') === $selectedDate && in_array(($r['status'] ?? 'new'), ['new','seen'], true)));
$resolvedCount = count(array_filter($reports, fn($r) => ($r['date'] ?? '') === $selectedDate && !in_array(($r['status'] ?? 'new'), ['new','seen'], true)));
$durations = duration_options();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>User Reports</title><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.38),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px 22px 106px}.wrap{width:min(1100px,96vw);margin:auto}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}h1{font-size:clamp(2rem,7vw,4rem);letter-spacing:-.08em;text-transform:uppercase;margin:0}.pill,a{color:#a2c4ff;font-weight:950}.pill{display:inline-block;text-decoration:none;background:rgba(38,45,58,.86);border:1px solid rgba(255,255,255,.12);border-radius:999px;padding:10px 14px;color:#eef3ff}.card{background:rgba(24,30,41,.94);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.32);margin-bottom:16px}.status{font-weight:950;color:#bfffe0;margin-bottom:12px}.muted{color:rgba(234,240,253,.62);font-weight:800}.report-head{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.tag{display:inline-block;border-radius:999px;padding:6px 10px;background:rgba(255,255,255,.08);font-size:.78rem;font-weight:950;color:rgba(234,240,253,.75)}.msg{padding:10px 12px;border-radius:14px;background:rgba(10,15,24,.52);border:1px solid rgba(255,255,255,.08);margin:8px 0;overflow-wrap:anywhere}.msg strong{color:#fff}.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px}button,select{border:0;border-radius:14px;padding:12px 14px;font-weight:950;font-family:inherit}select{background:#0a0f18;color:#eef3ff;border:1px solid rgba(255,255,255,.14)}button{background:linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);color:#111;cursor:pointer}.danger{background:#e15b67;color:#fff}.admin-clean-dock{position:fixed;left:50%;bottom:14px;z-index:2147482400;transform:translateX(-50%);width:min(980px,calc(100vw - 24px));display:flex;gap:8px;justify-content:center;flex-wrap:wrap;padding:10px;border-radius:24px;background:rgba(12,18,28,.78);border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 60px rgba(0,0,0,.38);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}.admin-clean-dock a{color:#eef3ff!important;text-decoration:none!important;font-weight:950;font-size:.86rem;padding:9px 12px;border-radius:999px;background:rgba(38,45,58,.86);border:1px solid rgba(255,255,255,.1);white-space:nowrap}.date-select{width:min(260px,100%);padding:11px 12px;border-radius:14px;background:rgba(10,15,24,.72);color:#eef3ff;border:1px solid rgba(255,255,255,.13);font-weight:900}@media(max-width:620px){.admin-clean-dock{bottom:8px;justify-content:flex-start;overflow-x:auto;flex-wrap:nowrap}}
</style></head><body><main class="wrap"><div class="top"><div><h1>User reports</h1><div class="muted">Reports from the three-dot menu in messages.</div></div><div><a class="pill" href="/admin.php">← Admin</a></div></div><?php if($status):?><div class="status"><?=e($status)?></div><?php endif;?><form method="GET" class="card"><label class="muted">Select date</label><br><select class="date-select" name="date" onchange="this.form.submit()"><?php foreach($dates as $d):?><option value="<?=e($d)?>" <?=$d===$selectedDate?'selected':''?>><?=e($d)?></option><?php endforeach;?></select></form><?php if($resolvedCount):?><div class="card muted"><?=e($resolvedCount)?> handled report<?= $resolvedCount === 1 ? '' : 's' ?> hidden for this date.</div><?php endif;?><?php if(!$filtered):?><div class="card muted">No open user reports for this date.</div><?php endif;?><?php foreach($filtered as $r):?><section class="card"><div class="report-head"><div><strong><?=e($r['from_display'] ?? $r['from'] ?? '')?></strong> reported <strong><?=e($r['reported_display'] ?? $r['reported_user'] ?? '')?></strong><div class="muted"><?=e($r['date'] ?? '')?> <?=e($r['time'] ?? '')?> · room <?=e($r['room'] ?? '')?></div></div><div><span class="tag"><?=e($r['status'] ?? 'new')?></span></div></div><h3>Chat messages around report</h3><?php $msgs=$r['messages'] ?? []; if(!$msgs):?><div class="muted">No saved chat context.</div><?php else:foreach($msgs as $m):?><div class="msg"><strong><?=e($m['display_name'] ?? $m['user'] ?? '')?></strong> <span class="muted"><?=e($m['time'] ?? '')?></span><br><?=e($m['message'] ?? '')?><?php if(!empty($m['image'])):?><br><span class="muted">Image: <?=e($m['image'])?></span><?php endif;?></div><?php endforeach;endif;?><form method="POST" class="actions"><input type="hidden" name="id" value="<?=e($r['id'] ?? '')?>"><select name="ban_seconds"><?php foreach($durations as $sec=>$label):?><option value="<?=e($sec)?>"><?=e($label)?></option><?php endforeach;?></select><button class="danger" name="action" value="ban" type="submit">Ban user</button><button name="action" value="ignore" type="submit">Ignore</button></form></section><?php endforeach;?></main><nav class="admin-clean-dock" aria-label="Admin quick menu"><a href="/admin.php">Dashboard</a><a href="/ban_admin.php">Bans</a><a href="/account_admin.php">Accounts</a><a href="/game_admin.php">Games</a><a href="/profile_admin.php">Approvals</a><a href="/user_report_admin.php">Reports</a><a href="/popup_admin.php">Popups</a><a href="/admin_tools.php">Tools</a><a href="/">Home</a></nav><?php include __DIR__ . '/site_popups.php'; ?></body></html>
