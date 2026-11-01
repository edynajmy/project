<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");
include("../includes/header.php");
include("../includes/navbar.php");

$user_id=$_SESSION['user_id'];

$search="";

if(isset($_GET['search']) && !empty($_GET['search'])){

    $search=trim($_GET['search']);

    $keyword="%".$search."%";

    $stmt=mysqli_prepare(
        $conn,
        "SELECT *
        FROM notes
        WHERE user_id=?
        AND title LIKE ?
        ORDER BY created_at DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $user_id,
        $keyword
    );

}else{

    $stmt=mysqli_prepare(
        $conn,
        "SELECT *
        FROM notes
        WHERE user_id=?
        ORDER BY created_at DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );

}

mysqli_stmt_execute($stmt);

$result=mysqli_stmt_get_result($stmt);

?>

<div class="container mt-4 mb-5">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2>📝 My Notes</h2>

<p class="text-muted">

Save lecture notes and revision notes.

</p>

</div>

<button
class="btn btn-primary"
data-bs-toggle="modal"
data-bs-target="#addNoteModal">

<i class="bi bi-plus-circle"></i>

Add Note

</button>

</div>

<form method="GET" class="mb-4">

<div class="input-group">

<input
type="text"
class="form-control"
placeholder="Search notes..."
name="search"
value="<?= htmlspecialchars($search); ?>">

<button
class="btn btn-outline-primary">

Search

</button>

</div>

</form>

<div class="row">

<?php if(mysqli_num_rows($result)>0){ ?>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<div class="col-md-6 col-lg-4 mb-4">

<div class="card shadow-sm h-100">

<div class="card-body">

<h5>

<?= htmlspecialchars($row['title']); ?>

</h5>

<hr>

<p>

<?= nl2br(htmlspecialchars($row['content'])); ?>

</p>

<div class="mt-3">

<a
href="edit_note.php?id=<?= $row['id']; ?>"
class="btn btn-warning btn-sm">

<i class="bi bi-pencil"></i>

Edit

</a>

<a
href="delete_note.php?id=<?= $row['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this note?')">

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

No notes available.

</div>

</div>

<?php } ?>

</div>

</div>

<?php include("add_note.php"); ?>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>