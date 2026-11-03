<?php
$host = "localhost";
$user = "root"; // Biasanya root jika menggunakan XAMPP/Laragon
$pass = "";     // Kosongkan jika tidak ada password
$db   = "db_bengkel";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>