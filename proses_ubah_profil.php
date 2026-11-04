<?php

session_start();
include 'koneksi.php';


// ==========================================
// CEK LOGIN
// ==========================================

if (!isset($_SESSION['id_pelanggan'])) {

    header(
        "Location: index.php?error="
            . urlencode("Silakan login terlebih dahulu.")
    );

    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit();
}


// ==========================================
// AMBIL DATA
// ==========================================

$id_pelanggan = (int) $_SESSION['id_pelanggan'];

$nama_lengkap =
    trim($_POST['nama_lengkap'] ?? '');

$username =
    trim($_POST['username'] ?? '');

$email =
    trim($_POST['email'] ?? '');

$no_hp =
    trim($_POST['no_hp'] ?? '');


// ==========================================
// VALIDASI
// ==========================================

if (
    $nama_lengkap === '' ||
    $username === '' ||
    $email === '' ||
    $no_hp === ''
) {

    header(
        "Location: index.php?error="
            . urlencode("Semua data profil wajib diisi.")
    );

    exit();
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    header(
        "Location: index.php?error="
            . urlencode("Format email tidak valid.")
    );

    exit();
}


// Periksa email asli pelanggan
$cekEmailAsli = $koneksi->prepare(
    "SELECT email
     FROM pelanggan
     WHERE id_pelanggan = ?
     LIMIT 1"
);

$cekEmailAsli->bind_param("i", $id_pelanggan);
$cekEmailAsli->execute();

$dataEmailAsli = $cekEmailAsli->get_result()->fetch_assoc();
$cekEmailAsli->close();

if (!$dataEmailAsli) {
    header("Location: index.php?halaman=account&error=" .
        urlencode("Akun tidak ditemukan."));
    exit();
}

// Email berbeda harus melalui verifikasi ulang
if (strcasecmp($email, $dataEmailAsli['email']) !== 0) {
    header("Location: index.php?halaman=account&error=" .
        urlencode("Perubahan email membutuhkan verifikasi OTP."));
    exit();
}


// ==========================================
// CEK USERNAME / EMAIL DUPLIKAT
// ==========================================

$cek = $koneksi->prepare(
    "SELECT id_pelanggan
     FROM pelanggan
     WHERE
        (username = ? OR email = ?)
        AND id_pelanggan != ?
     LIMIT 1"
);

$cek->bind_param(
    "ssi",
    $username,
    $email,
    $id_pelanggan
);

$cek->execute();

$hasil = $cek->get_result();


if ($hasil->num_rows > 0) {

    $cek->close();

    header(
        "Location: index.php?error="
            . urlencode(
                "Username atau email sudah digunakan akun lain."
            )
    );

    exit();
}

$cek->close();


// ==========================================
// UPDATE PROFIL
// ==========================================

$update = $koneksi->prepare(
    "UPDATE pelanggan
     SET
        nama_lengkap = ?,
        username = ?,
        email = ?,
        no_HP = ?
     WHERE id_pelanggan = ?"
);

$update->bind_param(
    "ssssi",
    $nama_lengkap,
    $username,
    $email,
    $no_hp,
    $id_pelanggan
);


if ($update->execute()) {

    // Perbarui session juga
    $_SESSION['nama_lengkap'] = $nama_lengkap;
    $_SESSION['username'] = $username;

    $update->close();

    header(
        "Location: index.php?sukses="
            . urlencode("Profil berhasil diperbarui.")
    );

    exit();
} else {

    $update->close();

    header(
        "Location: index.php?error="
            . urlencode("Gagal memperbarui profil.")
    );

    exit();
}
