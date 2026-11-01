<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$u = require_login();
if (($u['account_status'] ?? '') !== 'approved') { http_response_code(403); exit('Account approval required.'); }

$pageTitle = 'New Booking';
$pdo = db();
$venues = $pdo->query("SELECT v.*, (SELECT image_path FROM venue_images vi WHERE vi.venue_id=v.id AND vi.is_primary=1 ORDER BY vi.sort_order LIMIT 1) AS image_path FROM venues v WHERE v.status='available' AND v.bookable=1 ORDER BY v.featured DESC, v.name")->fetchAll();
$venueImages = [];
$imgRows = $pdo->query("SELECT venue_id,image_path,alt_text,sort_order,is_primary FROM venue_images ORDER BY venue_id,is_primary DESC,sort_order,id")->fetchAll();
foreach ($imgRows as $ir) { $venueImages[(int)$ir['venue_id']][] = $ir; }
$cats = $pdo->query('SELECT * FROM event_categories ORDER BY name')->fetchAll();
$rooms = $pdo->query("SELECT * FROM room_types WHERE status='available' ORDER BY name")->fetchAll();
$fac = $pdo->query("SELECT * FROM facilities WHERE status='available' ORDER BY name")->fetchAll();
$menus = $pdo->query("SELECT * FROM food_menus WHERE status='active' ORDER BY menu_type,name")->fetchAll();
$foodImageFor = function(array $m, int $index=0): string {
  $label = strtolower(($m['name'] ?? '').' '.($m['menu_type'] ?? ''));
  if (strpos($label,'hi-tea') !== false || strpos($label,'refresh') !== false || strpos($label,'tea') !== false) return url('assets/gallery/food/food-hitea.jpg');
  if (strpos($label,'asian') !== false || strpos($label,'lunch') !== false || strpos($label,'executive') !== false) return url('assets/gallery/food/food-asian.jpg');
  return url('assets/gallery/food/food-buffet.jpg');
};
$draftId = (int)($_GET['draft_id'] ?? 0);
$draft = null;
$draftRoom = null; $draftFacilities = []; $draftFood = null; $draftTimeline = [];
if ($draftId > 0) {
  $dq = $pdo->prepare("SELECT b.*, e.category_id, e.title, e.description, e.start_at, e.end_at, e.expected_pax, e.status AS event_status, e.other_venue_name, ev.venue_id FROM bookings b JOIN events e ON e.id=b.event_id LEFT JOIN event_venues ev ON ev.event_id=e.id WHERE b.id=? AND b.user_id=? AND b.status='draft' LIMIT 1");
  $dq->execute([$draftId, $u['id']]);
  $draft = $dq->fetch();
  if (!$draft) { $draftId = 0; }
  if ($draftId) {
    $rq=$pdo->prepare('SELECT * FROM booking_rooms WHERE booking_id=? ORDER BY id LIMIT 1'); $rq->execute([$draftId]); $draftRoom=$rq->fetch() ?: null;
    $fq=$pdo->prepare('SELECT * FROM booking_facilities WHERE booking_id=? ORDER BY id'); $fq->execute([$draftId]); foreach ($fq->fetchAll() as $fr) { $draftFacilities[(int)$fr['facility_id']] = $fr; }
    $mq=$pdo->prepare('SELECT * FROM booking_food WHERE booking_id=? ORDER BY id LIMIT 1'); $mq->execute([$draftId]); $draftFood=$mq->fetch() ?: null;
    $tq=$pdo->prepare('SELECT item_time,title,description FROM event_timeline_items WHERE event_id=? ORDER BY sort_order,id'); $tq->execute([(int)$draft['event_id']]); $draftTimeline=$tq->fetchAll();
  }
}
$selectedVenue = (int)($draft['venue_id'] ?? ($_GET['venue_id'] ?? 0));
$selectedOtherVenue = trim($draft['other_venue_name'] ?? '');
$venueIsOther = ($selectedVenue === 0 && $selectedOtherVenue !== '');
$selected = null;
foreach ($venues as $v) if ((int)$v['id'] === $selectedVenue) { $selected = $v; break; }
$initialImage = $selected['image_path'] ?? '';
// V47: A genuinely new booking starts empty. Venue/image data only appears after
// the user selects a venue (or explicitly opens an existing draft with draft_id).
require __DIR__ . '/../../includes/header.php';
?>

<div class="user-shell">
  <aside class="sidebar"><?php require __DIR__ . '/../sidebar.php'; ?></aside>
  <section class="admin-content user-content">

