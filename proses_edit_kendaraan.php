<?php
session_start();
require_once __DIR__ . '/koneksi.php';

function kembaliEditKendaraan(string $jenis, string $pesan): void {
    header('Location: index.php?halaman=account&' . $jenis . '=' . urlencode($pesan));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['id_pelanggan'])) {
    kembaliEditKendaraan('error', 'Silakan login terlebih dahulu.');
}
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !isset($_SESSION['csrf_kendaraan']) ||
    !hash_equals($_SESSION['csrf_kendaraan'], $token)) {
    kembaliEditKendaraan('error', 'Sesi form tidak valid. Muat ulang halaman.');
}
$id = filter_input(INPUT_POST, 'id_kendaraan', FILTER_VALIDATE_INT);
$namaMerk = trim((string)($_POST['merk'] ?? ''));
$model = trim((string)($_POST['model'] ?? ''));
$nopol = strtoupper(trim((string)($_POST['nopol'] ?? '')));
$tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
if (!$id || $id < 1 || !$tahun || $tahun < 1900 || $tahun > ((int)date('Y') + 1) ||
    $namaMerk === '' || $model === '' || $nopol === '' ||
    strlen($namaMerk) > 100 || strlen($model) > 100 || strlen($nopol) > 20) {
    kembaliEditKendaraan('error', 'Data kendaraan tidak lengkap atau tidak valid.');
}
$idPelanggan = (int)$_SESSION['id_pelanggan'];
$tahunKeluar = sprintf('%04d-01-01', $tahun);
try {
    // Cek kendaraan memang milik akun yang sedang login dan masih aktif.
    $cek = $koneksi->prepare('SELECT no_plat FROM kendaraan WHERE id_kendaraan=? AND id_pelanggan=? AND is_active=1 LIMIT 1');
    $cek->bind_param('ii', $id, $idPelanggan);
    $cek->execute();
    $dataLama = $cek->get_result()->fetch_assoc();
    $cek->close();
    if (!$dataLama) {
        kembaliEditKendaraan('error', 'Kendaraan tidak ditemukan pada akun kamu.');
    }
    // Nomor polisi yang sudah dipakai booking tidak boleh berubah agar riwayat tetap konsisten.
    if ($nopol !== $dataLama['no_plat']) {
        $cekRiwayat = $koneksi->prepare('SELECT id_booking FROM booking WHERE id_kendaraan=? LIMIT 1');
        $cekRiwayat->bind_param('i', $id);
        $cekRiwayat->execute();
        $punyaRiwayat = (bool)$cekRiwayat->get_result()->fetch_assoc();
        $cekRiwayat->close();
        if ($punyaRiwayat) {
            kembaliEditKendaraan('error', 'Nomor polisi tidak bisa diganti karena kendaraan sudah memiliki riwayat booking.');
        }
    }
    $stmt = $koneksi->prepare('UPDATE kendaraan SET merk=?, model=?, tahun_keluar=?, no_plat=? WHERE id_kendaraan=? AND id_pelanggan=? AND is_active=1');
    $stmt->bind_param('ssssii', $namaMerk, $model, $tahunKeluar, $nopol, $id, $idPelanggan);
    $stmt->execute();
    $stmt->close();
    kembaliEditKendaraan('sukses', 'Data kendaraan berhasil diperbarui.');
} catch (Throwable $e) {
    error_log('Edit kendaraan: ' . $e->getMessage());
    kembaliEditKendaraan('error', 'Gagal menyimpan edit kendaraan. Periksa apakah nomor polisi sudah digunakan.');
}
