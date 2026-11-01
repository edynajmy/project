<?php
session_start();
include("../config/database.php");

$message = "";

if(isset($_POST['submit'])){

    $email = mysqli_real_escape_string(
        $conn,
        $_POST['email']
    );

    $query = mysqli_query(
        $conn,
        "SELECT *
        FROM users
        WHERE email='$email'"
    );

    if(mysqli_num_rows($query) > 0){

        $_SESSION['reset_email'] = $email;

        header(
            "Location: reset_password.php"
        );
        exit();

    }else{

        $message =
        "Email not found.";

    }
}
?>

<?php include("../includes/header.php"); ?>

<div class="container mt-5">

<div class="row justify-content-center">

    <div class="col-md-5">

        <div class="card shadow">

            <div class="card-body">

                <h3 class="text-center mb-4">
                    Forgot Password
                </h3>

                <?php if($message != ""){ ?>

                    <div class="alert alert-danger">
                        <?= $message; ?>
                    </div>

                <?php } ?>

                <form method="POST">

                    <div class="mb-3">

                        <label>
                            Email Address
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        name="submit"
                        class="btn btn-primary w-100"
                    >
                        Continue
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

</div>

<?php include("../includes/footer.php"); ?>
