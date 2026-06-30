<?php
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
function js_escape_single($text){return str_replace(["\\","'","\r","\n"],["\\\\","\\'",' ',' '],(string)$text);} 
function parse_apps($file){return profile_load_json($file);}
function write_apps($file,$apps){profile_save_json($file,$apps);}

$appsFile = profile_data_dir() . '/apps.json';
$apps = parse_apps($appsFile);
$status='';$statusType='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  profile_csrf_verify(); // Bug #6 fix: CSRF protection
  $action=$_POST['action']??'create';
  $oldId=$_POST['old_id']??'';
  $appName=clean_text($_POST['app_name']??'',80);
  $appType=$_POST['app_type']??'embed';
  $appContent=trim((string)($_POST['app_content']??''));
  $appEnabled=isset($_POST['app_enabled']) ? 1 : 0;

  if($action==='delete'){
    if(isset($apps[$oldId])){
      // Delete cover image if exists
      if(!empty($apps[$oldId]['image'])){
        $oldImgPath = __DIR__ . parse_url($apps[$oldId]['image'], PHP_URL_PATH);
        if(file_exists($oldImgPath)) @unlink($oldImgPath);
      }
      unset($apps[$oldId]);
      write_apps($appsFile,$apps);
      $status='App deleted.';
      $statusType='good';
    } else {
      $status='App not found.';
      $statusType='bad';
    }
  } elseif($action==='toggle'){
    if(isset($apps[$oldId])){
      $apps[$oldId]['enabled'] = !($apps[$oldId]['enabled'] ?? true);
      write_apps($appsFile,$apps);
      $status='App '.($apps[$oldId]['enabled'] ? 'enabled.' : 'disabled.');
      $statusType='good';
    } else {
      $status='App not found.';
      $statusType='bad';
    }
  } elseif($appName===''){$status='Add a shown app name.';$statusType='bad';}
  elseif($appContent===''){$status='Add embed code or a URL.';$statusType='bad';}
  else{
    $id = $oldId !== '' ? $oldId : time() . '_' . bin2hex(random_bytes(3));
    
    $appEntry = $apps[$id] ?? [];
    $imageUrl = $appEntry['image'] ?? '';

    // Handle image upload
    if(isset($_FILES['app_image']) && is_uploaded_file($_FILES['app_image']['tmp_name'])){
      $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
      $mime = mime_content_type($_FILES['app_image']['tmp_name']);
      if(isset($allowed[$mime])){
        $dir = __DIR__ . '/app_images';
        if(!is_dir($dir)) mkdir($dir, 0755, true);
        $name = 'app_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        if(move_uploaded_file($_FILES['app_image']['tmp_name'], $dir . '/' . $name)){
          // Delete old image if exists
          if(!empty($imageUrl)){
            $oldPath = __DIR__ . parse_url($imageUrl, PHP_URL_PATH);
            if(file_exists($oldPath)) @unlink($oldPath);
          }
          $imageUrl = '/app_images/' . rawurlencode($name);
        }
      } else {
        $status = 'Image must be PNG, JPG, WEBP, or GIF.';
      }
    }

    $appEntry = [
      'name' => $appName,
      'type' => $appType,
      'content' => $appContent,
      'image' => $imageUrl,
      'enabled' => $appEnabled,
      'usage' => $appEntry['usage'] ?? 0,
      'created_at' => $appEntry['created_at'] ?? time(),
      'updated_at' => time()
    ];
    $apps[$id] = $appEntry;
    write_apps($appsFile,$apps);
    if($status==='') $status='App saved.';
    $statusType='good';
  }
}
$apps = parse_apps($appsFile);

