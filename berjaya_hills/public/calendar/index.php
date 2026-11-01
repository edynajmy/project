<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$pageTitle = 'Event Calendar';

$now = new DateTimeImmutable('now');
$currentYear = (int)$now->format('Y');

$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)$now->format('n');
$year  = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$view  = $_GET['view'] ?? 'month';
$view  = in_array($view, ['month','week','day','list','timeline'], true) ? $view : 'month';

$dateWasExplicit = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['date']);
$selectedDate = $dateWasExplicit ? (string)$_GET['date'] : $now->format('Y-m-d');
$selectedTs = strtotime($selectedDate) ?: $now->getTimestamp();
$selectedDate = date('Y-m-d', $selectedTs);
// Keep the selected-day panel inside the month being browsed. Previously a stale
// date query could leave September selected while the detail panel showed October 3.
if (!$dateWasExplicit || (int)date('n', $selectedTs) !== $month || (int)date('Y', $selectedTs) !== $year) {
    $selectedDate = sprintf('%04d-%02d-01', $year, $month);
}

$categoryId = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : null;
$venueId = isset($_GET['venue']) && $_GET['venue'] !== '' ? (int)$_GET['venue'] : null;
$organizerId = isset($_GET['organizer']) && $_GET['organizer'] !== '' ? (int)$_GET['organizer'] : null;
$period = $_GET['period'] ?? 'all';
$period = in_array($period, ['all','upcoming','past'], true) ? $period : 'all';

$viewer = current_user();
$viewerId = $viewer ? (int)$viewer['id'] : 0;
$visibilitySql = $viewerId > 0
    ? "(e.status IN ('confirmed','completed') OR e.owner_user_id=?)"
    : "e.status IN ('confirmed','completed')";
$visibleParams = $viewerId > 0 ? [$viewerId] : [];

$categories = db()->query("SELECT id, name, color_hex FROM event_categories ORDER BY name")->fetchAll();
$venues = db()->query("SELECT id, name FROM venues WHERE status <> 'maintenance' ORDER BY name")->fetchAll();
$organizers = db()->prepare("SELECT DISTINCT u.id, u.full_name FROM users u INNER JOIN events e ON e.owner_user_id = u.id WHERE $visibilitySql AND e.status IN ('pending','confirmed','completed') AND u.full_name <> '' ORDER BY u.full_name");
$organizers->execute($visibleParams);
$organizers = $organizers->fetchAll();

// Show every confirmed or completed event in the public event calendar, whether past or upcoming.
// Pending submissions remain visible only to their owner; drafts/rejected/cancelled records stay excluded.
$totalStmt = db()->prepare("SELECT COUNT(DISTINCT e.id) total,
    COUNT(DISTINCT CASE WHEN DATE(e.end_at)<CURDATE() THEN e.id END) past_total,
    COUNT(DISTINCT CASE WHEN DATE(e.start_at)>CURDATE() THEN e.id END) upcoming_total,
    COUNT(DISTINCT CASE WHEN DATE(e.start_at)<=CURDATE() AND DATE(e.end_at)>=CURDATE() THEN e.id END) ongoing_total
    FROM events e
    LEFT JOIN bookings b ON b.event_id=e.id
    WHERE $visibilitySql
      AND (e.status IN ('confirmed','completed')
        OR (e.owner_user_id=? AND b.status IN ('pending_review','pending_info','confirmed','completed')))");
$totalStmt->execute(array_merge($visibleParams,[$viewerId]));
$eventTotals=$totalStmt->fetch() ?: ['total'=>0,'past_total'=>0,'upcoming_total'=>0,'ongoing_total'=>0];

$yearStmt=db()->prepare("SELECT DISTINCT YEAR(e.start_at) event_year FROM events e LEFT JOIN bookings b ON b.event_id=e.id
    WHERE $visibilitySql AND (e.status IN ('confirmed','completed')
    OR (e.owner_user_id=? AND b.status IN ('pending_review','pending_info','confirmed','completed'))) ORDER BY event_year DESC");
$yearStmt->execute(array_merge($visibleParams,[$viewerId]));
$yearRows=$yearStmt->fetchAll();
$years = array_values(array_unique(array_merge([$currentYear], array_map(fn($r)=>(int)$r['event_year'], $yearRows))));
rsort($years);
if (!in_array($year, $years, true)) $years[] = $year;
rsort($years);

$monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
$monthEnd = new DateTimeImmutable($monthStart->format('Y-m-t 23:59:59'));

if ($view === 'list' || $view === 'timeline') {
    $rangeStart = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year));
    $rangeEnd = new DateTimeImmutable(sprintf('%04d-12-31 23:59:59', $year));
} elseif ($view === 'week') {
    $selectedWeek = new DateTimeImmutable($selectedDate . ' 00:00:00');
    $rangeStart = $selectedWeek->modify('monday this week')->setTime(0,0,0);
    $rangeEnd = $rangeStart->modify('+6 days')->setTime(23,59,59);
} elseif ($view === 'day') {
    // The Day tab still uses the month-grid layout; keep the complete month
    // event set loaded so events on other dates never vanish when a day is selected.
    // The right-hand selected-day panel is filtered separately below.
    $rangeStart = $monthStart;
    $rangeEnd = $monthEnd;
} else {
    $rangeStart = $monthStart;
    $rangeEnd = $monthEnd;
}

