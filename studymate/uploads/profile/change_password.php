<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id=$_SESSION['user_id'];

if(isset($_POST['change'])){

    $password=password_hash($_POST['password'],PASSWORD_DEFAULT);

    $stmt=mysqli_prepare(
        $conn,
        "UPDATE users
         SET password=?
         WHERE id=?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $password,
        $user_id
    );

    mysqli_stmt_execute($stmt);

    header("Location:index.php");
    exit();
}

include("../includes/header.php");
include("../includes/navbar.php");
?>

<div class="container mt-4">

<div class="dashboard-card">

<h3>

🔒 Change Password

</h3>

<form method="POST">

<div class="mb-3">

<label>New Password</label>

<input
type="password"
class="form-control"
name="password"
required>

</div>

<button
class="btn btn-primary"
name="change">

Update Password

</button>

<a
href="index.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

</div>

<?php include("../includes/footer.php"); ?>