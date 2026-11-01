<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    require_once(__DIR__ . '/../config/database.php');
}

$notification = ['total' => 0];
$userImage = ['profile_image' => ''];

if (isset($_SESSION['user_id'])) {

    $user_id = (int) $_SESSION['user_id'];

    $notificationQuery = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total
        FROM notifications
        WHERE user_id='$user_id'
        AND is_read=0"
    );

    if ($notificationQuery) {
        $notification = mysqli_fetch_assoc($notificationQuery);
    }

    $userQuery = mysqli_query(
        $conn,
        "SELECT profile_image
        FROM users
        WHERE id='$user_id'"
    );

    if ($userQuery) {
        $userImage = mysqli_fetch_assoc($userQuery);
    }
}
?>

<nav class="navbar navbar-expand-lg bg-white shadow-sm px-3">

    <div class="container-fluid">

        <!-- Logo -->
        <span class="navbar-brand fw-bold text-primary">
            📚 StudyMate
        </span>

        <!-- Right Icons -->
        <div class="d-flex align-items-center">

            <!-- About -->
            <a
                href="../about/index.php"
                class="btn btn-light me-2"
            >
                <i class="bi bi-info-circle"></i>
            </a>

            <!-- Notification -->
            <a
                href="../notifications/index.php"
                class="btn btn-light me-2 position-relative"
            >
                <i class="bi bi-bell-fill"></i>

                <?php if ($notification['total'] > 0) { ?>
                    <span
                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $notification['total']; ?>
                    </span>
                <?php } ?>
            </a>

            <!-- Profile -->
            <a
                href="../profile/index.php"
                class="btn btn-light me-2"
            >

                <?php if (!empty($userImage['profile_image'])) { ?>

                    <img
                        src="../uploads/profile/<?= $userImage['profile_image']; ?>"
                        width="30"
                        height="30"
                        class="rounded-circle"
                        style="object-fit:cover;"
                    >

                <?php } else { ?>

                    <i class="bi bi-person-circle"></i>

                <?php } ?>

            </a>

            <!-- Logout -->
            <a
                href="../auth/logout.php"
                class="btn btn-danger"
            >
                <i class="bi bi-box-arrow-right"></i>
            </a>

        </div>

    </div>

</nav>