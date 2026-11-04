<?php

session_start();

include 'koneksi.php';

date_default_timezone_set('Asia/Jakarta');


// ==========================================
// CEK LOGIN
// ==========================================

if (!isset($_SESSION['id_pelanggan'])) {

    header(
        "Location: index.php?halaman=booking&error="
            . urlencode("Silakan login terlebih dahulu.")
    );

    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");

    exit();
}


$id_pelanggan =
    (int) $_SESSION['id_pelanggan'];


// ==========================================
// AMBIL FORM
// ==========================================

$merk =
    trim($_POST['merk'] ?? '');

$model =
    trim($_POST['model'] ?? '');

$tahun =
    (int) ($_POST['tahun'] ?? 0);

$nopol =
    strtoupper(
        trim($_POST['nopol'] ?? '')
    );

$layanan =
    trim($_POST['layanan'] ?? '');

$keluhan =
    trim($_POST['keluhan'] ?? '');

$tanggal =
    trim($_POST['tanggal'] ?? '');

$jam =
    trim($_POST['jam'] ?? '');

$bengkel =
    trim($_POST['bengkel'] ?? '');

// 'baru' = ketik data kendaraan; angka ID = kendaraan tersimpan.
$pilihanKendaraan = trim((string)($_POST['kendaraan_id'] ?? 'baru'));
$pakaiKendaraanTersimpan = ($pilihanKendaraan !== 'baru');

// Form booking menggunakan token yang dibuat saat halaman index dimuat.
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !isset($_SESSION['csrf_kendaraan']) ||
    !hash_equals($_SESSION['csrf_kendaraan'], $token)) {
    header('Location: index.php?halaman=booking&error=' . urlencode('Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.'));
    exit();
}


// ==========================================
// VALIDASI
// ==========================================

if (
    (!$pakaiKendaraanTersimpan && (
        $merk === '' || $model === '' || $tahun < 1900 ||
        $tahun > ((int)date('Y') + 1) || $nopol === '' ||
        strlen($merk) > 100 || strlen($model) > 100 || strlen($nopol) > 20
    )) ||
    $layanan === '' ||
    $tanggal === '' ||
    $jam === '' ||
    $bengkel === ''
) {

    header(
        "Location: index.php?halaman=booking&error="
            . urlencode("Data booking belum lengkap.")
    );

    exit();
}


if ($tanggal < date('Y-m-d')) {

    header(
        "Location: index.php?halaman=booking&error="
            . urlencode(
                "Tanggal booking tidak boleh sebelum hari ini."
            )
    );

    exit();
}


// Database menggunakan INT untuk jam_booking.
// "08:00" akan menjadi 8.

$jam_booking =
    (int) substr($jam, 0, 2);


// kendaraan.tahun_keluar menggunakan DATE

$tahun_keluar =
    $tahun . '-01-01';


mysqli_begin_transaction($koneksi);


