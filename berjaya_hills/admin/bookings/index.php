<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Administrator', 'Management', 'Event');
$pageTitle = 'Booking Management';

$status = $_GET['status'] ?? '';
$sql = 'SELECT * FROM vw_booking_summary';
$params = [];

if ($status !== '') {
    $sql .= ' WHERE status = ?';
    $params[] = $status;
}

$sql .= ' ORDER BY created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="admin-shell">
    <aside class="sidebar">
        <?php require __DIR__ . '/../sidebar.php'; ?>
    </aside>

    <section class="admin-content">
        <div class="admin-top">
            <div>
                <div class="eyebrow">Workflow</div>
                <h1 class="admin-title">Booking Management</h1>
                <p class="kicker">Review, approve or reject submitted user bookings.</p>
            </div>

            <form method="get" class="admin-actions">
                <select name="status" class="admin-filter" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach (['pending_review','pending_info','confirmed','rejected','cancelled','completed','draft'] as $x): ?>
                        <option value="<?= e($x) ?>" <?= $status === $x ? 'selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $x))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="admin-stat-row">
            <div class="admin-stat-card">
                <span>Total</span>
                <strong><?= count($rows) ?></strong>
                <small>Bookings shown</small>
            </div>
            <div class="admin-stat-card">
                <span>Pending Review</span>
                <strong><?= count(array_filter($rows, fn($r) => $r['status'] === 'pending_review')) ?></strong>
                <small>Require action</small>
            </div>
            <div class="admin-stat-card">
                <span>Confirmed</span>
                <strong><?= count(array_filter($rows, fn($r) => $r['status'] === 'confirmed')) ?></strong>
                <small>Approved bookings</small>
            </div>
        </div>

        <div class="panel table-wrap">
            <table class="table admin-data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Owner</th>
                        <th>Event</th>
                        <th>Venue</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="empty-table">No bookings found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $b): ?>
                        <tr>
                            <td><b><?= e($b['booking_ref']) ?></b></td>
                            <td><?= e($b['user_name']) ?></td>
                            <td><?= e($b['event_title']) ?></td>
                            <td><?= e($b['other_venue_name'] ?: ($b['venue_names'] ?? '—')) ?></td>
                            <td><?= !empty($b['start_at']) ? e(date('d M Y, H:i', strtotime($b['start_at']))) : '—' ?></td>
                            <td>
                                <span class="badge <?= $b['status'] === 'confirmed' ? 'badge-green' : ($b['status'] === 'rejected' ? 'badge-red' : 'badge-gold') ?>">
                                    <?= e(ucwords(str_replace('_', ' ', $b['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-outline btn-sm admin-review-btn" href="<?= url('admin/bookings/view.php?id=' . (int)$b['id']) ?>">
                                    Review →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
