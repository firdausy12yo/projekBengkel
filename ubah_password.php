<?php

session_start();
include 'koneksi.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['id_pelanggan'])) {
    header("Location: index.php?error=Silakan+login+terlebih+dahulu.");
    exit();
}

// Hanya menerima POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

$id_pelanggan        = $_SESSION['id_pelanggan'];
$password_lama       = $_POST['password_lama'] ?? '';
$password_baru       = $_POST['password_baru'] ?? '';
$konfirmasi_password = $_POST['konfirmasi_password'] ?? '';


// ==============================
// Validasi field
// ==============================

if (
    empty($password_lama) ||
    empty($password_baru) ||
    empty($konfirmasi_password)
) {
    header("Location: index.php?error=Semua+field+password+wajib+diisi.");
    exit();
}


// ==============================
// Minimal 6 karakter
// ==============================

if (strlen($password_baru) < 6) {
    header("Location: index.php?error=Password+baru+minimal+6+karakter.");
    exit();
}


// ==============================
// Konfirmasi password
// ==============================

if ($password_baru !== $konfirmasi_password) {
    header("Location: index.php?error=Konfirmasi+password+tidak+sama.");
    exit();
}


// ==============================
// Ambil password lama dari database
// ==============================

$stmt = $koneksi->prepare(
    "SELECT password_hash
     FROM pelanggan
     WHERE id_pelanggan = ?"
);

$stmt->bind_param("i", $id_pelanggan);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();

    header("Location: index.php?error=Akun+tidak+ditemukan.");
    exit();
}

$data = $result->fetch_assoc();

$stmt->close();


// ==============================
// Periksa password lama
// ==============================

if (!password_verify($password_lama, $data['password_hash'])) {

    header("Location: index.php?error=Password+lama+salah.");
    exit();
}


// ==============================
// Jangan gunakan password sama
// ==============================

if (password_verify($password_baru, $data['password_hash'])) {

    header("Location: index.php?error=Password+baru+tidak+boleh+sama+dengan+password+lama.");
    exit();
}


// ==============================
// Hash password baru
// ==============================

$password_hash_baru = password_hash(
    $password_baru,
    PASSWORD_DEFAULT
);


// ==============================
// Update database
// ==============================

$update = $koneksi->prepare(
    "UPDATE pelanggan
     SET password_hash = ?
     WHERE id_pelanggan = ?"
);

$update->bind_param(
    "si",
    $password_hash_baru,
    $id_pelanggan
);


// ==============================
// Cek hasil update
// ==============================

if ($update->execute()) {

    $update->close();

    header("Location: index.php?sukses=Password+berhasil+diubah.");
    exit();
} else {

    $update->close();

    header("Location: index.php?error=Password+gagal+diubah.");
    exit();
}
