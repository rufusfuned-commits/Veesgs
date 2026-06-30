<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null) {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}

require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

date_default_timezone_set('Europe/London');
if (!profile_is_admin()) { http_response_code(403); echo 'No access.'; exit; }

function e($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function clean_text($text,$max=100){$text=trim((string)$text);$text=preg_replace('/\s+/',' ',$text);return mb_substr($text,0,$max);}
function slugify_game($name){$slug=preg_replace('/[^A-Za-z0-9]+/','-',trim((string)$name));$slug=trim($slug,'-');return $slug!==''?$slug:'New-Game';}
function js_escape_single($text){return str_replace(["\\","'","\r","\n"],["\\\\","\\'",' ',' '],(string)$text);}

// ---------- NEW JSON-BASED STORAGE ----------
// Games live in _private/games.json. Each entry:
//   { name, url, image, enabled, usage, created_at, updated_at }
// The actual game files live in learn/<slug>/index.html (+ cover.png).

function games_file(){ return profile_data_dir() . '/games.json'; }
function parse_games(){
  $file = games_file();
  $data = profile_load_json($file);
  $out = [];
  if (is_array($data)) {
    foreach ($data as $g) {
      if (!is_array($g) || empty($g['url'])) continue;
      $out[] = [
        'name'       => (string)($g['name'] ?? ''),
        'url'        => (string)($g['url'] ?? ''),
        'image'      => (string)($g['image'] ?? ''),
        'enabled'    => isset($g['enabled']) ? (bool)$g['enabled'] : true,
        'usage'      => (int)($g['usage'] ?? 0),
        'created_at' => (int)($g['created_at'] ?? time()),
        'updated_at' => (int)($g['updated_at'] ?? time()),
      ];
    }
  }
  return $out;
}
function write_games($games){
  // Re-index and sort alphabetically
  usort($games, function($a, $b){ return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); });
  profile_save_json(games_file(), array_values($games));
}
function folder_from_url($url){
  if (preg_match('#^/learn/(.*?)/index\.html$#', $url, $m)) return rawurldecode($m[1]);
  return '';
}

