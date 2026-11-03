<?php

session_start();
require_once 'koneksi.php';
require_once 'email_helper.php';

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

$stmt = $koneksi->prepare(
    'SELECT
        nama_lengkap,
        email,
        email_verified,
        otp_last_sent_at
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

if (!empty($akun['otp_last_sent_at'])) {
    $selisih = time() - strtotime($akun['otp_last_sent_at']);

    if ($selisih < 60) {
        $tunggu = 60 - $selisih;

        header(
            'Location: verifikasi_otp.php?error=' .
            urlencode(
                'Tunggu ' . $tunggu .
                ' detik sebelum meminta OTP baru.'
            )
        );
        exit();
    }
}

$otp = (string) random_int(100000, 999999);
$otp_hash = password_hash($otp, PASSWORD_DEFAULT);
$otp_expired_at = date('Y-m-d H:i:s', time() + 300);
$otp_last_sent_at = date('Y-m-d H:i:s');

$update = $koneksi->prepare(
    'UPDATE pelanggan
     SET otp_hash = ?,
         otp_expired_at = ?,
         otp_last_sent_at = ?,
         otp_attempts = 0
     WHERE id_pelanggan = ?'
);

$update->bind_param(
    'sssi',
    $otp_hash,
    $otp_expired_at,
    $otp_last_sent_at,
    $id_pelanggan
);

if (!$update->execute()) {
    $update->close();

    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode('Gagal membuat OTP baru.')
    );
    exit();
}

$update->close();

[$terkirim, $pesanEmail] =
    kirimOtpEmail(
        $akun['email'],
        $akun['nama_lengkap'],
        $otp
    );

if (!$terkirim) {
    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode($pesanEmail)
    );
    exit();
}

header(
    'Location: verifikasi_otp.php?sukses=' .
    urlencode('OTP baru sudah dikirim ke email kamu.')
);
exit();
