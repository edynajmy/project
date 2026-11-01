<?php
require_once __DIR__.'/../../includes/bootstrap.php';

$id=(int)($_GET['id']??0);
$s=db()->prepare("SELECT v.*,l.name location_name FROM venues v LEFT JOIN locations l ON l.id=v.location_id WHERE v.id=?");
$s->execute([$id]);
$v=$s->fetch();
if(!$v){http_response_code(404);exit('Venue not found');}

$f=db()->prepare('SELECT f.name,vf.quantity,f.unit_rate FROM venue_facilities vf JOIN facilities f ON f.id=vf.facility_id WHERE vf.venue_id=? ORDER BY f.name');
$f->execute([$id]);
$fac=$f->fetchAll();

$imgStmt=db()->prepare('SELECT * FROM venue_images WHERE venue_id=? ORDER BY is_primary DESC,sort_order ASC,id ASC');
$imgStmt->execute([$id]);
$venueImages=$imgStmt->fetchAll();
$gallery=[];
foreach($venueImages as $vi){
    $u=venue_image_url($vi['image_path']??null,$v['name']??null);
    if($u) $gallery[]=['url'=>$u,'alt'=>$vi['alt_text']??$v['name']];
}
if(!$gallery){
    $fallback=venue_image_url(null,$v['name']??null);
    if($fallback) $gallery[]=['url'=>$fallback,'alt'=>$v['name']];
}

/* Venue-specific activity cards. These are intentionally limited to activities supported by the current project content. */
$activityMap=[
    'berjaya hills adventure park'=>[
        ['title'=>'Flying Fox','icon'=>'↗','text'=>'Glide across the hillside on a high-adrenaline zipline experience.'],
        ['title'=>'Rock Climbing','icon'=>'◈','text'=>'Challenge yourself with climbing activities in the adventure area.'],
        ['title'=>'Rope Challenges','icon'=>'◎','text'=>'Test balance, coordination and confidence across rope elements.'],
    ],
    'berjaya hills horse trails'=>[
        ['title'=>'Horse Riding','icon'=>'♞','text'=>'Enjoy a guided riding experience through the highland surroundings.'],
        ['title'=>'Trail Ride','icon'=>'⌁','text'=>'Explore the natural landscape from the saddle.'],
    ],
    '9d motion adventure'=>[
        ['title'=>'9D Motion Experience','icon'=>'◉','text'=>'Enjoy an immersive motion-based entertainment experience.'],
    ],
    'rabbit park'=>[
        ['title'=>'Rabbit Encounter','icon'=>'♡','text'=>'Spend time with rabbits in a relaxed family-friendly setting.'],
        ['title'=>'Animal Feeding','icon'=>'✦','text'=>'A simple interactive activity for visitors and families.'],
    ],
    'japanese village'=>[
        ['title'=>'Japanese Garden Walk','icon'=>'⌂','text'=>'Explore the landscaped Japanese-style garden and surrounding highland scenery.'],
        ['title'=>'Tea Ceremony','icon'=>'◌','text'=>'Experience a traditional Japanese cultural activity when available.'],
    ],
    'botanical garden'=>[
        ['title'=>'Garden Walk','icon'=>'❧','text'=>'Walk through the highland botanical surroundings and landscaped paths.'],
        ['title'=>'Nature Photography','icon'=>'✦','text'=>'Capture the greenery, pathways and mountain atmosphere.'],
    ],
    'berjaya hills golf & country club'=>[
        ['title'=>'18-Hole Golf','icon'=>'●','text'=>'Enjoy a scenic golf experience within the Berjaya Hills landscape.'],
    ],
];
$activities=$activityMap[strtolower(trim($v['name']??''))]??[];

/* Nearby recommendations from the same Venues catalogue. */
$nearStmt=db()->prepare("SELECT v.id,v.name,v.venue_type,v.description,(SELECT vi.image_path FROM venue_images vi WHERE vi.venue_id=v.id ORDER BY vi.is_primary DESC,vi.sort_order ASC,vi.id ASC LIMIT 1) image_path FROM venues v WHERE v.status='available' AND v.id<>? ORDER BY v.featured DESC,v.name ASC LIMIT 3");
$nearStmt->execute([$id]);
$nearby=$nearStmt->fetchAll();

/* Reviews are optional until the supplied migration is run. */
$reviews=[];$reviewSummary=['count'=>0,'avg'=>0];$reviewsReady=false;
try{
    $chk=db()->query("SHOW TABLES LIKE 'venue_reviews'");
    $reviewsReady=(bool)$chk->fetchColumn();
    if($reviewsReady){
        $rq=db()->prepare("SELECT r.*,u.name user_name FROM venue_reviews r LEFT JOIN users u ON u.id=r.user_id WHERE r.venue_id=? AND r.status='approved' ORDER BY r.created_at DESC LIMIT 6");
        $rq->execute([$id]);$reviews=$rq->fetchAll();
        $rs=db()->prepare("SELECT COUNT(*) cnt, COALESCE(AVG(rating),0) avg_rating FROM venue_reviews WHERE venue_id=? AND status='approved'");
        $rs->execute([$id]);$reviewSummary=$rs->fetch()?:$reviewSummary;
    }
}catch(Throwable $e){$reviewsReady=false;}

