<?php
session_start();
include("../config/database.php");
include("../includes/header.php");
include("../includes/navbar.php");

$user_id = $_SESSION['user_id'];

$result = mysqli_query(
    $conn,
    "SELECT *
     FROM achievements
     WHERE user_id='$user_id'
     ORDER BY earned_date DESC"
);
?>

<div class="container mt-4">

    <h2>🏆 My Achievements</h2>

    <div class="row">

        <?php while($row = mysqli_fetch_assoc($result)){ ?>

            <div class="col-md-4 mb-3">

                <div class="card shadow text-center p-3">

                    <h3>🏆</h3>

                    <h5>
                        <?= $row['badge_name'] ?>
                    </h5>

                    <p>
                        <?= $row['description'] ?>
                    </p>

                    <small class="text-muted">
                        Earned on
                        <?= date('d M Y', strtotime($row['earned_date'])) ?>
                    </small>

                </div>

            </div>

        <?php } ?>

    </div>

</div>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>