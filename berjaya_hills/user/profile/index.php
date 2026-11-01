<?php
require_once __DIR__.'/../../includes/bootstrap.php';
$u = require_login();
$pageTitle = 'My Profile';
$pdo = db();

/* Load notification preferences from the existing schema. */
$prefStmt = $pdo->prepare('SELECT booking_updates, event_reminders, announcements, email_enabled FROM user_notification_preferences WHERE user_id=?');
$prefStmt->execute([$u['id']]);
$prefs = $prefStmt->fetch() ?: [
    'booking_updates' => 1,
    'event_reminders' => 1,
    'announcements' => 1,
    'email_enabled' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf'] ?? '');
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $s = $pdo->prepare('UPDATE users SET full_name=?, phone=?, company_name=?, job_title=? WHERE id=?');
        $s->execute([
            trim($_POST['full_name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['company_name'] ?? ''),
            trim($_POST['job_title'] ?? ''),
            $u['id']
        ]);
        audit('profile_updated','user',$u['id']);
        flash('success','Profile updated.');
    } elseif ($action === 'notifications') {
        $bookingUpdates = isset($_POST['booking_updates']) ? 1 : 0;
        $eventReminders = isset($_POST['event_reminders']) ? 1 : 0;
        $announcements = isset($_POST['announcements']) ? 1 : 0;
        $emailEnabled = isset($_POST['email_enabled']) ? 1 : 0;

        $s = $pdo->prepare(
            'INSERT INTO user_notification_preferences (user_id, booking_updates, event_reminders, announcements, email_enabled)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE booking_updates=VALUES(booking_updates), event_reminders=VALUES(event_reminders), announcements=VALUES(announcements), email_enabled=VALUES(email_enabled)'
        );
        $s->execute([$u['id'], $bookingUpdates, $eventReminders, $announcements, $emailEnabled]);
        audit('notification_preferences_updated','user',$u['id']);
        flash('success','Notification preferences updated.');
    } elseif ($action === 'photo') {
        if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            flash('error','Please choose a profile photo.');
        } else {
            $file = $_FILES['profile_photo'];
            if ($file['size'] > 2 * 1024 * 1024) {
                flash('error','Profile photo must be 2MB or smaller.');
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                if (!isset($allowed[$mime])) {
                    flash('error','Only JPG, PNG or WEBP images are allowed.');
                } else {
                    $uploadDir = __DIR__.'/../../uploads/profile';
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                        flash('error','Profile upload folder is not writable.');
                    } else {
                        foreach (['jpg','jpeg','png','webp'] as $oldExt) {
                            $old = $uploadDir.'/user_'.$u['id'].'.'.$oldExt;
                            if (is_file($old)) @unlink($old);
                        }
                        $destination = $uploadDir.'/user_'.$u['id'].'.'.$allowed[$mime];
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            audit('profile_photo_updated','user',$u['id']);
                            flash('success','Profile photo updated.');
                        } else {
                            flash('error','Unable to save the profile photo.');
                        }
                    }
                }
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPassword, $u['password_hash'])) {
            flash('error','Current password is incorrect.');
        } elseif (strlen($newPassword) < 8) {
            flash('error','New password must be at least 8 characters.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('error','New password and confirmation do not match.');
        } else {
            $s = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $s->execute([password_hash($newPassword, PASSWORD_DEFAULT), $u['id']]);
            audit('password_changed','user',$u['id']);
            flash('success','Password changed successfully.');
        }
    }

    redirect('user/profile/index.php');
}

$st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
$st->execute([$u['id']]);
$userUnreadNotifications = (int)$st->fetchColumn();

$accountStatus = ucfirst($u['account_status'] ?? 'approved');
$memberSince = !empty($u['created_at']) ? date('d M Y, h:i A', strtotime($u['created_at'])) : '—';
$lastLogin = !empty($u['last_login_at']) ? date('d M Y, h:i A', strtotime($u['last_login_at'])) : '—';
$initials = '';
foreach (preg_split('/\s+/', trim($u['full_name'] ?? 'User')) as $part) {
    if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
    if (strlen($initials) >= 2) break;
}

