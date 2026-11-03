<?php
session_start();
include 'koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $alamat       = trim($_POST['alamat'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $password     = $_POST['password'] ?? '';

    // Validasi tidak boleh kosong
    if (
        empty($nama_lengkap) ||
        empty($email) ||
        empty($alamat) ||
        empty($username) ||
        empty($password)
    ) {
        header("Location: index.php?error=Semua+field+wajib+diisi!");
        exit();
    }

    // Validasi panjang password
    if (strlen($password) < 6) {
        header("Location: index.php?error=Password+minimal+6+karakter!");
        exit();
    }

    // Cek apakah username atau email sudah ada (pakai prepared statement)
    $cek = $koneksi->prepare("SELECT id_pelanggan FROM pelanggan WHERE username = ? OR email = ?");
    $cek->bind_param("ss", $username, $email);
    $cek->execute();
    $cek->store_result();

    if ($cek->num_rows > 0) {
        $cek->close();
        header("Location: index.php?error=Username+atau+Email+sudah+terdaftar!");
        exit();
    }
    $cek->close();

    // Hash password & simpan ke database
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare(
        "INSERT INTO pelanggan
    (nama_lengkap, email, alamat, username, password_hash)
    VALUES (?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "sssss",
        $nama_lengkap,
        $email,
        $alamat,
        $username,
        $password_hash
    );

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: index.php?sukses=Pendaftaran+berhasil!+Silakan+login.");
        exit();
    } else {
        $stmt->close();
        header("Location: index.php?error=Pendaftaran+gagal.+Silakan+coba+lagi.");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
