<?php
// login.php
session_start();

/*
|--------------------------------------------------------------------------
| Get redirect destination
|--------------------------------------------------------------------------
| This allows the user to return to the page they originally wanted.
| Example:
| login.php?redirect=report_lost.php
| login.php?redirect=report_found.php
*/

// Default destination
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'reporter_dashboard.php';

// Only allow safe internal pages
$allowed_pages = [
    'report_lost.php',
    'report_found.php',
    'reporter_dashboard.php',
    'index.php'
];

// Prevent users from putting external URLs into redirect
if (!in_array($redirect, $allowed_pages, true)) {
    $redirect = 'reporter_dashboard.php';
}

/*
|--------------------------------------------------------------------------
| Redirect already logged-in users
|--------------------------------------------------------------------------
*/
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {

    // If they came from Lost/Found report, go there
    if (
        $redirect === 'report_lost.php' ||
        $redirect === 'report_found.php'
    ) {
        header("Location: " . $redirect);
        exit();
    }

    // Otherwise use role-based dashboard
    switch ($_SESSION['user_role']) {

        case 'Admin':
            header("Location: admin_dashboard.php");
            break;

        case 'Recorder':
            header("Location: recorder_dashboard.php");
            break;

        case 'Reporter':
        default:
            header("Location: reporter_dashboard.php");
            break;
    }

    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Resort Lost &amp; Found System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <style>
        :root{
            --blue:#0d6efd;
            --blue2:#0878e8;
            --navy:#09254a;
            --muted:#6b7c93;
        }

        *{box-sizing:border-box}

        body{
            margin:0;
            min-height:100vh;
            font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
            color:var(--navy);
            background:
                radial-gradient(circle at 10% 10%,rgba(13,110,253,.16),transparent 28%),
                radial-gradient(circle at 90% 90%,rgba(38,190,255,.16),transparent 30%),
                linear-gradient(135deg,#eef6ff 0%,#fff 48%,#edf7ff 100%);
        }

        .login-page{
            min-height:100vh;
            padding:30px;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            overflow:hidden;
        }

        .login-page:before,
        .login-page:after{
            content:"";
            position:absolute;
            border-radius:50%;
            pointer-events:none;
        }

        .login-page:before{
            width:500px;height:500px;
            left:-250px;top:-250px;
            border:70px solid rgba(13,110,253,.035);
        }

        .login-page:after{
            width:600px;height:600px;
            right:-330px;bottom:-330px;
            border:90px solid rgba(13,110,253,.035);
        }

        .login-shell{
            position:relative;
            z-index:2;
            width:min(1180px,100%);
            min-height:690px;
            display:grid;
            grid-template-columns:1.08fr .92fr;
            overflow:hidden;
            border-radius:30px;
            background:#fff;
            box-shadow:0 30px 90px rgba(18,60,105,.18);
            border:1px solid rgba(255,255,255,.9);
        }

        /* Resort welcome side */
        .welcome-panel{
            position:relative;
            overflow:hidden;
            padding:52px 58px;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            color:#fff;
            background:
                linear-gradient(145deg,#063c8e 0%,#0d6efd 55%,#16aeea 100%);
        }

        .welcome-panel:before{
            content:"";
            position:absolute;
            width:520px;height:520px;
            right:-270px;top:-220px;
            border-radius:50%;
            border:1px solid rgba(255,255,255,.18);
            box-shadow:
                0 0 0 55px rgba(255,255,255,.035),
                0 0 0 110px rgba(255,255,255,.025);
        }

        .welcome-panel:after{
            content:"";
            position:absolute;
            width:850px;height:250px;
            left:-160px;bottom:-170px;
            border-radius:50%;
            background:rgba(255,255,255,.10);
            transform:rotate(-8deg);
        }

        .brand{
            position:relative;
            z-index:2;
            display:flex;
            align-items:center;
            gap:13px;
            font-size:1.25rem;
            font-weight:800;
        }

        .brand-icon{
            width:47px;height:47px;
            display:grid;place-items:center;
            border-radius:14px;
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.25);
            backdrop-filter:blur(8px);
        }

        .brand small{
            display:block;
            margin-top:2px;
            font-size:.62rem;
            letter-spacing:1.6px;
            text-transform:uppercase;
            opacity:.7;
        }

        .welcome-content{
            position:relative;
            z-index:2;
            max-width:590px;
            margin-top:-15px;
        }

        .eyebrow{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 13px;
            border-radius:999px;
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.2);
            backdrop-filter:blur(10px);
            font-size:.72rem;
            font-weight:800;
            letter-spacing:.6px;
        }

        .welcome-content h1{
            margin:22px 0 15px;
            font-size:clamp(2.8rem,5vw,4.7rem);
            line-height:.97;
            letter-spacing:-3px;
            font-weight:850;
        }

        .welcome-content h1 span{color:#aee7ff}

        .welcome-content p{
            max-width:500px;
            margin:0;
            font-size:1rem;
            line-height:1.7;
            color:rgba(255,255,255,.78);
        }

        .feature-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:12px;
            margin-top:34px;
        }

        .feature{
            min-height:112px;
            padding:17px;
            border-radius:18px;
            background:rgba(255,255,255,.10);
            border:1px solid rgba(255,255,255,.15);
            backdrop-filter:blur(10px);
            transition:.25s ease;
        }

        .feature:hover{transform:translateY(-4px);background:rgba(255,255,255,.15)}

        .feature i{
            display:grid;
            place-items:center;
            width:35px;height:35px;
            margin-bottom:12px;
            border-radius:10px;
            background:#fff;
            color:var(--blue);
        }

        .feature strong{display:block;font-size:.82rem}
        .feature span{
            display:block;
            margin-top:4px;
            font-size:.68rem;
            line-height:1.4;
            color:rgba(255,255,255,.65);
        }

        .quote{
            position:relative;
            z-index:2;
            display:flex;
            align-items:center;
            gap:13px;
            font-size:.75rem;
            color:rgba(255,255,255,.65);
        }

        .quote-line{width:38px;height:2px;background:#8ddcff;border-radius:2px}

        /* Login side */
        .form-panel{
            position:relative;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:45px;
            background:rgba(255,255,255,.97);
        }

        .form-panel:before{
            content:"";
            position:absolute;
            width:240px;height:240px;
            right:-120px;top:-120px;
            border-radius:50%;
            background:rgba(13,110,253,.055);
        }

        .login-card{
            position:relative;
            z-index:2;
            width:100%;
            max-width:430px;
            border:0;
            background:transparent;
            box-shadow:none;
        }

        .login-header{
            padding:0 0 28px!important;
            background:transparent!important;
            color:var(--navy)!important;
            border-radius:0!important;
            text-align:left!important;
        }

        .login-header h4{
            display:flex;
            align-items:center;
            gap:11px;
            margin:0 0 7px!important;
            font-size:1.65rem;
            color:var(--navy);
        }

        .login-header h4 i{
            width:44px;height:44px;
            display:grid;place-items:center;
            border-radius:13px;
            color:#fff;
            background:linear-gradient(135deg,var(--blue),#25b7ff);
            box-shadow:0 10px 24px rgba(13,110,253,.25);
        }

        .login-header p{
            margin-left:55px!important;
            color:var(--muted)!important;
            opacity:1!important;
            font-size:.78rem!important;
        }

        .card-body{padding:0!important}

        .alert{
            border-radius:12px;
            font-size:.75rem;
        }

        .form-label{
            color:#243b5a;
            font-size:.78rem!important;
            margin-bottom:7px;
        }

        .input-group{
            border-radius:12px;
            box-shadow:0 8px 24px rgba(24,61,100,.06);
        }

        .input-group-text{
            width:47px;
            justify-content:center;
            background:#f6f9fd!important;
            border-color:#dfe7f0;
            border-radius:12px 0 0 12px!important;
        }

        .form-control{
            height:50px;
            border-color:#dfe7f0;
            border-radius:0 12px 12px 0!important;
            font-size:.88rem;
        }

        .form-control:focus{
            border-color:#86b7fe;
            box-shadow:0 0 0 .20rem rgba(13,110,253,.10);
        }

        .login-submit{
            height:52px;
            border:0;
            border-radius:12px;
            background:linear-gradient(135deg,var(--blue),var(--blue2));
            box-shadow:0 12px 25px rgba(13,110,253,.22);
            transition:.25s ease;
        }

        .login-submit:hover{
            transform:translateY(-2px);
            box-shadow:0 16px 30px rgba(13,110,253,.28);
        }

        .register-box{
            margin-top:24px;
            padding:18px;
            text-align:center;
            border:1px solid #e5edf6;
            border-radius:15px;
            background:#f8fbff;
        }

        .register-box a{display:inline-block;margin-top:5px}

        .or-divider{
            display:flex;
            align-items:center;
            gap:12px;
            margin:17px 0;
            color:#a1adbb;
            font-size:.68rem;
            font-weight:700;
        }

        .or-divider:before,.or-divider:after{
            content:"";
            flex:1;
            height:1px;
            background:#e5ebf2;
        }

        .back-link{
            display:inline-flex;
            align-items:center;
            gap:7px;
            color:#68788d!important;
        }

        .back-link:hover{color:var(--blue)!important}

        .security-note{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:7px;
            margin-top:20px;
            color:#93a0b0;
            font-size:.66rem;
        }

        .security-note i{color:#16a06a}

        @media(max-width:900px){
            .login-page{padding:20px}
            .login-shell{
                grid-template-columns:1fr;
                min-height:auto;
                max-width:620px;
            }
            .welcome-panel{min-height:430px;padding:35px}
            .welcome-content{margin-top:25px}
            .form-panel{padding:40px 32px}
        }

        @media(max-width:560px){
            .login-page{padding:0}
            .login-shell{min-height:100vh;border-radius:0}
            .welcome-panel{min-height:390px;padding:28px 24px}
            .welcome-content h1{font-size:2.6rem;letter-spacing:-2px}
            .welcome-content p{font-size:.86rem}
            .feature-grid{grid-template-columns:1fr 1fr}
            .feature:last-child{display:none}
            .form-panel{padding:38px 24px}
        }
    </style>
</head>

<body>
<div class="login-page">
    <div class="login-shell">

        <section class="welcome-panel">
            <div class="brand">
                <div class="brand-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div>
                    Resort L&amp;F
                    <small>Safe Items. Happier Guests.</small>
                </div>
            </div>

            <div class="welcome-content">
                <div class="eyebrow">
                    <i class="fa-solid fa-shield-heart"></i>
                    RESORT LOST &amp; FOUND PORTAL
                </div>

                <h1>Lost something?<br><span>We’ve got you.</span></h1>

                <p>
                    A smarter and more caring way to report, track and recover
                    your belongings throughout your resort experience.
                </p>

                <div class="feature-grid">
                    <div class="feature">
                        <i class="fa-solid fa-shield-halved"></i>
                        <strong>Secure Handling</strong>
                        <span>Your item information is managed safely.</span>
                    </div>
                    <div class="feature">
                        <i class="fa-solid fa-route"></i>
                        <strong>Easy Tracking</strong>
                        <span>Follow your report from start to finish.</span>
                    </div>
                    <div class="feature">
                        <i class="fa-solid fa-heart"></i>
                        <strong>Guest First</strong>
                        <span>Designed around a smoother guest experience.</span>
                    </div>
                </div>
            </div>

            <div class="quote">
                <span class="quote-line"></span>
                <span>Small things make a big difference.</span>
            </div>
        </section>

        <section class="form-panel">
            <div class="card login-card">

                <div class="login-header">
                    <h4>
                        <i class="fa-solid fa-boxes-stacked"></i>
                        <span>Welcome back</span>
                    </h4>
                    <p>Sign in to access your Resort L&amp;F portal</p>
                </div>

                <div class="card-body">

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <?= htmlspecialchars($_SESSION['error']); ?>
                            <?php unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
                            <i class="fa-solid fa-circle-check me-1"></i>
                            <?= htmlspecialchars($_SESSION['success']); ?>
                            <?php unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="login_process.php" method="POST">
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect); ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold small">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fa-solid fa-envelope text-muted"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="Enter your email address" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold small">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fa-solid fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password"
                                       placeholder="Enter your password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 fw-bold login-submit">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>
                            Sign In
                        </button>
                    </form>

                    <div class="text-end mt-3">
                        <a href="forgot_password.php" class="small fw-semibold text-primary text-decoration-none">
                            <i class="fa-solid fa-key me-1"></i>Forgot Password?
                        </a>
                    </div>

                    <div class="register-box">
                        <span class="small text-muted">First time reporting an item?</span>
                        <br>
                        <a href="register.php?redirect=<?= urlencode($redirect); ?>"
                           class="small fw-bold text-decoration-none text-primary">
                            Create a Reporter Account
                        </a>

                        <div class="or-divider"><span>OR</span></div>

                        <a href="index.php" class="back-link small text-decoration-none">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back to Front Page
                        </a>
                    </div>

                    <div class="security-note">
                        <i class="fa-solid fa-circle-check"></i>
                        Secure resort portal • Lost &amp; Found Management System
                    </div>

                </div>
            </div>
        </section>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