$learnDir = __DIR__ . '/learn';
$games    = parse_games();
$status   = '';
$statusType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  profile_csrf_verify(); // Bug #6 fix: CSRF protection
  $action      = $_POST['action'] ?? 'create';
  $oldUrl      = $_POST['old_url'] ?? '';
  $gameName    = clean_text($_POST['game_name'] ?? '', 80);
  $folderName  = clean_text($_POST['folder_name'] ?? '', 100);
  $gameCode    = (string)($_POST['game_code'] ?? '');
  $batchSuffix = clean_text($_POST['batch_suffix'] ?? '', 50);
  // Bug #5 fix: slugify the suffix to prevent path traversal. Previously
  // clean_text only collapsed whitespace, allowing `/`, `..`, etc. through.
  // Now we strip everything except A-Z, a-z, 0-9, hyphen, underscore — matching
  // the same character set as slugify_game().
  $batchSuffix = preg_replace('/[^A-Za-z0-9_-]/', '', $batchSuffix);
  $gameEnabled = isset($_POST['game_enabled']) ? true : false;

  // ----- DELETE -----
  if ($action === 'delete' && $oldUrl !== '') {
    $newGames = [];
    $deleted = false;
    foreach ($games as $g) {
      if ($g['url'] === $oldUrl) {
        $deleted = true;
        // Optionally remove the folder too. We DO NOT delete the folder
        // because the user might re-add it later — only remove from JSON.
      } else {
        $newGames[] = $g;
      }
    }
    if ($deleted) {
      write_games($newGames);
      $games = parse_games();
      $status = 'Game removed from list (learn/ folder kept on disk).';
      $statusType = 'good';
    } else {
      $status = 'Game not found.';
      $statusType = 'bad';
    }
  }

  // ----- TOGGLE ENABLE/DISABLE -----
  elseif ($action === 'toggle' && $oldUrl !== '') {
    $newGames = [];
    $found = false;
    foreach ($games as $g) {
      if ($g['url'] === $oldUrl) {
        $g['enabled'] = !$g['enabled'];
        $g['updated_at'] = time();
        $found = true;
        $status = 'Game ' . ($g['enabled'] ? 'enabled.' : 'disabled.');
        $statusType = 'good';
      }
      $newGames[] = $g;
    }
    if ($found) { write_games($newGames); $games = parse_games(); }
    else { $status = 'Game not found.'; $statusType = 'bad'; }
  }

  // ----- BATCH SUFFIX RENAME -----
  elseif ($action === 'batch_suffix' && $batchSuffix !== '') {
    $newGames = [];
    foreach ($games as $g) {
      $oldFolder = folder_from_url($g['url']);
      if ($oldFolder !== '') {
        $newFolder = $oldFolder . $batchSuffix;
        $oldPath = $learnDir . '/' . $oldFolder;
        $newPath = $learnDir . '/' . $newFolder;
        if (is_dir($oldPath) && !is_dir($newPath)) {
          rename($oldPath, $newPath);
        }
        $g['url'] = '/learn/' . $newFolder . '/index.html';
        $g['image'] = ''; // image field is unused; frontend derives cover from url
        $g['updated_at'] = time();
      }
      $newGames[] = $g;
    }
    write_games($newGames);
    $games = parse_games();
    $status = 'Applied suffix "' . e($batchSuffix) . '" to all game folders and updated games.json.';
    $statusType = 'good';
  }

  // ----- CREATE / EDIT -----
  elseif ($gameName === '') { $status = 'Add a shown game name.'; $statusType = 'bad'; }
  else {
    $slug    = slugify_game($folderName !== '' ? $folderName : $gameName);
    $newUrl  = '/learn/' . $slug . '/index.html';
    $folder  = $learnDir . '/' . $slug;

    // If editing and folder name changed, rename the folder on disk
    if ($action === 'edit' && $oldUrl !== '') {
      $oldFolderName = folder_from_url($oldUrl);
      $oldFolder     = $learnDir . '/' . $oldFolderName;
      if ($oldFolderName !== '' && $oldFolderName !== $slug && is_dir($oldFolder) && !is_dir($folder)) {
        rename($oldFolder, $folder);
      }
    }

    // Make sure the folder exists and has an index.html
    if (!is_dir($folder)) mkdir($folder, 0755, true);
    if (trim($gameCode) !== '') {
      file_put_contents($folder . '/index.html', $gameCode, LOCK_EX);
    } elseif (!file_exists($folder . '/index.html')) {
      file_put_contents($folder . '/index.html', '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . e($gameName) . '</title></head><body style="font-family:Inter,Arial,sans-serif;background:#0e1320;color:#eef3ff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center"><div><h1>' . e($gameName) . '</h1><p>Game coming soon.</p></div></body></html>', LOCK_EX);
    }

    // Handle cover image upload
    if (isset($_FILES['cover']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
      $info = @getimagesize($_FILES['cover']['tmp_name']);
      if ($info !== false) {
        move_uploaded_file($_FILES['cover']['tmp_name'], $folder . '/cover.png');
      }
    }

    // Update games.json
    $found = false;
    $newGames = [];
    foreach ($games as $g) {
      if ($g['url'] === $oldUrl && $action === 'edit') {
        $newGames[] = [
          'name'       => $gameName,
          'url'        => $newUrl,
          'image'      => '',
          'enabled'    => $gameEnabled,
          'usage'      => $g['usage'] ?? 0,
          'created_at' => $g['created_at'] ?? time(),
          'updated_at' => time(),
        ];
        $found = true;
      } else {
        $newGames[] = $g;
      }
    }
    if (!$found) {
      $newGames[] = [
        'name'       => $gameName,
        'url'        => $newUrl,
        'image'      => '',
        'enabled'    => $gameEnabled,
        'usage'      => 0,
        'created_at' => time(),
        'updated_at' => time(),
      ];
    }
    // De-dupe by url
    $ded = [];
    foreach ($newGames as $g) { $ded[$g['url']] = $g; }
    write_games(array_values($ded));
    $games = parse_games();
    $status = 'Game saved and games.json updated.';
    $statusType = 'good';
  }
}

// Sort for display
usort($games, function($a, $b){ return strcasecmp($a['name'] ?? '', $b['name'] ?? ''); });
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Game Admin</title><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.38),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px;padding-bottom:100px}
.wrap{width:min(1200px,96vw);margin:auto}
.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}
h1{font-size:clamp(2rem,7vw,4rem);letter-spacing:-.08em;text-transform:uppercase;margin:0}
a{color:#a2c4ff;font-weight:950}
.grid{display:grid;grid-template-columns:360px 1fr;gap:16px}
.card{background:rgba(24,30,41,.94);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.32)}
label{display:block;margin-top:12px;color:rgba(234,240,253,.72);font-weight:900}
input,textarea,select{width:100%;margin-top:7px;padding:13px 14px;border-radius:14px;border:1.5px solid rgba(160,195,255,.32);background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:850;outline:none}
textarea{min-height:260px;resize:vertical;font-family:Consolas,monospace}
button,input[type="submit"]{border:none;border-radius:14px;padding:13px 16px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);color:#111;margin-top:14px}
.status{font-weight:950;margin-bottom:10px}.bad{color:#ffd0d0}.good{color:#bfffe0}
.game-list{display:grid;gap:10px;max-height:70vh;overflow:auto;padding-right:6px}
.game-option{display:flex;gap:10px;align-items:center;padding:10px;border-radius:16px;background:rgba(10,15,24,.5);border:1px solid rgba(255,255,255,.09);cursor:pointer}
.game-option:hover{background:rgba(38,58,96,.75)}
.game-option img{width:58px;height:58px;object-fit:cover;border-radius:13px;background:#000;flex:0 0 58px}
.game-option .game-icon-fallback{width:58px;height:58px;border-radius:13px;background:rgba(95,130,220,.3);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:#a2c4ff;flex:0 0 58px}
.muted{color:rgba(234,240,253,.62);font-weight:800;line-height:1.45;font-size:.9rem}
.enabled-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.72rem;font-weight:950;margin-left:6px}
.enabled-badge.on{background:rgba(100,220,100,.18);color:#6f6}.enabled-badge.off{background:rgba(220,100,100,.18);color:#f66}
.inline-actions{display:flex;gap:6px;margin-top:6px;flex-wrap:wrap}
.inline-actions form{display:inline;margin:0}
.inline-actions button{display:inline-block;margin:0;padding:6px 10px;font-size:.78rem;border-radius:9px}
.btn-danger{background:#ff6b6b;color:#111}
.btn-warning{background:#ffd93d;color:#111}
.btn-primary{background:#a2c4ff;color:#111}
@media(max-width:860px){.grid{grid-template-columns:1fr}.game-list{max-height:280px}}
</style></head>
<body><main class="wrap"><div class="top"><h1>Game Admin</h1><div><a href="/admin.php">Admin</a> &middot; <a href="/">Home</a></div></div>
<div class="grid">
  <section class="card">
    <h2>Games List</h2>
    <p class="muted">Click a game to edit it. <?= count($games) ?> games total.</p>
    <input id="gameAdminSearch" type="search" placeholder="Search games..." style="margin:10px 0 10px;">
    <div class="game-list">
      <?php if (empty($games)): ?>
        <div class="muted" style="text-align:center;padding:20px 0;">No games yet. Create one or run the games.json builder.</div>
      <?php else: foreach ($games as $g):
        $folder = folder_from_url($g['url']);
        $cover = $folder ? ('/learn/' . rawurlencode($folder) . '/cover.png') : '/favicon.png';
        $enc = e(json_encode($g));
      ?>
        <div class="game-option" data-game-search="<?= e(strtolower($g['name'] . ' ' . $folder)) ?>" onclick='pickGame(<?= $enc ?>)'>
          <img src="<?= e($cover) ? alt="">" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" alt="">
          <div class="game-icon-fallback" style="display:none"><?= e(strtoupper(mb_substr($g['name'], 0, 1))) ?></div>
          <div style="flex:1;min-width:0">
            <strong><?= e($g['name']) ?></strong>
            <span class="enabled-badge <?= $g['enabled'] ? 'on' : 'off' ?>"><?= $g['enabled'] ? 'Enabled' : 'Disabled' ?></span>
            <div class="muted"><?= e($folder) ?> &middot; used <?= (int)$g['usage'] ?> times</div>
            <div class="inline-actions">
              <form method="POST" onsubmit="return confirm('Delete this game from the list? (Folder kept on disk.)')"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="old_url" value="<?= e($g['url']) ?>">
                <button type="submit" class="btn-danger">Delete</button>
              </form>
              <form method="POST"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="old_url" value="<?= e($g['url']) ?>">
                <button type="submit" class="btn-warning"><?= $g['enabled'] ? 'Disable' : 'Enable' ?></button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <section class="card">
    <?php if ($status !== ''): ?><div class="status <?= e($statusType) ?>"><?= e($status) ?></div><?php endif; ?>
    <h2 id="formTitle">Create new game</h2>
    <form method="POST" enctype="multipart/form-data" id="gameForm"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
      <input type="hidden" name="action" id="action" value="create">
      <input type="hidden" name="old_url" id="oldUrl" value="">

      <label>Name shown on website</label>
      <input name="game_name" id="gameName" required>

      <label>Folder/file name inside /learn/</label>
      <input name="folder_name" id="folderName" placeholder="Example: Drift-Hunters">
      <div class="muted">Final path becomes /learn/<strong>folder-name</strong>/index.html</div>

      <label>Cover image (PNG recommended)</label>
      <input name="cover" type="file" accept="image/*">

      <label>Paste full game HTML/code (optional when editing)</label>
      <textarea name="game_code" id="gameCode" placeholder="Leave blank when editing if you only want to rename/change cover."></textarea>

      <label style="display:flex;align-items:center;gap:8px;margin-top:12px;">
        <input type="checkbox" name="game_enabled" id="gameEnabled" value="1" checked style="width:auto;margin:0;">
        Game is enabled (visible on homepage)
      </label>

      <button type="submit" id="saveBtn">Save game</button>
      <button type="button" onclick="clearForm()" style="background:rgba(55,65,85,.78);color:#eaf0fd;margin-left:8px;">Cancel</button>
    </form>
  </section>

  <section class="card" style="grid-column:1/-1">
    <h2>Batch rename all game folders</h2>
    <p class="muted">Add a suffix to ALL game folders and update games.json. Use carefully.</p>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
      <input type="hidden" name="action" value="batch_suffix">
      <label>Suffix to add to all game folders</label>
      <input name="batch_suffix" placeholder="Example: -v2" required>
      <div class="muted">Example: if folder is "game1" and suffix is "-v2", it becomes "game1-v2"</div>
      <button type="submit" class="btn-danger">Apply suffix to all games</button>
    </form>
  </section>
</div></main>

<script>
function folderFromUrl(url){const m=String(url||'').match(/^\/learn\/(.*?)\/index\.html$/);return m?decodeURIComponent(m[1]):'';}
function pickGame(g){
  document.getElementById('formTitle').textContent = 'Editing: ' + g.name;
  document.getElementById('action').value = 'edit';
  document.getElementById('oldUrl').value = g.url;
  document.getElementById('gameName').value = g.name;
  document.getElementById('folderName').value = folderFromUrl(g.url);
  document.getElementById('gameEnabled').checked = !!g.enabled;
  document.getElementById('saveBtn').textContent = 'Update game';
  document.getElementById('gameCode').value = '';
  window.scrollTo({top:0, behavior:'smooth'});
}
function clearForm(){
  document.getElementById('formTitle').textContent = 'Create new game';
  document.getElementById('action').value = 'create';
  document.getElementById('oldUrl').value = '';
  document.getElementById('gameName').value = '';
  document.getElementById('folderName').value = '';
  document.getElementById('gameEnabled').checked = true;
  document.getElementById('saveBtn').textContent = 'Save game';
  document.getElementById('gameCode').value = '';
}
(function(){
  const s = document.getElementById('gameAdminSearch');
  if (!s) return;
  s.addEventListener('input', () => {
    const q = s.value.toLowerCase().trim();
    document.querySelectorAll('[data-game-search]').forEach(el => {
      el.style.display = el.getAttribute('data-game-search').includes(q) ? 'flex' : 'none';
    });
  });
})();
</script>

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
  }
</style>
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
</body></html>
