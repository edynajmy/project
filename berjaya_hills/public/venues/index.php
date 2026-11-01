<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$pageTitle = 'Venues';
$active = 'venues';

$sort = $_GET['sort'] ?? 'recommended';
$view = $_GET['view'] ?? 'grid';

$sql = "SELECT v.*, l.name AS location_name,
        (SELECT vi.image_path FROM venue_images vi WHERE vi.venue_id=v.id ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC LIMIT 1) AS image_path,
        (SELECT COUNT(*) FROM venue_facilities vf WHERE vf.venue_id=v.id) AS facility_count
        FROM venues v
        LEFT JOIN locations l ON l.id=v.location_id
        WHERE v.status='available'";
$params = [];


switch ($sort) {
    case 'capacity_high': $sql .= " ORDER BY v.capacity DESC, v.name ASC"; break;
    case 'capacity_low': $sql .= " ORDER BY v.capacity ASC, v.name ASC"; break;
    case 'price_low': $sql .= " ORDER BY v.rate ASC, v.name ASC"; break;
    case 'price_high': $sql .= " ORDER BY v.rate DESC, v.name ASC"; break;
    case 'name': $sql .= " ORDER BY v.name ASC"; break;
    default: $sql .= " ORDER BY v.featured DESC, v.name ASC";
}

$stmt = db()->prepare($sql);
$stmt->execute($params);
$venues = $stmt->fetchAll();




require __DIR__ . '/../../includes/header.php';
?>

<section class="venues-hero">
    <div class="venues-hero-overlay"></div>
    <div class="venues-hero-inner">
        <div class="eyebrow">Our Venues</div>
        <h1>Extraordinary venues<br>for memorable events.</h1>
        <p>Discover refined indoor and outdoor spaces set amid the natural beauty of Berjaya Hills — designed for meetings, weddings, banquets and special occasions.</p>
    </div>
</section>

<section class="venues-section">
    <div class="venues-layout venues-layout-full">

        <main class="venue-results venue-results-full">
            <div class="venue-results-toolbar">
                <div>
                    <strong><?= count($venues) ?> Places &amp; Venues Found</strong>
                </div>
                <div class="venue-toolbar-actions">
                    <label class="sort-control">Sort by
                        <select onchange="venueSort(this.value)">
                            <option value="recommended" <?= $sort==='recommended'?'selected':'' ?>>Recommended</option>
                            <option value="name" <?= $sort==='name'?'selected':'' ?>>Name</option>
                            <option value="capacity_high" <?= $sort==='capacity_high'?'selected':'' ?>>Capacity: High</option>
                            <option value="capacity_low" <?= $sort==='capacity_low'?'selected':'' ?>>Capacity: Low</option>
                            <option value="price_low" <?= $sort==='price_low'?'selected':'' ?>>Price: Low</option>
                            <option value="price_high" <?= $sort==='price_high'?'selected':'' ?>>Price: High</option>
                        </select>
                    </label>
                    <div class="view-toggle" role="group" aria-label="Venue view">
                        <button type="button" class="<?= $view==='grid'?'active':'' ?>" onclick="setVenueView('grid')">▦ Grid</button>
                        <button type="button" class="<?= $view==='list'?'active':'' ?>" onclick="setVenueView('list')">☷ List</button>
                    </div>
                </div>
            </div>

            <?php if (!$venues): ?>
                <div class="panel venue-empty">
                    <h2>No venues found</h2>
                    <p>No venues are currently available.</p>
                    <a class="btn btn-gold" href="<?= url('public/venues/index.php') ?>">Back to venues</a>
                </div>
            <?php else: ?>
                <div class="venue-grid <?= $view==='list'?'list-view':'' ?>">
                    <?php foreach ($venues as $v): ?>
                        <?php
                        $img = venue_image_url($v['image_path'] ?? null, $v['name'] ?? null);
                        $facStmt = db()->prepare("SELECT f.name FROM venue_facilities vf JOIN facilities f ON f.id=vf.facility_id WHERE vf.venue_id=? ORDER BY f.name LIMIT 4");
                        $facStmt->execute([(int)$v['id']]);
                        $venueFac = $facStmt->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        <article class="venue-card-premium">
                            <a class="venue-image" href="<?= url('public/venues/view.php?id='.$v['id']) ?>" <?= $img ? 'style="background-image:url(\''.e($img).'\')"' : '' ?> aria-label="<?= e($v['name']) ?>">
                                <?php if (!$img): ?><span class="image-placeholder">BH</span><?php endif; ?>
                                <span class="heart-btn" aria-label="Save venue">♡</span>
                            </a>
                            <div class="venue-card-content">
                                <span class="tag"><?= e($v['venue_type'] ?: 'Venue') ?></span>
                                <h2><?= e($v['name']) ?></h2>
                                <div class="venue-meta">
                                    <span>⌂ <?= e($v['venue_type'] ?: 'Venue') ?></span>
                                    <?php if ((int)($v['capacity'] ?? 0) > 0): ?><span>♙ <?= number_format((int)$v['capacity']) ?> pax</span><?php else: ?><span>📍 Berjaya Hills</span><?php endif; ?>
                                </div>
                                <p><?= e(mb_strimwidth($v['description'] ?? '', 0, 145, '...')) ?></p>
                                <div class="facility-pills">
                                    <?php foreach ($venueFac as $vf): ?><span><?= e($vf) ?></span><?php endforeach; ?>
                                </div>
                                <div class="venue-actions">
                                    <a class="btn btn-gold" href="<?= url('public/venues/view.php?id='.$v['id']) ?>">View Details →</a>
                                    <?php if ((int)($v['bookable'] ?? 1) === 1): ?>
                                        <a class="btn btn-outline-dark" href="<?= url('public/calendar/index.php?venue='.$v['id']) ?>">▣ Check Availability</a>
                                    <?php else: ?>
                                        <span class="venue-nonbookable">Information only</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


        </main>
    </div>
</section>

<script>
function venueSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    window.location.href = url.toString();
}
function setVenueView(view) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', view);
    window.location.href = url.toString();
}
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