uasort($apps, function($a, $b) {
  return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Apps Admin</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.38),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px}.wrap{width:min(1200px,96vw);margin:auto}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}h1{font-size:clamp(2rem,7vw,4rem);letter-spacing:-.08em;text-transform:uppercase;margin:0}a{color:#a2c4ff;font-weight:950}.grid{display:grid;grid-template-columns:360px 1fr;gap:16px}.card{background:rgba(24,30,41,.94);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:20px;box-shadow:0 18px 60px rgba(0,0,0,.32)}label{display:block;margin-top:12px;color:rgba(234,240,253,.72);font-weight:900}input,textarea,select{width:100%;margin-top:7px;padding:13px 14px;border-radius:14px;border:1.5px solid rgba(160,195,255,.32);background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:850;outline:none}textarea{min-height:120px;resize:vertical;font-family:Consolas,monospace}button,input[type="submit"]{border:none;border-radius:14px;padding:13px 16px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);color:#111;margin-top:14px}.status{font-weight:950;margin-bottom:10px}.bad{color:#ffd0d0}.good{color:#bfffe0}.app-list{display:grid;gap:10px;max-height:70vh;overflow:auto;padding-right:6px}.app-item{display:flex;gap:10px;align-items:center;padding:10px;border-radius:16px;background:rgba(10,15,24,.5);border:1px solid rgba(255,255,255,.09);cursor:pointer}.app-item:hover{background:rgba(38,58,96,.75)}.app-item img{width:58px;height:58px;object-fit:cover;border-radius:13px;background:#000}.app-item .app-icon-fallback{width:58px;height:58px;border-radius:13px;background:rgba(95,130,220,.3);display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;color:#a2c4ff;flex:0 0 58px}.muted{color:rgba(234,240,253,.62);font-weight:800;line-height:1.45;font-size:.9rem}.enabled-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.72rem;font-weight:950;margin-left:6px}.enabled-badge.on{background:rgba(100,220,100,.18);color:#6f6}.enabled-badge.off{background:rgba(220,100,100,.18);color:#f66}.app-type-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.7rem;font-weight:950;margin-left:4px;background:rgba(100,160,255,.18);color:#6af}.inline-actions{display:flex;gap:8px;margin-top:8px;flex-wrap:wrap}.inline-actions form{display:inline;margin:0}.inline-actions button{display:inline-block;margin:0;padding:7px 12px;font-size:.82rem;border-radius:10px}.btn-danger{background:#ff6b6b;color:#111}.btn-warning{background:#ffd93d;color:#111}.btn-primary{background:#a2c4ff;color:#111}.current-img-preview{max-width:120px;max-height:120px;border-radius:10px;margin-top:6px;border:1px solid rgba(255,255,255,.12)}@media(max-width:860px){.grid{grid-template-columns:1fr}.app-list{max-height:280px}}</style></head><body><main class="wrap"><div class="top"><h1>Apps Admin</h1><div><a href="/admin.php">Admin</a> · <a href="/">Home</a></div></div>

<div class="grid">
  <section class="card">
    <h2>Apps List</h2>
    <p class="muted">Click an app to edit, or create a new one below.</p>
    <input id="appAdminSearch" type="search" placeholder="Search apps..." style="margin:10px 0 10px;">
    <div class="app-list">
      <?php if(empty($apps)): ?>
        <div class="muted" style="text-align:center;padding:20px 0;">No apps yet.</div>
      <?php else: foreach($apps as $id => $a): $enc = e(json_encode(['id'=>$id,'name'=>$a['name'],'type'=>$a['type'],'content'=>$a['content'],'enabled'=>$a['enabled']??true,'image'=>$a['image']??''])); ?>
        <div class="app-item" data-app-search="<?=e(strtolower($a['name']))?>" onclick='pickApp(<?=$enc?>)'>
          <?php if(!empty($a['image'])): ?>
            <img src="<?=e($a['image'])? alt="">" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" alt=""><div class="app-icon-fallback" style="display:none"><?=e(strtoupper(mb_substr($a['name'],0,1)))?></div>
          <?php else: ?>
            <div class="app-icon-fallback"><?=e(strtoupper(mb_substr($a['name'],0,1)))?></div>
          <?php endif; ?>
          <div>
            <strong><?=e($a['name'])?></strong>
            <span class="enabled-badge <?=($a['enabled']??true)?'on':'off'?>"><?=($a['enabled']??true)?'Enabled':'Disabled'?></span>
            <span class="app-type-badge"><?=e($a['type'])?></span>
            <div class="muted">Used <?=(int)($a['usage']??0)?> times</div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <section class="card">
    <?php if($status!==''):?><div class="status <?=e($statusType)?>"><?=e($status)?></div><?php endif;?>
    <h2 id="formTitle">Create new app</h2>
    <form method="POST" enctype="multipart/form-data" id="appForm"><input type="hidden" name="csrf_token" value="<?= profile_csrf_token() ?>">
      <input type="hidden" name="action" id="action" value="create">
      <input type="hidden" name="old_id" id="oldId" value="">
      
      <label>App name</label>
      <input name="app_name" id="appName" required>

      <label>App image/thumbnail</label>
      <input name="app_image" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
      <div id="currentImageArea" style="display:none;margin-top:6px;">
        <img id="currentImagePreview" class="current-img-preview" src="" alt="Current image">
        <p style="margin:4px 0 0;font-weight:800;font-size:.82rem;opacity:.7;">Current image (upload new to replace)</p>
      </div>

      <label>App type</label>
      <select name="app_type" id="appType">
        <option value="embed">Embed / HTML code</option>
        <option value="url">External URL link</option>
      </select>

      <label>Content</label>
      <textarea name="app_content" id="appContent" placeholder="Paste embed code (iframe/HTML) or external URL (https://...)" required></textarea>
      <div class="muted" id="contentHint">For embeds, paste the full iframe or HTML code. For URLs, paste the full link including https://</div>

      <label style="display:flex;align-items:center;gap:8px;margin-top:12px;">
        <input type="checkbox" name="app_enabled" id="appEnabled" value="1" checked style="width:auto;margin:0;">
        App is enabled
      </label>

      <button type="submit" id="saveBtn">💾 Save app</button>
      <button type="button" onclick="clearForm()" style="background:rgba(55,65,85,.78);color:#eaf0fd;margin-left:8px;">Cancel</button>
    </form>
  </section>
</div>

</main>
<script>
let currentAppData = null;
function pickApp(a){
  currentAppData = a;
  document.getElementById('formTitle').textContent = 'Editing: ' + a.name;
  document.getElementById('action').value = 'edit';
  document.getElementById('oldId').value = a.id;
  document.getElementById('appName').value = a.name;
  document.getElementById('appType').value = a.type;
  document.getElementById('appContent').value = a.content;
  document.getElementById('appEnabled').checked = a.enabled;
  document.getElementById('saveBtn').textContent = '💾 Update app';
  
  // Show current image if exists
  const imgArea = document.getElementById('currentImageArea');
  const imgPreview = document.getElementById('currentImagePreview');
  if(a.image){
    imgArea.style.display = 'block';
    imgPreview.src = a.image;
  } else {
    imgArea.style.display = 'none';
  }
  window.scrollTo({top:0,behavior:'smooth'});
}
function clearForm(){
  currentAppData = null;
  document.getElementById('formTitle').textContent = 'Create new app';
  document.getElementById('action').value = 'create';
  document.getElementById('oldId').value = '';
  document.getElementById('appName').value = '';
  document.getElementById('appType').value = 'embed';
  document.getElementById('appContent').value = '';
  document.getElementById('appEnabled').checked = true;
  document.getElementById('saveBtn').textContent = '💾 Save app';
  document.getElementById('currentImageArea').style.display = 'none';
}
(function(){
  const s=document.getElementById('appAdminSearch');if(!s)return;
  s.addEventListener('input',()=>{const q=s.value.toLowerCase().trim();document.querySelectorAll('[data-app-search]').forEach(el=>{el.style.display=el.getAttribute('data-app-search').includes(q)?'flex':'none';});});
})();
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