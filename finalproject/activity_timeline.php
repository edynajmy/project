<?php
// activity_timeline.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
checkAccess(['Reporter','Recorder','Admin']);

$events=[];

$lost=$pdo->query("SELECT l.created_at, l.reference_code AS code, l.item_title AS title, u.full_name AS actor FROM lost_items l LEFT JOIN users u ON l.reporter_id=u.user_id ORDER BY l.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
foreach($lost as $r){$events[]=['time'=>$r['created_at'],'type'=>'Lost Report','title'=>$r['title'],'code'=>$r['code'],'actor'=>$r['actor']??'Reporter','icon'=>'fa-magnifying-glass','color'=>'warning'];}

$found=$pdo->query("SELECT f.created_at, f.item_code AS code, f.title, u.full_name AS actor FROM found_items f LEFT JOIN users u ON f.reporter_id=u.user_id ORDER BY f.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
foreach($found as $r){$events[]=['time'=>$r['created_at'],'type'=>'Found Report','title'=>$r['title'],'code'=>$r['code'],'actor'=>$r['actor']??'Reporter','icon'=>'fa-hand-holding-heart','color'=>'primary'];}

$matches=$pdo->query("SELECT im.match_date AS time, f.item_code AS code, f.title, u.full_name AS actor FROM item_matches im JOIN found_items f ON im.found_item_id=f.item_id LEFT JOIN users u ON im.matched_by_recorder_id=u.user_id ORDER BY im.match_date DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
foreach($matches as $r){$events[]=['time'=>$r['time'],'type'=>'Item Match','title'=>$r['title'],'code'=>$r['code'],'actor'=>$r['actor']??'Recorder','icon'=>'fa-link','color'=>'info'];}

$claims=$pdo->query("SELECT cl.date_claimed, cl.date_processed, cl.checkout_date, cl.status, f.item_code, f.title, u.full_name AS claimant, staff.full_name AS staff_name FROM claims cl JOIN found_items f ON cl.found_item_id=f.item_id LEFT JOIN users u ON cl.claimant_id=u.user_id LEFT JOIN users staff ON cl.handled_by_recorder_id=staff.user_id ORDER BY cl.date_claimed DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
foreach($claims as $r){
  $events[]=['time'=>$r['date_claimed'],'type'=>'Claim Submitted','title'=>$r['title'],'code'=>$r['item_code'],'actor'=>$r['claimant']??'Reporter','icon'=>'fa-file-signature','color'=>'success'];
  if(!empty($r['date_processed'])) $events[]=['time'=>$r['date_processed'],'type'=>'Claim '.$r['status'],'title'=>$r['title'],'code'=>$r['item_code'],'actor'=>$r['staff_name']??'Recorder','icon'=>$r['status']==='Approved'?'fa-circle-check':'fa-circle-xmark','color'=>$r['status']==='Approved'?'success':'danger'];
  if(!empty($r['checkout_date'])) $events[]=['time'=>$r['checkout_date'],'type'=>'Item Collected','title'=>$r['title'],'code'=>$r['item_code'],'actor'=>$r['claimant']??'Reporter','icon'=>'fa-box-open','color'=>'success'];
}

usort($events,fn($a,$b)=>strtotime($b['time'])<=>strtotime($a['time']));
$events=array_slice($events,0,100);

// Optional filters (read-only, derived from existing records)
$filter = $_GET['type'] ?? 'all';
$allowedFilters = ['all','Lost Report','Found Report','Item Match','Claim Submitted','Claim Approved','Claim Rejected','Item Collected'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';
if ($filter !== 'all') {
    $events = array_values(array_filter($events, fn($e) => $e['type'] === $filter));
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Activity Timeline - Resort L&amp;F</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet"><style>
body{background:#f4f7fb}.page-wrap{max-width:1000px}.timeline{position:relative}.timeline:before{content:"";position:absolute;left:21px;top:10px;bottom:10px;width:2px;background:#dce8f5}.event{position:relative;padding-left:62px;margin-bottom:22px}.event-icon{position:absolute;left:0;top:0;width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:#fff;box-shadow:0 6px 18px rgba(18,60,105,.1);border:1px solid #e8eef5}.event-card{border:0;border-radius:16px;box-shadow:0 7px 24px rgba(18,60,105,.06)}.filter-card{border:0;border-radius:16px;box-shadow:0 7px 24px rgba(18,60,105,.06)}.day-label{font-size:.78rem;font-weight:800;letter-spacing:.08em;color:#6c757d;text-transform:uppercase;margin:28px 0 12px 62px}.navbar-brand{letter-spacing:.2px}</style></head><body>
<nav class="navbar navbar-dark bg-dark sticky-top"><div class="container-fluid px-4"><a class="navbar-brand fw-bold" href="<?= $_SESSION['user_role']==='Admin'?'admin_dashboard.php':($_SESSION['user_role']==='Reporter'?'reporter_dashboard.php':'recorder_dashboard.php'); ?>"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Resort L&amp;F</a><div class="d-flex align-items-center gap-2"><a href="<?= $_SESSION['user_role']==='Admin'?'admin_dashboard.php':($_SESSION['user_role']==='Reporter'?'reporter_dashboard.php':'recorder_dashboard.php'); ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard</a></div></div></nav>
<div class="container page-wrap py-4 py-lg-5">
<div class="mb-4"><div class="text-primary fw-bold small text-uppercase">System Traceability</div><h2 class="fw-bold mb-1"><i class="fa-solid fa-clock-rotate-left me-2"></i>Activity Timeline</h2><p class="text-muted mb-0">A read-only timeline derived from existing Lost &amp; Found records.</p></div>
<div class="card filter-card p-3 mb-4"><form method="get" class="row g-2 align-items-end"><div class="col-md-8"><label class="form-label small fw-semibold">Activity Type</label><select name="type" class="form-select"><option value="all" <?= $filter==='all'?'selected':''; ?>>All Activities</option><option value="Lost Report" <?= $filter==='Lost Report'?'selected':''; ?>>Lost Report</option><option value="Found Report" <?= $filter==='Found Report'?'selected':''; ?>>Found Report</option><option value="Item Match" <?= $filter==='Item Match'?'selected':''; ?>>Item Match</option><option value="Claim Submitted" <?= $filter==='Claim Submitted'?'selected':''; ?>>Claim Submitted</option><option value="Claim Approved" <?= $filter==='Claim Approved'?'selected':''; ?>>Claim Approved</option><option value="Claim Rejected" <?= $filter==='Claim Rejected'?'selected':''; ?>>Claim Rejected</option><option value="Item Collected" <?= $filter==='Item Collected'?'selected':''; ?>>Item Collected</option></select></div><div class="col-md-4 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter me-1"></i>Filter</button><a href="activity_timeline.php" class="btn btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i></a></div></form></div>
<div class="timeline"><?php if(empty($events)): ?><div class="card event-card p-5 text-center text-muted"><i class="fa-regular fa-clock fs-2 mb-3"></i><div class="fw-semibold">No activity found</div><div class="small">No records match the selected activity type.</div></div><?php else: $lastDay=''; foreach($events as $e): $day=date('Y-m-d',strtotime($e['time'])); if($day!==$lastDay): $lastDay=$day; ?><div class="day-label"><?= date('d M Y',strtotime($e['time'])); ?></div><?php endif; ?><div class="event"><div class="event-icon text-<?= $e['color']; ?>"><i class="fa-solid <?= $e['icon']; ?>"></i></div><div class="card event-card p-3"><div class="d-flex flex-column flex-md-row justify-content-between gap-2"><div><span class="badge text-bg-<?= $e['color']; ?> mb-2"><?= htmlspecialchars($e['type']); ?></span><h6 class="fw-bold mb-1"><?= htmlspecialchars($e['title']); ?></h6><div class="small text-muted font-monospace"><?= htmlspecialchars($e['code']); ?></div></div><div class="text-md-end"><div class="small fw-semibold"><?= date('d M Y, h:i A',strtotime($e['time'])); ?></div><div class="small text-muted">By <?= htmlspecialchars($e['actor']); ?></div></div></div></div></div><?php endforeach; endif; ?></div>
</div></body></html>