<?php
require_once __DIR__.'/../../includes/bootstrap.php';
require_role('Administrator');
$pageTitle='Gallery Management';

// Do not auto-recreate deleted starter albums or repopulate albums whose photos were intentionally removed.
// Gallery content is managed explicitly by the administrator.

$uploadDir = dirname(__DIR__, 2) . '/assets/gallery';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

function gallery_slug(string $value): string {
    $value = trim(strtolower($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
    return trim($value, '-') ?: 'album';
}

function gallery_public_url(?string $path): string {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return url(ltrim($path, '/'));
}

function gallery_local_file(?string $path, string $uploadDir): ?string {
    if (!$path || preg_match('#^https?://#i', $path)) return null;
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    if (strpos($relative, 'assets/gallery/') !== 0) return null;
    $file = dirname(__DIR__, 2) . '/' . $relative;
    $realBase = realpath($uploadDir);
    $realFile = is_file($file) ? realpath($file) : null;
    if (!$realBase || !$realFile || strpos($realFile, $realBase . DIRECTORY_SEPARATOR) !== 0) return null;
    return $realFile;
}

function gallery_upload(array $file, string $uploadDir): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please choose a valid image file.');
    }
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) {
        throw new RuntimeException('Image is too large. Maximum allowed size is 8 MB.');
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload.');
    }
    $info = @getimagesize($tmp);
    if (!$info || empty($info['mime'])) {
        throw new RuntimeException('Only real image files are allowed.');
    }
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$info['mime']])) {
        throw new RuntimeException('Allowed formats: JPG, PNG, WEBP or GIF.');
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('The gallery upload folder could not be created.');
    }
    $name = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$info['mime']];
    $target = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('The image could not be saved to the gallery.');
    }
    return 'assets/gallery/' . $name;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf($_POST['csrf'] ?? null);
        $action = $_POST['action'] ?? '';

        if ($action === 'create_album') {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $slug = gallery_slug((string)($_POST['slug'] ?? $title));
            if ($title === '') throw new RuntimeException('Album title is required.');
            $check = db()->prepare('SELECT id FROM gallery_albums WHERE slug=? LIMIT 1');
            $check->execute([$slug]);
            if ($check->fetch()) throw new RuntimeException('An album with this name/slug already exists.');
            $s = db()->prepare('INSERT INTO gallery_albums(title,slug,description,cover_image,is_public) VALUES(?,?,?,?,1)');
            $s->execute([$title,$slug,$description,null]);
            $id = (int)db()->lastInsertId();
            audit('gallery_album_created','gallery_album',$id,null,['title'=>$title,'slug'=>$slug]);
            flash('success','Album created successfully. You can now add photos.');
            redirect('admin/gallery/index.php?album='.$id);
        }

        if ($action === 'update_album') {
            $albumId = (int)($_POST['album_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $isPublic = isset($_POST['is_public']) ? 1 : 0;
            if ($title === '') throw new RuntimeException('Album title is required.');
            $check = db()->prepare('SELECT id FROM gallery_albums WHERE slug=? AND id<>? LIMIT 1');
            $check->execute([gallery_slug($title), $albumId]);
            if ($check->fetch()) throw new RuntimeException('Another album already uses this title.');
            $oldStmt = db()->prepare('SELECT * FROM gallery_albums WHERE id=?');
            $oldStmt->execute([$albumId]); $oldAlbum = $oldStmt->fetch();
            if (!$oldAlbum) throw new RuntimeException('Album not found.');
            $newSlug = gallery_slug($title);
            db()->prepare('UPDATE gallery_albums SET title=?, slug=?, description=?, is_public=? WHERE id=?')->execute([$title,$newSlug,$description,$isPublic,$albumId]);
            audit('gallery_album_updated','gallery_album',$albumId,$oldAlbum,['title'=>$title,'slug'=>$newSlug,'description'=>$description,'is_public'=>$isPublic]);
            flash('success','Album details updated.');
            redirect('admin/gallery/index.php?album='.$albumId);
        }

        if ($action === 'delete_album') {
            $albumId = (int)($_POST['album_id'] ?? 0);
            $stmt = db()->prepare('SELECT * FROM gallery_albums WHERE id=? LIMIT 1');
            $stmt->execute([$albumId]);
            $albumToDelete = $stmt->fetch();
            if (!$albumToDelete) throw new RuntimeException('Album not found.');

            $photoStmt = db()->prepare('SELECT image_path FROM gallery_images WHERE album_id=?');
            $photoStmt->execute([$albumId]);
            $paths = $photoStmt->fetchAll(PDO::FETCH_COLUMN);

            db()->beginTransaction();
            try {
                $deleteStmt = db()->prepare('DELETE FROM gallery_albums WHERE id=?');
                $deleteStmt->execute([$albumId]);
                if ($deleteStmt->rowCount() !== 1) {
                    throw new RuntimeException('Album was not deleted. Please refresh and try again.');
                }
                $verify = db()->prepare('SELECT COUNT(*) FROM gallery_albums WHERE id=?');
                $verify->execute([$albumId]);
                if ((int)$verify->fetchColumn() !== 0) {
                    throw new RuntimeException('Album still exists in the database; deletion was not confirmed.');
                }
                db()->commit();
            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                throw $e;
            }

            foreach ($paths as $path) {
                $local = gallery_local_file((string)$path, $uploadDir);
                if ($local) @unlink($local);
            }
            audit('gallery_album_deleted','gallery_album',$albumId,$albumToDelete,null);
            $remainingId = (int)(db()->query('SELECT id FROM gallery_albums ORDER BY created_at DESC,id DESC LIMIT 1')->fetchColumn() ?: 0);
            flash('success','Album deleted successfully and database removal was verified.');
            redirect('admin/gallery/index.php'.($remainingId ? '?album='.$remainingId : ''));
        }

        if ($action === 'add_photo') {
            $albumId = (int)($_POST['album_id'] ?? 0);
            $caption = trim((string)($_POST['caption'] ?? ''));
            $album = db()->prepare('SELECT * FROM gallery_albums WHERE id=? LIMIT 1');
            $album->execute([$albumId]);
            $album = $album->fetch();
            if (!$album) throw new RuntimeException('Album not found.');
            $path = gallery_upload($_FILES['image'] ?? [], $uploadDir);
            $sort = (int)(db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM gallery_images WHERE album_id=' . $albumId)->fetchColumn());
            $s = db()->prepare('INSERT INTO gallery_images(album_id,image_path,caption,sort_order) VALUES(?,?,?,?)');
            $s->execute([$albumId,$path,$caption,$sort]);
            $imageId = (int)db()->lastInsertId();
            if (empty($album['cover_image'])) {
                db()->prepare('UPDATE gallery_albums SET cover_image=? WHERE id=?')->execute([$path,$albumId]);
            }
            audit('gallery_photo_added','gallery_image',$imageId,null,['album_id'=>$albumId,'image_path'=>$path,'caption'=>$caption]);
            flash('success','Photo added to “'.($album['title']).'”.');
            redirect('admin/gallery/index.php?album='.$albumId);
        }

        if ($action === 'update_photo') {
            $imageId = (int)($_POST['image_id'] ?? 0);
            $caption = trim((string)($_POST['caption'] ?? ''));
            $s = db()->prepare('SELECT gi.*,ga.title AS album_title FROM gallery_images gi JOIN gallery_albums ga ON ga.id=gi.album_id WHERE gi.id=? LIMIT 1');
            $s->execute([$imageId]);
            $old = $s->fetch();
            if (!$old) throw new RuntimeException('Photo not found.');
            $newPath = $old['image_path'];
            if (!empty($_FILES['image']['name'])) {
                $newPath = gallery_upload($_FILES['image'], $uploadDir);
            }
            db()->prepare('UPDATE gallery_images SET image_path=?, caption=? WHERE id=?')->execute([$newPath,$caption,$imageId]);
            if ($newPath !== $old['image_path'] && empty($old['image_path']) === false) {
                $oldFile = gallery_local_file($old['image_path'], $uploadDir);
                if ($oldFile) @unlink($oldFile);
                $albumCover = db()->prepare('SELECT cover_image FROM gallery_albums WHERE id=(SELECT album_id FROM gallery_images WHERE id=?)');
                $albumCover->execute([$imageId]);
                if ((string)$albumCover->fetchColumn() === (string)$old['image_path']) {
                    db()->prepare('UPDATE gallery_albums SET cover_image=? WHERE id=(SELECT album_id FROM gallery_images WHERE id=?)')->execute([$newPath,$imageId]);
                }
            }
            audit('gallery_photo_updated','gallery_image',$imageId,$old,['image_path'=>$newPath,'caption'=>$caption]);
            flash('success','Photo updated successfully.');
            redirect('admin/gallery/index.php?album='.(int)$old['album_id']);
        }

        if ($action === 'delete_photo') {
            $imageId = (int)($_POST['image_id'] ?? 0);
            $s = db()->prepare('SELECT * FROM gallery_images WHERE id=? LIMIT 1');
            $s->execute([$imageId]);
            $old = $s->fetch();
            if (!$old) throw new RuntimeException('Photo not found.');
            $albumId = (int)$old['album_id'];
            db()->prepare('DELETE FROM gallery_images WHERE id=?')->execute([$imageId]);
            $remaining = db()->prepare('SELECT id FROM gallery_images WHERE album_id=? ORDER BY sort_order ASC,id ASC');
            $remaining->execute([$albumId]);
            $renumber = db()->prepare('UPDATE gallery_images SET sort_order=? WHERE id=?');
            foreach ($remaining->fetchAll(PDO::FETCH_COLUMN) as $position => $remainingId) { $renumber->execute([$position + 1, (int)$remainingId]); }
            $oldFile = gallery_local_file($old['image_path'], $uploadDir);
            if ($oldFile) @unlink($oldFile);
            $cover = db()->prepare('SELECT cover_image FROM gallery_albums WHERE id=?');
            $cover->execute([$albumId]);
            if ((string)$cover->fetchColumn() === (string)$old['image_path']) {
                $next = db()->prepare('SELECT image_path FROM gallery_images WHERE album_id=? ORDER BY sort_order ASC,id ASC LIMIT 1');
                $next->execute([$albumId]);
                $nextPath = $next->fetchColumn() ?: null;
                db()->prepare('UPDATE gallery_albums SET cover_image=? WHERE id=?')->execute([$nextPath,$albumId]);
            }
            audit('gallery_photo_deleted','gallery_image',$imageId,$old,null);
            flash('success','Photo deleted successfully.');
            redirect('admin/gallery/index.php?album='.$albumId);
        }

        if ($action === 'set_cover') {
            $imageId = (int)($_POST['image_id'] ?? 0);
            $s = db()->prepare('SELECT * FROM gallery_images WHERE id=? LIMIT 1');
            $s->execute([$imageId]);
            $image = $s->fetch();
            if (!$image) throw new RuntimeException('Photo not found.');
            db()->prepare('UPDATE gallery_albums SET cover_image=? WHERE id=?')->execute([$image['image_path'],(int)$image['album_id']]);
            audit('gallery_cover_updated','gallery_album',(int)$image['album_id'],null,['cover_image'=>$image['image_path'],'image_id'=>$imageId]);
            flash('success','Album cover updated.');
            redirect('admin/gallery/index.php?album='.(int)$image['album_id']);
        }

        throw new RuntimeException('Unknown gallery action.');
    }
} catch (Throwable $e) {
    flash('error',$e->getMessage());
    $fallbackAlbum = (int)($_POST['album_id'] ?? $_GET['album'] ?? 0);
    redirect('admin/gallery/index.php'.($fallbackAlbum ? '?album='.$fallbackAlbum : ''));
}

$albums = db()->query('SELECT a.*, COUNT(i.id) AS image_count FROM gallery_albums a LEFT JOIN gallery_images i ON i.album_id=a.id GROUP BY a.id ORDER BY a.created_at DESC,a.title ASC')->fetchAll();
$selectedAlbumId = (int)($_GET['album'] ?? ($albums[0]['id'] ?? 0));
$selectedAlbum = null;
foreach ($albums as $a) { if ((int)$a['id'] === $selectedAlbumId) { $selectedAlbum = $a; break; } }
$images = [];
if ($selectedAlbum) {
    $s = db()->prepare('SELECT * FROM gallery_images WHERE album_id=? ORDER BY sort_order ASC,id ASC');
    $s->execute([$selectedAlbumId]);
    $images = $s->fetchAll();
}
$totalImages = array_sum(array_map(fn($a) => (int)$a['image_count'], $albums));
$publicAlbums = count(array_filter($albums, fn($a) => (int)$a['is_public'] === 1));
require __DIR__.'/../../includes/header.php';
?>

<div class="admin-shell">
  <aside class="sidebar"><?php require __DIR__.'/../sidebar.php'; ?></aside>

  <section class="admin-content">
    <div class="admin-top">
      <div>
        <div class="eyebrow">Content</div>
        <h1 class="admin-title">Gallery Management</h1>
        <p class="kicker">Add, replace, edit or remove photos shown on the public Gallery page.</p>
      </div>
      <div class="admin-actions">
        <button class="btn btn-gold" type="button" onclick="openGalleryModal('albumModal')">+ Create Album</button>
      </div>
    </div>

    <div class="admin-stat-row">
      <div class="admin-stat-card"><span>Albums</span><strong><?= count($albums) ?></strong><small><?= $publicAlbums ?> public albums</small></div>
      <div class="admin-stat-card"><span>Photos</span><strong><?= $totalImages ?></strong><small>Images stored in gallery</small></div>
      <div class="admin-stat-card"><span>Selected Album</span><strong><?= $selectedAlbum ? (int)$selectedAlbum['image_count'] : 0 ?></strong><small><?= $selectedAlbum ? e($selectedAlbum['title']) : 'Select an album' ?></small></div>
    </div>

    <div class="gallery-admin-layout">
      <div class="panel gallery-album-panel">
        <div class="gallery-admin-heading"><div><b>Albums</b><small>Choose an album to manage its photos.</small></div></div>
        <div class="gallery-admin-albums">
          <?php foreach ($albums as $a): ?>
            <a class="gallery-admin-album <?= (int)$a['id'] === $selectedAlbumId ? 'active' : '' ?>" href="<?= url('admin/gallery/index.php?album='.(int)$a['id']) ?>">
              <span class="gallery-admin-thumb">
                <?php if ($a['cover_image']): ?><img src="<?= e(gallery_public_url($a['cover_image'])) ?>" alt="">
                <?php else: ?><span>+</span><?php endif; ?>
              </span>
              <span class="gallery-admin-album-copy"><strong><?= e($a['title']) ?></strong><small><?= (int)$a['image_count'] ?> photos · <?= (int)$a['is_public'] ? 'Public' : 'Private' ?></small></span>
            </a>
          <?php endforeach; ?>
          <?php if (!$albums): ?><div class="gallery-admin-empty">No albums yet. Create your first album.</div><?php endif; ?>
        </div>
      </div>

      <div class="gallery-admin-main">
        <?php if ($selectedAlbum): ?>
          <div class="panel gallery-admin-toolbar">
            <div><div class="eyebrow">Album</div><h2><?= e($selectedAlbum['title']) ?></h2><p><?= e($selectedAlbum['description'] ?? '') ?></p></div>
            <div class="admin-actions" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
              <button class="btn btn-outline-dark" type="button" onclick="openGalleryModal('editAlbumModal')">Edit Album</button>
              <button class="btn btn-gold" type="button" onclick="openGalleryModal('photoModal')">+ Add Photo</button>
              <form method="post" style="display:inline-flex;margin:0" onsubmit="return confirm('Delete this album and all photos? This action cannot be undone.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_album">
                <input type="hidden" name="album_id" value="<?= (int)$selectedAlbumId ?>">
                <button class="btn btn-danger" type="submit">Delete Album</button>
              </form>
            </div>
          </div>

          <?php if (!$images): ?>
            <div class="panel gallery-admin-empty-large"><div class="empty-icon">▧</div><h3>No photos in this album yet.</h3><p>Upload the first image and it will appear automatically on the public Gallery page.</p><button class="btn btn-primary" type="button" onclick="openGalleryModal('photoModal')">Upload Photo</button></div>
          <?php else: ?>
            <div class="gallery-admin-grid">
              <?php foreach ($images as $photoIndex => $img): ?>
                <article class="gallery-admin-photo">
                  <div class="gallery-admin-photo-image"><img src="<?= e(gallery_public_url($img['image_path'])) ?>" alt="<?= e($img['caption'] ?? '') ?>"><span class="gallery-admin-photo-badge">#<?= $photoIndex + 1 ?></span></div>
                  <div class="gallery-admin-photo-body">
                    <div class="gallery-admin-caption"><?= e($img['caption'] ?: 'No caption') ?></div>
                    <?php if ((string)$selectedAlbum['cover_image'] === (string)$img['image_path']): ?><span class="badge badge-gold">Album cover</span><?php endif; ?>
                    <div class="gallery-admin-photo-actions">
                      <button class="btn btn-sm btn-outline-dark" type="button" onclick='openEditPhoto(<?= json_encode((int)$img['id']) ?>,<?= json_encode((string)$img['caption']) ?>)'>Edit</button>
                      <?php if ((string)$selectedAlbum['cover_image'] !== (string)$img['image_path']): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="set_cover"><input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>"><button class="btn btn-sm btn-outline-dark" type="submit">Set Cover</button></form><?php endif; ?>
                      <form method="post" onsubmit="return confirm('Delete this photo permanently?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_photo"><input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>"><button class="btn btn-sm btn-danger" type="submit">Delete</button></form>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="panel gallery-admin-empty-large"><div class="empty-icon">▧</div><h3>Create an album to start.</h3><p>Your album and photo changes will appear on the public Gallery automatically.</p><button class="btn btn-gold" type="button" onclick="openGalleryModal('albumModal')">Create Album</button></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<div class="modal" id="albumModal" aria-hidden="true"><div class="modal-card gallery-modal-card"><button class="modal-close" type="button" onclick="closeGalleryModal('albumModal')">×</button><div class="eyebrow">New Album</div><h2>Create Gallery Album</h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create_album"><div class="field full"><label>Album title</label><input name="title" required placeholder="e.g. Botanical Garden"></div><div class="field full"><label>Slug (optional)</label><input name="slug" placeholder="botanical-garden"></div><div class="field full"><label>Description</label><textarea name="description" rows="4" placeholder="Short description shown in the gallery."></textarea></div><div class="field full"><button class="btn btn-gold" type="submit">Create Album</button></div></form></div></div>

<div class="modal" id="editAlbumModal" aria-hidden="true"><div class="modal-card gallery-modal-card"><button class="modal-close" type="button" onclick="closeGalleryModal('editAlbumModal')">×</button><div class="eyebrow">Album Settings</div><h2>Edit Album</h2><form method="post" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_album"><input type="hidden" name="album_id" value="<?= (int)$selectedAlbumId ?>"><div class="field full"><label>Album title</label><input name="title" required maxlength="150" value="<?= e($selectedAlbum['title'] ?? '') ?>"></div><div class="field full"><label>Description</label><textarea name="description" rows="4"><?= e($selectedAlbum['description'] ?? '') ?></textarea></div><div class="field full"><label><input type="checkbox" name="is_public" value="1" <?= !empty($selectedAlbum['is_public']) ? 'checked' : '' ?>> Visible on public Gallery</label></div><div class="field full"><button class="btn btn-gold" type="submit">Save Album</button></div></form></div></div>



<div class="modal" id="photoModal" aria-hidden="true"><div class="modal-card gallery-modal-card"><button class="modal-close" type="button" onclick="closeGalleryModal('photoModal')">×</button><div class="eyebrow">Add Photo</div><h2>Upload to <?= $selectedAlbum ? e($selectedAlbum['title']) : 'Gallery' ?></h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_photo"><input type="hidden" name="album_id" value="<?= (int)$selectedAlbumId ?>"><div class="field full"><label>Photo</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" required><small>JPG, PNG, WEBP or GIF · maximum 8 MB</small></div><div class="field full"><label>Caption</label><input name="caption" maxlength="255" placeholder="Describe the photo"></div><div class="field full"><button class="btn btn-gold" type="submit">Upload Photo</button></div></form></div></div>

<div class="modal" id="editPhotoModal" aria-hidden="true"><div class="modal-card gallery-modal-card"><button class="modal-close" type="button" onclick="closeGalleryModal('editPhotoModal')">×</button><div class="eyebrow">Edit Photo</div><h2>Update Gallery Photo</h2><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_photo"><input type="hidden" name="image_id" id="editImageId"><div class="field full"><label>Replace image (optional)</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"><small>Leave empty to keep the current image.</small></div><div class="field full"><label>Caption</label><input name="caption" id="editCaption" maxlength="255"></div><div class="field full"><button class="btn btn-gold" type="submit">Save Changes</button></div></form></div></div>

<script>
function openGalleryModal(id){var el=document.getElementById(id);if(el){el.classList.add('show');el.setAttribute('aria-hidden','false');}}
function closeGalleryModal(id){var el=document.getElementById(id);if(el){el.classList.remove('show');el.setAttribute('aria-hidden','true');}}
function openEditPhoto(id,caption){document.getElementById('editImageId').value=id;document.getElementById('editCaption').value=caption||'';openGalleryModal('editPhotoModal');}
document.querySelectorAll('.modal').forEach(function(m){m.addEventListener('click',function(e){if(e.target===m) m.classList.remove('show');});});
document.addEventListener('keydown',function(e){if(e.key==='Escape') document.querySelectorAll('.modal.show').forEach(function(m){m.classList.remove('show');});});
</script>

<?php require __DIR__.'/../../includes/footer.php'; ?>
