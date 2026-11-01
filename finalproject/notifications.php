<?php
// notifications.php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

require_once 'db.php';
require_once 'auth_check.php';

checkAccess(['Reporter', 'Recorder', 'Admin']);

$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'Reporter';

// Return the user to the correct dashboard.
$dashboard = match ($user_role) {
    'Admin'    => 'admin_dashboard.php',
    'Recorder' => 'recorder_dashboard.php',
    default    => 'reporter_dashboard.php',
};

$message = '';
$error = '';

// Mark a single notification as read.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = (int)($_POST['notification_id'] ?? 0);

    if ($notification_id > 0) {
        try {
            $stmt = $pdo->prepare("\n                UPDATE notifications\n                SET is_read = 'Yes'\n                WHERE notification_id = ? AND user_id = ?\n            ");
            $stmt->execute([$notification_id, $user_id]);
            $message = 'Notification marked as read.';
        } catch (PDOException $e) {
            error_log('Notification read error: ' . $e->getMessage());
            $error = 'Unable to update the notification.';
        }
    }
}

// Mark all notifications as read.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("\n            UPDATE notifications\n            SET is_read = 'Yes'\n            WHERE user_id = ? AND is_read = 'No'\n        ");
        $stmt->execute([$user_id]);
        $message = 'All notifications have been marked as read.';
    } catch (PDOException $e) {
        error_log('Mark all notifications error: ' . $e->getMessage());
        $error = 'Unable to update your notifications.';
    }
}

// Load notifications for the current user.
$notifications = [];
try {
    $stmt = $pdo->prepare("\n        SELECT\n            n.notification_id,\n            n.user_id,\n            n.found_item_id,\n            n.message,\n            n.is_read,\n            n.created_at,\n            f.item_code,\n            f.title AS item_title\n        FROM notifications n\n        LEFT JOIN found_items f ON n.found_item_id = f.item_id\n        WHERE n.user_id = ?\n        ORDER BY n.created_at DESC, n.notification_id DESC\n        LIMIT 100\n    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Notification load error: ' . $e->getMessage());
    $error = 'Unable to load notifications at this time.';
}

$unread_count = 0;
foreach ($notifications as $notification) {
    if (($notification['is_read'] ?? '') === 'No') {
        $unread_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center - Resort L&amp;F</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-brand { font-weight: 700; letter-spacing: .3px; }
        .page-wrap { max-width: 1000px; margin: 0 auto; }
        .page-kicker { color: #0d6efd; font-weight: 700; font-size: .9rem; letter-spacing: .3px; }
        .notification-card {
            border: 0;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .notification-item {
            border: 0;
            border-bottom: 1px solid #edf0f4;
            padding: 18px 22px;
            transition: background-color .15s ease;
        }
        .notification-item:last-child { border-bottom: 0; }
        .notification-item.unread { background: #f8fbff; }
        .notification-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 46px;
            background: #eaf3ff;
            color: #0d6efd;
        }
        .notification-item.unread .notification-icon {
            background: #0d6efd;
            color: #fff;
        }
        .unread-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #dc3545;
            display: inline-block;
        }
        .empty-state { padding: 55px 20px; }
        .empty-icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #eaf3ff;
            color: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        .action-bar { gap: 10px; }
        @media (max-width: 576px) {
            .notification-item { padding: 16px; }
            .action-bar { width: 100%; }
            .action-bar .btn { flex: 1; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="<?= htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Resort L&amp;F
        </a>

        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <span class="text-light small me-1">
                <?= htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>
                (<?= htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8'); ?>)
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-right-from-bracket me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-5">
    <div class="page-wrap">

        <?php if ($message !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i>
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <div class="page-kicker">RESORT L&amp;F PORTAL</div>
                <h1 class="fw-bold mb-1">
                    <i class="fa-solid fa-bell me-2"></i>Notification Center
                </h1>
                <p class="text-muted mb-0">Stay updated on your Lost &amp; Found activity.</p>
            </div>

            <div class="d-flex action-bar">
                <a href="<?= htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary fw-semibold">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
                </a>
                <?php if ($unread_count > 0): ?>
                    <form method="POST" class="m-0">
                        <button type="submit" name="mark_all_read" value="1" class="btn btn-primary fw-semibold">
                            <i class="fa-solid fa-check-double me-1"></i>Mark All Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card notification-card">
            <?php if (empty($notifications)): ?>
                <div class="empty-state text-center">
                    <div class="empty-icon">
                        <i class="fa-solid fa-bell-slash"></i>
                    </div>
                    <h4 class="fw-bold mb-2">You're all caught up</h4>
                    <p class="text-muted mb-0">New system updates will appear here.</p>
                </div>
            <?php else: ?>
                <div class="card-header bg-white border-0 px-4 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-bold">Recent Notifications</div>
                        <span class="badge rounded-pill <?= $unread_count > 0 ? 'bg-danger' : 'bg-secondary'; ?>">
                            <?= $unread_count; ?> unread
                        </span>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <?php $isUnread = ($notification['is_read'] ?? '') === 'No'; ?>
                        <div class="notification-item <?= $isUnread ? 'unread' : ''; ?>">
                            <div class="d-flex align-items-start gap-3">
                                <div class="notification-icon">
                                    <i class="fa-solid <?= $isUnread ? 'fa-bell' : 'fa-check'; ?>"></i>
                                </div>

                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                        <div>
                                            <?php if ($isUnread): ?>
                                                <span class="unread-dot me-2" title="Unread"></span>
                                            <?php endif; ?>
                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($notification['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted text-nowrap">
                                            <?= !empty($notification['created_at'])
                                                ? date('d M Y, h:i A', strtotime($notification['created_at']))
                                                : 'Date unavailable'; ?>
                                        </small>
                                    </div>

                                    <?php if (!empty($notification['item_code']) || !empty($notification['item_title'])): ?>
                                        <div class="mt-2 small text-muted">
                                            <?php if (!empty($notification['item_title'])): ?>
                                                <strong><?= htmlspecialchars($notification['item_title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php endif; ?>
                                            <?php if (!empty($notification['item_code'])): ?>
                                                <span class="badge bg-secondary font-monospace ms-1">
                                                    <?= htmlspecialchars($notification['item_code'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($isUnread): ?>
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="notification_id" value="<?= (int)$notification['notification_id']; ?>">
                                            <button type="submit" name="mark_read" value="1" class="btn btn-sm btn-outline-primary">
                                                <i class="fa-solid fa-check me-1"></i>Mark as Read
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="small text-success mt-2">
                                            <i class="fa-solid fa-check me-1"></i>Read
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
