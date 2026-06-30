<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/ban_helpers.php';
require_once __DIR__ . '/profile_helpers.php';

date_default_timezone_set('Europe/London');

$currentUser = $_SESSION['username'] ?? '';
if (!profile_can_access_admin()) {
  http_response_code(403);
  echo 'No access.';
  exit;
}

$dataDir = __DIR__ . '/_private';
$learnDir = __DIR__ . '/learn';
$indexFile = __DIR__ . '/index.php';

function e($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function read_json_file($file) { $data = json_decode(@file_get_contents($file), true); return is_array($data) ? $data : []; }
function count_file_lines($file) { if (!file_exists($file)) return 0; $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); return is_array($lines) ? count($lines) : 0; }
function nice_bytes($bytes) { $bytes=(int)$bytes; if($bytes<1024)return $bytes.' B'; if($bytes<1048576)return round($bytes/1024,1).' KB'; return round($bytes/1048576,1).' MB'; }
function count_game_folders($learnDir) { if(!is_dir($learnDir)) return 0; $count=0; foreach(scandir($learnDir) as $item){ if($item==='.'||$item==='..')continue; if(is_dir($learnDir.'/'.$item)&&file_exists($learnDir.'/'.$item.'/index.html'))$count++; } return $count; }
function recent_lines($file,$n=5){ if(!file_exists($file)) return []; $lines=file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []; return array_slice(array_reverse($lines),0,$n); }

