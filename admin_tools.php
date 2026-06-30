<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

date_default_timezone_set('Europe/London');
if (!profile_is_admin()) { http_response_code(403); echo 'No access.'; exit; }

$dataDir = __DIR__ . '/_private';
$messagesFile = $dataDir . '/messages.txt';
$archiveFile = $dataDir . '/message_archive.txt';
$chatFile = $dataDir . '/chat_messages.txt';
$chatLogFile = $dataDir . '/chat_archive.txt';
$issuesFile = $dataDir . '/issues.txt';
$deletedFile = $dataDir . '/deleted_message_log.txt';
$status = '';
$statusType = '';

function e($text){ return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function file_lines($file,$limit=80){ if(!file_exists($file)) return []; $lines=file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []; return array_slice(array_reverse($lines),0,$limit); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'clear_chat') {
    $old = file_exists($messagesFile) ? trim((string)file_get_contents($messagesFile)) : '';
    if ($old !== '') {
      $header = "\n\n===== MANUAL CLEAR " . date('Y-m-d H:i:s') . " =====\n";
      file_put_contents($archiveFile, $header . $old . "\n", FILE_APPEND | LOCK_EX);
    }
    file_put_contents($messagesFile, '', LOCK_EX);
    $status = 'Current chat cleared and archived.';
    $statusType = 'good';
  }
  if ($action === 'clear_live_chat') {
    $old = file_exists($chatFile) ? trim((string)file_get_contents($chatFile)) : '';
    if ($old !== '') {
      $header = "\n\n===== LIVE CHAT MANUAL CLEAR " . date('Y-m-d H:i:s') . " =====\n";
      file_put_contents($chatLogFile, $header . $old . "\n", FILE_APPEND | LOCK_EX);
    }
    file_put_contents($chatFile, '', LOCK_EX);
    $status = 'Live chat cleared and archived.';
    $statusType = 'good';
  }
}


function dates_from_lines($file){ $dates=[]; if(file_exists($file)){ foreach(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)?:[] as $l){ if(preg_match('/^(\d{4}-\d{2}-\d{2})/', $l, $m)) $dates[$m[1]]=true; }} return array_keys($dates); }
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
$toolDates = array_unique(array_merge([date('Y-m-d')], dates_from_lines($issuesFile), dates_from_lines($deletedFile), dates_from_lines($archiveFile), dates_from_lines($chatLogFile))); rsort($toolDates);
function lines_for_date($file,$date,$limit=80){ if(!file_exists($file)) return []; $out=[]; foreach(array_reverse(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)?:[]) as $l){ if(strpos($l,$date)===0 || strpos($l,'=====')!==false && strpos($l,$date)!==false) $out[]=$l; if(count($out)>=$limit) break; } return $out; }
$recentIssues = lines_for_date($issuesFile,$selectedDate,80);
$recentDeleted = lines_for_date($deletedFile,$selectedDate,80);
$recentArchive = lines_for_date($archiveFile,$selectedDate,80);
$recentChatArchive = lines_for_date($chatLogFile,$selectedDate,80);

