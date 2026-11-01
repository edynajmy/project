<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator','Management','Event');

$id=(int)($_GET['id']??0);
$s=db()->prepare('SELECT * FROM vw_booking_summary WHERE id=?');
$s->execute([$id]);
$b=$s->fetch();
if(!$b) exit('Booking not found');

$pdo=db();
$payStmt=$pdo->prepare('SELECT payment_method FROM bookings WHERE id=?');
$payStmt->execute([$id]);
$paymentMethod=$payStmt->fetchColumn() ?: 'Not specified';
$catStmt=$pdo->prepare('SELECT ec.name FROM events e LEFT JOIN event_categories ec ON ec.id=e.category_id WHERE e.id=?');
$catStmt->execute([(int)$b['event_id']]);
$category=$catStmt->fetchColumn() ?: '—';

$facStmt=$pdo->prepare('SELECT f.name, bf.quantity, bf.rate FROM booking_facilities bf JOIN facilities f ON f.id=bf.facility_id WHERE bf.booking_id=? ORDER BY f.name');
$facStmt->execute([$id]);
$facilities=$facStmt->fetchAll();

$roomStmt=$pdo->prepare('SELECT rt.name, br.quantity, br.nights, br.rate FROM booking_rooms br JOIN room_types rt ON rt.id=br.room_type_id WHERE br.booking_id=? ORDER BY rt.name');
$roomStmt->execute([$id]);
$rooms=$roomStmt->fetchAll();

$changesStmt=$pdo->prepare('SELECT * FROM booking_change_requests WHERE booking_id=? ORDER BY created_at DESC');
$changesStmt->execute([$id]);
$changes=$changesStmt->fetchAll();

$venueIdStmt=$pdo->prepare('SELECT venue_id FROM event_venues WHERE event_id=? LIMIT 1');
$venueIdStmt->execute([(int)$b['event_id']]);
$currentVenueId=(int)$venueIdStmt->fetchColumn();
$currentVenue=$b['other_venue_name'] ?: ($b['venue_names'] ?: '—');

