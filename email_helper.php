<?php

use PHPMailer\PHPMailer\PHPMailer;

function kirimOtpEmail(string $email, string $nama, string $otp, string $tujuan = 'verifikasi'): array
{
    $autoload = __DIR__ . '/vendor/autoload.php';

    if (!file_exists($autoload)) {
        return [
            false,
            'PHPMailer belum terpasang. Jalankan composer install di folder project.'
        ];
    }

    require_once $autoload;

    $config = require __DIR__ . '/config_email.php';

    if (
        empty($config['username']) ||
        empty($config['password']) ||
        str_contains($config['username'], 'GANTI_DENGAN') ||
        str_contains($config['password'], 'GANTI_DENGAN')
    ) {
        return [
            false,
            'Konfigurasi email belum diisi di config_email.php.'
        ];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $config['port'];
        $mail->CharSet = 'UTF-8';

        $fromEmail = $config['from_email'] ?: $config['username'];
        $mail->setFrom($fromEmail, $config['from_name']);
        $mail->addAddress($email, $nama);

        $mail->isHTML(true);
        $resetPassword = $tujuan === 'reset_password';
        $judul = $resetPassword ? 'Reset Password' : 'Verifikasi Email';
        $kalimat = $resetPassword
            ? 'Gunakan kode berikut untuk mengizinkan penggantian password akun PitStop.id kamu.'
            : 'Gunakan kode berikut untuk memverifikasi email akun PitStop.id kamu.';
        $mail->Subject = 'Kode OTP ' . $judul . ' PitStop.id';

        $namaAman = htmlspecialchars($nama, ENT_QUOTES, 'UTF-8');
        $otpAman = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:24px'>
                <h2 style='margin-bottom:8px'>{$judul} PitStop.id</h2>
                <p>Halo, {$namaAman}.</p>
                <p>{$kalimat}</p>
                <div style='font-size:32px;font-weight:700;letter-spacing:8px;
                            text-align:center;padding:18px;background:#f4f4f4;
                            border-radius:10px;margin:20px 0'>
                    {$otpAman}
                </div>
                <p>Kode ini berlaku selama <strong>5 menit</strong>.</p>
                <p>Jangan berikan kode OTP ini kepada siapa pun.</p>
                <p style='color:#777;font-size:13px;margin-top:24px'>
                    Jika kamu tidak meminta kode ini, abaikan email ini.
                </p>
            </div>
        ";

        $mail->AltBody =
            "Kode OTP {$judul} PitStop.id: {$otp}. Kode berlaku selama 5 menit.";

        $mail->send();

        return [true, ''];
    } catch (Throwable $e) {
        error_log('Gagal mengirim OTP PitStop.id: ' . $e->getMessage());

        return [
            false,
            'OTP gagal dikirim. Periksa konfigurasi SMTP lalu coba kirim ulang.'
        ];
    }
}
