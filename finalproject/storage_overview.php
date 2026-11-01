<?php
// storage_overview.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
checkAccess(['Recorder','Admin']);

$locations = $pdo->query("\n    SELECT sl.*,\n           COUNT(CASE WHEN f.status = 'Stored' THEN 1 END) AS occupied\n    FROM storage_locations sl\n    LEFT JOIN found_items f ON f.storage_location_id = sl.location_id\n    GROUP BY sl.location_id\n    ORDER BY sl.location_name ASC\n")->fetchAll(PDO::FETCH_ASSOC);

$storedItemsStmt = $pdo->query("
    SELECT
        f.item_id,
        f.item_code,
        f.title,
        f.location_found,
        f.date_found,
        f.expiry_date,
        f.image_path,
        f.remarks,
        f.storage_location_id,
        c.category_name
    FROM found_items f
    LEFT JOIN categories c ON c.category_id = f.category_id
    WHERE f.status = 'Stored'
      AND f.storage_location_id IS NOT NULL
    ORDER BY f.created_at DESC, f.item_id DESC
");
$storedItems = $storedItemsStmt->fetchAll(PDO::FETCH_ASSOC);

$itemsByLocation = [];
foreach ($storedItems as $item) {
    $locationId = (int)$item['storage_location_id'];
    $itemsByLocation[$locationId][] = $item;
}

$totalCapacity = 0; $totalOccupied = 0;
foreach ($locations as $loc) { $totalCapacity += (int)$loc['capacity']; $totalOccupied += (int)$loc['occupied']; }
$overallPercent = $totalCapacity > 0 ? min(100, round(($totalOccupied/$totalCapacity)*100)) : 0;
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Storage Overview - Resort L&amp;F</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
:root{--ink:#10233f;--muted:#65748b;--bg:#f3f7fc;--line:#e5edf6;--blue:#0d6efd}
body{background:var(--bg);color:var(--ink);font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.navbar{min-height:70px;box-shadow:0 3px 18px rgba(16,35,63,.12)}
.page-wrap{max-width:1500px}
.kicker{color:var(--blue);font-size:.78rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.page-title{font-weight:800;letter-spacing:-.02em}.muted{color:var(--muted)}
.legend,.summary,.loc-card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 8px 25px rgba(18,60,105,.07)}
.legend-dot{width:20px;height:20px;border-radius:50%;display:inline-block}
.available{background:#16a765}.nearly{background:#ffb20f}.full{background:#ef233c}
.loc-card{height:100%;transition:.16s;cursor:pointer}.loc-card:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(18,60,105,.12);border-color:#b9d5ff}
.loc-card:focus{outline:3px solid rgba(13,110,253,.2)}
.loc-icon{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:#eaf2ff;color:#0d6efd}
.loc-title{font-size:1.08rem;font-weight:800}.loc-zone,.meta{font-size:.76rem;color:#65748b}.desc{min-height:24px;color:#5f6f84}
.badge-status{border-radius:7px;padding:.38rem .62rem;font-size:.72rem;font-weight:800;white-space:nowrap}
.progress{height:10px;border-radius:999px;background:#eef2f6}.progress-bar{border-radius:999px}
.summary{border-radius:16px}.summary-icon{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;background:#eaf2ff;color:#0d6efd}
.view-items{color:#0d6efd;font-size:.82rem;font-weight:700}
.item-card{border:1px solid #e5edf6;border-radius:15px;background:#fff}
.item-thumb{width:72px;height:72px;border-radius:12px;object-fit:cover;background:#eef3f8}
.modal-content{border:0;border-radius:22px;box-shadow:0 25px 70px rgba(16,35,63,.22)}
.detail-thumb{width:120px;height:120px;border-radius:16px;object-fit:cover;background:#eef3f8}
.detail-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#7a8798;font-weight:800}
.detail-value{font-weight:600;color:#20324b}
.image-preview-btn{position:relative;display:block;border-radius:14px;cursor:zoom-in}
.image-preview-btn .item-thumb{display:block;transition:transform .18s ease,box-shadow .18s ease}
.image-preview-btn:hover .item-thumb{transform:scale(1.025);box-shadow:0 8px 22px rgba(13,110,253,.18)}
.image-click-hint{display:block;font-size:.68rem;color:#0d6efd;font-weight:700;text-align:center;margin-top:5px}
.image-viewer{background:rgba(9,17,29,.96);border:0;border-radius:20px;overflow:hidden}
.image-viewer .modal-header{border-bottom:1px solid rgba(255,255,255,.12)}
.image-viewer .modal-body{min-height:65vh;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.2)}
.image-viewer-img{max-width:100%;max-height:65vh;object-fit:contain;border-radius:10px}
.image-viewer-title{color:#fff}
.image-viewer-subtitle{color:#b9c5d6}

@media(max-width:767.98px){.legend .legend-item{width:50%}.page-title{font-size:1.7rem}.detail-thumb{width:90px;height:90px}}
</style></head><body>
<nav class="navbar navbar-dark bg-dark sticky-top">
<div class="container-fluid px-3 px-lg-4">
<a class="navbar-brand fw-bold" href="<?= $_SESSION['user_role']==='Admin'?'admin_dashboard.php':'recorder_dashboard.php'; ?>"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Resort L&amp;F</a>
<a href="<?= $_SESSION['user_role']==='Admin'?'admin_dashboard.php':'recorder_dashboard.php'; ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard</a>
</div></nav>

<div class="container-fluid page-wrap px-3 px-lg-4 py-4 py-lg-5">
<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-4 mb-4">
<div><div class="kicker">Storage Management</div><h1 class="page-title h2 mb-1">Storage Overview</h1><p class="muted mb-0">Monitor storage locations and click any location to view the items stored there.</p></div>
<div class="legend px-3 px-lg-4 py-3"><div class="d-flex flex-wrap gap-3 gap-lg-4">
<div class="legend-item d-flex align-items-center gap-2"><span class="legend-dot available"></span><div><b class="small d-block">Available</b><span class="meta">0% – 79%</span></div></div>
<div class="legend-item d-flex align-items-center gap-2"><span class="legend-dot nearly"></span><div><b class="small d-block">Nearly Full</b><span class="meta">80% – 99%</span></div></div>
<div class="legend-item d-flex align-items-center gap-2"><span class="legend-dot full"></span><div><b class="small d-block">Full</b><span class="meta">100%</span></div></div>
</div></div></div>

<div class="row g-3 mb-4">
<div class="col-6 col-lg-4"><div class="summary p-3 h-100 d-flex align-items-center gap-3"><div class="summary-icon"><i class="fa-solid fa-warehouse"></i></div><div><div class="small text-muted">Total Locations</div><div class="fw-bold fs-5"><?= count($locations); ?></div></div></div></div>
<div class="col-6 col-lg-4"><div class="summary p-3 h-100 d-flex align-items-center gap-3"><div class="summary-icon"><i class="fa-solid fa-box-open"></i></div><div><div class="small text-muted">Total Stored Items</div><div class="fw-bold fs-5"><?= $totalOccupied; ?></div></div></div></div>
<div class="col-12 col-lg-4"><div class="summary p-3 h-100 d-flex align-items-center gap-3"><div class="summary-icon"><i class="fa-solid fa-chart-simple"></i></div><div class="flex-grow-1"><div class="d-flex justify-content-between"><div class="small text-muted">Overall Usage</div><div class="fw-bold"><?= $overallPercent; ?>%</div></div><div class="progress mt-2"><div class="progress-bar available" style="width:<?= $overallPercent; ?>%"></div></div><div class="meta mt-1"><?= $totalOccupied; ?> used / <?= $totalCapacity; ?> total spaces</div></div></div></div>
</div>

<div class="row g-4">
<?php foreach($locations as $loc):
$cap=max(1,(int)$loc['capacity']); $occ=(int)$loc['occupied']; $pct=min(100,round(($occ/$cap)*100));
if($pct>=100){$label='Full';$sc='full';$icon='fa-lock';}
elseif($pct>=80){$label='Nearly Full';$sc='nearly';$icon='fa-triangle-exclamation';}
else{$label='Available';$sc='available';$icon='fa-box-archive';}
$locationId=(int)$loc['location_id'];
$locationItems=$itemsByLocation[$locationId]??[];
$modalId='storageModal'.$locationId;
?>
<div class="col-sm-6 col-xl-3">
<div class="card loc-card p-3 p-lg-4" role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#<?= $modalId; ?>" aria-label="View items stored in <?= htmlspecialchars($loc['location_name']); ?>">
<div class="d-flex justify-content-between align-items-start gap-2 mb-3">
<div class="d-flex align-items-center gap-3"><div class="loc-icon"><i class="fa-solid <?= $icon; ?>"></i></div><div><div class="loc-title"><?= htmlspecialchars($loc['location_name']); ?></div><div class="loc-zone"><?= htmlspecialchars($loc['building_zone']??'General Storage'); ?></div></div></div>
<span class="badge-status <?= $sc; ?>"><?= $label; ?></span>
</div>
<div class="desc small mb-4"><?= htmlspecialchars($loc['description']??''); ?></div>
<div class="d-flex justify-content-between mb-2"><span>Occupancy</span><strong><?= $occ; ?> / <?= $cap; ?></strong></div>
<div class="progress"><div class="progress-bar <?= $sc; ?>" style="width:<?= $pct; ?>%"></div></div>
<div class="d-flex justify-content-between mt-2 meta"><span><?= $pct; ?>% used</span><span><?= max(0,$cap-$occ); ?> <?= $cap-$occ==1?'space':'spaces'; ?> free</span></div>
<div class="view-items mt-3"><i class="fa-solid fa-list me-1"></i><?= count($locationItems); ?> <?= count($locationItems)===1?'item':'items'; ?> stored <i class="fa-solid fa-chevron-right float-end mt-1"></i></div>
</div>
</div>

<div class="modal fade" id="<?= $modalId; ?>" tabindex="-1" aria-labelledby="<?= $modalId; ?>Label" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
<div class="modal-content">
<div class="modal-header border-0 px-4 pt-4">
<div><div class="kicker mb-1">Storage Location</div><h4 class="fw-bold mb-1" id="<?= $modalId; ?>Label"><?= htmlspecialchars($loc['location_name']); ?></h4><div class="small muted"><?= htmlspecialchars($loc['building_zone']??'General Storage'); ?></div></div>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body px-4 pb-4">
<div class="summary p-3 mb-4">
<div class="d-flex justify-content-between"><span>Occupancy</span><strong><?= $occ; ?> / <?= $cap; ?></strong></div>
<div class="progress mt-2"><div class="progress-bar <?= $sc; ?>" style="width:<?= $pct; ?>%"></div></div>
<div class="d-flex justify-content-between mt-2 meta"><span><?= $pct; ?>% used</span><span><?= max(0,$cap-$occ); ?> spaces free</span></div>
</div>
<div class="d-flex justify-content-between align-items-center mb-3"><h5 class="fw-bold mb-0">Items Stored in <?= htmlspecialchars($loc['location_name']); ?> <span class="text-muted">(<?= count($locationItems); ?>)</span></h5></div>
<?php if(empty($locationItems)): ?>
<div class="text-center py-5 text-muted"><i class="fa-solid fa-box-open fa-2x mb-3"></i><div class="fw-semibold">No items currently stored here.</div></div>
<?php else: foreach($locationItems as $item):
$image=trim((string)($item['image_path']??''));
if($image!=='' && !preg_match('#^(https?:)?//#i',$image) && !str_starts_with($image,'/') && !str_starts_with($image,'uploads/')){$image='uploads/'.ltrim($image,'/\\');}
?>
<div class="item-card p-3 mb-3">
<div class="d-flex flex-column flex-md-row gap-3">
<?php if($image!==''): ?>
<button type="button" class="image-preview-btn p-0 border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#itemImageModal" data-image="<?= htmlspecialchars($image); ?>" data-code="<?= htmlspecialchars($item['item_code']); ?>" data-title="<?= htmlspecialchars($item['title']); ?>" title="Click to enlarge image" aria-label="View image of <?= htmlspecialchars($item['title']); ?>">
    <img src="<?= htmlspecialchars($image); ?>" class="item-thumb" alt="<?= htmlspecialchars($item['title']); ?>">
    <span class="image-click-hint"><i class="fa-solid fa-magnifying-glass-plus me-1"></i>Click to enlarge</span>
</button>
<?php else: ?>
<div class="item-thumb d-grid place-items-center text-muted"><i class="fa-solid fa-image fa-xl"></i></div>
<?php endif; ?>
<div class="flex-grow-1">
<div class="d-flex justify-content-between gap-2"><div><div class="fw-bold"><?= htmlspecialchars($item['item_code']); ?></div><div class="fs-5 fw-semibold"><?= htmlspecialchars($item['title']); ?></div></div><span class="badge text-bg-success align-self-start">Stored</span></div>
<div class="row g-3 mt-1">
<div class="col-sm-6"><div class="detail-label">Category</div><div class="detail-value"><?= htmlspecialchars($item['category_name']??'Not specified'); ?></div></div>
<div class="col-sm-6"><div class="detail-label">Found Location</div><div class="detail-value"><?= htmlspecialchars($item['location_found']??'Not specified'); ?></div></div>
<div class="col-sm-6"><div class="detail-label">Date Found</div><div class="detail-value"><?= !empty($item['date_found'])?date('d M Y',strtotime($item['date_found'])):'Not specified'; ?></div></div>
<div class="col-sm-6"><div class="detail-label">Expiry Date</div><div class="detail-value"><?= !empty($item['expiry_date'])?date('d M Y',strtotime($item['expiry_date'])):'Not specified'; ?></div></div>
<?php if(!empty($item['remarks'])): ?><div class="col-12"><div class="detail-label">Remarks</div><div class="detail-value"><?= nl2br(htmlspecialchars($item['remarks'])); ?></div></div><?php endif; ?>
</div>
</div>
</div>
</div>
<?php endforeach; endif; ?>
</div>
<div class="modal-footer border-0 px-4 pb-4"><button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Close</button></div>
</div></div></div>
<?php endforeach; ?>
</div>
</div>

<div class="modal fade" id="itemImageModal" tabindex="-1" aria-labelledby="itemImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content image-viewer">
            <div class="modal-header px-4 py-3">
                <div>
                    <div class="fw-bold image-viewer-title" id="itemImageModalLabel">Item Image</div>
                    <div class="small image-viewer-subtitle" id="itemImageSubtitle"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-lg-4">
                <img id="previewItemImage" class="image-viewer-img" src="" alt="Item image">
            </div>
            <div class="modal-footer border-0 px-4 py-3">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const itemImageModal=document.getElementById('itemImageModal');
if(itemImageModal){
    itemImageModal.addEventListener('show.bs.modal',event=>{
        const button=event.relatedTarget;
        if(!button) return;
        document.getElementById('previewItemImage').src=button.dataset.image||'';
        document.getElementById('previewItemImage').alt=button.dataset.title||'Item image';
        document.getElementById('itemImageSubtitle').textContent=(button.dataset.code||'')+' — '+(button.dataset.title||'');
    });
    itemImageModal.addEventListener('hidden.bs.modal',()=>{
        document.getElementById('previewItemImage').src='';
    });
}
document.querySelectorAll('.loc-card').forEach(card=>{
    card.addEventListener('keydown',e=>{
        if(e.key==='Enter'||e.key===' '){
            e.preventDefault();
            const target=document.querySelector(card.dataset.bsTarget);
            if(target) bootstrap.Modal.getOrCreateInstance(target).show();
        }
    });
});
</script>
</body></html>
