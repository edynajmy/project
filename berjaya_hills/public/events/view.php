<?php
require_once __DIR__.'/../../includes/bootstrap.php';

$id=(int)($_GET['id']??0);
$viewer=current_user();
$viewerId=$viewer?(int)$viewer['id']:0;
$s=db()->prepare('SELECT e.id,e.title,e.description,e.start_at,e.end_at,e.expected_pax,e.other_venue_name,e.status,e.is_public,e.owner_user_id,
           COALESCE(b.status,e.status) AS booking_status,
           c.name AS category_name,c.color_hex,
           GROUP_CONCAT(DISTINCT v.name ORDER BY v.name SEPARATOR ", ") AS venue_names
    FROM events e
    LEFT JOIN bookings b ON b.event_id=e.id AND b.user_id=e.owner_user_id
    LEFT JOIN event_categories c ON c.id=e.category_id
    LEFT JOIN event_venues ev ON ev.event_id=e.id
    LEFT JOIN venues v ON v.id=ev.venue_id
    WHERE e.id=?
      AND (e.status IN ("confirmed","completed")
           OR (e.owner_user_id=? AND b.status IN ("pending_review","pending_info","confirmed","completed")))
    GROUP BY e.id');
$s->execute([$id,$viewerId]);
$ev=$s->fetch();
if(!$ev){http_response_code(404);exit('Event not found.');}

$past = strtotime($ev['end_at']) < time();
$pageTitle=$ev['title'];

$albums=[];
$galleryImages=[];
if ($past) {
    $g = db()->prepare('
        SELECT a.id,a.title,a.description,a.cover_image
        FROM event_gallery eg
        INNER JOIN gallery_albums a ON a.id=eg.gallery_id
        WHERE eg.event_id=? AND a.is_public=1
        ORDER BY a.created_at DESC
    ');
    $g->execute([$id]);
    $albums=$g->fetchAll();

    if ($albums) {
        $placeholders=implode(',',array_fill(0,count($albums),'?'));
        $ids=array_map(fn($a)=>(int)$a['id'],$albums);
        $ig=db()->prepare("SELECT album_id,image_path,caption FROM gallery_images WHERE album_id IN ($placeholders) ORDER BY sort_order ASC,id ASC");
        $ig->execute($ids);
        $galleryImages=$ig->fetchAll();
    }
}

$testimonials=[];
if ($past) {
    try {
        $ts=db()->prepare('SELECT guest_name,quote FROM event_testimonials WHERE event_id=? AND is_public=1 ORDER BY created_at DESC LIMIT 3');
        $ts->execute([$id]);
        $testimonials=$ts->fetchAll();
    } catch (Throwable $ignore) {
        $testimonials=[];
    }
}
$shareUrl = url('public/events/view.php?id='.$id);
require __DIR__.'/../../includes/header.php';
?>
<section class="event-view-hero <?= $past?'recap-hero':'upcoming-hero' ?>">
  <div class="container">
    <div class="eyebrow"><?= e($ev['category_name']??'Event') ?> · <?= $past?'Event recap':'Upcoming event' ?></div>
    <h1 class="page-title"><?= e($ev['title']) ?></h1>
    <p><?= e(date('d M Y · H:i',strtotime($ev['start_at']))) ?> — <?= e(date('H:i',strtotime($ev['end_at']))) ?> · <?= e($ev['other_venue_name'] ?: ($ev['venue_names']??'Resort venue')) ?></p>
    <div class="event-hero-actions">
      <span class="status-chip <?= $past?'past':'upcoming' ?>"><?= $past?'Past event':'Upcoming' ?></span>
      <button class="btn btn-share" type="button" data-share-url="<?= e($shareUrl) ?>" data-share-title="<?= e($ev['title']) ?>">↗ Share</button>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php if($past): ?>
      <div class="recap-heading">
        <div>
          <div class="eyebrow">Post-event recap</div>
          <h2>Relive the occasion.</h2>
          <p>Explore the published highlights, event details and shared moments from this event.</p>
        </div>
        <a class="btn btn-outline-dark" href="<?= url('public/gallery/index.php') ?>">Open gallery</a>
      </div>

      <?php if($galleryImages): ?>
        <div class="recap-gallery">
          <?php foreach($galleryImages as $img): ?>
            <figure>
              <img src="<?= e(preg_match('#^https?://#i',$img['image_path'])?$img['image_path']:url(ltrim($img['image_path'],'/'))) ?>" alt="<?= e($img['caption']?:$ev['title']) ?>">
              <?php if(!empty($img['caption'])): ?><figcaption><?= e($img['caption']) ?></figcaption><?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="recap-empty">
          <div class="recap-empty-icon">◫</div>
          <div><h3>Highlights will appear here</h3><p>Once a public gallery is linked to this event, its photos and videos will automatically appear on this recap page.</p></div>
          <a class="btn btn-gold" href="<?= url('public/gallery/index.php') ?>">Explore gallery</a>
        </div>
      <?php endif; ?>

      <div class="recap-grid">
        <article class="panel recap-info">
          <div class="eyebrow">Event story</div>
          <h2><?= e($ev['title']) ?></h2>
          <p><?= e($ev['description'] ?: 'This event has concluded. The event record remains available as part of the resort archive.') ?></p>
          <div class="stats">
            <div class="stat"><small>Date</small><strong><?= e(date('d M Y',strtotime($ev['start_at']))) ?></strong></div>
            <div class="stat"><small>Venue</small><strong><?= e($ev['other_venue_name'] ?: ($ev['venue_names']?:'TBA')) ?></strong></div>
            <div class="stat"><small>Attendance</small><strong><?= number_format((int)$ev['expected_pax']) ?> pax</strong></div>
          </div>
        </article>
        <aside class="panel guest-voice">
          <div class="eyebrow">Guest voice</div>
          <h3>What guests said.</h3>
          <?php if($testimonials): ?>
            <?php foreach($testimonials as $t): ?>
              <blockquote>“<?= e($t['quote']) ?>”<cite>— <?= e($t['guest_name']) ?></cite></blockquote>
            <?php endforeach; ?>
          <?php else: ?>
            <p>Guest testimonials can be published here when feedback has been collected and approved by the resort team.</p>
            <span class="feedback-note">Only approved testimonials should appear publicly.</span>
          <?php endif; ?>
        </aside>
      </div>
    <?php else: ?>
      <div class="split">
        <div class="panel">
          <div class="eyebrow">Event information</div>
          <h2 style="font-size:38px"><?= e($ev['title']) ?></h2>
          <p><?= e($ev['description'] ?: 'This public event is published through the resort event calendar. Venue and operational details are managed through the event management workflow.') ?></p>
          <div class="stats">
            <div class="stat"><small>Category</small><strong style="font-size:20px"><?= e($ev['category_name']??'Event') ?></strong></div>
            <div class="stat"><small>Venue</small><strong style="font-size:20px"><?= e($ev['other_venue_name'] ?: ($ev['venue_names']??'TBA')) ?></strong></div>
            <div class="stat"><small>Expected guests</small><strong style="font-size:20px"><?= number_format((int)$ev['expected_pax']) ?></strong></div>
          </div>
        </div>
        <aside class="panel">
          <div class="eyebrow">Planning</div>
          <h3 style="font:600 32px 'Cormorant Garamond';color:var(--forest)">Interested in hosting your own event?</h3>
          <p class="kicker">Explore available venues and submit an account request to begin the booking workflow.</p>
          <a class="btn btn-gold" href="<?= url('public/venues/index.php') ?>">Explore venues</a>
        </aside>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
(function(){
 const btn=document.querySelector('[data-share-url]');
 if(!btn)return;
 btn.addEventListener('click',async()=>{
   const url=btn.dataset.shareUrl,title=btn.dataset.shareTitle;
   if(navigator.share){try{await navigator.share({title,url,text:title});return;}catch(e){}}
   const options=[
    ['WhatsApp','https://wa.me/?text='+encodeURIComponent(title+' '+url)],
    ['Facebook','https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(url)],
    ['TikTok','https://www.tiktok.com/upload']
   ];
   const pick=prompt('Share via: 1 WhatsApp  2 Facebook  3 TikTok\\nEnter 1, 2 or 3');
   const item=options[Number(pick)-1];
   if(item)window.open(item[1],'_blank','noopener');
 });
})();
</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
