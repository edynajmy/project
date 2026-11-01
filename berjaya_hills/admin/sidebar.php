<?php
$currentPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

function admin_nav_active(string $path): string
{
    global $currentPath;
    return strpos($currentPath, '/' . ltrim($path, '/')) !== false ? 'active' : '';
}
?>

<div class="side-label">Overview</div>

<a class="<?= admin_nav_active('admin/dashboard/') ?>" href="<?= url('admin/dashboard/index.php') ?>">
    <span class="side-icon">⌂</span>
    <span>Dashboard</span>
</a>

<a class="<?= admin_nav_active('admin/calendar/') ?>" href="<?= url('admin/calendar/index.php') ?>">
    <span class="side-icon">▣</span>
    <span>Management Calendar</span>
</a>

<a class="<?= admin_nav_active('admin/bookings/') ?>" href="<?= url('admin/bookings/index.php') ?>">
    <span class="side-icon">▤</span>
    <span>Booking Management</span>
</a>

<a class="<?= admin_nav_active('admin/change-requests/') ?>" href="<?= url('admin/change-requests/index.php') ?>">
    <span class="side-icon">↔</span>
    <span>Change Requests</span>
</a>

<a class="<?= admin_nav_active('admin/users/') ?>" href="<?= url('admin/users/index.php') ?>">
    <span class="side-icon">♙</span>
    <span>User Management</span>
</a>

<div class="side-label">Operations</div>

<a class="<?= admin_nav_active('admin/events/') ?>" href="<?= url('admin/events/index.php') ?>">
    <span class="side-icon">◆</span>
    <span>Event Management</span>
</a>

<a class="<?= admin_nav_active('admin/venues/') ?>" href="<?= url('admin/venues/index.php') ?>">
    <span class="side-icon">⌂</span>
    <span>Venue Management</span>
</a>

<a class="<?= admin_nav_active('admin/facilities/') ?>" href="<?= url('admin/facilities/index.php') ?>">
    <span class="side-icon">⚙</span>
    <span>Facility Management</span>
</a>

<a class="<?= admin_nav_active('admin/activities/') ?>" href="<?= url('admin/activities/index.php') ?>">
    <span class="side-icon">✦</span>
    <span>Activity Management</span>
</a>

<div class="side-label">Content &amp; Insights</div>

<a class="<?= admin_nav_active('admin/gallery/') ?>" href="<?= url('admin/gallery/index.php') ?>">
    <span class="side-icon">▧</span>
    <span>Gallery Management</span>
</a>

<a class="<?= admin_nav_active('admin/contact/') ?>" href="<?= url('admin/contact/index.php') ?>">
    <span class="side-icon">✉</span>
    <span>Contact Enquiries</span>
</a>

<a class="<?= admin_nav_active('admin/reports/') ?>" href="<?= url('admin/reports/index.php') ?>">
    <span class="side-icon">▥</span>
    <span>Reports</span>
</a>

<a class="<?= admin_nav_active('admin/audit/') ?>" href="<?= url('admin/audit/index.php') ?>">
    <span class="side-icon">◷</span>
    <span>Audit Trail</span>
</a>

<div class="side-label">System</div>

<a class="<?= admin_nav_active('admin/notifications/') ?>" href="<?= url('admin/notifications/index.php') ?>">
    <span class="side-icon">♧</span>
    <span>Notifications</span>
</a>

<a class="<?= admin_nav_active('admin/email/') ?>" href="<?= url('admin/email/index.php') ?>">
    <span class="side-icon">✉</span>
    <span>Email Logs</span>
</a>

<a class="<?= admin_nav_active('admin/roles/') ?>" href="<?= url('admin/roles/index.php') ?>">
    <span class="side-icon">♙</span>
    <span>Roles &amp; Permissions</span>
</a>

<a class="<?= admin_nav_active('admin/settings/') ?>" href="<?= url('admin/settings/index.php') ?>">
    <span class="side-icon">⚙</span>
    <span>System Settings</span>
</a>

