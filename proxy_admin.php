<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';

date_default_timezone_set('Europe/London');
if (!profile_is_admin()) { http_response_code(403); echo 'No access.'; exit; }

if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null) {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}

function e($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}

$proxyFile = profile_data_dir() . '/proxy_content.json';
$proxyData = profile_load_json($proxyFile);
$status='';$statusType='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  profile_csrf_verify(); // Bug #6 fix: CSRF protection
  $action=$_POST['action']??'save';

  if($action==='toggle'){
    // Enable / disable the proxy page
    $proxyData['enabled'] = empty($proxyData['enabled']) ? true : false;
    $proxyData['updated_at'] = time();
    profile_save_json($proxyFile, $proxyData);
    $status = 'Proxy page ' . ($proxyData['enabled'] ? 'enabled.' : 'disabled.');
    $statusType='good';
  }
  elseif($action==='save'){
    $htmlContent = $_POST['proxy_html'] ?? '';
    $urlContent  = trim($_POST['proxy_url'] ?? '');
    // Basic size cap to prevent runaway storage (1 MB)
    if (strlen($htmlContent) > 1048576) {
      $status = 'HTML too large (max 1MB).';
      $statusType = 'bad';
    }
    // Phase 3: Mutual exclusivity — admin can save EITHER code OR URL, not both
    elseif ($htmlContent !== '' && $urlContent !== '') {
      $status = 'Please enter EITHER HTML code OR a URL, not both.';
      $statusType = 'bad';
    } else {
      if ($urlContent !== '') {
        // URL mode
        $proxyData['type'] = 'url';
        $proxyData['url']  = $urlContent;
        $proxyData['html'] = ''; // clear code when URL is saved
      } else {
        // Code mode (HTML)
        $proxyData['type'] = 'code';
        $proxyData['html'] = $htmlContent;
        $proxyData['url']  = ''; // clear URL when code is saved
      }
      $proxyData['enabled'] = isset($_POST['proxy_enabled']) ? true : false;
      $proxyData['updated_at'] = time();
      $proxyData['updated_at_text'] = date('Y-m-d H:i:s');
      profile_save_json($proxyFile, $proxyData);
      $status = 'Proxy ' . ($proxyData['type'] === 'url' ? 'URL' : 'HTML code') . ' saved.';
      $statusType = 'good';
    }
  }
}