$where = [
    $visibilitySql,
    "(e.status IN ('confirmed','completed') OR (e.owner_user_id=? AND b.status IN ('pending_review','pending_info','confirmed','completed')))",
    "e.start_at <= ?",
    "e.end_at >= ?"
];
$params = array_merge($visibleParams,[$viewerId,$rangeEnd->format('Y-m-d H:i:s'),$rangeStart->format('Y-m-d H:i:s')]);

if ($categoryId) { $where[] = 'e.category_id = ?'; $params[] = $categoryId; }
if ($venueId) { $where[] = 'EXISTS (SELECT 1 FROM event_venues evf WHERE evf.event_id=e.id AND evf.venue_id=?)'; $params[] = $venueId; }
if ($organizerId) { $where[] = 'e.owner_user_id = ?'; $params[] = $organizerId; }
if ($period === 'upcoming') {
    $where[] = 'DATE(e.start_at) > CURDATE()';
} elseif ($period === 'past') {
    $where[] = 'DATE(e.end_at) < CURDATE()';
}

$sql = "
SELECT e.id,e.title,e.description,e.start_at,e.end_at,e.expected_pax,e.status,e.is_public,
       COALESCE(b.status,e.status) AS calendar_status,
       c.name category_name,c.color_hex,u.full_name organizer_name,
       e.other_venue_name, GROUP_CONCAT(DISTINCT v.name ORDER BY v.name SEPARATOR ', ') venue_names
FROM events e
LEFT JOIN bookings b ON b.event_id=e.id AND b.user_id=e.owner_user_id
LEFT JOIN event_categories c ON c.id=e.category_id
LEFT JOIN users u ON u.id=e.owner_user_id
LEFT JOIN event_venues ev ON ev.event_id=e.id
LEFT JOIN venues v ON v.id=ev.venue_id
WHERE ".implode(' AND ',$where)."
GROUP BY e.id,e.other_venue_name
ORDER BY e.start_at ".($view === 'month' || $view === 'week' || $view === 'day' ? 'ASC' : 'DESC');

$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$byDay = [];
foreach ($events as $event) {
    $day = (int)date('j', strtotime($event['start_at']));
    $byDay[$day][] = $event;
}

$selectedEvents = array_values(array_filter($events, function($event) use ($selectedDate) {
    $startDate = date('Y-m-d', strtotime($event['start_at']));
    $endDate = date('Y-m-d', strtotime($event['end_at']));
    return $selectedDate >= $startDate && $selectedDate <= $endDate;
}));

$days = (int)$monthStart->format('t');
$today = $now->format('Y-m-d');

$buildUrl = function(array $overrides = []) use ($month,$year,$view,$categoryId,$venueId,$organizerId,$selectedDate,$period) {
    $query = [
        'month'=>$month,'year'=>$year,'view'=>$view,'category'=>$categoryId ?? '',
        'venue'=>$venueId ?? '','organizer'=>$organizerId ?? '',
        'date'=>$selectedDate,'period'=>$period
    ];
    foreach ($overrides as $key=>$value) $query[$key]=$value;
    $query=array_filter($query,static fn($v)=>$v!=='' && $v!==null);
    return url('public/calendar/index.php').'?'.http_build_query($query);
};

$prevTs = $monthStart->modify('-1 month')->getTimestamp();
$nextTs = $monthStart->modify('+1 month')->getTimestamp();
$selectedYearCount = count($events);

