<?php
// Tetapan pangkalan data (Biasanya untuk pengguna XAMPP/WAMP)
$servername = "localhost";
$username   = "root";      // Username lalai (default)
$password   = "";          // Biarkan kosong jika tiada password diletakkan
$dbname     = "traceiot_system"; // TUKAR INI kepada nama database awak yang sebenar!

// Cipta sambungan ke database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Semak sama ada sambungan berjaya atau gagal
if (!$conn) {
    die("Sambungan Gagal: " . mysqli_connect_error());
}

// Boleh buang tanda (//) di bawah ni untuk test sambungan masa mula-mula buat
// echo "Berjaya disambungkan ke pangkalan data!";
?>