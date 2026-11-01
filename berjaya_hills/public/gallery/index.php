<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$pageTitle='Gallery';

function gallery_image_url(?string $path): string {
  if (!$path) return '';
  if (preg_match('#^https?://#i', $path)) return $path;
  return url(ltrim($path, '/'));
}

// Permanent gallery starters keep the public page useful before event albums are published.
// URLs are taken from Berjaya Hills / related official imagery where available.
$starterAlbums = [
  [
    'slug'=>'colmar-tropicale','title'=>'Colmar Tropicale','description'=>'French-inspired architecture, village streets and the character of Berjaya Hills.','category'=>'Scenery',
    'images'=>[
      ['path'=>'https://berjayahills.org/img/colmar-tropicale.jpg','caption'=>'Colmar Tropicale gateway'],
      ['path'=>'https://berjayahills.org/img/colmar-tropicale-valley.jpg','caption'=>'Colmar Tropicale village and highland landscape'],
      ['path'=>'https://ak-d.tripcdn.com/images/1mi4c224x98dv6ctyFEB4_Q90.jpg?proc=source%2Ftrip','caption'=>'Colmar Tropicale entrance area'],
      ['path'=>'https://q-xx.bstatic.com/xdata/images/hotel/max1024x768/10368584.jpg?k=1ee0943be02941d379830369d54f00f551684f079c86310f479a583aafaa7aef&o=','caption'=>'Aerial view of the resort'],
      ['path'=>'https://berjayahills.org/img/colmar-tropicale.jpg','caption'=>'French-style village architecture'],
    ]
  ],
  [
    'slug'=>'japanese-village','title'=>'Japanese Village','description'=>'A tranquil highland garden experience with Japanese-inspired landscapes, water features and greenery.','category'=>'Nature',
    'images'=>[
      ['path'=>'https://berjayahills.org/img/japanese-village-garden.jpg','caption'=>'Japanese Village garden'],
      ['path'=>'https://berjayahills.org/img/japanese-village-activities.jpg','caption'=>'Japanese Village garden and stream'],
      ['path'=>'https://berjayahills.org/img/japanese-village-ceremony.jpg','caption'=>'Traditional Japanese tea ceremony'],
      ['path'=>'https://berjayahills.org/img/botanical-garden-walk.jpg','caption'=>'Rainforest garden walk'],
      ['path'=>'https://berjayahills.org/img/botanical-garden-path.jpg','caption'=>'Botanical garden path near Japanese Village'],
    ]
  ],
  [
    'slug'=>'nature-adventure','title'=>'Nature & Adventure','description'=>'Highland scenery, rainforest trails and outdoor experiences across Berjaya Hills.','category'=>'Activities',
    'images'=>[
      ['path'=>'https://berjayahills.org/img/botanical-garden-walk.jpg','caption'=>'Rainforest trail'],
      ['path'=>'https://berjayahills.org/img/botanical-garden-path.jpg','caption'=>'Botanical Garden path'],
      ['path'=>'https://petualang.travelingyuk.com/uploads/2017/03/Flying-Fox.jpg','caption'=>'Adventure Park flying fox'],
      ['path'=>'https://www.melancong.my/wp-content/uploads/2020/10/Colmar-Tropicale-Berjaya-Hills-Canopy-Walk.jpg','caption'=>'Canopy walk adventure'],
      ['path'=>'https://www.berjayahills.org/img/activities-horse-riding.jpg','caption'=>'Horse riding in the highlands'],
    ]
  ],
  [
    'slug'=>'event-venues','title'=>'Event Venues','description'=>'Spaces prepared for meetings, celebrations, weddings, conferences and private occasions.','category'=>'Events',
    'images'=>[
      ['path'=>'https://www.berjayahills.com/assets/img/La-Serre-Glasshouse-600x400.jpg','caption'=>'La Serre Glasshouse / Glass Hall'],
      ['path'=>'https://www.berjayahills.com/assets/img/Le-Chevalier-600x400.jpg','caption'=>'Le Chevalier meeting setup'],
      ['path'=>'https://www.berjayahills.com/assets/img/La-Serre-Glasshouse-600x400.jpg','caption'=>'Glasshouse event setting'],
      ['path'=>'https://www.berjayahills.com/assets/img/Le-Chevalier-600x400.jpg','caption'=>'Conference and banquet setup'],
    ]
  ],
  [
    'slug'=>'golf-country-club','title'=>'Golf & Country Club','description'=>'An 18-hole highland golf experience framed by rainforest valleys and cool mountain scenery.','category'=>'Activities',
    'images'=>[
      ['path'=>'https://www.berjayahills.org/img/golf-country-club-clubhouse.jpg','caption'=>'Golf & Country Club clubhouse'],
      ['path'=>'https://1.bp.blogspot.com/-JWiHJ3qeOgQ/YMYqeXuzDjI/AAAAAAAAIF0/tI1wYVB6YiokqGBIy5Cf1AT7yP-IM1MKwCLcBGAsYHQ/w640-h640/berjayahills_golf800X800.jpg','caption'=>'Highland golf course'],
      ['path'=>'https://www.berjayahills.org/img/golf-country-club-clubhouse.jpg','caption'=>'Clubhouse entrance'],
    ]
  ],
  [
    'slug'=>'horse-trails','title'=>'Horse Trails','description'=>'A distinctive highland riding experience through the natural surroundings of Berjaya Hills.','category'=>'Activities',
    'images'=>[
      ['path'=>'https://www.berjayahills.org/img/activities-horse-riding.jpg','caption'=>'Guided horse riding'],
      ['path'=>'https://www.berjayahills.org/img/activities-horse-riding.jpg','caption'=>'Horse Trails experience'],
    ]
  ],
  [
    'slug'=>'the-chateau','title'=>'The Chateau','description'=>'A castle-inspired wellness retreat surrounded by the rainforest of Berjaya Hills.','category'=>'Scenery',
    'images'=>[
      ['path'=>'https://photos.tpn.to/js/qk/ts/qg/653x490.jpg','caption'=>'The Chateau Spa & Wellness Resort'],
      ['path'=>'https://ak-d.tripcdn.com/images/1mi1h324x90n00ma8862D_R_600_400_R5_Q90.jpg?proc=source%2Ftrip','caption'=>'The Chateau poolside'],
      ['path'=>'https://www.berjayahills.org/img/chateau-spa3.jpg','caption'=>'The Chateau spa treatment'],
    ]
  ],
  [
    'slug'=>'rabbit-park','title'=>'Rabbit Park','description'=>'A family-friendly attraction where visitors can meet and interact with rabbits and other small animals.','category'=>'Activities',
    'images'=>[
      ['path'=>'https://ak-d.tripcdn.com/images/1i60x2215cjcthyg1F351.jpg?proc=source%2Ftrip','caption'=>'Rabbits at Rabbit Park'],
      ['path'=>'https://4.bp.blogspot.com/-a1RgQajHckE/UGhPQa7nuVI/AAAAAAAAATs/fxWIcl9JQ2Y/s1600/photo-61.jpg','caption'=>'Visitors feeding rabbits'],
      ['path'=>'https://ak-d.tripcdn.com/images/1i60x2215cjcthyg1F351.jpg?proc=source%2Ftrip','caption'=>'Family-friendly animal experience'],
    ]
  ],
];

