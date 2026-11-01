<?php
require_once __DIR__.'/../../includes/bootstrap.php';

if (current_user()) redirect('user/dashboard/index.php');

$pageTitle = 'Forgot password';
$error = '';
$success = '';
$devResetUrl = '';

try {
    db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(token_hash),
        INDEX(user_id),
        CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Throwable $e) {
    $error = 'Password reset is temporarily unavailable. Please contact the administrator.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    verify_csrf($_POST['csrf'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Always use the same public response so the page does not reveal
    // whether an email address is registered.
    $success = 'If that email address is registered, a password reset link has been prepared. Please check your email.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $s = db()->prepare('SELECT id, full_name, email FROM users WHERE email=? LIMIT 1');
        $s->execute([$email]);
        $user = $s->fetch();

        if ($user) {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expires = date('Y-m-d H:i:s', time() + 3600);

            db()->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([(int)$user['id']]);
            db()->prepare('INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?,?,?)')
                ->execute([(int)$user['id'], $tokenHash, $expires]);

            $resetUrl = url('public/auth/reset_password.php?token=' . urlencode($rawToken));

            $subject = 'Berjaya Hills – Password Reset';
            $message = "Hello {$user['full_name']},\n\n"
                     . "We received a request to reset your Berjaya Hills account password.\n\n"
                     . "Reset your password here:\n{$resetUrl}\n\n"
                     . "This link expires in 1 hour. If you did not request this, you can ignore this email.";
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n"
                     . "From: Berjaya Hills <no-reply@berjayahills.local>\r\n";
            @mail($user['email'], $subject, $message, $headers);

            // Local development fallback: make the generated link available
            // so the feature can be tested without a local mail server.
            $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
            if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
                $devResetUrl = $resetUrl;
            }
        }
    }
}

require __DIR__.'/../../includes/header.php';
?>
<section class="auth-shell">
  <div class="auth-card">
    <div class="eyebrow">Account recovery</div>
    <h1>Forgot your password?</h1>
    <p>Enter the email address linked to your Berjaya Hills account and we’ll help you reset your password.</p>

    <?php if ($error): ?>
      <div class="badge badge-red" style="margin-bottom:15px"><?=e($error)?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="badge badge-green" style="display:block;padding:12px 14px;border-radius:6px;margin-bottom:18px;line-height:1.5"><?=e($success)?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" type="email" name="email" autocomplete="email" placeholder="you@example.com" required>
      </div>
      <button class="btn btn-primary" style="width:100%;margin-top:20px">Send reset link</button>
    </form>

    <?php if ($devResetUrl): ?>
      <div style="margin-top:20px;padding:14px;background:#f8f6f0;border:1px solid #e2ded5;border-radius:6px">
        <div class="kicker" style="margin-bottom:6px"><strong>Local development</strong></div>
        <div class="kicker">Email sending is not configured on localhost. Use this reset link to test the feature:</div>
        <a href="<?=e($devResetUrl)?>" style="display:block;margin-top:8px;color:var(--forest);font-weight:700;word-break:break-all">Reset my password →</a>
      </div>
    <?php endif; ?>

    <p class="kicker" style="margin-top:22px">
      Remember your password?
      <a style="color:var(--forest);font-weight:700" href="<?=url('public/auth/login.php')?>">Back to sign in</a>
    </p>
  </div>
</section>
<?php require __DIR__.'/../../includes/footer.php'; ?>
