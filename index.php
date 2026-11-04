<?php
session_start();

include 'koneksi.php';

// Versi cache otomatis berdasarkan waktu ubah file.
function asset_v($file)
{
    $path = __DIR__ . '/' . $file;
    return $file . '?v=' . (file_exists($path) ? filemtime($path) : time());
}

// Daftar bengkel & layanan: ubah di sini saja, form ikut berubah.
$daftar_bengkel = [
    'PitStop Mitra - Jakarta Selatan',
    'PitStop Mitra - Jakarta Pusat',
    'PitStop Mitra - Jakarta Barat',
    'PitStop Mitra - Jakarta Timur',
    'PitStop Mitra - Bandung',
    'PitStop Mitra - Surabaya',
];

$daftar_layanan = [
    ['Servis Ban & Roda',   'fa-circle-dot',  'Servis Ban & Roda'],
    ['Servis Mesin',        'fa-gear',        'Servis Mesin'],
    ['Servis AC',           'fa-snowflake',   'Servis AC'],
    ['Servis Kelistrikan',  'fa-bolt',        'Kelistrikan'],
    ['Rem & Suspensi',      'fa-car-burst',   'Rem & Suspensi'],
    ['Cuci & Poles',        'fa-soap',        'Cuci & Poles'],
];

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
) {
    header("Location: admin.php");
    exit();
}

// ==========================================
// AMBIL PESANAN USER DARI DATABASE
// ==========================================

$pesanan_user = [];

$jumlah_semua     = 0;
$jumlah_menunggu  = 0;
$jumlah_diterima  = 0;
$jumlah_diproses  = 0;
$jumlah_selesai   = 0;
$jumlah_ditolak   = 0;
$jumlah_dibatalkan = 0;

