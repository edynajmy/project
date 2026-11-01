<?php
require_once __DIR__.'/includes/bootstrap.php';
$rootUser=current_user();
if($rootUser && !has_role('Administrator','Management','Event','HR','Author','IT')) { redirect('user/dashboard/index.php'); }
if($rootUser && has_role('Administrator','Management','Event','HR','Author','IT')) { redirect('admin/dashboard/index.php'); }

$pageTitle='Exceptional venues for memorable events';
$events=db()->query("SELECT * FROM vw_upcoming_public_events ORDER BY start_at LIMIT 3")->fetchAll();
$venues=db()->query("SELECT v.*, (SELECT image_path FROM venue_images vi WHERE vi.venue_id=v.id AND vi.is_primary=1 ORDER BY vi.sort_order,id LIMIT 1) image FROM venues v WHERE v.status='available' ORDER BY featured DESC,name LIMIT 8")->fetchAll();

$heroSlides = [
    ['image'=>url('assets/hero/resort-hero.jpg'),'label'=>'Berjaya Hills Resort at sunset'],
    ['image'=>url('assets/hero/golf-course-hero.jpg'),'label'=>'Highland golf course'],
    ['image'=>url('assets/hero/japanese-garden-hero.jpg'),'label'=>'Japanese garden escape'],
    ['image'=>url('assets/hero/equestrian-stables-hero.jpg'),'label'=>'Equestrian experiences'],
    ['image'=>url('assets/hero/colmar-tropicale-hero.jpg'),'label'=>'Colmar Tropicale'],
];

$approvedReviews=[];
$reviewStats=['count'=>0,'average'=>0];
try {
    $reviewStats = db()->query("SELECT COUNT(*) count, COALESCE(ROUND(AVG(rating),1),0) average FROM venue_reviews WHERE status='approved'")->fetch() ?: $reviewStats;
    $approvedReviews = db()->query("SELECT vr.*, v.name venue_name FROM venue_reviews vr JOIN venues v ON v.id=vr.venue_id WHERE vr.status='approved' ORDER BY vr.created_at DESC LIMIT 3")->fetchAll();
} catch (Throwable $e) {
    // Reviews are optional until the review table is installed.
}

$placeCount=(int)(db()->query("SELECT COUNT(*) FROM venues WHERE status <> 'unavailable'")->fetchColumn() ?: 0);
if($placeCount < 17) $placeCount=17;
?>
<?php require __DIR__.'/includes/header.php'; ?>

<section class="home-hero-v2" aria-label="Berjaya Hills event introduction">
  <div class="home-hero-slides">
    <?php foreach($heroSlides as $i=>$slide): ?>
      <div class="home-hero-slide <?= $i===0 ? 'is-active' : '' ?>" style="background-image:url('<?=e($slide['image'])?>')"></div>
    <?php endforeach; ?>
  </div>
  <div class="home-hero-overlay"></div>
  <div class="home-hero-inner container">
    <div class="home-hero-copy">
      <div class="eyebrow">Berjaya Hills Resort · Events & Activities</div>
      <h1>Exceptional venues for memorable events.</h1>
      <p>Discover refined spaces, thoughtful hospitality and a seamless way to plan your next corporate gathering, celebration, meeting or private event.</p>
      <div class="hero-actions">
        <a class="btn btn-gold home-cta-primary" href="<?=url('public/venues/index.php')?>">Explore venues <span>→</span></a>
        <a class="btn btn-outline home-cta-secondary" href="<?=url('public/calendar/index.php')?>">View event calendar</a>
      </div>
    </div>
    <aside class="home-hero-card home-fun-fact-card">
      <div class="eyebrow">Did you know?</div>
      <div class="mini-title">A little wonder behind Berjaya Hills.</div>
      <hr>
      <div class="hero-stat"><span>Colmar Tropicale</span><strong>Inspired by Alsace, France</strong></div>
      <div class="hero-stat"><span>Japanese Village</span><strong>A Japanese-style garden escape</strong></div>
      <div class="hero-stat"><span>Setting</span><strong>Highlands of Bukit Tinggi, Pahang</strong></div>
      <p class="home-fun-fact-note">Discover the stories, scenery and experiences that make this destination distinctive.</p>
    </aside>
  </div>
  <div class="home-hero-controls" aria-label="Hero image controls">
    <button type="button" class="home-hero-arrow" data-hero-prev aria-label="Previous image">‹</button>
    <div class="home-hero-dots">
      <?php foreach($heroSlides as $i=>$slide): ?><button type="button" class="home-hero-dot <?= $i===0 ? 'is-active' : '' ?>" data-hero-dot="<?=$i?>" aria-label="Show image <?=($i+1)?>"></button><?php endforeach; ?>
    </div>
    <button type="button" class="home-hero-arrow" data-hero-next aria-label="Next image">›</button>
  </div>
