<?php
session_start();
require_once __DIR__ . '/koneksi.php';

// ---------- PENGATURAN ----------
const KOLOM_PASSWORD = 'password_hash'; // nama kolom password di tabel pelanggan
const BATAS_GAGAL    = 5;          // maksimal percobaan verifikasi
const BLOKIR_MENIT   = 15;         // lama form dikunci
const BERLAKU_MENIT  = 10;         // waktu untuk membuat password baru

if (empty($_SESSION['csrf_lupa'])) {
    $_SESSION['csrf_lupa'] = bin2hex(random_bytes(32));
}

function h($teks)
{
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

// 0812..., +62812..., 62812... -> 0812...
function normalHP($hp)
{
    $digit = preg_replace('/\D/', '', (string)$hp);
    if (strpos($digit, '62') === 0) {
        $digit = '0' . substr($digit, 2);
    }
    return $digit;
}

$pesan_error    = '';
$username_input = '';
$sekarang       = time();

// Langkah 2 hanya aktif kalau verifikasi sudah lolos dan belum kedaluwarsa
$langkah = (!empty($_SESSION['lp_id']) && !empty($_SESSION['lp_sampai'])
    && $_SESSION['lp_sampai'] > $sekarang) ? 2 : 1;

if ($langkah === 1) {
    unset($_SESSION['lp_id'], $_SESSION['lp_sampai']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_lupa'], $token)) {

        $pesan_error = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {

        $aksi = $_POST['aksi'] ?? '';

        try {

            // ===== LANGKAH 1: VERIFIKASI IDENTITAS =====
            if ($aksi === 'verifikasi' && $langkah === 1) {

                $blokirSampai = $_SESSION['lp_blokir_sampai'] ?? 0;

                if ($blokirSampai > $sekarang) {

                    $sisa = (int) ceil(($blokirSampai - $sekarang) / 60);
                    $pesan_error = "Terlalu banyak percobaan. Coba lagi dalam {$sisa} menit.";
                } else {

                    $username_input = trim($_POST['username'] ?? '');
                    $email          = strtolower(trim($_POST['email'] ?? ''));
                    $hp             = normalHP($_POST['no_hp'] ?? '');

                    $cocok = false;
                    $idPelanggan = 0;

                    if ($username_input !== '' && $email !== '' && $hp !== '') {

                        $stmt = $koneksi->prepare(
                            'SELECT id_pelanggan, email, no_HP
                             FROM pelanggan
                             WHERE username = ?
                             LIMIT 1'
                        );
                        $stmt->bind_param('s', $username_input);
                        $stmt->execute();
                        $row = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if (
                            $row &&
                            hash_equals(strtolower((string)$row['email']), $email) &&
                            hash_equals(normalHP($row['no_HP']), $hp)
                        ) {
                            $cocok = true;
                            $idPelanggan = (int)$row['id_pelanggan'];
                        }
                    }

                    if ($cocok) {

                        session_regenerate_id(true);
                        $_SESSION['lp_id']     = $idPelanggan;
                        $_SESSION['lp_sampai'] = $sekarang + (BERLAKU_MENIT * 60);
                        $_SESSION['lp_gagal']  = 0;
                        $_SESSION['csrf_lupa'] = bin2hex(random_bytes(32));

                        header('Location: lupa_password.php');
                        exit();
                    }

                    $_SESSION['lp_gagal'] = ($_SESSION['lp_gagal'] ?? 0) + 1;

                    if ($_SESSION['lp_gagal'] >= BATAS_GAGAL) {
                        $_SESSION['lp_blokir_sampai'] = $sekarang + (BLOKIR_MENIT * 60);
                        $_SESSION['lp_gagal'] = 0;
                    }

                    $pesan_error = 'Data yang dimasukkan tidak cocok dengan akun mana pun.';
                }
            }

            // ===== LANGKAH 2: BUAT PASSWORD BARU =====
            elseif ($aksi === 'reset' && $langkah === 2) {

                $baru = (string)($_POST['password_baru'] ?? '');
                $konf = (string)($_POST['konfirmasi_password'] ?? '');

                if (strlen($baru) < 6) {

                    $pesan_error = 'Password baru minimal 6 karakter.';
                } elseif (!hash_equals($baru, $konf)) {

                    $pesan_error = 'Konfirmasi password tidak sama.';
                } else {

                    $hash = password_hash($baru, PASSWORD_DEFAULT);
                    $id   = (int)$_SESSION['lp_id'];

                    $stmt = $koneksi->prepare(
                        'UPDATE pelanggan SET ' . KOLOM_PASSWORD . ' = ? WHERE id_pelanggan = ?'
                    );
                    $stmt->bind_param('si', $hash, $id);
                    $stmt->execute();
                    $stmt->close();

                    unset($_SESSION['lp_id'], $_SESSION['lp_sampai'], $_SESSION['lp_gagal']);
                    $_SESSION['csrf_lupa'] = bin2hex(random_bytes(32));

                    header(
                        'Location: index.php?sukses='
                            . urlencode('Password berhasil diubah. Silakan login dengan password baru.')
                    );
                    exit();
                }
            }
        } catch (Throwable $e) {

            error_log('Lupa password gagal: ' . $e->getMessage());
            $pesan_error = 'Terjadi kesalahan. Coba lagi beberapa saat.';
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
                    <li><i class="fa-solid fa-id-card"></i> Verifikasi data akun</li>
                    <li><i class="fa-solid fa-key"></i> Buat password baru</li>
                    <li><i class="fa-solid fa-right-to-bracket"></i> Langsung masuk kembali</li>
                </ul>
            </div>
        </div>

        <!-- Kanan: form -->
        <div class="auth-right">
            <div class="form-box">

                <?php if ($langkah === 1): ?>

                    <div class="form-header">
                        <h2>Lupa Password?</h2>
                        <p class="subtitle">Masukkan data akun Anda untuk memverifikasi identitas.</p>
                    </div>

                    <?php if ($pesan_error): ?>
                        <div class="form-alert" role="alert"><?= h($pesan_error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="lupa_password.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_lupa']) ?>">
                        <input type="hidden" name="aksi" value="verifikasi">

                        <label class="input-label" for="lp-user">Username</label>
                        <div class="input-group">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="username" id="lp-user" placeholder="Username akun Anda" value="<?= h($username_input) ?>" required>
                        </div>

                        <label class="input-label" for="lp-email">Email terdaftar</label>
                        <div class="input-group">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" id="lp-email" placeholder="Email saat mendaftar" required>
                        </div>

                        <label class="input-label" for="lp-hp">Nomor HP terdaftar</label>
                        <div class="input-group">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="tel" name="no_hp" id="lp-hp" placeholder="Contoh: 081234567890" inputmode="numeric" required>
                        </div>

                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-shield-halved"></i> Verifikasi
                        </button>
                    </form>

                <?php else: ?>

                    <div class="form-header">
                        <h2>Buat Password Baru</h2>
                        <p class="subtitle">Identitas terverifikasi. Silakan atur password baru Anda.</p>
                    </div>

                    <div class="form-info">
                        <i class="fa-solid fa-circle-info"></i>
                        Anda punya <?= (int)BERLAKU_MENIT ?> menit untuk menyelesaikan langkah ini.
                    </div>

                    <?php if ($pesan_error): ?>
                        <div class="form-alert" role="alert"><?= h($pesan_error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="lupa_password.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_lupa']) ?>">
                        <input type="hidden" name="aksi" value="reset">

                        <label class="input-label" for="lp-baru">Password Baru</label>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password_baru" id="lp-baru" placeholder="Minimal 6 karakter" minlength="6" required autocomplete="new-password">
                            <button type="button" class="toggle-pass" aria-label="Tampilkan atau sembunyikan password" onclick="togglePassword('lp-baru', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>

                        <label class="input-label" for="lp-konf">Konfirmasi Password Baru</label>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="konfirmasi_password" id="lp-konf" placeholder="Ulangi password baru" minlength="6" required autocomplete="new-password">
                            <button type="button" class="toggle-pass" aria-label="Tampilkan atau sembunyikan password" onclick="togglePassword('lp-konf', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>

                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-key"></i> Simpan Password Baru
                        </button>
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