$dbAlbums = [];
try {
  $dbAlbums = db()->query("SELECT id,title,slug,description,cover_image,created_at FROM gallery_albums WHERE is_public=1 ORDER BY created_at DESC")->fetchAll();
} catch (Throwable $e) {}
$bySlug=[];
// Public gallery is database-driven: deleted albums must not be restored from hard-coded starter data.
foreach($dbAlbums as $a){
  try {
    $imgs=db()->prepare('SELECT image_path AS path, caption FROM gallery_images WHERE album_id=? ORDER BY sort_order ASC,id ASC');
    $imgs->execute([(int)$a['id']]); $a['images']=$imgs->fetchAll();
  } catch(Throwable $e){$a['images']=[];}
  $a['category']='Events';
  if(!$a['images'] && $a['cover_image']) $a['images']=[['path'=>$a['cover_image'],'caption'=>$a['title']]];
  foreach($a['images'] as &$galleryImage){
    $galleryImage['path']=gallery_image_url($galleryImage['path'] ?? '');
  }
  unset($galleryImage);
  $a['cover_image']=gallery_image_url($a['cover_image'] ?? ($a['images'][0]['path'] ?? ''));
  $bySlug[$a['slug']]=$a;
}
$albums=array_values($bySlug);
$totalImages=0; foreach($albums as $a){$totalImages+=count($a['images']??[]);}
$filters=['All','Scenery','Nature','Activities','Events','Food & Dining','Photography Spots'];
?>
<?php require __DIR__.'/../../includes/header.php'; ?>

