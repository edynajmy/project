<?php
require_once __DIR__.'/../../includes/bootstrap.php';

if (current_user()) redirect('user/dashboard/index.php');

$pageTitle = 'Reset password';
$error = '';
$success = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$resetRow = null;

if ($token !== '') {
    $s = db()->prepare('SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email
                        FROM password_resets pr
                        JOIN users u ON u.id=pr.user_id
                        WHERE pr.token_hash=? LIMIT 1');
    $s->execute([hash('sha256', $token)]);
    $resetRow = $s->fetch();

    if (!$resetRow || $resetRow['used_at'] !== null || strtotime($resetRow['expires_at']) < time()) {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
        $resetRow = null;
    }
} else {
    $error = 'No password reset token was provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRow) {
    verify_csrf($_POST['csrf'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirmation'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Your new password must contain at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The password confirmation does not match.';
    } else {
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE users SET password_hash=?, updated_at=NOW() WHERE id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$resetRow['user_id']]);
            db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=?')
                ->execute([(int)$resetRow['id']]);
            db()->commit();
            $success = 'Your password has been updated successfully. You can now sign in with your new password.';
            $resetRow = null;
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $error = 'Unable to update your password. Please request a new reset link.';
        }
    }
}

require __DIR__.'/../../includes/header.php';
?>
<section class="auth-shell">
  <div class="auth-card">
    <div class="eyebrow">Account recovery</div>
    <h1>Set a new password</h1>
    <p>Choose a new password for your Berjaya Hills account.</p>

    <?php if ($error): ?>
      <div class="badge badge-red" style="display:block;padding:12px 14px;border-radius:6px;margin-bottom:18px;line-height:1.5"><?=e($error)?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="badge badge-green" style="display:block;padding:12px 14px;border-radius:6px;margin-bottom:18px;line-height:1.5"><?=e($success)?></div>
      <a class="btn btn-primary" style="width:100%;margin-top:4px" href="<?=url('public/auth/login.php')?>">Back to sign in</a>
    <?php elseif ($resetRow): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="token" value="<?=e($token)?>">
        <div class="field">
          <label for="password">New password</label>
          <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
        </div>
        <div class="field" style="margin-top:15px">
          <label for="password_confirmation">Confirm new password</label>
          <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="Re-enter your new password" required>
        </div>
        <button class="btn btn-primary" style="width:100%;margin-top:20px">Update password</button>
      </form>
    <?php else: ?>
      <a class="btn btn-outline" style="width:100%;margin-top:5px;color:var(--forest)" href="<?=url('public/auth/forgot_password.php')?>">Request a new reset link</a>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__.'/../../includes/footer.php'; ?>
