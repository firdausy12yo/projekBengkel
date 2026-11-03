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
