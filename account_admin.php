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

$dataDir = __DIR__ . '/_private';
$accountsFile = $dataDir . '/allowed_accounts.json';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
if (!file_exists($accountsFile)) file_put_contents($accountsFile, '{}');

function e($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');}
function clean_text($text,$max=40){$text=trim((string)$text);$text=preg_replace('/\s+/',' ',$text);return mb_substr($text,0,$max);} 
$accounts=json_decode(@file_get_contents($accountsFile),true); if(!is_array($accounts)) $accounts=[];
$status=''; $statusType='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  $oldKey=strtolower(clean_text($_POST['old_username']??'',40));
  $username=clean_text($_POST['username']??'',40);
  $newKey=strtolower($username);
  $display=clean_text($_POST['display_name']??$username,40);
  $password=(string)($_POST['password']??'');
  $permissions = [
    'send_images' => isset($_POST['perm_send_images']),
    'send_popups' => isset($_POST['perm_send_popups']),
    'delete_messages' => isset($_POST['perm_delete_messages']),
    'access_admin' => isset($_POST['perm_access_admin'])
  ];
  if($action==='save'){
    if($username===''||$password===''){$status='Add a username and password.';$statusType='bad';}
    else{
      if($oldKey!=='' && $oldKey!==$newKey && isset($accounts[$oldKey])) unset($accounts[$oldKey]);
      $accounts[$newKey]=['password'=>$password,'display_name'=>$display?:$username,'permissions'=>$permissions,'updated_at'=>date('Y-m-d H:i:s')];
      ksort($accounts,SORT_NATURAL|SORT_FLAG_CASE);
      file_put_contents($accountsFile,json_encode($accounts,JSON_PRETTY_PRINT),LOCK_EX);
      $status='Account saved.';$statusType='good';
    }
  }
  if($action==='delete'){
    if(isset($accounts[$oldKey])){unset($accounts[$oldKey]);file_put_contents($accountsFile,json_encode($accounts,JSON_PRETTY_PRINT),LOCK_EX);$status='Account deleted.';$statusType='good';}
  }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Account Admin</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.38),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px}.wrap{width:min(1050px,96vw);margin:auto}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px}h1{font-size:clamp(2rem,7vw,4rem);letter-spacing:-.08em;text-transform:uppercase;margin:0}a{color:#a2c4ff;font-weight:950}.card{background:rgba(24,30,41,.94);border:1px solid rgba(255,255,255,.13);border-radius:24px;padding:22px;box-shadow:0 18px 60px rgba(0,0,0,.32);margin-bottom:18px}label{display:block;margin-top:12px;color:rgba(234,240,253,.72);font-weight:900}input{width:100%;margin-top:7px;padding:13px 14px;border-radius:14px;border:1.5px solid rgba(160,195,255,.32);background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:850;outline:none}button{border:none;border-radius:14px;padding:13px 16px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);color:#111;margin-top:14px}.delete{background:rgba(220,80,90,.95);color:white}.status{font-weight:950;margin-bottom:10px}.bad{color:#ffd0d0}.good{color:#bfffe0}.list{display:grid;gap:12px}.item{padding:15px;border-radius:18px;background:rgba(10,15,24,.5);border:1px solid rgba(255,255,255,.1)}.edit-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:10px;align-items:end}.small-show{white-space:nowrap;background:rgba(55,65,85,.9);color:#eaf0fd;border:1px solid rgba(255,255,255,.15);padding:11px 14px}.muted{color:rgba(234,240,253,.6);font-weight:800;margin-top:5px}.perm-grid{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:8px;margin-top:12px}.perm-grid label{margin:0;padding:10px;border-radius:14px;background:rgba(10,15,24,.45);border:1px solid rgba(255,255,255,.1);font-size:.86rem}.perm-grid input{width:auto;margin:0 6px 0 0}@media(max-width:900px){.edit-grid{grid-template-columns:1fr}}</style></head><body><main class="wrap"><div class="top"><h1>Accounts</h1><div><a href="/admin.php">Admin</a> · <a href="/">Home</a></div></div><section class="card"><?php if($status!==''):?><div class="status <?=e($statusType)?>"><?=e($status)?></div><?php endif;?><h2>Add account</h2><form method="POST"><input type="hidden" name="action" value="save"><label>Username</label><input name="username" required><label>Password</label><input id="newPass" type="password" name="password" required><button class="small-show" type="button" onclick="togglePassword('newPass',this)">Show password</button><label>Display name</label><input name="display_name"><div class="perm-grid"><label><input type="checkbox" name="perm_send_images"> Send images</label><label><input type="checkbox" name="perm_send_popups"> Do popups</label><label><input type="checkbox" name="perm_delete_messages"> Delete messages</label><label><input type="checkbox" name="perm_access_admin"> Access admin.php</label></div><button type="submit">Save account</button></form></section><section class="card"><h2>Edit accounts</h2><input id="accountSearch" type="search" placeholder="Search accounts..." style="margin-bottom:12px;"><div class="list" id="accountList"><?php if(count($accounts)===0):?><div class="muted">No accounts yet.</div><?php else:?><?php foreach($accounts as $username=>$acc): $id='pass_'.preg_replace('/[^a-z0-9]/i','_',$username); ?><div class="item" data-account-search="<?=e(strtolower($username . ' ' . ($acc['display_name']??'')))?>"><form method="POST" class="edit-grid"><input type="hidden" name="action" value="save"><input type="hidden" name="old_username" value="<?=e($username)?>"><div><label>Username</label><input name="username" value="<?=e($username)?>"></div><div><label>Display name</label><input name="display_name" value="<?=e($acc['display_name']??$username)?>"></div><div><label>Password</label><input id="<?=e($id)?>" name="password" type="password" value="<?=e($acc['password']??'')?>"></div><button type="button" class="small-show" onclick="togglePassword('<?=e($id)?>',this)">Show</button><button type="submit">Save</button><div class="perm-grid" style="grid-column:1/-1;"><?php $perms=$acc['permissions']??[]; ?><label><input type="checkbox" name="perm_send_images" <?=!empty($perms['send_images'])?'checked':''?>> Send images</label><label><input type="checkbox" name="perm_send_popups" <?=!empty($perms['send_popups'])?'checked':''?>> Do popups</label><label><input type="checkbox" name="perm_delete_messages" <?=!empty($perms['delete_messages'])?'checked':''?>> Delete messages</label><label><input type="checkbox" name="perm_access_admin" <?=!empty($perms['access_admin'])?'checked':''?>> Access admin.php</label></div></form><form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="old_username" value="<?=e($username)?>"><button class="delete" type="submit">Delete</button></form></div><?php endforeach;?><?php endif;?></div></section></main><script>function togglePassword(id,btn){const i=document.getElementById(id);if(!i)return;i.type=i.type==='password'?'text':'password';btn.textContent=i.type==='password'?'Show':'Hide';}</script>
<script>
(function(){const s=document.getElementById('accountSearch');if(!s)return;s.addEventListener('input',()=>{const q=s.value.toLowerCase().trim();document.querySelectorAll('[data-account-search]').forEach(el=>{el.style.display=el.getAttribute('data-account-search').includes(q)?'block':'none';});});})();
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

</body></html>