$pageTitle=$v['name'];
$isUserVenuePage = strpos(str_replace('\\','/',$_SERVER['SCRIPT_NAME']??''), '/user/venues/') !== false;
require __DIR__.'/../../includes/header.php';
if($isUserVenuePage): ?>
<div class="admin-shell user-shell venue-user-shell"><aside class="sidebar"><?php require __DIR__.'/../../user/sidebar.php'; ?></aside><section class="admin-content user-content venue-user-content">
<?php endif; ?>
<section class="venue-detail-page">
  <div class="venue-detail-container">
    <div class="venue-detail-crumb"><a href="<?= $isUserVenuePage ? url('user/bookings/create.php?step=3&venue_id='.$id) : url('public/venues/index.php') ?>" <?= $isUserVenuePage ? 'onclick="if(document.referrer && new URL(document.referrer).origin===location.origin){history.back();return false;}"' : '' ?>><?= $isUserVenuePage ? '← Back to booking · Continue where you left off' : 'Venues' ?></a><span>›</span><span><?=e($v['name'])?></span></div>

    <section class="venue-story-hero">
      <div class="venue-story-media" id="venue-gallery">
        <?php foreach($gallery as $i=>$g): ?>
          <img class="venue-slide <?= $i===0?'active':'' ?>" src="<?=e($g['url'])?>" alt="<?=e($g['alt'])?>" loading="<?= $i===0?'eager':'lazy' ?>">
        <?php endforeach; ?>
        <?php if(count($gallery)>1): ?>
          <button class="venue-slide-btn prev" type="button" aria-label="Previous photo" onclick="venueSlide(-1)">‹</button>
          <button class="venue-slide-btn next" type="button" aria-label="Next photo" onclick="venueSlide(1)">›</button>
          <div class="venue-slide-dots" aria-label="Photo selector">
            <?php foreach($gallery as $i=>$g): ?><button type="button" class="venue-slide-dot <?= $i===0?'active':'' ?>" onclick="venueGoTo(<?=$i?>)" aria-label="Photo <?=($i+1)?>"></button><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="venue-story-media-caption"><span><?=e($v['venue_type']?:'Berjaya Hills place')?></span><strong><?=count($gallery)?> <?=count($gallery)===1?'photo':'photos'?></strong></div>
      </div>
      <div class="venue-story-intro">
        <div class="eyebrow">Berjaya Hills · <?=e($v['venue_type']?:'Venue')?></div>
        <h1><?=e($v['name'])?></h1>
        <p><?=e($v['description']?:'Discover this Berjaya Hills destination and explore the experience around it.')?></p>
        <div class="venue-story-facts">
          <?php if((int)$v['capacity']>0): ?><div><span>Capacity</span><strong><?=number_format((int)$v['capacity'])?> pax</strong></div><?php endif; ?>
          <div><span>Location</span><strong><?=e($v['location_name']?:'Berjaya Hills')?></strong></div>
          <?php if((float)$v['area_sqft']>0): ?><div><span>Area</span><strong><?=number_format((float)$v['area_sqft'],0)?> sq ft</strong></div><?php endif; ?>
        </div>
        <div class="venue-story-actions">
          <?php if((int)($v['bookable']??1)===1): ?><a class="btn btn-gold" href="<?=url('user/bookings/create.php?venue_id='.$v['id'].'&step=3')?>">Book this venue →</a><?php endif; ?>
          <a class="btn btn-outline-dark" href="#venue-map">View location</a>
        </div>
      </div>
    </section>

    <div class="venue-story-sections">
      <section class="venue-story-section venue-overview-section">
        <div class="section-heading-inline"><div><div class="eyebrow">Discover the place</div><h2>More than a venue.</h2></div><span class="section-rule"></span></div>
        <p class="venue-lead"><?=e($v['description']?:'Explore the surroundings, facilities and activities available at this Berjaya Hills destination.')?></p>
      </section>

      <?php if($fac): ?>
      <section class="venue-story-section">
        <div class="eyebrow">Facilities</div><h2>Everything you need, at a glance.</h2>
        <div class="facility-icon-grid">
          <?php foreach($fac as $x):
            $n=strtolower($x['name']);$icon='✦';
            if(str_contains($n,'parking'))$icon='⌖'; elseif(str_contains($n,'toilet')||str_contains($n,'restroom'))$icon='♧'; elseif(str_contains($n,'food')||str_contains($n,'cafe')||str_contains($n,'restaurant'))$icon='◒'; elseif(str_contains($n,'first')||str_contains($n,'medical'))$icon='+'; elseif(str_contains($n,'wifi')||str_contains($n,'internet'))$icon='⌁'; elseif(str_contains($n,'room')||str_contains($n,'hall'))$icon='▦';
          ?>
            <div class="facility-icon-card"><div class="facility-icon"><?=e($icon)?></div><div><strong><?=e($x['name'])?></strong><?php if((int)$x['quantity']>1): ?><span>×<?=e((string)$x['quantity'])?></span><?php endif; ?></div></div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if($activities): ?>
      <section class="venue-story-section">
        <div class="eyebrow">What to do</div><h2>Activities to enjoy.</h2>
        <div class="activity-story-grid">
          <?php foreach($activities as $a): ?><article class="activity-story-card"><div class="activity-story-icon"><?=e($a['icon'])?></div><h3><?=e($a['title'])?></h3><p><?=e($a['text'])?></p></article><?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="venue-story-section" id="venue-reviews">
        <div class="reviews-head">
          <div><div class="eyebrow">Visitor feedback</div><h2>Reviews &amp; ratings.</h2></div>
          <div class="rating-summary"><strong><?= $reviewSummary['count'] ? number_format((float)$reviewSummary['avg_rating'],1) : '—' ?></strong><span><?= $reviewSummary['count'] ? '★★★★★' : 'No rating yet' ?></span><small><?= (int)$reviewSummary['count'] ?> approved review<?=((int)$reviewSummary['count']===1?'':'s')?></small></div>
        </div>
        <?php if($reviews): ?><div class="review-grid"><?php foreach($reviews as $r): ?><article class="review-card"><div class="review-stars"><?=str_repeat('★',(int)$r['rating'])?><?=str_repeat('☆',5-(int)$r['rating'])?></div><p>“<?=e($r['review_text'])?>”</p><strong><?=e($r['user_name']?:'Visitor')?></strong><small><?=e(date('d M Y',strtotime($r['created_at'])))?></small></article><?php endforeach; ?></div>
        <?php elseif(!$reviewsReady): ?><div class="review-empty"><strong>Reviews are ready for this venue.</strong><p>Run the included venue reviews migration to enable visitor ratings and review submissions.</p></div>
        <?php else: ?><div class="review-empty"><strong>No reviews yet.</strong><p>Be the first visitor to share your experience.</p></div><?php endif; ?>
      </section>

      <section class="venue-story-section" id="venue-map">
        <div class="eyebrow">Find your way</div><h2>Location &amp; directions.</h2>
        <div class="venue-map-card"><iframe title="Map showing <?=e($v['name'])?>" loading="lazy" src="https://www.google.com/maps?q=<?=rawurlencode($v['name'].' Berjaya Hills Pahang') ?>&output=embed"></iframe><div class="venue-map-overlay"><strong><?=e($v['name'])?></strong><span><?=e($v['location_name']?:'Berjaya Hills, Pahang')?></span><a class="btn btn-gold btn-sm" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?=rawurlencode($v['name'].' Berjaya Hills Pahang')?>">Open in Google Maps ↗</a></div></div>
      </section>

      <?php if((int)($v['bookable']??1)!==1): ?>
      <section class="venue-story-section venue-info-banner"><div class="info-banner-icon">✓</div><div><div class="eyebrow">Visitor information</div><h3>Perfect for a relaxed visit &amp; exploration.</h3><p>This place is available for discovery and leisure. No event slot booking is required.</p></div></section>
      <?php endif; ?>

      <?php if($nearby): ?>
      <section class="venue-story-section">
        <div class="eyebrow">Keep exploring</div><h2>Places you may also like.</h2>
        <div class="nearby-story-grid">
          <?php foreach($nearby as $n): $nu=venue_image_url($n['image_path']??null,$n['name']??null); ?><a class="nearby-story-card" href="<?=url(($isUserVenuePage?'user/venues/view.php?id=':'public/venues/view.php?id=').$n['id'])?>"><div class="nearby-story-image" <?= $nu?'style="background-image:url(\''.e($nu).'\')"':'' ?>></div><div class="nearby-story-body"><span><?=e($n['venue_type']?:'Berjaya Hills place')?></span><h3><?=e($n['name'])?></h3><p><?=e(mb_strimwidth($n['description']??'',0,100,'...'))?></p><b>Explore →</b></div></a><?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php if($isUserVenuePage): ?></section></div><?php endif; ?>

<script>
(function(){
  let venueCurrent=0;
  const slides=[...document.querySelectorAll('.venue-slide')],dots=[...document.querySelectorAll('.venue-slide-dot')];
  window.venueGoTo=function(i){if(!slides.length)return;venueCurrent=(i+slides.length)%slides.length;slides.forEach((s,n)=>s.classList.toggle('active',n===venueCurrent));dots.forEach((d,n)=>d.classList.toggle('active',n===venueCurrent));};
  window.venueSlide=function(delta){venueGoTo(venueCurrent+delta);};
  if(slides.length>1){setInterval(()=>venueSlide(1),6500);}
})();
</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
