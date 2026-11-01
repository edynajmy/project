<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator','Management','Event');
$period=$_GET['period']??'monthly';
$allowed=['weekly'=>7,'monthly'=>30,'yearly'=>365];
$days=$allowed[$period]??30;
$from=date('Y-m-d H:i:s',strtotime("-$days days"));
$pdo=db();
$q=$pdo->prepare('SELECT COUNT(*) FROM events WHERE created_at>=?');$q->execute([$from]);$events=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT COUNT(*) FROM bookings WHERE created_at>=?');$q->execute([$from]);$bookings=(int)$q->fetchColumn();
$q=$pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status='confirmed' AND created_at>=?");$q->execute([$from]);$confirmed=(int)$q->fetchColumn();
$q=$pdo->prepare('SELECT COUNT(DISTINCT ev.venue_id) FROM event_venues ev JOIN events e ON e.id=ev.event_id WHERE e.created_at>=?');$q->execute([$from]);$venues=(int)$q->fetchColumn();
$cat=$pdo->prepare("SELECT COALESCE(c.name,'Uncategorised') name,COUNT(*) total FROM events e LEFT JOIN event_categories c ON c.id=e.category_id WHERE e.created_at>=? GROUP BY c.id,c.name ORDER BY total DESC");$cat->execute([$from]);$cats=$cat->fetchAll();
$eventRows=$pdo->query("SELECT e.id,e.title,e.start_at,e.end_at,e.expected_pax,e.status,e.created_at,COALESCE(c.name,'Uncategorised') category,COALESCE(u.full_name,'—') organizer,COALESCE(GROUP_CONCAT(DISTINCT v.name ORDER BY v.name SEPARATOR ', '),'—') venues FROM events e LEFT JOIN event_categories c ON c.id=e.category_id LEFT JOIN users u ON u.id=e.owner_user_id LEFT JOIN event_venues ev ON ev.event_id=e.id LEFT JOIN venues v ON v.id=ev.venue_id GROUP BY e.id,e.title,e.start_at,e.end_at,e.expected_pax,e.status,e.created_at,c.name,u.full_name ORDER BY e.start_at DESC,e.id DESC")->fetchAll();
$pageTitle='Reports';require __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell report-shell">
  <aside class="sidebar report-sidebar"><?php require __DIR__.'/../sidebar.php';?></aside>
  <section class="admin-content report-content">
    <div class="admin-top report-controls"><div><div class="eyebrow">Operational reporting</div><h1 class="admin-title">Event Reports</h1><p class="kicker">Administrative record of events, bookings and venue usage.</p></div><div class="report-actions"><a class="btn btn-outline btn-sm" style="color:var(--forest);border-color:#bbb" href="?period=weekly">Weekly</a><a class="btn btn-outline btn-sm" style="color:var(--forest);border-color:#bbb" href="?period=monthly">Monthly</a><a class="btn btn-outline btn-sm" style="color:var(--forest);border-color:#bbb" href="?period=yearly">Yearly</a><button class="btn btn-primary" type="button" onclick="window.print()">Print report</button></div></div>
    <div class="report-print-area">
      <div class="print-heading"><div class="print-brand">BERJAYA HILLS · RESORT EVENTS & ACTIVITIES</div><h1>Event Records Report</h1><p>Generated <?=date('d M Y, h:i A')?> · Complete event register</p></div>
      <div class="stats report-stats"><div class="stat"><small>Events created in selected period</small><strong><?=$events?></strong></div><div class="stat"><small>Bookings in selected period</small><strong><?=$bookings?></strong></div><div class="stat"><small>Confirmed bookings</small><strong><?=$confirmed?></strong></div><div class="stat"><small>Venues used in period</small><strong><?=$venues?></strong></div></div>
      <div class="panel report-panel"><div class="section-head"><div><div class="eyebrow">Summary</div><h2>Event category distribution</h2></div><span class="report-period">Period: <?=e(ucfirst($period))?></span></div><div class="table-wrap"><table class="table"><thead><tr><th>Category</th><th>Event count</th></tr></thead><tbody><?php if(!$cats):?><tr><td colspan="2">No event category records for this period.</td></tr><?php else:foreach($cats as $c):?><tr><td><?=e($c['name'])?></td><td><?=number_format($c['total'])?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
      <div class="panel report-panel event-register"><div class="section-head"><div><div class="eyebrow">Full administrative register</div><h2>All recorded events</h2><p class="kicker"><?=count($eventRows)?> event record(s), including past, upcoming and other statuses.</p></div></div><div class="table-wrap"><table class="table event-report-table"><thead><tr><th>#</th><th>Event</th><th>Category</th><th>Venue</th><th>Organizer</th><th>Start</th><th>End</th><th>Pax</th><th>Status</th></tr></thead><tbody><?php if(!$eventRows):?><tr><td colspan="9">No event records available.</td></tr><?php else:foreach($eventRows as $i=>$ev):?><tr><td><?=($i+1)?></td><td><strong><?=e($ev['title'])?></strong></td><td><?=e($ev['category'])?></td><td><?=e($ev['venues'])?></td><td><?=e($ev['organizer'])?></td><td><?=date('d M Y, h:i A',strtotime($ev['start_at']))?></td><td><?=date('d M Y, h:i A',strtotime($ev['end_at']))?></td><td><?=number_format((int)$ev['expected_pax'])?></td><td><?=e(ucwords(str_replace('_',' ',$ev['status'])))?></td></tr><?php endforeach;endif;?></tbody></table></div></div>
      <div class="print-footer">Berjaya Hills Resort · Internal administrative record · <?=date('d M Y')?></div>
    </div>
  </section>
