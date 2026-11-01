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

$search = "";

if(isset($_GET['search']) && !empty($_GET['search'])){

    $search = trim($_GET['search']);

    $keyword = "%".$search."%";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
        FROM exams
        WHERE user_id=?
        AND (subject LIKE ? OR venue LIKE ?)
        ORDER BY exam_date ASC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iss",
        $user_id,
        $keyword,
        $keyword
    );

}else{

    $stmt = mysqli_prepare(
        $conn,
        "SELECT *
        FROM exams
        WHERE user_id=?
        ORDER BY exam_date ASC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );

}

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

?>

<div class="container mt-4 mb-5">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>📅 My Exams</h2>

<p class="text-muted">

Manage upcoming examinations.

</p>

</div>

<button
class="btn btn-primary"
data-bs-toggle="modal"
data-bs-target="#addExamModal">

<i class="bi bi-plus-circle"></i>

Add Exam

</button>

</div>

<form method="GET" class="mb-4">

<div class="input-group">

<input
type="text"
class="form-control"
placeholder="Search exam..."
name="search"
value="<?php echo htmlspecialchars($search); ?>">

<button class="btn btn-outline-primary">

Search

</button>

</div>

</form>

<div class="table-responsive">

<table class="table table-hover">

<thead class="table-dark">

<tr>

<th>Subject</th>

<th>Date</th>

<th>Time</th>

<th>Venue</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php if(mysqli_num_rows($result) > 0){ ?>

    <?php while($row = mysqli_fetch_assoc($result)){ ?>

    <tr>

        <td>

            <strong>

                <?= htmlspecialchars($row['exam_title']) ?>

            </strong>

            <br>

            <small class="text-muted">

                <?= htmlspecialchars($row['subject']) ?>

            </small>

        </td>

        <td>

            <?= date("d M Y", strtotime($row['exam_date'])) ?>

        </td>

        <td>

            <?= date("g:i A", strtotime($row['exam_time'])) ?>

        </td>

        <td>

            <?= htmlspecialchars($row['venue']) ?>

        </td>

        <td>

            <a
                href="edit_exam.php?id=<?= $row['id']; ?>"
                class="btn btn-warning btn-sm">

                <i class="bi bi-pencil"></i>

            </a>

            <a
                href="delete_exam.php?id=<?= $row['id']; ?>"
                class="btn btn-danger btn-sm"
                onclick="return confirm('Delete this exam?')">

                <i class="bi bi-trash"></i>

            </a>

        </td>

    </tr>

    <?php } ?>

<?php } else { ?>

<tr>

<td colspan="5" class="text-center">

No exam found.

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

<?php include("add_exam.php"); ?>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>