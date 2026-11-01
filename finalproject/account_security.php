<?php
// account_security.php
session_start();
require_once 'db.php';
require_once 'auth_check.php';
checkAccess(['Reporter','Recorder','Admin']);

$user_id = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf_account_security'])) {
    $_SESSION['csrf_account_security'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_account_security'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $posted_csrf)) {
        $error = 'Security validation failed. Please refresh the page and try again.';
    }
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($error === '') {
        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'Please complete all password fields.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $error = 'New password must contain at least one uppercase letter, one lowercase letter and one number.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (password_verify($new, $user['password'])) {
            $error = 'Your new password must be different from your current password.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            $update->execute([$hash, $user_id]);

            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            $success = 'Password changed successfully. Your account security has been updated.';
            $_SESSION['csrf_account_security'] = bin2hex(random_bytes(32));
            $csrf_token = $_SESSION['csrf_account_security'];
        }
        }
    }
}

$role = $_SESSION['user_role'] ?? 'User';
$back = $role === 'Admin' ? 'admin_dashboard.php' : ($role === 'Recorder' ? 'recorder_dashboard.php' : 'reporter_dashboard.php');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Account Security - Resort L&amp;F</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet"><style>
body{background:#f4f7fb}.security-card{border:0;border-radius:22px;box-shadow:0 15px 45px rgba(18,60,105,.1)}.security-head{background:linear-gradient(135deg,#063c8e,#0d6efd,#16aeea);color:#fff;border-radius:22px 22px 0 0}.input-group-text{width:46px;justify-content:center}.form-control{height:48px}.strength{height:7px;border-radius:99px}.security-item{border:1px solid #e8eef5;border-radius:14px;padding:14px;background:#f9fbfe}
.security-item i{font-size:1.05rem}
.security-tip{border:1px solid #d9e9ff;background:#f1f7ff;border-radius:14px;padding:14px;color:#24527a}
.requirements{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px}
.requirement{font-size:.82rem;color:#6c757d}
.requirement i{margin-right:6px}
.requirement.valid{color:#198754}
@media(max-width:576px){.requirements{grid-template-columns:1fr}}
</style></head><body><nav class="navbar navbar-dark bg-dark sticky-top"><div class="container-fluid px-4"><a class="navbar-brand fw-bold" href="<?= $back; ?>"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Resort L&amp;F</a><div class="d-flex align-items-center gap-2"><a href="<?= $back; ?>" class="btn btn-outline-light btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard</a><a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a></div></div></nav>
<div class="container py-4 py-lg-5" style="max-width:1000px"><div class="card security-card overflow-hidden"><div class="security-head p-4 p-lg-5"><div class="small text-uppercase fw-bold opacity-75">Account Protection</div><h2 class="fw-bold mb-2"><i class="fa-solid fa-shield-halved me-2"></i>Account Security</h2><p class="mb-0 opacity-75">Manage your password and keep your Resort L&amp;F account protected.</p></div><div class="card-body p-4 p-lg-5">
<?php if($error): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error); ?></div><?php endif; ?><?php if($success): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success); ?></div><?php endif; ?>
<div class="row g-4"><div class="col-lg-7"><h5 class="fw-bold mb-3">Change Password</h5><form method="POST" autocomplete="off" id="securityForm"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token); ?>">
<?php foreach([['current_password','Current Password'],['new_password','New Password'],['confirm_password','Confirm New Password']] as $field): ?><div class="mb-3"><label class="form-label fw-semibold"><?= $field[1]; ?></label><div class="input-group"><span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span><input type="password" class="form-control password-field" id="<?= $field[0]; ?>" name="<?= $field[0]; ?>" required <?= $field[0]==='current_password'?'autocomplete="current-password"':'autocomplete="new-password"'; ?>><button class="btn btn-outline-secondary toggle-password" type="button" data-target="<?= $field[0]; ?>"><i class="fa-solid fa-eye"></i></button></div><?php if($field[0]==='new_password'): ?><div class="mt-2"><div class="progress strength"><div id="strengthBar" class="progress-bar" style="width:0%"></div></div><small id="strengthText" class="text-muted">Use 8+ characters with uppercase, lowercase and a number.</small>
<div class="requirements">
<div id="reqLength" class="requirement"><i class="fa-regular fa-circle"></i>At least 8 characters</div>
<div id="reqUpper" class="requirement"><i class="fa-regular fa-circle"></i>One uppercase letter</div>
<div id="reqLower" class="requirement"><i class="fa-regular fa-circle"></i>One lowercase letter</div>
<div id="reqNumber" class="requirement"><i class="fa-regular fa-circle"></i>One number</div>
<div id="reqSpecial" class="requirement"><i class="fa-regular fa-circle"></i>Special character recommended</div>
</div></div><?php endif; ?></div><?php endforeach; ?><button class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-key me-1"></i>Update Password</button></form></div>
<div class="col-lg-5">
<h5 class="fw-bold mb-3">Security Checklist</h5>
<div class="d-grid gap-2">
<div class="security-item"><i class="fa-solid fa-shield-halved text-success me-2"></i><strong>Password Protection</strong><div class="small text-muted mt-1">Passwords are stored using secure one-way hashing.</div></div>
<div class="security-item"><i class="fa-solid fa-rotate text-success me-2"></i><strong>Session Protection</strong><div class="small text-muted mt-1">The session ID is regenerated after a successful password update.</div></div>
<div class="security-item"><i class="fa-solid fa-clock text-success me-2"></i><strong>Session Timeout</strong><div class="small text-muted mt-1">Idle-session expiry is handled by the system authentication layer.</div></div>
<div class="security-item"><i class="fa-solid fa-user-lock text-success me-2"></i><strong>Role-Based Access</strong><div class="small text-muted mt-1">Access to system pages is restricted according to the user's role.</div></div>
<div class="security-item"><i class="fa-solid fa-fingerprint text-success me-2"></i><strong>Form Security</strong><div class="small text-muted mt-1">Password changes require a valid security token and the current password.</div></div>
</div>
<div class="security-tip mt-3"><i class="fa-solid fa-lightbulb me-2"></i><strong>Tip:</strong> Use a unique password that you do not reuse on other accounts.</div>
</div></div>
</div></div></div>
<script>
document.querySelectorAll('.toggle-password').forEach(btn=>btn.addEventListener('click',()=>{const input=document.getElementById(btn.dataset.target);const icon=btn.querySelector('i');input.type=input.type==='password'?'text':'password';icon.classList.toggle('fa-eye');icon.classList.toggle('fa-eye-slash');}));
const p=document.getElementById('new_password'),bar=document.getElementById('strengthBar'),txt=document.getElementById('strengthText');
const requirements=[
    [v=>v.length>=8,'At least 8 characters','reqLength'],
    [v=>/[A-Z]/.test(v),'One uppercase letter','reqUpper'],
    [v=>/[a-z]/.test(v),'One lowercase letter','reqLower'],
    [v=>/[0-9]/.test(v),'One number','reqNumber'],
    [v=>/[^A-Za-z0-9]/.test(v),'Optional special character','reqSpecial']
];
const updateStrength=()=>{
    const v=p.value; let score=0;
    requirements.forEach(([test,,id])=>{
        const el=document.getElementById(id);
        if(test(v)){score++;el.classList.add('valid');el.querySelector('i').className='fa-solid fa-circle-check';}
        else{el.classList.remove('valid');el.querySelector('i').className='fa-regular fa-circle';}
    });
    const pct=Math.min(100,Math.round(score/5*100)); bar.style.width=pct+'%';
    txt.textContent=v===''?'Use 8+ characters with uppercase, lowercase and a number.':pct<60?'Weak password':pct<100?'Good password':'Strong password';
};
p.addEventListener('input',updateStrength);
document.getElementById('securityForm').addEventListener('submit',e=>{
    if(p.value!==document.getElementById('confirm_password').value){
        document.getElementById('confirm_password').focus();
    }
});
</script></body></html>
