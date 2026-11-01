<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Dashboard';
$today = date('Y-m-d');
$displayDate = date('l, d F Y');
$coverFiles = glob(__DIR__ . '/uploads/cover/cover.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
$dashboardCoverUrl = $coverFiles ? 'uploads/cover/' . basename($coverFiles[0]) : ''; 

$taskStmt = $pdo->prepare("SELECT id, title, category, priority, due_date, is_done FROM tasks WHERE due_date = ? ORDER BY is_done ASC, FIELD(priority, 'high','medium','low'), id DESC LIMIT 8");
$taskStmt->execute([$today]);
$tasks = $taskStmt->fetchAll();

$activityStmt = $pdo->prepare("SELECT id, title, category, start_time, end_time, notes FROM activities WHERE activity_date = ? ORDER BY start_time ASC LIMIT 8");
$activityStmt->execute([$today]);
$activities = $activityStmt->fetchAll();

$doneStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date = ? AND is_done = 1");
$doneStmt->execute([$today]);
$completedToday = (int)$doneStmt->fetchColumn();

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date = ?");
$totalStmt->execute([$today]);
$totalToday = (int)$totalStmt->fetchColumn();

$weekStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE due_date BETWEEN ? AND ?");
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
$weekStmt->execute([$monday, $sunday]);
$weekTasks = (int)$weekStmt->fetchColumn();

$activityCountStmt = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE activity_date = ?");
$activityCountStmt->execute([$today]);
$activityCount = (int)$activityCountStmt->fetchColumn();

$progress = $totalToday > 0 ? (int)round(($completedToday / $totalToday) * 100) : 0;
include __DIR__ . '/includes/header.php';
?>
<section class="hero-panel"<?= $dashboardCoverUrl !== '' ? ' style="background-image: url(' . e($dashboardCoverUrl) . ');"' : '' ?>>
  <div class="hero-shade"></div>
  <div class="hero-content">
    <div class="eyebrow"><span class="eyebrow-line"></span> YOUR PERSONAL SPACE</div>
    <p class="hero-date"><?= e($displayDate) ?></p>
    <h1>Make today<br><em>yours.</em></h1>
    <p class="hero-copy">A little structure. A little adventure. A life that feels like yours.</p>
    <button class="btn btn-gold" type="button" data-open-modal="activityModal"><span>＋</span> Add activity</button>
  </div>
<div class="hero-caption">EDYLIFE / 001</div>
  <form class="cover-upload-form" action="actions/cover-upload.php" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="index.php">
    <label class="btn btn-outline btn-small" for="dashboardCoverInput">▧ Change cover photo</label>
    <input id="dashboardCoverInput" type="file" name="cover" accept="image/jpeg,image/png,image/webp" required onchange="if(this.form.dataset.submitting!=='1'){this.form.dataset.submitting='1';this.form.submit();}">
  </form>
</section>

<section class="section-heading">
  <div>
    <p class="eyebrow">AT A GLANCE</p>
    <h2>Your overview</h2>
  </div>
  <span class="muted small"><?= e(date('D, d M')) ?></span>
</section>

<section class="stats-grid">
  <article class="stat-card">
    <div class="stat-top"><span class="stat-icon">◷</span><span class="stat-label">TODAY'S TASKS</span></div>
    <div class="stat-value"><?= $totalToday ?><span class="stat-unit"> tasks</span></div>
    <div class="stat-foot"><?= $completedToday ?> completed today</div>
    <div class="mini-progress"><span style="width:<?= $progress ?>%"></span></div>
  </article>
  <article class="stat-card">
    <div class="stat-top"><span class="stat-icon">✦</span><span class="stat-label">WEEKLY FOCUS</span></div>
    <div class="stat-value"><?= $weekTasks ?><span class="stat-unit"> tasks</span></div>
    <div class="stat-foot">Scheduled this week</div>
    <div class="stat-decoration">✧</div>
  </article>
  <article class="stat-card">
    <div class="stat-top"><span class="stat-icon">⌁</span><span class="stat-label">ACTIVITIES</span></div>
    <div class="stat-value"><?= $activityCount ?><span class="stat-unit"> planned</span></div>
    <div class="stat-foot">On today's timeline</div>
    <div class="stat-decoration">⌁</div>
  </article>
</section>

<section class="content-grid">
  <article class="panel tasks-panel">
    <div class="panel-heading">
      <div><p class="eyebrow">STAY ON TRACK</p><h2>Today's tasks</h2></div>
      <button class="icon-button" type="button" data-open-modal="taskModal" aria-label="Add task">＋</button>
    </div>
    <?php if (!$tasks): ?>
      <div class="empty-state">
        <div class="empty-icon">✦</div>
        <h3>A fresh page.</h3>
        <p>Your day is open. Add a task to get started.</p>
        <button class="btn btn-outline" type="button" data-open-modal="taskModal">Create a task</button>
      </div>
    <?php else: ?>
      <div class="task-list">
        <?php foreach ($tasks as $task): ?>
          <div class="task-row <?= $task['is_done'] ? 'is-done' : '' ?>">
            <form method="post" action="actions/task-toggle.php" class="task-check-form">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$task['id'] ?>">
              <button class="task-check" type="submit" aria-label="<?= $task['is_done'] ? 'Mark incomplete' : 'Mark complete' ?>"><?= $task['is_done'] ? '✓' : '' ?></button>
            </form>
            <div class="task-info">
              <strong><?= e($task['title']) ?></strong>
              <span><?= e($task['category'] ?: 'Personal') ?></span>
            </div>
            <span class="priority priority-<?= e($task['priority']) ?>"><?= e(ucfirst($task['priority'])) ?></span>
            <button class="mini-action" type="button" data-edit-task='<?= e(json_encode($task, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>' aria-label="Edit task">✎</button>
            <form method="post" action="actions/task-remove.php" class="inline-form" data-confirm="Remove this task?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$task['id'] ?>"><button class="mini-action danger-action" type="submit" aria-label="Remove task">×</button></form>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="panel-bottom"><span class="muted small"><?= $completedToday ?> of <?= $totalToday ?> complete</span><span class="gold-text small"><?= $progress ?>%</span></div>
    <?php endif; ?>
  </article>

  <article class="panel timeline-panel">
    <div class="panel-heading">
      <div><p class="eyebrow">MAKE IT COUNT</p><h2>Today's timeline</h2></div>
      <button class="icon-button" type="button" data-open-modal="activityModal" aria-label="Add activity">＋</button>
    </div>
    <?php if (!$activities): ?>
      <div class="empty-state compact">
        <div class="empty-icon">◷</div>
        <h3>Make room for what matters.</h3>
        <p>Add work, rest, gaming or anything you want to make time for.</p>
      </div>
    <?php else: ?>
      <div class="timeline">
        <?php foreach ($activities as $activity): ?>
          <div class="timeline-item">
            <div class="timeline-time"><?= e(date('g:i A', strtotime($activity['start_time']))) ?></div>
            <div class="timeline-track"><span></span></div>
            <div class="timeline-details">
              <strong><?= e($activity['title']) ?></strong>
              <span><?= e($activity['category']) ?><?php if ($activity['end_time']): ?> · <?= e(date('g:i A', strtotime($activity['end_time']))) ?><?php endif; ?></span>
            </div>
            <div class="activity-actions"><button class="mini-action" type="button" data-edit-activity='<?= e(json_encode($activity, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)) ?>' aria-label="Edit activity">✎</button><form method="post" action="actions/activity-remove.php" class="inline-form" data-confirm="Remove this activity?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$activity['id'] ?>"><button class="mini-action danger-action" type="submit" aria-label="Remove activity">×</button></form></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
</section>

<?php
// Dashboard 2.0: cross-module snapshots. These queries use existing NoirLife tables.
$monthStart = date('Y-m-01');
$nextMonth = date('Y-m-01', strtotime('+1 month'));
$monthMediaStmt = $pdo->prepare("SELECT COUNT(*) FROM media_watchlist WHERE watch_status='Completed' AND completed_at >= ? AND completed_at < ?");
$monthMediaStmt->execute([$monthStart, $nextMonth]);
$completedThisMonth = (int)$monthMediaStmt->fetchColumn();
$watchingNow = $pdo->query("SELECT id, title, media_type, poster_file, rating FROM media_watchlist WHERE watch_status='Watching' ORDER BY updated_at DESC, title ASC LIMIT 4")->fetchAll();
$recentJournal = $pdo->query("SELECT id, entry_date, title, category, mood FROM daily_entries ORDER BY entry_date DESC, created_at DESC LIMIT 4")->fetchAll();
$photoCount = (int)$pdo->query('SELECT COUNT(*) FROM photos')->fetchColumn();
$journalMonthStmt = $pdo->prepare('SELECT COUNT(*) FROM daily_entries WHERE entry_date >= ? AND entry_date < ?');
$journalMonthStmt->execute([$monthStart, $nextMonth]);
$journalThisMonth = (int)$journalMonthStmt->fetchColumn();
$dashboardMonths = [];
for ($i=5; $i>=0; $i--) { $d = new DateTimeImmutable('first day of this month'); $d = $d->modify("-$i months"); $dashboardMonths[$d->format('Y-m')] = ['label'=>$d->format('M'), 'completed'=>0, 'journal'=>0]; }
$dashChartStmt = $pdo->prepare("SELECT DATE_FORMAT(completed_at,'%Y-%m') month_key, COUNT(*) total FROM media_watchlist WHERE watch_status='Completed' AND completed_at >= ? AND completed_at < ? GROUP BY month_key");
$dashChartStmt->execute([date('Y-m-01', strtotime('-5 months')), $nextMonth]);
foreach ($dashChartStmt->fetchAll() as $r) if (isset($dashboardMonths[$r['month_key']])) $dashboardMonths[$r['month_key']]['completed']=(int)$r['total'];
$dashJournalChart = $pdo->prepare("SELECT DATE_FORMAT(entry_date,'%Y-%m') month_key, COUNT(*) total FROM daily_entries WHERE entry_date >= ? AND entry_date < ? GROUP BY month_key");
$dashJournalChart->execute([date('Y-m-01', strtotime('-5 months')), $nextMonth]);
foreach ($dashJournalChart->fetchAll() as $r) if (isset($dashboardMonths[$r['month_key']])) $dashboardMonths[$r['month_key']]['journal']=(int)$r['total'];
$dashboardMax=1; foreach($dashboardMonths as $m) $dashboardMax=max($dashboardMax,$m['completed'],$m['journal']);
?>
<section class="section-heading dashboard-extra-heading"><div><p class="eyebrow">YOUR LIFE, IN ONE VIEW</p><h2>Dashboard <em>highlights.</em></h2></div><span class="muted small"><?= e(date('F Y')) ?></span></section>
<section class="stats-grid dashboard-life-stats">
  <article class="stat-card"><div class="stat-top"><span class="stat-icon">▣</span><span class="stat-label">COMPLETED THIS MONTH</span></div><div class="stat-value"><?= $completedThisMonth ?><span class="stat-unit"> titles</span></div><div class="stat-foot">Movies, dramas and more</div><a class="stat-link" href="<?= e(app_url('pages/movies.php')) ?>">Open watchlist ↗</a></article>
  <article class="stat-card"><div class="stat-top"><span class="stat-icon">✎</span><span class="stat-label">JOURNAL THIS MONTH</span></div><div class="stat-value"><?= $journalThisMonth ?><span class="stat-unit"> entries</span></div><div class="stat-foot">Small moments worth keeping</div><a class="stat-link" href="<?= e(app_url('pages/journal.php')) ?>">Open journal ↗</a></article>
  <article class="stat-card"><div class="stat-top"><span class="stat-icon">▧</span><span class="stat-label">MEMORIES & PHOTOS</span></div><div class="stat-value"><?= $photoCount ?><span class="stat-unit"> photos</span></div><div class="stat-foot">Saved in your personal gallery</div><a class="stat-link" href="<?= e(app_url('pages/gallery.php')) ?>">Open gallery ↗</a></article>
</section>
<section class="dashboard-highlights-grid">
  <article class="panel dashboard-chart-panel"><div class="panel-heading"><div><p class="eyebrow">YOUR MONTHLY RHYTHM</p><h2>Activity at a glance</h2><p class="muted small">Completed titles and journal entries · 6 months</p></div></div>
    <div class="dashboard-chart-legend"><span><i class="dash-legend-media"></i>Completed titles</span><span><i class="dash-legend-journal"></i>Journal entries</span></div>
    <div class="dashboard-mini-chart" role="img" aria-label="Six month comparison of completed titles and journal entries">
    <?php foreach($dashboardMonths as $m): ?><div class="dashboard-chart-month"><div class="dashboard-chart-bars"><div class="dashboard-chart-bar-wrap"><span class="dashboard-chart-value"><?= $m['completed'] ?></span><div class="dashboard-chart-bar dash-bar-media" style="height:<?= max(3,(int)round($m['completed']/$dashboardMax*100)) ?>%" title="Completed titles: <?= $m['completed'] ?>"></div></div><div class="dashboard-chart-bar-wrap"><span class="dashboard-chart-value"><?= $m['journal'] ?></span><div class="dashboard-chart-bar dash-bar-journal" style="height:<?= max(3,(int)round($m['journal']/$dashboardMax*100)) ?>%" title="Journal entries: <?= $m['journal'] ?>"></div></div></div><span class="dashboard-chart-month-label"><?= e($m['label']) ?></span></div><?php endforeach; ?>
    </div>
  </article>
  <article class="panel dashboard-watching-panel"><div class="panel-heading"><div><p class="eyebrow">CURRENTLY IN PROGRESS</p><h2>On your screen</h2></div><a class="text-link" href="<?= e(app_url('pages/movies.php')) ?>">View all ↗</a></div>
  <?php if(!$watchingNow): ?><div class="dashboard-empty"><span>▣</span><p>Nothing in progress yet.</p><a href="<?= e(app_url('pages/movies.php')) ?>">Add something to watch ↗</a></div><?php else: ?><div class="dashboard-watching-list"><?php foreach($watchingNow as $media): ?><div class="dashboard-watching-item"><div class="dashboard-poster-mark"><?= e(mb_strtoupper(mb_substr($media['title'],0,1))) ?></div><div class="dashboard-watching-info"><strong><?= e($media['title']) ?></strong><span><?= e($media['media_type']) ?><?php if($media['rating']!==null): ?> · ★ <?= e((string)$media['rating']) ?>/10<?php endif; ?></span></div><span class="watching-pill">Watching</span></div><?php endforeach; ?></div><?php endif; ?>
  </article>
  <article class="panel dashboard-journal-panel"><div class="panel-heading"><div><p class="eyebrow">RECENT THOUGHTS</p><h2>Latest journal</h2></div><a class="text-link" href="<?= e(app_url('pages/journal.php')) ?>">View all ↗</a></div>
  <?php if(!$recentJournal): ?><div class="dashboard-empty"><span>✎</span><p>Your story starts with a note.</p><a href="<?= e(app_url('pages/journal.php')) ?>">Write an entry ↗</a></div><?php else: ?><div class="dashboard-journal-list"><?php foreach($recentJournal as $entry): ?><a class="dashboard-journal-item" href="<?= e(app_url('pages/journal.php?date='.$entry['entry_date'])) ?>"><span class="dashboard-journal-date"><strong><?= e(date('d',strtotime($entry['entry_date']))) ?></strong><small><?= e(date('M',strtotime($entry['entry_date']))) ?></small></span><span class="dashboard-journal-info"><strong><?= e($entry['title']) ?></strong><small><?= e($entry['category']) ?><?php if($entry['mood']): ?> · <?= e($entry['mood']) ?><?php endif; ?></small></span><span class="dashboard-journal-arrow">↗</span></a><?php endforeach; ?></div><?php endif; ?>
  </article>
</section>

<section class="quote-strip">
  <div class="quote-symbol">“</div>
  <div><p>Collect moments, not just milestones.</p><span>A NOTE TO YOURSELF</span></div>
  <div class="quote-stars">✧ &nbsp; ✦ &nbsp; ✧</div>
</section>

<div class="modal-backdrop" id="taskModal" aria-hidden="true">
  <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle">
    <div class="modal-head"><div><p class="eyebrow">MAKE A PLAN</p><h2 id="taskModalTitle">Add a task</h2></div><button class="icon-button" type="button" data-close-modal aria-label="Close">×</button></div>
    <form action="actions/task-create.php" method="post" class="form-stack">
      <?= csrf_field() ?>
      <label>Task title<input name="title" maxlength="160" required placeholder="What would you like to get done?"></label>
      <div class="form-row">
        <label>Category<select name="category"><option>Personal</option><option>Work</option><option>Fitness</option><option>Gaming</option><option>Entertainment</option><option>Errands</option></select></label>
        <label>Priority<select name="priority"><option value="medium">Medium</option><option value="high">High</option><option value="low">Low</option></select></label>
      </div>
      <label>Due date<input type="date" name="due_date" value="<?= e($today) ?>" required></label>
      <div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn btn-gold" type="submit">Save task</button></div>
    </form>
  </section>
</div>

<div class="modal-backdrop" id="editTaskModal" aria-hidden="true"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="editTaskTitleHeading"><div class="modal-head"><div><p class="eyebrow">REFINE YOUR PLAN</p><h2 id="editTaskTitleHeading">Edit task</h2></div><button class="icon-button" type="button" data-close-modal aria-label="Close">×</button></div><form action="actions/task-edit.php" method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="id" id="editTaskId"><label>Task title<input name="title" id="editTaskTitle" maxlength="160" required></label><div class="form-row"><label>Category<select name="category" id="editTaskCategory"><option>Personal</option><option>Work</option><option>Fitness</option><option>Gaming</option><option>Entertainment</option><option>Errands</option></select></label><label>Priority<select name="priority" id="editTaskPriority"><option value="medium">Medium</option><option value="high">High</option><option value="low">Low</option></select></label></div><label>Due date<input type="date" name="due_date" id="editTaskDate" required></label><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn btn-gold" type="submit">Save changes</button></div></form></section></div>
<div class="modal-backdrop" id="activityModal" aria-hidden="true">
  <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="activityModalTitle">
    <div class="modal-head"><div><p class="eyebrow">MAKE TIME FOR IT</p><h2 id="activityModalTitle">Add an activity</h2></div><button class="icon-button" type="button" data-close-modal aria-label="Close">×</button></div>
    <form action="actions/activity-create.php" method="post" class="form-stack">
      <?= csrf_field() ?>
      <label>Activity name<input name="title" maxlength="160" required placeholder="Movie night, work, gaming..."></label>
      <div class="form-row">
        <label>Category<select name="category"><option>Personal</option><option>Work</option><option>Fitness</option><option>Gaming</option><option>Entertainment</option><option>Rest</option><option>Errands</option></select></label>
        <label>Date<input type="date" name="activity_date" value="<?= e($today) ?>" required></label>
      </div>
      <div class="form-row">
        <label>Start time<input type="time" name="start_time" value="<?= e(date('H:i')) ?>" required></label>
        <label>End time<input type="time" name="end_time"></label>
      </div>
      <label>Notes <span class="muted">(optional)</span><textarea name="notes" rows="3" maxlength="1000" placeholder="Anything you want to remember..."></textarea></label>
      <div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn btn-gold" type="submit">Save activity</button></div>
    </form>
  </section>
</div>
<div class="modal-backdrop" id="editActivityModal" aria-hidden="true"><section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="editActivityTitleHeading"><div class="modal-head"><div><p class="eyebrow">REFINE YOUR DAY</p><h2 id="editActivityTitleHeading">Edit activity</h2></div><button class="icon-button" type="button" data-close-modal aria-label="Close">×</button></div><form action="actions/activity-edit.php" method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="id" id="editActivityId"><label>Activity name<input name="title" id="editActivityTitle" maxlength="160" required></label><div class="form-row"><label>Category<select name="category" id="editActivityCategory"><option>Personal</option><option>Work</option><option>Fitness</option><option>Gaming</option><option>Entertainment</option><option>Rest</option><option>Errands</option></select></label><label>Date<input type="date" name="activity_date" id="editActivityDate" required></label></div><div class="form-row"><label>Start time<input type="time" name="start_time" id="editActivityStart" required></label><label>End time<input type="time" name="end_time" id="editActivityEnd"></label></div><label>Notes<textarea name="notes" id="editActivityNotes" rows="3" maxlength="1000"></textarea></label><div class="modal-actions"><button class="btn btn-outline" type="button" data-close-modal>Cancel</button><button class="btn btn-gold" type="submit">Save changes</button></div></form></section></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
