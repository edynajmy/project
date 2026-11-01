<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('Administrator', 'Management', 'Event');
$pageTitle = 'Venue Management';

$rows = db()->query("\n    SELECT v.*, l.name AS location_name,\n           (SELECT vi.image_path FROM venue_images vi WHERE vi.venue_id=v.id ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC LIMIT 1) AS image_path,\n           (SELECT COUNT(*) FROM venue_images vi2 WHERE vi2.venue_id=v.id) AS image_count\n    FROM venues v\n    LEFT JOIN locations l ON l.id = v.location_id\n    ORDER BY v.featured DESC, v.name ASC\n")->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>

<div class="admin-shell">
    <aside class="sidebar">
        <?php require __DIR__ . '/../sidebar.php'; ?>
    </aside>

    <section class="admin-content">
        <div class="admin-top">
            <div>
                <div class="eyebrow">Venue Master</div>
                <h1 class="admin-title">Venue Management</h1>
                <p class="kicker">Manage resort venues, capacities, rates and availability status.</p>
            </div>
            <div class="admin-actions">
                <span class="admin-count"><?= count($rows) ?> Venues</span>
            </div>
        </div>

        <div class="admin-stat-row">
            <div class="admin-stat-card">
                <span>Total Venues</span>
                <strong><?= count($rows) ?></strong>
                <small>Venue records</small>
            </div>
            <div class="admin-stat-card">
                <span>Available</span>
                <strong><?= count(array_filter($rows, fn($v) => $v['status'] === 'available')) ?></strong>
                <small>Open for booking</small>
            </div>
            <div class="admin-stat-card">
                <span>Featured</span>
                <strong><?= count(array_filter($rows, fn($v) => (int)$v['featured'] === 1)) ?></strong>
                <small>Featured venues</small>
            </div>
        </div>

        <div class="panel table-wrap">
            <table class="table admin-data-table">
                <thead>
                    <tr>
                        <th>Venue</th>
                        <th>Photo</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Rate</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Photos</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="empty-table">No venues found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $v): ?>
                        <tr>
                            <td>
                                <strong><?= e($v['name']) ?></strong>
                            </td>
                            <td>
                                <?php $venueThumb = venue_image_url($v['image_path'] ?? null, $v['name'] ?? null); ?>
                                <?php if ($venueThumb): ?>
                                    <img src="<?= e($venueThumb) ?>" alt="<?= e($v['name']) ?>" style="width:82px;height:54px;object-fit:cover;border-radius:6px;display:block">
                                <?php else: ?>
                                    <span class="badge">No photo</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($v['venue_type']) ?></td>
                            <td><?= number_format((int)$v['capacity']) ?> pax</td>
                            <td>RM <?= number_format((float)$v['rate'], 2) ?></td>
                            <td><?= e($v['location_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge <?= $v['status'] === 'available' ? 'badge-green' : 'badge-gold' ?>">
                                    <?= e(ucfirst($v['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/venues/photos.php?venue='.(int)$v['id']) ?>">Manage Photos (<?= (int)$v['image_count'] ?>)</a>
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