if (isset($_SESSION['id_pelanggan'])) {

    $id_pelanggan = (int) $_SESSION['id_pelanggan'];

    $stmtPesanan = $koneksi->prepare("
        SELECT
            b.id_booking,
            b.tanggal_booking,
            b.jam_booking,
            b.nama_bengkel,
            b.status_booking,
            b.catatan_keluhan,
            b.catatan_admin,
            b.dibuat_pada,

            k.merk,
            k.model,
            k.tahun_keluar,
            k.no_plat,

            (
                SELECT GROUP_CONCAT(
                    l.nama_layanan
                    SEPARATOR ', '
                )

                FROM detail_booking db

                JOIN layanan l
                    ON db.id_layanan = l.id_layanan

                WHERE db.id_booking = b.id_booking

            ) AS layanan

        FROM booking b

        JOIN kendaraan k
            ON b.id_kendaraan = k.id_kendaraan

        WHERE b.id_pelanggan = ?

        ORDER BY b.dibuat_pada DESC
    ");

    $stmtPesanan->bind_param(
        "i",
        $id_pelanggan
    );

    $stmtPesanan->execute();

    $hasilPesanan =
        $stmtPesanan->get_result();

    while (
        $pesanan = $hasilPesanan->fetch_assoc()
    ) {

        $pesanan_user[] = $pesanan;

        $jumlah_semua++;

        switch ($pesanan['status_booking']) {

            case 'Menunggu':
                $jumlah_menunggu++;
                break;

            case 'Diterima':
                $jumlah_diterima++;
                break;

            case 'Diproses':
                $jumlah_diproses++;
                break;

            case 'Selesai':
                $jumlah_selesai++;
                break;

            case 'Ditolak':
                $jumlah_ditolak++;
                break;

            case 'Dibatalkan':
                $jumlah_dibatalkan++;
                break;
        }
    }

    $stmtPesanan->close();
}

// Format tanggal Indonesia

function tanggalIndonesia($tanggal)
{
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $timestamp = strtotime($tanggal);

    return date('d', $timestamp)
        . ' '
        . $bulan[(int) date('n', $timestamp)]
        . ' '
        . date('Y', $timestamp);
}

// ==========================================
// AMBIL PROFIL USER DARI DATABASE
// ==========================================

$nama_sesi     = 'Pengguna';
$username_sesi = '';
$email_sesi    = '';
$no_hp_sesi    = '';

if (isset($_SESSION['id_pelanggan'])) {

    $id_pelanggan =
        (int) $_SESSION['id_pelanggan'];

    $stmtProfil = $koneksi->prepare(
        "SELECT
            nama_lengkap,
            username,
            email,
            no_HP
         FROM pelanggan
         WHERE id_pelanggan = ?
         LIMIT 1"
    );

    $stmtProfil->bind_param(
        "i",
        $id_pelanggan
    );

    $stmtProfil->execute();

    $hasilProfil =
        $stmtProfil->get_result();

    if ($hasilProfil->num_rows === 1) {

        $profil =
            $hasilProfil->fetch_assoc();

        $nama_sesi =
            htmlspecialchars(
                $profil['nama_lengkap']
            );

        $username_sesi =
            htmlspecialchars(
                $profil['username']
            );

        $email_sesi =
            htmlspecialchars(
                $profil['email']
            );

        $no_hp_sesi =
            htmlspecialchars(
                $profil['no_HP']
            );

        // Sinkronkan session
        $_SESSION['nama_lengkap'] =
            $profil['nama_lengkap'];

        $_SESSION['username'] =
            $profil['username'];
    }

    $stmtProfil->close();
}

// ==========================================
// DAFTAR KENDARAAN AKTIF MILIK USER
// ==========================================

$kendaraan_profil = [];
$jumlah_pesanan_profil = $jumlah_semua;
$jumlah_kendaraan_profil = 0;

if (isset($_SESSION['id_pelanggan'])) {
    $id_pelanggan = (int) $_SESSION['id_pelanggan'];
    if (!isset($_SESSION['csrf_kendaraan'])) {
        $_SESSION['csrf_kendaraan'] = bin2hex(random_bytes(32));
    }
    $stmtKendaraan = $koneksi->prepare(
        "SELECT id_kendaraan, merk, model, tahun_keluar, no_plat
         FROM kendaraan
         WHERE id_pelanggan = ? AND is_active = 1
         ORDER BY id_kendaraan DESC"
    );
    $stmtKendaraan->bind_param("i", $id_pelanggan);
    $stmtKendaraan->execute();
    $hasilKendaraanProfil = $stmtKendaraan->get_result();
    while ($item = $hasilKendaraanProfil->fetch_assoc()) {
        $kendaraan_profil[] = $item;
    }
    $stmtKendaraan->close();
    $jumlah_kendaraan_profil = count($kendaraan_profil);
}

// Riwayat booking/servis untuk setiap kendaraan, satu query per akun.
$riwayat_per_kendaraan = [];
if (isset($_SESSION['id_pelanggan'])) {
    $id_pelanggan = (int) $_SESSION['id_pelanggan'];
    $stmtRiwayat = $koneksi->prepare(
        "SELECT b.id_booking, b.id_kendaraan, b.tanggal_booking, b.jam_booking,
                b.status_booking, b.nama_bengkel,
                COALESCE((
                    SELECT GROUP_CONCAT(l.nama_layanan ORDER BY l.nama_layanan SEPARATOR ', ')
                    FROM detail_booking d
                    JOIN layanan l ON l.id_layanan = d.id_layanan
                    WHERE d.id_booking = b.id_booking
                ), 'Layanan servis') AS nama_layanan
         FROM booking b
         WHERE b.id_pelanggan = ?
         ORDER BY b.tanggal_booking DESC, b.jam_booking DESC, b.id_booking DESC"
    );
    $stmtRiwayat->bind_param('i', $id_pelanggan);
    $stmtRiwayat->execute();
    $hasilRiwayat = $stmtRiwayat->get_result();
    while ($r = $hasilRiwayat->fetch_assoc()) {
        $riwayat_per_kendaraan[(int)$r['id_kendaraan']][] = $r;
    }
    $stmtRiwayat->close();
}

// ==========================================
// PESAN REDIRECT
// ==========================================

$pesan_error =
    isset($_GET['error'])
    ? htmlspecialchars($_GET['error'])
    : '';

$pesan_sukses =
    isset($_GET['sukses'])
    ? htmlspecialchars($_GET['sukses'])
    : '';

// ==========================================
// JUMLAH NOTIFIKASI BELUM DIBACA
// ==========================================

$jumlahNotifBaru = 0;

if (isset($_SESSION['id_pelanggan'])) {

    if (!isset($_SESSION['csrf_notifikasi'])) {
        $_SESSION['csrf_notifikasi'] = bin2hex(random_bytes(32));
    }

    $idPelanggan = (int) $_SESSION['id_pelanggan'];

    $stmtNotif = $koneksi->prepare(
        "SELECT COUNT(*) AS total
         FROM notifikasi
         WHERE id_pelanggan = ?
         AND sudah_dibaca = 0
         AND dihapus = 0"
    );

    $stmtNotif->bind_param('i', $idPelanggan);
    $stmtNotif->execute();

    $hasilNotif = $stmtNotif->get_result()->fetch_assoc();

    $jumlahNotifBaru = (int) $hasilNotif['total'];

    $stmtNotif->close();
}

// ==========================================
// HALAMAN AWAL
// ==========================================

$halaman_awal =
    isset($_SESSION['id_pelanggan'])
    ? 'main-app'
    : 'auth-page';

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PitStop.id - Booking Service Mobil</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset_v('style.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('kendaraan_fitur.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('akun_layout.css') ?>">
    <link rel="stylesheet" href="<?= asset_v('notifikasi.css') ?>">
</head>

<body>

    <!-- ========================================== -->
    <!-- NOTIFIKASI TOAST                           -->
    <!-- ========================================== -->
    <?php if ($pesan_error): ?>
        <div class="toast toast-error" id="toast-msg">
            <i class="fa-solid fa-circle-xmark"></i>
            <span><?= $pesan_error ?></span>
            <button onclick="document.getElementById('toast-msg').remove()"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>
    <?php if ($pesan_sukses): ?>
        <div class="toast toast-success" id="toast-msg">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= $pesan_sukses ?></span>
            <button onclick="document.getElementById('toast-msg').remove()"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- 1. HALAMAN LOGIN & DAFTAR                  -->
    <!-- ========================================== -->
    <div id="auth-page" class="page <?= $halaman_awal === 'auth-page' ? 'active' : '' ?>">
        <div class="auth-container">
            <!-- Kiri: Branding -->
            <div class="auth-left">
                <div class="auth-left-overlay"></div>
                <div class="auth-left-content">
                    <div class="brand">
                        <span class="brand-logo-wrap">
                            <img class="brand-logo" src="foto/logo%20bengkel.jpeg" alt="Logo Bengkel">
                        </span>
                        <h1>PitStop.id</h1>
                    </div>
                    <p class="tagline">Booking Service, Jadi Lebih Mudah!</p>
                    <ul class="auth-benefits">
                        <li><i class="fa-solid fa-calendar-check"></i> Booking servis tanpa antre</li>
                        <li><i class="fa-solid fa-tags"></i> Harga transparan</li>
                        <li><i class="fa-solid fa-bell"></i> Pantau status pesanan</li>
                    </ul>
                </div>
            </div>

            <!-- Kanan: Form -->
            <div class="auth-right">
                <!-- Form Login -->
                <div id="login-form" class="form-box">
                    <div class="form-header">
                        <h2>Selamat Datang!</h2>
                        <p class="subtitle">Masuk ke akun PitStop.id Anda</p>
                    </div>
                    <form action="proses_login.php" method="POST">
                        <label class="input-label" for="login-user">Username</label>
                        <div class="input-group">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="username" id="login-user" placeholder="Masukkan username" required autocomplete="username">
                        </div>

                        <label class="input-label" for="login-pass">Password</label>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="login-pass" placeholder="Masukkan password" required autocomplete="current-password">
                            <button type="button" class="toggle-pass" aria-label="Tampilkan atau sembunyikan password" onclick="togglePassword('login-pass', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>

                        <a class="forgot-link" href="lupa_password.php">
                            Lupa password?
                        </a>
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-right-to-bracket"></i> Masuk
                        </button>
                    </form>
                    <p class="switch-text">Belum punya akun? <a href="#" onclick="toggleAuth('register')">Daftar Sekarang!</a></p>
                </div>

                <!-- Form Daftar -->
                <div id="register-form" class="form-box" style="display: none;">
                    <div class="form-header">
                        <h2>Buat Akun Baru</h2>
                        <p class="subtitle">Daftar dan nikmati kemudahan booking servis di PitStop.id</p>
                    </div>
                    <form action="proses_daftar.php" method="POST">
                        <label class="input-label" for="reg-nama">Nama Lengkap</label>
                        <div class="input-group">
                            <i class="fa-solid fa-id-card input-icon"></i>
                            <input type="text" name="nama_lengkap" id="reg-nama" placeholder="Nama sesuai identitas" required>
                        </div>

                        <label class="input-label" for="reg-alamat">Alamat Lengkap</label>
                        <div class="input-group">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" name="alamat" id="reg-alamat" placeholder="Jalan, kota" required>
                        </div>

                        <label class="input-label" for="reg-hp">Nomor HP</label>
                        <div class="input-group">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input type="tel" name="no_hp" id="reg-hp" placeholder="Contoh: 081234567890"
                                pattern="08[0-9]{8,11}" minlength="10" maxlength="13" inputmode="numeric" required>
                        </div>

                        <label class="input-label" for="reg-email">Email</label>
                        <div class="input-group">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" id="reg-email" placeholder="nama@email.com" required autocomplete="email">
                        </div>

                        <label class="input-label" for="reg-user">Username</label>
                        <div class="input-group">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="username" id="reg-user" placeholder="Pilih username" required autocomplete="username">
                        </div>

                        <label class="input-label" for="reg-pass">Password</label>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="reg-pass" placeholder="Minimal 6 karakter" required minlength="6" autocomplete="new-password">
                            <button type="button" class="toggle-pass" aria-label="Tampilkan atau sembunyikan password" onclick="togglePassword('reg-pass', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>

                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-user-plus"></i> Daftar Sekarang
                        </button>
                    </form>
                    <p class="switch-text">Sudah punya akun? <a href="#" onclick="toggleAuth('login')">Login Sekarang!</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. HALAMAN UTAMA (SETELAH LOGIN)           -->
    <!-- ========================================== -->
    <div id="main-app" class="page <?= $halaman_awal === 'main-app' ? 'active' : '' ?>">
        <!-- Navbar -->
        <nav class="navbar" id="navbar">
            <div class="nav-brand">
                <i class="fa-solid fa-car-side"></i> PitStop<span>.id</span>
            </div>
            <ul class="nav-links" id="nav-links">
                <li>
                    <a href="#" onclick="showPage('home')" id="nav-home" class="active">
                        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 11.5 12 4l9 7.5" />
                            <path d="M5 10v10h14V10" />
                            <path d="M9 20v-6h6v6" />
                        </svg>
                        <span>Beranda</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showPage('booking')" id="nav-booking">
                        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="16" rx="2" />
                            <path d="M16 3v4M8 3v4M3 10h18" />
                            <path d="M12 13v5M9.5 15.5h5" />
                        </svg>
                        <span>Booking</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showPage('notif')" id="nav-notif">
                        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                            <path d="M10 21h4" />
                        </svg>
                        <span>Notifikasi</span>
                        <?php if ($jumlahNotifBaru > 0): ?>
                            <span class="notif-dot">
                                <?= $jumlahNotifBaru ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showPage('order')" id="nav-order">
                        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m3 6 2 2 4-4" />
                            <path d="M11 6h10" />
                            <path d="m3 12 2 2 4-4" />
                            <path d="M11 12h10" />
                            <path d="m3 18 2 2 4-4" />
                            <path d="M11 18h10" />
                        </svg>
                        <span>Pesanan</span>
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showPage('account')" id="nav-account">
                        <svg class="svg-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <circle cx="12" cy="9" r="3" />
                            <path d="M6.5 19c1-3 3-4.5 5.5-4.5s4.5 1.5 5.5 4.5" />
                        </svg>
                        <span>Akun</span>
                    </a>
                </li>
            </ul>
            <div class="nav-right">
                <span class="nav-user"><i class="fa-solid fa-circle-user"></i> <?= $username_sesi ?></span>
                <a href="logout.php" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
                <button class="hamburger" type="button" aria-label="Buka menu navigasi" aria-controls="nav-links" aria-expanded="false" onclick="toggleMobileMenu()"><i class="fa-solid fa-bars"></i></button>
            </div>
        </nav>

        <!-- A. KONTEN BERANDA -->
        <div id="content-home" class="content-section active">
            <header class="hero">
                <div class="hero-text">
                    <span class="hero-badge"><i class="fa-solid fa-bolt"></i> Booking Instan</span>
                    <h1>Servis Mobil Lebih Mudah, Kapan Saja.</h1>
                    <h2>Booking layanan bengkel terpercaya dengan cepat dan tanpa antre.</h2>
                    <div class="hero-actions">
                        <button class="btn-hero-primary" onclick="showPage('booking')">
                            <i class="fa-solid fa-calendar-plus"></i> Booking Sekarang
                        </button>
                        <button class="btn-hero-secondary" onclick="showPage('order')">
                            <i class="fa-solid fa-list-check"></i> Lihat Pesanan
                        </button>
                    </div>
                </div>
            </header>

            <div class="features-bar">
                <div class="feature"><i class="fa-solid fa-shield-halved"></i> Terjamin & Terpercaya</div>
                <div class="feature"><i class="fa-solid fa-tags"></i> Harga Transparan</div>
                <div class="feature"><i class="fa-solid fa-star"></i> Teknisi Berpengalaman</div>
                <div class="feature"><i class="fa-solid fa-clock"></i> Layanan 7 Hari Seminggu</div>
            </div>

            <section class="services">
                <h2 class="section-title"><i class="fa-solid fa-wrench"></i> Layanan Unggulan</h2>
                <div class="grid-cards">
                    <div class="card" onclick="bookService('Servis Ban & Roda')">
                        <div class="card-img-wrapper">
                            <img src="https://images.unsplash.com/photo-1578844251758-2f71da64c96f?q=80&w=400&auto=format&fit=crop" alt="Servis Ban">
                            <span class="card-badge">Populer</span>
                        </div>
                        <div class="card-body">
                            <h3><i class="fa-solid fa-circle-dot"></i> Servis Ban & Roda</h3>
                            <p>Perawatan ban, rotasi, balancing, spooring, dan penggantian ban.</p>
                            <div class="card-price">Mulai <strong>Rp 75.000</strong></div>
                            <button class="btn-card">Booking <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>
                    <div class="card" onclick="bookService('Servis Mesin')">
                        <div class="card-img-wrapper">
                            <img src="https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?q=80&w=400&auto=format&fit=crop" alt="Servis Mesin">
                        </div>
                        <div class="card-body">
                            <h3><i class="fa-solid fa-gear"></i> Servis Mesin</h3>
                            <p>Perawatan mesin berkala, tune up, ganti oli, dan perbaikan lengkap.</p>
                            <div class="card-price">Mulai <strong>Rp 150.000</strong></div>
                            <button class="btn-card">Booking <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>
                    <div class="card" onclick="bookService('Servis AC')">
                        <div class="card-img-wrapper">
                            <img src="https://images.unsplash.com/photo-1625047509168-a7026f36de04?q=80&w=400&auto=format&fit=crop" alt="Servis AC">
                            <span class="card-badge card-badge-new">Baru</span>
                        </div>
                        <div class="card-body">
                            <h3><i class="fa-solid fa-snowflake"></i> Servis AC</h3>
                            <p>Perawatan AC mobil agar tetap dingin, isi freon, dan servis kompresor.</p>
                            <div class="card-price">Mulai <strong>Rp 120.000</strong></div>
                            <button class="btn-card">Booking <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>
                    <div class="card" onclick="bookService('Servis Kelistrikan')">
                        <div class="card-img-wrapper">
                            <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?q=80&w=400&auto=format&fit=crop" alt="Servis Kelistrikan">
                        </div>
                        <div class="card-body">
                            <h3><i class="fa-solid fa-bolt"></i> Servis Kelistrikan</h3>
                            <p>Perbaikan sistem kelistrikan, aki, lampu, dan sensor kendaraan.</p>
                            <div class="card-price">Mulai <strong>Rp 100.000</strong></div>
                            <button class="btn-card">Booking <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>
                    <div class="card" onclick="bookService('Rem & Suspensi')">
                        <div class="card-img-wrapper">
                            <img src="https://images.unsplash.com/photo-1486006920555-c77dcf18193c?q=80&w=400&auto=format&fit=crop" alt="Servis Rem">
                        </div>
                        <div class="card-body">
                            <h3><i class="fa-solid fa-car-burst"></i> Rem & Suspensi</h3>
                            <p>Penggantian kampas rem, cek suspensi, shockbreaker, dan tie rod.</p>
                            <div class="card-price">Mulai <strong>Rp 200.000</strong></div>
                            <button class="btn-card">Booking <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>
                    <div class="card" onclick="bookService('Cuci & Poles')">
                        <div class="card-img-wrapper">
                            <img src="https://images.unsplash.com/photo-1520340356584-f9917d1eea6f?q=80&w=400&auto=format&fit=crop" alt="Cuci Poles">
                        </div>
                        <div class="card-body">
                            <h3><i class="fa-solid fa-soap"></i> Cuci & Poles</h3>
                            <p>Cuci mobil, poles body, coating, dan detailing interior eksterior.</p>
                            <div class="card-price">Mulai <strong>Rp 50.000</strong></div>
                            <button class="btn-card">Booking <i class="fa-solid fa-arrow-right"></i></button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Testimonial -->
            <section class="testimonial-section">
                <h2 class="section-title"><i class="fa-solid fa-comment-dots"></i> Apa Kata Pelanggan Kami</h2>
                <p class="testimonial-note">Contoh ulasan untuk keperluan demo. Ganti dengan ulasan pelanggan asli saat sudah tersedia.</p>
                <div class="testimonial-grid">
                    <div class="testimonial-card">
                        <div class="testimonial-stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                        <p>"Booking mudah, teknisi datang tepat waktu. Servis mesin mobil saya selesai dalam 2 jam. Recommended banget!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">BW</div>
                            <div>
                                <strong>Budi Waluyo</strong>
                                <span>Jakarta Selatan</span>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-card">
                        <div class="testimonial-stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                        <p>"Harga transparan, tidak ada biaya tersembunyi. Teknisinya profesional dan sopan. Pasti balik lagi!"</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">SR</div>
                            <div>
                                <strong>Sari Rahayu</strong>
                                <span>Bandung</span>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-card">
                        <div class="testimonial-stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-regular fa-star"></i></div>
                        <p>"Aplikasinya sangat mudah digunakan. Notifikasi status pesanan real-time sangat membantu."</p>
                        <div class="testimonial-author">
                            <div class="author-avatar">AP</div>
                            <div>
                                <strong>Agus Pratama</strong>
                                <span>Surabaya</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="footer">
                <div class="footer-content">
                    <div class="footer-brand">
                        <div class="footer-logo"><i class="fa-solid fa-car-side"></i> PitStop.id</div>
                        <p>Platform booking servis mobil terpercaya di Indonesia. Mudah, cepat, dan transparan.</p>
                        <div class="footer-social">
                            <a href="https://wa.me/6288985118045" target="_blank" rel="noopener" aria-label="WhatsApp PitStop.id"><i class="fa-brands fa-whatsapp"></i></a>
                            <a href="https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=pitstopid.official@gmail.com" target="_blank" rel="noopener" aria-label="Kirim email ke PitStop.id"><i class="fa-solid fa-envelope"></i></a>
                        </div>
                    </div>
                    <div class="footer-links">
                        <h4>Layanan</h4>
                        <ul>
                            <li><a href="#" onclick="bookService('Servis Mesin'); return false;">Servis Mesin</a></li>
                            <li><a href="#" onclick="bookService('Servis Ban & Roda'); return false;">Servis Ban</a></li>
                            <li><a href="#" onclick="bookService('Servis AC'); return false;">Servis AC</a></li>
                            <li><a href="#" onclick="bookService('Rem & Suspensi'); return false;">Servis Rem</a></li>
                        </ul>
                    </div>
                    <div class="footer-links">
                        <h4>Bantuan</h4>
                        <ul>
                            <li><a href="https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=pitstopid.official@gmail.com" target="_blank" rel="noopener">Email Dukungan</a></li>
                            <li><a href="https://wa.me/6288985118045" target="_blank" rel="noopener">Chat WhatsApp</a></li>
                            <li><a href="#" onclick="showPage('order'); return false;">Pesanan Saya</a></li>
                        </ul>
                    </div>
                    <div class="footer-contact">
                        <h4>Kontak</h4>
                        <p><i class="fa-solid fa-phone"></i> 0889-8511-8045</p>
                        <p><i class="fa-solid fa-envelope"></i> <a href="https://mail.google.com/mail/?view=cm&amp;fs=1&amp;to=pitstopid.official@gmail.com" target="_blank" rel="noopener" class="footer-email">pitstopid.official@gmail.com</a></p>
                        <p><i class="fa-solid fa-location-dot"></i> Jakarta, Indonesia</p>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>&copy; <?= date('Y') ?> PitStop.id — All Rights Reserved.</p>
                </div>
            </footer>
        </div>

        <!-- B. KONTEN BOOKING -->
        <div id="content-booking" class="content-section">
            <div class="page-header">
                <i class="fa-solid fa-calendar-plus header-icon"></i>
                <div>
                    <h2>Booking Servis</h2>
                    <p>Isi form di bawah untuk menjadwalkan servis kendaraan Anda.</p>
                </div>
            </div>
            <div class="booking-container">
                <form
                    class="booking-form"
                    id="form-booking"
                    action="proses_booking.php"
                    method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_kendaraan'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="booking-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Data Kendaraan</h3>
                            <div class="form-field vehicle-select-field">
                                <label for="booking-pilih-kendaraan">Pilih Kendaraan</label>
                                <select name="kendaraan_id" id="booking-pilih-kendaraan" onchange="pilihKendaraanBooking()" required>
                                    <?php if (!empty($kendaraan_profil)): ?>
                                        <option value="" disabled selected>-- Pilih kendaraan tersimpan atau tambah baru --</option>
                                        <?php foreach ($kendaraan_profil as $itemKendaraan): ?>
                                            <option value="<?= (int)$itemKendaraan['id_kendaraan'] ?>"
                                                data-merk="<?= htmlspecialchars($itemKendaraan['merk'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-model="<?= htmlspecialchars($itemKendaraan['model'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-tahun="<?= htmlspecialchars(date('Y', strtotime($itemKendaraan['tahun_keluar'])), ENT_QUOTES, 'UTF-8') ?>"
                                                data-plat="<?= htmlspecialchars($itemKendaraan['no_plat'], ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($itemKendaraan['merk'] . ' ' . $itemKendaraan['model'] . ' — ' . $itemKendaraan['no_plat'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <option value="baru" <?= empty($kendaraan_profil) ? 'selected' : '' ?>>+ Tambah kendaraan baru</option>
                                </select>
                                <p class="vehicle-help">Gunakan kendaraan yang sudah terdaftar agar tidak perlu mengisi data berulang.</p>
                            </div>
                            <div id="booking-ringkasan-kendaraan" class="booking-vehicle-preview" hidden></div>
                            <div id="booking-data-baru" class="form-grid" <?= !empty($kendaraan_profil) ? 'hidden' : '' ?>>
                                <div class="form-field">
                                    <label>Merk Kendaraan</label>
                                    <select name="merk" required>
                                        <option value="" disabled selected>Pilih Merk</option>
                                        <option>Toyota</option>
                                        <option>Honda</option>
                                        <option>Suzuki</option>
                                        <option>Daihatsu</option>
                                        <option>Mitsubishi</option>
                                        <option>Nissan</option>
                                        <option>Mazda</option>
                                        <option>Ford</option>
                                        <option>Lainnya</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label>Model / Tipe</label>
                                    <input type="text" name="model" maxlength="100" placeholder="Contoh: Avanza 1.3 G" required>
                                </div>
                                <div class="form-field">
                                    <label>Tahun Kendaraan</label>
                                    <input type="number" name="tahun" placeholder="Contoh: 2019" min="1900" max="<?= (int)date('Y') + 1 ?>" required>
                                </div>
                                <div class="form-field">
                                    <label>Nomor Polisi</label>
                                    <input type="text" name="nopol" maxlength="20" placeholder="Contoh: AD 1234 ABC" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="booking-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Pilih Layanan</h3>
                            <p class="vehicle-help" style="margin-bottom:12px;">Pilih satu atau lebih layanan.</p>
                            <div class="service-options" id="service-options">
                                <?php foreach ($daftar_layanan as $lay): ?>
                                    <label class="service-option">
                                        <input type="checkbox" name="layanan[]" value="<?= htmlspecialchars($lay[0], ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="service-option-box"><i class="fa-solid <?= $lay[1] ?>"></i><span><?= htmlspecialchars($lay[2], ENT_QUOTES, 'UTF-8') ?></span></div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="form-error" id="layanan-error" role="alert" hidden>Pilih minimal satu layanan.</p>
                            <div class="form-field" style="margin-top:20px;">
                                <label>Keluhan / Keterangan Tambahan</label>
                                <textarea name="keluhan" rows="3" placeholder="Ceritakan keluhan kendaraan Anda..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="booking-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Jadwal & Lokasi</h3>
                            <div class="form-grid">
                                <div class="form-field">
                                    <label>Tanggal Servis</label>
                                    <input type="date" name="tanggal" required id="tgl-booking">
                                </div>
                                <div class="form-field">
                                    <label for="booking-jam">Jam Servis</label>
                                    <select name="jam" id="booking-jam" required>
                                        <option value="" disabled selected>Pilih Jam</option>
                                        <option>08:00</option>
                                        <option>09:00</option>
                                        <option>10:00</option>
                                        <option>11:00</option>
                                        <option>13:00</option>
                                        <option>14:00</option>
                                        <option>15:00</option>
                                        <option>16:00</option>
                                    </select>
                                </div>
                                <div class="form-field full-width">
                                    <label>Pilih Bengkel</label>
                                    <select name="bengkel" id="booking-bengkel" required>
                                        <option value="" disabled selected>Pilih Bengkel Terdekat</option>
                                        <?php foreach ($daftar_bengkel as $b): ?>
                                            <option><?= htmlspecialchars($b, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="booking-submit">
                        <button type="submit" class="btn-primary btn-booking">
                            <i class="fa-solid fa-paper-plane"></i> Kirim Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- C. KONTEN NOTIFIKASI -->
        <div id="content-notif" class="content-section">

            <?php include __DIR__ . '/notifikasi_view.php'; ?>

        </div>




        <!-- D. KONTEN PESANAN -->

        <div id="content-order" class="content-section">

            <div class="page-header">

                <i class="fa-solid fa-list-check header-icon"></i>

                <div>

                    <h2>Riwayat Pesanan</h2>

                    <p>
                        Pantau semua pesanan servis kendaraan Anda di sini.
                    </p>

                </div>

            </div>

            <!-- TAB STATUS -->

            <div class="tabs">

                <button
                    class="tab active"
                    onclick="switchOrderTab(this, 'semua')">

                    Semua

                    <span class="badge">
                        <?= $jumlah_semua ?>
                    </span>

                </button>

                <button
                    class="tab"
                    onclick="switchOrderTab(this, 'menunggu')">

                    Menunggu

                    <span class="badge">
                        <?= $jumlah_menunggu ?>
                    </span>

                </button>

                <button
                    class="tab"
                    onclick="switchOrderTab(this, 'diterima')">

                    Diterima

                    <span class="badge">
                        <?= $jumlah_diterima ?>
                    </span>

                </button>

                <button
                    class="tab"
                    onclick="switchOrderTab(this, 'diproses')">

                    Diproses

                    <span class="badge">
                        <?= $jumlah_diproses ?>
                    </span>

                </button>

                <button
                    class="tab"
                    onclick="switchOrderTab(this, 'selesai')">

                    Selesai

                    <span class="badge">
                        <?= $jumlah_selesai ?>
                    </span>

                </button>

                <button
                    class="tab"
                    onclick="switchOrderTab(this, 'ditolak')">

                    Ditolak

                    <span class="badge">
                        <?= $jumlah_ditolak ?>
                    </span>

                </button>

                <button
                    class="tab"
                    onclick="switchOrderTab(this, 'dibatalkan')">

                    Dibatalkan

                    <span class="badge">
                        <?= $jumlah_dibatalkan ?>
                    </span>

                </button>

            </div>

            <div
                class="list-container"
                id="order-list">

                <?php foreach ($pesanan_user as $pesanan): ?>

                    <?php

                    $status =
                        $pesanan['status_booking'];

                    // status untuk filter

                    $data_status = strtolower($status);

                    // CSS badge

                    switch ($status) {

                        case 'Menunggu':

                            $class_status =
                                'status-jadwal';

                            $icon =
                                'fa-clock';

                            break;

                        case 'Diterima':

                            $class_status =
                                'status-diterima';

                            $icon =
                                'fa-calendar-check';

                            break;

                        case 'Diproses':

                            $class_status =
                                'status-proses';

                            $icon =
                                'fa-gears';

                            break;

                        case 'Selesai':

                            $class_status =
                                'status-selesai';

                            $icon =
                                'fa-circle-check';

                            break;

                        default:

                            $class_status =
                                'status-batal';

                            $icon =
                                'fa-circle-xmark';

                            break;
                    }

                    $id_display =
                        'BK-' .
                        str_pad(
                            $pesanan['id_booking'],
                            5,
                            '0',
                            STR_PAD_LEFT
                        );

                    $tanggal_display =
                        tanggalIndonesia(
                            $pesanan['tanggal_booking']
                        );

                    $jam_display =
                        str_pad(
                            $pesanan['jam_booking'],
                            2,
                            '0',
                            STR_PAD_LEFT
                        ) . ':00';

                    $kendaraan_display =
                        $pesanan['merk']
                        . ' '
                        . $pesanan['model']
                        . ' '
                        . date(
                            'Y',
                            strtotime(
                                $pesanan['tahun_keluar']
                            )
                        );

                    ?>

                    <div
                        class="order-card"
                        data-status="<?= htmlspecialchars($data_status) ?>">

                        <div class="order-icon">

                            <i class="fa-solid <?= $icon ?>"></i>

                        </div>

                        <div class="order-info">

                            <span
                                class="status-badge <?= $class_status ?>">

                                <?= htmlspecialchars($status) ?>

                            </span>

                            <p>

                                <?= htmlspecialchars(
                                    $pesanan['layanan'] ?? 'Layanan Servis'
                                ) ?>

                            </p>

                            <small>

                                <i class="fa-solid fa-hashtag"></i>

                                <?= htmlspecialchars($id_display) ?>

                                &nbsp;•&nbsp;

                                <i class="fa-solid fa-calendar"></i>

                                <?= htmlspecialchars($tanggal_display) ?>,

                                <?= htmlspecialchars($jam_display) ?>

                            </small>

                        </div>

                        <div class="order-actions">

                            <?php if ($status === 'Menunggu'): ?>

                                <form
                                    action="proses_batal_booking.php"
                                    method="POST"
                                    class="cancel-form"
                                    onsubmit="return confirmBatalBooking();">

                                    <input
                                        type="hidden"
                                        name="id_booking"
                                        value="<?= (int)$pesanan['id_booking'] ?>">

                                    <button
                                        type="submit"
                                        class="btn-cancel-order">

                                        <i class="fa-solid fa-xmark"></i>
                                        Batalkan

                                    </button>

                                </form>

                            <?php endif; ?>

                            <button
                                class="btn-detail"
                                type="button"

                                data-id="<?= htmlspecialchars($id_display) ?>"

                                data-status="<?= htmlspecialchars($status) ?>"

                                data-service="<?= htmlspecialchars(
                                                    $pesanan['layanan'] ?? 'Layanan Servis'
                                                ) ?>"

                                data-vehicle="<?= htmlspecialchars(
                                                    $kendaraan_display
                                                ) ?>"

                                data-plate="<?= htmlspecialchars(
                                                $pesanan['no_plat']
                                            ) ?>"

                                data-workshop="<?= htmlspecialchars(
                                                    $pesanan['nama_bengkel'] ?? '-'
                                                ) ?>"

                                data-date="<?= htmlspecialchars(
                                                $tanggal_display
                                            ) ?>"

                                data-time="<?= htmlspecialchars(
                                                $jam_display
                                            ) ?>"

                                data-note="<?= htmlspecialchars(
                                                $pesanan['catatan_keluhan']
                                            ) ?>"

                                onclick="openOrderFromData(this)">

                                Detail

                                <i class="fa-solid fa-chevron-right"></i>

                            </button>

                        </div>

                    </div>

                <?php endforeach; ?>

                <div
                    id="order-empty"
                    class="order-empty"

                    style="<?= count($pesanan_user) === 0
                                ? 'display:flex;'
                                : 'display:none;' ?>">

                    <i class="fa-solid fa-box-open"></i>

                    <p>
                        Belum ada pesanan.
                    </p>
                </div>
            </div>
        </div>

        <!-- modal detail pesanan -->
        <div id="order-detail-modal" class="modal-overlay" style="display:none;" onclick="closeOrderDetail(event)">
            <div class="modal-box order-detail-modal" onclick="event.stopPropagation()">
                <div class="modal-header-row">
                    <div>
                        <span class="modal-eyebrow">DETAIL PESANAN</span>
                        <h3 id="detail-service">Detail Pesanan</h3>
                    </div>
                    <button class="modal-close" type="button" onclick="closeOrderDetail()" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="detail-status-row">
                    <span id="detail-status" class="status-badge status-proses">Diproses</span>
                    <strong id="detail-id">-</strong>
                </div>
                <div class="detail-grid">
                    <div class="detail-item"><span>Kendaraan</span><strong id="detail-vehicle">-</strong></div>
                    <div class="detail-item"><span>Nomor Polisi</span><strong id="detail-plate">-</strong></div>
                    <div class="detail-item"><span>Bengkel</span><strong id="detail-workshop">-</strong></div>
                    <div class="detail-item"><span>Tanggal</span><strong id="detail-date">-</strong></div>
                    <div class="detail-item"><span>Jam</span><strong id="detail-time">-</strong></div>
                </div>
                <div class="detail-note">
                    <span>Keterangan / Keluhan</span>
                    <p id="detail-note">-</p>
                </div>
                <button class="btn-primary" type="button" onclick="closeOrderDetail()">Tutup</button>
            </div>
        </div>

        <!-- E. KONTEN AKUN -->
        <div id="content-account" class="content-section">
            <div class="page-header">
                <i class="fa-solid fa-user-circle header-icon"></i>
                <div>
                    <h2>Akun Saya</h2>
                    <p>Kelola informasi profil dan data kendaraan Anda.</p>
                </div>
            </div>
            <div class="account-container">
                <!-- Kartu Profil -->
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($nama_sesi, 0, 2)) ?>
                    </div>
                    <div class="profile-info">
                        <h3><?= $nama_sesi ?></h3>
                        <p><?= $username_sesi ?></p>
                        <span class="profile-badge"><i class="fa-solid fa-shield-check"></i> Akun Terverifikasi</span>
                    </div>
                    <div class="profile-stats">
                        <div class="pstat"><span><?= $jumlah_pesanan_profil ?></span><small>Pesanan</small></div>
                        <div class="pstat"><span><?= $jumlah_kendaraan_profil ?></span><small>Kendaraan</small></div>
                    </div>
                </div>

                <!-- Menu Akun -->
                <div class="account-grid">
                    <div class="account-section">
                        <h3><i class="fa-solid fa-pen-to-square"></i> Edit Profil</h3>
                        <form class="account-form" action="proses_ubah_profil.php" method="POST">
                            <div class="form-field">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" value="<?= $nama_sesi ?>" placeholder="Nama Lengkap" required>
                            </div>
                            <div class="form-field">
                                <label>Username</label>
                                <input type="text" name="username" value="<?= $username_sesi ?>" placeholder="Username" required>
                            </div>
                            <div class="form-field">
                                <label>Email</label>
                                <input type="email" name="email" value="<?= $email_sesi ?>" placeholder="email@contoh.com" required>
                            </div>
                            <div class="form-field">
                                <label>Nomor Telepon</label>
                                <input type="tel" name="no_hp" value="<?= $no_hp_sesi ?>" placeholder="08XXXXXXXXXX" required>
                            </div>
                            <button type="submit" class="btn-primary btn-sm">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                            </button>
                        </form>
                    </div>

                    <div class="account-section">
                        <div class="vehicle-panel-heading">
                            <h3>
                                <i class="fa-solid fa-car"></i>
                                Kendaraan Saya
                            </h3>

                            <div class="vehicle-scroll-buttons">
                                <button type="button" aria-label="Geser kendaraan ke kiri" onclick="geserKendaraan(-1)">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </button>

                                <button type="button" aria-label="Geser kendaraan ke kanan" onclick="geserKendaraan(1)">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="vehicle-list">
                            <?php if (empty($kendaraan_profil)): ?>
                                <div class="vehicle-empty"><i class="fa-solid fa-car"></i> Belum ada kendaraan terdaftar. Tambah kendaraan atau buat booking baru.</div>
                            <?php else: ?>
                                <?php foreach ($kendaraan_profil as $itemKendaraan): ?>
                                    <?php
                                    $idK = (int)$itemKendaraan['id_kendaraan'];
                                    $riwayatK = $riwayat_per_kendaraan[$idK] ?? [];
                                    $tahunK = date('Y', strtotime($itemKendaraan['tahun_keluar']));
                                    ?>
                                    <div class="vehicle-entry">
                                        <div class="vehicle-card">
                                            <div class="vehicle-icon"><i class="fa-solid fa-car"></i></div>
                                            <div class="vehicle-info">
                                                <strong><?= htmlspecialchars($itemKendaraan['merk'] . ' ' . $itemKendaraan['model'] . ' ' . $tahunK, ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span><?= htmlspecialchars($itemKendaraan['no_plat'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <form action="proses_arsip_kendaraan.php" method="POST"
                                                onsubmit="return confirm('Hapus dari daftar aktif? Riwayat booking tetap tersimpan.')"
                                                class="vehicle-delete-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_kendaraan'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id_kendaraan" value="<?= $idK ?>">
                                                <button class="btn-icon-danger" type="submit" title="Hapus kendaraan dari daftar aktif" aria-label="Hapus kendaraan"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </div>
                                        <div class="vehicle-actions">
                                            <button type="button" class="btn-primary btn-sm" onclick="mulaiBookingKendaraan(<?= $idK ?>)"><i class="fa-solid fa-calendar-plus"></i> Booking Servis</button>
                                            <button type="button" class="btn-outline btn-sm" onclick="toggleEditKendaraan(<?= $idK ?>)"><i class="fa-solid fa-pen"></i> Edit</button>
                                        </div>
                                        <form id="edit-kendaraan-<?= $idK ?>" class="account-form vehicle-edit-form" method="POST" action="proses_edit_kendaraan.php" hidden>
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_kendaraan'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="id_kendaraan" value="<?= $idK ?>">
                                            <div class="form-field"><label>Merk</label><input name="merk" maxlength="100" value="<?= htmlspecialchars($itemKendaraan['merk'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                            <div class="form-field"><label>Model / Tipe</label><input name="model" maxlength="100" value="<?= htmlspecialchars($itemKendaraan['model'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                            <div class="form-field"><label>Tahun</label><input type="number" name="tahun" min="1900" max="<?= (int)date('Y') + 1 ?>" value="<?= htmlspecialchars($tahunK, ENT_QUOTES, 'UTF-8') ?>" required></div>
                                            <div class="form-field"><label>Nomor Polisi</label><input name="nopol" maxlength="20" value="<?= htmlspecialchars($itemKendaraan['no_plat'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                                            <?php if ($riwayatK): ?>
                                                <p class="vehicle-help">Kendaraan ini punya riwayat booking; nomor polisi tidak bisa diubah agar data pesanan tetap konsisten.</p>
                                            <?php endif; ?>
                                            <div class="vehicle-actions"><button type="submit" class="btn-primary btn-sm"><i class="fa-solid fa-floppy-disk"></i> Simpan</button><button class="btn-outline btn-sm" type="button" onclick="toggleEditKendaraan(<?= $idK ?>)">Batal</button></div>
                                        </form>
                                        <details class="vehicle-history">
                                            <summary><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Booking & Servis <span>(<?= count($riwayatK) ?>)</span></summary>
                                            <?php if (!$riwayatK): ?>
                                                <p class="vehicle-help">Belum ada riwayat servis untuk kendaraan ini.</p>
                                            <?php else: ?>
                                                <div class="vehicle-history-items">
                                                    <?php foreach ($riwayatK as $riwayat): ?>
                                                        <div class="vehicle-history-item">
                                                            <strong><?= htmlspecialchars($riwayat['nama_layanan'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <span class="vehicle-history-status"><?= htmlspecialchars($riwayat['status_booking'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            <small>BK-<?= str_pad((string)$riwayat['id_booking'], 4, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars(date('d-m-Y', strtotime($riwayat['tanggal_booking'])), ENT_QUOTES, 'UTF-8') ?> · <?= sprintf('%02d:00', (int)$riwayat['jam_booking']) ?></small>
                                                            <small><?= htmlspecialchars($riwayat['nama_bengkel'] ?: 'Bengkel belum ditentukan', ENT_QUOTES, 'UTF-8') ?></small>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </details>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button class="btn-outline" type="button" style="margin-top:15px;"
                            onclick="var f=document.getElementById('form-tambah-kendaraan'); f.hidden=!f.hidden;">
                            <i class="fa-solid fa-plus"></i> Tambah Kendaraan
                        </button>
                        <form id="form-tambah-kendaraan" class="account-form" action="proses_tambah_kendaraan.php"
                            method="POST" hidden style="margin-top:15px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_kendaraan'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-field">
                                <label>Merk</label>
                                <input type="text" name="merk" maxlength="100" placeholder="Contoh: Toyota" required>
                            </div>
                            <div class="form-field">
                                <label>Model / Tipe</label>
                                <input type="text" name="model" maxlength="100" placeholder="Contoh: Avanza" required>
                            </div>
                            <div class="form-field">
                                <label>Tahun</label>
                                <input type="number" name="tahun" min="1900" max="<?= (int) date('Y') + 1 ?>" placeholder="Contoh: 2020" required>
                            </div>
                            <div class="form-field">
                                <label>Nomor Polisi</label>
                                <input type="text" name="nopol" maxlength="20" placeholder="Contoh: AD 1234 AB" required>
                            </div>
                            <button class="btn-primary btn-sm" type="submit">Simpan Kendaraan</button>
                        </form>
                    </div>

                    <div class="account-section">
                        <h3><i class="fa-solid fa-lock"></i> Ubah Password</h3>

                        <form class="account-form" action="ubah_password.php" method="POST">

                            <div class="form-field">
                                <label>Password Lama</label>
                                <input
                                    type="password"
                                    name="password_lama"
                                    placeholder="Password saat ini"
                                    required
                                    autocomplete="current-password">
                            </div>

                            <div class="form-field">
                                <label>Password Baru</label>
                                <input
                                    type="password"
                                    name="password_baru"
                                    placeholder="Password baru (min. 6 karakter)"
                                    minlength="6"
                                    required
                                    autocomplete="new-password">
                            </div>

                            <div class="form-field">
                                <label>Konfirmasi Password Baru</label>
                                <input
                                    type="password"
                                    name="konfirmasi_password"
                                    placeholder="Ulangi password baru"
                                    minlength="6"
                                    required
                                    autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn-primary btn-sm">
                                <i class="fa-solid fa-key"></i> Ubah Password
                            </button>

                        </form>
                    </div>

                    <div class="account-section account-session">
                        <h3><i class="fa-solid fa-right-from-bracket"></i> Sesi Akun</h3>
                        <p>Keluar dari akun ini di perangkat yang sedang Anda pakai.</p>
                        <a href="logout.php" class="btn-outline"><i class="fa-solid fa-right-from-bracket"></i> Logout dari Akun</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Booking Sukses -->
    <div id="booking-modal" class="modal-overlay" style="display:none;">
        <div class="modal-box">
            <div class="modal-icon success-icon"><i class="fa-solid fa-circle-check"></i></div>
            <h3>Booking Berhasil Dikirim!</h3>
            <p>Pesanan Anda sedang diproses. Kami akan menghubungi Anda segera untuk konfirmasi jadwal.</p>
            <p class="modal-id">ID Pesanan: <strong id="modal-order-id"></strong></p>
            <button class="btn-primary" onclick="closeModal()"><i class="fa-solid fa-check"></i> Oke, Mengerti</button>
        </div>
    </div>

    <script>
        // ============================================
        // TOGGLE LOGIN / REGISTER
        // ============================================
        function toggleAuth(type) {
            const login = document.getElementById('login-form');
            const register = document.getElementById('register-form');
            if (type === 'register') {
                login.style.display = 'none';
                register.style.display = 'block';
            } else {
                login.style.display = 'block';
                register.style.display = 'none';
            }
        }

        // ============================================
        // TOGGLE PASSWORD VISIBILITY
        // ============================================
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

        // ============================================
        // NAVIGASI HALAMAN UTAMA
        // ============================================
        function showPage(pageId) {

            // Tutup modal detail jika sedang terbuka
            const detailModal =
                document.getElementById('order-detail-modal');

            if (detailModal) {
                detailModal.style.display = 'none';
            }

            // Tutup modal booking jika masih terbuka
            const bookingModal =
                document.getElementById('booking-modal');

            if (bookingModal) {
                bookingModal.style.display = 'none';
            }

            // Sembunyikan semua halaman
            document
                .querySelectorAll('.content-section')
                .forEach(el => {
                    el.classList.remove('active');
                });

            // Hilangkan status active navbar
            document
                .querySelectorAll('.nav-links a')
                .forEach(el => {
                    el.classList.remove('active');
                });

            // Tampilkan halaman yang dipilih
            const targetPage =
                document.getElementById(
                    'content-' + pageId
                );

            if (targetPage) {
                targetPage.classList.add('active');
            }

            // Aktifkan navbar
            const navEl =
                document.getElementById(
                    'nav-' + pageId
                );

            if (navEl) {
                navEl.classList.add('active');
            }

            // Tutup menu mobile
            const navLinks =
                document.getElementById('nav-links');

            if (navLinks) {
                navLinks.classList.remove('open');
            }

            window.scrollTo(0, 0);
        }

        // ============================================
        // MOBILE MENU
        // ============================================
        function toggleMobileMenu() {
            const menu = document.getElementById('nav-links');
            const terbuka = menu.classList.toggle('open');
            const tombolMenu = document.querySelector('.hamburger');
            if (tombolMenu) tombolMenu.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
        }

        // ============================================
        // NAVBAR SCROLL EFFECT
        // ============================================
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (navbar) {
                if (window.scrollY > 50) navbar.classList.add('scrolled');
                else navbar.classList.remove('scrolled');
            }
        });

        // ============================================
        // SWITCH TAB
        // ============================================
        function switchTab(el) {
            const tabs = el.closest('.tabs').querySelectorAll('.tab');
            tabs.forEach(t => t.classList.remove('active'));
            el.classList.add('active');
        }

        function filterNotif(mode, tombol) {
            document.querySelectorAll('.psn-filter').forEach(btn => {
                btn.classList.remove('active');
            });

            tombol.classList.add('active');

            let jumlahTampil = 0;

            document.querySelectorAll('.psn-card').forEach(card => {
                const belumDibaca = card.dataset.unread === '1';

                const tampil = mode === 'semua' || belumDibaca;

                card.hidden = !tampil;

                if (tampil) {
                    jumlahTampil++;
                }
            });

            const pesanKosong = document.getElementById('psn-filter-empty');

            if (pesanKosong) {
                pesanKosong.hidden = jumlahTampil > 0;
            }
        }

        // Filter tab khusus halaman Pesanan
        function switchOrderTab(el, status) {
            const tabs = el.closest('.tabs').querySelectorAll('.tab');
            tabs.forEach(t => t.classList.remove('active'));
            el.classList.add('active');

            let visibleCount = 0;
            document.querySelectorAll('#order-list .order-card').forEach(card => {
                const show = status === 'semua' || card.dataset.status === status;
                card.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            document.getElementById('order-empty').style.display = visibleCount === 0 ? 'flex' : 'none';
        }

        // Membuka detail dan menandai kartu pesanan yang dipilih
        function openOrderDetail(button, id, status, service, vehicle, plate, workshop, date, time, note) {

            document.querySelectorAll('#order-list .order-card').forEach(card => {
                card.classList.remove('selected');
            });

            const card = button.closest('.order-card');

            if (card) {
                card.classList.add('selected');
            }

            document.getElementById('detail-id').textContent = id;
            document.getElementById('detail-service').textContent = service;
            document.getElementById('detail-vehicle').textContent = vehicle;
            document.getElementById('detail-plate').textContent = plate;
            document.getElementById('detail-workshop').textContent = workshop;
            document.getElementById('detail-date').textContent = date;
            document.getElementById('detail-time').textContent = time;
            document.getElementById('detail-note').textContent = note;

            const badge = document.getElementById('detail-status');

            badge.textContent = status;

            let statusClass = 'status-proses';

            if (status === 'Menunggu') {
                statusClass = 'status-jadwal';
            } else if (status === 'Diterima') {
                statusClass = 'status-diterima';
            } else if (status === 'Diproses') {
                statusClass = 'status-proses';
            } else if (status === 'Selesai') {
                statusClass = 'status-selesai';
            } else if (
                status === 'Ditolak' ||
                status === 'Dibatalkan'
            ) {
                statusClass = 'status-batal';
            }

            badge.className =
                'status-badge ' + statusClass;

            document.getElementById(
                'order-detail-modal'
            ).style.display = 'flex';
        }

        function openOrderFromData(button) {

            openOrderDetail(
                button,
                button.dataset.id,
                button.dataset.status,
                button.dataset.service,
                button.dataset.vehicle,
                button.dataset.plate,
                button.dataset.workshop,
                button.dataset.date,
                button.dataset.time,
                button.dataset.note
            );
        }

        function closeOrderDetail(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('order-detail-modal').style.display = 'none';
        }

        // ESC untuk menutup modal detail
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('order-detail-modal').style.display = 'none';
            }
        });

        // ============================================
        // KENDARAAN TERSIMPAN + EDIT KENDARAAN
        // ============================================
        function toggleEditKendaraan(id) {
            const form = document.getElementById('edit-kendaraan-' + id);

            if (!form) return;

            const akanDibuka = form.hidden;

            // Tutup semua form edit kendaraan
            document.querySelectorAll('.vehicle-edit-form').forEach(item => {
                item.hidden = true;
            });

            // Buka atau tutup form yang dipilih
            form.hidden = !akanDibuka;
        }

        function geserKendaraan(arah) {
            const daftar = document.querySelector(
                '#content-account .vehicle-list'
            );

            if (!daftar) return;

            const kartu = daftar.querySelector('.vehicle-entry');

            const jarak = kartu ?
                kartu.getBoundingClientRect().width + 18 :
                300;

            daftar.scrollBy({
                left: arah * jarak,
                behavior: 'smooth'
            });
        }

        function pilihKendaraanBooking() {
            const select = document.getElementById('booking-pilih-kendaraan');
            const baru = document.getElementById('booking-data-baru');
            const ringkasan = document.getElementById('booking-ringkasan-kendaraan');
            if (!select || !baru || !ringkasan) return;
            const id = select.value;
            const isBaru = id === 'baru';
            const isTersimpan = id !== '' && !isBaru;
            baru.hidden = !isBaru;
            baru.querySelectorAll('input,select').forEach(field => {
                field.disabled = !isBaru;
                field.required = isBaru;
            });
            ringkasan.hidden = !isTersimpan;
            if (isTersimpan) {
                const item = select.options[select.selectedIndex];
                const info = document.createElement('span');
                info.textContent = 'Kendaraan dipilih: ' + item.dataset.merk + ' ' + item.dataset.model +
                    ' (' + item.dataset.tahun + ') — ' + item.dataset.plat;
                ringkasan.replaceChildren(info);
            } else {
                ringkasan.replaceChildren();
            }
        }

        function mulaiBookingKendaraan(id) {
            showPage('booking');
            const select = document.getElementById('booking-pilih-kendaraan');
            if (!select) return;
            select.value = String(id);
            pilihKendaraanBooking();
            document.getElementById('content-booking')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        // ============================================
        // BOOKING SERVICE (dari card di beranda)
        // ============================================
        function bookService(namaLayanan) {
            showPage('booking');
            // Pilih radio button yang sesuai
            setTimeout(() => {
                document.querySelectorAll('input[name="layanan[]"]').forEach(c => {
                    c.checked = (c.value === namaLayanan);
                });
                const err = document.getElementById('layanan-error');
                if (err) err.hidden = true;
            }, 100);
        }

        function closeModal() {
            document.getElementById('booking-modal').style.display = 'none';
            showPage('order');
        }

        // ============================================
        // TOAST NOTIFICATION
        // ============================================
        function showToast(msg, type = 'success') {
            const old = document.getElementById('toast-msg');
            if (old) old.remove();
            const toast = document.createElement('div');
            toast.id = 'toast-msg';
            toast.className = `toast toast-${type} show`;
            toast.innerHTML = `<i class="fa-solid fa-${type === 'success' ? 'circle-check' : 'circle-xmark'}"></i><span>${msg}</span><button onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        // ============================================
        // VALIDASI LAYANAN (minimal 1 dipilih)
        // ============================================
        const formBooking = document.getElementById('form-booking');
        if (formBooking) {
            formBooking.addEventListener('submit', function(e) {
                const dipilih = formBooking.querySelectorAll('input[name="layanan[]"]:checked').length;
                const err = document.getElementById('layanan-error');
                if (dipilih === 0) {
                    e.preventDefault();
                    if (err) {
                        err.hidden = false;
                        err.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                } else if (err) {
                    err.hidden = true;
                }
            });
            formBooking.addEventListener('change', function(e) {
                if (e.target.name === 'layanan[]') {
                    const err = document.getElementById('layanan-error');
                    if (err) err.hidden = formBooking.querySelectorAll('input[name="layanan[]"]:checked').length > 0;
                }
            });
        }

        // ============================================
        // JAM PENUH: tandai slot yang sudah terisi
        // ============================================
        function perbaruiJamPenuh() {
            const tgl = document.getElementById('tgl-booking');
            const bengkel = document.getElementById('booking-bengkel');
            const jam = document.getElementById('booking-jam');
            if (!tgl || !bengkel || !jam) return;

            // Reset dulu semua pilihan jam
            Array.from(jam.options).forEach(opt => {
                if (!opt.value) return;
                opt.disabled = false;
                opt.textContent = opt.value;
            });

            if (!tgl.value || !bengkel.value) return;

            const url = 'cek_jam.php?tanggal=' + encodeURIComponent(tgl.value) +
                '&bengkel=' + encodeURIComponent(bengkel.value);

            fetch(url, {
                    credentials: 'same-origin'
                })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(data => {
                    const penuh = (data && data.penuh) || [];
                    Array.from(jam.options).forEach(opt => {
                        if (!opt.value) return;
                        if (penuh.includes(parseInt(opt.value, 10))) {
                            opt.disabled = true;
                            opt.textContent = opt.value + ' (Penuh)';
                            if (jam.value === opt.value) jam.value = '';
                        }
                    });
                })
                .catch(() => {
                    /* gagal cek: biarkan semua jam bisa dipilih */
                });
        }

        document.getElementById('tgl-booking')?.addEventListener('change', perbaruiJamPenuh);
        document.getElementById('booking-bengkel')?.addEventListener('change', perbaruiJamPenuh);

        // ============================================
        // SET MIN DATE BOOKING = BESOK
        // ============================================
        const tglInput = document.getElementById('tgl-booking');
        if (tglInput) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tglInput.min = tomorrow.toISOString().split('T')[0];
        }

        // ============================================
        // AUTO-DISMISS TOAST
        // ============================================
        window.addEventListener('DOMContentLoaded', () => {
            const toast = document.getElementById('toast-msg');
            if (toast) {
                toast.classList.add('show');
                setTimeout(() => toast && toast.remove(), 5000);
            }
        });

        function confirmBatalBooking() {
            return confirm('Apakah Anda yakin ingin membatalkan booking ini?');
        }

        // Mulai dengan pilihan kendaraan sesuai data akun.
        pilihKendaraanBooking();
        // Setelah proses simpan/edit/hapus, kembali ke halaman yang relevan.
        const parameterHalaman = new URLSearchParams(window.location.search).get('halaman');
        if (document.getElementById('main-app')?.classList.contains('active') && ['account', 'booking', 'order', 'home', 'notif'].includes(parameterHalaman)) {
            showPage(parameterHalaman);
        }

        // Buka detail booking dari notifikasi
        const idDariNotif = new URLSearchParams(
            window.location.search
        ).get('pesanan_id');

        if (idDariNotif && /^\d+$/.test(idDariNotif)) {

            const nomorBooking = 'BK-' +
                String(Number(idDariNotif)).padStart(5, '0');

            const tombolDetail = Array.from(
                document.querySelectorAll('#order-list .btn-detail')
            ).find(btn => btn.dataset.id === nomorBooking);

            if (tombolDetail) {
                showPage('order');
                openOrderFromData(tombolDetail);
            }
        }

        // Jangan mengulang notifikasi sukses/error setiap kali halaman di-refresh.
        const urlBersih = new URL(window.location.href);
        if (urlBersih.searchParams.has('sukses') || urlBersih.searchParams.has('error')) {
            urlBersih.searchParams.delete('sukses');
            urlBersih.searchParams.delete('error');
            window.history.replaceState({}, document.title,
                urlBersih.pathname + urlBersih.search + urlBersih.hash);
        }
    </script>
</body>

</html>