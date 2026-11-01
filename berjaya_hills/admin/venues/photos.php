<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator');
$pageTitle='Venue Photos';

$venueId=(int)($_GET['venue'] ?? $_POST['venue_id'] ?? 0);
if($venueId<=0){ redirect('admin/venues/index.php'); }

$uploadDir=dirname(__DIR__,2).'/assets/venue-images';
if(!is_dir($uploadDir)){ @mkdir($uploadDir,0755,true); }

function venue_photo_public_url(?string $path): string {
    if(!$path) return '';
    if(preg_match('#^https?://#i',$path)) return $path;
    return url(ltrim($path,'/'));
}
function venue_photo_local_file(?string $path,string $uploadDir): ?string {
    if(!$path || preg_match('#^https?://#i',$path)) return null;
    $relative=ltrim(str_replace('\\','/',$path),'/');
    if(strpos($relative,'assets/venue-images/')!==0) return null;
    $file=dirname(__DIR__,2).'/'.$relative;
    $base=realpath($uploadDir); $real=is_file($file)?realpath($file):null;
    if(!$base || !$real || strpos($real,$base.DIRECTORY_SEPARATOR)!==0) return null;
    return $real;
}
function venue_photo_upload(array $file,string $uploadDir): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Please choose a valid image file.');
    if(($file['size']??0)>8*1024*1024) throw new RuntimeException('Image is too large. Maximum allowed size is 8 MB.');
    $tmp=$file['tmp_name']??'';
    if(!is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');
    $info=@getimagesize($tmp);
    if(!$info || empty($info['mime'])) throw new RuntimeException('Only real image files are allowed.');
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!isset($allowed[$info['mime']])) throw new RuntimeException('Allowed formats: JPG, PNG, WEBP or GIF.');
    if(!is_dir($uploadDir) && !@mkdir($uploadDir,0755,true)) throw new RuntimeException('The venue photo folder could not be created.');
    $name=date('YmdHis').'_'.bin2hex(random_bytes(5)).'.'.$allowed[$info['mime']];
    $target=rtrim($uploadDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
    if(!move_uploaded_file($tmp,$target)) throw new RuntimeException('The image could not be saved.');
    return 'assets/venue-images/'.$name;
}

