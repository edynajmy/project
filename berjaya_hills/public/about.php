<?php
require_once __DIR__.'/../includes/bootstrap.php';
$pageTitle = 'About Berjaya Hills';
require __DIR__.'/../includes/header.php';
?>

<style>
.about-page{background:#f7f5ef;color:#17352d}
.about-hero{background:#082b23;color:#fff;position:relative;overflow:hidden}
.about-hero:before{content:"";position:absolute;inset:0;background:
 radial-gradient(circle at 82% 28%,rgba(193,165,103,.20),transparent 24%),
 linear-gradient(120deg,rgba(8,43,35,.98),rgba(15,57,47,.90));}
.about-hero-inner{position:relative;width:min(100% - 48px,1180px);margin:auto;padding:92px 0 82px;display:grid;grid-template-columns:1.15fr .85fr;gap:56px;align-items:center}
.about-eyebrow{color:#d1ae69;font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;margin-bottom:16px}
.about-hero h1{font:500 clamp(46px,6vw,78px)/.95 'Cormorant Garamond',Georgia,serif;margin:0 0 22px;max-width:800px}
.about-hero p{font-size:15px;line-height:1.7;color:#dce6e1;max-width:700px;margin:0}
.about-hero-card{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);backdrop-filter:blur(8px);padding:28px;border-radius:12px;box-shadow:0 25px 70px rgba(0,0,0,.18)}
.about-hero-card strong{display:block;font:500 28px/1.1 'Cormorant Garamond',Georgia,serif;margin-bottom:12px}
.about-hero-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:rgba(255,255,255,.15);margin-top:22px}
.about-hero-stat{padding:14px;background:rgba(8,43,35,.55)}
.about-hero-stat b{display:block;font-size:18px;color:#fff}.about-hero-stat span{font-size:10px;color:#bfcfc9}
.about-section{padding:78px 0}
.about-container{width:min(100% - 48px,1180px);margin:auto}
.about-kicker{color:#b38c4e;font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;margin-bottom:10px}
.about-title{font:500 clamp(34px,4vw,52px)/1.02 'Cormorant Garamond',Georgia,serif;color:#10372e;margin:0 0 18px}
.about-lead{color:#66746e;line-height:1.7;max-width:760px;font-size:13px}
.about-split{display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center}
.about-feature-image{min-height:430px;border-radius:12px;background:center/cover no-repeat;box-shadow:0 20px 45px rgba(26,50,42,.12);position:relative;overflow:hidden}
.about-feature-image:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 45%,rgba(0,0,0,.35))}
.about-image-caption{position:absolute;bottom:20px;left:20px;right:20px;color:#fff;z-index:2;font-size:11px}
.about-image-caption strong{display:block;font-size:17px;margin-bottom:3px}
.about-points{display:grid;gap:12px;margin-top:26px}
.about-point{padding:18px 20px;border:1px solid #e1ddd3;background:#fff;border-radius:9px}
.about-point h3{margin:0 0 7px;font-size:15px;color:#153a31}.about-point p{margin:0;color:#68746e;font-size:11px;line-height:1.6}
.about-dark{background:#092c24;color:#fff}
.about-dark .about-title{color:#fff}.about-dark .about-lead{color:#c7d5d0}
.about-card-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:34px}
.about-card{background:#fff;color:#17352d;border-radius:10px;padding:24px;border:1px solid #e2ded5;min-height:190px}
.about-card .number{font-size:11px;color:#b18b4e;font-weight:700;letter-spacing:2px}.about-card h3{font:500 27px/1.05 'Cormorant Garamond',Georgia,serif;margin:20px 0 10px}.about-card p{margin:0;color:#68746e;font-size:11px;line-height:1.65}
.team-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:30px}
.team-card{background:#fff;border:1px solid #e1ddd3;border-radius:10px;padding:24px;display:flex;gap:16px;align-items:center}
.team-avatar{width:58px;height:58px;border-radius:50%;background:linear-gradient(145deg,#174d3e,#c6aa6d);display:grid;place-items:center;color:#fff;font:600 18px Georgia,serif;flex:0 0 auto}
.team-card h3{font-size:14px;margin:0 0 5px}.team-card p{margin:0;color:#68746e;font-size:10px;line-height:1.5}
.proof-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:30px}
.proof-card{background:#fff;border:1px solid #e1ddd3;border-radius:10px;padding:26px}
.proof-card h3{margin:0 0 12px;font-size:17px}.proof-card p{margin:0;color:#68746e;font-size:11px;line-height:1.65}
.proof-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.proof-badge{border:1px solid #ddd6c7;border-radius:999px;padding:7px 10px;font-size:9px;color:#52615a;background:#faf9f5}
.about-cta{background:#eadfca;padding:56px 0}.about-cta-inner{display:flex;justify-content:space-between;align-items:center;gap:30px}.about-cta h2{font:500 42px/1 'Cormorant Garamond',Georgia,serif;margin:0;color:#10372e}.about-cta p{color:#66746e;font-size:11px;margin:10px 0 0}.about-cta-actions{display:flex;gap:10px;flex-wrap:wrap}.about-btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 17px;border-radius:4px;text-decoration:none;font-size:11px;font-weight:700;background:#0a2d24;color:#fff}.about-btn.alt{background:transparent;color:#0a2d24;border:1px solid #a9966e}
@media(max-width:850px){.about-hero-inner,.about-split{grid-template-columns:1fr}.about-hero-inner{padding:65px 0}.about-card-grid,.team-grid{grid-template-columns:1fr}.proof-grid{grid-template-columns:1fr}.about-feature-image{min-height:300px}.about-cta-inner{align-items:flex-start;flex-direction:column}}
@media(max-width:520px){.about-container,.about-hero-inner{width:min(100% - 30px,700px)}.about-hero h1{font-size:48px}.about-section{padding:58px 0}.about-card-grid{gap:12px}}
</style>

<main class="about-page">
<section class="about-hero">
  <div class="about-hero-inner">
    <div>
      <div class="about-eyebrow">About Berjaya Hills</div>
      <h1>A highland escape where nature, hospitality and memorable experiences meet.</h1>
      <p>Berjaya Hills brings together distinctive places, nature attractions, hospitality and event spaces in one highland destination. This platform makes it easier to discover what the resort offers and plan an experience with confidence.</p>
    </div>
    <aside class="about-hero-card">
      <strong>One destination. Many ways to experience it.</strong>
      <p>From event venues and gardens to family attractions, hospitality and outdoor activities, the destination is designed for different occasions and different ways of travelling.</p>
      <div class="about-hero-stats">
        <div class="about-hero-stat"><b>Highland</b><span>Nature-led setting</span></div>
        <div class="about-hero-stat"><b>17+</b><span>Places in our catalogue</span></div>
        <div class="about-hero-stat"><b>Events</b><span>Spaces for gatherings</span></div>
        <div class="about-hero-stat"><b>Explore</b><span>Activities & attractions</span></div>
      </div>
    </aside>
  </div>
</section>

<section class="about-section">
  <div class="about-container about-split">
    <div class="about-feature-image" style="background-image:url('https://www.berjayahills.com/assets/img/La-Serre-Glasshouse-600x400.jpg')">
      <div class="about-image-caption"><strong>La Serre Glasshouse</strong>One of the destination's distinctive spaces for events and experiences.</div>
    </div>
    <div>
      <div class="about-kicker">01 · Unique selling proposition</div>
      <h2 class="about-title">A different kind of highland destination.</h2>
      <p class="about-lead">The character of Berjaya Hills comes from the combination of highland scenery, European-inspired architecture, landscaped gardens, nature attractions and event-ready spaces. Instead of being only a place to stay, it offers visitors several reasons to explore, gather and spend time together.</p>
      <div class="about-points">
        <div class="about-point"><h3>Distinctive atmosphere</h3><p>A cooler highland environment surrounded by greenery creates a change of pace from the city.</p></div>
        <div class="about-point"><h3>More than accommodation</h3><p>Visitors can move between hospitality, attractions, gardens, activities and venues within the same destination.</p></div>
        <div class="about-point"><h3>Built for occasions</h3><p>From corporate gatherings to celebrations and casual visits, the destination supports different types of experiences.</p></div>
      </div>
    </div>
  </div>
</section>

<section class="about-section" style="background:#fff">
  <div class="about-container about-split">
    <div>
      <div class="about-kicker">02 · Origin & story</div>
      <h2 class="about-title">A destination shaped by place and imagination.</h2>
      <p class="about-lead">Berjaya Hills combines a highland setting with distinctive themed architecture and nature-focused attractions. Its identity is expressed through places such as Colmar Tropicale and the Japanese Village, creating experiences that feel different from a conventional city resort.</p>
      <div class="about-points">
        <div class="about-point"><h3>Inspired by the world</h3><p>Distinctive architectural themes give different parts of the destination their own character and atmosphere.</p></div>
        <div class="about-point"><h3>Hospitality with a human touch</h3><p>The goal is simple: make visitors feel welcomed, comfortable and confident throughout their experience.</p></div>
      </div>
    </div>
    <div class="about-feature-image" style="background-image:url('https://www.berjayahills.org/img/japanese-village-garden.jpg')">
      <div class="about-image-caption"><strong>Japanese Village</strong>A nature-led experience within the wider Berjaya Hills destination.</div>
    </div>
  </div>
</section>

<section class="about-section about-dark">
  <div class="about-container">
    <div class="about-kicker">03 · Sustainability & community</div>
    <h2 class="about-title">Protect the setting. Respect the community.</h2>
    <p class="about-lead">A highland destination depends on the natural environment and the people around it. Our approach is centred on responsible use of the destination, respect for its greenery and creating positive experiences for visitors and the wider local tourism ecosystem.</p>
    <div class="about-card-grid">
      <article class="about-card"><div class="number">01</div><h3>Nature first</h3><p>Encourage visitors to enjoy gardens, forests and outdoor attractions responsibly and leave the environment respected.</p></article>
      <article class="about-card"><div class="number">02</div><h3>Responsible experiences</h3><p>Design information and visitor journeys that make it easier to understand the destination and use its spaces appropriately.</p></article>
      <article class="about-card"><div class="number">03</div><h3>Local connection</h3><p>Support the wider highland tourism ecosystem by connecting visitors with attractions, hospitality and activities in one destination.</p></article>
    </div>
  </div>
</section>

<section class="about-section">
  <div class="about-container">
    <div class="about-kicker">04 · Meet the people</div>
    <h2 class="about-title">The people behind the experience.</h2>
    <p class="about-lead">Hospitality is delivered by people. These roles represent the teams visitors interact with across the destination and event journey.</p>
    <div class="team-grid">
      <article class="team-card"><div class="team-avatar">ES</div><div><h3>Event Services</h3><p>Supports venue enquiries, event planning and booking coordination.</p></div></article>
      <article class="team-card"><div class="team-avatar">GS</div><div><h3>Guest Services</h3><p>Helps visitors navigate their stay, attractions and resort experience.</p></div></article>
      <article class="team-card"><div class="team-avatar">RO</div><div><h3>Resort Operations</h3><p>Keeps spaces, facilities and day-to-day experiences running smoothly.</p></div></article>
    </div>
  </div>
</section>

<section class="about-section" style="background:#fff">
  <div class="about-container">
    <div class="about-kicker">05 · Trust & guest confidence</div>
    <h2 class="about-title">Make every decision with clearer information.</h2>
    <p class="about-lead">Rather than displaying unverified awards or invented testimonials, this platform focuses on information that visitors can act on: venue details, places to explore, availability and a structured booking journey. Verified awards and approved guest testimonials can be added here when official records are available.</p>
    <div class="proof-grid">
      <article class="proof-card"><h3>What visitors can expect</h3><p>Clear place information, useful imagery and a single catalogue for event venues and Berjaya Hills attractions.</p><div class="proof-badges"><span class="proof-badge">Venue information</span><span class="proof-badge">Place discovery</span><span class="proof-badge">Availability</span></div></article>
      <article class="proof-card"><h3>Guest voices</h3><p>Guest testimonials should be published only after approval and with their wording preserved. This keeps social proof authentic and traceable.</p><div class="proof-badges"><span class="proof-badge">Approved reviews</span><span class="proof-badge">Verified recognition</span></div></article>
    </div>
  </div>
</section>

<section class="about-cta">
  <div class="about-container about-cta-inner">
    <div><h2>Ready to explore Berjaya Hills?</h2><p>Discover venues, attractions and places in one unified catalogue.</p></div>
    <div class="about-cta-actions">
      <a class="about-btn" href="<?= url('public/venues/index.php') ?>">Explore venues & places</a>
      <a class="about-btn alt" href="<?= url('public/calendar.php') ?>">View event calendar</a>
    </div>
  </div>
</section>
</main>

<?php require __DIR__.'/../includes/footer.php'; ?>
