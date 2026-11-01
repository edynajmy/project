<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u = require_login();
$pageTitle = 'Calendar';
$pdo = db();

$month = isset($_GET['month']) ? max(1,min(12,(int)$_GET['month'])) : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$start = sprintf('%04d-%02d-01 00:00:00',$year,$month);
$end = date('Y-m-t 23:59:59',strtotime($start));

$sql = "SELECT e.id AS event_id,e.title,e.start_at,e.end_at,e.status AS event_status,e.expected_pax,
               b.id AS booking_id,b.booking_ref,b.status AS booking_status,
               c.name AS category_name,
               GROUP_CONCAT(DISTINCT v.name ORDER BY v.name SEPARATOR ', ') AS venue_names
        FROM events e JOIN bookings b ON b.event_id=e.id
        LEFT JOIN event_categories c ON c.id=e.category_id
        LEFT JOIN event_venues ev ON ev.event_id=e.id LEFT JOIN venues v ON v.id=ev.venue_id
        WHERE e.owner_user_id=? AND e.start_at <= ? AND e.end_at >= ?
          AND b.status NOT IN ('draft','rejected','cancelled')
        GROUP BY e.id,b.id ORDER BY e.start_at ASC";
$stmt=$pdo->prepare($sql); $stmt->execute([$u['id'],$end,$start]); $events=$stmt->fetchAll();
$todayDate=date('Y-m-d');
$pastEvents=array_values(array_filter($events,static fn($event)=>date('Y-m-d',strtotime($event['end_at']))<$todayDate));
$upcomingEvents=array_values(array_filter($events,static fn($event)=>date('Y-m-d',strtotime($event['end_at']))>=$todayDate));
$byDay=[]; foreach($events as $event){$d=(int)date('j',strtotime($event['start_at']));$byDay[$d][]=$event;}

$days=(int)date('t',strtotime($start));
$offset=(int)date('w',strtotime($start));
$prev=strtotime('-1 month',strtotime($start));
$next=strtotime('+1 month',strtotime($start));

$monthUrl=function($ts){return url('user/calendar/index.php?month='.date('n',$ts).'&year='.date('Y',$ts));};

require __DIR__.'/../../includes/header.php';
?>

<div class="user-shell">
    <aside class="sidebar">
        <?php require __DIR__.'/../sidebar.php'; ?>
    </aside>

    <section class="admin-content user-content">
        <section class="page-hero">
            <div class="container">
                <div class="eyebrow">Dashboard &nbsp;›&nbsp; Calendar</div>
                <h1 class="page-title">Event Calendar</h1>
                <p>View your submitted bookings, pending approvals and confirmed event dates. Drag an eligible booking to request a new date.</p>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="panel">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:20px;flex-wrap:wrap">
                        <div>
                            <div class="eyebrow">Schedule</div>
                            <h2 style="margin:7px 0 0"><?= e(date('F Y',strtotime($start))) ?></h2>
                        </div>
                        <div style="display:flex;gap:8px">
                            <a class="btn btn-outline" style="color:var(--forest);border-color:#bbb" href="<?= e($monthUrl($prev)) ?>">←</a>
                            <a class="btn btn-outline" style="color:var(--forest);border-color:#bbb" href="<?= e($monthUrl(strtotime(date('Y-m-01')))) ?>">Today</a>
                            <a class="btn btn-outline" style="color:var(--forest);border-color:#bbb" href="<?= e($monthUrl($next)) ?>">→</a>
                            <a class="btn btn-gold" href="<?= url('user/bookings/create.php?fresh=1') ?>">+ New booking</a>
                        </div>
                    </div>

                    <div class="user-calendar-grid">
                        <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day): ?>
                            <div class="user-calendar-head"><?= $day ?></div>
                        <?php endforeach; ?>

                        <?php for($i=0;$i<$offset;$i++): ?>
                            <div class="user-calendar-cell empty"></div>
                        <?php endfor; ?>

                        <?php for($d=1;$d<=$days;$d++):
                            $date=sprintf('%04d-%02d-%02d',$year,$month,$d);
                            $isToday=$date===date('Y-m-d');
                        ?>
                            <div class="user-calendar-cell <?= $isToday?'today':'' ?>">
                                <div class="user-calendar-day"><?= $d ?></div>
                                <?php foreach(array_slice($byDay[$d]??[],0,3) as $event): ?>
                                    <div class="user-calendar-event booking-<?=e($event['booking_status'])?>" <?=in_array($event['booking_status'],['pending_review','pending_info','confirmed'],true)?'draggable="true" data-booking-id="'.(int)$event['booking_id'].'" data-title="'.e($event['title']).'"':''?>>
                                        <strong><?= e($event['title']) ?></strong>
                                        <span><?= e(date('h:i A',strtotime($event['start_at']))) ?></span>
                                        <small><?= e($event['venue_names'] ?: 'Venue TBA') ?></small><small class="calendar-status"><?=e(ucwords(str_replace('_',' ',$event['booking_status'])))?><?=in_array($event['booking_status'],['pending_review','pending_info','confirmed'],true)?' · drag to request date':''?></small>
                                    </div>
                                <?php endforeach; ?>
                                <?php if(count($byDay[$d]??[])>3): ?>
                                    <div class="user-calendar-more">+<?= count($byDay[$d])-3 ?> more</div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="calendar-legend"><span><i class="pending"></i> Pending review</span><span><i class="confirmed"></i> Confirmed</span><span><i class="past"></i> Past event</span><small>Drag a booking to another date to request a change. Management approval is required.</small></div>
                <div class="panel" style="margin-top:18px">
                    <div class="eyebrow">Event history · <?= count($pastEvents) ?> past · <?= count($upcomingEvents) ?> upcoming/ongoing</div>
                    <h2 style="margin:7px 0 18px">Events in <?= e(date('F Y',strtotime($start))) ?>.</h2>
                    <?php if(!$events): ?>
                        <div class="empty-state"><h3>No events recorded for this month.</h3><p>Choose another month to browse the event schedule and archive.</p></div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead><tr><th>Event</th><th>Category</th><th>Venue</th><th>Date</th><th>Pax</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach($events as $event): $isPast = date('Y-m-d', strtotime($event['end_at'])) < $todayDate; ?>
                                    <tr class="<?= $isPast ? 'calendar-row-past' : 'calendar-row-current' ?>">
                                        <td><b><?= e($event['title']) ?></b></td>
                                        <td><?= e($event['category_name'] ?: 'Event') ?></td>
                                        <td><?= e($event['venue_names'] ?: 'Venue TBA') ?></td>
                                        <td><?= e(date('d M Y, h:i A',strtotime($event['start_at']))) ?></td>
                                        <td><?= number_format((int)$event['expected_pax']) ?></td>
                                        <td><span class="badge <?= $isPast ? 'badge-blue' : ($event['booking_status']==='confirmed'?'badge-green':'badge-gold') ?>"><?= $isPast ? 'Past event' : (date('Y-m-d',strtotime($event['start_at'])) === $todayDate ? 'Ongoing today' : ucwords(str_replace('_',' ',$event['booking_status']))) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </section>
