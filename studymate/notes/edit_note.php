<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];
$id = $_GET['id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM notes WHERE id=? AND user_id=?"
);

mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$note = mysqli_fetch_assoc($result);

if (!$note) {
    die("Note not found.");
}

if (isset($_POST['update'])) {

    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    $update = mysqli_prepare(
        $conn,
        "UPDATE notes
         SET title=?, content=?
         WHERE id=? AND user_id=?"
    );

    mysqli_stmt_bind_param(
        $update,
        "ssii",
        $title,
        $content,
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

<h2>Edit Note</h2>

<form method="POST">

<div class="mb-3">

<label>Title</label>

<input
type="text"
class="form-control"
name="title"
value="<?= htmlspecialchars($note['title']) ?>"
required>

</div>

<div class="mb-3">

<label>Content</label>

<textarea
class="form-control"
rows="10"
name="content"
required><?= htmlspecialchars($note['content']) ?></textarea>

</div>

<button
class="btn btn-primary"
name="update">

Update Note

</button>

<a
href="index.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

<?php include("../includes/footer.php"); ?>