<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/database.php");

$user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $query = mysqli_query(
        $conn,
        "SELECT password
         FROM users
         WHERE id='$user_id'"
    );

    $user = mysqli_fetch_assoc($query);

    if (!password_verify($current, $user['password'])) {

        $message =
            '<div class="alert alert-danger">
                Current password is incorrect.
            </div>';

    } elseif ($new != $confirm) {

        $message =
            '<div class="alert alert-danger">
                New passwords do not match.
            </div>';

    } else {

        $newPassword =
            password_hash($new, PASSWORD_DEFAULT);

        mysqli_query(
            $conn,
            "UPDATE users
             SET password='$newPassword'
             WHERE id='$user_id'"
        );

        $message =
            '<div class="alert alert-success">
                Password changed successfully.
            </div>';
    }
}

include("../includes/header.php");
include("../includes/navbar.php");
?>

<div class="container mt-4">

    <div class="dashboard-card">

        <h2>🔒 Change Password</h2>

        <?= $message ?>

        <form method="POST">

            <div class="mb-3">

                <label class="form-label">
                    Current Password
                </label>

                <input
                    type="password"
                    name="current_password"
                    class="form-control"
                    required>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    New Password
                </label>

                <input
                    type="password"
                    name="new_password"
                    class="form-control"
                    required>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Confirm Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    class="form-control"
                    required>

            </div>

            <button
                type="submit"
                class="btn btn-primary">

                <i class="bi bi-key"></i>
                Change Password

            </button>

        </form>

    </div>

</div>

<?php
include("../includes/sidebar.php");
include("../includes/footer.php");
?>