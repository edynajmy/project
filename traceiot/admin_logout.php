<?php
// Mulakan session untuk kenal pasti sesi siapa yang sedang aktif
session_start();

// Buang semua data memori sistem tentang Admin ini
session_unset();
session_destroy();

// Hantar Admin kembali ke halaman Log Masuk Admin
header("Location: signin.php");
exit();
?>