try {


    // ==========================================
    // 1. AMBIL KENDARAAN TERSIMPAN (JIKA DIPILIH)
    // ==========================================
    if ($pakaiKendaraanTersimpan) {
        $idDipilih = filter_var($pilihanKendaraan, FILTER_VALIDATE_INT);
        if (!$idDipilih || $idDipilih < 1) {
            throw new Exception('Pilihan kendaraan tidak valid.');
        }
        $cekDipilih = $koneksi->prepare(
            'SELECT merk, model, tahun_keluar, no_plat FROM kendaraan
             WHERE id_kendaraan = ? AND id_pelanggan = ? AND is_active = 1 LIMIT 1'
        );
        $cekDipilih->bind_param('ii', $idDipilih, $id_pelanggan);
        $cekDipilih->execute();
        $dataDipilih = $cekDipilih->get_result()->fetch_assoc();
        $cekDipilih->close();
        if (!$dataDipilih) {
            throw new Exception('Kendaraan tidak ditemukan pada akun kamu. Pilih kendaraan lain.');
        }
        // Abaikan data kendaraan dari browser; data resmi berasal dari database.
        $merk = $dataDipilih['merk'];
        $model = $dataDipilih['model'];
        $nopol = $dataDipilih['no_plat'];
        $tahun_keluar = $dataDipilih['tahun_keluar'];
    }

    // ==========================================
    // 2. CARI ATAU BUAT KENDARAAN
    // ==========================================

    $cekKendaraan = $koneksi->prepare(
        "SELECT
            id_kendaraan,
            id_pelanggan
         FROM kendaraan
         WHERE no_plat = ?
         LIMIT 1"
    );

    $cekKendaraan->bind_param(
        "s",
        $nopol
    );

    $cekKendaraan->execute();

    $hasilKendaraan =
        $cekKendaraan->get_result();


    if ($hasilKendaraan->num_rows === 1) {

        $kendaraan =
            $hasilKendaraan->fetch_assoc();


        // Nomor polisi tidak boleh milik akun lain

        if (
            (int)$kendaraan['id_pelanggan']
            !== $id_pelanggan
        ) {

            throw new Exception(
                "Nomor polisi sudah digunakan akun lain."
            );
        }


        $id_kendaraan =
            (int)$kendaraan['id_kendaraan'];


        // Update data kendaraan terbaru

        $updateKendaraan =
            $koneksi->prepare(
                "UPDATE kendaraan
                 SET
                    merk = ?,
                    model = ?,
                    tahun_keluar = ?,
                    is_active = 1
                 WHERE id_kendaraan = ?
                 AND id_pelanggan = ?"
            );

        $updateKendaraan->bind_param(
            "sssii",
            $merk,
            $model,
            $tahun_keluar,
            $id_kendaraan,
            $id_pelanggan
        );

        $updateKendaraan->execute();

        $updateKendaraan->close();
    } else {


        // Kendaraan belum ada → buat

        $insertKendaraan =
            $koneksi->prepare(
                "INSERT INTO kendaraan
                (
                    id_pelanggan,
                    no_plat,
                    merk,
                    model,
                    tahun_keluar
                )
                VALUES (?, ?, ?, ?, ?)"
            );

        $insertKendaraan->bind_param(
            "issss",
            $id_pelanggan,
            $nopol,
            $merk,
            $model,
            $tahun_keluar
        );

        if (!$insertKendaraan->execute()) {

            throw new Exception(
                "Gagal menyimpan kendaraan."
            );
        }

        $id_kendaraan =
            $koneksi->insert_id;

        $insertKendaraan->close();
    }


    $cekKendaraan->close();


    // ==========================================
    // 2. CARI LAYANAN
    // ==========================================

    $cekLayanan =
        $koneksi->prepare(
            "SELECT
                id_layanan,
                harga
             FROM layanan
             WHERE nama_layanan = ?
             LIMIT 1"
        );

    $cekLayanan->bind_param(
        "s",
        $layanan
    );

    $cekLayanan->execute();

    $hasilLayanan =
        $cekLayanan->get_result();


    if ($hasilLayanan->num_rows === 1) {

        $dataLayanan =
            $hasilLayanan->fetch_assoc();

        $id_layanan =
            (int)$dataLayanan['id_layanan'];

        $harga =
            (int)$dataLayanan['harga'];
    } else {


        /*
         * Form kamu punya layanan seperti:
         * Servis Ban & Roda
         * Servis Mesin
         * Servis AC
         * dll.
         *
         * Kalau belum ada di database,
         * otomatis dibuat.
         */

        $deskripsi =
            "Layanan booking PitStop.id";

        $harga = 0;


        $insertLayanan =
            $koneksi->prepare(
                "INSERT INTO layanan
                (
                    nama_layanan,
                    deskripsi,
                    harga
                )
                VALUES (?, ?, ?)"
            );

        $insertLayanan->bind_param(
            "ssi",
            $layanan,
            $deskripsi,
            $harga
        );

        if (!$insertLayanan->execute()) {

            throw new Exception(
                "Gagal menyimpan layanan."
            );
        }


        $id_layanan =
            $koneksi->insert_id;

        $insertLayanan->close();
    }


    $cekLayanan->close();


    // ==========================================
    // 3. SIMPAN BOOKING
    // ==========================================

    $status =
        "Menunggu";


    if ($keluhan === '') {
        $keluhan = '-';
    }


    $insertBooking =
        $koneksi->prepare(
            "INSERT INTO booking
            (
                id_pelanggan,
                id_kendaraan,
                tanggal_booking,
                jam_booking,
                nama_bengkel,
                status_booking,
                catatan_keluhan
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );


    $insertBooking->bind_param(
        "iisisss",
        $id_pelanggan,
        $id_kendaraan,
        $tanggal,
        $jam_booking,
        $bengkel,
        $status,
        $keluhan
    );


    if (!$insertBooking->execute()) {

        throw new Exception(
            "Gagal menyimpan booking."
        );
    }


    $id_booking =
        $koneksi->insert_id;

    $insertBooking->close();


    // ==========================================
    // 4. SIMPAN DETAIL BOOKING
    // ==========================================

    $insertDetail =
        $koneksi->prepare(
            "INSERT INTO detail_booking
            (
                id_booking,
                id_layanan,
                subtotal
            )
            VALUES (?, ?, ?)"
        );


    $insertDetail->bind_param(
        "iii",
        $id_booking,
        $id_layanan,
        $harga
    );


    if (!$insertDetail->execute()) {

        throw new Exception(
            "Gagal menyimpan detail booking."
        );
    }


    $insertDetail->close();


    // ==========================================
    // BERHASIL
    // ==========================================

    mysqli_commit($koneksi);


    header(
        "Location: index.php?halaman=order&sukses="
            . urlencode(
                "Booking berhasil dibuat. Menunggu konfirmasi admin."
            )
    );

    exit();
} catch (Exception $e) {


    mysqli_rollback($koneksi);


    header(
        "Location: index.php?halaman=booking&error="
            . urlencode($e->getMessage())
    );

    exit();
}