$deposit = ((float)$b['total_estimate']) * 0.50;
$pageTitle='Review '.$b['booking_ref'];
require __DIR__.'/../../includes/header.php';
?>
<style>
.booking-back-link{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:42px;padding:10px 16px;margin:0 0 20px;border:1px solid #c9b27d;border-radius:8px;background:#f5ead2;color:#17362f!important;font-size:14px;font-weight:700;line-height:1.2;text-decoration:none!important;visibility:visible;opacity:1;position:relative;z-index:2}.booking-back-link:hover{background:#e8d5ad;color:#102a23!important}.booking-back-link span:first-child{font-size:20px;line-height:1}
.admin-review-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,.85fr);gap:24px;align-items:start}
.admin-review-stack{display:grid;gap:24px}
.review-panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px;box-shadow:0 16px 40px rgba(16,48,40,.07)}
.review-panel h2,.review-panel h3{margin:0 0 18px;color:#092c24}
.review-details{display:grid;grid-template-columns:1fr 1fr;border:1px solid var(--line);border-radius:12px;overflow:hidden}
.review-detail{padding:16px 18px;border-bottom:1px solid var(--line);display:flex;flex-direction:column;gap:6px}
.review-detail:nth-child(odd){border-right:1px solid var(--line)}
.review-detail:nth-last-child(-n+2){border-bottom:0}
.review-detail span{font-size:12px;color:#7c8782;text-transform:uppercase;letter-spacing:.08em}
.review-detail strong{color:#17362f;font-size:15px}
.review-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.review-list li{display:flex;justify-content:space-between;gap:18px;padding:11px 0;border-bottom:1px solid #ece9e1}
.review-list li:last-child{border-bottom:0}
.review-list .muted{color:#7b8581}
.financial-card{background:#0a2e26;color:#fff;border-radius:16px;padding:26px;box-shadow:0 18px 44px rgba(10,46,38,.16)}
.financial-card .eyebrow{color:#d7b66f}.financial-total{display:flex;justify-content:space-between;align-items:end;padding:18px 0;border-bottom:1px solid rgba(255,255,255,.16)}
.financial-total strong{font:500 32px Georgia,serif;color:#fff}.financial-row{display:flex;justify-content:space-between;padding:12px 0;color:#d9e2df}.financial-row strong{color:#fff}
.action-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}.btn-hold{background:#f3ead6;color:#17362f;border:1px solid #d5bd89}.btn-profile{background:#fff;color:#17362f;border:1px solid #c8d0cc}
.change-card{border:1px solid var(--line);border-radius:12px;padding:16px;margin-top:12px}.change-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.change-old{border-left:3px solid #c46b67;padding:10px 12px;background:#fff7f6}.change-new{border-left:3px solid #4e9c73;padding:10px 12px;background:#f3fbf6}.change-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#75807b;font-weight:700}.review-status{display:inline-flex;padding:7px 11px;border-radius:999px;background:#f2ead9;color:#765b23;font-size:12px;font-weight:700}.review-note{color:#6e7874;line-height:1.6}.modal-backdrop{display:none;position:fixed;inset:0;background:rgba(4,22,18,.58);z-index:9999;align-items:center;justify-content:center;padding:20px}.modal-backdrop.open{display:flex}.review-modal{width:min(560px,100%);background:#fff;border-radius:16px;padding:26px;box-shadow:0 24px 70px rgba(0,0,0,.28)}.review-modal textarea{width:100%;min-height:130px;border:1px solid #d4d8d5;border-radius:10px;padding:12px;font:inherit;resize:vertical}.review-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}
@media(max-width:900px){.admin-review-grid{grid-template-columns:1fr}.review-details,.change-grid{grid-template-columns:1fr}.review-detail:nth-child(odd){border-right:0}.review-detail:nth-last-child(-n+2){border-bottom:1px solid var(--line)}.review-detail:last-child{border-bottom:0}}
</style>
<section class="page-hero"><div class="container"><?php $fromActivities=(($_GET['from']??'')==='activities'); $backHref=$fromActivities ? url('admin/activities/index.php?category='.rawurlencode((string)($_GET['category']??'Corporate'))) : url('admin/bookings/index.php'); $backLabel=$fromActivities ? 'Back to Activity Management' : 'Back to Booking Management'; ?><a class="booking-back-link" href="<?=e($backHref)?>"><span aria-hidden="true">←</span><span><?=e($backLabel)?></span></a><div class="eyebrow">Management review · <?=e($b['booking_ref'])?></div><h1 class="page-title"><?=e($b['event_title'])?></h1><p>Owned by <?=e($b['user_name'])?> · <?=date('d M Y H:i',strtotime($b['start_at']))?></p></div></section>
<section class="section"><div class="container admin-review-grid">
  <div class="admin-review-stack">
    <div class="review-panel">
      <div class="eyebrow">Booking details</div><h2>Review the complete request.</h2>
      <div class="review-details">
        <div class="review-detail"><span>Owner</span><strong><?=e($b['user_name'])?></strong></div>
        <div class="review-detail"><span>Event category</span><strong><?=e($category)?></strong></div>
        <div class="review-detail"><span>Venue</span><strong><?=e($currentVenue)?></strong></div>
        <div class="review-detail"><span>Participants</span><strong><?=number_format((int)$b['expected_pax'])?> pax</strong></div>
        <div class="review-detail"><span>Date & time</span><strong><?=date('d M Y',strtotime($b['start_at']))?> · <?=date('H:i',strtotime($b['start_at']))?>–<?=date('H:i',strtotime($b['end_at']))?></strong></div>
        <div class="review-detail"><span>Status</span><strong><span class="review-status"><?=e(ucwords(str_replace('_',' ',$b['status'])))?></span></strong></div>
      </div>
      <div style="margin-top:22px">
        <h3>Selected facilities</h3>
        <?php if($facilities): ?><ul class="review-list"><?php foreach($facilities as $f): ?><li><span><?=e($f['name'])?></span><strong><?=number_format((int)$f['quantity'])?> × RM <?=number_format((float)$f['rate'],2)?></strong></li><?php endforeach; ?></ul><?php else: ?><p class="review-note">No facilities selected.</p><?php endif; ?>
      </div>
      <?php if($rooms): ?><div style="margin-top:22px"><h3>Rooms</h3><ul class="review-list"><?php foreach($rooms as $r): ?><li><span><?=e($r['name'])?></span><strong><?=number_format((int)$r['quantity'])?> room(s) · <?=number_format((int)$r['nights'])?> night(s)</strong></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php if(!empty($b['notes'])): ?><div style="margin-top:22px"><h3>Customer notes</h3><p class="review-note"><?=nl2br(e($b['notes']))?></p></div><?php endif; ?>

      <?php if(in_array($b['status'],['pending_review','pending_info'],true)): ?>
      <div class="action-row">
        <form method="post" action="<?=url('admin/bookings/action.php')?>"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$id?>"><button class="btn btn-gold" name="action" value="approve">Approve booking</button></form>
        <button type="button" class="btn btn-danger" onclick="openRejectModal()">Reject booking</button>
        <button type="button" class="btn btn-hold" onclick="openHoldModal()">Hold / Need clarification</button>
        <a class="btn btn-profile" href="<?=url('admin/users/view.php?id='.(int)$b['user_id'])?>">View user profile</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <aside class="admin-review-stack">
    <div class="financial-card">
      <div class="eyebrow">Financial summary</div>
      <div class="financial-total"><span>Total estimated cost</span><strong>RM <?=number_format((float)$b['total_estimate'],2)?></strong></div>
      <div class="financial-row"><span>Deposit required · 50%</span><strong>RM <?=number_format($deposit,2)?></strong></div>
      <div class="financial-row"><span>Payment method</span><strong><?=e($paymentMethod)?></strong></div>
    </div>
    <div class="review-panel">
      <div class="eyebrow">Change requests</div><h3>Requested changes</h3>
      <?php foreach($changes as $x): ?>
        <div class="change-card">
          <div style="display:flex;justify-content:space-between;gap:10px"><strong><?=e(ucfirst($x['status']))?></strong><small><?=date('d M Y H:i',strtotime($x['created_at']))?></small></div>
          <p class="review-note" style="margin:8px 0 12px"><?=e($x['reason'])?></p>
          <?php if($x['proposed_start_at']||$x['proposed_end_at']): ?><div class="change-grid"><div class="change-old"><div class="change-label">Current</div><?=date('d M Y H:i',strtotime($b['start_at']))?> → <?=date('d M Y H:i',strtotime($b['end_at']))?></div><div class="change-new"><div class="change-label">Requested</div><?=e($x['proposed_start_at']?date('d M Y H:i',strtotime($x['proposed_start_at'])):'—')?> → <?=e($x['proposed_end_at']?date('d M Y H:i',strtotime($x['proposed_end_at'])):'—')?></div></div><?php endif; ?>
          <?php if($x['proposed_venue_id']): $pv=$pdo->prepare('SELECT name FROM venues WHERE id=?');$pv->execute([(int)$x['proposed_venue_id']]);$pn=$pv->fetchColumn()?:'—'; ?><div class="change-grid" style="margin-top:12px"><div class="change-old"><div class="change-label">Current venue</div><?=e($currentVenue)?></div><div class="change-new"><div class="change-label">Requested venue</div><?=e($pn)?></div></div><?php endif; ?>
          <?php if($x['status']==='pending'): ?><form method="post" action="<?=url('admin/bookings/change_action.php')?>" class="action-row" style="margin-top:14px"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?= (int)$x['id']?>"><button class="btn btn-gold btn-sm" name="action" value="approve">Approve</button><button class="btn btn-danger btn-sm" name="action" value="reject">Reject</button></form><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if(!$changes): ?><p class="review-note">No change requests.</p><?php endif; ?>
    </div>
  </aside>
</div></section>

<div class="modal-backdrop" id="rejectModal"><div class="review-modal"><div class="eyebrow">Reject booking</div><h2>Reason for rejection</h2><p class="review-note">Provide a clear reason so the customer knows what needs to change.</p><form method="post" action="<?=url('admin/bookings/action.php')?>"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="reject"><textarea name="reason" required placeholder="e.g. The selected venue overlaps with a scheduled maintenance period. Please choose another date or venue."></textarea><div class="review-modal-actions"><button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button><button class="btn btn-danger">Reject booking</button></div></form></div></div>
<div class="modal-backdrop" id="holdModal"><div class="review-modal"><div class="eyebrow">Need clarification</div><h2>Put booking on hold</h2><p class="review-note">Add a note describing what information the customer needs to provide.</p><form method="post" action="<?=url('admin/bookings/action.php')?>"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="action" value="hold"><textarea name="reason" required placeholder="e.g. Please confirm the final catering headcount and dietary requirements."></textarea><div class="review-modal-actions"><button type="button" class="btn btn-outline" onclick="closeModals()">Cancel</button><button class="btn btn-hold">Place on hold</button></div></form></div></div>
<script>function openRejectModal(){document.getElementById('rejectModal').classList.add('open')}function openHoldModal(){document.getElementById('holdModal').classList.add('open')}function closeModals(){document.querySelectorAll('.modal-backdrop').forEach(m=>m.classList.remove('open'))}document.querySelectorAll('.modal-backdrop').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModals()}));</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
