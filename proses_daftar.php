<?php

session_start();
require_once 'koneksi.php';
require_once 'email_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');
$no_hp = trim($_POST['no_hp'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (
    $nama_lengkap === '' ||
    $alamat === '' ||
    $no_hp === '' ||
    $email === '' ||
    $username === '' ||
    $password === ''
) {
    header('Location: index.php?error=' . urlencode('Semua field wajib diisi.'));
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?error=' . urlencode('Format email tidak valid.'));
    exit();
}

if (!preg_match('/^08[0-9]{8,11}$/', $no_hp)) {
    header(
        'Location: index.php?error=' .
        urlencode('Nomor HP harus diawali 08 dan terdiri dari 10-13 digit.')
    );
    exit();
}

if (strlen($password) < 6) {
    header(
        'Location: index.php?error=' .
        urlencode('Password minimal 6 karakter.')
    );
    exit();
}

/* Cek username */
$cekUsername = $koneksi->prepare(
    'SELECT id_pelanggan
     FROM pelanggan
     WHERE username = ?
     LIMIT 1'
);

$cekUsername->bind_param('s', $username);
$cekUsername->execute();
$hasilUsername = $cekUsername->get_result();

if ($hasilUsername->num_rows > 0) {
    $cekUsername->close();

    header(
        'Location: index.php?error=' .
        urlencode('Username sudah digunakan.')
    );
    exit();
}

$cekUsername->close();

/* Cek email */
$cekEmail = $koneksi->prepare(
    'SELECT id_pelanggan, email_verified
     FROM pelanggan
     WHERE email = ?
     LIMIT 1'
);

$cekEmail->bind_param('s', $email);
$cekEmail->execute();
$hasilEmail = $cekEmail->get_result();

if ($hasilEmail->num_rows === 1) {
    $akunLama = $hasilEmail->fetch_assoc();
    $cekEmail->close();

    if ((int) $akunLama['email_verified'] === 0) {
        $_SESSION['pending_verification_id'] =
            (int) $akunLama['id_pelanggan'];

        $_SESSION['pending_verification_email'] = $email;

        header(
            'Location: verifikasi_otp.php?error=' .
            urlencode(
                'Email ini sudah terdaftar tetapi belum diverifikasi. ' .
                'Silakan verifikasi atau kirim ulang OTP.'
            )
        );
        exit();
    }

    header(
        'Location: index.php?error=' .
        urlencode('Email sudah terdaftar.')
    );
    exit();
}

$cekEmail->close();

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$otp = (string) random_int(100000, 999999);
$otp_hash = password_hash($otp, PASSWORD_DEFAULT);
$otp_expired_at = date('Y-m-d H:i:s', time() + 300);
$otp_last_sent_at = date('Y-m-d H:i:s');

$stmt = $koneksi->prepare(
    'INSERT INTO pelanggan (
        nama_lengkap,
        username,
        no_HP,
        email,
        alamat,
        password_hash,
        email_verified,
        otp_hash,
        otp_expired_at,
        otp_last_sent_at,
        otp_attempts
    ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, 0)'
);

$stmt->bind_param(
    'sssssssss',
    $nama_lengkap,
    $username,
    $no_hp,
    $email,
    $alamat,
    $password_hash,
    $otp_hash,
    $otp_expired_at,
    $otp_last_sent_at
);

if (!$stmt->execute()) {
    $stmt->close();

    header(
        'Location: index.php?error=' .
        urlencode('Pendaftaran gagal. Silakan coba lagi.')
    );
    exit();
}

$id_pelanggan = $stmt->insert_id;
$stmt->close();

$_SESSION['pending_verification_id'] = $id_pelanggan;
$_SESSION['pending_verification_email'] = $email;

[$terkirim, $pesanEmail] =
    kirimOtpEmail($email, $nama_lengkap, $otp);

if (!$terkirim) {
    header(
        'Location: verifikasi_otp.php?error=' .
        urlencode(
            'Akun berhasil dibuat, tetapi ' . $pesanEmail .
            ' Setelah SMTP diperbaiki, klik Kirim Ulang OTP.'
        )
    );
    exit();
}

header(
    'Location: verifikasi_otp.php?sukses=' .
    urlencode('Kode OTP sudah dikirim ke email kamu.')
);
exit();
