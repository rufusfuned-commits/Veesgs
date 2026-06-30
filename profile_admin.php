<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';
if(!profile_is_admin()){http_response_code(403);echo 'No access.';exit;}
$requests=profile_load_json(profile_requests_file());$accounts=profile_load_json(profile_accounts_file());$notes=profile_load_json(profile_notifications_file());$status='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $i=(int)($_POST['i']??-1);$decision=$_POST['decision']??'';
  if(isset($requests[$i])){ $r=$requests[$i]; $key=strtolower($r['account_key']??'');
    if($decision==='approve' && isset($accounts[$key])){
      $newKey=strtolower($r['new_username']??$key); if($newKey!==$key){$accounts[$newKey]=$accounts[$key];unset($accounts[$key]);$key=$newKey;}
      if(isset($r['new_display_name']))$accounts[$key]['display_name']=$r['new_display_name'];
      if(isset($r['new_password']))$accounts[$key]['password']=$r['new_password'];
      if(isset($r['new_pfp'])){ if(!is_dir(__DIR__.'/profile_pfps'))mkdir(__DIR__.'/profile_pfps',0755,true); $safe=preg_replace('/[^a-z0-9_-]/i','-',$key); @copy(__DIR__.$r['new_pfp'],__DIR__.'/profile_pfps/'.$safe.'.png'); }
      $notes[$key][]=['time'=>time(),'message'=>'Your profile/account change was approved. Press OK to continue.']; profile_save_json(profile_accounts_file(),$accounts);profile_save_json(profile_notifications_file(),$notes);$status='Approved.';
    } else { $status='Rejected.'; }
    unset($requests[$i]);$requests=array_values($requests);profile_save_json(profile_requests_file(),$requests);
  }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Profile approvals</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.42),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:24px}.wrap{width:min(900px,96vw);margin:auto}h1{font-size:clamp(2rem,7vw,4rem);letter-spacing:-.08em}.card{background:rgba(24,30,41,.96);border:1px solid rgba(255,255,255,.13);border-radius:28px;padding:24px;box-shadow:0 18px 60px rgba(0,0,0,.35);margin-bottom:16px}.pfp{max-width:220px;max-height:220px;border-radius:24px;object-fit:cover}.actions{display:flex;gap:12px}.approve{background:#44d17a}.reject{background:#ff5c6a;color:white}button,a{display:inline-block;text-decoration:none;border:none;border-radius:14px;padding:13px 16px;font-weight:950;cursor:pointer;font-family:inherit;color:#111;background:linear-gradient(110deg,#a2c4ff,#6c91c2);margin-top:12px}.muted{color:rgba(234,240,253,.65);font-weight:800}</style></head><body><main class="wrap"><a href="/admin.php">← Admin</a><h1>Profile approvals</h1><?php if($status):?><p><strong><?=profile_e($status)?></strong></p><?php endif;?><?php if(!$requests):?><div class="card muted">No pending requests.</div><?php endif;?><?php foreach($requests as $i=>$r):?><div class="card"><h2><?=profile_e($r['account_key']??'unknown')?></h2><p class="muted">Requested at <?=profile_e($r['requested_at']??'')?></p><?php foreach(['new_username'=>'New username','new_display_name'=>'New display name','new_password'=>'New password'] as $k=>$label): if(isset($r[$k])):?><p><strong><?=$label?>:</strong> <?=profile_e($r[$k])?></p><?php endif; endforeach;?><?php if(isset($r['new_pfp'])):?><p><strong>New profile picture:</strong></p><img class="pfp" src="<?=profile_e($r['new_pfp'])? alt="">"><?php endif;?><form method="POST" class="actions"><input type="hidden" name="i" value="<?=$i?>"><button class="reject" name="decision" value="reject">Reject ←</button><button class="approve" name="decision" value="approve">Approve →</button></form></div><?php endforeach;?></main>

<!-- Bug #7 fix: Removed the duplicate popup poller (globalAdminPopup) that was
     competing with site_popups.php. Popups are now handled solely by
     site_popups.php (included below). -->
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
