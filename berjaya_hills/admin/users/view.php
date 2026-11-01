<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator','Management','IT');
$id=(int)($_GET['id']??0);
$s=db()->prepare('SELECT u.*,GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ", ") roles FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.id=? GROUP BY u.id');
$s->execute([$id]);
$user=$s->fetch();
if(!$user) exit('User not found');
$q=db()->prepare('SELECT * FROM vw_booking_summary WHERE user_id=? ORDER BY created_at DESC');
$q->execute([$id]);$bookings=$q->fetchAll();
$pageTitle='User Profile';require __DIR__.'/../../includes/header.php';
?>
<section class="section"><div class="container"><a class="btn btn-outline btn-sm" href="<?=url('admin/users/index.php')?>">← Back to users</a><div class="admin-top" style="margin-top:20px"><div><div class="eyebrow">Customer profile</div><h1 class="admin-title"><?=e($user['full_name'])?></h1><p class="kicker"><?=e($user['email'])?> · <?=e($user['company_name']?:'No company specified')?></p></div><span class="badge badge-green"><?=e(ucfirst($user['account_status']))?></span></div><div class="panel" style="margin-top:20px"><h2>Account details</h2><div class="review-details"><div class="review-detail"><span>Full name</span><strong><?=e($user['full_name'])?></strong></div><div class="review-detail"><span>Company</span><strong><?=e($user['company_name']?:'—')?></strong></div><div class="review-detail"><span>Email</span><strong><?=e($user['email'])?></strong></div><div class="review-detail"><span>Phone</span><strong><?=e($user['phone']?:'—')?></strong></div><div class="review-detail"><span>Role</span><strong><?=e($user['roles']?:'Registered User')?></strong></div><div class="review-detail"><span>Registered</span><strong><?=date('d M Y',strtotime($user['created_at']))?></strong></div></div></div><div class="panel" style="margin-top:20px"><div class="eyebrow">Booking history</div><h2>Previous bookings</h2><div class="table-wrap"><table class="table"><thead><tr><th>Reference</th><th>Event</th><th>Venue</th><th>Date</th><th>Status</th></tr></thead><tbody><?php if(!$bookings): ?><tr><td colspan="5" class="empty-table">No bookings found.</td></tr><?php else: foreach($bookings as $b): ?><tr><td><?=e($b['booking_ref'])?></td><td><?=e($b['event_title'])?></td><td><?=e($b['other_venue_name'] ?: ($b['venue_names']?:'—'))?></td><td><?=date('d M Y H:i',strtotime($b['start_at']))?></td><td><?=e(ucwords(str_replace('_',' ',$b['status'])))?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div></section>
<?php require __DIR__.'/../../includes/footer.php'; ?>
