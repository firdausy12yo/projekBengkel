<?php
session_start();
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/email_helper.php';
date_default_timezone_set('Asia/Jakarta');

const RP_OTP_DETIK = 300;    // OTP berlaku 5 menit
const RP_RESET_DETIK = 600;  // Setelah OTP benar, 10 menit untuk membuat password
const RP_JEDA_KIRIM = 60;   // Jeda pengiriman OTP per akun dan per sesi

if (empty($_SESSION['csrf_lupa'])) {
    $_SESSION['csrf_lupa'] = bin2hex(random_bytes(32));
}

function h($teks): string {
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

// Hasil 0 juga digunakan jika email tidak ditemukan, agar tidak membocorkan akun.
function kirimKodeReset(mysqli $db, string $email): int {
    $cari = $db->prepare(
        'SELECT id_pelanggan, nama_lengkap FROM pelanggan
         WHERE email = ? AND email_verified = 1 LIMIT 1'
    );
    $cari->bind_param('s', $email);
    $cari->execute();
    $akun = $cari->get_result()->fetch_assoc();
    $cari->close();

    if (!$akun) return 0;

    $id = (int) $akun['id_pelanggan'];
    $otp = (string) random_int(100000, 999999);
    $hash = password_hash($otp, PASSWORD_DEFAULT);

    // UPDATE atomik: 1 akun hanya boleh mendapatkan 1 kode baru tiap 60 detik.
    $simpan = $db->prepare(
        'UPDATE pelanggan
         SET reset_otp_hash = ?,
             reset_otp_expired_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
             reset_otp_sent_at = NOW(),
             reset_otp_attempts = 0
         WHERE id_pelanggan = ?
           AND (reset_otp_sent_at IS NULL
                OR reset_otp_sent_at <= DATE_SUB(NOW(), INTERVAL 60 SECOND))'
    );
    $simpan->bind_param('si', $hash, $id);
    $simpan->execute();
    $bolehKirim = $simpan->affected_rows === 1;
    $simpan->close();
    if (!$bolehKirim) return 0;

    [$terkirim, $pesan] = kirimOtpEmail(
        $email, $akun['nama_lengkap'], $otp, 'reset_password'
    );

    if (!$terkirim) {
        error_log('OTP reset tidak terkirim: ' . $pesan);
        // Hapus kode yang gagal dikirim (tanpa menghapus kode yang lebih baru).
        $hapus = $db->prepare(
            'UPDATE pelanggan
             SET reset_otp_hash = NULL, reset_otp_expired_at = NULL,
                 reset_otp_sent_at = NULL, reset_otp_attempts = 0
             WHERE id_pelanggan = ? AND reset_otp_hash = ?'
        );
        $hapus->bind_param('is', $id, $hash);
        $hapus->execute();
        $hapus->close();
        return 0;
    }

    return $id;
}

$sekarang = time();
$pesan_error = '';
$pesan_info = $_SESSION['rp_info'] ?? '';
unset($_SESSION['rp_info']);

// Tahap tidak ditentukan oleh input browser; hanya oleh sesi di server.
$langkah = 1;
if (!empty($_SESSION['rp_ok_id']) &&
    (int) ($_SESSION['rp_ok_until'] ?? 0) > $sekarang) {
    $langkah = 3;
} elseif (isset($_SESSION['rp_email']) &&
    (int) ($_SESSION['rp_until'] ?? 0) > $sekarang) {
    $langkah = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_lupa'], $token)) {
        $pesan_error = 'Form kedaluwarsa. Muat ulang halaman dan coba lagi.';
    } else {
        $aksi = $_POST['aksi'] ?? '';
        try {
            // Tahap 1 atau pengiriman ulang: kirim OTP ke email terdaftar.
            if (($aksi === 'kirim' && $langkah === 1) ||
                ($aksi === 'ulang' && $langkah === 2)) {
                $email = $aksi === 'ulang'
                    ? (string) $_SESSION['rp_email']
                    : strtolower(trim((string) ($_POST['email'] ?? '')));

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $pesan_error = 'Masukkan alamat email yang valid.';
                } elseif ((int) ($_SESSION['rp_next_send'] ?? 0) > time()) {
                    $pesan_error = 'Tunggu 60 detik sebelum meminta kode baru.';
                } else {
                    $_SESSION['rp_next_send'] = time() + RP_JEDA_KIRIM;
                    $_SESSION['rp_email'] = $email;
                    $_SESSION['rp_id'] = kirimKodeReset($koneksi, $email);
                    $_SESSION['rp_until'] = time() + RP_OTP_DETIK;
                    unset($_SESSION['rp_ok_id'], $_SESSION['rp_ok_until']);
                    $_SESSION['rp_info'] = 'Jika email terdaftar dan sudah diverifikasi, '
                        . 'kode OTP akan dikirim. Periksa kotak masuk dan spam.';
                    header('Location: lupa_password.php');
                    exit();
                }
            }

            // Kembali ke form email tanpa menghapus batas pengiriman.
            elseif ($aksi === 'ganti_email' && $langkah === 2) {
                unset($_SESSION['rp_email'], $_SESSION['rp_id'], $_SESSION['rp_until']);
                header('Location: lupa_password.php');
                exit();
            }

            // Tahap 2: kode benar + belum kedaluwarsa + kurang dari 5 kesalahan.
            elseif ($aksi === 'cek_otp' && $langkah === 2) {
                $otp = (string) ($_POST['otp'] ?? '');
                $id = (int) ($_SESSION['rp_id'] ?? 0);
                $valid = false;

                if (preg_match('/^[0-9]{6}$/', $otp) && $id > 0) {
                    $cek = $koneksi->prepare(
                        'SELECT reset_otp_hash, reset_otp_attempts,
                                (reset_otp_expired_at > NOW()) AS belum_expired
                         FROM pelanggan WHERE id_pelanggan = ? LIMIT 1'
                    );
                    $cek->bind_param('i', $id);
                    $cek->execute();
                    $row = $cek->get_result()->fetch_assoc();
                    $cek->close();

                    if ($row && $row['reset_otp_hash'] &&
                        (int) $row['reset_otp_attempts'] < 5 &&
                        (int) $row['belum_expired'] === 1) {
                        $hash = $row['reset_otp_hash'];
                        if (password_verify($otp, $hash)) {
                            // Habiskan OTP satu kali saja, termasuk ketika ada permintaan bersamaan.
                            $habiskan = $koneksi->prepare(
                                'UPDATE pelanggan
                                 SET reset_otp_hash = NULL, reset_otp_expired_at = NULL
                                 WHERE id_pelanggan = ? AND reset_otp_hash = ?
                                   AND reset_otp_expired_at > NOW()
                                   AND reset_otp_attempts < 5'
                            );
                            $habiskan->bind_param('is', $id, $hash);
                            $habiskan->execute();
                            $valid = $habiskan->affected_rows === 1;
                            $habiskan->close();
                        } else {
                            $gagal = $koneksi->prepare(
                                'UPDATE pelanggan
                                 SET reset_otp_attempts = reset_otp_attempts + 1
                                 WHERE id_pelanggan = ? AND reset_otp_hash = ?
                                   AND reset_otp_attempts < 5'
                            );
                            $gagal->bind_param('is', $id, $hash);
                            $gagal->execute();
                            $gagal->close();
                        }
                    }
                }

                if ($valid) {
                    session_regenerate_id(true);
                    $_SESSION['rp_ok_id'] = $id;
                    $_SESSION['rp_ok_until'] = time() + RP_RESET_DETIK;
                    unset($_SESSION['rp_email'], $_SESSION['rp_id'], $_SESSION['rp_until']);
                    header('Location: lupa_password.php');
                    exit();
                }
                $pesan_error = 'OTP salah, sudah kedaluwarsa, atau batas percobaan tercapai.';
            }

            // Tahap 3: hanya boleh setelah verifikasi OTP berhasil.
            elseif ($aksi === 'reset' && $langkah === 3) {
                $baru = (string) ($_POST['password_baru'] ?? '');
                $ulang = (string) ($_POST['konfirmasi_password'] ?? '');

                if (strlen($baru) < 8 || strlen($baru) > 72) {
                    $pesan_error = 'Password harus berisi 8–72 karakter/byte.';
                } elseif (!hash_equals($baru, $ulang)) {
                    $pesan_error = 'Konfirmasi password tidak sama.';
                } else {
                    $id = (int) $_SESSION['rp_ok_id'];
                    $hash = password_hash($baru, PASSWORD_DEFAULT);
                    $ubah = $koneksi->prepare(
                        'UPDATE pelanggan SET password_hash = ?,
                         reset_otp_hash = NULL, reset_otp_expired_at = NULL,
                         reset_otp_sent_at = NULL, reset_otp_attempts = 0
                         WHERE id_pelanggan = ?'
                    );
                    $ubah->bind_param('si', $hash, $id);
                    $ubah->execute();
                    $berhasil = $ubah->affected_rows === 1;
                    $ubah->close();
                    if (!$berhasil) throw new RuntimeException('Akun gagal diperbarui');

                    unset($_SESSION['rp_ok_id'], $_SESSION['rp_ok_until'],
                        $_SESSION['rp_next_send'], $_SESSION['rp_email'],
                        $_SESSION['rp_id'], $_SESSION['rp_until']);
                    session_regenerate_id(true);
                    $_SESSION['csrf_lupa'] = bin2hex(random_bytes(32));
                    header('Location: index.php?sukses=' .
                        urlencode('Password berhasil diubah. Silakan login kembali.'));
                    exit();
                }
            }
        } catch (Throwable $e) {
            error_log('Reset password: ' . $e->getMessage());
            $pesan_error = 'Terjadi kesalahan. Periksa MySQL/SMTP, lalu coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - PitStop.id</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <style>
        .form-alert {
            background: #ffe6e6;
            color: #721c24;
            border-left: 4px solid var(--danger);
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .form-info {
            background: var(--primary-light);
            color: var(--dark-blue);
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <div class="auth-container">

        <!-- Kiri: branding -->
        <div class="auth-left">
            <div class="auth-left-overlay"></div>
            <div class="auth-left-content">
                <div class="brand">
                    <span class="brand-logo-wrap">
                        <img class="brand-logo" src="foto/logo%20bengkel.jpeg" alt="Logo Bengkel">
                    </span>
                    <h1>PitStop.id</h1>
                </div>
                <p class="tagline">Pulihkan akun Anda dengan aman</p>
                <ul class="auth-benefits">
                    <li><i class="fa-solid fa-id-card"></i> Kirim kode OTP ke email</li>
                    <li><i class="fa-solid fa-key"></i> Buat password baru</li>
                    <li><i class="fa-solid fa-right-to-bracket"></i> Login dengan password baru</li>
                </ul>
            </div>
        </div>

        <!-- Kanan: form -->
        <div class="auth-right">
            <div class="form-box">

                <?php if ($langkah === 1): ?>
                    <div class="form-header">
                        <h2>Lupa Password?</h2>
                        <p class="subtitle">Masukkan email yang sudah diverifikasi untuk menerima OTP.</p>
                    </div>
                <?php elseif ($langkah === 2): ?>
                    <div class="form-header">
                        <h2>Verifikasi OTP</h2>
                        <p class="subtitle">Masukkan kode 6 digit yang dikirim ke email kamu.</p>
                    </div>
                <?php else: ?>
                    <div class="form-header">
                        <h2>Buat Password Baru</h2>
                        <p class="subtitle">OTP berhasil diverifikasi. Buat password baru.</p>
                    </div>
                <?php endif; ?>

                <?php if ($pesan_error): ?>
                    <div class="form-alert" role="alert"><?= h($pesan_error) ?></div>
                <?php endif; ?>
                <?php if ($pesan_info): ?>
                    <div class="form-info"><?= h($pesan_info) ?></div>
                <?php endif; ?>

                <?php if ($langkah === 1): ?>
                    <form method="POST" action="lupa_password.php">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_lupa']) ?>">
                        <input type="hidden" name="aksi" value="kirim">
                        <label class="input-label" for="rp-email">Email terdaftar</label>
                        <div class="input-group">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" id="rp-email" name="email"
                                placeholder="Masukkan email akun" autocomplete="email" required>
                        </div>
                        <button class="btn-primary" type="submit">
                            <i class="fa-solid fa-paper-plane"></i> Kirim Kode OTP
                        </button>
                    </form>
                <?php elseif ($langkah === 2): ?>
                    <div class="form-info">Kode hanya berlaku 5 menit. Maksimal 5 percobaan.</div>
                    <form method="POST" action="lupa_password.php">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_lupa']) ?>">
                        <input type="hidden" name="aksi" value="cek_otp">
                        <label class="input-label" for="rp-otp">Kode OTP</label>
                        <div class="input-group">
                            <i class="fa-solid fa-shield-halved input-icon"></i>
                            <input type="text" id="rp-otp" name="otp" inputmode="numeric"
                                pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                                placeholder="6 digit OTP" required>
                        </div>
                        <button class="btn-primary" type="submit">Verifikasi OTP</button>
                    </form>
                    <form method="POST" action="lupa_password.php" style="margin-top:12px">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_lupa']) ?>">
                        <input type="hidden" name="aksi" value="ulang">
                        <button type="submit" class="btn-primary" style="background:#e6ecff;color:#1a22b3">
                            Kirim Ulang OTP
                        </button>
                    </form>
                    <form method="POST" action="lupa_password.php" style="margin-top:12px">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_lupa']) ?>">
                        <input type="hidden" name="aksi" value="ganti_email">
                        <button type="submit" class="btn-primary" style="background:#f3f4f6;color:#374151">Gunakan Email Lain</button>
                    </form>
                <?php else: ?>
                    <div class="form-info">Kamu punya 10 menit untuk membuat password baru.</div>
                    <form method="POST" action="lupa_password.php">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_lupa']) ?>">
                        <input type="hidden" name="aksi" value="reset">
                        <label class="input-label" for="lp-baru">Password Baru</label>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="lp-baru" name="password_baru"
                                placeholder="Minimal 8 karakter" minlength="8" maxlength="72"
                                autocomplete="new-password" required>
                            <button type="button" class="toggle-pass" onclick="togglePassword('lp-baru', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <label class="input-label" for="lp-konf">Konfirmasi Password</label>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="lp-konf" name="konfirmasi_password"
                                placeholder="Ulangi password" minlength="8" maxlength="72"
                                autocomplete="new-password" required>
                            <button type="button" class="toggle-pass" onclick="togglePassword('lp-konf', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <button class="btn-primary" type="submit">Simpan Password Baru</button>
                    </form>
                <?php endif; ?>

                <p class="switch-text"><a href="index.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke halaman login</a></p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>