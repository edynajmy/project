<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$profileError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['display_name'] ?? ''));
    $tagline = trim((string)($_POST['tagline'] ?? ''));
    if ($name === '' || mb_strlen($name) > 80 || mb_strlen($tagline) > 160) {
        $profileError = 'Please enter a display name (up to 80 characters) and tagline (up to 160 characters).';
    } else {
        $avatarPath = $profileSettings['avatar_path'] ?? null;
        if (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['avatar'];
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
                $profileError = 'Avatar upload failed. Please use an image under 5 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime]) || !is_uploaded_file($file['tmp_name'])) {
                    $profileError = 'Please choose a JPG, PNG or WebP image.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/profile';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $profileError = 'Could not create the profile image folder.';
                    } else {
                        $filename = 'avatar_' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
                        if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                            $profileError = 'Could not save the profile image.';
                        } else {
                            $avatarPath = 'uploads/profile/' . $filename;
                        }
                    }
                }
            }
        }
        if ($profileError === '') {
            $stmt = $pdo->prepare('UPDATE profile_settings SET display_name = ?, tagline = ?, avatar_path = ? WHERE id = 1');
            $stmt->execute([$name, $tagline, $avatarPath]);
            header('Location: ' . app_url('pages/profile.php?saved=1'));
            exit;
        }
    }
}
$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
$displayName = (string)$profileSettings['display_name'];
$taglineText = (string)$profileSettings['tagline'];
$avatarPath = (string)($profileSettings['avatar_path'] ?? '');
$avatarUrl = $avatarPath !== '' ? app_url($avatarPath) : '';
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($displayName, 0, 1)) : strtoupper(substr($displayName, 0, 1));
?>
<section class="profile-page">
  <div class="page-heading"><div><span class="eyebrow">PERSONAL SPACE</span><h1>My Profile</h1><p>Personalise your EdyLife workspace.</p></div></div>
  <?php if (isset($_GET['saved'])): ?><div class="flash-message" role="status">Profile updated successfully.</div><?php endif; ?>
  <?php if ($profileError !== ''): ?><div class="flash-message profile-error" role="alert"><?= e($profileError) ?></div><?php endif; ?>
  <section class="profile-hero">
    <?php if ($avatarUrl !== ''): ?><img class="profile-avatar-large profile-photo" src="<?= e($avatarUrl) ?>" alt="Profile photo"><?php else: ?><div class="profile-avatar-large"><?= e($initial) ?></div><?php endif; ?>
    <div><span class="eyebrow">EDYLIFE MEMBER</span><h2 style="margin:6px 0 4px"><?= e($displayName) ?></h2><p style="margin:0;color:var(--muted)"><?= e($taglineText) ?></p></div>
  </section>
  <form class="profile-panel profile-edit-form" method="post" enctype="multipart/form-data">
    <h2>Edit profile</h2>
    <p class="profile-help">Change your display name, tagline and profile picture. These details are saved locally in your EdyLife database.</p>
    <?= csrf_field() ?>
    <label class="profile-field"><span>Display name</span><input type="text" name="display_name" maxlength="80" required value="<?= e($displayName) ?>" placeholder="Edy"></label>
    <label class="profile-field"><span>Tagline</span><input type="text" name="tagline" maxlength="160" value="<?= e($taglineText) ?>" placeholder="Your world. Your story."></label>
    <label class="profile-field"><span>Profile picture</span><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP · maximum 5 MB. Leave empty to keep the current picture.</small></label>
    <div class="profile-form-actions"><button class="button button-primary" type="submit">Save profile</button><a class="button button-secondary" href="<?= e(app_url('index.php')) ?>">Cancel</a></div>
  </form>
  <section class="profile-panel"><h2>About your space</h2><p style="color:var(--muted);line-height:1.8">EdyLife keeps your activities, work plans, watchlist, calendar, journal and memories together in one private local workspace.</p><div class="profile-detail"><span>Workspace</span><strong>Local workspace</strong></div><div class="profile-detail"><span>Application</span><strong>EdyLife</strong></div><div class="profile-detail"><span>Mode</span><strong>Personal · Local</strong></div></section>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
