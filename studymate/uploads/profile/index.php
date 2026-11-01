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

$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM users
     WHERE id=?"
);

mysqli_stmt_bind_param($stmt,"i",$user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$user = mysqli_fetch_assoc($result);
?>

<div class="container mt-4 mb-5">

<div class="dashboard-card">

<div class="text-center">

<?php if(!empty($user['profile_image'])){ ?>

<img
src="../uploads/profile/<?= htmlspecialchars($user['profile_image']) ?>"
class="rounded-circle"
width="120"
height="120">

<?php }else{ ?>

<img
src="https://via.placeholder.com/120"
class="rounded-circle">

<?php } ?>

<h3 class="mt-3">

<?= htmlspecialchars($user['full_name']); ?>

</h3>

<p class="text-muted">

<?= htmlspecialchars($user['email']); ?>

</p>

</div>

<hr>

<form action="update_profile.php" method="POST">

<div class="mb-3">

<label>Full Name</label>

<input
type="text"
class="form-control"
name="full_name"
value="<?= htmlspecialchars($user['full_name']); ?>">

</div>

<div class="mb-3">

<label>Email</label>

<input
type="email"
class="form-control"
name="email"
value="<?= htmlspecialchars($user['email']); ?>">

</div>

<div class="mb-3">

<label>Course</label>

<input
type="text"
class="form-control"
name="course"
value="<?= htmlspecialchars($user['course']); ?>">

</div>

<div class="mb-3">

<label>Semester</label>

<input
type="number"
class="form-control"
name="semester"
value="<?= htmlspecialchars($user['semester']); ?>">

</div>

<button
class="btn btn-primary">

Update Profile

</button>

</form>

<hr>

<form
action="upload_photo.php"
method="POST"
enctype="multipart/form-data">

<label>

Profile Picture

</label>

<input
type="file"
class="form-control"
name="photo">

<button
class="btn btn-success mt-3">

Upload Photo

</button>

</form>

<hr>

<a
href="change_password.php"
class="btn btn-warning">

Change Password

</a>

</div>

</div>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>