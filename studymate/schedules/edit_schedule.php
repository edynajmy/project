<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM schedules
    WHERE id=? AND user_id=?"
);

mysqli_stmt_bind_param($stmt,"ii",$id,$user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$schedule = mysqli_fetch_assoc($result);

if(!$schedule){
    die("Schedule not found.");
}

if(isset($_POST['update'])){

    $subject=$_POST['subject'];
    $topic=$_POST['topic'];
    $study_date=$_POST['study_date'];
    $start_time=$_POST['start_time'];
    $end_time=$_POST['end_time'];

    $update=mysqli_prepare(
        $conn,
        "UPDATE schedules
        SET
        subject=?,
        topic=?,
        study_date=?,
        start_time=?,
        end_time=?
        WHERE id=? AND user_id=?"
    );

    mysqli_stmt_bind_param(
        $update,
        "sssssii",
        $subject,
        $topic,
        $study_date,
        $start_time,
        $end_time,
        $id,
        $user_id
    );

    mysqli_stmt_execute($update);

    header("Location:index.php");
    exit();
}

include("../includes/header.php");
include("../includes/navbar.php");
?>

<div class="container mt-4">

<h2>Edit Study Schedule</h2>

<form method="POST">

<div class="mb-3">
<label>Subject</label>
<input
type="text"
name="subject"
class="form-control"
value="<?= htmlspecialchars($schedule['subject']) ?>"
required>
</div>

<div class="mb-3">
<label>Topic</label>
<input
type="text"
name="topic"
class="form-control"
value="<?= htmlspecialchars($schedule['topic']) ?>"
required>
</div>

<div class="mb-3">
<label>Study Date</label>
<input
type="date"
name="study_date"
class="form-control"
value="<?= $schedule['study_date'] ?>"
required>
</div>

<div class="row">

<div class="col-md-6">
<label>Start Time</label>
<input
type="time"
name="start_time"
class="form-control"
value="<?= $schedule['start_time'] ?>"
required>
</div>

<div class="col-md-6">
<label>End Time</label>
<input
type="time"
name="end_time"
class="form-control"
value="<?= $schedule['end_time'] ?>"
required>
</div>

</div>

<div class="mt-4">

<button
class="btn btn-primary"
name="update">

Update Schedule

</button>

<a
href="index.php"
class="btn btn-secondary">

Cancel

</a>

</div>

</form>

</div>

<?php include("../includes/footer.php"); ?>