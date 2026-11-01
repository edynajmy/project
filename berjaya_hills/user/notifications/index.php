<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u = require_login();
$pageTitle = 'Notifications';
$pdo = db();

$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all','unread','booking','account','event','system'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';

$where = ['user_id = ?'];
$params = [$u['id']];

if ($filter === 'unread') $where[] = 'is_read = 0';
if ($filter !== 'all' && $filter !== 'unread') {
    $where[] = 'LOWER(type) = ?';
    $params[] = $filter;
}

$sql = 'SELECT * FROM notifications WHERE '.implode(' AND ', $where).' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$countStmt = $pdo->prepare('SELECT
    COUNT(*) AS all_count,
    SUM(CASE WHEN is_read=0 THEN 1 ELSE 0 END) AS unread_count,
    SUM(CASE WHEN LOWER(type)=\'booking\' THEN 1 ELSE 0 END) AS booking_count,
    SUM(CASE WHEN LOWER(type)=\'account\' THEN 1 ELSE 0 END) AS account_count,
    SUM(CASE WHEN LOWER(type)=\'event\' THEN 1 ELSE 0 END) AS event_count,
    SUM(CASE WHEN LOWER(type)=\'system\' THEN 1 ELSE 0 END) AS system_count
    FROM notifications WHERE user_id=?');
$countStmt->execute([$u['id']]);
$counts = $countStmt->fetch() ?: [];
$userUnreadNotifications = (int)($counts['unread_count'] ?? 0);

require __DIR__.'/../../includes/header.php';
?>

<div class="user-shell">
    <aside class="sidebar">
        <?php require __DIR__.'/../sidebar.php'; ?>
    </aside>

    <section class="admin-content user-content">
        <section class="page-hero">
            <div class="container">
                <div class="eyebrow">Dashboard &nbsp;›&nbsp; Notifications</div>
                <h1 class="page-title">Notifications</h1>
                <p>Stay updated with the latest information about your bookings, events and account.</p>
            </div>
        </section>

        <section class="section">
            <div class="container">

                <div class="notification-stats">
                    <div class="notification-stat"><small>Unread</small><strong><?= $userUnreadNotifications ?></strong></div>
                    <div class="notification-stat"><small>All Notifications</small><strong><?= (int)($counts['all_count'] ?? 0) ?></strong></div>
                    <div class="notification-stat"><small>Booking Updates</small><strong><?= (int)($counts['booking_count'] ?? 0) ?></strong></div>
                    <div class="notification-stat"><small>Account Updates</small><strong><?= (int)($counts['account_count'] ?? 0) ?></strong></div>
                    <div class="notification-stat"><small>System / Event</small><strong><?= (int)($counts['system_count'] ?? 0) + (int)($counts['event_count'] ?? 0) ?></strong></div>
                </div>

                <div class="notification-toolbar">
                    <div class="notification-tabs">
                        <?php
                        $tabNames = [
                            'all' => 'All',
                            'unread' => 'Unread',
                            'booking' => 'Booking',
                            'account' => 'Account',
                            'event' => 'Event',
                            'system' => 'System'
                        ];
                        foreach ($tabNames as $key => $label):
                        ?>
                            <a class="<?= $filter === $key ? 'active' : '' ?>" href="<?= url('user/notifications/index.php?filter='.urlencode($key)) ?>">
                                <?= e($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <form class="notification-search" method="get">
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                    <input type="text" name="q" placeholder="Search notifications..." value="<?= e($_GET['q'] ?? '') ?>">
                    <select name="date">
                        <option value="">All Dates</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                    <button class="btn btn-primary" type="submit">Search</button>
                </form>

                <div class="notification-grid">
                    <div class="notification-list-card">
                        <?php if (!$rows): ?>
                            <div class="empty-state">
                                <h3>You're all caught up.</h3>
                                <p>No notifications match the current filter.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($rows as $n):
                                $link = $n['link_url'] ?? '';
                                $safeLink = '';
                                if ($link && str_starts_with($link, BASE_URL)) {
                                    $safeLink = substr($link, strlen(BASE_URL));
                                }
                                $readHref = url('user/notifications/read.php?id='.(int)$n['id']);
                            ?>
                                <a class="notification-list-item <?= empty($n['is_read']) ? 'selected' : '' ?>" href="<?= e($readHref) ?>">
                                    <div class="notification-icon">♧</div>
                                    <div>
                                        <div class="notification-list-title">
                                            <?= e($n['title']) ?>
                                            <?php if (empty($n['is_read'])): ?><span class="badge badge-green" style="margin-left:6px">Unread</span><?php endif; ?>
                                        </div>
                                        <div class="notification-list-message"><?= e($n['message']) ?></div>
                                    </div>
                                    <div class="notification-list-time"><?= e(date('d M Y, h:i A', strtotime($n['created_at']))) ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <aside class="notification-detail-card">
                        <div class="eyebrow">Notification centre</div>
                        <h2>Stay informed.</h2>
                        <p>
                            Booking submissions, review updates, confirmations, schedule changes and important account updates appear here.
                        </p>
                        <div class="info-box" style="margin-top:18px">
                            <strong>Unread notifications</strong><br>
                            You currently have <b><?= $userUnreadNotifications ?></b> unread notification<?= $userUnreadNotifications === 1 ? '' : 's' ?>.
                        </div>
                        <a class="btn btn-primary" style="margin-top:18px" href="<?= url('user/bookings/index.php') ?>">View My Bookings →</a>
                    </aside>
                </div>

            </div>
        </section>
    </section>
</div>

<?php require __DIR__.'/../../includes/footer.php'; ?>