</section>

<section class="home-benefits section">
  <div class="container home-benefit-grid">
    <article><div class="home-benefit-icon">⌂</div><h3>Refreshing Highland Atmosphere</h3><p>A distinctive escape from the city, surrounded by nature.</p></article>
    <article><div class="home-benefit-icon">♧</div><h3>Comprehensive Event Management</h3><p>From planning to execution, the workflow keeps every step organised.</p></article>
    <article><div class="home-benefit-icon">▦</div><h3>Complete Facilities</h3><p>Venues, accommodation, activities and dining within one destination.</p></article>
    <article><div class="home-benefit-icon">♧</div><h3>Versatile Spaces</h3><p>Options for corporate events, weddings, meetings and private celebrations.</p></article>
  </div>
</section>

<section class="section home-venues-section">
  <div class="container">
    <div class="section-head home-section-head">
      <div><div class="eyebrow">Signature spaces</div><h2>Venues designed around the occasion.</h2><p class="section-intro">Browse selected spaces, compare capacities and discover the setting that fits your event.</p></div>
      <a class="btn btn-primary" href="<?=url('public/venues/index.php')?>">View all venues <span>→</span></a>
    </div>
    <div class="home-venue-carousel-wrap">
      <button class="home-carousel-arrow" type="button" data-carousel-prev aria-label="Previous venues">‹</button>
      <div class="home-venue-carousel" data-venue-carousel>
        <?php foreach($venues as $v): $homeVenueImg=venue_image_url($v['image']??null,$v['name']??null); ?>
          <article class="home-venue-card">
            <a class="home-venue-image" href="<?=url('public/venues/view.php?id='.$v['id'])?>" style="<?= $homeVenueImg ? 'background-image:url(\''.e($homeVenueImg).'\');' : '' ?>">
              <span class="home-venue-type"><?=e($v['venue_type'])?></span>
            </a>
            <div class="home-venue-body">
              <div class="home-venue-title-row"><h3><?=e($v['name'])?></h3><a class="home-venue-arrow" href="<?=url('public/venues/view.php?id='.$v['id'])?>" aria-label="View <?=e($v['name'])?>">→</a></div>
              <div class="home-capacity">♧ <span><?=number_format((int)$v['capacity'])?> pax capacity</span></div>
              <p><?=e($v['description'])?></p>
              <a class="btn btn-primary btn-sm" href="<?=url('public/venues/view.php?id='.$v['id'])?>">View venue</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <button class="home-carousel-arrow" type="button" data-carousel-next aria-label="Next venues">›</button>
    </div>
  </div>
</section>

<section class="home-stat-strip">
  <div class="container home-stat-grid">
    <div><strong><?=$placeCount?>+</strong><span>Places & venues available to explore</span></div>
    <div><strong>Live</strong><span>Availability and booking workflow</span></div>
    <div><strong>1</strong><span>Highland destination for events & activities</span></div>
    <div><strong>24/7</strong><span>Online venue discovery</span></div>
  </div>
</section>

