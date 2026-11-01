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

$stmt = mysqli_prepare($conn,
"SELECT * FROM assignments
WHERE id=? AND user_id=?");

mysqli_stmt_bind_param($stmt,"ii",$id,$user_id);

mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

$assignment=mysqli_fetch_assoc($result);

if(!$assignment){
    die("Assignment not found.");
}

if(isset($_POST['update'])){

    $title=$_POST['title'];
    $subject=$_POST['subject'];
    $description=$_POST['description'];
    $due_date=$_POST['due_date'];
    $priority=$_POST['priority'];
    $status=$_POST['status'];

    $update=mysqli_prepare($conn,
    "UPDATE assignments
    SET
    title=?,
    subject=?,
    description=?,
    due_date=?,
    priority=?,
    status=?
    WHERE id=? AND user_id=?");

    mysqli_stmt_bind_param(
        $update,
        "ssssssii",
        $title,
        $subject,
        $description,
        $due_date,
        $priority,
        $status,
        $id,
        $user_id
    );


        mysqli_stmt_execute($update);

    // CHECK STUDY MASTER ACHIEVEMENT
    if($status == 'Completed'){

        $completed = mysqli_fetch_assoc(
            mysqli_query(
                $conn,
                "SELECT COUNT(*) total
                FROM assignments
                WHERE user_id='$user_id'
                AND status='Completed'"
            )
        );

        if($completed['total'] >= 10){

            $exist = mysqli_num_rows(
                mysqli_query(
                    $conn,
                    "SELECT id
                    FROM achievements
                    WHERE user_id='$user_id'
                    AND badge_name='Study Master'"
                )
            );

            if($exist == 0){

                mysqli_query(
                    $conn,
                    "INSERT INTO achievements
                    (
                        user_id,
                        badge_name,
                        description,
                        earned_date
                    )
                    VALUES
                    (
                        '$user_id',
                        'Study Master',
                        'Completed 10 assignments.',
                        CURDATE()
                    )"
                );
            }
        }
    }

    header("Location:index.php");
    exit();
}
?>

<?php include("../includes/header.php"); ?>
<?php include("../includes/navbar.php"); ?>

<div class="container mt-4">

<h2>Edit Assignment</h2>

<form method="POST">

<div class="mb-3">

<label>Title</label>

<input
class="form-control"
name="title"
value="<?= htmlspecialchars($assignment['title']) ?>"
required>

</div>

<div class="mb-3">

<label>Subject</label>

<input
class="form-control"
name="subject"
value="<?= htmlspecialchars($assignment['subject']) ?>"
required>

</div>

<div class="mb-3">

<label>Description</label>

<textarea
class="form-control"
name="description"><?= htmlspecialchars($assignment['description']) ?></textarea>

</div>

<div class="row">

<div class="col-md-6">

<label>Due Date</label>

<input
type="date"
class="form-control"
name="due_date"
value="<?= $assignment['due_date'] ?>">

</div>

<div class="col-md-6">

<label>Priority</label>

<select
class="form-select"
name="priority">

<option <?=($assignment['priority']=="High")?"selected":""?>>High</option>
<option <?=($assignment['priority']=="Medium")?"selected":""?>>Medium</option>
<option <?=($assignment['priority']=="Low")?"selected":""?>>Low</option>

</select>

</div>

</div>

<div class="mt-3">

<label>Status</label>

<select
class="form-select"
name="status">

<option <?=($assignment['status']=="Pending")?"selected":""?>>Pending</option>

<option <?=($assignment['status']=="Completed")?"selected":""?>>Completed</option>

</select>

</div>

<div class="mt-4">

<button
name="update"
class="btn btn-primary">

Update Assignment

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