<style>
.gallery-hero{position:relative;overflow:hidden;color:#fff;background:#06231b;min-height:315px}
.gallery-hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(3,27,20,.98) 0%,rgba(3,27,20,.92) 38%,rgba(3,27,20,.42) 68%,rgba(3,27,20,.18) 100%),url('https://berjayahills.org/img/colmar-tropicale.jpg') right center/auto 100% no-repeat;opacity:1}
.gallery-hero:after{content:"";position:absolute;width:620px;height:620px;right:-260px;bottom:-520px;border:1px solid rgba(223,196,143,.25);border-radius:50%;box-shadow:0 0 0 70px rgba(223,196,143,.035),0 0 0 140px rgba(223,196,143,.02)}
.gallery-hero-inner{position:relative;z-index:2;width:min(1180px,calc(100% - 48px));margin:0 auto;padding:48px 0 38px}.gallery-hero .eyebrow{color:#e3c98e}.gallery-hero .page-title{color:#fff!important;max-width:850px;margin:10px 0 12px;font:600 58px/1.02 'Cormorant Garamond',serif}.gallery-hero p{max-width:780px;color:#e0e8e3;font-size:14px;line-height:1.65;margin:0}.gallery-hero-stats{display:flex;gap:28px;flex-wrap:wrap;margin-top:22px;color:#b9c8c0;font-size:10px}.gallery-hero-stats strong{color:#fff;font-size:18px;margin-right:4px}
.gallery-section{padding:38px 0 75px}.gallery-section>.container{width:min(1290px,calc(100% - 48px));margin:0 auto}.gallery-toolbar{display:flex;justify-content:space-between;align-items:center;gap:25px;margin-bottom:18px}.gallery-toolbar h2{margin:5px 0 0;font:600 40px/1 'Cormorant Garamond',serif;color:var(--forest)}.gallery-filters{display:flex;gap:8px;flex-wrap:wrap}.gallery-filter{border:1px solid #d6d0c4;background:#fbfaf7;color:#4c5a53;border-radius:999px;padding:9px 14px;font-size:10px;cursor:pointer;transition:.2s}.gallery-filter:hover,.gallery-filter.active{background:#092a20;color:#fff;border-color:#092a20}.gallery-search{width:250px;height:40px;border:1px solid #cfc9be;border-radius:999px;background:#fff;padding:0 15px;font-size:10px;color:#34463e}
.gallery-masonry{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;align-items:stretch}.gallery-album{height:100%;display:flex;flex-direction:column;margin:0;border:1px solid #ded9cf;border-radius:11px;overflow:hidden;background:#fff;box-shadow:0 9px 28px rgba(11,28,23,.06);transition:transform .25s ease,box-shadow .25s ease}.gallery-album:hover{transform:translateY(-4px);box-shadow:0 15px 36px rgba(11,28,23,.11)}.gallery-cover{display:block;position:relative;width:100%;height:205px;padding:0;border:0;background:#f2f1ec;cursor:pointer;overflow:hidden}.gallery-cover img{display:block;width:100%;height:100%;object-fit:contain;object-position:center;transition:filter .25s ease}.gallery-cover:hover img{filter:brightness(.88)}.gallery-cover:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.02) 42%,rgba(0,0,0,.65) 100%);pointer-events:none}.gallery-count{position:absolute;right:10px;top:10px;z-index:2;background:rgba(255,255,255,.92);color:#33443c;border-radius:999px;padding:6px 9px;font-size:9px}.gallery-overlay{position:absolute;left:0;right:0;bottom:0;z-index:2;padding:35px 15px 14px;color:#fff;text-align:left}.gallery-overlay-title{display:block;font:600 24px/1 'Cormorant Garamond',serif;margin-bottom:4px}.gallery-overlay span:last-child{font-size:9px}.gallery-album-body{padding:13px 15px 16px;display:flex;flex-direction:column;flex:1;min-height:148px}.gallery-album-meta{display:flex;justify-content:space-between;gap:8px;color:#768079;font-size:8px;text-transform:uppercase;letter-spacing:1px}.gallery-album-meta span:first-child{color:#9a7337;font-weight:800}.gallery-album h3{margin:6px 0 4px;min-height:2.1em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font:600 25px/1.05 'Cormorant Garamond',serif;color:var(--forest)}.gallery-album p{margin:0;color:#68746e;font-size:10px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.gallery-social-panel{margin-top:30px;padding:24px 26px;display:flex;justify-content:space-between;align-items:center;gap:22px;border-radius:11px;background:linear-gradient(135deg,#092a20,#153d30);color:#fff}.gallery-social-panel h2{margin:4px 0 7px;font:600 33px/1 'Cormorant Garamond',serif}.gallery-social-panel p{max-width:700px;margin:0;color:#c7d3cc;font-size:10px;line-height:1.6}.gallery-social-actions{display:flex;gap:8px;flex-wrap:wrap}.gallery-social-actions .btn-outline-dark{background:transparent;color:#fff;border-color:rgba(255,255,255,.35)}
.gallery-lightbox{position:fixed;inset:0;z-index:1000;display:none}.gallery-lightbox.open{display:block}.gallery-lightbox-backdrop{position:absolute;inset:0;background:rgba(3,15,12,.88);backdrop-filter:blur(5px)}.gallery-lightbox-panel{position:relative;z-index:2;width:min(1100px,94vw);height:min(90vh,820px);transition:width .2s ease;margin:5vh auto;background:#f8f6f0;border-radius:12px;overflow:hidden;box-shadow:0 30px 90px rgba(0,0,0,.4);display:grid;grid-template-rows:1fr auto}.gallery-lightbox-close{position:absolute;z-index:4;right:14px;top:12px;width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,.6);background:rgba(0,0,0,.35);color:#fff;font-size:22px;cursor:pointer}.gallery-lightbox-media{min-width:0;min-height:0;background:#071b15;display:flex;align-items:center;justify-content:center;overflow:hidden}.gallery-lightbox-media img{display:block;width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain;object-position:center center;image-rendering:auto;flex:0 1 auto}.gallery-lightbox-panel.is-portrait{width:min(760px,94vw)}.gallery-lightbox-panel.is-landscape{width:min(1100px,94vw)}.gallery-lightbox-panel.is-square{width:min(900px,94vw)}.gallery-lightbox-media{min-width:0;min-height:0;padding:0}.gallery-lightbox-media img{width:100%;height:100%;max-width:100%;max-height:100%;object-fit:contain}.gallery-lightbox-info{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:15px 18px}.gallery-lightbox-info h3{margin:3px 0 2px;font:600 28px 'Cormorant Garamond',serif;color:var(--forest)}.gallery-lightbox-info p{margin:0;color:#69756e;font-size:9px}.gallery-lightbox-actions{display:flex;align-items:center;gap:6px}.gallery-lightbox-actions>span{font-size:9px;color:#68746d;min-width:44px;text-align:center}.no-scroll{overflow:hidden}
@media(max-width:1100px){.gallery-masonry{grid-template-columns:repeat(3,minmax(0,1fr))}.gallery-toolbar{align-items:flex-start;flex-direction:column}.gallery-search{width:100%}}
@media(max-width:800px){.gallery-masonry{grid-template-columns:repeat(2,minmax(0,1fr))}.gallery-hero:before{background:linear-gradient(90deg,rgba(3,27,20,.98),rgba(3,27,20,.78)),url('https://berjayahills.org/img/colmar-tropicale.jpg') center/cover}.gallery-hero .page-title{font-size:46px}.gallery-social-panel{align-items:flex-start;flex-direction:column}.gallery-lightbox-panel{height:94vh;margin:3vh auto}.gallery-lightbox-info{align-items:flex-start;flex-direction:column}}
@media(max-width:560px){.gallery-masonry{grid-template-columns:1fr}.gallery-hero-inner,.gallery-section>.container{width:calc(100% - 28px)}.gallery-hero .page-title{font-size:40px}.gallery-toolbar h2{font-size:34px}.gallery-cover{height:230px}}
</style>
<section class="gallery-hero">
  <div class="gallery-hero-inner">
    <div class="eyebrow">Gallery</div>
    <h1 class="page-title">Moments from Berjaya Hills.</h1>
    <p>Explore the places, landscapes and event-ready spaces that make Berjaya Hills distinctive — with permanent attraction albums available even before new event albums are published.</p>
    <div class="gallery-hero-stats">
      <span><strong><?= count($albums) ?></strong> curated albums</span>
      <span><strong><?= $totalImages ?>+</strong> images</span>
      <span><strong>17+</strong> places in Venues</span>
    </div>
  </div>
</section>

<section class="gallery-section">
  <div class="container">
    <div class="gallery-toolbar">
      <div>
        <div class="eyebrow">Explore by mood</div>
        <h2>See Berjaya Hills from every angle.</h2>
      </div>
      <input class="gallery-search" id="gallerySearch" type="search" placeholder="Search albums..." aria-label="Search albums">
    </div>
    <div class="gallery-filters" role="tablist" aria-label="Gallery filters" style="margin-bottom:22px">
      <?php foreach($filters as $i=>$filter): ?><button class="gallery-filter <?= $i===0?'active':'' ?>" type="button" data-filter="<?= e(strtolower($filter)) ?>"><?= e($filter) ?></button><?php endforeach; ?>
    </div>

    <div class="gallery-masonry" id="galleryGrid">
      <?php foreach($albums as $index=>$a): $category=strtolower($a['category']??'event'); $images=$a['images']??[]; $cover=$a['cover_image']??($images[0]['path']??''); $count=count($images); ?>
      <article class="gallery-album" data-category="<?= e($category) ?>" data-title="<?= e(strtolower($a['title'])) ?>">
        <button class="gallery-cover" type="button" data-album-index="<?= $index ?>" aria-label="Open <?= e($a['title']) ?> album">
          <?php if($cover): ?><img src="<?= e($cover) ?>" alt="<?= e($a['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='https://berjayahills.org/img/colmar-tropicale.jpg';"><?php endif; ?>
          <span class="gallery-count">▧ <?= $count ?> images</span>
          <span class="gallery-overlay"><span class="gallery-overlay-title">View <?= e($a['title']) ?></span><span><?= $count ?> images · Open album →</span></span>
        </button>
        <div class="gallery-album-body"><div class="gallery-album-meta"><span><?= e($a['category']??'Gallery') ?></span><span><?= $count ?> images</span></div><h3><?= e($a['title']) ?></h3><p><?= e($a['description']??'') ?></p></div>
      </article>
      <?php endforeach; ?>
    </div>

    <div class="gallery-social-panel"><div><div class="eyebrow" style="color:#e0c48f">Berjaya Hills moments</div><h2>Share your view of the hills.</h2><p>Tag your visit with <strong>#BerjayaHillsMoments</strong> and discover how other visitors experience the destination.</p></div><div class="gallery-social-actions"><a class="btn btn-gold" href="https://www.instagram.com/explore/tags/berjayahillsmoments/" target="_blank" rel="noopener">Explore Instagram →</a><button class="btn btn-outline-dark" type="button" id="copyHashtag">Copy #BerjayaHillsMoments</button></div></div>
  </div>
</section>

<div class="gallery-lightbox" id="galleryLightbox" aria-hidden="true"><div class="gallery-lightbox-backdrop" data-close-lightbox></div><div class="gallery-lightbox-panel" role="dialog" aria-modal="true" aria-label="Gallery viewer"><button class="gallery-lightbox-close" type="button" data-close-lightbox aria-label="Close">×</button><div class="gallery-lightbox-media"><img id="lightboxImage" src="" alt=""></div><div class="gallery-lightbox-info"><div><span class="eyebrow" id="lightboxCategory">Gallery</span><h3 id="lightboxTitle">Album</h3><p id="lightboxCaption"></p></div><div class="gallery-lightbox-actions"><button class="btn btn-outline-dark" type="button" id="lightboxPrev">←</button><span id="lightboxCounter">1 / 1</span><button class="btn btn-outline-dark" type="button" id="lightboxNext">→</button><button class="btn btn-gold" type="button" id="lightboxShare">Share</button></div></div></div></div>

<script>
const galleryAlbums=<?= json_encode($albums,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
const lightbox=document.getElementById('galleryLightbox'), lbImg=document.getElementById('lightboxImage'), lbTitle=document.getElementById('lightboxTitle'), lbCaption=document.getElementById('lightboxCaption'), lbCategory=document.getElementById('lightboxCategory'), lbCounter=document.getElementById('lightboxCounter');
let currentAlbum=0,currentImage=0;
function openAlbum(ai,ii=0){currentAlbum=ai;currentImage=ii;renderLightbox();lightbox.classList.add('open');lightbox.setAttribute('aria-hidden','false');document.body.classList.add('no-scroll');}
function renderLightbox(){const a=galleryAlbums[currentAlbum]||{};const imgs=a.images||[];const item=imgs[currentImage]||{};const panel=lightbox.querySelector('.gallery-lightbox-panel');const applyImageFrame=()=>{if(!lbImg.naturalWidth||!lbImg.naturalHeight)return;const portrait=lbImg.naturalHeight>lbImg.naturalWidth;panel.classList.toggle('is-portrait',portrait);panel.classList.toggle('is-landscape',!portrait);panel.classList.toggle('is-square',lbImg.naturalHeight===lbImg.naturalWidth);};panel.classList.remove('is-portrait','is-landscape','is-square');lbImg.onload=applyImageFrame;lbImg.onerror=()=>panel.classList.remove('is-portrait','is-landscape','is-square');lbImg.src=item.path||a.cover_image||'';if(lbImg.complete)applyImageFrame();lbImg.alt=item.caption||a.title||'Gallery image';lbTitle.textContent=a.title||'Gallery';lbCategory.textContent=a.category||'Gallery';lbCaption.textContent=item.caption||'';lbCounter.textContent=`${currentImage+1} / ${Math.max(imgs.length,1)}`;}
function closeLightbox(){lightbox.classList.remove('open');lightbox.setAttribute('aria-hidden','true');document.body.classList.remove('no-scroll');}
document.querySelectorAll('.gallery-cover').forEach(b=>b.addEventListener('click',()=>openAlbum(Number(b.dataset.albumIndex))));
document.querySelectorAll('[data-close-lightbox]').forEach(x=>x.addEventListener('click',closeLightbox));
document.getElementById('lightboxPrev').addEventListener('click',()=>{const n=(galleryAlbums[currentAlbum].images||[]).length;currentImage=(currentImage-1+n)%n;renderLightbox();});
document.getElementById('lightboxNext').addEventListener('click',()=>{const n=(galleryAlbums[currentAlbum].images||[]).length;currentImage=(currentImage+1)%n;renderLightbox();});
document.getElementById('lightboxShare').addEventListener('click',async()=>{const a=galleryAlbums[currentAlbum]||{};const data={title:a.title||'Berjaya Hills Gallery',text:`${a.title||'Berjaya Hills'} — #BerjayaHillsMoments`,url:location.href};try{if(navigator.share)await navigator.share(data);else await navigator.clipboard.writeText(location.href);}catch(e){}});
document.addEventListener('keydown',e=>{if(!lightbox.classList.contains('open'))return;if(e.key==='Escape')closeLightbox();if(e.key==='ArrowLeft')document.getElementById('lightboxPrev').click();if(e.key==='ArrowRight')document.getElementById('lightboxNext').click();});
document.querySelectorAll('.gallery-filter').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.gallery-filter').forEach(b=>b.classList.remove('active'));btn.classList.add('active');const f=btn.dataset.filter;document.querySelectorAll('.gallery-album').forEach(card=>{const cat=card.dataset.category;card.style.display=(f==='all'||cat===f)?'':'none';});}));
document.getElementById('gallerySearch').addEventListener('input',e=>{const q=e.target.value.toLowerCase().trim();document.querySelectorAll('.gallery-album').forEach(card=>{card.style.display=(!q||card.dataset.title.includes(q))?'':'none';});});
document.getElementById('copyHashtag').addEventListener('click',async()=>{try{await navigator.clipboard.writeText('#BerjayaHillsMoments');document.getElementById('copyHashtag').textContent='Copied ✓';setTimeout(()=>document.getElementById('copyHashtag').textContent='Copy #BerjayaHillsMoments',1500);}catch(e){}});
</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