<style>
.booking-page{background:#f4f1e9;min-height:calc(100vh - 72px)}
.booking-hero{position:relative;overflow:hidden;background:linear-gradient(105deg,rgba(5,24,18,.98) 0%,rgba(9,38,29,.93) 48%,rgba(9,38,29,.72) 100%),url('<?=e(url($initialImage))?>');background-size:cover;background-position:center;color:#fff;padding:34px 0 30px;border-bottom:1px solid rgba(255,255,255,.08)}
.booking-hero:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 80% 20%,rgba(197,160,93,.15),transparent 32%);pointer-events:none}.booking-hero-inner{position:relative;z-index:1;width:min(1540px,96vw);margin:auto}.booking-breadcrumb{font-size:12px;color:#aebbb3;margin-bottom:10px}.booking-hero h1{font:600 48px/.95 'Cormorant Garamond',serif;margin:0 0 8px;color:#fff}.booking-hero p{margin:0;color:#d8e1dc;font-size:15px}
.booking-layout{width:min(1540px,96vw);margin:0 auto;padding:24px 0 70px;display:grid;grid-template-columns:190px minmax(0,1fr) 350px;gap:16px;align-items:start}.booking-sidebar{position:sticky;top:88px;background:#09241c;color:#fff;border-radius:10px;padding:16px 12px;box-shadow:0 16px 40px rgba(10,30,23,.12)}.booking-sidebar h3{font-size:13px;margin:4px 8px 14px;color:#d9e1dc}.step-nav{display:grid;gap:5px}.step-nav a{display:flex;align-items:center;gap:9px;padding:9px 8px;border-radius:7px;color:#9fb0a8;font-size:11px;text-decoration:none;transition:.18s}.step-nav a:hover{background:#12372b;color:#fff}.step-nav a.active{background:#1b4738;color:#fff}.step-nav a.locked{opacity:.48;cursor:not-allowed}.step-nav a.completed .step-dot{background:#6d9d7e;color:#fff}.step-dot{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#294f42;color:#dfe8e2;font-size:10px;font-weight:700;flex:0 0 22px}.step-nav a.active .step-dot{background:var(--gold);color:#16241e}.step-caption{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* V44 — force the booking workspace into one desktop row. Flex prevents the active step
   from being pushed below the step sidebar by grid-area/implicit-row sizing. */
.booking-layout{display:flex!important;align-items:flex-start!important;gap:16px!important;grid-template-columns:none!important;grid-template-areas:none!important;grid-auto-rows:auto!important}
.booking-sidebar{flex:0 0 190px!important;width:190px!important;align-self:flex-start!important;margin:0!important}
.booking-main{flex:1 1 auto!important;width:auto!important;min-width:0!important;align-self:flex-start!important;margin:0!important;padding:0!important}
.booking-summary{flex:0 0 350px!important;width:350px!important;align-self:flex-start!important;margin:0!important}
.booking-card.active{display:block!important;margin-top:0!important}
@media(max-width:1250px){.booking-sidebar{flex-basis:175px!important;width:175px!important}.booking-summary{flex-basis:315px!important;width:315px!important}.booking-layout{gap:14px!important}}
@media(max-width:1050px){.booking-layout{display:grid!important;grid-template-columns:1fr!important;grid-template-areas:"sidebar" "main" "summary"!important}.booking-sidebar,.booking-main,.booking-summary{width:auto!important;flex:none!important}.booking-sidebar{position:relative!important;top:0!important}.booking-summary{position:relative!important;top:0!important}}
.booking-main{min-width:0}.booking-stepper{display:none}.booking-card{display:none;background:#fff;border:1px solid #e1ddd4;border-radius:10px;margin-bottom:13px;box-shadow:0 8px 30px rgba(16,35,29,.045);overflow:hidden}.booking-card.active{display:block}.booking-card-head{display:flex;align-items:center;gap:12px;padding:18px 20px;border-bottom:1px solid #ebe7df;background:linear-gradient(90deg,#fff,#faf8f3)}.booking-number{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#edf1ed;color:#174534;font-weight:700;font-size:12px}.booking-card-head h2{font:600 27px 'Cormorant Garamond',serif;margin:0;color:#13251e}.booking-card-head p{margin:2px 0 0;color:#7a847e;font-size:11px}.booking-card-body{padding:20px;min-width:0}.step-actions{display:flex;justify-content:space-between;gap:10px;padding-top:12px;margin-top:10px;border-top:1px solid #ebe7df}.step-actions .btn{min-width:130px}.facilities-body{padding-bottom:4px}.facilities-body + .step-actions{margin-top:6px;padding-top:10px}.step-actions .btn[disabled]{opacity:.45;cursor:not-allowed}.step-actions-first{justify-content:flex-end}
.form-grid-book{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.field-book{min-width:0}.field-book.full{grid-column:1/-1}.field-book label{display:block;font-size:11px;font-weight:700;color:#455149;margin-bottom:5px}.field-book input,.field-book select,.field-book textarea{width:100%;border:1px solid #d9d6ce;background:#fff;border-radius:6px;padding:10px 11px;color:#1c2923;outline:none;font-size:13px}.field-book input:focus,.field-book select:focus,.field-book textarea:focus{border-color:#7b9c8c;box-shadow:0 0 0 3px rgba(123,156,140,.12)}.field-book input[readonly]{background:#f3f2ee;color:#6f7872}.required{color:#a45b50}.info-box{background:#f6f4ee;border:1px solid #e5e0d6;border-radius:7px;padding:11px 12px;color:#657068;font-size:11px}.date-availability{display:none;margin-top:12px;padding:11px 12px;border-radius:7px;background:#e7f5eb;border:1px solid #c9e4d2;color:#2f704a;font-size:11px}.date-availability.show{display:block}.date-availability.invalid{background:#fff2ed;border-color:#f0c7ba;color:#9b4f3e}
.venue-selector{display:grid;grid-template-columns:minmax(230px,280px) minmax(0,1fr);gap:18px;align-items:start;min-width:0} .other-venue-field{background:#f7f5ef;border:1px solid #e5e0d6;border-radius:9px;padding:10px 11px}.other-venue-field label{margin-bottom:6px!important}.other-venue-field input{background:#fff}.venue-select-box{display:flex;flex-direction:column;gap:8px;min-width:0}.venue-preview{display:grid;grid-template-columns:minmax(0,1fr);gap:14px;min-width:0;max-width:100%;width:100%;box-sizing:border-box;align-items:start;border:1px solid #e1ddd4;border-radius:12px;padding:14px;background:#faf9f5;overflow:hidden}.venue-preview-media,.venue-preview-copy{min-width:0;width:100%;max-width:100%;box-sizing:border-box}.venue-preview-image{width:100%;height:220px;border-radius:9px;background:linear-gradient(135deg,#173c30,#8c9b87,#c9aa6d);background-size:cover;background-position:center;display:block}.venue-gallery-thumbs{display:flex;justify-content:center;gap:6px;margin-top:7px;overflow-x:auto;max-width:100%;padding-bottom:2px}.venue-gallery-thumbs button{border:1px solid #d9d6ce;padding:0;border-radius:5px;background:#fff;overflow:hidden;width:52px;height:38px;flex:0 0 auto;cursor:pointer}.venue-gallery-thumbs button.active{border:2px solid var(--gold)}.venue-gallery-thumbs img{width:100%;height:100%;object-fit:cover}.venue-preview-copy{overflow:hidden;text-align:center;padding:0 8px}.venue-preview-copy h3{font-size:30px;line-height:1.08;word-break:normal;overflow-wrap:anywhere;margin:0 0 9px;color:#17342a;font-family:'Cormorant Garamond',serif}.venue-preview-copy p{font-size:12px;line-height:1.65;color:#6d7871;margin:0 auto 12px;max-width:520px;word-break:normal;overflow-wrap:anywhere}.venue-meta{display:flex;justify-content:center;gap:7px;flex-wrap:wrap}.meta-pill{font-size:10px;padding:6px 9px;border-radius:14px;background:#edf1ec;color:#375445}.venue-capacity-note{margin:10px auto 0;max-width:520px;font-size:11px;line-height:1.5;color:#59645e}.venue-card-actions{display:flex;justify-content:center;gap:8px;margin-top:14px;flex-wrap:wrap}.venue-card-actions .btn{font-size:10px;white-space:nowrap}.section-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.check-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.check-item{display:flex;align-items:center;gap:8px;border:1px solid #e0ddd5;border-radius:6px;padding:9px 10px;font-size:11px;color:#46534c}.check-item input{accent-color:#0c6a54}.facility-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.facility-item{border:1px solid #e0ddd5;border-radius:8px;background:#fff;padding:10px}.facility-check{display:flex;align-items:center;gap:8px;min-width:0}.facility-check input{accent-color:#0c6a54;flex:0 0 auto}.facility-name{font-size:11px;color:#46534c;flex:1;min-width:0;white-space:normal}.facility-rate{font-size:10px;color:#8a938d;white-space:nowrap}.facility-qty{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:8px;padding-top:8px;border-top:1px solid #eeeae2}.facility-qty label{font-size:10px;font-weight:700;color:#6a746e}.facility-qty input{width:82px;border:1px solid #d9d6ce;border-radius:5px;padding:7px 8px;font-size:12px;color:#1c2923;background:#fff}.facilities-note{margin-top:10px}.facilities-body .step-actions{margin-top:12px;padding-top:12px}.food-section-heading{margin-bottom:12px}.food-eyebrow,.summary-food-label{font-size:9px;letter-spacing:2px;text-transform:uppercase;color:#c39b5d;font-weight:800}.food-section-heading h3{font:600 24px/1.05 'Cormorant Garamond',serif;color:#17342a;margin:3px 0 3px}.food-section-heading p{margin:0;color:#7b847f;font-size:11px}.food-menu-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.food-menu-card{border:1px solid #e1ddd4;border-radius:8px;background:#fff;overflow:hidden;transition:.18s;min-width:0}.food-menu-card.selected{border-color:#c9a663;box-shadow:0 0 0 2px rgba(201,166,99,.18)}.food-menu-image{height:112px;background-size:cover;background-position:center;position:relative}.food-menu-badge{position:absolute;left:9px;top:8px;background:rgba(255,255,255,.92);color:#335244;padding:5px 8px;border-radius:14px;font-size:9px}.food-menu-content{padding:10px}.food-menu-title-row{display:flex;justify-content:space-between;gap:8px;align-items:start}.food-menu-title-row h4{font:600 17px/1.05 'Cormorant Garamond',serif;color:#17342a;margin:0}.food-menu-title-row strong{font-size:10px;color:#263a31;white-space:nowrap}.food-menu-title-row small{font-weight:500;color:#7b847f}.food-menu-content p{font-size:10px;line-height:1.45;color:#77827b;min-height:30px;margin:6px 0 9px}.food-pax-row{display:grid;grid-template-columns:30px 1fr;gap:8px;align-items:center;margin-bottom:8px}.food-pax-row label{font-size:10px;font-weight:700;color:#46534c}.food-card-pax{width:100%;border:1px solid #d9d6ce;border-radius:5px;padding:7px 8px;font-size:12px;color:#1c2923;background:#fff}.food-card-actions{display:grid;grid-template-columns:1fr 1fr;gap:7px}.food-card-actions .btn{min-width:0;padding:8px 6px;font-size:10px}.food-dietary-card{margin-top:12px;border:1px solid #e1ddd4;border-radius:9px;padding:12px;background:#fff}.food-dietary-head{display:flex;gap:9px;align-items:flex-start}.dietary-icon{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:#e7f0e9;flex:0 0 30px}.food-dietary-head h3{font:600 18px/1.1 'Cormorant Garamond',serif;color:#17342a;margin:0}.food-dietary-head h3 small{font:500 11px;color:#77827b}.food-dietary-head p{font-size:10px;color:#7b847f;margin:2px 0 8px}.dietary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}.dietary-item{display:flex;align-items:center;gap:7px;border:1px solid #e5e1d8;border-radius:6px;padding:8px;font-size:10px;color:#46534c;background:#fff}.dietary-item input{accent-color:#0c6a54}.food-special-notes{position:relative;margin-top:9px}.food-special-notes textarea{padding-right:60px!important}.food-char-count{position:absolute;right:8px;bottom:7px;color:#8b948f;font-size:9px}.summary-food-card{margin-top:10px;border:1px solid #e4dfd5;border-radius:8px;padding:10px;background:#fff}.summary-food-row{display:flex;align-items:center;gap:8px;margin-top:7px}.summary-food-thumb{width:46px;height:38px;border-radius:5px;background-size:cover;background-position:center;flex:0 0 46px}.summary-food-copy{display:flex;flex-direction:column;gap:2px;min-width:0;flex:1}.summary-food-copy strong{font-size:10px;color:#2a3b33}.summary-food-copy span{font-size:9px;color:#7b847f}.summary-food-subtotal{font-size:10px;color:#17372c;white-space:nowrap}.food-modal{position:fixed;inset:0;background:rgba(5,24,18,.62);display:none;align-items:center;justify-content:center;padding:20px;z-index:1000}.food-modal.open{display:flex}.food-modal-card{width:min(520px,100%);background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.28)}.food-modal-image{height:190px;background-size:cover;background-position:center}.food-modal-body{padding:18px}.food-modal-body h3{font:600 28px 'Cormorant Garamond',serif;color:#17342a;margin:0 0 4px}.food-modal-body .modal-meta{font-size:10px;color:#78827c;margin-bottom:10px}.food-modal-body p{font-size:12px;line-height:1.6;color:#5f6a64;margin:0 0 14px}.food-modal-close{display:flex;justify-content:flex-end}.timeline-box{display:grid;grid-template-columns:120px 1fr;gap:8px;margin-bottom:8px}.timeline-box input,.timeline-box textarea{width:100%;border:1px solid #ddd9d0;border-radius:6px;padding:8px;font-size:11px}
.booking-summary{position:sticky;top:88px;background:#fff;border:1px solid #dedbd2;border-radius:10px;box-shadow:0 16px 45px rgba(16,35,29,.10);overflow:hidden}.summary-head{background:#0c2a21;color:#fff;padding:18px}.summary-head .eyebrow{font-size:9px}.summary-head h2{font:600 24px 'Cormorant Garamond',serif;margin:5px 0 0;color:#fff}.summary-body{padding:13px}.summary-image{width:100%;aspect-ratio:3 / 2;height:auto;min-height:180px;border-radius:7px;background-color:#eef0eb;background-repeat:no-repeat;background-size:contain;background-position:center;margin-bottom:10px;overflow:hidden;border:1px solid #e1ddd4}.summary-venue{font:600 23px 'Cormorant Garamond',serif;color:#16372d;margin:0 0 4px}.summary-sub{font-size:10px;color:#78827c}.summary-list{display:grid;gap:8px;margin:12px 0;padding:12px 0;border-top:1px solid #e7e2d9;border-bottom:1px solid #e7e2d9}.summary-row{display:flex;justify-content:space-between;gap:12px;font-size:10px}.summary-row span{color:#77817b}.summary-row strong{color:#26342e;text-align:right}.summary-total{border-top:1px solid #e5e1d8;padding-top:13px;margin-top:13px;display:flex;justify-content:space-between;align-items:end}.summary-total span{font-size:11px;color:#69756e}.summary-total strong{font-size:24px;color:#17372c}.availability-box{margin-top:12px;padding:11px;border-radius:7px;background:#e5f4e9;border:1px solid #c8e3d1;color:#2f704a;font-size:11px}.summary-actions{display:grid;grid-template-columns:1fr 1.2fr;gap:8px;margin-top:12px}.summary-actions .btn{font-size:11px;padding:11px 8px}.summary-note{font-size:9px;color:#89928d;line-height:1.5;margin-top:10px}.sticky-mobile-actions{display:none}
@media(max-width:1250px){.booking-layout{grid-template-columns:175px minmax(0,1fr) 315px;grid-template-areas:"sidebar main summary"}.venue-selector{grid-template-columns:1fr}.venue-preview{grid-template-columns:minmax(0,1fr)}.venue-preview-image{height:210px}}
@media(max-width:1050px){.booking-layout{grid-template-columns:1fr;grid-template-areas:"sidebar" "main" "summary"}.booking-sidebar,.booking-summary{position:relative;top:0}.booking-sidebar{order:1}.booking-main{order:2}.booking-summary{order:3}.step-nav{display:flex;overflow:auto}.step-nav a{min-width:max-content}.summary-image{min-height:210px}}
@media(max-width:700px){.booking-hero h1{font-size:40px}.booking-layout{width:94vw}.form-grid-book,.section-grid-3,.check-grid,.facility-grid{grid-template-columns:1fr}.field-book.full{grid-column:auto}.venue-selector{grid-template-columns:1fr}.venue-preview{grid-template-columns:1fr}.venue-preview-image{height:190px}.booking-card-head h2{font-size:23px}.step-actions{position:sticky;bottom:8px;background:rgba(255,255,255,.96);padding:10px;border:1px solid #e1ddd4;border-radius:8px;z-index:4}.food-menu-grid{grid-template-columns:1fr}.dietary-grid{grid-template-columns:1fr 1fr}.food-menu-image{height:150px}.food-card-actions{grid-template-columns:1fr 1fr}}

/* V48 — clean three-column booking workspace based on the approved UI reference */
.booking-page{background:#f4f1e9;min-height:calc(100vh - 72px)}
.booking-hero{display:none!important}
.booking-layout{width:min(1670px,97vw)!important;margin:0 auto!important;padding:14px 0 36px!important;display:grid!important;grid-template-columns:280px minmax(0,1fr) 400px!important;gap:20px!important;align-items:start!important}
.booking-sidebar{position:sticky!important;top:92px!important;flex:none!important;width:280px!important;background:linear-gradient(180deg,#06271e 0%,#082d23 100%)!important;border-radius:12px!important;padding:20px 14px!important;box-shadow:0 18px 45px rgba(10,30,23,.13)!important}
.booking-sidebar h3{font-size:15px!important;margin:4px 10px 18px!important;color:#f2f4f0!important}
.step-nav{gap:6px!important}.step-nav a{padding:11px 10px!important;border-radius:9px!important;font-size:12px!important;min-height:44px}.step-dot{width:28px!important;height:28px!important;flex-basis:28px!important;font-size:11px!important;background:#275548!important}.step-nav a.active{background:#1b513f!important}.step-nav a.active .step-dot{background:var(--gold)!important;color:#14251e!important}.step-nav a.completed .step-dot{background:#76a889!important}.step-caption{font-size:12px!important}
.booking-main{min-width:0!important;width:auto!important;flex:1 1 auto!important}
.booking-card{border-radius:13px!important;margin:0 0 14px!important;box-shadow:0 10px 34px rgba(16,35,29,.055)!important}.booking-card-head{padding:22px 26px!important}.booking-number{width:42px!important;height:42px!important;font-size:13px!important}.booking-card-head h2{font-size:32px!important}.booking-card-head p{font-size:12px!important}.booking-card-body{padding:24px 26px!important}
.form-grid-book{gap:14px!important}.field-book label{font-size:12px!important;margin-bottom:6px!important}.field-book input,.field-book select,.field-book textarea{padding:12px 13px!important;border-radius:8px!important;font-size:13px!important}
.booking-summary{position:sticky!important;top:92px!important;flex:none!important;width:400px!important;background:#fff!important;border:1px solid #dedbd2!important;border-radius:13px!important;box-shadow:0 18px 48px rgba(16,35,29,.11)!important;overflow:hidden!important}
.summary-head{padding:20px 22px!important;background:#082c22!important}.summary-head .eyebrow{font-size:9px!important;letter-spacing:3px!important;color:#d7b16e!important}.summary-head h2{font-size:27px!important;line-height:1.05!important;margin-top:6px!important}
.summary-body{padding:16px!important}.summary-image{aspect-ratio:16/8!important;min-height:150px!important;height:150px!important;background-size:cover!important;background-position:center!important;border-radius:9px!important;margin-bottom:12px!important;display:flex;align-items:center;justify-content:center;position:relative}
.summary-image.empty::before{content:'⌂';width:42px;height:42px;border:1px solid #b8c2bb;border-radius:10px;display:grid;place-items:center;color:#819087;font-size:22px;background:rgba(255,255,255,.5)}
.summary-image.empty::after{content:'Select a venue to see the image';position:absolute;left:0;right:0;bottom:16px;text-align:center;font-size:10px;color:#6f7b74}
.summary-venue{font-size:27px!important;line-height:1.05!important;margin:0 0 4px!important}.summary-sub{font-size:11px!important}
.summary-list{gap:9px!important;margin:13px 0!important;padding:13px 0!important}.summary-row{font-size:11px!important;min-height:20px!important;align-items:center}.summary-row span{color:#6d7871!important}.summary-row strong{color:#26342e!important}
.summary-services-card{background:#f7f5ef;border:1px solid #e4dfd5;border-radius:9px;padding:12px!important}.summary-total{padding-top:14px!important;margin-top:14px!important}.summary-total span{font-size:11px!important}.summary-total strong{font-size:27px!important}
.availability-box{margin-top:13px!important;border-radius:9px!important;padding:12px!important}.summary-actions{margin-top:13px!important}.summary-note{font-size:9px!important}
.booking-summary-extra{margin-top:12px;border:1px solid #e4dfd5;border-radius:9px;padding:13px;background:#fff}.booking-summary-extra h3{font:600 18px/1.1 'Cormorant Garamond',serif;color:#17342a;margin:0 0 4px}.booking-summary-extra p{font-size:10px;line-height:1.5;color:#78827c;margin:0}.summary-mini-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}.summary-mini{background:#f7f5ef;border-radius:7px;padding:9px}.summary-mini strong{display:block;font-size:10px;color:#29483b;margin-bottom:2px}.summary-mini span{font-size:9px;color:#7a847e}
@media(max-width:1350px){.booking-layout{grid-template-columns:240px minmax(0,1fr) 340px!important;gap:15px!important}.booking-sidebar{width:240px!important}.booking-summary{width:340px!important}}
@media(max-width:1100px){.booking-layout{display:grid!important;grid-template-columns:210px minmax(0,1fr)!important;grid-template-areas:'sidebar main' 'summary summary'!important}.booking-sidebar{grid-area:sidebar;width:210px!important}.booking-main{grid-area:main}.booking-summary{grid-area:summary;position:relative!important;top:0!important;width:auto!important}}
@media(max-width:760px){.booking-layout{display:block!important;padding:10px!important}.booking-sidebar,.booking-main,.booking-summary{width:100%!important;position:relative!important;top:0!important}.booking-sidebar{margin-bottom:12px}.booking-card-head{padding:18px!important}.booking-card-body{padding:18px!important}.form-grid-book{grid-template-columns:1fr 1fr!important}.booking-summary{margin-top:12px}.summary-mini-grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.form-grid-book{grid-template-columns:1fr!important}.booking-card-head h2{font-size:27px!important}.summary-mini-grid{grid-template-columns:1fr}}

/* V49 — booking workspace row fix: keep steps, form and summary together on desktop */
.booking-layout{
  width:100%!important;
  max-width:1670px!important;
  margin:0 auto!important;
  padding:14px 0 36px!important;
  display:flex!important;
  flex-direction:row!important;
  align-items:flex-start!important;
  gap:20px!important;
  box-sizing:border-box!important;
}
.booking-sidebar{
  flex:0 0 240px!important;
  width:240px!important;
  min-width:240px!important;
  align-self:flex-start!important;
}
.booking-main{
  flex:1 1 auto!important;
  width:auto!important;
  min-width:0!important;
  max-width:none!important;
}
.booking-summary{
  flex:0 0 340px!important;
  width:340px!important;
  min-width:340px!important;
  position:sticky!important;
  top:92px!important;
  align-self:flex-start!important;
  margin:0!important;
}
/* Prevent the summary from becoming a second grid row at medium desktop widths. */
@media (max-width:1100px) and (min-width:901px){
  .booking-layout{display:flex!important;flex-direction:row!important;gap:14px!important;padding-left:0!important;padding-right:0!important}
  .booking-sidebar{flex-basis:205px!important;width:205px!important;min-width:205px!important}
  .booking-summary{flex-basis:300px!important;width:300px!important;min-width:300px!important}
  .booking-card-head{padding:18px 20px!important}
  .booking-card-body{padding:20px!important}
  .form-grid-book{grid-template-columns:repeat(2,minmax(0,1fr))!important}
}
@media (max-width:900px){
  .booking-layout{display:block!important;width:100%!important;padding:10px!important}
  .booking-sidebar,.booking-main,.booking-summary{width:100%!important;min-width:0!important;max-width:none!important;position:relative!important;top:0!important}
  .booking-sidebar{margin-bottom:12px!important}
  .booking-summary{margin-top:14px!important}
}


/* V50 — compact desktop workspace: keep the summary on the right even when the browser is zoomed. */
@media (max-width:1200px) and (min-width:901px){
  .user-content .booking-layout{
    display:grid!important;
    grid-template-columns:160px minmax(0,1fr) 290px!important;
    grid-template-areas:"sidebar main summary"!important;
    gap:12px!important;
    align-items:start!important;
    width:calc(100% - 28px)!important;
    max-width:none!important;
  }
  .user-content .booking-sidebar{grid-area:sidebar!important;width:160px!important;min-width:160px!important;position:sticky!important;top:88px!important;}
  .user-content .booking-main{grid-area:main!important;min-width:0!important;width:auto!important;}
  .user-content .booking-summary{grid-area:summary!important;width:290px!important;min-width:290px!important;position:sticky!important;top:88px!important;margin:0!important;}
  .user-content .summary-image{min-height:150px!important;}
  .user-content .summary-head h2{font-size:21px!important;}
  .user-content .summary-body{padding:11px!important;}
}
/* V51 — final responsive rule: keep the approved 3-column workspace on desktop/tablet widths,
   including when the browser is zoomed. Only stack on genuinely narrow mobile viewports. */
@media (min-width:701px) and (max-width:1200px){
  .user-content .booking-layout{
    display:grid!important;
    grid-template-columns:minmax(150px,190px) minmax(260px,1fr) minmax(250px,300px)!important;
    grid-template-areas:"sidebar main summary"!important;
    gap:12px!important;
    align-items:start!important;
    width:100%!important;
    max-width:none!important;
    padding-left:10px!important;
    padding-right:10px!important;
    box-sizing:border-box!important;
  }
  .user-content .booking-sidebar{grid-area:sidebar!important;width:auto!important;min-width:0!important;position:sticky!important;top:88px!important;}
  .user-content .booking-main{grid-area:main!important;width:auto!important;min-width:0!important;}
  .user-content .booking-summary{grid-area:summary!important;width:auto!important;min-width:0!important;position:sticky!important;top:88px!important;margin:0!important;}
  .user-content .summary-head{padding:15px!important;}
  .user-content .summary-head h2{font-size:20px!important;}
  .user-content .summary-body{padding:10px!important;}
  .user-content .summary-image{height:125px!important;min-height:125px!important;}
}
@media (min-width:551px){
  /* Final desktop/tablet workspace: keep all three panels in one row.
     This intentionally remains active at high browser zoom so the summary
     does not fall underneath the form. */
  .user-content .booking-layout{
    display:grid!important;
    grid-template-columns:minmax(135px,190px) minmax(175px,1fr) minmax(205px,300px)!important;
    grid-template-areas:"sidebar main summary"!important;
    gap:10px!important;
    align-items:start!important;
    width:100%!important;
    max-width:none!important;
    padding-left:6px!important;
    padding-right:6px!important;
    box-sizing:border-box!important;
  }
  .user-content .booking-sidebar{grid-area:sidebar!important;width:auto!important;min-width:0!important;position:sticky!important;top:88px!important;}
  .user-content .booking-main{grid-area:main!important;width:auto!important;min-width:0!important;}
  .user-content .booking-summary{grid-area:summary!important;width:auto!important;min-width:0!important;position:sticky!important;top:88px!important;margin:0!important;}
}
@media (max-width:550px){
  .user-content .booking-layout{display:grid!important;grid-template-columns:1fr!important;grid-template-areas:"sidebar" "main" "summary"!important;}
  .user-content .booking-sidebar,.user-content .booking-main,.user-content .booking-summary{width:auto!important;min-width:0!important;position:relative!important;top:0!important;}
  .user-content .booking-sidebar{margin-bottom:12px!important;}
  .user-content .booking-summary{margin-top:14px!important;}
}

/* V53 — HARD FIX: booking workspace must never place Summary on a second row on desktop.
   The previous rules used max-width breakpoints that fired when browser zoom reduced the
   CSS viewport. This final rule keeps the three booking panels in one horizontal track.
   Horizontal scrolling is allowed only if the browser viewport becomes physically too
   narrow; the Summary remains to the RIGHT of the form rather than dropping underneath. */
@media (min-width:551px){
  .user-content .booking-layout{
    display:grid!important;
    grid-template-columns:160px minmax(420px,1fr) 280px!important;
    grid-template-areas:"sidebar main summary"!important;
    gap:10px!important;
    align-items:start!important;
    width:max(100%, 870px)!important;
    min-width:870px!important;
    max-width:none!important;
    margin:0!important;
    padding:14px 6px 36px!important;
    box-sizing:border-box!important;
  }
  .user-content .booking-sidebar{
    grid-area:sidebar!important;
    width:auto!important;
    min-width:0!important;
    max-width:none!important;
    position:sticky!important;
    top:88px!important;
    align-self:start!important;
  }
  .user-content .booking-main{
    grid-area:main!important;
    width:auto!important;
    min-width:420px!important;
    max-width:none!important;
    position:relative!important;
  }
  .user-content .booking-summary{
    grid-area:summary!important;
    grid-column:auto!important;
    grid-row:auto!important;
    width:auto!important;
    min-width:280px!important;
    max-width:none!important;
    position:sticky!important;
    top:88px!important;
    margin:0!important;
    align-self:start!important;
  }
}
@media (max-width:550px){
  .user-content .booking-layout{
    display:grid!important;
    grid-template-columns:1fr!important;
    grid-template-areas:"sidebar" "main" "summary"!important;
    width:100%!important;
    min-width:0!important;
    padding:10px!important;
  }
  .user-content .booking-sidebar,
  .user-content .booking-main,
  .user-content .booking-summary{
    width:auto!important;
    min-width:0!important;
    position:relative!important;
    top:0!important;
  }
}

/* V54 — APPROVED BOOKING WORKSPACE UI
   Rebuild the booking shell to match the clean 3-column reference:
   global sidebar | booking steps | active form | sticky summary.
   This intentionally overrides the older V44–V53 layout patches. */

/* Keep the global member sidebar compact like the approved reference. */
.user-shell{
  grid-template-columns:250px minmax(0,1fr)!important;
  min-height:calc(100vh - 74px)!important;
  background:#f5f3ed!important;
}
.user-shell .sidebar{
  width:250px!important;
  min-width:250px!important;
  height:calc(100vh - 74px)!important;
  padding:24px 14px 28px!important;
  background:linear-gradient(180deg,#09221b 0%,#071a15 100%)!important;
  box-shadow:none!important;
}
.user-content{
  min-width:0!important;
  width:100%!important;
  padding:0!important;
  overflow-x:hidden!important;
  background:#f5f3ed!important;
}

/* The reference uses the workspace immediately below the header. */
.booking-hero{display:none!important}
.booking-page{min-height:calc(100vh - 74px)!important;background:#f5f3ed!important}

.booking-layout{
  width:100%!important;
  max-width:none!important;
  min-width:0!important;
  margin:0!important;
  padding:18px 20px 42px!important;
  display:grid!important;
  grid-template-columns:215px minmax(0,1fr) 410px!important;
  grid-template-areas:"sidebar main summary"!important;
  gap:18px!important;
  align-items:start!important;
  box-sizing:border-box!important;
}

.booking-sidebar{
  grid-area:sidebar!important;
  width:215px!important;
  min-width:215px!important;
  max-width:215px!important;
  position:sticky!important;
  top:92px!important;
  align-self:start!important;
  margin:0!important;
  padding:20px 12px!important;
  border-radius:12px!important;
  background:#09241c!important;
  box-shadow:0 18px 45px rgba(10,30,23,.12)!important;
}
.booking-sidebar h3{
  margin:2px 8px 14px!important;
  font-size:15px!important;
  color:#fff!important;
}
.step-nav{gap:6px!important}
.step-nav a{
  min-height:43px!important;
  padding:9px 8px!important;
  border-radius:8px!important;
  font-size:12px!important;
}
.step-dot{
  width:28px!important;height:28px!important;flex-basis:28px!important;
  font-size:11px!important;
}

.booking-main{
  grid-area:main!important;
  width:auto!important;
  min-width:0!important;
  max-width:none!important;
  margin:0!important;
  padding:0!important;
}
.booking-card{
  width:100%!important;
  max-width:none!important;
  margin:0!important;
  border-radius:12px!important;
  border:1px solid #dfdbd2!important;
  box-shadow:0 14px 38px rgba(16,35,29,.07)!important;
}
.booking-card-head{
  padding:22px 24px!important;
  gap:14px!important;
  background:linear-gradient(90deg,#fff,#fbfaf7)!important;
}
.booking-number{
  width:46px!important;height:46px!important;
  flex:0 0 46px!important;
  font-size:13px!important;
}
.booking-card-head h2{font-size:31px!important;line-height:1!important}
.booking-card-head p{font-size:13px!important;margin-top:5px!important}
.booking-card-body{padding:24px!important}
.form-grid-book{
  grid-template-columns:repeat(3,minmax(0,1fr))!important;
  gap:14px!important;
}
/* Step 1 desktop field map: Event / Category / Pax, then Organizer / Company, then Contact / Email / Phone. */
.booking-card#step-1 .form-grid-book > .field-book:nth-child(1){grid-column:1;grid-row:1}
.booking-card#step-1 .form-grid-book > .field-book:nth-child(2){grid-column:2;grid-row:1}
.booking-card#step-1 .form-grid-book > .field-book:nth-child(8){grid-column:3;grid-row:1}
.booking-card#step-1 .form-grid-book > .field-book:nth-child(3){grid-column:1 / span 2;grid-row:2}
.booking-card#step-1 .form-grid-book > .field-book:nth-child(4){grid-column:3;grid-row:2}
.booking-card#step-1 .form-grid-book > .field-book:nth-child(5){grid-column:1;grid-row:3}
.booking-card#step-1 .form-grid-book > .field-book:nth-child(6){grid-column:2;grid-row:3}
.booking-card#step-1 .form-grid-book > .field-book:nth-child(7){grid-column:3;grid-row:3}
.field-book label{font-size:12px!important;margin-bottom:6px!important}
.field-book input,.field-book select,.field-book textarea{
  min-height:46px!important;
  padding:11px 13px!important;
  border-radius:7px!important;
  font-size:13px!important;
}
.booking-description-field{position:relative!important;margin-top:2px!important}
.booking-description-field textarea{min-height:92px!important;resize:vertical!important;padding-bottom:24px!important}
.optional{font-weight:500;color:#89918c}
.char-counter{position:absolute;right:12px;bottom:9px;color:#8b938e;font-size:10px}
.step-actions{
  margin-top:20px!important;
  padding-top:18px!important;
  gap:12px!important;
}
.step-actions .btn{min-height:46px!important;padding:10px 22px!important;font-size:13px!important}
/* V61: tighten the Facilities footer so navigation buttons sit closer to the content. */
.facilities-body + .step-actions{margin-top:4px!important;padding-top:8px!important;padding-bottom:8px!important}
.facilities-body{padding-bottom:0!important}

.booking-summary{
  grid-area:summary!important;
  width:410px!important;
  min-width:410px!important;
  max-width:410px!important;
  position:sticky!important;
  top:92px!important;
  align-self:start!important;
  margin:0!important;
  border-radius:12px!important;
  box-shadow:0 18px 48px rgba(16,35,29,.10)!important;
}
.summary-head{padding:20px 22px!important}
.summary-head h2{font-size:27px!important}
.summary-body{padding:16px!important}
.summary-image{height:150px!important;min-height:150px!important;aspect-ratio:auto!important;background-size:cover!important;background-position:center!important}
.summary-venue{font-size:28px!important;margin-top:8px!important}
.summary-sub{font-size:12px!important}
.summary-list{gap:11px!important;margin:15px 0!important;padding:15px 0!important}
.summary-row{font-size:12px!important;min-height:22px!important}
.summary-row span{color:#68736d!important}
.summary-row strong{font-size:12px!important;color:#25332d!important}
.summary-services-card,.booking-summary .info-box{font-size:12px!important}
.summary-total{padding-top:16px!important;margin-top:16px!important}
.summary-total span{font-size:12px!important}
.summary-total strong{font-size:29px!important}
.availability-box{padding:13px!important;font-size:12px!important}
.booking-summary-extra{padding:14px!important}
.summary-actions .btn{min-height:42px!important}
.summary-note{font-size:10px!important}

/* Medium desktop/tablet: preserve the three-column concept while reducing widths. */
@media (min-width:1051px) and (max-width:1450px){
  .booking-layout{
    grid-template-columns:190px minmax(0,1fr) 330px!important;
    gap:14px!important;
    padding-left:16px!important;padding-right:16px!important;
  }
  .booking-sidebar{width:190px!important;min-width:190px!important;max-width:190px!important}
  .booking-summary{width:330px!important;min-width:330px!important;max-width:330px!important}
  .booking-card-head{padding:20px!important}
  .booking-card-body{padding:20px!important}
  .booking-card-head h2{font-size:28px!important}
  .form-grid-book{grid-template-columns:repeat(2,minmax(0,1fr))!important}
  .form-grid-book .field-book:nth-child(n){grid-column:auto!important}
  .form-grid-book .field-book.full{grid-column:1/-1!important}
}

/* Narrow devices: stack cleanly instead of creating a squeezed/overlapping layout. */
@media (max-width:1050px){
  .user-shell{grid-template-columns:1fr!important}
  .user-shell .sidebar{position:relative!important;width:100%!important;min-width:100%!important;height:auto!important}
  .booking-layout{
    display:grid!important;
    grid-template-columns:1fr!important;
    grid-template-areas:"sidebar" "main" "summary"!important;
    width:100%!important;
    min-width:0!important;
    padding:14px!important;
  }
  .booking-sidebar,.booking-main,.booking-summary{
    width:100%!important;min-width:0!important;max-width:none!important;position:relative!important;top:0!important;
  }
  .booking-summary{margin-top:0!important}
  .form-grid-book{grid-template-columns:repeat(2,minmax(0,1fr))!important}
  .form-grid-book .field-book:nth-child(n){grid-column:auto!important}
  .form-grid-book .field-book.full{grid-column:1/-1!important}
}
@media (max-width:650px){
  .booking-layout{padding:10px!important}
  .booking-sidebar{padding:16px 10px!important}
  .step-nav{display:flex!important;overflow-x:auto!important;gap:5px!important}
  .step-nav a{min-width:max-content!important}
  .booking-card-head{padding:18px!important}
  .booking-card-head h2{font-size:26px!important}
  .booking-card-body{padding:16px!important}
  .form-grid-book{grid-template-columns:1fr!important}
  .form-grid-book .field-book.full{grid-column:auto!important}
}


/* V55: keep the summary beside the form at desktop and tablet widths. */
@media (min-width:701px) and (max-width:1050px){
  .user-content .booking-layout{display:grid!important;grid-template-columns:minmax(125px,155px) minmax(300px,1fr) minmax(220px,260px)!important;grid-template-areas:"sidebar main summary"!important;gap:10px!important;width:100%!important;max-width:none!important;padding:12px!important;box-sizing:border-box!important;align-items:start!important}
  .user-content .booking-sidebar{grid-area:sidebar!important;width:auto!important;min-width:0!important;max-width:none!important;position:sticky!important;top:88px!important}
  .user-content .booking-main{grid-area:main!important;width:auto!important;min-width:0!important}
  .user-content .booking-summary{grid-area:summary!important;width:auto!important;min-width:0!important;max-width:none!important;position:sticky!important;top:88px!important;margin:0!important}
  .user-content .summary-head{padding:13px!important}.user-content .summary-head h2{font-size:18px!important}.user-content .summary-body{padding:10px!important}.user-content .summary-image{height:110px!important;min-height:110px!important}
  .user-content .form-grid-book{grid-template-columns:repeat(2,minmax(0,1fr))!important}.user-content .form-grid-book .field-book:nth-child(n){grid-column:auto!important;grid-row:auto!important}.user-content .form-grid-book .field-book.full{grid-column:1/-1!important}
}
@media (max-width:700px){
  .user-shell{grid-template-columns:1fr!important}.user-shell .sidebar{position:relative!important;width:100%!important;min-width:100%!important;height:auto!important}
  .user-content .booking-layout{display:grid!important;grid-template-columns:1fr!important;grid-template-areas:"sidebar" "main" "summary"!important;width:100%!important;min-width:0!important;padding:10px!important;box-sizing:border-box!important}
  .user-content .booking-sidebar,.user-content .booking-main,.user-content .booking-summary{grid-area:auto!important;width:100%!important;min-width:0!important;max-width:none!important;position:relative!important;top:0!important;margin:0!important}
  .user-content .booking-sidebar{margin-bottom:12px!important;padding:16px 10px!important}.user-content .booking-summary{margin-top:14px!important}
  .user-content .step-nav{display:flex!important;overflow-x:auto!important;gap:5px!important}.user-content .step-nav a{min-width:max-content!important}
  .user-content .form-grid-book{grid-template-columns:1fr!important}.user-content .form-grid-book .field-book:nth-child(n){grid-column:auto!important;grid-row:auto!important}.user-content .form-grid-book .field-book.full{grid-column:1/-1!important}
}


/* V57 — remove the right-side live booking summary; keep booking steps and form as the only workspace columns. */
.booking-summary,#bookingSummary{display:none!important;}
.user-content .booking-layout{
  display:grid!important;
  grid-template-columns:minmax(190px,240px) minmax(0,1fr)!important;
  grid-template-areas:"sidebar main"!important;
  align-items:start!important;
  gap:18px!important;
  width:100%!important;
  max-width:1500px!important;
  margin:0 auto!important;
  box-sizing:border-box!important;
}
.user-content .booking-sidebar{grid-area:sidebar!important;width:auto!important;min-width:0!important;position:sticky!important;top:88px!important;}
.user-content .booking-main{grid-area:main!important;width:auto!important;min-width:0!important;max-width:none!important;}
@media(max-width:760px){
 .user-content .booking-layout{display:block!important;width:100%!important;padding:10px!important;}
 .user-content .booking-sidebar,.user-content .booking-main{width:100%!important;min-width:0!important;position:relative!important;top:0!important;}
 .user-content .booking-sidebar{margin-bottom:12px!important;}
}

/* V63 — keep the active booking form aligned at the top beside the step navigator.
   Earlier accumulated responsive overrides could leave an oversized implicit grid row. */
.user-content form#bookingForm{display:block!important;width:100%!important;margin:0!important;padding:0!important;}
.user-content form#bookingForm>.booking-layout{
  display:grid!important;
  grid-template-columns:minmax(190px,240px) minmax(0,1fr)!important;
  grid-template-rows:auto!important;
  grid-template-areas:"sidebar main"!important;
  align-items:start!important;
  align-content:start!important;
  justify-items:stretch!important;
  gap:18px!important;
  width:100%!important;
  min-width:0!important;
  max-width:1500px!important;
  margin:0 auto!important;
  padding:14px 18px 36px!important;
  box-sizing:border-box!important;
}
.user-content form#bookingForm>.booking-layout>.booking-sidebar{
  grid-area:sidebar!important;grid-row:1!important;grid-column:1!important;
  align-self:start!important;margin:0!important;
}
.user-content form#bookingForm>.booking-layout>.booking-main{
  grid-area:main!important;grid-row:1!important;grid-column:2!important;
  display:block!important;align-self:start!important;justify-self:stretch!important;
  width:auto!important;min-width:0!important;max-width:none!important;
  margin:0!important;padding:0!important;transform:none!important;
}
.user-content .booking-main>.booking-card{margin-top:0!important;scroll-margin-top:94px!important;}
@media(max-width:760px){
 .user-content form#bookingForm>.booking-layout{display:grid!important;grid-template-columns:minmax(0,1fr)!important;grid-template-areas:"sidebar" "main"!important;gap:12px!important;padding:10px!important;}
 .user-content form#bookingForm>.booking-layout>.booking-sidebar,.user-content form#bookingForm>.booking-layout>.booking-main{grid-area:auto!important;grid-row:auto!important;grid-column:1!important;width:100%!important;min-width:0!important;}
}


/* V64 — keep every active booking step aligned beside the step navigator.
   Prevent hidden step panels or inherited workspace spacing from creating a tall blank row. */
.user-content form#bookingForm > .booking-layout{
  display:grid!important;
  grid-template-columns:220px minmax(0,1fr)!important;
  grid-template-areas:"sidebar main"!important;
  align-items:start!important;
  justify-items:stretch!important;
  gap:18px!important;
  width:100%!important;
  max-width:1500px!important;
  margin:0 auto!important;
  padding:14px 18px 36px!important;
  box-sizing:border-box!important;
  min-height:0!important;
}
.user-content form#bookingForm > .booking-layout > .booking-sidebar{
  grid-area:sidebar!important;align-self:start!important;position:sticky!important;top:96px!important;
  margin:0!important;transform:none!important;float:none!important;
}
.user-content form#bookingForm > .booking-layout > .booking-main{
  grid-area:main!important;align-self:start!important;justify-self:stretch!important;
  display:block!important;position:relative!important;top:0!important;inset:auto!important;
  width:100%!important;min-width:0!important;max-width:none!important;
  margin:0!important;padding:0!important;transform:none!important;float:none!important;
}
.user-content form#bookingForm > .booking-layout > .booking-main > .booking-card{display:none!important;}
.user-content form#bookingForm > .booking-layout > .booking-main > .booking-card.active{
  display:block!important;position:relative!important;top:0!important;inset:auto!important;
  margin:0!important;transform:none!important;visibility:visible!important;
}
@media(max-width:760px){
 .user-content form#bookingForm > .booking-layout{display:grid!important;grid-template-columns:minmax(0,1fr)!important;grid-template-areas:"sidebar" "main"!important;padding:12px!important;gap:12px!important}
 .user-content form#bookingForm > .booking-layout > .booking-sidebar{position:relative!important;top:auto!important;width:100%!important}
 .user-content form#bookingForm > .booking-layout > .booking-main{width:100%!important}
}

/* V65 — keep the active booking form at the top beside the step navigator. */
@media (min-width: 901px){
  .user-content form#bookingForm > .booking-layout{
    display:grid!important;
    grid-template-columns: minmax(190px,240px) minmax(420px,1fr) minmax(280px,340px)!important;
    grid-template-areas:"sidebar main summary"!important;
    grid-auto-flow:row!important;
    align-items:start!important;
    align-content:start!important;
    justify-content:stretch!important;
    min-height:0!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-sidebar{grid-area:sidebar!important;grid-row:1!important;align-self:start!important;margin:0!important;}
  .user-content form#bookingForm > .booking-layout > .booking-main{
    grid-area:main!important;grid-row:1!important;align-self:start!important;
    display:block!important;height:auto!important;min-height:0!important;max-height:none!important;
    margin:0!important;padding:0!important;position:relative!important;top:0!important;
    transform:none!important;align-content:start!important;justify-self:stretch!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-main > .booking-card.active{
    display:block!important;position:relative!important;inset:auto!important;
    margin:0 0 13px!important;transform:none!important;top:0!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-summary{grid-area:summary!important;grid-row:1!important;align-self:start!important;}
}

/* V67 — compact the active step panel and remove the excessive vertical offset. */
@media (min-width: 761px){
  .user-content form#bookingForm > .booking-layout{
    align-items:start!important;
    align-content:start!important;
    padding-top:12px!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-main{
    align-self:start!important;
    margin-top:-285px!important;
    min-height:0!important;
    height:auto!important;
    max-width:1080px!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-main > .booking-card.active{
    width:100%!important;
    max-width:1080px!important;
    margin:0 0 12px!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-main > .booking-card.active .booking-card-head{
    padding:14px 18px!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-main > .booking-card.active .booking-card-body{
    padding:16px 18px!important;
  }
}
@media (max-width:760px){
  .user-content form#bookingForm > .booking-layout > .booking-main{margin-top:0!important;max-width:none!important;}
}

/* V66 — remove the unnecessary catering step and pin the active step card to the workspace top. */
@media (min-width: 761px){
  .user-content form#bookingForm > .booking-layout{
    display:grid!important; grid-template-columns:260px minmax(0,1fr)!important;
    grid-template-areas:"sidebar main"!important; grid-auto-rows:min-content!important;
    align-items:start!important; align-content:start!important; justify-content:start!important;
    min-height:0!important; padding-top:12px!important; gap:16px!important;
  }
  .user-content form#bookingForm > .booking-layout > .booking-sidebar{grid-area:sidebar!important;grid-row:1!important;align-self:start!important;}
  .user-content form#bookingForm > .booking-layout > .booking-main{grid-area:main!important;grid-row:1!important;align-self:start!important;justify-self:stretch!important;min-height:0!important;height:auto!important;margin:0!important;padding:0!important;transform:none!important;}
  .user-content form#bookingForm > .booking-layout > .booking-main > .booking-card.active{display:block!important;position:relative!important;top:0!important;inset:auto!important;margin:0!important;transform:none!important;}
  .user-content form#bookingForm > .booking-layout > .booking-summary{display:none!important;}
}


/* V75: booking page's local layout must respect the shared collapsed icon rail. */
.admin-shell.user-shell.user-sidebar-collapsed{grid-template-columns:76px minmax(0,1fr)!important}
.admin-shell.user-shell.user-sidebar-collapsed>.sidebar{width:76px!important;min-width:76px!important;max-width:76px!important;padding:16px 9px 24px!important}
.admin-shell.user-shell.user-sidebar-collapsed>.sidebar>a{width:48px!important;min-width:48px!important;max-width:48px!important;height:48px!important;min-height:48px!important;margin:5px auto!important;padding:0!important;display:flex!important;justify-content:center!important;align-items:center!important;font-size:0!important}
.admin-shell.user-shell.user-sidebar-collapsed>.sidebar>a .side-icon{width:24px!important;min-width:24px!important;flex:0 0 24px!important;font-size:17px!important}
@media(max-width:760px){.admin-shell.user-shell.user-sidebar-collapsed{grid-template-columns:64px minmax(0,1fr)!important}.admin-shell.user-shell.user-sidebar-collapsed>.sidebar{width:64px!important;min-width:64px!important;max-width:64px!important;padding:12px 5px!important}.admin-shell.user-shell.user-sidebar-collapsed>.sidebar>a{width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important}}
</style>

<section class="booking-page">
  <div class="booking-hero">
    <div class="booking-hero-inner">
      <div class="booking-breadcrumb">Dashboard &nbsp;›&nbsp; Bookings &nbsp;›&nbsp; New Booking</div>
      <div class="eyebrow">Berjaya Hills Resort · Booking Management</div>
      <h1>Book an Event</h1>
      <p>Plan your event with a guided booking experience. Complete the details below, save a draft at any time, or submit when ready.</p>
    </div>
  </div>

  <form id="bookingForm" method="post" action="<?=url('user/bookings/save.php')?>">
    <input type="hidden" name="csrf" value="<?=csrf_token()?>">
    <input type="hidden" name="timeline_compiled" id="timeline_compiled" value="">
    <input type="hidden" name="food_menu_id" id="food_menu_id" value="0"><input type="hidden" name="food_pax" id="food_pax" value="0"><input type="hidden" name="food_dietary" id="food_dietary" value=""><input type="hidden" name="food_special_notes" id="food_special_notes" value="">
    <input type="hidden" name="action" id="booking_action" value=""><input type="hidden" name="draft_id" id="draft_id" value="<?= (int)$draftId ?>">

    <div class="booking-layout">
      <aside class="booking-sidebar">
        <h3>Booking steps</h3>
        <nav class="step-nav">
          <?php $steps=['Event Information','Date & Time','Venue','Group & Rooms','Facilities']; foreach($steps as $i=>$label): ?>
            <a href="#step-<?=$i+1?>" class="<?= $i===0?'active':'' ?>"><span class="step-dot"><?=$i+1?></span><span class="step-caption"><?=e($label)?></span></a>
          <?php endforeach; ?>
        </nav>
      </aside>

      <main class="booking-main">

        <section class="booking-card" id="step-1">
          <div class="booking-card-head"><span class="booking-number">1</span><div><h2>Event Information</h2><p>Tell us what you are planning.</p></div></div>
          <div class="booking-card-body">
            <div class="form-grid-book">
              <div class="field-book"><label>Event Name <span class="required">*</span></label><input name="title" id="event_title" required placeholder="e.g. Corporate Annual Meeting" value="<?=e($draft['title'] ?? '')?>"></div>
              <div class="field-book"><label>Event Category <span class="required">*</span></label><select name="category_id" id="category_id" required><option value="">Select category</option><?php foreach($cats as $c): ?><option value="<?=$c['id']?>" <?=((int)($draft['category_id'] ?? 0)===(int)$c['id'])?'selected':''?> ><?=e($c['name'])?></option><?php endforeach; ?></select></div>
              <div class="field-book"><label>Organizer</label><input readonly value="<?=e($u['full_name'])?>"></div>
              <div class="field-book"><label>Company Name</label><input readonly value="<?=e($u['company_name'] ?? '')?>"></div>
              <div class="field-book"><label>Contact Person</label><input readonly value="<?=e($u['full_name'])?>"></div>
              <div class="field-book"><label>Email</label><input readonly value="<?=e($u['email'])?>"></div>
              <div class="field-book"><label>Phone</label><input readonly value="<?=e($u['phone'] ?? '')?>"></div>
              <div class="field-book"><label>Expected Pax <span class="required">*</span></label><input type="number" name="expected_pax" id="expected_pax" min="1" value="<?= (int)($draft['expected_pax'] ?? 50) ?>" required></div>
              <div class="field-book full booking-description-field"><label>Event Description / Purpose <span class="optional">(Optional)</span></label><textarea name="description" id="event_description" rows="3" maxlength="500" placeholder="e.g. Meeting, seminar, workshop, celebration, etc."><?=e($draft['description'] ?? '')?></textarea><small class="char-counter"><span id="event_description_count"><?=strlen($draft['description'] ?? '')?></span> / 500</small></div>
            </div>
            <div class="step-actions step-actions-first"><button type="button" class="btn btn-outline save-draft">Save Draft</button><button type="button" class="btn btn-gold next-step">Next Step →</button></div>
          </div>
        </section>

        <section class="booking-card" id="step-2">
          <div class="booking-card-head"><span class="booking-number">2</span><div><h2>Date & Time</h2><p>Choose the event schedule. Availability is checked when you submit.</p></div></div>
          <div class="booking-card-body">
            <div class="form-grid-book">
              <div class="field-book"><label>Start Date <span class="required">*</span></label><input type="date" name="start_date" id="start_date" min="<?=date('Y-m-d')?>" required value="<?=!empty($draft['start_at'])?date('Y-m-d',strtotime($draft['start_at'])):''?>"></div>
              <div class="field-book"><label>Start Time <span class="required">*</span></label><input type="time" name="start_time" id="start_time" value="<?=!empty($draft['start_at'])?date('H:i',strtotime($draft['start_at'])):'09:00'?>" required></div>
              <div class="field-book"><label>End Date <span class="required">*</span></label><input type="date" name="end_date" id="end_date" min="<?=date('Y-m-d')?>" required value="<?=!empty($draft['end_at'])?date('Y-m-d',strtotime($draft['end_at'])):''?>"></div>
              <div class="field-book"><label>End Time <span class="required">*</span></label><input type="time" name="end_time" id="end_time" value="<?=!empty($draft['end_at'])?date('H:i',strtotime($draft['end_at'])):'17:00'?>" required></div>
            </div>
            <input type="hidden" name="start_at" id="start_at"><input type="hidden" name="end_at" id="end_at">
            <div class="info-box" style="margin-top:12px">The system will check overlapping confirmed/pending bookings and scheduled venue maintenance before accepting the request.</div><div class="date-availability" id="date_availability">✓ Date and time selected. Venue availability will be confirmed when you submit.</div>
            <div class="step-actions"><button type="button" class="btn btn-outline prev-step">← Previous Step</button><div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end"><button type="button" class="btn btn-outline save-draft">Save Draft</button><button type="button" class="btn btn-gold next-step">Next Step →</button></div></div>
          </div>
        </section>

        <section class="booking-card" id="step-3">
          <div class="booking-card-head"><span class="booking-number">3</span><div><h2>Venue</h2><p>Select the space that best fits your event.</p></div></div>
          <div class="booking-card-body">
            <div class="venue-selector">
              <div class="venue-select-box">
                <div class="field-book"><label>Select Venue <span class="required">*</span></label><select name="venue_id" id="venue_id" required><option value="" data-name="" data-capacity="0" data-rate="0" data-type="" data-image="">Select a venue</option><?php foreach($venues as $v): ?><option value="<?=$v['id']?>" data-name="<?=e($v['name'])?>" data-capacity="<?=$v['capacity']?>" data-rate="<?=$v['rate']?>" data-type="<?=e($v['venue_type'])?>" data-image="<?=e(venue_image_url($v['image_path'] ?? null, $v['name']) ?? '')?>" <?=$selectedVenue===$v['id']?'selected':''?>><?=e($v['name'])?></option><?php endforeach; ?><option value="other" data-name="Other" data-capacity="0" data-rate="0" data-type="Other" data-image="" <?=$venueIsOther?'selected':''?>>Others</option></select></div>
                <div class="field-book other-venue-field" id="other_venue_field" style="display:<?= $venueIsOther ? 'block' : 'none' ?>;margin-top:10px"><label for="other_venue_name">Other venue / space <span class="required">*</span></label><input type="text" name="other_venue_name" id="other_venue_name" maxlength="150" value="<?=e($selectedOtherVenue)?>" placeholder="e.g. Poolside Lawn, Private Garden, Meeting Area"></div>
                <div class="info-box"><strong>Capacity:</strong> <span id="venue_capacity">—</span> pax<br><strong>Venue rate:</strong> RM <span id="venue_rate">0.00</span></div>
              </div>
              <div class="venue-preview">
                <div class="venue-preview-media">
                  <div class="venue-preview-image" id="venue_preview_image" style="<?= $initialImage ? "background-image:url('".e(venue_image_url($initialImage, $selected['name'] ?? null))."')" : '' ?>"></div>
                  <div class="venue-gallery-thumbs" id="venue_gallery_thumbs"></div>
                </div>
                <div class="venue-preview-copy">
                  <h3 id="venue_preview_name"><?=e($selected['name'] ?? 'Select a venue')?></h3>
                  <p id="venue_preview_desc"><?=e($selected['description'] ?? 'Choose a venue to see its capacity and pricing.')?></p>
                  <div class="venue-meta"><span class="meta-pill" id="venue_preview_type"><?=e($selected['venue_type'] ?? 'Venue')?></span><span class="meta-pill"><span id="venue_preview_capacity"><?=number_format((int)($selected['capacity'] ?? 0))?></span> pax</span></div>
                  <div class="venue-capacity-note" id="venue_capacity_note"></div>
                  <div class="venue-card-actions"><a class="btn btn-outline" style="color:var(--forest);border-color:#c9c3b7" id="venue_details_link" href="<?=url('user/venues/view.php')?><?= $selectedVenue ? '?id='.$selectedVenue : '' ?>">View Details →</a><a class="btn btn-gold" id="venue_gallery_link" href="<?=url('user/venues/view.php')?><?= $selectedVenue ? '?id='.$selectedVenue : '' ?>#venue-gallery">View Gallery →</a></div>
                </div>
              </div>
            </div>
            <div class="step-actions"><button type="button" class="btn btn-outline prev-step">← Previous Step</button><div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end"><button type="button" class="btn btn-outline save-draft">Save Draft</button><button type="button" class="btn btn-gold next-step">Next Step →</button></div></div>
          </div>
        </section>

        <section class="booking-card" id="step-4">
          <div class="booking-card-head"><span class="booking-number">4</span><div><h2>Group & Rooms</h2><p>Accommodation is optional and can be included in your request.</p></div></div>
          <div class="booking-card-body"><div class="form-grid-book">
            <div class="field-book"><label>Total Pax</label><input type="number" id="group_pax" min="1" value="50" readonly></div>
            <div class="field-book"><label>Number of Rooms</label><input type="number" name="room_qty" id="room_qty" min="0" value="<?= (int)($draftRoom['quantity'] ?? 0) ?>"></div>
            <div class="field-book"><label>Room Type</label><select name="room_type_id" id="room_type_id"><option value="">No room block</option><?php foreach($rooms as $r): ?><option value="<?=$r['id']?>" data-rate="<?=$r['nightly_rate']?>" data-name="<?=e($r['name'])?>" <?=((int)($draftRoom['room_type_id'] ?? 0)===(int)$r['id'])?'selected':''?> ><?=e($r['name'])?> · RM <?=number_format($r['nightly_rate'],2)?>/night</option><?php endforeach; ?></select></div>
            <div class="field-book"><label>Nights</label><input type="number" name="nights" id="nights" min="1" value="<?= (int)($draftRoom['nights'] ?? 1) ?>"></div>
          </div><div class="step-actions"><button type="button" class="btn btn-outline prev-step">← Previous Step</button><div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end"><button type="button" class="btn btn-outline save-draft">Save Draft</button><button type="button" class="btn btn-gold next-step">Next Step →</button></div></div></div>
        </section>

        <section class="booking-card" id="step-5">
          <div class="booking-card-head"><span class="booking-number">5</span><div><h2>Facilities</h2><p>Select equipment and services required for the event.</p></div></div>
          <div class="booking-card-body facilities-body"><div class="facility-grid"><?php foreach($fac as $f): $savedFacility=$draftFacilities[(int)$f['id']] ?? null; $savedQty=(int)($savedFacility['quantity'] ?? 0); ?><div class="facility-item"><label class="facility-check"><input type="checkbox" name="facility_ids[]" value="<?=$f['id']?>" data-rate="<?=$f['unit_rate']?>" data-name="<?=e($f['name'])?>" <?=($savedQty>0)?'checked':''?> ><span class="facility-name"><?=e($f['name'])?></span><span class="facility-rate">RM <?=number_format($f['unit_rate'],2)?></span></label><div class="facility-qty"><label for="facility_qty_<?=$f['id']?>">Quantity</label><input type="number" id="facility_qty_<?=$f['id']?>" name="facility_qty[<?=$f['id']?>]" min="0" value="<?=$savedQty?>" data-facility-id="<?=$f['id']?>"></div></div><?php endforeach; ?></div><div class="info-box facilities-note">Select the facilities you need and enter the required quantity for each one. Only facilities with a quantity above 0 will be included in the booking.</div></div><div class="step-actions"><button type="button" class="btn btn-outline prev-step">← Previous Step</button><div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end"><button type="button" class="btn btn-outline save-draft">Save Draft</button><button type="submit" class="btn btn-gold" name="action" value="submit">Submit Booking →</button></div></div></div>
        </section>

        <!-- Steps 6, 7 and 8 removed: Timeline, Additional Information and Summary. -->
        <!-- Submission is now available directly from Step 5 (Facilities). -->
      </main>

      <aside class="booking-summary" id="bookingSummary">
        <div class="summary-head"><div class="eyebrow">Booking Summary</div><h2>Your booking details at a glance.</h2></div>
        <div class="summary-body">
          <div class="summary-image<?= $initialImage ? "" : " empty" ?>" id="summary_image" style="<?= $initialImage ? "background-image:url('".e(url($initialImage))."')" : '' ?>"></div>
          <h3 class="summary-venue" id="summary_venue_name"><?=e($selected['name'] ?? 'Select a venue')?></h3>
          <div class="summary-sub"><span id="summary_type"><?=e($selected['venue_type'] ?? 'Venue')?></span> · <span id="summary_capacity"><?=number_format((int)($selected['capacity'] ?? 0))?></span> pax</div>
          <div class="summary-list">
            <div class="summary-row"><span>Event Name</span><strong id="summary_event">—</strong></div>
            <div class="summary-row"><span>Category</span><strong id="summary_category">—</strong></div>
            <div class="summary-row"><span>Organizer</span><strong><?=e($u['full_name'])?></strong></div>
            <div class="summary-row"><span>Date & Time</span><strong id="summary_datetime">—</strong></div>
            <div class="summary-row"><span>Total Pax</span><strong id="summary_pax">50 pax</strong></div>
          </div>
          <div class="info-box"><strong>Selected Facilities</strong><div id="summary_services" style="margin-top:5px">No additional services selected.</div></div>
          <div class="summary-food-card" id="summary_food_card" style="display:none"><div class="summary-food-label">Selected Menu</div><div class="summary-food-row"><div class="summary-food-thumb" id="summary_food_thumb"></div><div class="summary-food-copy"><strong id="summary_food_name">—</strong><span><span id="summary_food_pax">0</span> pax × RM <span id="summary_food_rate">0.00</span></span></div><strong class="summary-food-subtotal">RM <span id="summary_food_subtotal">0.00</span></strong></div></div>
          <div class="summary-total"><span>Total Estimated Cost</span><strong>RM <span id="summary_total">0.00</span></strong></div>
          <div class="availability-box">✓ <strong>Availability will be checked</strong><br><span>The system validates venue conflicts and maintenance periods on submission.</span></div>
          <div class="booking-summary-extra">
            <h3>Plan with confidence.</h3>
            <p>Your selections will appear here as you complete each step. You can save a draft anytime and continue later.</p>
            <div class="summary-mini-grid">
              <div class="summary-mini"><strong>1 · Choose a venue</strong><span>Pick the space that fits your event.</span></div>
              <div class="summary-mini"><strong>2 · Build your event</strong><span>Add rooms, facilities and catering.</span></div>
            </div>
          </div>
          <div class="summary-actions"><button type="button" class="btn btn-outline prev-step" style="color:var(--forest);border-color:#c5c0b7">← Previous</button><button type="button" class="btn btn-primary summary-next">Next Step →</button></div>
          <p class="summary-note">Submitting sends your request to management for review. Confirmed bookings cannot be edited directly; changes use a change request workflow.</p>
        </div>
      </aside>
    </div>
  </form>

<div class="food-modal" id="foodModal" aria-hidden="true">
  <div class="food-modal-card" role="dialog" aria-modal="true" aria-labelledby="foodModalTitle">
    <div class="food-modal-image" id="foodModalImage"></div>
    <div class="food-modal-body">
      <div class="modal-meta" id="foodModalMeta">Menu Package</div>
      <h3 id="foodModalTitle">Menu Details</h3>
      <p id="foodModalDescription">—</p>
      <div class="info-box">The menu information shown here is taken from the active catering menu configured by management.</div>
      <div class="food-modal-close"><button type="button" class="btn btn-gold" id="foodModalClose">Close</button></div>
    </div>
  </div>
</div>
</section>
  </section>
</div>

<script>
(function(){
  const form=document.getElementById('bookingForm');
  const venue=document.getElementById('venue_id');
  const room=document.getElementById('room_type_id');
  const food=document.getElementById('food_menu_id');
  const facilities=[...document.querySelectorAll('input[name="facility_ids[]"]')];
  const foodCards=[...document.querySelectorAll('.food-menu-card')];
  const draftNotes=<?=json_encode($draft['notes'] ?? '', JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
  const sections=[...document.querySelectorAll('.booking-card')];
  const nav=[...document.querySelectorAll('.step-nav a')];
  const venueImages=<?=json_encode($venueImages, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
  let currentStep=1, maxUnlocked=1;
  // Keep the in-progress booking in this browser tab while the user opens venue details/gallery.
  const bookingStateKey='bh_booking_progress_v1';
  // Explicit New Booking entry points must not reopen stale in-progress form data.
  const startFresh=<?= !empty($_GET['fresh']) && $draftId===0 ? 'true' : 'false' ?>;
  if(startFresh){try{sessionStorage.removeItem(bookingStateKey);}catch(e){}}
  function captureBookingState(){
    const values={};
    [...form.elements].forEach((el)=>{
      if(!el.name || ['csrf','action'].includes(el.name) || el.type==='file' || el.disabled) return;
      const key=el.name;
      if(el.type==='checkbox' || el.type==='radio'){
        if(!(key in values)) values[key]=[];
        if(el.checked) values[key].push(el.value);
      }else values[key]=el.value;
    });
    try{sessionStorage.setItem(bookingStateKey,JSON.stringify({values,step:currentStep,maxUnlocked,at:Date.now()}));}catch(e){}
  }
  function restoreBookingState(){
    if(<?= $draftId ?> > 0) return null;
    try{
      const saved=JSON.parse(sessionStorage.getItem(bookingStateKey)||'null');
      if(!saved || !saved.values || Date.now()-Number(saved.at||0)>8*60*60*1000) return null;
      [...form.elements].forEach((el)=>{
        if(!el.name || !(el.name in saved.values) || ['csrf','action'].includes(el.name) || el.type==='file') return;
        const val=saved.values[el.name];
        if(el.type==='checkbox' || el.type==='radio') el.checked=Array.isArray(val)&&val.includes(el.value);
        else if(!el.disabled) el.value=val;
      });
      currentStep=Math.min(5,Math.max(1,Number(saved.step)||1));
      maxUnlocked=Math.min(5,Math.max(currentStep,Number(saved.maxUnlocked)||currentStep));
      return saved;
    }catch(e){return null;}
  }
  const restoredBooking=restoreBookingState();
  form.addEventListener('input',captureBookingState);
  form.addEventListener('change',captureBookingState);
  window.addEventListener('pagehide',captureBookingState);
  const money=n=>Number(n||0).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});
  function optionData(sel){return sel?.selectedOptions?.[0];}
  function foodData(){const id=Number(food?.value||0);const card=foodCards.find(c=>Number(c.dataset.menuId)===id);return card?{id,rate:Number(card.dataset.rate||0),name:card.dataset.name||'',type:card.dataset.type||'',description:card.dataset.description||'',image:card.dataset.image||''}:{id:0,rate:0,name:'',type:'',description:'',image:''};}
  function syncFoodHiddenFromCard(card){if(!card)return;food.value=card.dataset.menuId||'0';const paxInput=card.querySelector('.food-card-pax');document.getElementById('food_pax').value=Math.max(0,Number(paxInput?.value||0));foodCards.forEach(c=>c.classList.toggle('selected',c===card));foodCards.forEach(c=>{const b=c.querySelector('.food-select');if(b)b.textContent=c===card?'Selected ✓':'Select Menu';});updateTotal();}
  function syncFoodNotes(){const dietary=[...document.querySelectorAll('.food-dietary-check:checked')].map(x=>x.value);document.getElementById('food_dietary').value=dietary.join('||');const notes=document.getElementById('food_special_notes_ui')?.value.trim()||'';document.getElementById('food_special_notes').value=notes;const count=document.getElementById('food_notes_count');if(count)count.textContent=notes.length;}
  function restoreFoodNotes(){const dm=draftNotes.match(/\[FOOD_DIETARY\]\s*(.*?)\s*\[\/FOOD_DIETARY\]/s);if(dm&&dm[1]){const vals=dm[1].split('||').map(x=>x.trim()).filter(Boolean);document.querySelectorAll('.food-dietary-check').forEach(c=>c.checked=vals.includes(c.value));}const nm=draftNotes.match(/\[FOOD_NOTES\]\s*(.*?)\s*\[\/FOOD_NOTES\]/s);if(nm){const ui=document.getElementById('food_special_notes_ui');if(ui)ui.value=nm[1].trim();}syncFoodNotes();}
  function resolveImageUrl(path){if(!path)return ''; if(/^https?:\/\//i.test(path)) return path; return '<?=url('')?>'+path.replace(/^\//,'');}
  function setImage(el,path,name){if(!el)return; const src=resolveImageUrl(path); el.style.backgroundImage=src?`url("${src}")`:'';}
  function dateTime(){
    const sd=document.getElementById('start_date').value, st=document.getElementById('start_time').value, ed=document.getElementById('end_date').value, et=document.getElementById('end_time').value;
    if(sd&&st) document.getElementById('start_at').value=sd+' '+st+':00';
    if(ed&&et) document.getElementById('end_at').value=ed+' '+et+':00';
    if(sd&&ed){ const a=new Date(sd+'T00:00:00'), b=new Date(ed+'T00:00:00'); const nights=Math.max(1,Math.round((b-a)/86400000)); document.getElementById('nights').value=nights; }
  }
  function validateDates(){
    const sd=document.getElementById('start_date'), ed=document.getElementById('end_date'), box=document.getElementById('date_availability');
    if(!sd||!ed||!box)return true;
    const now=new Date();
    const today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    const localToday=[today.getFullYear(),String(today.getMonth()+1).padStart(2,'0'),String(today.getDate()).padStart(2,'0')].join('-');
    sd.min=localToday;
    ed.min=sd.value&&sd.value>localToday?sd.value:localToday;
    if(sd.value&&sd.value<localToday){ box.textContent='Past dates are unavailable. Please select today or a future date.'; box.classList.add('show','invalid'); return false; }
    if(ed.value&&ed.value<localToday){ box.textContent='Past dates are unavailable. Please select today or a future date.'; box.classList.add('show','invalid'); return false; }
    if(sd.value&&ed.value&&ed.value<sd.value){ box.textContent='Please choose an end date on or after the start date.'; box.classList.add('show','invalid'); return false; }
    if(sd.value&&ed.value){ box.textContent='✓ Date and time selected. Venue availability will be confirmed when you submit.'; box.classList.remove('invalid'); box.classList.add('show'); return true; }
    box.classList.remove('show','invalid'); return true;
  }
  function renderVenueGallery(){
    const o=optionData(venue); const thumbs=document.getElementById('venue_gallery_thumbs'); thumbs.innerHTML='';
    const imgs=(venueImages[o?.value]||[]).map(x=>x.image_path).filter(Boolean);
    const paths=imgs.length?imgs:[o?.dataset.image].filter(Boolean);
    const main=document.getElementById('venue_preview_image');
    paths.forEach((path,i)=>{const b=document.createElement('button');b.type='button';b.className=i===0?'active':'';b.innerHTML='<img alt="Venue photo">'; const im=b.querySelector('img'); im.src=resolveImageUrl(path); b.addEventListener('click',()=>{main.style.backgroundImage=`url("${im.src}")`;thumbs.querySelectorAll('button').forEach(x=>x.classList.remove('active'));b.classList.add('active');});thumbs.appendChild(b);});
  }
  function updateVenue(){
    const o=optionData(venue); if(!o) return; const d=o.dataset;
    const isOther=(venue.value==='other');
    const otherField=document.getElementById('other_venue_field');
    const otherInput=document.getElementById('other_venue_name');
    if(otherField) otherField.style.display=isOther?'block':'none';
    if(otherInput){ otherInput.required=isOther; if(!isOther) otherInput.value=''; }
    const cap=Number(d.capacity||0);
    document.getElementById('venue_capacity').textContent=cap?cap.toLocaleString():'—';
    document.getElementById('venue_rate').textContent=money(d.rate);
    document.getElementById('venue_preview_name').textContent=isOther?(otherInput?.value.trim()||'Other venue'): (d.name||'Select a venue');
    document.getElementById('venue_preview_type').textContent=d.type||'Venue';
    document.getElementById('venue_preview_capacity').textContent=cap?cap.toLocaleString():'—';
    document.getElementById('venue_preview_desc').textContent=isOther?'Please provide the name of the venue or space you would like to use.':(d.name?('A bookable event space at Berjaya Hills. '+(cap?'Maximum capacity: '+cap.toLocaleString()+' guests.':'Capacity information is not available yet.')):'Choose a venue to see its details.');
    document.getElementById('venue_capacity_note').textContent=isOther?'Venue capacity will be confirmed by management.':(cap?'Maximum capacity: '+cap.toLocaleString()+' guests.':'Capacity information is not available for this venue yet.');
    document.getElementById('summary_venue_name').textContent=isOther?(otherInput?.value.trim()||'Other venue'):(d.name||'Select a venue'); document.getElementById('summary_type').textContent=isOther?'Other':(d.type||'Venue'); document.getElementById('summary_capacity').textContent=cap?cap.toLocaleString():'—';
    const img=isOther?'':(d.image||''); ['summary_image'].forEach(id=>{const el=document.getElementById(id); el.style.backgroundImage=img?`url("${resolveImageUrl(img)}")`:''; el.classList.toggle('empty', !img);});
    document.getElementById('venue_details_link').style.display=isOther?'none':'inline-flex'; document.getElementById('venue_gallery_link').style.display=isOther?'none':'inline-flex'; document.getElementById('venue_details_link').href='<?=url('user/venues/view.php')?>?id='+o.value;
    document.getElementById('venue_gallery_link').href='<?=url('user/venues/view.php')?>?id='+o.value+'#venue-gallery';
    renderVenueGallery(); updateTotal();
  }
  function updateTotal(){
    dateTime(); validateDates();
    const v=Number(optionData(venue)?.dataset.rate||0);
    const rooms=Number(document.getElementById('room_qty').value||0)*Number(document.getElementById('nights').value||1)*Number(optionData(room)?.dataset.rate||0);
    const fac=document.querySelectorAll('input[name="facility_ids[]"]:checked');
    const f=[...fac].reduce((sum,el)=>sum + Number(document.querySelector('[name="facility_qty['+el.value+']"]')?.value||0)*Number(el.dataset.rate||0),0);
    const fd=foodData(); const foodRate=fd.rate, foodPax=Number(document.getElementById('food_pax').value||0), foodTotal=foodRate*foodPax;
    const total=v+rooms+f+foodTotal;
    ['summary_total','inline_total'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=money(total)});
    const services=[]; if(room.value&&Number(document.getElementById('room_qty').value)>0) services.push(optionData(room).dataset.name+' × '+document.getElementById('room_qty').value); [...fac].forEach(el=>{const q=Number(document.querySelector('[name="facility_qty['+el.value+']"]')?.value||0);if(q>0)services.push(el.dataset.name+' × '+q);}); document.getElementById('summary_services').textContent=services.length?services.join(' · '):'No additional services selected.';
    const foodCard=document.getElementById('summary_food_card'); if(fd.id&&foodPax>0){foodCard.style.display='block';document.getElementById('summary_food_name').textContent=fd.name;document.getElementById('summary_food_pax').textContent=foodPax;document.getElementById('summary_food_rate').textContent=money(foodRate);document.getElementById('summary_food_subtotal').textContent=money(foodTotal);document.getElementById('summary_food_thumb').style.backgroundImage=fd.image?'url("'+fd.image+'")':'';}else{foodCard.style.display='none';}
    const ev=document.getElementById('event_title').value||'—'; const cat=optionData(document.getElementById('category_id'))?.textContent||'—'; const pax=document.getElementById('expected_pax').value||0;
    document.getElementById('summary_event').textContent=ev; document.getElementById('summary_category').textContent=cat; document.getElementById('summary_pax').textContent=pax+' pax';
    document.getElementById('sum_event').textContent=ev; document.getElementById('sum_venue').textContent=optionData(venue)?.dataset.name||'—'; document.getElementById('sum_schedule').textContent=(document.getElementById('start_date').value||'—')+' '+(document.getElementById('start_time').value||'')+' → '+(document.getElementById('end_date').value||'—')+' '+(document.getElementById('end_time').value||'');
    const dt=(document.getElementById('start_date').value||'')+' '+(document.getElementById('start_time').value||'')+' → '+(document.getElementById('end_date').value||'')+' '+(document.getElementById('end_time').value||''); document.getElementById('summary_datetime').textContent=dt||'—'; document.getElementById('group_pax').value=pax;
  }
  function compileTimeline(){const rows=[...document.querySelectorAll('.timeline-box')].map(r=>{const t=r.querySelector('.timeline-time').value,n=r.querySelector('.timeline-item-text').value.trim();return t&&n?t+' — '+n:null}).filter(Boolean);document.getElementById('timeline_compiled').value=rows.join('\n');}
  function validateStep(step){
    const section=document.getElementById('step-'+step); if(!section)return true;
    if(step===2 && !validateDates())return false;
    const fields=[...section.querySelectorAll('input,select,textarea')].filter(el=>el.required && !el.disabled && el.type!=='hidden');
    for(const field of fields){if(!field.checkValidity()){field.reportValidity();return false;}}
    return true;
  }
  function showStep(step,scroll=true){
    if(step<1||step>5)return; currentStep=step; maxUnlocked=Math.max(maxUnlocked,step);
    sections.forEach((sec,i)=>sec.classList.toggle('active',i===step-1));
    nav.forEach((a,i)=>{a.classList.toggle('active',i===step-1);a.classList.toggle('completed',i<step);a.classList.toggle('locked',i>maxUnlocked-1);a.setAttribute('aria-current',i===step-1?'step':'false');});
    document.querySelectorAll('.summary-next').forEach(b=>b.textContent=step===5?'Submit Booking →':'Next Step →');
    document.querySelectorAll('.summary-actions').forEach(el=>el.style.display=step===5?'none':'grid');
    if(scroll) document.getElementById('step-'+step)?.scrollIntoView({behavior:'smooth',block:'start'});
  }
  function next(){if(!validateStep(currentStep))return;showStep(currentStep+1);}
  document.querySelectorAll('.next-step').forEach(b=>b.addEventListener('click',next));
  document.querySelectorAll('.prev-step').forEach(b=>b.addEventListener('click',()=>showStep(currentStep-1)));
  document.querySelectorAll('.summary-next').forEach(b=>b.addEventListener('click',next));
  nav.forEach((a,i)=>a.addEventListener('click',e=>{e.preventDefault(); if(i+1<=maxUnlocked) showStep(i+1); else {a.classList.add('locked');}}));
  venue.addEventListener('change',updateVenue);
  document.getElementById('other_venue_name')?.addEventListener('input',()=>{ if(venue.value==='other'){ updateVenue(); updateTotal(); }}); [room,document.getElementById('room_qty'),document.getElementById('nights'),...document.querySelectorAll('input[name^="facility_qty["]'),document.getElementById('event_title'),document.getElementById('expected_pax'),document.getElementById('category_id'),document.getElementById('start_date'),document.getElementById('start_time'),document.getElementById('end_date'),document.getElementById('end_time')].forEach(el=>el.addEventListener('input',updateTotal)); facilities.forEach(el=>el.addEventListener('change',updateTotal)); document.querySelectorAll('input[name^="facility_qty["]').forEach(el=>el.addEventListener('input',()=>{const id=el.dataset.facilityId;const cb=document.querySelector('input[name="facility_ids[]"][value="'+id+'"]');if(cb)cb.checked=Number(el.value||0)>0;updateTotal();}));
  foodCards.forEach(card=>{card.querySelector('.food-select')?.addEventListener('click',()=>syncFoodHiddenFromCard(card));card.querySelector('.food-card-pax')?.addEventListener('input',()=>{if(Number(food.value)===Number(card.dataset.menuId)){document.getElementById('food_pax').value=Math.max(0,Number(card.querySelector('.food-card-pax').value||0));updateTotal();}});card.querySelector('.food-details')?.addEventListener('click',()=>openFoodModal(card));});
  document.querySelectorAll('.food-dietary-check').forEach(c=>c.addEventListener('change',syncFoodNotes));document.getElementById('food_special_notes_ui')?.addEventListener('input',syncFoodNotes);
  function openFoodModal(card){const modal=document.getElementById('foodModal');document.getElementById('foodModalTitle').textContent=card.dataset.name||'Menu Details';document.getElementById('foodModalMeta').textContent=(card.dataset.type||'Menu Package')+' · RM '+money(card.dataset.rate)+' / pax';document.getElementById('foodModalDescription').textContent=card.dataset.description||'Menu package details are available from the current catering setup.';document.getElementById('foodModalImage').style.backgroundImage=card.dataset.image?'url("'+card.dataset.image+'")':'';modal.classList.add('open');modal.setAttribute('aria-hidden','false');}
  function closeFoodModal(){const modal=document.getElementById('foodModal');modal.classList.remove('open');modal.setAttribute('aria-hidden','true');} document.getElementById('foodModalClose')?.addEventListener('click',closeFoodModal);document.getElementById('foodModal')?.addEventListener('click',e=>{if(e.target.id==='foodModal')closeFoodModal();});
  function saveDraft(){
    dateTime();
    compileTimeline();
    syncFoodNotes();
    const title=document.getElementById('event_title').value.trim();
    const category=document.getElementById('category_id').value;
    const start=document.getElementById('start_date').value;
    const end=document.getElementById('end_date').value;
    const venueId=document.getElementById('venue_id').value;
    const missing=[];
    if(!title) missing.push('Event Name');
    if(!category) missing.push('Event Category');
    if(!start) missing.push('Start Date');
    if(!end) missing.push('End Date');
    if(!venueId) missing.push('Venue');
    if(missing.length){ alert('Please complete these core details before saving the draft:\n\n• '+missing.join('\n• ')); return; }
    if(!validateDates()) return;
    document.getElementById('booking_action').value='draft';
    form.requestSubmit();
  }
  document.querySelectorAll('.save-draft').forEach(b=>b.addEventListener('click',saveDraft));
  form.addEventListener('submit',e=>{dateTime();if(!validateDates()){e.preventDefault();return;}});

  if (<?= $draftId ?> > 0) { maxUnlocked=5; currentStep=1; }
  if(!Number(food.value||0))document.getElementById('food_pax').value=Number(document.getElementById('expected_pax').value||0);
  restoreFoodNotes();
  const initialFoodCard=foodCards.find(c=>Number(c.dataset.menuId)===Number(food.value||0));if(initialFoodCard){initialFoodCard.classList.add('selected');const b=initialFoodCard.querySelector('.food-select');if(b)b.textContent='Selected ✓';}
  const startDateInput=document.getElementById('start_date'), endDateInput=document.getElementById('end_date');
  startDateInput?.addEventListener('change',()=>{validateDates();dateTime();});
  endDateInput?.addEventListener('change',()=>{validateDates();dateTime();});
  validateDates();
  const requestedStep=Number(new URLSearchParams(location.search).get('step')||0);
  if(requestedStep>=1&&requestedStep<=5&&!restoredBooking) currentStep=requestedStep;
  showStep(currentStep,false); updateVenue(); updateTotal(); captureBookingState();
})();

  const eventDescription=document.getElementById('event_description');
  const eventDescriptionCount=document.getElementById('event_description_count');
  if(eventDescription && eventDescriptionCount){
    const syncDescriptionCount=()=>{eventDescriptionCount.textContent=String(eventDescription.value.length);};
    eventDescription.addEventListener('input',syncDescriptionCount); syncDescriptionCount();
  }
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

