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
    "SELECT * FROM exams
     WHERE id=? AND user_id=?"
);

mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$exam = mysqli_fetch_assoc($result);

if (!$exam) {
    die("Exam not found.");
}

if (isset($_POST['update'])) {

    $subject = trim($_POST['subject']);
    $exam_title = trim($_POST['exam_title']);
    $exam_date = $_POST['exam_date'];
    $exam_time = $_POST['exam_time'];
    $venue = trim($_POST['venue']);
    $notes = trim($_POST['notes']);

    $update = mysqli_prepare(
        $conn,
        "UPDATE exams
         SET subject=?,
             exam_title=?,
             exam_date=?,
             exam_time=?,
             venue=?,
             notes=?
         WHERE id=? AND user_id=?"
    );

    mysqli_stmt_bind_param(
        $update,
        "ssssssii",
        $subject,
        $exam_title,
        $exam_date,
        $exam_time,
        $venue,
        $notes,
        $id,
        $user_id
    );

    mysqli_stmt_execute($update);

    header("Location: index.php");
    exit();
}

include("../includes/header.php");
include("../includes/navbar.php");
?>

<div class="container mt-4">

<h2>Edit Exam</h2>

<form method="POST">

<div class="mb-3">
<label>Exam Title</label>
<input type="text"
class="form-control"
name="exam_title"
value="<?= htmlspecialchars($exam['exam_title']) ?>"
required>
</div>

<div class="mb-3">
<label>Subject</label>
<input type="text"
class="form-control"
name="subject"
value="<?= htmlspecialchars($exam['subject']) ?>"
required>
</div>

<div class="row">

<div class="col-md-6">
<label>Exam Date</label>
<input type="date"
class="form-control"
name="exam_date"
value="<?= $exam['exam_date'] ?>">
</div>

<div class="col-md-6">
<label>Exam Time</label>
<input type="time"
class="form-control"
name="exam_time"
value="<?= $exam['exam_time'] ?>">
</div>

</div>

<div class="mt-3">
<label>Venue</label>
<input type="text"
class="form-control"
name="venue"
value="<?= htmlspecialchars($exam['venue']) ?>">
</div>

<div class="mt-3">
<label>Notes</label>
<textarea
class="form-control"
name="notes"
rows="4"><?= htmlspecialchars($exam['notes']) ?></textarea>
</div>

<div class="mt-4">

<button
class="btn btn-primary"
name="update">

Update Exam

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