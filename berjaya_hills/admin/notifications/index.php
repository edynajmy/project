<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator','Management','IT');
$pageTitle='Notification Management';

$pdo = db();
$rows = $pdo->query("SELECT n.*, u.full_name FROM notifications n JOIN users u ON u.id=n.user_id ORDER BY n.created_at DESC LIMIT 100")->fetchAll();

$totalNotifications = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$unreadNotifications = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0")->fetchColumn();
$readNotifications = max(0, $totalNotifications - $unreadNotifications);
$todayNotifications = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()")->fetchColumn();

require __DIR__.'/../../includes/header.php';
?>
<style>
.notification-page{max-width:1280px;margin:0 auto;padding-bottom:56px}
.notification-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin:34px 0 24px}
.notification-stat{background:#fff;border:1px solid #e4dfd4;border-radius:14px;padding:22px 24px;box-shadow:0 12px 32px rgba(19,42,34,.045)}
.notification-stat .label{font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:#7c8580;font-weight:700}
.notification-stat .value{font-family:Georgia,serif;font-size:38px;color:#092b22;margin-top:8px;line-height:1}
.notification-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:24px;align-items:start}
.notification-panel{background:#fff;border:1px solid #e4dfd4;border-radius:14px;box-shadow:0 12px 32px rgba(19,42,34,.045);overflow:hidden}
.notification-panel-head{padding:30px 30px 22px;border-bottom:1px solid #ebe7df}
.notification-panel-head h2{margin:5px 0 5px;font-size:32px;color:#082c23}
.notification-toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.notification-toolbar input,.notification-toolbar select{height:42px;border:1px solid #d8d4cb;border-radius:8px;padding:0 12px;background:#fff;color:#18352d;min-width:145px}
.notification-toolbar input{flex:1;min-width:220px}
.n-btn{height:42px;border-radius:8px;border:1px solid #cfc8ba;background:#fff;padding:0 15px;font-weight:700;color:#17342c;cursor:pointer}
.n-btn.primary{background:#cda966;border-color:#cda966;color:#102a22}
.n-btn:hover{transform:translateY(-1px)}
.notification-list{padding:0 30px}
.notification-row{display:grid;grid-template-columns:42px minmax(0,1fr) auto;gap:14px;align-items:center;padding:19px 0;border-bottom:1px solid #eee9e0}
.notification-row:last-child{border-bottom:0}
.n-icon{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#edf3ef;color:#174d3f;font-size:18px}
.notification-row.unread .n-icon{background:#f5ead3;color:#9a702b}
.n-title{font-weight:700;color:#14342b;margin-bottom:4px}
.n-meta{font-size:13px;color:#7a817d}
.n-status{font-size:12px;font-weight:700;border-radius:999px;padding:6px 10px;white-space:nowrap;background:#edf3ef;color:#23604e}
.notification-row.unread .n-status{background:#f6ead2;color:#8b682e}
.empty-notifications{padding:34px 0;color:#78817c}
.template-list{padding:10px 30px 26px}
.template-item{padding:18px 0;border-bottom:1px solid #eee9e0;display:flex;justify-content:space-between;gap:16px;align-items:center}
.template-item:last-child{border-bottom:0}
.template-name{font-weight:700;color:#15372e}
.template-channel{font-size:12px;color:#8a8f8b;margin-top:5px;text-transform:uppercase;letter-spacing:.08em}
.template-action{border:0;background:transparent;color:#9a702b;font-weight:700;cursor:pointer}
.settings-panel{margin-top:24px;padding:28px 30px}
.settings-panel h2{font-size:28px;margin:5px 0 4px;color:#082c23}
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px}
.setting-row{border:1px solid #e6e1d8;border-radius:10px;padding:15px 16px;display:flex;justify-content:space-between;align-items:center;gap:15px}
.setting-row strong{font-size:14px;color:#18352d}
.setting-row span{font-size:12px;color:#858b87;display:block;margin-top:3px}
.switch{position:relative;width:44px;height:24px;flex:0 0 auto}.switch input{opacity:0;width:0;height:0}.slider{position:absolute;inset:0;background:#d8d7d2;border-radius:24px;cursor:pointer;transition:.2s}.slider:before{content:'';position:absolute;width:18px;height:18px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}.switch input:checked+.slider{background:#1f5c4b}.switch input:checked+.slider:before{transform:translateX(20px)}
@media(max-width:1000px){.notification-stats{grid-template-columns:repeat(2,1fr)}.notification-layout{grid-template-columns:1fr}.settings-grid{grid-template-columns:1fr}}
@media(max-width:600px){.notification-stats{grid-template-columns:1fr}.notification-panel-head,.notification-list,.settings-panel{padding-left:18px;padding-right:18px}.notification-row{grid-template-columns:36px minmax(0,1fr);}.n-status{grid-column:2;justify-self:start}.n-icon{width:36px;height:36px}.notification-toolbar input{min-width:100%}}
</style>
<div class="admin-shell">
  <aside class="sidebar"><?php require __DIR__.'/../sidebar.php';?></aside>
  <section class="admin-content">
    <div class="notification-page">
      <div class="admin-top">
        <div>
          <div class="eyebrow">Communications</div>
          <h1 class="admin-title">Notifications</h1>
          <p class="kicker">Review alerts and control notification preferences from one place.</p>
        </div>
      </div>

      <div class="notification-stats">
        <div class="notification-stat"><div class="label">Total notifications</div><div class="value"><?= $totalNotifications ?></div></div>
        <div class="notification-stat"><div class="label">Unread</div><div class="value"><?= $unreadNotifications ?></div></div>
        <div class="notification-stat"><div class="label">Read</div><div class="value"><?= $readNotifications ?></div></div>
        <div class="notification-stat"><div class="label">Today</div><div class="value"><?= $todayNotifications ?></div></div>
      </div>

      <div class="notification-layout">
        <section class="notification-panel">
          <div class="notification-panel-head">
            <div class="eyebrow">Inbox</div>
            <h2>Recent alerts</h2>
            <p class="kicker">Keep track of booking, account and system activity.</p>
            <div class="notification-toolbar">
              <input id="notificationSearch" type="search" placeholder="Search notifications...">
              <select id="notificationType"><option value="all">All types</option><option value="booking">Booking</option><option value="account">Account</option><option value="change">Change request</option><option value="system">System</option></select>
              <select id="notificationStatus"><option value="all">All status</option><option value="unread">Unread</option><option value="read">Read</option></select>
              <button class="n-btn primary" type="button" id="markAllRead">Mark all as read</button>
            </div>
          </div>
          <div class="notification-list" id="notificationList">
            <?php if (!$rows): ?>
              <div class="empty-notifications">No notifications have been recorded yet.</div>
            <?php else: foreach($rows as $r): $type=strtolower((string)$r['type']); $isUnread=!(int)$r['is_read']; ?>
              <div class="notification-row <?= $isUnread?'unread':'' ?>" data-search="<?=e(strtolower($r['full_name'].' '.$r['title'].' '.$r['type']))?>" data-type="<?=e($type)?>" data-status="<?=$isUnread?'unread':'read'?>">
                <div class="n-icon"><?= $type==='booking'?'▣':($type==='account'?'♙':'●') ?></div>
                <div><div class="n-title"><?=e($r['title'])?></div><div class="n-meta"><?=e($r['full_name'])?> · <?=e(date('d M Y, H:i', strtotime($r['created_at'])))?></div></div>
                <div class="n-status"><?= $isUnread?'Unread':'Read' ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </section>

      </div>

      <section class="notification-panel settings-panel">
        <div class="eyebrow">Preferences</div>
        <h2>Notification settings</h2>
        <p class="kicker">Choose which operational alerts are enabled for the management workspace.</p>
        <div class="settings-grid">
          <?php foreach(['Booking alerts','Account alerts','Change requests','System alerts'] as $i=>$label): ?>
            <div class="setting-row"><div><strong><?=e($label)?></strong><span>Show alerts in the management workspace</span></div><label class="switch"><input type="checkbox" class="notification-setting" data-setting="<?=e($label)?>" checked><span class="slider"></span></label></div>
          <?php endforeach; ?>
        </div>
      </section>
    </div>
  </section>
</div>
<script>
(function(){
  const search=document.getElementById('notificationSearch'), type=document.getElementById('notificationType'), status=document.getElementById('notificationStatus');
  function filter(){const q=(search.value||'').toLowerCase().trim();document.querySelectorAll('#notificationList .notification-row').forEach(row=>{const okQ=!q||row.dataset.search.includes(q);const okT=type.value==='all'||row.dataset.type===type.value;const okS=status.value==='all'||row.dataset.status===status.value;row.style.display=okQ&&okT&&okS?'grid':'none';});}
  [search,type,status].forEach(el=>el&&el.addEventListener('input',filter));
  document.getElementById('markAllRead')?.addEventListener('click',()=>{document.querySelectorAll('#notificationList .notification-row.unread').forEach(row=>{row.classList.remove('unread');row.dataset.status='read';const badge=row.querySelector('.n-status');if(badge)badge.textContent='Read';});filter();});
  document.querySelectorAll('.notification-setting').forEach(input=>{const key='bh-notification-'+input.dataset.setting;const saved=localStorage.getItem(key);if(saved!==null)input.checked=saved==='1';input.addEventListener('change',()=>localStorage.setItem(key,input.checked?'1':'0'));});
})();
</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
