<?php

session_start();
require_once 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verifikasi_otp.php');
    exit();
}

if (!isset($_SESSION['pending_verification_id'])) {
    header(
        'Location: index.php?error=' .
        urlencode('Sesi verifikasi tidak ditemukan.')
    );
    exit();
}

$id_pelanggan = (int) $_SESSION['pending_verification_id'];
$otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

if (strlen($otp) !== 6) {
    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode('Kode OTP harus terdiri dari 6 digit.')
    );
    exit();
}

$stmt = $koneksi->prepare(
    'SELECT
        email_verified,
        otp_hash,
        otp_expired_at,
        otp_attempts
     FROM pelanggan
     WHERE id_pelanggan = ?
     LIMIT 1'
);

$stmt->bind_param('i', $id_pelanggan);
$stmt->execute();
$hasil = $stmt->get_result();

if ($hasil->num_rows !== 1) {
    $stmt->close();

    header(
        'Location: index.php?error=' .
        urlencode('Akun tidak ditemukan.')
    );
    exit();
}

$akun = $hasil->fetch_assoc();
$stmt->close();

if ((int) $akun['email_verified'] === 1) {
    unset(
        $_SESSION['pending_verification_id'],
        $_SESSION['pending_verification_email']
    );

    header(
        'Location: index.php?sukses=' .
        urlencode('Email sudah terverifikasi. Silakan login.')
    );
    exit();
}

if ((int) $akun['otp_attempts'] >= 5) {
    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode('Batas percobaan OTP tercapai. Silakan kirim ulang OTP.')
    );
    exit();
}

if (
    empty($akun['otp_hash']) ||
    empty($akun['otp_expired_at']) ||
    strtotime($akun['otp_expired_at']) < time()
) {
    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode('Kode OTP sudah kedaluwarsa. Silakan kirim ulang OTP.')
    );
    exit();
}

if (!password_verify($otp, $akun['otp_hash'])) {
    $updatePercobaan = $koneksi->prepare(
        'UPDATE pelanggan
         SET otp_attempts = otp_attempts + 1
         WHERE id_pelanggan = ?'
    );

    $updatePercobaan->bind_param('i', $id_pelanggan);
    $updatePercobaan->execute();
    $updatePercobaan->close();

    $sisa = max(0, 4 - (int) $akun['otp_attempts']);

    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode(
            'Kode OTP salah. Sisa percobaan: ' . $sisa . '.'
        )
    );
    exit();
}

$verifikasi = $koneksi->prepare(
    'UPDATE pelanggan
     SET email_verified = 1,
         otp_hash = NULL,
         otp_expired_at = NULL,
         otp_last_sent_at = NULL,
         otp_attempts = 0
     WHERE id_pelanggan = ?'
);

$verifikasi->bind_param('i', $id_pelanggan);

if (!$verifikasi->execute()) {
    $verifikasi->close();

    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode('Verifikasi gagal. Silakan coba lagi.')
    );
    exit();
}

$verifikasi->close();

unset(
    $_SESSION['pending_verification_id'],
    $_SESSION['pending_verification_email']
);

header(
    'Location: index.php?sukses=' .
    urlencode('Email berhasil diverifikasi. Sekarang silakan login.')
);
exit();
