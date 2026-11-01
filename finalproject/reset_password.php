<?php
// reset_password.php
session_start();
require_once 'db.php';

$error = '';
$success = '';
$validToken = false;
$user = null;
$userId = 0;

function base64UrlDecode(string $value): string
{
    $remainder = strlen($value) % 4;
    if ($remainder) {
        $value .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($value, '-_', '+/')) ?: '';
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

if ($token === '') {
    $error = 'This password reset link is missing or invalid.';
} else {
    $decoded = base64UrlDecode($token);
    $parts = explode('|', $decoded);

    if (count($parts) === 3 && ctype_digit($parts[0]) && ctype_digit($parts[1]) && preg_match('/^[a-f0-9]{64}$/', $parts[2])) {
        $userId = (int)$parts[0];
        $expires = (int)$parts[1];
        $signature = $parts[2];

        if ($expires < time()) {
            $error = 'This password reset link has expired. Please request a new one.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, full_name, email, password, role FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || ($user['role'] ?? '') !== 'Reporter') {
                    $error = 'This password reset link is invalid.';
                } else {
                    $payload = $userId . '|' . $expires;
                    $expected = hash_hmac('sha256', $payload, (string)$user['password']);

                    if (!hash_equals($expected, $signature)) {
                        $error = 'This password reset link is invalid or has already been used.';
                    } else {
                        $validToken = true;
                    }
                }
            } catch (Throwable $e) {
                error_log('Reset password token validation error: ' . $e->getMessage());
                $error = 'Unable to validate this password reset link.';
            }
        }
    } else {
        $error = 'This password reset link is invalid.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $error = 'New password must contain at least one uppercase letter, one lowercase letter and one number.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (password_verify($newPassword, (string)$user['password'])) {
        $error = 'Your new password must be different from your current password.';
    } else {
        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hash, $userId]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Password update did not complete.');
            }

            // Changing the password changes the signing key, invalidating this reset token.
            $success = 'Your password has been reset successfully. You can now sign in with your new password.';
            $validToken = false;
            $token = '';
        } catch (Throwable $e) {
            error_log('Reset password update error: ' . $e->getMessage());
            $error = 'Unable to reset your password right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password - Resort Lost &amp; Found</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
body{margin:0;min-height:100vh;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:linear-gradient(135deg,#eef6ff 0%,#fff 48%,#edf7ff 100%);color:#09254a}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:25px}
.card-reset{width:min(540px,100%);border:0;border-radius:24px;box-shadow:0 25px 70px rgba(18,60,105,.16);overflow:hidden}
.head{padding:30px;color:#fff;background:linear-gradient(135deg,#063c8e,#0d6efd,#16aeea)}
.body{padding:34px;background:#fff}
.icon{width:58px;height:58px;display:grid;place-items:center;border-radius:16px;background:rgba(255,255,255,.16);font-size:1.5rem;margin-bottom:15px}
.form-control{height:50px;border-color:#dfe7f0}
.input-group-text{width:48px;justify-content:center;background:#f6f9fd;border-color:#dfe7f0}
.btn-reset{height:50px;border:0;border-radius:11px;background:linear-gradient(135deg,#0d6efd,#0878e8)}
.requirements{background:#f8fbff;border:1px solid #e5edf6;border-radius:12px;padding:13px;font-size:.75rem}
</style>
</head>
<body>
<div class="page">
<div class="card-reset">
<div class="head">
<div class="icon"><i class="fa-solid fa-lock"></i></div>
<h3 class="fw-bold mb-1">Reset Password</h3>
<p class="mb-0 opacity-75 small">Create a new password for your Reporter account.</p>
</div>
<div class="body">
<?php if ($error !== ''): ?>
<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success !== ''): ?>
<div class="alert alert-success">
<i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success); ?>
<div class="mt-3"><a href="login.php" class="btn btn-success btn-sm fw-bold">Go to Login</a></div>
</div>
<?php elseif ($validToken): ?>
<form method="POST">
<input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

<label for="new_password" class="form-label fw-semibold small">New Password</label>
<div class="input-group mb-3">
<span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
<input type="password" class="form-control" id="new_password" name="new_password" required>
</div>

<label for="confirm_password" class="form-label fw-semibold small">Confirm New Password</label>
<div class="input-group mb-3">
<span class="input-group-text"><i class="fa-solid fa-lock text-muted"></i></span>
<input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
</div>

<div class="requirements mb-3">
<strong>Password requirements</strong>
<ul class="mb-0 mt-1 ps-3">
<li>At least 8 characters</li>
<li>At least one uppercase letter</li>
<li>At least one lowercase letter</li>
<li>At least one number</li>
</ul>
</div>

<button type="submit" class="btn btn-primary btn-reset w-100 fw-bold">
<i class="fa-solid fa-key me-2"></i>Reset Password
</button>
</form>
<?php else: ?>
<div class="text-center py-2">
<i class="fa-solid fa-link-slash fs-1 text-danger mb-3"></i>
<p class="text-muted small mb-4"><?= htmlspecialchars($error !== '' ? $error : 'This password reset link is no longer valid.'); ?></p>
<a href="forgot_password.php" class="btn btn-primary fw-bold">Request New Reset Link</a>
</div>
<?php endif; ?>

<div class="text-center mt-4">
<a href="login.php" class="text-decoration-none small fw-semibold text-secondary">
<i class="fa-solid fa-arrow-left me-1"></i>Back to Login
</a>
</div>
</div>
</div>
</div>
<script>
const p=document.getElementById('new_password');
const c=document.getElementById('confirm_password');
if(p&&c){
    c.addEventListener('input',()=>{c.setCustomValidity(c.value!==p.value?'Passwords do not match':'');});
    p.addEventListener('input',()=>{c.setCustomValidity(c.value!==p.value?'Passwords do not match':'');});
}
</script>
</body>
</html>
