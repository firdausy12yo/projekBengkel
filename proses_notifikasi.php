<?php
session_start();
require 'koneksi.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_SESSION['id_pelanggan'])
) {
    header('Location: index.php');
    exit;
}

$token = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    !isset($_SESSION['csrf_notifikasi']) ||
    !hash_equals($_SESSION['csrf_notifikasi'], $token)
) {
    http_response_code(403);
    exit('Form tidak valid. Refresh halaman dan coba lagi.');
}

$idPelanggan = (int) $_SESSION['id_pelanggan'];
$idNotif = (int) ($_POST['id_notifikasi'] ?? 0);
$aksi = $_POST['aksi'] ?? '';

$tujuan = 'index.php?halaman=notif';

// Tandai semua notifikasi sudah dibaca
if ($aksi === 'baca_semua') {

    $stmt = $koneksi->prepare(
        'UPDATE notifikasi
         SET sudah_dibaca = 1
         WHERE id_pelanggan = ?
         AND dihapus = 0'
    );

    $stmt->bind_param('i', $idPelanggan);
    $stmt->execute();
    $stmt->close();
} elseif (
    $idNotif > 0 &&
    in_array($aksi, ['baca', 'hapus', 'lihat'], true)
) {

    if ($aksi === 'lihat') {

        // Cari notifikasi milik akun yang login
        $stmt = $koneksi->prepare(
            'SELECT id_booking
             FROM notifikasi
             WHERE id_notifikasi = ?
             AND id_pelanggan = ?
             AND dihapus = 0'
        );

        $stmt->bind_param('ii', $idNotif, $idPelanggan);
        $stmt->execute();

        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($data) {

            // Tandai notifikasi sebagai dibaca
            $stmt = $koneksi->prepare(
                'UPDATE notifikasi
                 SET sudah_dibaca = 1
                 WHERE id_notifikasi = ?
                 AND id_pelanggan = ?
                 AND dihapus = 0'
            );

            $stmt->bind_param('ii', $idNotif, $idPelanggan);
            $stmt->execute();
            $stmt->close();

            if (!empty($data['id_booking'])) {
                $tujuan = 'index.php?halaman=order&pesanan_id='
                    . (int) $data['id_booking'];
            }
        }
    } else {

        // Hanya dua kolom ini yang boleh diubah
        $kolom = $aksi === 'baca' ? 'sudah_dibaca' : 'dihapus';

        $stmt = $koneksi->prepare(
            "UPDATE notifikasi
             SET $kolom = 1
             WHERE id_notifikasi = ?
             AND id_pelanggan = ?
             AND dihapus = 0"
        );

        $stmt->bind_param('ii', $idNotif, $idPelanggan);
        $stmt->execute();
        $stmt->close();
    }
}

header('Location: ' . $tujuan);
exit;
