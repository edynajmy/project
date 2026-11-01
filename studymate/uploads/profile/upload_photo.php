<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];

if(isset($_FILES['photo']) && $_FILES['photo']['error']==0){

    $filename=time()."_".basename($_FILES["photo"]["name"]);

    $target="../uploads/profile/".$filename;

    move_uploaded_file($_FILES["photo"]["tmp_name"],$target);

    $stmt=mysqli_prepare(
        $conn,
        "UPDATE users
         SET profile_image=?
         WHERE id=?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $filename,
        $user_id
    );

    mysqli_stmt_execute($stmt);

}

header("Location:index.php");
exit();
?>