</div>

<style>
.user-calendar-cell.drop-target{background:#f5f0e3;outline:2px dashed #c6a66b;outline-offset:-4px}.user-calendar-event{border-left:3px solid #9babb0;background:#edf1f0;border-radius:6px;padding:8px;margin:7px 0;overflow-wrap:anywhere}.user-calendar-event[draggable=true]{cursor:grab}.user-calendar-event.dragging{opacity:.4}.user-calendar-event.booking-pending_review,.user-calendar-event.booking-pending_info{border-color:#c49b4e;background:#fbf2df}.user-calendar-event.booking-confirmed{border-color:#36836c;background:#e8f3ed}.user-calendar-event strong,.user-calendar-event span,.user-calendar-event small{display:block}.user-calendar-event strong{font-size:12px;line-height:1.35}.user-calendar-event span,.user-calendar-event small{font-size:10px;color:#66756f;margin-top:3px}.calendar-status{font-weight:700}.calendar-legend{display:flex;gap:16px;flex-wrap:wrap;align-items:center;padding:14px 4px;color:#60716b;font-size:12px}.calendar-legend span{display:flex;gap:6px;align-items:center}.calendar-legend i{width:9px;height:9px;border-radius:50%;display:inline-block;background:#c49b4e}.calendar-legend i.confirmed{background:#36836c}.calendar-legend i.past{background:#9babb0}
</style>
<script>(()=>{const csrf=<?=json_encode(csrf_token())?>;let dragged=null;document.querySelectorAll('.user-calendar-event[draggable=true]').forEach(el=>{el.addEventListener('dragstart',e=>{dragged=el;el.classList.add('dragging');e.dataTransfer.setData('text/plain',el.dataset.bookingId);});el.addEventListener('dragend',()=>{el.classList.remove('dragging');document.querySelectorAll('.drop-target').forEach(x=>x.classList.remove('drop-target'));dragged=null;});});document.querySelectorAll('.user-calendar-cell:not(.empty)').forEach(cell=>{cell.addEventListener('dragover',e=>{if(dragged){e.preventDefault();cell.classList.add('drop-target');}});cell.addEventListener('dragleave',()=>cell.classList.remove('drop-target'));cell.addEventListener('drop',async e=>{if(!dragged)return;e.preventDefault();cell.classList.remove('drop-target');const day=cell.querySelector('.user-calendar-day')?.textContent.trim();if(!day)return;const target=<?=json_encode(sprintf('%04d-%02d-',$year,$month))?>+String(day).padStart(2,'0');if(!confirm('Request to move “'+dragged.dataset.title+'” to '+target+'? It changes only after Management approval.'))return;try{const r=await fetch(<?=json_encode(url('user/bookings/calendar_change.php'))?>,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf,booking_id:dragged.dataset.bookingId,target_date:target})});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.message||'Request failed');alert(d.message);location.reload();}catch(err){alert(err.message);}});});})();</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