function event_is_past(array $event): bool {
    return date('Y-m-d', strtotime($event['end_at'])) < date('Y-m-d');
}
function event_is_upcoming(array $event): bool {
    return date('Y-m-d', strtotime($event['start_at'])) > date('Y-m-d');
}
function event_action_label(array $event): string {
    return event_is_past($event) ? 'View Highlights' : 'View Event';
}

require __DIR__ . '/../../includes/header.php';
?>

<section class="calendar-hero">
  <div class="calendar-hero-inner">
    <div class="eyebrow">Berjaya Hills Resort · Events</div>
    <h1>Event Calendar</h1>
    <p>Discover upcoming experiences, revisit past events and explore the resort's event history in one place.</p>
    <div class="calendar-hero-stats">
      <span><strong><?= (int)$eventTotals['total'] ?></strong> Events recorded</span>
      <span><i></i> <strong><?= (int)$eventTotals['past_total'] ?></strong> Past events</span>
      <span><i></i> <strong><?= (int)$eventTotals['upcoming_total'] ?></strong> Upcoming</span>
      <?php if((int)$eventTotals['ongoing_total']>0): ?><span><i></i> <?= (int)$eventTotals['ongoing_total'] ?> Ongoing now</span><?php endif; ?>
    </div>
  </div>
</section>

