<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u=require_login();
$id=(int)($_GET['id']??0);
$s=db()->prepare('SELECT * FROM vw_booking_summary WHERE id=? AND user_id=?');
$s->execute([$id,$u['id']]);
$b=$s->fetch();
if(!$b){http_response_code(404);exit('Booking not found');}

// Keep a confirmed booking's stored status aligned with the event lifecycle.
// Once the event end time has passed, it is completed automatically.
if((string)$b['status']==='confirmed' && strtotime($b['end_at'])<=time()){
  db()->prepare("UPDATE bookings SET status='completed' WHERE id=? AND status='confirmed'")->execute([$id]);
  db()->prepare("UPDATE events SET status='completed' WHERE id=? AND status='confirmed'")->execute([(int)$b['event_id']]);
  $b['status']='completed';
}
$h=db()->prepare('SELECT * FROM booking_status_history WHERE booking_id=? ORDER BY created_at');
$h->execute([$id]);
$history=$h->fetchAll();
$img=db()->prepare('SELECT vi.image_path FROM event_venues ev JOIN venue_images vi ON vi.venue_id=ev.venue_id WHERE ev.event_id=? ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC LIMIT 1');
$img->execute([(int)$b['event_id']]);
$venueImage=$img->fetchColumn();
$venueLabel=$b['other_venue_name'] ?: ($b['venue_names']??null);
$venueImage=venue_image_url($venueImage ?: null,$venueLabel);
$pageTitle='Booking '.$b['booking_ref'];
$ns=db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$ns->execute([$u['id']]);
$userUnreadNotifications=(int)$ns->fetchColumn();
$status=(string)$b['status'];
$statusLabel=ucwords(str_replace('_',' ',$status));
$statusClass=$status==='confirmed'||$status==='completed'?'badge-green':($status==='rejected'||$status==='cancelled'?'badge-red':'badge-gold');
$steps=[
  ['key'=>'draft','label'=>'Draft','desc'=>'Saved booking details'],
  ['key'=>'pending_review','label'=>'Under review','desc'=>'Management is reviewing'],
  ['key'=>'confirmed','label'=>'Confirmed','desc'=>'Booking has been approved'],
  ['key'=>'completed','label'=>'Completed','desc'=>'Event has been completed'],
];
$stepIndex=0;
if($status==='draft')$stepIndex=0;
elseif($status==='pending_review')$stepIndex=1;
elseif($status==='confirmed')$stepIndex=2;
elseif($status==='completed')$stepIndex=3;
elseif(in_array($status,['rejected','cancelled'],true))$stepIndex=1;
require __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell user-shell booking-detail-page">
  <aside class="sidebar"><?php require __DIR__ . '/../sidebar.php'; ?></aside>
  <section class="admin-content user-content">
    <section class="booking-detail-hero">
      <div class="booking-detail-hero-bg" style="background-image:url('<?=e($venueImage ?: url('assets/img/colmar-tropicale.jpg'))?>')"></div>
      <div class="booking-detail-hero-overlay"></div>
      <div class="booking-detail-hero-inner">
        <div>
          <div class="eyebrow">Booking <?=e($b['booking_ref'])?></div>
          <h1><?=e($b['event_title'])?></h1>
          <div class="booking-hero-meta">
            <span>◷ <?=date('d M Y',strtotime($b['start_at']))?></span>
            <span>◷ <?=date('H:i',strtotime($b['start_at']))?> – <?=date('H:i',strtotime($b['end_at']))?></span>
            <span>⌖ <?=e(($b['other_venue_name'] ?: ($b['venue_names']??'Venue pending')))?></span>
          </div>
        </div>
        <div class="booking-ref-card">
          <small>Current status</small>
          <strong><?=e($statusLabel)?></strong>
          <span class="badge <?=$statusClass?>"><?=e(str_replace('_',' ',$status))?></span>
        </div>
      </div>
    </section>

    <section class="section booking-detail-section">
      <div class="container booking-detail-grid">
        <main>
          <div class="booking-detail-status-card">
            <div class="detail-section-head">
              <div><div class="eyebrow">Booking progress</div><h2>Where your request stands.</h2><p>Follow the progress of your event request from saved draft to completion.</p></div>
            </div>
            <div class="booking-status-track">
              <?php foreach($steps as $i=>$step): $done=$i<=$stepIndex; $active=$i===$stepIndex; ?>
                <div class="booking-status-step <?=$done?'done':''?> <?=$active?'active':''?>">
                  <div class="status-dot"><?=$done?'✓':($i+1)?></div>
                  <div><strong><?=e($step['label'])?></strong><small><?=e($step['desc'])?></small></div>
                </div>
                <?php if($i<count($steps)-1): ?><div class="status-connector <?=$i<$stepIndex?'done':''?>"></div><?php endif; ?>
              <?php endforeach; ?>
            </div>
            <?php if($status==='rejected'): ?>
              <div class="detail-alert danger"><strong>Booking not approved.</strong><span>Please review the management response in your notifications or contact the event team if you need clarification.</span></div>
            <?php elseif($status==='cancelled'): ?>
              <div class="detail-alert danger"><strong>Booking cancelled.</strong><span>This booking is no longer active.</span></div>
            <?php elseif($status==='pending_review'): ?>
              <div class="detail-alert"><strong>Your request is being reviewed.</strong><span>The management team will update the booking once a decision has been made.</span></div>
            <?php elseif($status==='confirmed'): ?>
              <div class="detail-alert success"><strong>Your event is confirmed.</strong><span>Your booking is approved. Any changes after confirmation must go through the change-request workflow.</span></div>
            <?php endif; ?>
          </div>

          <div class="booking-detail-card">
            <div class="detail-section-head compact"><div><div class="eyebrow">Event overview</div><h2>Your event details.</h2></div></div>
            <div class="detail-info-grid">
              <div><span>Event</span><strong><?=e($b['event_title'])?></strong></div>
              <div><span>Venue</span><strong><?=e(($b['other_venue_name'] ?: ($b['venue_names']??'Venue pending')))?></strong></div>
              <div><span>Event date</span><strong><?=date('d F Y',strtotime($b['start_at']))?></strong></div>
              <div><span>Time</span><strong><?=date('H:i',strtotime($b['start_at']))?> – <?=date('H:i',strtotime($b['end_at']))?></strong></div>
              <div><span>Participants</span><strong><?=number_format((int)$b['expected_pax'])?> pax</strong></div>
              <div><span>Created</span><strong><?=date('d M Y · H:i',strtotime($b['created_at']))?></strong></div>
            </div>
          </div>

          <div class="booking-detail-card">
            <div class="detail-section-head compact"><div><div class="eyebrow">Activity</div><h2>Booking timeline.</h2></div></div>
            <div class="detail-timeline">
              <?php foreach($history as $i=>$x): ?>
                <div class="detail-timeline-item">
                  <div class="detail-timeline-marker">✓</div>
                  <div><strong><?=e(ucwords(str_replace('_',' ',$x['new_status'])))?></strong><small><?=date('d M Y · H:i',strtotime($x['created_at']))?></small><p><?=e($x['note']??'Status updated.')?></p></div>
                </div>
              <?php endforeach; if(!$history): ?>
                <div class="detail-empty">No timeline activity yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </main>

        <aside class="booking-detail-aside">
          <div class="booking-venue-card">
            <div class="booking-venue-image" style="background-image:url('<?=e($venueImage ?: url('assets/img/colmar-tropicale.jpg'))?>')"></div>
            <div class="booking-venue-body">
              <div class="eyebrow">Selected venue</div>
              <h3><?=e(($b['other_venue_name'] ?: ($b['venue_names']??'Venue pending')))?></h3>
              <p>Your selected event space for this booking.</p>
              <a class="btn btn-outline detail-outline" href="<?=url('public/venues/index.php')?>">Explore venues →</a>
            </div>
          </div>

          <div class="booking-cost-card">
            <div class="eyebrow">Booking estimate</div>
            <div class="cost-row"><span>Estimated total</span><strong>RM <?=number_format((float)$b['total_estimate'],2)?></strong></div>
            <small>Final pricing may be confirmed or adjusted by management based on your complete event requirements.</small>
          </div>

          <div class="booking-action-card">
            <div class="eyebrow">Next steps</div>
            <?php if($status==='draft'): ?>
              <h3>Ready to continue?</h3><p>Return to the booking form and complete your event details before submitting.</p>
              <a class="btn btn-gold full-btn" href="<?=url('user/bookings/create.php?draft_id='.$b['id'])?>">Continue booking →</a>
            <?php elseif($status==='confirmed'): ?>
              <h3>Need a change?</h3><p>Confirmed bookings are locked. Send a change request for management review.</p>
              <a class="btn btn-gold full-btn" href="<?=url('user/bookings/change.php?id='.$b['id'])?>">Request a change →</a>
            <?php elseif($status==='pending_review'): ?>
              <h3>Management review</h3><p>No action is required right now. We will update you when your request is reviewed.</p>
            <?php elseif(in_array($status,['rejected','cancelled'],true)): ?>
              <h3>Need help?</h3><p>Contact the event team if you would like to discuss this booking.</p>
              <a class="btn btn-gold full-btn" href="<?=url('contact.php')?>">Contact event team →</a>
            <?php else: ?>
              <h3>Event completed</h3><p>This booking has reached the end of its workflow.</p>
            <?php endif; ?>
            <a class="btn btn-outline detail-outline full-btn" href="<?=url('user/bookings/index.php')?>">← Back to my bookings</a>
          </div>
        </aside>
      </div>
    </section>
  </section>
</div>
<?php require __DIR__.'/../../includes/footer.php'; ?>