/* User profile photo is stored as a local file so no database schema change is required. */
$profileUploadDir = __DIR__.'/../../uploads/profile';
if (!is_dir($profileUploadDir)) {
    @mkdir($profileUploadDir, 0755, true);
}
$profileImageUrl = '';
$profileImagePath = '';
foreach (['jpg','jpeg','png','webp'] as $ext) {
    $candidate = $profileUploadDir.'/user_'.$u['id'].'.'.$ext;
    if (is_file($candidate)) {
        $profileImagePath = $candidate;
        $profileImageUrl = '../../uploads/profile/'.basename($candidate);
        break;
    }
}

require __DIR__.'/../../includes/header.php';
?>

<div class="user-shell profile-shell">
    <aside class="sidebar">
        <?php require __DIR__.'/../sidebar.php'; ?>
    </aside>

    <section class="admin-content user-content profile-content">
        <div class="profile-page-head">
            <div>
                <div class="eyebrow">Dashboard &nbsp;›&nbsp; Profile</div>
                <h1 class="profile-page-title">My Profile</h1>
                <p>Manage your personal information, company details and notification preferences.</p>
            </div>
        </div>

        <div class="profile-layout">
            <div class="profile-main-column">
                <form method="post" enctype="multipart/form-data" class="profile-card profile-identity-card profile-photo-form" id="profile-photo-form">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="photo">
                    <input type="file" name="profile_photo" id="profile-photo-input" accept="image/jpeg,image/png,image/webp" hidden>

                    <div class="profile-avatar-wrap">
                        <?php if ($profileImageUrl): ?>
                            <img class="profile-avatar profile-avatar-image" id="profile-avatar-preview" src="<?= e($profileImageUrl) ?>?v=<?= filemtime($profileImagePath) ?>" alt="Profile photo">
                        <?php else: ?>
                            <div class="profile-avatar" id="profile-avatar-preview" aria-hidden="true"><?= e($initials ?: 'U') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-identity">
                        <div class="profile-name-row">
                            <h2><?= e($u['full_name'] ?? 'User') ?></h2>
                            <span class="profile-status"><span></span><?= e($accountStatus) ?></span>
                        </div>
                        <p class="profile-company"><?= e($u['company_name'] ?? 'Berjaya Hills Resort') ?></p>
                        <p class="profile-role"><?= e($u['job_title'] ?? 'Registered User') ?></p>
                        <div class="profile-contact-row">
                            <span>✉ <?= e($u['email'] ?? '—') ?></span>
                            <span>☎ <?= e($u['phone'] ?? '—') ?></span>
                        </div>
                    </div>
                    <div class="profile-photo-action">
                        <button class="btn btn-outline profile-edit-link" id="change-photo-btn" type="button">Change Photo</button>
                        <small>JPG, PNG or WEBP · max 2MB</small>
                    </div>
                </form>

                <section class="profile-card profile-info-card" id="personal-information">
                    <div class="profile-section-heading profile-section-heading-with-action">
                        <div class="profile-heading-icon">♙</div>
                        <div>
                            <h3>Personal Information</h3>
                            <p>Update the contact details used for your event bookings.</p>
                        </div>
                        <button class="btn btn-outline profile-section-edit" type="button" data-toggle-panel="personal-edit">✎ Edit</button>
                    </div>

                    <div class="profile-read-list" id="personal-read">
                        <div><span>Full Name</span><strong><?= e($u['full_name'] ?? '—') ?></strong></div>
                        <div><span>Email Address</span><strong><?= e($u['email'] ?? '—') ?></strong></div>
                        <div><span>Phone Number</span><strong><?= e($u['phone'] ?? '—') ?></strong></div>
                        <div><span>Job Title</span><strong><?= e($u['job_title'] ?? '—') ?></strong></div>
                    </div>

                    <form method="post" class="profile-edit-panel" id="personal-edit" hidden>
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="profile">
                        <div class="profile-form-grid">
                            <div class="field"><label>Full Name</label><input name="full_name" value="<?= e($u['full_name'] ?? '') ?>" required></div>
                            <div class="field"><label>Email Address</label><input value="<?= e($u['email'] ?? '') ?>" disabled></div>
                            <div class="field"><label>Phone Number</label><input name="phone" value="<?= e($u['phone'] ?? '') ?>"></div>
                            <div class="field"><label>Job Title</label><input name="job_title" value="<?= e($u['job_title'] ?? '') ?>"></div>
                        </div>
                        <div class="profile-form-actions">
                            <button class="btn btn-outline" type="button" data-cancel-panel="personal-edit">Cancel</button>
                            <button class="btn btn-gold" type="submit">Save changes</button>
                        </div>
                    </form>
                </section>

                <section class="profile-card profile-info-card">
                    <div class="profile-section-heading profile-section-heading-with-action">
                        <div class="profile-heading-icon">▥</div>
                        <div>
                            <h3>Company Information</h3>
                            <p>Company details shown when you submit an event booking.</p>
                        </div>
                        <button class="btn btn-outline profile-section-edit" type="button" data-toggle-panel="company-edit">✎ Edit</button>
                    </div>

                    <div class="profile-read-list" id="company-read">
                        <div><span>Company Name</span><strong><?= e($u['company_name'] ?? '—') ?></strong></div>
                        <div><span>Job Title</span><strong><?= e($u['job_title'] ?? '—') ?></strong></div>
                    </div>

                    <form method="post" class="profile-edit-panel" id="company-edit" hidden>
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="profile">
                        <div class="profile-form-grid profile-company-grid">
                            <div class="field"><label>Company Name</label><input name="company_name" value="<?= e($u['company_name'] ?? '') ?>"></div>
                            <div class="field"><label>Job Title</label><input name="job_title" value="<?= e($u['job_title'] ?? '') ?>"></div>
                            <input type="hidden" name="full_name" value="<?= e($u['full_name'] ?? '') ?>">
                            <input type="hidden" name="phone" value="<?= e($u['phone'] ?? '') ?>">
                        </div>
                        <div class="profile-form-actions">
                            <button class="btn btn-outline" type="button" data-cancel-panel="company-edit">Cancel</button>
                            <button class="btn btn-gold" type="submit">Save changes</button>
                        </div>
                    </form>
                </section>

                <section class="profile-card profile-info-card">
                    <div class="profile-section-heading profile-section-heading-with-action">
                        <div class="profile-heading-icon">♢</div>
                        <div>
                            <h3>Account Information</h3>
                            <p>Your account status and membership details.</p>
                        </div>
                        <button class="btn btn-outline profile-section-edit" type="button" data-toggle-panel="password-edit">▣ Change Password</button>
                    </div>
                    <div class="profile-read-list">
                        <div><span>Account Status</span><strong class="profile-status-inline"><span></span><?= e($accountStatus) ?></strong></div>
                        <div><span>User Role</span><strong>Registered User</strong></div>
                        <div><span>Member Since</span><strong><?= e($memberSince) ?></strong></div>
                        <div><span>Last Login</span><strong><?= e($lastLogin) ?></strong></div>
                    </div>

                    <form method="post" class="profile-edit-panel" id="password-edit" hidden>
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="password">
                        <div class="profile-form-grid">
                            <div class="field"><label>Current Password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
                            <div></div>
                            <div class="field"><label>New Password</label><input type="password" name="new_password" minlength="8" required autocomplete="new-password"></div>
                            <div class="field"><label>Confirm New Password</label><input type="password" name="confirm_password" minlength="8" required autocomplete="new-password"></div>
                        </div>
                        <div class="profile-form-actions">
                            <button class="btn btn-outline" type="button" data-cancel-panel="password-edit">Cancel</button>
                            <button class="btn btn-gold" type="submit">Update password</button>
                        </div>
                    </form>
                </section>
            </div>

            <aside class="profile-side-column">
                <section class="profile-card profile-preferences-card">
                    <div class="profile-section-heading">
                        <div class="profile-heading-icon gold">♧</div>
                        <div>
                            <h3>Notification Preferences</h3>
                            <p>Choose which updates you would like to receive in the system and by email.</p>
                        </div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="notifications">
                        <div class="profile-pref-head"><span></span><b>In-System</b><b>Email</b></div>

                        <div class="profile-pref-row">
                            <div class="profile-pref-icon blue">▣</div>
                            <div class="profile-pref-copy"><strong>Booking Updates</strong><small>Booking submitted, under review, confirmed and cancelled.</small></div>
                            <label class="profile-switch"><input type="checkbox" name="booking_updates" <?= !empty($prefs['booking_updates']) ? 'checked' : '' ?>><span></span></label>
                            <label class="profile-switch"><input type="checkbox" name="email_enabled" <?= !empty($prefs['email_enabled']) ? 'checked' : '' ?>><span></span></label>
                        </div>

                        <div class="profile-pref-row">
                            <div class="profile-pref-icon gold">♧</div>
                            <div class="profile-pref-copy"><strong>Booking Reminders</strong><small>Upcoming event reminders and important deadlines.</small></div>
                            <label class="profile-switch"><input type="checkbox" name="event_reminders" <?= !empty($prefs['event_reminders']) ? 'checked' : '' ?>><span></span></label>
                            <label class="profile-switch"><input type="checkbox" name="email_enabled_2" class="email-proxy" <?= !empty($prefs['email_enabled']) ? 'checked' : '' ?>><span></span></label>
                        </div>

                        <div class="profile-pref-row">
                            <div class="profile-pref-icon green">▣</div>
                            <div class="profile-pref-copy"><strong>Event Updates</strong><small>Important event and resort activity updates.</small></div>
                            <label class="profile-switch"><input type="checkbox" name="announcements" <?= !empty($prefs['announcements']) ? 'checked' : '' ?>><span></span></label>
                            <label class="profile-switch"><input type="checkbox" name="email_enabled_3" class="email-proxy" <?= !empty($prefs['email_enabled']) ? 'checked' : '' ?>><span></span></label>
                        </div>

                        <div class="profile-pref-row">
                            <div class="profile-pref-icon purple">♙</div>
                            <div class="profile-pref-copy"><strong>Account Updates</strong><small>Account approval, profile updates and password changes.</small></div>
                            <label class="profile-switch"><input type="checkbox" checked disabled><span></span></label>
                            <label class="profile-switch"><input type="checkbox" checked disabled><span></span></label>
                        </div>

                        <div class="profile-pref-note">Email delivery is controlled by your account email preference.</div>
                        <div class="profile-form-actions">
                            <button class="btn btn-gold" type="submit">Save notification settings</button>
                        </div>
                    </form>
                </section>

                <section class="profile-card profile-email-card">
                    <div class="profile-section-heading">
                        <div class="profile-heading-icon">✉</div>
                        <div>
                            <h3>Email Notification Settings</h3>
                            <p>Manage your email preference and delivery behaviour.</p>
                        </div>
                    </div>
                    <div class="profile-email-grid">
                        <div><span>Email Address</span><strong><?= e($u['email'] ?? '—') ?></strong></div>
                        <div><span>Email Delivery</span><strong><?= !empty($prefs['email_enabled']) ? 'Enabled' : 'Disabled' ?></strong></div>
                    </div>
                </section>
            </aside>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-toggle-panel]').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.dataset.togglePanel);
            if (!panel) return;
            const isHidden = panel.hasAttribute('hidden');
            panel.toggleAttribute('hidden', !isHidden);
            button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
            if (isHidden) panel.querySelector('input:not([type="hidden"])')?.focus();
        });
    });
    document.querySelectorAll('[data-cancel-panel]').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.dataset.cancelPanel);
            if (panel) panel.setAttribute('hidden', '');
        });
    });
});
</script>
<?php require __DIR__.'/../../includes/footer.php'; ?>
