<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u=require_login();
$pageTitle='My Dashboard';
$pdo=db();

// Automatically move confirmed bookings to Completed once their event has ended.
// This keeps the user's progress tracker in sync without requiring a manual action.
$pdo->prepare("UPDATE bookings b JOIN events e ON e.id=b.event_id SET b.status='completed' WHERE b.user_id=? AND b.status='confirmed' AND e.end_at<=NOW()")->execute([$u['id']]);
$pdo->prepare("UPDATE events e JOIN bookings b ON b.event_id=e.id SET e.status='completed' WHERE b.user_id=? AND b.status='completed' AND e.end_at<=NOW() AND e.status='confirmed'")->execute([$u['id']]);

$s=$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=? AND status IN ('pending_review','confirmed')");$s->execute([$u['id']]);$active=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id=? AND status='draft'");$s->execute([$u['id']]);$drafts=(int)$s->fetchColumn();
$s=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");$s->execute([$u['id']]);$unread=(int)$s->fetchColumn();
$userUnreadNotifications=$unread;
$s=$pdo->prepare("SELECT * FROM vw_booking_summary WHERE user_id=? ORDER BY created_at DESC LIMIT 5");$s->execute([$u['id']]);$bookings=$s->fetchAll();
$plannerEmail='events@berjayahills.local';
$plannerPhone='+60 3 0000 0000';
$brochureUrl=url('assets/docs/berjaya-hills-venue-guide.pdf');
require __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell user-shell user-dashboard-v28">
  <aside class="sidebar"><?php require __DIR__ . '/../sidebar.php'; ?></aside>
  <section class="admin-content user-content">
    <div class="user-dashboard-hero">
      <div class="user-dashboard-hero-image"></div>
      <div class="user-dashboard-hero-overlay"></div>
      <div class="user-dashboard-hero-content">
        <div>
          <div class="eyebrow">Private planning workspace</div>
          <h1 class="admin-title">Welcome, <?=e($u['full_name'])?>.</h1>
          <p>Manage your event requests, booking status and notifications from one place.</p>
        </div>
        <a class="btn btn-gold" href="<?=url('user/bookings/create.php?fresh=1')?>">▣ &nbsp; Open booking form →</a>
      </div>
    </div>

    <div class="user-kpi-grid-v28">
      <div class="user-kpi-v28 kpi-green"><span class="kpi-icon">▣</span><div><small>Active bookings</small><strong><?=$active?></strong></div></div>
      <div class="user-kpi-v28 kpi-gold"><span class="kpi-icon">▤</span><div><small>Drafts</small><strong><?=$drafts?></strong></div></div>
      <div class="user-kpi-v28 kpi-rose"><span class="kpi-icon">♧</span><div><small>Unread notifications</small><strong><?=$unread?></strong></div></div>
      <div class="user-kpi-v28 kpi-blue"><span class="kpi-icon">♙</span><div><small>Account status</small><strong class="status-word">Approved</strong></div></div>
    </div>

    <section class="user-quick-v28">
      <div class="user-section-title"><div class="eyebrow">Get started</div><h2>Quick actions</h2><p>Common tasks and useful resources for planning your event.</p></div>
      <div class="user-quick-grid-v28">
        <a class="user-action-card primary" href="<?=url('user/bookings/create.php?fresh=1')?>"><span class="action-icon">▣</span><span><b>+ New booking</b><small>Plan a new event</small></span></a>
        <a class="user-action-card" href="<?=$brochureUrl?>" target="_blank" rel="noopener"><span class="action-icon">⇩</span><span><b>Download venue guide</b><small>Spaces, facilities & key information</small></span></a>
        <a class="user-action-card" href="<?=url('public/venues/index.php')?>"><span class="action-icon">□</span><span><b>Venue information</b><small>Explore facilities & capacity</small></span></a>
        <a class="user-action-card whatsapp" href="https://wa.me/60300000000?text=Hello%20Berjaya%20Hills%20Event%20Planning%20Team%2C%20I%20would%20like%20help%20with%20an%20event." target="_blank" rel="noopener"><span class="action-icon">◔</span><span><b>Chat with event planner</b><small>Get direct planning support</small></span></a>
      </div>
    </section>

    <div class="user-dashboard-main-grid-v28">
      <section class="panel user-bookings-v28">
        <div class="section-head user-bookings-head-v28">
          <div><div class="eyebrow">Your activity</div><h2>Recent bookings.</h2></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn btn-outline user-dark-outline" href="<?=url('user/drafts/index.php')?>">Drafts</a><a class="btn btn-gold" href="<?=url('user/bookings/create.php?fresh=1')?>">+ New booking</a></div>
        </div>
        <div class="table-wrap">
          <table class="table user-booking-table-v28">
            <thead><tr><th>Reference</th><th>Event</th><th>Date</th><th>Status</th><th>Progress</th><th></th></tr></thead>
            <tbody>
            <?php foreach($bookings as $b):
              $status=(string)$b['status'];
              $statusMap=['draft'=>1,'pending_review'=>2,'pending_info'=>2,'deposit_paid'=>3,'confirmed'=>4,'completed'=>5];
              $step=$statusMap[$status] ?? (in_array($status,['rejected','cancelled'],true)?2:2);
              $progressLastLabel='Live';
              // A confirmed event becomes Live while it is happening and Completed after its end time.
              if($status==='confirmed' && strtotime($b['start_at'])<=time() && strtotime($b['end_at'])>time()){
                $step=5;
                $progressLastLabel='Live';
              } elseif($status==='completed' || ($status==='confirmed' && strtotime($b['end_at'])<=time())) {
                $step=5;
                $progressLastLabel='Completed';
              }
              $displayStatus=$status;
              if($status==='confirmed' && strtotime($b['end_at'])<=time()) $displayStatus='completed';
            ?>
              <tr>
                <td><b><?=e($b['booking_ref'])?></b></td>
                <td><?=e($b['event_title'])?></td>
                <td><?=date('d M Y · H:i',strtotime($b['start_at']))?></td>
                <td><span class="badge <?=in_array($displayStatus,['confirmed','completed'],true)?'badge-green':($displayStatus==='rejected'?'badge-red':'badge-gold')?>"><?=e(ucwords(str_replace('_',' ',$displayStatus)))?></span></td>
                <td>
                  <div class="booking-progress-v28" aria-label="Booking progress: <?=e($progressLastLabel)?>">
                    <div class="progress-line"><span style="width:<?=($step-1)/4*100?>%"></span></div>
                    <div class="progress-labels"><span class="<?= $step>=1?'on':''?>">Draft</span><span class="<?= $step>=2?'on':''?>">Review</span><span class="<?= $step>=3?'on':''?>">Deposit</span><span class="<?= $step>=4?'on':''?>">Confirmed</span><span class="<?= $step>=5?'on':''?>"><?=e($progressLastLabel)?></span></div>
                  </div>
                </td>
                <td><a class="btn btn-outline btn-sm user-dark-outline" href="<?=url('user/bookings/view.php?id='.$b['id'])?>">View</a></td>
              </tr>
            <?php endforeach; if(!$bookings): ?>
              <tr><td colspan="6"><div class="user-empty-v28"><div class="empty-calendar-icon">＋</div><h3>Ready to plan your next exceptional event?</h3><p>You do not have any bookings yet. Get started by creating your first event booking.</p><a class="btn btn-gold" href="<?=url('user/bookings/create.php?fresh=1')?>">▣ &nbsp; Create a new booking →</a></div></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <aside class="user-planner-card-v28">
        <div class="eyebrow">Planning support</div><h2>Your dedicated planner.</h2><p>Our event planning team is here to assist you from enquiry through confirmation.</p>
        <div class="planner-avatar-v28">BH</div><strong>Berjaya Hills Event Planning Team</strong><small>Event planning support</small>
        <div class="planner-actions-v28"><a class="btn btn-outline user-dark-outline" href="mailto:<?=$plannerEmail?>">✉ Email</a><a class="btn btn-gold" href="tel:<?=preg_replace('/[^0-9+]/','',$plannerPhone)?>">☎ Call</a></div>
        <div class="planner-hours-v28"><b>◷ &nbsp; Office hours</b><span>Monday – Friday: 9:00 AM – 6:00 PM</span><span>Saturday – Sunday: Closed</span></div>
      </aside>
    </div>

    <section class="user-services-v28">
      <div class="section-head"><div><div class="eyebrow">Enhance your event</div><h2>Recommended services.</h2><p class="kicker">Make your event more memorable with additional services and facilities.</p></div><a class="btn btn-outline user-dark-outline" href="<?=url('public/venues/index.php')?>">View all services →</a></div>
      <div class="user-service-grid-v28">
        <article class="user-service-card-v28"><div class="service-image catering"></div><div class="service-copy"><b>♜ &nbsp; Catering Services</b><p>Explore buffet, set menu and customised catering options for your event.</p><a href="<?=url('public/contact.php')?>">Enquire more →</a></div></article>
        <article class="user-service-card-v28"><div class="service-image team"></div><div class="service-copy"><b>♧ &nbsp; Team Building Activities</b><p>Group activities at Adventure Park and the surrounding highland environment.</p><a href="<?=url('public/venues/index.php')?>">Explore venues →</a></div></article>
        <article class="user-service-card-v28"><div class="service-image stay"></div><div class="service-copy"><b>▱ &nbsp; Accommodation</b><p>Plan accommodation options for guests attending your event.</p><a href="<?=url('public/contact.php')?>">Enquire more →</a></div></article>
      </div>
    </section>
  </section>
</div>
<?php require __DIR__.'/../../includes/footer.php'; ?>
