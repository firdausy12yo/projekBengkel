<?php
session_start();
require_once __DIR__ . '/koneksi.php';

function kembali($jenis, $pesan) {
    header('Location: index.php?halaman=account&' . $jenis . '=' . urlencode($pesan));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['id_pelanggan'])) {
    kembali('error', 'Silakan login terlebih dahulu.');
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !isset($_SESSION['csrf_kendaraan']) ||
    !hash_equals($_SESSION['csrf_kendaraan'], $token)) {
    kembali('error', 'Permintaan tidak valid. Muat ulang halaman dan coba lagi.');
}
$merk = trim($_POST['merk'] ?? '');
$model = trim($_POST['model'] ?? '');
$nopol = strtoupper(trim($_POST['nopol'] ?? ''));
$tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
if ($merk === '' || $model === '' || $nopol === '' || !$tahun ||
    $tahun < 1900 || $tahun > ((int)date('Y') + 1) ||
    strlen($merk) > 100 || strlen($model) > 100 || strlen($nopol) > 20) {
    kembali('error', 'Data kendaraan belum lengkap atau tidak valid.');
}
$idPelanggan = (int) $_SESSION['id_pelanggan'];
$tahunKeluar = sprintf('%04d-01-01', $tahun);
try {
    $cek = $koneksi->prepare('SELECT id_kendaraan, id_pelanggan, is_active FROM kendaraan WHERE no_plat=? LIMIT 1');
    $cek->bind_param('s', $nopol);
    $cek->execute();
    $hasil = $cek->get_result()->fetch_assoc();
    $cek->close();
    if ($hasil) {
        if ((int)$hasil['id_pelanggan'] !== $idPelanggan) {
            kembali('error', 'Nomor polisi sudah terdaftar di akun lain.');
        }
        if ((int)$hasil['is_active'] === 1) {
            kembali('error', 'Kendaraan dengan nomor polisi ini sudah ada di profilmu.');
        }
        $idKendaraan = (int)$hasil['id_kendaraan'];
        $stmt = $koneksi->prepare('UPDATE kendaraan SET merk=?, model=?, tahun_keluar=?, is_active=1 WHERE id_kendaraan=? AND id_pelanggan=?');
        $stmt->bind_param('sssii', $merk, $model, $tahunKeluar, $idKendaraan, $idPelanggan);
    } else {
        $stmt = $koneksi->prepare('INSERT INTO kendaraan (id_pelanggan,no_plat,merk,model,tahun_keluar,is_active) VALUES (?,?,?,?,?,1)');
        $stmt->bind_param('issss', $idPelanggan, $nopol, $merk, $model, $tahunKeluar);
    }
    $stmt->execute();
    $stmt->close();
    kembali('sukses', 'Kendaraan berhasil disimpan.');
} catch (Throwable $e) {
    error_log('Tambah kendaraan gagal: ' . $e->getMessage());
    kembali('error', 'Gagal menyimpan kendaraan. Coba lagi.');
}