?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Admin Tools</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.38),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px 22px 106px}.wrap{width:min(1100px,96vw);margin:auto}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}h1{font-size:clamp(2rem,7vw,4rem);letter-spacing:-.08em;text-transform:uppercase;margin:0}a{color:#a2c4ff;font-weight:950}.grid{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px}.card{background:rgba(24,30,41,.94);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.32);margin-bottom:16px}.danger{background:rgba(160,45,55,.95);color:white}.btn,button{border:none;border-radius:14px;padding:13px 16px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);color:#111}.status{font-weight:950;margin-bottom:12px}.good{color:#bfffe0}.bad{color:#ffd0d0}.log{padding:10px 12px;border-radius:14px;background:rgba(10,15,24,.5);border:1px solid rgba(255,255,255,.08);margin-bottom:8px;font-weight:800;color:rgba(238,243,252,.82);overflow-wrap:anywhere}.muted{color:rgba(234,240,253,.62);font-weight:800;line-height:1.45}details summary{cursor:pointer;font-size:1.2rem;font-weight:950}.date-select{width:100%;padding:11px 12px;border-radius:14px;background:rgba(10,15,24,.72);color:#eef3ff;border:1px solid rgba(255,255,255,.13);font-weight:900;margin-bottom:14px}.admin-clean-dock{position:fixed;left:50%;bottom:14px;z-index:2147482400;transform:translateX(-50%);width:min(980px,calc(100vw - 24px));display:flex;gap:8px;justify-content:center;flex-wrap:wrap;padding:10px;border-radius:24px;background:rgba(12,18,28,.78);border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 60px rgba(0,0,0,.38);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px)}.admin-clean-dock a{color:#eef3ff!important;text-decoration:none!important;font-weight:950;font-size:.86rem;padding:9px 12px;border-radius:999px;background:rgba(38,45,58,.86);border:1px solid rgba(255,255,255,.1);white-space:nowrap}@media(max-width:850px){.grid{grid-template-columns:1fr}.admin-clean-dock{bottom:8px;justify-content:flex-start;overflow-x:auto;flex-wrap:nowrap}}</style></head><body><main class="wrap"><div class="top"><h1>Tools</h1><div><a href="/admin.php">Admin</a> · <a href="/">Home</a></div></div><section class="card"><?php if($status!==''):?><div class="status <?=e($statusType)?>"><?=e($status)?></div><?php endif;?><h2>Maintenance</h2><p class="muted">Use this when chat gets messy. Clearing chat archives the old messages first.</p><form method="POST" onsubmit="return confirm('Clear the current chat? It will be archived first.');"><input type="hidden" name="action" value="clear_chat"><button class="danger" type="submit">Clear current chat</button></form><form method="POST" onsubmit="return confirm('Clear the live chat? It will be archived first.');" style="margin-top:10px;"><input type="hidden" name="action" value="clear_live_chat"><button class="danger" type="submit">Clear live chat</button></form></section><form method="GET"><select class="date-select" name="date" onchange="this.form.submit()"><?php foreach($toolDates as $d): ?><option value="<?=e($d)?>" <?=$d===$selectedDate?'selected':''?>><?=e($d)?></option><?php endforeach; ?></select></form><section class="grid"><div class="card"><details><summary>Recent reports</summary><?php if(!$recentIssues):?><div class="muted">No reports for this date.</div><?php else:foreach($recentIssues as $l):?><div class="log"><?=e($l)?></div><?php endforeach;endif;?></details></div><div class="card"><details><summary>Filtered / deleted</summary><?php if(!$recentDeleted):?><div class="muted">No filtered logs for this date.</div><?php else:foreach($recentDeleted as $l):?><div class="log"><?=e($l)?></div><?php endforeach;endif;?></details></div><div class="card"><details><summary>Recent archive log</summary><?php if(!$recentArchive):?><div class="muted">No archive for this date.</div><?php else:foreach($recentArchive as $l):?><div class="log"><?=e($l)?></div><?php endforeach;endif;?></details></div><div class="card"><details><summary>Live chat archive</summary><?php if(!$recentChatArchive):?><div class="muted">No live chat archive for this date.</div><?php else:foreach($recentChatArchive as $l):?><div class="log"><?=e($l)?></div><?php endforeach;endif;?></details></div></section></main><nav class="admin-clean-dock" aria-label="Admin quick menu"><a href="/admin.php">Dashboard</a><a href="/game_admin.php">Games</a><a href="/account_admin.php">Accounts</a><a href="/ban_admin.php">Bans</a><a href="/popup_admin.php">Popups</a><a href="/profile_admin.php">Profiles</a><a href="/user_report_admin.php">Reports</a><a href="/admin_tools.php">Tools</a></nav><?php include __DIR__ . '/site_popups.php'; ?></body></html>
