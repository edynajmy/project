<?php
session_start();

if(
    !isset($_SESSION['reset_email'])
){
    header(
        "Location: forgot_password.php"
    );
    exit();
}

include("../config/database.php");

$message = "";

if(isset($_POST['submit'])){

    $password =
        mysqli_real_escape_string(
            $conn,
            $_POST['password']
        );

    $confirm =
        mysqli_real_escape_string(
            $conn,
            $_POST['confirm']
        );

    if($password != $confirm){

        $message =
        "Passwords do not match.";

    }else{

        $email =
            $_SESSION['reset_email'];

        $hashed =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        mysqli_query(
            $conn,
            "UPDATE users
            SET password='$hashed'
            WHERE email='$email'"
        );

        unset(
            $_SESSION['reset_email']
        );

        echo "
        <script>
        alert(
            'Password updated successfully!'
        );
        window.location=
        'login.php';
        </script>";
        exit();
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
                    Reset Password
                </h3>

                <?php if($message != ""){ ?>

                    <div class="alert alert-danger">
                        <?= $message; ?>
                    </div>

                <?php } ?>

                <form method="POST">

                    <div class="mb-3">

                        <label>
                            New Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            required
                        >

                    </div>

                    <div class="mb-3">

                        <label>
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            name="confirm"
                            class="form-control"
                            required
                        >

                    </div>

                    <button
                        type="submit"
                        name="submit"
                        class="btn btn-success w-100"
                    >
                        Reset Password
                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

</div>

<?php include("../includes/footer.php"); ?>
