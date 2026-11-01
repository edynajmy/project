<?php
session_start();

if(!isset($_SESSION['user_id'])){
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
FROM schedules
WHERE user_id=?
ORDER BY study_date,start_time ASC"
);

mysqli_stmt_bind_param(
$stmt,
"i",
$user_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>

<div class="container mt-4 mb-5">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>📅 Study Schedule</h2>

<p class="text-muted">

Organise your daily study timetable.

</p>

</div>

<button
class="btn btn-primary"
data-bs-toggle="modal"
data-bs-target="#addScheduleModal">

<i class="bi bi-plus-circle"></i>

Add Schedule

</button>

</div>

<div class="row">

<?php if(mysqli_num_rows($result)>0){ ?>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<div class="col-lg-6 mb-4">

<div class="card shadow-sm h-100">

<div class="card-body">

<h4 class="text-primary">

<?= htmlspecialchars($row['subject']); ?>

</h4>

<hr>

<p>

📖

<strong>Topic:</strong>

<?= htmlspecialchars($row['topic']); ?>

</p>

<p>

📅

<?= date("d M Y",strtotime($row['study_date'])); ?>

</p>

<p>

🕒

<?= date("g:i A",strtotime($row['start_time'])); ?>

-

<?= date("g:i A",strtotime($row['end_time'])); ?>

</p>

<div class="mt-3">

<a
href="edit_schedule.php?id=<?= $row['id']; ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil"></i>

Edit

</a>

<a
href="delete_schedule.php?id=<?= $row['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this schedule?')">

<i class="bi bi-trash"></i>

Delete

</a>

</div>

</div>

</div>

</div>

<?php } ?>

<?php }else{ ?>

<div class="col-12">

<div class="alert alert-info">

No study schedule available.

</div>

</div>

<?php } ?>

</div>

</div>

<?php include("add_schedule.php"); ?>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>