try {
    $vst=db()->prepare('SELECT v.*,l.name AS location_name FROM venues v LEFT JOIN locations l ON l.id=v.location_id WHERE v.id=? LIMIT 1');
    $vst->execute([$venueId]);
    $venue=$vst->fetch();
    if(!$venue) throw new RuntimeException('Venue not found.');

    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf($_POST['csrf']??null);
        $action=$_POST['action']??'';

        if($action==='add_photo'){
            $caption=trim((string)($_POST['alt_text']??''));
            $path=venue_photo_upload($_FILES['image']??[],$uploadDir);
            $q=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM venue_images WHERE venue_id=?'); $q->execute([$venueId]); $sort=(int)$q->fetchColumn();
            $q=db()->prepare('SELECT COUNT(*) FROM venue_images WHERE venue_id=?'); $q->execute([$venueId]); $count=(int)$q->fetchColumn();
            $primary=$count===0?1:0;
            $s=db()->prepare('INSERT INTO venue_images(venue_id,image_path,alt_text,sort_order,is_primary) VALUES(?,?,?,?,?)');
            $s->execute([$venueId,$path,$caption,$sort,$primary]);
            $id=(int)db()->lastInsertId();
            audit('venue_photo_added','venue_image',$id,null,['venue_id'=>$venueId,'image_path'=>$path,'alt_text'=>$caption,'is_primary'=>$primary]);
            flash('success','Photo added to “'.$venue['name'].'”.');
            redirect('admin/venues/photos.php?venue='.$venueId);
        }

        if($action==='update_photo'){
            $id=(int)($_POST['image_id']??0);
            $q=db()->prepare('SELECT * FROM venue_images WHERE id=? AND venue_id=? LIMIT 1'); $q->execute([$id,$venueId]); $old=$q->fetch();
            if(!$old) throw new RuntimeException('Venue photo not found.');
            $alt=trim((string)($_POST['alt_text']??''));
            $newPath=$old['image_path'];
            if(!empty($_FILES['image']['name'])) $newPath=venue_photo_upload($_FILES['image'],$uploadDir);
            $sort=max(0,(int)($_POST['sort_order']??$old['sort_order']));
            db()->prepare('UPDATE venue_images SET image_path=?,alt_text=?,sort_order=? WHERE id=? AND venue_id=?')->execute([$newPath,$alt,$sort,$id,$venueId]);
            if($newPath!==$old['image_path']){ $oldFile=venue_photo_local_file($old['image_path'],$uploadDir); if($oldFile) @unlink($oldFile); }
            audit('venue_photo_updated','venue_image',$id,$old,['venue_id'=>$venueId,'image_path'=>$newPath,'alt_text'=>$alt,'sort_order'=>$sort]);
            flash('success','Venue photo updated.');
            redirect('admin/venues/photos.php?venue='.$venueId);
        }

        if($action==='delete_photo'){
            $id=(int)($_POST['image_id']??0);
            $q=db()->prepare('SELECT * FROM venue_images WHERE id=? AND venue_id=? LIMIT 1'); $q->execute([$id,$venueId]); $old=$q->fetch();
            if(!$old) throw new RuntimeException('Venue photo not found.');
            db()->prepare('DELETE FROM venue_images WHERE id=? AND venue_id=?')->execute([$id,$venueId]);
            $oldFile=venue_photo_local_file($old['image_path'],$uploadDir); if($oldFile) @unlink($oldFile);
            if((int)$old['is_primary']===1){ $q=db()->prepare('SELECT id FROM venue_images WHERE venue_id=? ORDER BY sort_order ASC,id ASC LIMIT 1'); $q->execute([$venueId]); $next=$q->fetchColumn(); if($next){db()->prepare('UPDATE venue_images SET is_primary=1 WHERE id=?')->execute([(int)$next]);} }
            audit('venue_photo_deleted','venue_image',$id,$old,null);
            flash('success','Venue photo deleted.');
            redirect('admin/venues/photos.php?venue='.$venueId);
        }

        if($action==='set_primary'){
            $id=(int)($_POST['image_id']??0);
            $q=db()->prepare('SELECT * FROM venue_images WHERE id=? AND venue_id=? LIMIT 1'); $q->execute([$id,$venueId]); $img=$q->fetch();
            if(!$img) throw new RuntimeException('Venue photo not found.');
            db()->prepare('UPDATE venue_images SET is_primary=0 WHERE venue_id=?')->execute([$venueId]);
            db()->prepare('UPDATE venue_images SET is_primary=1 WHERE id=? AND venue_id=?')->execute([$id,$venueId]);
            audit('venue_primary_photo_updated','venue',$venueId,null,['image_id'=>$id,'image_path'=>$img['image_path']]);
            flash('success','Primary venue photo updated.');
            redirect('admin/venues/photos.php?venue='.$venueId);
        }

        throw new RuntimeException('Unknown venue photo action.');
    }
} catch(Throwable $e){
    flash('error',$e->getMessage());
    redirect('admin/venues/photos.php?venue='.$venueId);
}

