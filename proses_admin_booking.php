<?php

session_start();
include 'koneksi.php';


/* ========================================
   CEK ADMIN
======================================== */

if (
    !isset($_SESSION['id_admin']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    header(
        "Location: index.php?error=Akses+ditolak!"
    );

    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: admin.php");

    exit();
}


$id_booking = (int)(
    $_POST['id_booking'] ?? 0
);

$aksi = $_POST['aksi'] ?? '';

$catatan_admin = trim(
    $_POST['catatan_admin'] ?? ''
);

$id_admin = (int)$_SESSION['id_admin'];


if ($id_booking <= 0) {

    header(
        "Location: admin.php?error=Booking+tidak+valid"
    );

    exit();
}


/* ========================================
   ATUR TRANSISI STATUS
======================================== */

$transisi = [

    'terima' => [
        'dari' => 'Menunggu',
        'ke'   => 'Diterima'
    ],

    'tolak' => [
        'dari' => 'Menunggu',
        'ke'   => 'Ditolak'
    ],

    'proses' => [
        'dari' => 'Diterima',
        'ke'   => 'Diproses'
    ],

    'selesai' => [
        'dari' => 'Diproses',
        'ke'   => 'Selesai'
    ]

];


if (!isset($transisi[$aksi])) {

    header(
        "Location: admin.php?error=Aksi+tidak+valid"
    );

    exit();
}


$status_dari =
    $transisi[$aksi]['dari'];

$status_baru =
    $transisi[$aksi]['ke'];


/* Tolak wajib memberikan alasan */

if (
    $aksi === 'tolak' &&
    $catatan_admin === ''
) {

    header(
        "Location: admin.php?error=Tuliskan+alasan+penolakan"
    );

    exit();
}


/* ========================================
   TRANSACTION
======================================== */

mysqli_begin_transaction($koneksi);


try {


    /* Ambil status terbaru */

    $cek = $koneksi->prepare(
        "SELECT status_booking
         FROM booking
         WHERE id_booking = ?
         FOR UPDATE"
    );

    $cek->bind_param(
        "i",
        $id_booking
    );

    $cek->execute();

    $result = $cek->get_result();


    if ($result->num_rows !== 1) {

        throw new Exception(
            "Pesanan tidak ditemukan."
        );
    }


    $booking = $result->fetch_assoc();


    /*
     * Pastikan status tidak boleh dilompati.
     *
     * Contoh:
     * Menunggu tidak bisa langsung Selesai.
     */

    if (
        $booking['status_booking']
        !== $status_dari
    ) {

        throw new Exception(
            "Status pesanan sudah berubah."
        );
    }


    /* Jika bukan ditolak, catatan admin opsional */

    if (
        $aksi !== 'tolak' &&
        $catatan_admin === ''
    ) {

        $catatan_admin = null;
    }


    $update = $koneksi->prepare(
        "UPDATE booking
         SET
            status_booking = ?,
            catatan_admin = ?,
            status_diubah_pada = NOW(),
            id_admin_terakhir = ?
         WHERE id_booking = ?"
    );


    $update->bind_param(
        "ssii",
        $status_baru,
        $catatan_admin,
        $id_admin,
        $id_booking
    );


    if (!$update->execute()) {

        throw new Exception(
            "Gagal mengubah status."
        );
    }


    mysqli_commit($koneksi);


    header(
        "Location: admin.php?sukses="
            . urlencode(
                "Pesanan berhasil diubah menjadi "
                    . $status_baru
            )
    );

    exit();
} catch (Exception $e) {


    mysqli_rollback($koneksi);


    header(
        "Location: admin.php?error="
            . urlencode(
                $e->getMessage()
            )
    );

    exit();
}
