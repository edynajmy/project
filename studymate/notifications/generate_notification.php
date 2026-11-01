<?php
include("../config/database.php");

$tomorrow = date('Y-m-d', strtotime('+1 day'));

$query = mysqli_query(
    $conn,
    "SELECT *
     FROM assignments
     WHERE due_date='$tomorrow'
     AND status='Pending'"
);

while($row = mysqli_fetch_assoc($query)){

    $user_id = $row['user_id'];

    $message =
        "Assignment '".$row['title']."' is due tomorrow.";

    mysqli_query(
        $conn,
        "INSERT INTO notifications(user_id,message)
         VALUES('$user_id','$message')"
    );
}
?>