$proxyHtml   = $proxyData['html'] ?? '';
$proxyUrl    = $proxyData['url'] ?? '';
$proxyType   = $proxyData['type'] ?? 'code';
$proxyEnabled = isset($proxyData['enabled']) ? (bool)$proxyData['enabled'] : true;
$updatedAt   = $proxyData['updated_at_text'] ?? '';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Proxy Admin</title><style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.38),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px;padding-bottom:100px}
.wrap{width:min(1200px,96vw);margin:auto}
.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}
h1{font-size:clamp(2rem,7vw,4rem);letter-spacing:-.08em;text-transform:uppercase;margin:0}
a{color:#a2c4ff;font-weight:950}
.card{background:rgba(24,30,41,.94);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.32);margin-bottom:18px}
label{display:block;margin-top:12px;color:rgba(234,240,253,.72);font-weight:900}
textarea{width:100%;min-height:400px;margin-top:7px;padding:13px 14px;border-radius:14px;border:1.5px solid rgba(160,195,255,.32);background:rgba(10,15,24,.72);color:#eef3ff;font-family:Consolas,monospace;font-weight:850;outline:none;resize:vertical}
button,input[type="submit"]{border:none;border-radius:14px;padding:13px 16px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);color:#111;margin-top:14px}
.btn-secondary{background:rgba(55,65,85,.78);color:#eaf0fd}
.btn-warning{background:#ffd93d;color:#111}
.btn-danger{background:#ff6b6b;color:#111}
.status{font-weight:950;margin-bottom:10px}
.bad{color:#ffd0d0}.good{color:#bfffe0}
.muted{color:rgba(234,240,253,.62);font-weight:800;line-height:1.45;font-size:.9rem}
.hint{background:rgba(10,15,24,.5);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:12px;margin-top:10px;font-weight:800;line-height:1.35;font-size:.9rem;color:rgba(238,243,252,.78)}
.badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.78rem;font-weight:950;margin-left:8px}
.badge-on{background:rgba(100,220,100,.18);color:#6f6}
.badge-off{background:rgba(220,100,100,.18);color:#f66}
.toggle-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:8px}
.toggle-row form{display:inline;margin:0}
.toggle-row button{margin-top:0;padding:8px 14px;font-size:.85rem}
.checkbox-row{display:flex;align-items:center;gap:8px;margin-top:14px;font-weight:850;color:rgba(234,240,253,.85)}
.checkbox-row input{width:auto;margin:0}
.preview-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin-bottom:8px}
.preview-bar button{margin-top:0;padding:8px 14px;font-size:.82rem}
</style></head><body><main class="wrap"><div class="top"><h1>Proxy Admin</h1><div><a href="/admin.php">Admin</a> &middot; <a href="/">Home</a></div></div>

<div class="card">
  <?php if($status!==''):?><div class="status <?=e($statusType)?>"><?=e($status)?></div><?php endif;?>

  <div class="toggle-row">
    <h2 style="margin:0;">Proxy Page Status</h2>
    <span class="badge <?= $proxyEnabled ? 'badge-on' : 'badge-off' ?>"><?= $proxyEnabled ? 'Enabled' : 'Disabled' ?></span>
    <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
      <input type="hidden" name="action" value="toggle">
      <button type="submit" class="<?= $proxyEnabled ? 'btn-warning' : 'btn-secondary' ?>"><?= $proxyEnabled ? 'Disable Proxy Page' : 'Enable Proxy Page' ?></button>
    </form>
    <?php if ($updatedAt): ?>
      <span class="muted">Last updated: <?= e($updatedAt) ?></span>
    <?php endif; ?>
  </div>
  <p class="muted">When disabled, the Proxy button on the home menu shows a "Proxy is currently disabled" message instead of loading the HTML below.</p>
</div>

<div class="card">
  <h2>Proxy Page Content</h2>
  <p class="muted">Enter EITHER a URL OR custom HTML code. When one has content, the other is automatically disabled.</p>
  <form method="POST"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
    <input type="hidden" name="action" value="save">

    <!-- Phase 3: URL input -->
    <label>External URL</label>
    <input type="text" name="proxy_url" id="proxyUrl" value="<?= e($proxyUrl) ?>" placeholder="https://example.com/proxy" style="width:100%;margin-top:7px;padding:13px 14px;border-radius:14px;border:1.5px solid rgba(255,255,255,.2);background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:850;outline:none;" <?= $proxyHtml ? 'disabled' : '' ?>>
    <div class="hint">Enter a URL (e.g. <code>https://dontleaktoeman.global.ssl.fastly.net/</code>) if the proxy is hosted externally. The URL will load in an iframe.</div>

    <!-- Phase 3: HTML code textarea -->
    <label style="margin-top:18px;">Custom HTML / JS Code</label>
    <textarea name="proxy_html" id="proxyHtml" placeholder="Paste any HTML here (iframe, embed, or full page)." style="<?= $proxyUrl ? 'opacity:.4;pointer-events:none;' : '' ?>" <?= $proxyUrl ? 'disabled' : '' ?>><?=e($proxyHtml)?></textarea>
    <div class="hint">Paste custom HTML code (iframe, embed, or full page). This renders inside the Proxy player view via srcdoc.</div>

    <p id="mutualExclHint" style="font-size:.82rem;font-weight:850;color:#ffd98a;margin-top:8px;display:none;">Only one field can have content at a time. The other is disabled.</p>

    <label class="checkbox-row">
      <input type="checkbox" name="proxy_enabled" value="1" <?= $proxyEnabled ? 'checked' : '' ?>>
      Proxy page is enabled (visible to users)
    </label>

    <button type="submit">Save Proxy Content</button>
    <button type="button" class="btn-secondary" onclick="document.getElementById('proxyHtml').value='';document.getElementById('proxyUrl').value='';enableBoth();">Clear Both</button>
  </form>
</div>

<div class="card">
  <div class="preview-bar">
    <h2 style="margin:0;">Preview</h2>
    <button type="button" class="btn-secondary" onclick="refreshPreview()">Refresh preview</button>
  </div>
  <p class="muted">See how the proxy page looks. This is the exact content users will see when they click Proxy.</p>
  <div style="background:#000;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.1);min-height:300px;">
    <iframe id="proxyPreview" srcdoc="<?=e($proxyHtml)?>" style="width:100%;height:400px;border:none;"></iframe>
  </div>
</div>

</main>

<script>
function refreshPreview(){
  const url = document.getElementById('proxyUrl').value.trim();
  const html = document.getElementById('proxyHtml').value;
  const preview = document.getElementById('proxyPreview');
  if (url) {
    // URL mode — load URL in preview iframe
    preview.src = url;
    preview.srcdoc = '';
  } else {
    // Code mode — render HTML
    preview.src = 'about:blank';
    preview.srcdoc = html;
  }
}

// Phase 3: Mutual exclusivity — typing in one field disables the other
function enableBoth() {
  const url = document.getElementById('proxyUrl');
  const html = document.getElementById('proxyHtml');
  const hint = document.getElementById('mutualExclHint');
  url.disabled = false;
  url.style.opacity = '1';
  html.disabled = false;
  html.style.opacity = '1';
  html.style.pointerEvents = 'auto';
  hint.style.display = 'none';
}

function handleUrlInput() {
  const url = document.getElementById('proxyUrl');
  const html = document.getElementById('proxyHtml');
  const hint = document.getElementById('mutualExclHint');
  if (url.value.trim() !== '') {
    html.disabled = true;
    html.style.opacity = '.4';
    html.style.pointerEvents = 'none';
    hint.style.display = 'block';
  } else {
    html.disabled = false;
    html.style.opacity = '1';
    html.style.pointerEvents = 'auto';
    hint.style.display = 'none';
  }
}

function handleHtmlInput() {
  const url = document.getElementById('proxyUrl');
  const html = document.getElementById('proxyHtml');
  const hint = document.getElementById('mutualExclHint');
  if (html.value.trim() !== '') {
    url.disabled = true;
    url.style.opacity = '.4';
    hint.style.display = 'block';
  } else {
    url.disabled = false;
    url.style.opacity = '1';
    hint.style.display = 'none';
  }
}

document.getElementById('proxyUrl').addEventListener('input', handleUrlInput);
document.getElementById('proxyHtml').addEventListener('input', handleHtmlInput);
</script>

<style>
.admin-clean-dock{position:fixed;left:50%;bottom:14px;z-index:2147482400;transform:translateX(-50%);width:min(980px,calc(100vw - 24px));display:flex;gap:8px;justify-content:center;flex-wrap:wrap;padding:10px;border-radius:24px;background:rgba(12,18,28,.78);border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 60px rgba(0,0,0,.38);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);font-family:Inter,Arial,Helvetica,sans-serif}
.admin-clean-dock a{color:#eef3ff!important;text-decoration:none!important;font-weight:950;font-size:.86rem;padding:9px 12px;border-radius:999px;background:rgba(38,45,58,.86);border:1px solid rgba(255,255,255,.1);white-space:nowrap}
.admin-clean-dock a:hover{background:rgba(55,67,92,.96)}
@media(max-width:620px){.admin-clean-dock{bottom:8px;justify-content:flex-start;overflow-x:auto;flex-wrap:nowrap}}
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
