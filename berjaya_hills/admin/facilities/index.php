<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator','Management','Event','IT');
$pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf($_POST['csrf']??''); $action=$_POST['action']??'';
 try{
  if($action==='save'){
   $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $category=trim($_POST['category']??''); $description=trim($_POST['description']??''); $quantity=max(0,(int)($_POST['quantity']??0)); $rate=max(0,(float)($_POST['unit_rate']??0)); $status=$_POST['status']??'available';
   if($name==='') throw new RuntimeException('Facility name is required.'); if(!in_array($status,['available','maintenance','inactive'],true)) throw new RuntimeException('Invalid status.');
   if($id){$pdo->prepare('UPDATE facilities SET name=?,category=?,description=?,quantity=?,unit_rate=?,status=? WHERE id=?')->execute([$name,$category,$description,$quantity,$rate,$status,$id]);audit('facility_updated','facility',$id);flash('success','Facility details updated.');}
   else{$pdo->prepare('INSERT INTO facilities(name,category,description,quantity,unit_rate,status) VALUES(?,?,?,?,?,?)')->execute([$name,$category,$description,$quantity,$rate,$status]);$newId=(int)$pdo->lastInsertId();audit('facility_created','facility',$newId);flash('success','Facility added.');}
  }elseif($action==='delete'){$id=(int)($_POST['id']??0);$pdo->prepare('DELETE FROM facilities WHERE id=?')->execute([$id]);audit('facility_deleted','facility',$id);flash('success','Facility deleted.');}
  else throw new RuntimeException('Unknown action.');
 }catch(Throwable $e){flash('error','Unable to complete action. This facility may be linked to existing bookings or venues. '.$e->getMessage());}
 redirect('admin/facilities/index.php');
}
$pageTitle='Facility Management';$fac=$pdo->query('SELECT * FROM facilities ORDER BY category,name')->fetchAll();require __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell"><aside class="sidebar"><?php require __DIR__.'/../sidebar.php';?></aside><section class="admin-content">
<div class="admin-top"><div><div class="eyebrow">Operations</div><h1 class="admin-title">Facility management</h1><p class="kicker">Track equipment, quantities, rates and maintenance status.</p></div></div>
<?php foreach(get_flashes() as $msg):?><div class="notice <?=e($msg[0])?>" style="margin:12px 0;padding:12px;border-radius:8px"><?=e($msg[1])?></div><?php endforeach;?>
<div class="stats"><div class="stat"><small>Total facilities</small><strong><?=count($fac)?></strong></div><div class="stat"><small>Available</small><strong><?=count(array_filter($fac,fn($x)=>$x['status']==='available'))?></strong></div></div>
<div style="display:flex;justify-content:flex-end;margin:18px 0"><button class="btn btn-gold" type="button" onclick="openFacilityForm()">＋ Add facility</button></div>
<div class="panel table-wrap" style="margin-top:12px"><table class="table"><thead><tr><th>Facility / details</th><th>Category</th><th>Quantity</th><th>Unit rate</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach($fac as $f):?><tr><td><strong><?=e($f['name'])?></strong><div class="muted" style="font-size:13px;margin-top:4px"><?=e($f['description']??'')?></div></td><td><?=e($f['category']??'—')?></td><td><?=(int)$f['quantity']?></td><td>RM <?=number_format((float)$f['unit_rate'],2)?></td><td><span class="status-pill"><?=e(ucfirst($f['status']))?></span></td><td><div style="display:flex;gap:8px;align-items:center"><button class="btn btn-gold btn-sm" type="button" title="Edit facility" aria-label="Edit facility" onclick='editFacility(<?=json_encode(["id"=>(int)$f["id"],"name"=>$f["name"],"category"=>$f["category"],"description"=>$f["description"],"quantity"=>(int)$f["quantity"],"unit_rate"=>$f["unit_rate"],"status"=>$f["status"]],JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT)?>)'>✎</button><form method="post" onsubmit="return confirm('Delete this facility? Existing booking or venue links may prevent deletion.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$f['id']?>"><button class="btn btn-danger btn-sm" type="submit">Delete</button></form></div></td></tr><?php endforeach;?>
<?php if(!$fac):?><tr><td colspan="6">No facilities found. Add a facility to get started.</td></tr><?php endif;?></tbody></table></div>
<style>
.facility-modal{position:fixed;inset:0;background:rgba(5,22,17,.68);backdrop-filter:blur(5px);z-index:9999;display:none;align-items:center;justify-content:center;padding:24px}
.facility-dialog{width:min(680px,100%);max-height:min(88vh,820px);overflow:auto;background:#fbfaf7;border:1px solid #e7e1d6;border-radius:18px;box-shadow:0 24px 80px rgba(0,0,0,.24);padding:0!important}
.facility-dialog-head{padding:25px 30px 20px;border-bottom:1px solid #e9e4db;display:flex;align-items:flex-start;justify-content:space-between;background:#fff;border-radius:18px 18px 0 0}
.facility-dialog-head h2{font-family:Georgia,serif;font-size:30px;font-weight:500;color:#102b22;margin:0 0 6px}
.facility-dialog-head p{margin:0;color:#77827d;font-size:13px}
.facility-close{border:0;background:#f2f0eb;border-radius:9px;width:38px;height:38px;font-size:20px;color:#34483f;cursor:pointer}
.facility-form{padding:25px 30px 0;display:grid;grid-template-columns:1fr 1fr;gap:19px 20px}
.facility-form label{display:flex;flex-direction:column;gap:8px;color:#34483f;font-size:13px;font-weight:650;letter-spacing:.01em;min-width:0}
.facility-form label.full{grid-column:1/-1}
.facility-form input,.facility-form select,.facility-form textarea{box-sizing:border-box;width:100%;min-height:46px;border:1px solid #d9d8d0;border-radius:9px;background:#fff;padding:11px 13px;color:#18352b;font:400 14px inherit;outline:none;transition:border-color .15s,box-shadow .15s}
.facility-form textarea{min-height:108px;resize:vertical;line-height:1.5}
.facility-form input:focus,.facility-form select:focus,.facility-form textarea:focus{border-color:#b9975b;box-shadow:0 0 0 3px rgba(201,166,103,.18)}
.facility-form .hint{font-size:11px;color:#89928d;font-weight:400;margin-top:-3px}
.facility-form-foot{grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:18px 30px 23px;margin:4px -30px 0;border-top:1px solid #e9e4db;background:#fff;border-radius:0 0 18px 18px}
.facility-form-foot .foot-note{font-size:12px;color:#89928d}
.facility-form-actions{display:flex;gap:10px}
@media(max-width:600px){.facility-modal{padding:10px}.facility-dialog-head,.facility-form{padding-left:19px;padding-right:19px}.facility-form{grid-template-columns:1fr;gap:15px}.facility-form label.full,.facility-form-foot{grid-column:1}.facility-form-foot{margin-left:-19px;margin-right:-19px;padding:16px 19px;align-items:flex-start;flex-direction:column}.facility-form-actions{width:100%}.facility-form-actions button{flex:1}}
</style>
<div id="facilityModal" class="facility-modal" hidden><div role="dialog" aria-modal="true" aria-labelledby="facilityModalTitle" class="panel facility-dialog"><div class="facility-dialog-head"><div><h2 id="facilityModalTitle">Add facility</h2><p>Enter the facility information below.</p></div><button type="button" class="facility-close" onclick="closeFacilityForm()" aria-label="Close">×</button></div><form method="post" id="facilityForm" class="facility-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="facilityId"><label class="full">Facility name <input name="name" id="facilityName" required maxlength="150" placeholder="e.g. PA System"></label><label>Category <input name="category" id="facilityCategory" placeholder="e.g. Audio Visual"></label><label>Quantity <input type="number" min="0" name="quantity" id="facilityQuantity" value="1" required></label><label>Unit rate (RM) <input type="number" min="0" step="0.01" name="unit_rate" id="facilityRate" value="0.00" required><span class="hint">Rate per unit</span></label><label>Availability status <select name="status" id="facilityStatus"><option value="available">Available</option><option value="maintenance">Under maintenance</option><option value="inactive">Inactive</option></select></label><label class="full">Description <textarea name="description" id="facilityDescription" rows="4" placeholder="Add useful details, specifications or notes (optional)"></textarea><span class="hint">A short description helps staff identify the facility.</span></label><div class="facility-form-foot"><span class="foot-note">Fields marked by browser validation are required.</span><div class="facility-form-actions"><button type="button" class="btn" onclick="closeFacilityForm()">Cancel</button><button type="submit" class="btn btn-gold">Save facility</button></div></div></form></div></div>
<script>
function openFacilityForm(){document.getElementById('facilityForm').reset();document.getElementById('facilityId').value='';document.getElementById('facilityQuantity').value=1;document.getElementById('facilityRate').value='0.00';document.getElementById('facilityModalTitle').textContent='Add facility';showFacilityModal();}
function editFacility(f){document.getElementById('facilityId').value=f.id;document.getElementById('facilityName').value=f.name||'';document.getElementById('facilityCategory').value=f.category||'';document.getElementById('facilityDescription').value=f.description||'';document.getElementById('facilityQuantity').value=f.quantity??0;document.getElementById('facilityRate').value=f.unit_rate??0;document.getElementById('facilityStatus').value=f.status||'available';document.getElementById('facilityModalTitle').textContent='Edit facility';showFacilityModal();}
function showFacilityModal(){const m=document.getElementById('facilityModal');m.hidden=false;m.style.display='flex';document.getElementById('facilityName').focus();}
function closeFacilityForm(){const m=document.getElementById('facilityModal');m.style.display='none';m.hidden=true;}
document.getElementById('facilityModal').addEventListener('click',e=>{if(e.target.id==='facilityModal')closeFacilityForm();});document.addEventListener('keydown',e=>{if(e.key==='Escape')closeFacilityForm();});
</script>
</section></div><?php require __DIR__.'/../../includes/footer.php';?>