</div>
<style>
.print-heading{display:none}.report-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.report-panel{margin-top:20px}.report-panel h2{font-size:25px;margin:5px 0}.report-period{font-size:12px;color:var(--muted)}.event-report-table{font-size:13px}.event-report-table th,.event-report-table td{vertical-align:top}
@media print{
 @page{size:A4 landscape;margin:12mm}
 html,body{background:#fff!important;color:#17251f!important;font-size:9pt!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .site-header,.report-sidebar,.report-controls,.mobile-menu,.toast,.report-actions,footer,.site-footer{display:none!important}
 body.admin-body main{margin:0!important;padding:0!important}
 .admin-shell.report-shell{display:block!important;min-height:0!important;margin:0!important;padding:0!important;background:#fff!important}
 .report-content.admin-content{display:block!important;width:100%!important;max-width:none!important;margin:0!important;padding:0!important;overflow:visible!important}
 .report-print-area{display:block!important;width:100%!important}
 .print-heading{display:block!important;margin:0 0 12px!important;border-bottom:2px solid #183b30;padding-bottom:8px}
 .print-brand{font-size:9pt;letter-spacing:2px;font-weight:700;color:#183b30}
 .print-heading h1{font-size:22pt;margin:5px 0;color:#183b30}
 .print-heading p{font-size:9pt;color:#66736d;margin:0}
 .report-stats{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:8px!important;margin:10px 0!important}
 .report-stats .stat{border:1px solid #d9ded9!important;padding:8px!important;box-shadow:none!important;break-inside:avoid}
 .report-stats .stat strong{font-size:17pt!important}
 .report-panel{margin:10px 0!important;padding:10px!important;border:1px solid #d9ded9!important;box-shadow:none!important;break-inside:auto}
 .report-panel h2{font-size:14pt!important}
 .table-wrap{overflow:visible!important;width:100%!important}
 .table{width:100%!important;border-collapse:collapse!important;font-size:7.3pt!important;table-layout:auto!important}
 .table th,.table td{padding:5px 4px!important;border:1px solid #dfe4df!important;color:#17251f!important;white-space:normal!important;overflow-wrap:anywhere!important}
 .table thead{display:table-header-group!important}.table tr{break-inside:avoid!important}
 .event-register{break-before:page}
 .print-footer{display:block!important;margin-top:12px;padding-top:7px;border-top:1px solid #ccd4ce;font-size:8pt;color:#64716a}
}
</style>
<?php require __DIR__.'/../../includes/footer.php'; ?>
