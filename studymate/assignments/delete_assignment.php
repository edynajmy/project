<?php

session_start();

if(!isset($_SESSION['user_id'])){

    header("Location: ../auth/login.php");

    exit();

}

include("../config/database.php");

$id=$_GET['id'];

$user_id=$_SESSION['user_id'];

$stmt=mysqli_prepare(
$conn,
"DELETE FROM assignments
WHERE id=? AND user_id=?"
);

mysqli_stmt_bind_param(
$stmt,
"ii",
$id,
$user_id
);

mysqli_stmt_execute($stmt);

header("Location:index.php");

exit();

?>