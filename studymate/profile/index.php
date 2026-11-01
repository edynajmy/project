<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");
include("../includes/header.php");
include("../includes/navbar.php");

$user_id = $_SESSION['user_id'];

$query = mysqli_query(
    $conn,
    "SELECT * FROM users
    WHERE id='$user_id'"
);

$user = mysqli_fetch_assoc($query);
?>

<div class="container mt-4">

<div class="dashboard-card p-4">

    <h2 class="fw-bold mb-4">
        👤 My Profile
    </h2>

    <hr>

    <div class="text-center mb-4">

        <?php if(!empty($user['profile_image'])){ ?>

            <img
                src="../uploads/profile/<?= $user['profile_image']; ?>"
                width="150"
                height="150"
                class="rounded-circle border shadow"
                style="object-fit:cover;"
            >

        <?php } else { ?>

            <i
                class="bi bi-person-circle text-secondary"
                style="font-size:120px;"
            ></i>

        <?php } ?>

        <form
            action="upload_image.php"
            method="POST"
            enctype="multipart/form-data"
            class="mt-3"
        >

            <input
                type="file"
                name="profile_image"
                class="form-control mb-3"
                required
            >

            <button
                type="submit"
                class="btn btn-primary rounded-pill"
            >
                <i class="bi bi-upload"></i>
                Upload Picture
            </button>

        </form>

    </div>

    <div class="text-center">

        <h4 class="fw-bold">
            <?= htmlspecialchars($user['full_name']); ?>
        </h4>

        <p class="text-muted">
            <?= htmlspecialchars($user['email']); ?>
        </p>

    </div>

    <hr>

    <div class="row">

        <div class="col-md-6 mb-3">
            <strong>Email:</strong><br>
            <?= htmlspecialchars($user['email']); ?>
        </div>

        <div class="col-md-6 mb-3">
            <strong>Course:</strong><br>
            <?= htmlspecialchars($user['course']); ?>
        </div>

        <div class="col-md-6 mb-3">
            <strong>Semester:</strong><br>
            <?= htmlspecialchars($user['semester']); ?>
        </div>

        <?php if(isset($user['created_at'])){ ?>

        <div class="col-md-6 mb-3">
            <strong>Date Joined:</strong><br>
            <?= date(
                "d M Y",
                strtotime($user['created_at'])
            ); ?>
        </div>

        <?php } ?>

    </div>

    <div class="mt-4">

        <a
            href="edit_profile.php"
            class="btn btn-warning rounded-pill"
        >
            <i class="bi bi-pencil-square"></i>
            Edit Profile
        </a>

        <a
            href="change_password.php"
            class="btn btn-danger rounded-pill ms-2"
        >
            <i class="bi bi-key"></i>
            Change Password
        </a>

    </div>

</div>

</div>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>
