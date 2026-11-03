<?php

session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['pending_verification_id'])) {
    header(
        'Location: index.php?error=' .
        urlencode('Tidak ada proses verifikasi email yang aktif.')
    );
    exit();
}

$id_pelanggan = (int) $_SESSION['pending_verification_id'];

$stmt = $koneksi->prepare(
    'SELECT nama_lengkap, email, email_verified
     FROM pelanggan
     WHERE id_pelanggan = ?
     LIMIT 1'
);

$stmt->bind_param('i', $id_pelanggan);
$stmt->execute();
$hasil = $stmt->get_result();

if ($hasil->num_rows !== 1) {
    $stmt->close();

    unset(
        $_SESSION['pending_verification_id'],
        $_SESSION['pending_verification_email']
    );

    header(
        'Location: index.php?error=' .
        urlencode('Data akun tidak ditemukan.')
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

function sensorEmail(string $email): string
{
    [$nama, $domain] = array_pad(explode('@', $email, 2), 2, '');

    if (strlen($nama) <= 2) {
        $namaSensor = substr($nama, 0, 1) . '*';
    } else {
        $namaSensor =
            substr($nama, 0, 2) .
            str_repeat('*', max(2, strlen($nama) - 2));
    }

    return $namaSensor . '@' . $domain;
}

$email_sensor = htmlspecialchars(
    sensorEmail($akun['email']),
    ENT_QUOTES,
    'UTF-8'
);

$pesan_error =
    isset($_GET['error'])
    ? htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8')
    : '';

$pesan_sukses =
    isset($_GET['sukses'])
    ? htmlspecialchars($_GET['sukses'], ENT_QUOTES, 'UTF-8')
    : '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - PitStop.id</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f7fb;
            font-family: 'Poppins', sans-serif;
            color: #20242c;
        }

        .otp-card {
            width: min(92%, 430px);
            background: #fff;
            border-radius: 18px;
            padding: 32px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, .09);
            text-align: center;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .logo span {
            color: #e63946;
        }

        h1 {
            margin: 8px 0;
            font-size: 24px;
        }

        .desc {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .message {
            padding: 11px 13px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 13px;
            text-align: left;
        }

        .error {
            background: #fff1f2;
            color: #be123c;
        }

        .success {
            background: #ecfdf5;
            color: #047857;
        }

        .otp-input {
            width: 100%;
            padding: 15px;
            border: 1.5px solid #d7dce5;
            border-radius: 12px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 10px;
            outline: none;
        }

        .otp-input:focus {
            border-color: #e63946;
        }

        .btn {
            width: 100%;
            border: 0;
            border-radius: 11px;
            padding: 13px 16px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            margin-top: 14px;
        }

        .btn-primary {
            background: #e63946;
            color: #fff;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .small {
            margin-top: 18px;
            font-size: 12px;
            color: #8b929d;
        }

        a {
            color: #e63946;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="otp-card">
        <div class="logo">PitStop<span>.id</span></div>

        <h1>Verifikasi Email</h1>

        <p class="desc">
            Kami mengirim kode OTP 6 digit ke
            <strong><?= $email_sensor ?></strong>.
            Kode berlaku selama 5 menit.
        </p>

        <?php if ($pesan_error): ?>
            <div class="message error"><?= $pesan_error ?></div>
        <?php endif; ?>

        <?php if ($pesan_sukses): ?>
            <div class="message success"><?= $pesan_sukses ?></div>
        <?php endif; ?>

        <form action="proses_verifikasi_otp.php" method="POST">
            <input
                class="otp-input"
                type="text"
                name="otp"
                inputmode="numeric"
                autocomplete="one-time-code"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="000000"
                required
                autofocus>

            <button class="btn btn-primary" type="submit">
                Verifikasi OTP
            </button>
        </form>

        <form action="kirim_ulang_otp.php" method="POST">
            <button class="btn btn-secondary" type="submit">
                Kirim Ulang OTP
            </button>
        </form>

        <p class="small">
            Tidak menggunakan email ini?
            <a href="index.php">Kembali ke login</a>
        </p>
    </div>

    <script>
        const url = new URL(window.location.href);

        if (
            url.searchParams.has('sukses') ||
            url.searchParams.has('error')
        ) {
            url.searchParams.delete('sukses');
            url.searchParams.delete('error');

            window.history.replaceState(
                {},
                document.title,
                url.pathname + url.search + url.hash
            );
        }
    </script>
</body>

</html>