$accounts = read_json_file($dataDir . '/allowed_accounts.json');
$bans = prune_expired_bans();
$offences = read_json_file($dataDir . '/chat_filter_offences.json');
$rulesAgreed = read_json_file($dataDir . '/message_rules_agreed.json');
$profileRequests = read_json_file($dataDir . '/profile_requests.json');
$gamesJson = read_json_file($dataDir . '/games.json');
$gamesCount = count($gamesJson);
$chatMessagesCount = count_file_lines($dataDir . '/chat_messages.txt');
$issuesCount = count_file_lines($dataDir . '/issues.txt');
$deletedLogCount = count_file_lines($dataDir . '/deleted_message_log.txt');
$archiveFile = $dataDir . '/message_archive.txt';
$archiveSize = file_exists($archiveFile) ? filesize($archiveFile) : 0;
$gameFolders = count_game_folders($learnDir);
$lastWipeRaw = trim((string)@file_get_contents($dataDir . '/last_chat_wipe.txt'));
$lastWipeText = $lastWipeRaw ?: 'Not set';
$recentIssues = recent_lines($dataDir . '/issues.txt', 5);
$recentDeleted = recent_lines($dataDir . '/deleted_message_log.txt', 5);
$userFile = $dataDir . '/user_reports.json';
$user = read_json_file($userFile);
$selectedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
function issue_dates_from_file($file){ $dates=[]; if(file_exists($file)){ foreach(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)?:[] as $l){ if(preg_match('/^(\d{4}-\d{2}-\d{2})/', $l, $m)) $dates[$m[1]]=true; }} return array_keys($dates); }
$userReportDates = []; foreach ($user as $ur) { if (!empty($ur['date'])) $userReportDates[] = $ur['date']; }
$adminDates = array_unique(array_merge([date('Y-m-d')], issue_dates_from_file($dataDir . '/issues.txt'), $userReportDates));
$adminDates = array_values(array_filter($adminDates)); rsort($adminDates);
$recentIssuesForDate = []; if(file_exists($dataDir . '/issues.txt')){ foreach(array_reverse(file($dataDir . '/issues.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)?:[]) as $l){ if(strpos($l,$selectedDate)===0) $recentIssuesForDate[]=$l; }}
$userForDate = []; foreach ($user as $ur) { if (($ur['date'] ?? '') === $selectedDate) $userForDate[] = $ur; }
$newUserReportCount = 0; foreach ($user as $ur) { if (($ur['status'] ?? '') === 'new') $newUserReportCount++; }
$newReportCount = $newUserReportCount;
$newProfileRequestCount = count($profileRequests);

// Health check (now actually rendered — Bug fix from Proposal 2)
$health = [];
$healthLevel = 'ok'; // ok | warn | bad
if ($gamesCount !== $gameFolders) {
  $health[] = 'Game links (' . $gamesCount . ') and game folders (' . $gameFolders . ') do not match.';
  $healthLevel = 'warn';
}
if (count($bans) > 0) {
  $health[] = count($bans) . ' active ban(s) right now.';
  $healthLevel = 'warn';
}
if (count($profileRequests) > 0) {
  $health[] = count($profileRequests) . ' profile request(s) waiting.';
  $healthLevel = 'warn';
}
if ($newUserReportCount > 0) {
  $health[] = $newUserReportCount . ' new user report(s) to review.';
  $healthLevel = 'warn';
}
if (!$health) $health[] = 'No obvious problems found. All systems nominal.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <style>
    * { box-sizing: border-box; }
    body { margin:0; min-height:100vh; font-family:Inter,Arial,Helvetica,sans-serif; color:#eaf0fd; padding:24px 24px 106px; background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.42),transparent 28%),radial-gradient(circle at 90% 18%,rgba(220,230,255,.22),transparent 26%),linear-gradient(120deg,#080d14 0%,#141a24 45%,#222936 100%); }
    a { color:inherit; text-decoration:none; }
    .wrap { width:min(1180px,96vw); margin:0 auto; }
    .hero { display:grid; grid-template-columns:1fr auto; gap:16px; align-items:center; margin-bottom:18px; }
    h1 { margin:0; font-size:clamp(2.2rem,7vw,5rem); letter-spacing:-.1em; text-transform:uppercase; line-height:.9; }
    .sub { color:rgba(234,240,253,.64); font-weight:850; margin-top:8px; }
    .pill { display:inline-flex; align-items:center; gap:8px; border-radius:999px; padding:10px 14px; background:rgba(24,30,41,.78); border:1px solid rgba(255,255,255,.12); font-weight:950; white-space:nowrap; }
    .pill:hover { background:rgba(38,58,96,.94); }

    /* ===== PROPOSAL 3: Visual hierarchy — action tiles get accent border + hover lift ===== */
    .quick { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:18px 0; }
    .quick a { min-height:98px; padding:18px; display:flex; flex-direction:column; justify-content:space-between; transition:.15s; background:rgba(24,30,41,.92); border:1px solid rgba(160,195,255,.22); border-radius:24px; box-shadow:0 18px 60px rgba(0,0,0,.28); }
    .quick a:hover { transform:translateY(-2px); background:rgba(38,58,96,.94); border-color:rgba(160,195,255,.6); box-shadow:0 22px 70px rgba(0,0,0,.36); }
    .quick .icon { font-size:1.6rem; margin-bottom:6px; }
    .quick strong { font-size:1.05rem; letter-spacing:-.04em; }
    .quick span { color:rgba(234,240,253,.62); font-weight:800; font-size:.82rem; line-height:1.25; }
    .red-dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#ff3b3b;box-shadow:0 0 0 4px rgba(255,59,59,.16);margin-left:6px;vertical-align:middle}

    /* ===== PROPOSAL 2: Health banner (green/yellow/red) ===== */
    .health-banner { display:flex; align-items:flex-start; gap:12px; padding:14px 18px; border-radius:18px; margin-bottom:18px; font-weight:850; line-height:1.4; }
    .health-banner.ok { background:rgba(80,200,120,.14); border:1px solid rgba(80,200,120,.3); color:#8fdba8; }
    .health-banner.warn { background:rgba(255,200,80,.14); border:1px solid rgba(255,200,80,.3); color:#ffd98a; }
    .health-banner.bad { background:rgba(255,90,90,.14); border:1px solid rgba(255,90,90,.3); color:#ffa3a3; }
    .health-banner .health-icon { font-size:1.3rem; flex:0 0 auto; }
    .health-banner ul { margin:4px 0 0 0; padding-left:18px; }

    /* ===== PROPOSAL 2: Stats grid — every card has a proper label ===== */
    .stats { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:18px; }
    @media(max-width:980px){ .stats{grid-template-columns:repeat(3,1fr)} }
    .stat { background:rgba(24,30,41,.88); border:1px solid rgba(255,255,255,.12); border-radius:22px; padding:16px; }
    .label { color:rgba(234,240,253,.58); font-weight:950; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; }
    .num { font-size:clamp(1.5rem,4vw,2.5rem); font-weight:950; letter-spacing:-.08em; margin-top:8px; }
    .note { color:rgba(234,240,253,.55); font-size:.78rem; font-weight:800; margin-top:5px; }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .panel { background:rgba(24,30,41,.92); border:1px solid rgba(255,255,255,.12); border-radius:24px; padding:18px; box-shadow:0 18px 60px rgba(0,0,0,.28); }
    h2 { margin:0 0 12px; letter-spacing:-.05em; }
    .logs { display:grid; gap:8px; }
    .log { padding:11px 12px; border-radius:14px; background:rgba(10,15,24,.48); border:1px solid rgba(255,255,255,.08); color:rgba(238,243,252,.82); font-weight:800; line-height:1.35; overflow-wrap:anywhere; }
    .empty { color:rgba(234,240,253,.55); font-weight:800; }
    .date-select{width:100%;margin:0 0 12px;padding:11px 12px;border-radius:14px;background:rgba(10,15,24,.72);color:#eef3ff;border:1px solid rgba(255,255,255,.13);font-weight:900}

    /* ===== PROPOSAL 4: Bottom dock now includes Apps + Proxy ===== */
    .admin-clean-dock { position:fixed; left:50%; bottom:14px; z-index:2147482400; transform:translateX(-50%); width:min(980px,calc(100vw - 24px)); display:flex; gap:8px; justify-content:center; flex-wrap:wrap; padding:10px; border-radius:24px; background:rgba(12,18,28,.78); border:1px solid rgba(255,255,255,.12); box-shadow:0 18px 60px rgba(0,0,0,.38); backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); }
    .admin-clean-dock a { color:#eef3ff!important; font-weight:950; font-size:.86rem; padding:9px 12px; border-radius:999px; background:rgba(38,45,58,.86); border:1px solid rgba(255,255,255,.1); white-space:nowrap; }
    .admin-clean-dock a:hover { background:rgba(55,67,92,.96); }
    @media(max-width:980px){ .quick{grid-template-columns:repeat(2,1fr)} .stats{grid-template-columns:repeat(2,1fr)} .grid{grid-template-columns:1fr} .hero{grid-template-columns:1fr} }
    @media(max-width:560px){ body{padding:16px 16px 108px}.quick{grid-template-columns:1fr}.stats{grid-template-columns:1fr}.admin-clean-dock{bottom:8px;justify-content:flex-start;overflow-x:auto;flex-wrap:nowrap} }
  </style>
</head>
<body>
  <main class="wrap">
    <section class="hero">
      <div><h1>Admin</h1><div class="sub">Clean control panel for the site. Logged in as <?= e($currentUser) ?>.</div></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;"><a class="pill" href="/popup_admin.php">Pop-up</a><a class="pill" href="/admin_logout.php">Logout</a><a class="pill" href="/">Back to site</a></div>
    </section>

    <!-- PROPOSAL 2: Health banner — always visible, color-coded -->
    <section class="health-banner <?= e($healthLevel) ?>">
      <div class="health-icon"><?= $healthLevel === 'ok' ? '✓' : '⚠' ?></div>
      <div>
        <strong>Site Health: <?= $healthLevel === 'ok' ? 'All Good' : 'Needs Attention' ?></strong>
        <?php if (count($health) > 1 || $health[0] !== 'No obvious problems found. All systems nominal.'): ?>
          <ul><?php foreach ($health as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
    </section>

    <!-- PROPOSAL 1: Fixed quick-actions grid — 8 tiles, all admin pages reachable -->
    <section class="quick" aria-label="Admin quick actions">
      <a href="/game_admin.php"><span class="icon">🎮</span><strong>Games</strong><span><?= e($gamesCount) ?> games · manage + covers</span></a>
      <a href="/app_admin.php"><span class="icon">📱</span><strong>Apps</strong><span>add/edit embed/URL apps</span></a>
      <a href="/proxy_admin.php"><span class="icon">🌐</span><strong>Proxy</strong><span>edit proxy page HTML</span></a>
      <a href="/popup_admin.php"><span class="icon">📣</span><strong>Pop-ups</strong><span>launch + manage popups</span></a>
      <a href="/account_admin.php"><span class="icon">👥</span><strong>Accounts</strong><span><?= e(count($accounts)) ?> allowed users</span></a>
      <a href="/ban_admin.php"><span class="icon">🔨</span><strong>Bans</strong><span><?= e(count($bans)) ?> active bans</span></a>
      <a href="/profile_admin.php"><span class="icon">🖼️</span><strong>Profiles<?php if ($newProfileRequestCount > 0): ?><i class="red-dot"></i><?php endif; ?></strong><span><?= e($newProfileRequestCount) ?> pending requests</span></a>
      <a href="/user_report_admin.php"><span class="icon">🚩</span><strong>Reports<?php if ($newUserReportCount > 0): ?><i class="red-dot"></i><?php endif; ?></strong><span><?= e($newUserReportCount) ?> new to review</span></a>
    </section>

    <!-- PROPOSAL 2: Fixed stats grid — every card has a proper label -->
    <section class="stats">
      <div class="stat"><div class="label">Users</div><div class="num"><?= e(count($accounts)) ?></div><div class="note">allowed accounts</div></div>
      <div class="stat"><div class="label">Bans</div><div class="num"><?= e(count($bans)) ?></div><div class="note">currently active</div></div>
      <div class="stat"><div class="label">Games</div><div class="num"><?= e($gamesCount) ?></div><div class="note"><?= e($gameFolders) ?> folders on disk</div></div>
      <div class="stat"><div class="label">Chat</div><div class="num"><?= e($chatMessagesCount) ?></div><div class="note">messages today</div></div>
      <div class="stat"><div class="label">Issues</div><div class="num"><?= e($issuesCount) ?></div><div class="note">reports + requests</div></div>
      <div class="stat"><div class="label">Filtered</div><div class="num"><?= e($deletedLogCount) ?></div><div class="note">deleted log entries</div></div>
      <div class="stat"><div class="label">Offences</div><div class="num"><?= e(count($offences)) ?></div><div class="note">users with history</div></div>
      <div class="stat"><div class="label">Rules</div><div class="num"><?= e(count($rulesAgreed)) ?></div><div class="note">agreed to chat rules</div></div>
      <div class="stat"><div class="label">Archive</div><div class="num" style="font-size:1.4rem"><?= e(nice_bytes($archiveSize)) ?></div><div class="note">chat backup size</div></div>
      <div class="stat"><div class="label">Last Wipe</div><div class="num" style="font-size:1.05rem;letter-spacing:-.03em"><?= e($lastWipeText) ?></div><div class="note">daily chat reset</div></div>
    </section>

    <section class="grid">
      <!-- PROPOSAL 5: Date filter always visible (no <details> wrapper) -->
      <div class="panel">
        <h2>Recent reports<?php if ($newReportCount > 0): ?><i class="red-dot"></i><?php endif; ?></h2>
        <form method="GET"><select class="date-select" name="date" onchange="this.form.submit()"><?php foreach($adminDates as $d): ?><option value="<?= e($d) ?>" <?= $d===$selectedDate?'selected':'' ?>><?= e($d) ?></option><?php endforeach; ?></select></form>
        <div class="logs">
          <?php if(!$recentIssuesForDate && !$userForDate): ?><div class="empty">No reports for this date.</div><?php endif; ?>
          <?php foreach($recentIssuesForDate as $line): ?><div class="log"><?= e($line) ?></div><?php endforeach; ?>
          <?php foreach($userForDate as $r): ?><div class="log">User report <?= e($r['time'] ?? '') ?> · <?= e($r['from'] ?? '') ?> reported <?= e($r['reported_user'] ?? '') ?> · room <?= e($r['room'] ?? '') ?></div><?php endforeach; ?>
        </div>
      </div>
      <div class="panel">
        <h2>Recent filtered/deleted logs</h2>
        <div class="logs"><?php if(!$recentDeleted): ?><div class="empty">No deleted/filtered logs yet.</div><?php else: foreach($recentDeleted as $line): ?><div class="log"><?= e($line) ?></div><?php endforeach; endif; ?></div>
      </div>
    </section>
  </main>

  <!-- PROPOSAL 4: Bottom dock now includes Apps + Proxy -->
  <nav class="admin-clean-dock" aria-label="Admin quick menu">
    <a href="/admin.php">Dashboard</a>
    <a href="/game_admin.php">Games</a>
    <a href="/app_admin.php">Apps</a>
    <a href="/proxy_admin.php">Proxy</a>
    <a href="/account_admin.php">Accounts</a>
    <a href="/ban_admin.php">Bans</a>
    <a href="/popup_admin.php">Popups</a>
    <a href="/profile_admin.php">Profiles</a>
    <a href="/user_report_admin.php">Reports</a>
    <a href="/admin_tools.php">Tools</a>
  </nav>
  <?php include __DIR__ . '/site_popups.php'; ?>
</body>
</html>