<?php if($approvedReviews): ?>
<section class="section home-reviews-section">
  <div class="container">
    <div class="section-head home-section-head">
      <div><div class="eyebrow">Real experiences</div><h2>What our guests say.</h2><p class="section-intro">Reviews shown here are published only after approval in the system.</p></div>
      <div class="home-rating-summary"><strong>★ <?=e((string)$reviewStats['average'])?></strong><span><?=number_format((int)$reviewStats['count'])?> approved reviews</span></div>
    </div>
    <div class="home-review-grid">
      <?php foreach($approvedReviews as $review): ?>
        <article class="home-review-card">
          <div class="home-review-stars"><?=str_repeat('★',(int)$review['rating'])?><?=str_repeat('☆',5-(int)$review['rating'])?></div>
          <p>“<?=e($review['review_text'])?>”</p>
          <strong><?=e($review['venue_name'])?></strong>
          <small>Verified system review</small>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section home-journey-section">
  <div class="container home-journey-grid">
    <div>
      <div class="eyebrow">A complete event journey</div>
      <h2>Plan with clarity. Host with confidence.</h2>
      <p class="section-intro">The system brings venue discovery, availability checks, booking review and confirmation into one clear workflow.</p>
      <a class="btn btn-gold" href="<?=url('public/contact.php')?>">Enquire now →</a>
    </div>
    <div class="home-process-card">
      <div><b>01</b><span><strong>Request access</strong>Submit your company and personal details.</span></div>
      <div><b>02</b><span><strong>Discover</strong>Explore venues, events and facilities.</span></div>
      <div><b>03</b><span><strong>Book</strong>Save a draft and submit it for review.</span></div>
      <div><b>04</b><span><strong>Confirm</strong>Management reviews and communicates the outcome.</span></div>
    </div>
  </div>
</section>

<?php if($events): ?>
<section class="section home-events-section">
  <div class="container">
    <div class="section-head home-section-head"><div><div class="eyebrow">What's happening</div><h2>Upcoming events.</h2></div><a class="btn btn-outline home-light-outline" href="<?=url('public/calendar/index.php')?>">Full calendar</a></div>
    <div class="home-event-grid">
      <?php foreach($events as $ev): ?>
        <article class="home-event-card"><span class="tag"><?=e($ev['category_name']??'Event')?></span><h3><?=e($ev['title'])?></h3><p><?=date('d M Y · H:i',strtotime($ev['start_at']))?> — <?=date('H:i',strtotime($ev['end_at']))?></p><span class="kicker"><?=e($ev['venue_names']??'Resort venue')?></span></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="home-final-cta">
  <div class="home-final-overlay"></div>
  <div class="container home-final-inner">
    <div class="eyebrow">Create your next event</div>
    <h2>Let’s plan something memorable.</h2>
    <p>Explore the resort’s spaces and discover the right setting for your next occasion.</p>
    <div class="hero-actions"><a class="btn btn-gold" href="<?=url('public/contact.php')?>">Enquire now →</a><a class="btn btn-outline" href="<?=url('public/venues/index.php')?>">View venues</a></div>
  </div>
</section>

<script>
(function(){
  const slides=[...document.querySelectorAll('.home-hero-slide')], dots=[...document.querySelectorAll('[data-hero-dot]')];
  let current=0,timer;
  function show(i){ if(!slides.length)return; current=(i+slides.length)%slides.length; slides.forEach((s,n)=>s.classList.toggle('is-active',n===current)); dots.forEach((d,n)=>d.classList.toggle('is-active',n===current)); }
  function restart(){clearInterval(timer);timer=setInterval(()=>show(current+1),6500)}
  document.querySelector('[data-hero-prev]')?.addEventListener('click',()=>{show(current-1);restart()});
  document.querySelector('[data-hero-next]')?.addEventListener('click',()=>{show(current+1);restart()});
  dots.forEach(d=>d.addEventListener('click',()=>{show(Number(d.dataset.heroDot));restart()})); restart();

  const track=document.querySelector('[data-venue-carousel]');
  if(track){
    const step=()=>Math.max(290, Math.min(420, track.clientWidth*.78));
    document.querySelector('[data-carousel-prev]')?.addEventListener('click',()=>track.scrollBy({left:-step(),behavior:'smooth'}));
    document.querySelector('[data-carousel-next]')?.addEventListener('click',()=>track.scrollBy({left:step(),behavior:'smooth'}));
  }
})();
</script>

<?php require __DIR__.'/includes/footer.php'; ?>