<section class="calendar-section">
  <div class="calendar-shell enhanced-calendar-shell">
    <aside class="calendar-sidebar">
      <div class="calendar-sidebar-title"><span class="calendar-sidebar-mark">▣</span><span>Explore events</span><small><?= (int)$eventTotals['total'] ?> event records</small></div>

      <div class="calendar-quick-filter">
        <span class="quick-label">Event status</span>
        <div class="quick-pills">
          <a class="<?= $period==='all'?'active':'' ?>" href="<?= e($buildUrl(['period'=>'all','view'=>$view])) ?>">All</a>
          <a class="<?= $period==='upcoming'?'active':'' ?>" href="<?= e($buildUrl(['period'=>'upcoming','view'=>$view])) ?>">Upcoming</a>
          <a class="<?= $period==='past'?'active':'' ?>" href="<?= e($buildUrl(['period'=>'past','view'=>$view])) ?>">Past</a>
        </div>
      </div>

      <div class="year-picker">
        <label for="calendar-year">Archive year</label>
        <select id="calendar-year" onchange="window.location.href='<?= e($buildUrl()) ?>&year='+this.value+'&month=1&date='+this.value+'-01-01'">
          <?php foreach($years as $yr): ?>
            <option value="<?= $yr ?>" <?= $yr===$year?'selected':'' ?>><?= $yr ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mini-calendar">
        <div class="mini-head">
          <a href="<?= e($buildUrl(['month'=>(int)date('n',$prevTs),'year'=>(int)date('Y',$prevTs),'date'=>date('Y-m-01',$prevTs)])) ?>">‹</a>
          <strong><?= e($monthStart->format('F Y')) ?></strong>
          <a href="<?= e($buildUrl(['month'=>(int)date('n',$nextTs),'year'=>(int)date('Y',$nextTs),'date'=>date('Y-m-01',$nextTs)])) ?>">›</a>
        </div>
        <div class="mini-weekdays"><?php foreach(['S','M','T','W','T','F','S'] as $d): ?><span><?= $d ?></span><?php endforeach; ?></div>
        <div class="mini-days">
          <?php
          $miniFirst=(int)$monthStart->format('w');
          for($i=0;$i<$miniFirst;$i++) echo '<span></span>';
          for($d=1;$d<=$days;$d++):
            $miniDate=$monthStart->format('Y-m-').str_pad((string)$d,2,'0',STR_PAD_LEFT);
            $classes=[];
            if($miniDate===$selectedDate)$classes[]='selected';
            if($miniDate===$today)$classes[]='today';
            if(!empty($byDay[$d]))$classes[]='has-event';
          ?>
            <a class="<?= implode(' ',$classes) ?>" href="<?= e($buildUrl(['date'=>$miniDate,'view'=>'day'])) ?>"><?= $d ?></a>
          <?php endfor; ?>
        </div>
      </div>

      <div class="filter-heading">
        <span>⌕ &nbsp; More filters</span>
        <a href="<?= e(url('public/calendar/index.php')) ?>">Reset</a>
      </div>

      <form method="get" action="<?= e(url('public/calendar/index.php')) ?>" class="calendar-filters">
        <input type="hidden" name="month" value="<?= $month ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <input type="hidden" name="date" value="<?= e($selectedDate) ?>">
        <input type="hidden" name="period" value="<?= e($period) ?>">

        <details class="filter-dropdown" <?= $categoryId?'open':'' ?>>
          <summary><span><small>Event Category</small><b><?= $categoryId ? e(next(array_column(array_filter($categories,fn($c)=>(int)$c['id']===$categoryId),'name')) ?: 'Selected') : 'All Categories' ?></b></span><i>⌄</i></summary>
          <div class="filter-options">
            <label><input type="radio" name="category" value="" <?= !$categoryId?'checked':'' ?>> All Categories</label>
            <?php foreach($categories as $category): ?><label><input type="radio" name="category" value="<?= (int)$category['id'] ?>" <?= $categoryId===(int)$category['id']?'checked':'' ?>> <?= e($category['name']) ?></label><?php endforeach; ?>
          </div>
        </details>

        <details class="filter-dropdown" <?= $venueId?'open':'' ?>>
          <summary><span><small>Venue</small><b><?= $venueId?'Selected Venue':'All Venues' ?></b></span><i>⌄</i></summary>
          <div class="filter-options">
            <label><input type="radio" name="venue" value="" <?= !$venueId?'checked':'' ?>> All Venues</label>
            <?php foreach($venues as $venue): ?><label><input type="radio" name="venue" value="<?= (int)$venue['id'] ?>" <?= $venueId===(int)$venue['id']?'checked':'' ?>> <?= e($venue['name']) ?></label><?php endforeach; ?>
          </div>
        </details>

        <details class="filter-dropdown" <?= $organizerId?'open':'' ?>>
          <summary><span><small>Organized By</small><b><?= $organizerId?'Selected Organizer':'All Organizers' ?></b></span><i>⌄</i></summary>
          <div class="filter-options">
            <label><input type="radio" name="organizer" value="" <?= !$organizerId?'checked':'' ?>> All Organizers</label>
            <?php foreach($organizers as $organizer): ?><label><input type="radio" name="organizer" value="<?= (int)$organizer['id'] ?>" <?= $organizerId===(int)$organizer['id']?'checked':'' ?>> <?= e($organizer['full_name']) ?></label><?php endforeach; ?>
          </div>
        </details>

        <button class="filter-apply" type="submit">Apply Filters</button>
      </form>

      <div class="archive-tip">
        <strong>Event history & upcoming plans</strong>
        <span>Browse saved public events by year, status, category or venue. Drafts and private requests are not shown publicly.</span>
        <a href="<?= e($buildUrl(['view'=>'timeline','period'=>'all','year'=>$currentYear])) ?>">Explore event timeline →</a>
      </div>
    </aside>

    <main class="calendar-main">
      <div class="calendar-toolbar">
        <div class="calendar-nav">
          <a href="<?= e($buildUrl(['month'=>(int)date('n',$prevTs),'year'=>(int)date('Y',$prevTs),'date'=>date('Y-m-01',$prevTs)])) ?>">←</a>
          <a href="<?= e($buildUrl(['month'=>(int)date('n',$nextTs),'year'=>(int)date('Y',$nextTs),'date'=>date('Y-m-01',$nextTs)])) ?>">→</a>
          <h2><?= e($view==='list'||$view==='timeline' ? (string)$year : $monthStart->format('F Y')) ?></h2>
        </div>
        <div class="view-switcher">
          <?php foreach(['month'=>'Month','week'=>'Week','day'=>'Day','list'=>'List','timeline'=>'Timeline'] as $key=>$label): ?>
            <a class="<?= $view===$key?'active':'' ?>" href="<?= e($buildUrl(['view'=>$key])) ?>"><?= $label ?></a>
          <?php endforeach; ?>
          <?php if (has_role('Administrator','Management','Event')): ?>
            <a class="add-event" href="<?= e(url('public/auth/login.php')) ?>">＋ Add Event</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if($view==='list' || $view==='timeline'): ?>
        <div class="archive-intro">
          <div><span class="eyebrow"><?= $view==='timeline'?'Year timeline':'Event archive' ?></span><h3><?= $period==='past'?'Past events':'Events in '.$year ?></h3></div>
          <span class="archive-count"><?= count($events) ?> record<?= count($events)===1?'':'s' ?></span>
        </div>

        <?php if(!$events): ?>
          <div class="archive-empty"><div class="empty-icon">◌</div><h3>No events found</h3><p>Try another year or switch between Upcoming and Past.</p></div>
        <?php elseif($view==='timeline'): ?>
          <div class="event-timeline" data-timeline>
            <?php foreach($events as $i=>$event): $past=event_is_past($event); ?>
              <article class="timeline-item <?= $past?'is-past':'is-future' ?>" data-index="<?= $i ?>">
                <div class="timeline-date"><strong><?= e(date('d',strtotime($event['start_at']))) ?></strong><span><?= e(date('M Y',strtotime($event['start_at']))) ?></span></div>
                <div class="timeline-line"></div>
                <div class="timeline-card">
                  <div class="timeline-card-top"><span class="status-chip <?= $past?'past':'upcoming' ?>"><?= $past?'Past event':'Upcoming' ?></span><span><?= e($event['category_name']?:'Event') ?></span></div>
                  <h3><?= e($event['title']) ?></h3>
                  <p><?= e($event['description'] ?: 'Discover the event details, schedule and venue information.') ?></p>
                  <div class="timeline-meta"><span>◷ <?= e(date('d M Y · H:i',strtotime($event['start_at']))) ?></span><span>⌖ <?= e($event['other_venue_name'] ?: ($event['venue_names']?:'Venue TBA')) ?></span></div>
                  <div class="timeline-actions">
                    <a class="btn <?= $past?'btn-outline-dark':'btn-gold' ?>" href="<?= e(url('public/events/view.php?id='.$event['id'])) ?>"><?= e(event_action_label($event)) ?></a>
                    <?php if(!$past): ?><button class="btn btn-soft remind-btn" type="button" data-event-id="<?= (int)$event['id'] ?>" data-event-title="<?= e($event['title']) ?>">♡ Remind me</button><?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <div class="timeline-more" data-timeline-more>Scroll to load more events</div>
        <?php else: ?>
          <div class="event-list">
            <?php foreach($events as $event): $past=event_is_past($event); ?>
              <article class="archive-card <?= $past?'is-past':'is-future' ?>">
                <div class="archive-date"><strong><?= e(date('d',strtotime($event['start_at']))) ?></strong><span><?= e(date('M',strtotime($event['start_at']))) ?></span></div>
                <div class="archive-content">
                  <div class="archive-card-top"><span class="status-chip <?= $past?'past':'upcoming' ?>"><?= $past?'Past event':'Upcoming' ?></span><span><?= e($event['category_name']?:'Event') ?></span></div>
                  <h3><?= e($event['title']) ?></h3>
                  <p><?= e($event['description'] ?: 'Explore event details and venue information.') ?></p>
                  <div class="archive-meta"><span>◷ <?= e(date('d M Y · H:i',strtotime($event['start_at']))) ?></span><span>⌖ <?= e($event['other_venue_name'] ?: ($event['venue_names']?:'Venue TBA')) ?></span><span>♟ <?= number_format((int)$event['expected_pax']) ?> pax</span></div>
                </div>
                <div class="archive-action">
                  <a class="btn <?= $past?'btn-outline-dark':'btn-gold' ?>" href="<?= e(url('public/events/view.php?id='.$event['id'])) ?>"><?= e(event_action_label($event)) ?></a>
                  <?php if(!$past): ?><button class="btn btn-soft remind-btn" type="button" data-event-id="<?= (int)$event['id'] ?>" data-event-title="<?= e($event['title']) ?>">♡ Remind me</button><?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="calendar-grid-premium">
          <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?><div class="cal-head"><?= $d ?></div><?php endforeach; ?>
          <?php
          $offset=(int)$monthStart->format('w');
          for($i=0;$i<$offset;$i++): ?><div class="cal-cell empty"></div><?php endfor; ?>
          <?php for($d=1;$d<=$days;$d++):
            $cellDate=sprintf('%04d-%02d-%02d',$year,$month,$d);
            $classes=['cal-cell'];
            if($cellDate===$selectedDate)$classes[]='selected';
            if($cellDate===$today)$classes[]='today';
          ?>
            <div class="<?= implode(' ',$classes) ?>">
              <a class="day-number" href="<?= e($buildUrl(['date'=>$cellDate,'view'=>'day'])) ?>"><?= $d ?></a>
              <div class="cell-events">
                <?php foreach(array_slice($byDay[$d]??[],0,4) as $event): $past=event_is_past($event); ?>
                  <a class="calendar-event <?= $past?'past-event':'future-event' ?>" style="--event-color:<?= e($event['color_hex']?:'#4d7860') ?>" href="<?= e(url('public/events/view.php?id='.$event['id'])) ?>">
                    <span><?= e(date('H:i',strtotime($event['start_at']))) ?></span><b><?= e($event['title']) ?></b>
                    <small><?= $past?'View highlights':'View event' ?></small>
                  </a>
                <?php endforeach; ?>
                <?php if(count($byDay[$d]??[])>4): ?><span class="more-events">+<?= count($byDay[$d])-4 ?> more</span><?php endif; ?>
              </div>
            </div>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </main>

    <aside class="day-panel">
      <div class="day-panel-head">
        <div>
          <span class="eyebrow">Selected day</span>
          <h2><?= e(date('l, d F Y',$selectedTs)) ?></h2>
          <span><?= count($selectedEvents) ?> Event<?= count($selectedEvents)===1?'':'s' ?> Scheduled</span>
        </div>
      </div>
      <div class="day-events">
        <?php if(!$selectedEvents): ?>
          <div class="empty-day"><div class="empty-ring">○</div><h3>No events scheduled</h3><p>Try another date or browse the archive using List or Timeline.</p></div>
        <?php else: foreach($selectedEvents as $event): $past=event_is_past($event); ?>
          <a class="event-detail-card <?= $past?'is-past':'' ?>" href="<?= e(url('public/events/view.php?id='.$event['id'])) ?>">
            <div class="event-thumb <?= $past?'thumb-past':'' ?>" style="--event-color:<?= e($event['color_hex']?:'#4d7860') ?>"><span><?= $past?'PAST':'NEXT' ?></span></div>
            <div class="event-detail-body">
              <span class="event-category"><?= e($event['category_name']?:'Event') ?></span>
              <h3><?= e($event['title']) ?></h3>
              <p>◷ <?= e(date('h:i A',strtotime($event['start_at']))) ?> – <?= e(date('h:i A',strtotime($event['end_at']))) ?></p>
              <p>⌖ <?= e($event['other_venue_name'] ?: ($event['venue_names']?:'Venue TBA')) ?></p>
              <strong class="side-action"><?= $past?'View Highlights →':'View Event →' ?></strong>
            </div>
          </a>
        <?php endforeach; endif; ?>
      </div>
      <a class="day-panel-button" href="<?= e($buildUrl(['view'=>'list','date'=>$selectedDate])) ?>">Open <?= e($year) ?> archive →</a>
    </aside>
  </div>
