<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';
$key = profile_account_key(); $display = profile_display_name($key); $accounts = profile_all_accounts();
$status=''; $statusType=''; $dataDir=profile_data_dir(); $pendingDir=__DIR__.'/profile_pending_pfps'; if(!is_dir($pendingDir))mkdir($pendingDir,0755,true);
if($_SERVER['REQUEST_METHOD']==='POST'){
  $requests=profile_load_json(profile_requests_file());
  $req=['account_key'=>$key,'current_display'=>$display,'requested_at'=>date('Y-m-d H:i:s'),'status'=>'pending'];
  $newUsername=profile_clean($_POST['username']??'',40); $newDisplay=profile_clean($_POST['display_name']??'',40); $newPassword=(string)($_POST['password']??'');
  if($newUsername!=='' && strtolower($newUsername)!==$key)$req['new_username']=strtolower($newUsername);
  if($newDisplay!=='' && $newDisplay!==$display)$req['new_display_name']=$newDisplay;
  if($newPassword!=='')$req['new_password']=$newPassword;
  $pfpData = $_POST['pfp_cropped_data'] ?? '';
  if (strpos($pfpData, 'data:image/png;base64,') === 0) {
    $safe=preg_replace('/[^a-z0-9_-]/i','-',$key); $pending='/profile_pending_pfps/'.$safe.'_'.time().'.png';
    file_put_contents(__DIR__.$pending, base64_decode(substr($pfpData, 22))); $req['new_pfp']=$pending;
  } elseif(isset($_FILES['pfp'])&&is_uploaded_file($_FILES['pfp']['tmp_name'])){ $info=@getimagesize($_FILES['pfp']['tmp_name']); if($info!==false){ $safe=preg_replace('/[^a-z0-9_-]/i','-',$key); $pending='/profile_pending_pfps/'.$safe.'_'.time().'.png'; move_uploaded_file($_FILES['pfp']['tmp_name'], __DIR__.$pending); $req['new_pfp']=$pending; }}
  if(count($req)>4){ $requests[]=$req; profile_save_json(profile_requests_file(),$requests); $status='Request sent. Keep using your old login until baldy approves it.';$statusType='good'; }
  elseif($status===''){$status='No changes entered.';$statusType='bad';}
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Settings</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 10% 20%,rgba(95,130,220,.42),transparent 28%),linear-gradient(120deg,#080d14,#141a24 45%,#222936);color:#eaf0fd;padding:22px}.wrap{width:min(720px,94vw);margin:auto}.card{background:rgba(24,30,41,.95);border:1px solid rgba(255,255,255,.13);border-radius:26px;padding:24px;box-shadow:0 18px 60px rgba(0,0,0,.35)}h1{font-size:clamp(2rem,8vw,4rem);letter-spacing:-.08em;margin:0 0 12px}.pfp{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid rgba(160,195,255,.35)}label{display:block;margin-top:14px;font-weight:950;color:rgba(234,240,253,.74)}input{width:100%;margin-top:8px;padding:13px 14px;border-radius:14px;border:1.5px solid rgba(160,195,255,.32);background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:850}.crop-box{display:none;margin-top:12px;padding:14px;border-radius:18px;background:rgba(10,15,24,.45);border:1px solid rgba(255,255,255,.1)}.crop-preview{width:140px;height:140px;border-radius:50%;object-fit:cover;border:3px solid rgba(160,195,255,.35);display:block;margin:10px auto}.range-row{display:grid;grid-template-columns:90px 1fr;gap:10px;align-items:center;margin-top:8px;color:rgba(234,240,253,.72);font-weight:900}.range-row input{margin:0}button,.btn{display:inline-block;text-decoration:none;border:none;border-radius:14px;padding:13px 16px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%);color:#111;margin-top:16px}.muted{color:rgba(234,240,253,.62);font-weight:800;line-height:1.45}.good{color:#bfffe0}.bad{color:#ffd0d0}</style></head><body><main class="wrap"><div class="card"><a class="btn" href="/">← Home</a><h1>Settings</h1><?php if($status):?><p class="<?=profile_e($statusType)?>"><strong><?=profile_e($status)?></strong></p><?php endif;?><img class="pfp" src="<?=profile_e(profile_pfp_url($key))? alt="">"><p class="muted">Logged in as <strong><?=profile_e($display)?></strong>. Any username, display name, password, or profile picture change has to be approved by baldy first.</p><form method="POST" enctype="multipart/form-data"><label>New login username</label><input name="username" placeholder="Leave blank to keep current"><label>New display name</label><input name="display_name" placeholder="Leave blank to keep current"><label>New password</label><input name="password" type="password" placeholder="Leave blank to keep current"><label>New profile picture</label><input id="pfpInput" name="pfp" type="file" accept="image/*"><input type="hidden" name="pfp_cropped_data" id="pfpCroppedData"><div class="crop-box" id="cropBox"><strong>Crop preview</strong><img id="cropPreview" class="crop-preview" alt="Preview"><div class="range-row"><span>Zoom</span><input id="cropZoom" type="range" min="1" max="3" step="0.01" value="1"></div><div class="range-row"><span>Left/right</span><input id="cropX" type="range" min="-100" max="100" step="1" value="0"></div><div class="range-row"><span>Up/down</span><input id="cropY" type="range" min="-100" max="100" step="1" value="0"></div><p class="muted">Move and zoom the image until the circle looks right. This preview is what baldy will approve.</p></div><button type="submit">Send for approval</button></form></div></main><script>
(function(){
  const input=document.getElementById('pfpInput'), box=document.getElementById('cropBox'), preview=document.getElementById('cropPreview'), hidden=document.getElementById('pfpCroppedData');
  const zoom=document.getElementById('cropZoom'), rx=document.getElementById('cropX'), ry=document.getElementById('cropY');
  let img=new Image();
  function render(){ if(!img.src)return; const z=parseFloat(zoom.value||'1'); const x=parseInt(rx.value||'0',10); const y=parseInt(ry.value||'0',10); preview.style.transform='translate('+x/3+'px,'+y/3+'px) scale('+z+')'; const c=document.createElement('canvas'); c.width=300; c.height=300; const ctx=c.getContext('2d'); ctx.fillStyle='transparent'; ctx.clearRect(0,0,300,300); const size=Math.max(300/img.width,300/img.height)*z; const w=img.width*size, h=img.height*size; ctx.drawImage(img,(300-w)/2+x,(300-h)/2+y,w,h); hidden.value=c.toDataURL('image/png'); }
  if(input)input.addEventListener('change',()=>{const f=input.files&&input.files[0]; if(!f)return; const url=URL.createObjectURL(f); img.onload=()=>{box.style.display='block'; preview.src=url; render();}; img.src=url;});
  [zoom,rx,ry].forEach(el=>el&&el.addEventListener('input',render));
})();
</script>

<!-- Bug #7 fix: Removed the duplicate popup poller (globalAdminPopup) that was
     competing with site_popups.php. Popups are now handled solely by
     site_popups.php (included below) which uses the correct modal/banner
     rendering and the correct dismiss_once endpoint for once_on_launch. -->
<?php include __DIR__ . '/site_popups.php'; ?>
</body></html>
