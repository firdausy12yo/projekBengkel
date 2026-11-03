<?php

session_start();
include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header(
        'Location: index.php?error=' .
        urlencode('Username dan password wajib diisi.')
    );
    exit();
}

/* ===============================
   CEK LOGIN ADMIN
================================ */

$stmtAdmin = $koneksi->prepare(
    'SELECT
        id_admin,
        nama_admin,
        username,
        password_hash
     FROM admin
     WHERE username = ?
     LIMIT 1'
);

$stmtAdmin->bind_param('s', $username);
$stmtAdmin->execute();
$resultAdmin = $stmtAdmin->get_result();

if ($resultAdmin->num_rows === 1) {
    $admin = $resultAdmin->fetch_assoc();

    if (password_verify($password, $admin['password_hash'])) {
        $stmtAdmin->close();

        session_regenerate_id(true);

        unset(
            $_SESSION['id_pelanggan'],
            $_SESSION['pending_verification_id'],
            $_SESSION['pending_verification_email']
        );

        $_SESSION['id_admin'] = $admin['id_admin'];
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = $admin['username'];
        $_SESSION['nama_lengkap'] = $admin['nama_admin'];

        header('Location: admin.php');
        exit();
    }
}

$stmtAdmin->close();

/* ===============================
   CEK LOGIN PELANGGAN
================================ */

$stmt = $koneksi->prepare(
    'SELECT
        id_pelanggan,
        nama_lengkap,
        username,
        email,
        password_hash,
        email_verified
     FROM pelanggan
     WHERE username = ?
     LIMIT 1'
);

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();

    if (password_verify($password, $row['password_hash'])) {
        if ((int) $row['email_verified'] !== 1) {
            $stmt->close();

            $_SESSION['pending_verification_id'] =
                (int) $row['id_pelanggan'];

            $_SESSION['pending_verification_email'] =
                $row['email'];

            header(
                'Location: verifikasi_otp.php?error=' .
                urlencode(
                    'Email akun ini belum diverifikasi. ' .
                    'Masukkan OTP atau kirim ulang OTP.'
                )
            );
            exit();
        }

        $stmt->close();

        session_regenerate_id(true);

        unset(
            $_SESSION['id_admin'],
            $_SESSION['pending_verification_id'],
            $_SESSION['pending_verification_email']
        );

        $_SESSION['id_pelanggan'] = $row['id_pelanggan'];
        $_SESSION['role'] = 'pelanggan';
        $_SESSION['username'] = $row['username'];
        $_SESSION['nama_lengkap'] = $row['nama_lengkap'];

        header('Location: index.php');
        exit();
    }
}

$stmt->close();

header(
    'Location: index.php?error=' .
    urlencode('Username atau password salah.')
);
exit();
