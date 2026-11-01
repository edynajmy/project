<?php
session_start();

include("../config/database.php");

$user_id = $_SESSION['user_id'];

if(isset($_FILES['profile_image'])){

    $file = $_FILES['profile_image'];

    $filename = time().'_'.$file['name'];

    $target =
        "../uploads/profile/".$filename;

    move_uploaded_file(
        $file['tmp_name'],
        $target
    );

    mysqli_query(
        $conn,
        "UPDATE users
         SET profile_image='$filename'
         WHERE id='$user_id'"
    );
}

header("Location: index.php");
exit();
?>