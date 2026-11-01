<?php
session_start();

if(isset($_SESSION['user_id'])){
    header("Location: ../dashboard/index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Register | StudyMate</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/auth.css">

</head>

<body>

<div class="login-container">

<div class="login-card">

<div class="text-center mb-4">

<h1 class="logo">📚 StudyMate</h1>

<p class="text-muted">
Create your account
</p>

</div>

<form action="register_process.php" method="POST">

<div class="mb-3">

<label class="form-label">Full Name</label>

<input
type="text"
class="form-control"
name="full_name"
required>

</div>

<div class="mb-3">

<label class="form-label">Email</label>

<input
type="email"
class="form-control"
name="email"
required>

</div>

<div class="mb-3">

<label class="form-label">Course</label>

<input
type="text"
class="form-control"
name="course"
placeholder="Example: CS110"
required>

</div>

<div class="mb-3">

<label class="form-label">Semester</label>

<select
class="form-select"
name="semester"
required>

<option value="">Choose Semester</option>

<option>1</option>
<option>2</option>
<option>3</option>
<option>4</option>
<option>5</option>
<option>6</option>

</select>

</div>

<div class="mb-3">

<label>Password</label>

<input
type="password"
class="form-control"
name="password"
required>

</div>

<div class="mb-4">

<label>Confirm Password</label>

<input
type="password"
class="form-control"
name="confirm_password"
required>

</div>

<button class="btn btn-primary w-100">

<i class="bi bi-person-plus"></i>

Register

</button>

</form>

<div class="text-center mt-4">

Already have an account?

<a href="login.php">

Login

</a>

</div>

</div>

</div>

</body>

</html>