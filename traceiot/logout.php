<?php
session_start();

// Buang semua memori sesi yang sistem dah hafal
session_unset();
session_destroy();

// Bawa pengguna kembali ke halaman utama (Sign In / Daftar)
header("Location: signin.php");
exit();
?>