<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator','Management');
$pageTitle='Contact Enquiries';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf($_POST['csrf']??null);
    $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??'new');
    $allowed=['new','in_progress','responded','closed'];
    if($id>0 && in_array($status,$allowed,true)) {
        $s=db()->prepare('SELECT * FROM contact_inquiries WHERE id=?'); $s->execute([$id]); $old=$s->fetch();
        if($old){ db()->prepare('UPDATE contact_inquiries SET status=? WHERE id=?')->execute([$status,$id]); audit('update','contact_inquiry',$id,['status'=>$old['status']],['status'=>$status]); flash('success','Enquiry status updated.'); }
    }
    redirect('admin/contact/index.php');
}

$rows=db()->query("SELECT c.*, u.full_name assigned_name FROM contact_inquiries c LEFT JOIN users u ON u.id=c.assigned_to ORDER BY c.created_at DESC")->fetchAll();
require __DIR__.'/../../includes/header.php';
$counts=['new'=>0,'in_progress'=>0,'responded'=>0,'closed'=>0];
foreach($rows as $row){$key=$row['status']??'new';if(isset($counts[$key]))$counts[$key]++;}
?>
<div class="admin-shell contact-admin-shell">
  <aside class="sidebar"><?php require __DIR__.'/../sidebar.php'; ?></aside>
  <section class="admin-content contact-admin-content">
    <div class="admin-top contact-admin-heading">
      <div><div class="eyebrow">Inbox · Guest Relations</div><h1 class="admin-title">Contact Enquiries</h1><p class="kicker">Review messages and event enquiries submitted through the public Contact page.</p></div>
      <a class="btn btn-outline btn-sm contact-export-link" href="mailto:events@berjayahills.com">Contact team</a>
    </div>
    <div class="stats contact-stats">
      <div class="stat"><small>Total enquiries</small><strong><?=count($rows)?></strong></div>
      <div class="stat"><small>New</small><strong><?=$counts['new']?></strong></div>
      <div class="stat"><small>In progress</small><strong><?=$counts['in_progress']?></strong></div>
      <div class="stat"><small>Responded / closed</small><strong><?=$counts['responded']+$counts['closed']?></strong></div>
    </div>
    <div class="panel contact-inquiry-panel">
      <div class="contact-panel-heading"><div><div class="eyebrow">Guest messages</div><h2>Enquiry inbox.</h2><p>Update each enquiry status or reply directly to the sender.</p></div><span class="contact-count"><?=count($rows)?> record<?=count($rows)===1?'':'s'?></span></div>
      <div class="table-wrap"><table class="table contact-inquiry-table"><thead><tr><th>Enquiry</th><th>Event</th><th>Guests</th><th>Preferred date</th><th>Preference</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead><tbody>
      <?php if(!$rows): ?><tr><td colspan="8"><div class="contact-empty"><span class="contact-empty-icon">✉</span><h3>No enquiries yet</h3><p>New messages submitted through the public Contact page will appear here.</p></div></td></tr><?php endif; ?>
      <?php foreach($rows as $r): ?><tr><td><strong><?=e($r['name'])?></strong><div class="admin-muted"><?=e($r['email'])?><?= $r['company']?' · '.e($r['company']):'' ?></div><div class="inquiry-message"><?=e($r['message'])?></div></td><td><?=e($r['event_type'])?></td><td><?=number_format((int)$r['estimated_guests'])?></td><td><?=e($r['preferred_date']?:'Not specified')?></td><td><?=e($r['contact_preference']??'Email')?></td><td><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e((string)$r['id'])?>"><select name="status" aria-label="Enquiry status" onchange="this.form.submit()"><option value="new" <?=$r['status']==='new'?'selected':''?>>New</option><option value="in_progress" <?=$r['status']==='in_progress'?'selected':''?>>In progress</option><option value="responded" <?=$r['status']==='responded'?'selected':''?>>Responded</option><option value="closed" <?=$r['status']==='closed'?'selected':''?>>Closed</option></select></form></td><td><?=e(date('d M Y, h:i A',strtotime($r['created_at'])))?></td><td><a class="btn btn-outline btn-sm contact-reply" href="mailto:<?=e($r['email'])?>?subject=Berjaya%20Hills%20Event%20Enquiry">Reply ↗</a></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
  </section>
</div>
<style>
.contact-admin-shell{grid-template-columns:245px minmax(0,1fr);align-items:stretch}.contact-admin-content{min-width:0;background:var(--cream);padding:42px clamp(22px,4vw,64px) 70px}.contact-admin-heading{align-items:center}.contact-admin-heading .kicker{max-width:650px}.contact-export-link{color:var(--forest);border-color:#c9c5bb;white-space:nowrap}.contact-stats{margin:24px 0 26px;grid-template-columns:repeat(4,minmax(0,1fr))}.contact-stats .stat{min-height:108px}.contact-stats .stat strong{font-size:32px}.contact-inquiry-panel{padding:24px 26px 12px;min-width:0}.contact-panel-heading{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:20px}.contact-panel-heading h2{font:600 34px/1.05 'Cormorant Garamond',serif;color:var(--forest);margin:7px 0}.contact-panel-heading p{color:var(--muted);margin:0;font-size:12px}.contact-count{background:#f4f0e6;border:1px solid #e7dfcd;color:#75613d;border-radius:30px;padding:6px 11px;font-size:11px;white-space:nowrap}.contact-inquiry-table{min-width:920px}.contact-inquiry-table th{white-space:nowrap}.contact-inquiry-table td{padding:16px 12px}.contact-inquiry-table select{border:1px solid #d8d5cd;background:#fff;border-radius:5px;padding:8px 9px;color:#24332c;max-width:125px}.inquiry-message{max-width:310px;margin-top:7px;color:#68756e;font-size:12px;line-height:1.5;white-space:normal}.contact-reply{color:var(--forest);border-color:#c9c5bb;white-space:nowrap}.contact-empty{text-align:center;padding:55px 20px 60px;color:var(--muted)}.contact-empty-icon{display:grid;place-items:center;width:48px;height:48px;margin:0 auto 14px;border-radius:50%;background:#f3f0e8;color:#b18d50;font-size:21px}.contact-empty h3{font:600 27px 'Cormorant Garamond',serif;color:var(--forest);margin:0 0 5px}.contact-empty p{margin:0;font-size:12px}@media(max-width:1050px){.contact-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.contact-admin-shell{display:block}.contact-admin-shell>.sidebar{position:relative;top:0;height:auto}.contact-admin-content{padding:25px 16px 40px}.contact-admin-heading{align-items:flex-start;flex-direction:column}.contact-panel-heading{align-items:flex-start;flex-direction:column}.contact-inquiry-panel{padding:18px 14px 8px}}
</style>
<?php require __DIR__.'/../../includes/footer.php'; ?>
