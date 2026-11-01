<?php
$activePage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$statusMessages = [
    'task-added' => 'Task saved successfully.',
    'activity-added' => 'Activity saved successfully.',
    'task-updated' => 'Task status updated.',
    'task-edited' => 'Task updated.', 'task-removed' => 'Task removed.',
    'activity-edited' => 'Activity updated.', 'activity-removed' => 'Activity removed.',
    'folder-added' => 'Folder created.', 'folder-edited' => 'Folder updated.', 'folder-removed' => 'Folder and its photos removed.',
    'folder-exists' => 'A folder with that name already exists.',
    'photos-uploaded' => 'Photos uploaded.', 'photo-edited' => 'Photo updated.', 'photo-removed' => 'Photo removed.',
    'upload-failed' => 'No photos were uploaded. Check file types and sizes.',
    'media-added' => 'Added to your watchlist.', 'media-edited' => 'Watchlist entry updated.', 'media-removed' => 'Removed from your watchlist.',
    'poster-failed' => 'The poster could not be uploaded. Use JPG, PNG or WebP under 5 MB.',
    'work-added' => 'Work item added.', 'work-edited' => 'Work item updated.', 'work-removed' => 'Work item removed.', 'work-status' => 'Work status updated.',
    'work-added' => 'Work item added.', 'work-edited' => 'Work item updated.', 'work-removed' => 'Work item removed.', 'work-status' => 'Work status updated.',
    'work-added' => 'Work item added.', 'work-edited' => 'Work item updated.', 'work-removed' => 'Work item removed.', 'work-status' => 'Work status updated.',
    'journal-added' => 'Journal entry saved.', 'journal-edited' => 'Journal entry updated.', 'journal-removed' => 'Journal entry removed.',
    'invalid' => 'Please check the submitted information.',
];
$status = $_GET['status'] ?? '';
$profileName = (string)($profileSettings['display_name'] ?? 'Edy');
$profileTagline = (string)($profileSettings['tagline'] ?? 'Your world. Your story.');
$profileAvatarPath = (string)($profileSettings['avatar_path'] ?? '');
$profileInitial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($profileName, 0, 1)) : strtoupper(substr($profileName, 0, 1));
$profileAvatarUrl = $profileAvatarPath !== '' ? app_url($profileAvatarPath) : ''; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#0b0b0d">
  <meta name="description" content="EdyLife — your personal space for plans, passions and memories.">
  <title><?= e($pageTitle ?? 'Dashboard') ?> · EdyLife</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">
  <script>try{document.documentElement.dataset.theme=localStorage.getItem("noirlife-theme")||"dark";}catch(e){document.documentElement.dataset.theme="dark";}</script>
  <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
  <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(app_url('index.php')) ?>" aria-label="EdyLife home">
      <span class="brand-symbol">E</span><span class="brand-copy"><strong>EDYLIFE</strong><small>YOUR WORLD. YOUR STORY.</small></span>
    </a>
    <div class="side-label">WORKSPACE</div>
    <nav class="main-nav" aria-label="Main navigation">
      <a class="nav-link <?= $activePage === 'index.php' ? 'active' : '' ?>" href="<?= e(app_url('index.php')) ?>"><span class="nav-icon">⌂</span><span>Dashboard</span><span class="nav-indicator"></span></a>
      <a class="nav-link <?= $activePage === 'activities.php' ? 'active' : '' ?>" href="<?= e(app_url('pages/activities.php')) ?>"><span class="nav-icon">◷</span><span>Activities</span></a>
      <a class="nav-link <?= $activePage === 'movies.php' ? 'active' : '' ?>" href="<?= e(app_url('pages/movies.php')) ?>"><span class="nav-icon">▣</span><span>Movie & Drama</span></a>
      <a class="nav-link <?= $activePage === 'work-planner.php' ? 'active' : '' ?>" href="<?= e(app_url('pages/work-planner.php')) ?>"><span class="nav-icon">▤</span><span>Work Planner</span></a>
      <a class="nav-link <?= $activePage === 'gallery.php' ? 'active' : '' ?>" href="<?= e(app_url('pages/gallery.php')) ?>"><span class="nav-icon">▧</span><span>Memories & Photos</span></a>
    </nav>
    <div class="side-label side-label-lower">PERSONAL</div>
    <nav class="main-nav" aria-label="Personal navigation">
      <a class="nav-link <?= $activePage === 'calendar.php' ? 'active' : '' ?>" href="<?= e(app_url('pages/calendar.php')) ?>"><span class="nav-icon">▦</span><span>Calendar</span></a>
      <a class="nav-link <?= $activePage === 'journal.php' ? 'active' : '' ?>" href="<?= e(app_url('pages/journal.php')) ?>"><span class="nav-icon">✎</span><span>Daily Journal</span></a>
    </nav>
    <div class="sidebar-bottom">
      <div class="sidebar-note"><span class="note-star">✦</span><p>Make space for<br><em>what matters.</em></p><span class="note-rule"></span><small>YOUR LIFE, YOUR PACE</small></div>
      <a class="profile-mini profile-link" href="<?= e(app_url('pages/profile.php')) ?>" aria-label="Open profile"><?php if ($profileAvatarUrl !== ''): ?><img class="avatar avatar-image" src="<?= e($profileAvatarUrl) ?>" alt=""><?php else: ?><div class="avatar"><?= e($profileInitial) ?></div><?php endif; ?><div><strong><?= e($profileName) ?></strong><small>My profile</small></div><span class="online-dot"></span></a>
    </div>
  </aside>

  <main class="main-area">
    <header class="topbar">
      <button class="mobile-menu icon-button" type="button" id="menuToggle" aria-label="Open navigation">☰</button>
      <div class="breadcrumb"><span>Workspace</span><span class="crumb-slash">/</span><strong><?= e($pageTitle ?? 'Dashboard') ?></strong></div>
      <div class="topbar-actions"><button class="theme-toggle" type="button" id="themeToggle" aria-label="Switch to light mode" title="Switch theme"><span class="theme-icon" aria-hidden="true">☼</span><span class="theme-label">Light mode</span></button><span class="local-badge"><span></span> LOCAL MODE</span><div class="topbar-divider"></div><span class="topbar-date"><?= e(date('d M Y')) ?></span><?php if ($profileAvatarUrl !== ''): ?><a class="avatar avatar-small avatar-profile-link avatar-image-link" href="<?= e(app_url('pages/profile.php')) ?>" aria-label="Open profile" title="My profile"><img class="avatar avatar-image" src="<?= e($profileAvatarUrl) ?>" alt=""></a><?php else: ?><a class="avatar avatar-small avatar-profile-link" href="<?= e(app_url('pages/profile.php')) ?>" aria-label="Open profile" title="My profile"><?= e($profileInitial) ?></a><?php endif; ?></div>
    </header>
    <div class="page-content">
      <?php if (isset($statusMessages[$status])): ?><div class="flash-message" role="status"><?= e($statusMessages[$status]) ?><button type="button" class="flash-close" aria-label="Dismiss">×</button></div><?php endif; ?>
