<?php
$u = current_user();
$pageTitle = $pageTitle ?? APP_NAME;

// Admin/management area uses its own sidebar navigation.
// Do not show the public website navigation after an administrator logs in.
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$isAdminArea = strpos($scriptPath, '/admin/') !== false;
$isAdminUser = $u && has_role('Administrator','Management','Event','HR','Author','IT');
$isUserArea = $u && strpos($scriptPath, '/user/') !== false;
$explorePaths = ['/public/calendar/index.php','/public/venues/index.php','/public/gallery/index.php','/public/about.php','/public/contact.php'];
// SCRIPT_NAME may include the local project folder (e.g. /berjaya_hills/public/calendar/index.php).
$matchesExplorePath = false;
foreach ($explorePaths as $explorePath) {
    if (str_ends_with($scriptPath, $explorePath)) { $matchesExplorePath = true; break; }
}
$useExploreShell = (bool)$u && !$isAdminArea && !$isUserArea && $matchesExplorePath;
$useAdminShell = $isAdminArea && $isAdminUser;
$useUserShell = false; // User pages render their own application shell.
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
<meta name="theme-color" content="#0d211c">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css?v=115')) ?>">
</head>
<body class="<?= $useAdminShell ? 'admin-body' : (($useUserShell || $useExploreShell) ? 'user-body' : '') ?><?= $useExploreShell ? ' explore-body' : '' ?>">

<header class="site-header">
  <div class="nav-wrap">
    <a class="brand" href="<?= url($isAdminUser ? 'admin/dashboard/index.php' : ($isUserArea ? 'user/dashboard/index.php' : 'index.php')) ?>">
      <span class="brand-mark">BH</span>
      <span><b>BERJAYA HILLS</b><small>RESORT · EVENTS & ACTIVITIES</small></span>
    </a>

    <?php if ($isAdminUser): ?>
      <div class="nav-actions admin-nav-actions">
        <span class="nav-user"><?= e($u['full_name'] ?? 'Administrator') ?></span>
        <a class="btn btn-outline" href="<?= url('auth/logout.php') ?>">Sign out</a>
      </div>
    <?php elseif ($isUserArea || $useExploreShell): ?>
      <div class="nav-actions user-nav-actions">
        <span class="nav-user"><?= e($u['full_name'] ?? 'User') ?></span>
        <a class="btn btn-outline" href="<?= url('auth/logout.php') ?>">Sign out</a>
      </div>
    <?php else: ?>
      <nav class="main-nav">
        <a href="<?= url('index.php') ?>">Home</a>
        <a href="<?= url('public/calendar/index.php') ?>">Calendar</a>
        <a href="<?= url('public/venues/index.php') ?>">Venues</a>
        <a href="<?= url('public/gallery/index.php') ?>">Gallery</a>
        <a href="<?= url('public/about.php') ?>">About</a>
        <a href="<?= url('public/contact.php') ?>">Contact</a>
      </nav>
      <div class="nav-actions">
        <?php if ($u): ?>
          <a class="nav-user" href="<?= url('user/dashboard/index.php') ?>"><?= e($u['full_name']) ?></a>
          <a class="btn btn-outline" href="<?= url('auth/logout.php') ?>">Sign out</a>
        <?php else: ?>
          <a class="btn btn-ghost" href="<?= url('public/auth/login.php') ?>">Sign in</a>
          <a class="btn btn-gold" href="<?= url('public/auth/register.php') ?>">Request account</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <button class="mobile-menu" aria-label="Menu">☰</button>
  </div>
</header>

<?php foreach (get_flashes() as $f): ?>
  <div class="toast toast-<?= e($f[0]) ?>"><?= e($f[1]) ?></div>
<?php endforeach; ?>

<main>
<?php if ($useExploreShell): ?>
<div class="admin-shell user-shell explore-user-shell">
  <aside class="sidebar"><?php require __DIR__ . '/../user/sidebar.php'; ?></aside>
  <section class="admin-content user-content explore-user-content">
<?php endif; ?>

