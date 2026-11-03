<?php
session_start();

include 'koneksi.php';

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
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
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
                        <img class="brand-logo" src="foto/logo%20bengkel.jpeg" alt="Logo Bengkel">
                        <h1>PitStop.id</h1>
                    </div>
                    <p class="tagline">Booking Service, Jadi Lebih Mudah!</p>
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
                        <div class="input-group">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="username" placeholder="Username" required autocomplete="username">
                        </div>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="login-pass" placeholder="Password" required autocomplete="current-password">
                            <button type="button" class="toggle-pass" onclick="togglePassword('login-pass', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-right-to-bracket"></i> Masuk
                        </button>
                    </form>
                    <div class="auth-divider"><span>atau</span></div>
                    <p class="switch-text">Belum punya akun? <a href="#" onclick="toggleAuth('register')">Daftar Sekarang!</a></p>
                </div>

                <!-- Form Daftar -->
                <div id="register-form" class="form-box" style="display: none;">
                    <div class="form-header">
                        <h2>Buat Akun Baru</h2>
                        <p class="subtitle">Bergabung dengan jutaan pengguna PitStop.id</p>
                    </div>
                    <form action="proses_daftar.php" method="POST">
                        <div class="input-group">
                            <i class="fa-solid fa-id-card input-icon"></i>
                            <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" required>
                        </div>
                        <div class="input-group">
                            <i class="fa-solid fa-location-dot input-icon"></i>
                            <input type="text" name="alamat" placeholder="Alamat Lengkap" required>
                        </div>
                        <div class="input-group">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="Email" required>
                        </div>
                        <div class="input-group">
                            <i class="fa-regular fa-user input-icon"></i>
                            <input type="text" name="username" placeholder="Username" required autocomplete="username">
                        </div>
                        <div class="input-group">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="reg-pass" placeholder="Password (min. 6 karakter)" required minlength="6" autocomplete="new-password">
                            <button type="button" class="toggle-pass" onclick="togglePassword('reg-pass', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-user-plus"></i> Daftar Sekarang
                        </button>
                    </form>
                    <div class="auth-divider"><span>atau</span></div>
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
                        <span class="notif-dot">3</span>
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
                <button class="hamburger" onclick="toggleMobileMenu()"><i class="fa-solid fa-bars"></i></button>
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
                <div class="feature"><i class="fa-solid fa-thumbs-up"></i> Kepuasan Pelanggan 98%</div>
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
                    <div class="card" onclick="bookService('Servis Rem & Suspensi')">
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
                            <a href="#"><i class="fa-brands fa-instagram"></i></a>
                            <a href="#"><i class="fa-brands fa-facebook"></i></a>
                            <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
                            <a href="#"><i class="fa-brands fa-twitter"></i></a>
                        </div>
                    </div>
                    <div class="footer-links">
                        <h4>Layanan</h4>
                        <ul>
                            <li><a href="#">Servis Mesin</a></li>
                            <li><a href="#">Servis Ban</a></li>
                            <li><a href="#">Servis AC</a></li>
                            <li><a href="#">Servis Rem</a></li>
                        </ul>
                    </div>
                    <div class="footer-links">
                        <h4>Perusahaan</h4>
                        <ul>
                            <li><a href="#">Tentang Kami</a></li>
                            <li><a href="#">Blog</a></li>
                            <li><a href="#">Karir</a></li>
                            <li><a href="#">Hubungi Kami</a></li>
                        </ul>
                    </div>
                    <div class="footer-contact">
                        <h4>Kontak</h4>
                        <p><i class="fa-solid fa-phone"></i> 0889-8511-8045</p>
                        <p><i class="fa-solid fa-envelope"></i> support@pitstop.id</p>
                        <p><i class="fa-solid fa-location-dot"></i> Jakarta, Indonesia</p>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>&copy; 2025 PitStop.id — All Rights Reserved.</p>
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
                    action="proses_booking.php"
                    method="POST">
                    <div class="booking-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Data Kendaraan</h3>
                            <div class="form-grid">
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
                                    <input type="text" name="model" placeholder="Contoh: Avanza 1.3 G" required>
                                </div>
                                <div class="form-field">
                                    <label>Tahun Kendaraan</label>
                                    <input type="number" name="tahun" placeholder="Contoh: 2019" min="1990" max="2026" required>
                                </div>
                                <div class="form-field">
                                    <label>Nomor Polisi</label>
                                    <input type="text" name="nopol" placeholder="Contoh: B 1234 ABC" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="booking-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Pilih Layanan</h3>
                            <div class="service-options">
                                <label class="service-option">
                                    <input type="radio" name="layanan" value="Servis Ban & Roda" required>
                                    <div class="service-option-box"><i class="fa-solid fa-circle-dot"></i><span>Servis Ban & Roda</span></div>
                                </label>
                                <label class="service-option">
                                    <input type="radio" name="layanan" value="Servis Mesin">
                                    <div class="service-option-box"><i class="fa-solid fa-gear"></i><span>Servis Mesin</span></div>
                                </label>
                                <label class="service-option">
                                    <input type="radio" name="layanan" value="Servis AC">
                                    <div class="service-option-box"><i class="fa-solid fa-snowflake"></i><span>Servis AC</span></div>
                                </label>
                                <label class="service-option">
                                    <input type="radio" name="layanan" value="Servis Kelistrikan">
                                    <div class="service-option-box"><i class="fa-solid fa-bolt"></i><span>Kelistrikan</span></div>
                                </label>
                                <label class="service-option">
                                    <input type="radio" name="layanan" value="Rem & Suspensi">
                                    <div class="service-option-box"><i class="fa-solid fa-car-burst"></i><span>Rem & Suspensi</span></div>
                                </label>
                                <label class="service-option">
                                    <input type="radio" name="layanan" value="Cuci & Poles">
                                    <div class="service-option-box"><i class="fa-solid fa-soap"></i><span>Cuci & Poles</span></div>
                                </label>
                            </div>
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
                                    <label>Jam Servis</label>
                                    <select name="jam" required>
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
                                    <select name="bengkel" required>
                                        <option value="" disabled selected>Pilih Bengkel Terdekat</option>
                                        <option>PitStop Mitra - Jakarta Selatan</option>
                                        <option>PitStop Mitra - Jakarta Pusat</option>
                                        <option>PitStop Mitra - Jakarta Barat</option>
                                        <option>PitStop Mitra - Jakarta Timur</option>
                                        <option>PitStop Mitra - Bandung</option>
                                        <option>PitStop Mitra - Surabaya</option>
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
            <div class="page-header">
                <i class="fa-solid fa-bell header-icon"></i>
                <div>
                    <h2>Notifikasi</h2>
                    <p>Pantau semua informasi terbaru mengenai pesanan dan aktivitas akun Anda.</p>
                </div>
            </div>
            <div class="tabs">
                <button class="tab active" onclick="switchTab(this)">Servis <span class="badge">3</span></button>
                <button class="tab" onclick="switchTab(this)">Pesanan <span class="badge">2</span></button>
                <button class="tab" onclick="switchTab(this)">Sistem <span class="badge">1</span></button>
            </div>
            <div class="list-container">
                <div class="list-item success">
                    <div class="item-icon"><i class="fa-solid fa-check"></i></div>
                    <div class="item-content">
                        <h4>Pesanan Diterima</h4>
                        <p>Pesanan <strong>#BK-20251026-001</strong> telah diterima dan sedang diproses oleh bengkel.</p>
                    </div>
                    <div class="item-time">Hari ini, 10:24 <i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="list-item info">
                    <div class="item-icon"><i class="fa-solid fa-calendar"></i></div>
                    <div class="item-content">
                        <h4>Jadwal Servis Terkonfirmasi</h4>
                        <p>Servis untuk pesanan <strong>#BK-20251024-005</strong> akan dilaksanakan pada <strong>28 Oktober 2025</strong> pukul 09:00.</p>
                    </div>
                    <div class="item-time">Kemarin, 14:45 <i class="fa-solid fa-chevron-right"></i></div>
                </div>
                <div class="list-item danger">
                    <div class="item-icon"><i class="fa-solid fa-xmark"></i></div>
                    <div class="item-content">
                        <h4>Pesanan Dibatalkan</h4>
                        <p>Pesanan <strong>#BK-20251020-003</strong> dibatalkan oleh admin. Alasan: Jadwal bengkel penuh.</p>
                    </div>
                    <div class="item-time">20 Okt 2025, 14:32 <i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </div>
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
                        <div class="pstat"><span>4</span><small>Pesanan</small></div>
                        <div class="pstat"><span>2</span><small>Kendaraan</small></div>
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
                                <input
                                    type="tel"
                                    name="no_hp"
                                    value="<?= $no_hp_sesi ?>"
                                    placeholder="Contoh: 081234567890"
                                    pattern="08[0-9]{8,11}"
                                    minlength="10"
                                    maxlength="13"
                                    inputmode="numeric"
                                    required>
                            </div>
                            <button type="submit" class="btn-primary btn-sm">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                            </button>
                        </form>
                    </div>

                    <div class="account-section">
                        <h3><i class="fa-solid fa-car"></i> Kendaraan Saya</h3>
                        <div class="vehicle-list">
                            <div class="vehicle-card">
                                <div class="vehicle-icon"><i class="fa-solid fa-car"></i></div>
                                <div class="vehicle-info">
                                    <strong>Toyota Avanza 2019</strong>
                                    <span>B 1234 ABC</span>
                                </div>
                                <button class="btn-icon-danger"><i class="fa-solid fa-trash"></i></button>
                            </div>
                            <div class="vehicle-card">
                                <div class="vehicle-icon"><i class="fa-solid fa-car"></i></div>
                                <div class="vehicle-info">
                                    <strong>Honda Brio 2021</strong>
                                    <span>B 5678 XYZ</span>
                                </div>
                                <button class="btn-icon-danger"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                        <button class="btn-outline" style="margin-top:15px;" onclick="alert('Fitur tambah kendaraan segera hadir!')">
                            <i class="fa-solid fa-plus"></i> Tambah Kendaraan
                        </button>
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

                    <div class="account-section account-danger-zone">
                        <h3><i class="fa-solid fa-triangle-exclamation"></i> Zona Bahaya</h3>
                        <p>Tindakan di bawah ini bersifat permanen dan tidak dapat dibatalkan.</p>
                        <a href="logout.php" class="btn-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout dari Akun</a>
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
            document.getElementById('nav-links').classList.toggle('open');
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

        // Filter tab khusus halaman Pesanan
        function switchOrderTab(el, status) {
            const tabs = el.closest('.tabs').querySelectorAll('.tab');
            tabs.forEach(t => t.classList.remove('active'));
            el.classList.add('active');

            let visibleCount = 0;
            document.querySelectorAll('#order-list .order-card').forEach(card => {
                const show = status === 'semua' || card.dataset.status === status;
                card.style.display = show ? 'flex' : 'none';
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
        // BOOKING SERVICE (dari card di beranda)
        // ============================================
        function bookService(namaLayanan) {
            showPage('booking');
            // Pilih radio button yang sesuai
            setTimeout(() => {
                document.querySelectorAll('input[name="layanan"]').forEach(r => {
                    if (r.value === namaLayanan) r.checked = true;
                });
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

        window.addEventListener('DOMContentLoaded', function() {
            const url = new URL(window.location.href);

            if (url.searchParams.has('sukses') || url.searchParams.has('error')) {
                url.searchParams.delete('sukses');
                url.searchParams.delete('error');

                window.history.replaceState({},
                    document.title,
                    url.pathname + url.search + url.hash
                );
            }
        });
    </script>
</body>

</html>