</section>

<script>
(function(){
  const key='bh-event-reminders';
  const saved=JSON.parse(localStorage.getItem(key)||'{}');
  document.querySelectorAll('.remind-btn').forEach(btn=>{
    const id=btn.dataset.eventId;
    if(saved[id]){btn.classList.add('saved');btn.textContent='✓ Reminder saved';}
    btn.addEventListener('click',()=>{
      if(saved[id]){delete saved[id];btn.classList.remove('saved');btn.textContent='♡ Remind me';}
      else{saved[id]={title:btn.dataset.eventTitle,savedAt:new Date().toISOString()};btn.classList.add('saved');btn.textContent='✓ Reminder saved';}
      localStorage.setItem(key,JSON.stringify(saved));
    });
  });
  const items=[...document.querySelectorAll('[data-timeline] .timeline-item')];
  if(items.length>8){
    items.forEach((el,i)=>{if(i>=8)el.classList.add('timeline-hidden');});
    const more=document.querySelector('[data-timeline-more]');
    let shown=8;
    const reveal=()=>{
      let next=0;
      for(let i=shown;i<items.length && next<6;i++,next++)items[i].classList.remove('timeline-hidden');
      shown=Math.min(items.length,shown+6);
      if(shown>=items.length && more)more.textContent='You have reached the end of this archive.';
    };
    const io=new IntersectionObserver(es=>{if(es.some(e=>e.isIntersecting)&&shown<items.length)reveal();},{rootMargin:'300px'});
    if(more)io.observe(more);
  }
})();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
