<?php
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

$navActive = static function (string $needle) use ($currentPath): string {
    return strpos($currentPath, '/' . ltrim($needle, '/')) !== false ? 'active' : '';
};

$isBookingCreate = strpos($currentPath, '/user/bookings/create.php') !== false;
$isBookingChange = strpos($currentPath, '/user/bookings/change.php') !== false;
?>

<div class="user-sidebar-tools"><span class="side-label">Overview</span><button type="button" class="sidebar-collapse-toggle" aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar">‹</button></div>

<a class="<?= $navActive('user/dashboard/') ?>" href="<?= url('user/dashboard/index.php') ?>">
    <span class="side-icon">⌂</span>
    <span>Dashboard</span>
</a>

<a class="<?= $isBookingCreate ? 'active' : '' ?>" href="<?= url('user/bookings/create.php?fresh=1') ?>">
    <span class="side-icon">＋</span>
    <span>New Booking</span>
</a>

<a class="<?= (!$isBookingCreate && !$isBookingChange && $navActive('user/bookings/')) ? 'active' : '' ?>" href="<?= url('user/bookings/index.php') ?>">
    <span class="side-icon">▤</span>
    <span>My Bookings</span>
</a>

<a class="<?= $navActive('user/drafts/') ?>" href="<?= url('user/drafts/index.php') ?>">
    <span class="side-icon">▱</span>
    <span>Drafts</span>
</a>

<a class="<?= $navActive('user/calendar/') ?>" href="<?= url('user/calendar/index.php') ?>">
    <span class="side-icon">▣</span>
    <span>Calendar</span>
</a>

<a class="<?= $navActive('user/notifications/') ?>" href="<?= url('user/notifications/index.php') ?>">
    <span class="side-icon">♧</span>
    <span>Notifications</span>
    <?php if (!empty($userUnreadNotifications) && (int)$userUnreadNotifications > 0): ?>
        <span class="user-notification-badge"><?= (int)$userUnreadNotifications ?></span>
    <?php endif; ?>
</a>

<a class="<?= $navActive('user/profile/') ?>" href="<?= url('user/profile/index.php') ?>">
    <span class="side-icon">♙</span>
    <span>Profile</span>
</a>

<div class="side-label" style="margin-top:26px">Explore</div>

<a class="<?= strpos($currentPath, '/public/calendar/') !== false ? 'active' : '' ?>" href="<?= url('public/calendar/index.php') ?>">
    <span class="side-icon">▣</span>
    <span>Event Calendar</span>
</a>

<a class="<?= strpos($currentPath, '/public/venues/') !== false ? 'active' : '' ?>" href="<?= url('public/venues/index.php') ?>">
    <span class="side-icon">▤</span>
    <span>Venues</span>
</a>

<a class="<?= strpos($currentPath, '/public/gallery/') !== false ? 'active' : '' ?>" href="<?= url('public/gallery/index.php') ?>">
    <span class="side-icon">▧</span>
    <span>Gallery</span>
</a>

<a class="<?= strpos($currentPath, '/public/about.php') !== false ? 'active' : '' ?>" href="<?= url('public/about.php') ?>">
    <span class="side-icon">ⓘ</span>
    <span>About</span>
</a>

<a class="<?= strpos($currentPath, '/public/contact.php') !== false ? 'active' : '' ?>" href="<?= url('public/contact.php') ?>">
    <span class="side-icon">✉</span>
    <span>Contact</span>
</a>