$q=db()->prepare('SELECT * FROM venue_images WHERE venue_id=? ORDER BY is_primary DESC,sort_order ASC,id ASC');
$q->execute([$venueId]); $images=$q->fetchAll();
require __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell">
  <aside class="sidebar"><?php require __DIR__.'/../sidebar.php'; ?></aside>
  <section class="admin-content">
    <div class="admin-top">
      <div>
        <div class="eyebrow">Venue Content</div>
        <h1 class="admin-title">Venue Photos</h1>
        <p class="kicker">Manage the photos displayed on the Venues cards and venue detail page.</p>
      </div>
      <div class="admin-actions"><a class="btn btn-outline-dark" href="<?=url('admin/venues/index.php')?>">← Back to Venues</a><button class="btn btn-gold" type="button" onclick="openVenuePhotoModal('addPhotoModal')">+ Add Photo</button></div>
    </div>

    <div class="panel venue-photo-admin-head">
      <div class="venue-photo-admin-cover">
        <?php $hero=venue_image_url($images[0]['image_path']??null,$venue['name']); ?>
        <?php if($hero): ?><img src="<?=e($hero)?>" alt="<?=e($venue['name'])?>"><?php else: ?><span>BH</span><?php endif; ?>
      </div>
      <div><div class="eyebrow">Selected Venue</div><h2><?=e($venue['name'])?></h2><p><?=e($venue['description']??'')?></p><div class="venue-photo-meta"><span class="tag"><?=e($venue['venue_type']?:'Venue')?></span><span class="badge badge-gold"><?=count($images)?> photos</span></div></div>
    </div>

    <?php if(!$images): ?>
      <div class="panel gallery-admin-empty-large"><div class="empty-icon">▧</div><h3>No venue photos yet.</h3><p>Add the first photo. It will become the primary image automatically and appear on the public Venues page.</p><button class="btn btn-gold" type="button" onclick="openVenuePhotoModal('addPhotoModal')">Upload Photo</button></div>
    <?php else: ?>
      <div class="venue-photo-admin-grid">
      <?php foreach($images as $img): $src=venue_photo_public_url($img['image_path']); ?>
        <article class="venue-photo-admin-card">
          <div class="venue-photo-admin-image"><img src="<?=e($src)?>" alt="<?=e($img['alt_text']?:$venue['name'])?>"><span class="venue-photo-order">#<?=((int)$img['sort_order'])?></span><?php if((int)$img['is_primary']===1): ?><span class="venue-photo-primary">Primary</span><?php endif; ?></div>
          <div class="venue-photo-admin-body"><strong><?=e($img['alt_text']?:'No description')?></strong><div class="venue-photo-admin-actions">
            <button class="btn btn-sm btn-outline-dark" type="button" onclick='openEditVenuePhoto(<?=json_encode((int)$img['id'])?>,<?=json_encode((string)$img['alt_text'])?>,<?=json_encode((int)$img['sort_order'])?>)'>Edit</button>
            <?php if((int)$img['is_primary']!==1): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="set_primary"><input type="hidden" name="venue_id" value="<?=$venueId?>"><input type="hidden" name="image_id" value="<?=((int)$img['id'])?>"><button class="btn btn-sm btn-outline-dark" type="submit">Set Primary</button></form><?php endif; ?>
            <form method="post" onsubmit="return confirm('Delete this venue photo permanently?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_photo"><input type="hidden" name="venue_id" value="<?=$venueId?>"><input type="hidden" name="image_id" value="<?=((int)$img['id'])?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
          </div></div>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<div class="modal" id="addPhotoModal" aria-hidden="true"><div class="modal-card gallery-modal-card"><button class="modal-close" type="button" onclick="closeVenuePhotoModal('addPhotoModal')">×</button><div class="eyebrow">Add Venue Photo</div><h2>Upload to <?=e($venue['name'])?></h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add_photo"><input type="hidden" name="venue_id" value="<?=$venueId?>"><div class="field full"><label>Photo</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required><small>JPG, PNG, WEBP or GIF · maximum 8 MB</small></div><div class="field full"><label>Photo description / alt text</label><input name="alt_text" maxlength="190" placeholder="e.g. Main ballroom setup"></div><div class="field full"><button class="btn btn-gold" type="submit">Upload Photo</button></div></form></div></div>

<div class="modal" id="editPhotoModal" aria-hidden="true"><div class="modal-card gallery-modal-card"><button class="modal-close" type="button" onclick="closeVenuePhotoModal('editPhotoModal')">×</button><div class="eyebrow">Edit Venue Photo</div><h2>Update Photo</h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="update_photo"><input type="hidden" name="venue_id" value="<?=$venueId?>"><input type="hidden" name="image_id" id="venueEditImageId"><div class="field full"><label>Replace image (optional)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><small>Leave empty to keep the current photo.</small></div><div class="field full"><label>Photo description / alt text</label><input name="alt_text" id="venueEditAlt" maxlength="190"></div><div class="field"><label>Sort order</label><input type="number" min="0" name="sort_order" id="venueEditSort"></div><div class="field full"><button class="btn btn-gold" type="submit">Save Changes</button></div></form></div></div>
<script>
function openVenuePhotoModal(id){var e=document.getElementById(id);if(e){e.classList.add('show');e.setAttribute('aria-hidden','false');}}
function closeVenuePhotoModal(id){var e=document.getElementById(id);if(e){e.classList.remove('show');e.setAttribute('aria-hidden','true');}}
function openEditVenuePhoto(id,alt,sort){document.getElementById('venueEditImageId').value=id;document.getElementById('venueEditAlt').value=alt||'';document.getElementById('venueEditSort').value=sort||0;openVenuePhotoModal('editPhotoModal');}
document.querySelectorAll('.modal').forEach(function(m){m.addEventListener('click',function(e){if(e.target===m)m.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal.show').forEach(function(m){m.classList.remove('show');});});
</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
