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

$idKendaraan = filter_input(INPUT_POST, 'id_kendaraan', FILTER_VALIDATE_INT);
if (!$idKendaraan || $idKendaraan < 1) {
    kembali('error', 'Kendaraan tidak valid.');
}

$idPelanggan = (int) $_SESSION['id_pelanggan'];
try {
    // Soft delete: tetap simpan data kendaraan untuk riwayat booking dan relasi foreign key.
    $stmt = $koneksi->prepare(
        'UPDATE kendaraan SET is_active = 0
         WHERE id_kendaraan = ? AND id_pelanggan = ? AND is_active = 1'
    );
    $stmt->bind_param('ii', $idKendaraan, $idPelanggan);
    $stmt->execute();
    $berhasil = $stmt->affected_rows === 1;
    $stmt->close();
    kembali($berhasil ? 'sukses' : 'error',
        $berhasil ? 'Kendaraan dihapus dari daftar. Riwayat booking tetap aman.' : 'Kendaraan tidak ditemukan pada akunmu.');
} catch (Throwable $e) {
    error_log('Arsip kendaraan gagal: ' . $e->getMessage());
    kembali('error', 'Gagal menghapus kendaraan. Silakan coba lagi.');
}
