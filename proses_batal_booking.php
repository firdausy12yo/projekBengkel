<?php

session_start();
include 'koneksi.php';


// ================================
// CEK LOGIN USER
// ================================

if (!isset($_SESSION['id_pelanggan'])) {

    header(
        "Location: index.php?error="
            . urlencode("Silakan login terlebih dahulu.")
    );

    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: index.php");
    exit();
}


$id_pelanggan = (int) $_SESSION['id_pelanggan'];
$id_booking   = (int) ($_POST['id_booking'] ?? 0);


if ($id_booking <= 0) {

    header(
        "Location: index.php?error="
            . urlencode("Booking tidak valid.")
    );

    exit();
}


// ================================
// CEK BOOKING MILIK USER
// ================================

$stmt = $koneksi->prepare(
    "SELECT status_booking
     FROM booking
     WHERE id_booking = ?
     AND id_pelanggan = ?
     LIMIT 1"
);

$stmt->bind_param(
    "ii",
    $id_booking,
    $id_pelanggan
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    $stmt->close();

    header(
        "Location: index.php?error="
            . urlencode("Pesanan tidak ditemukan.")
    );

    exit();
}


$booking = $result->fetch_assoc();

$stmt->close();


// ================================
// CEK STATUS
// ================================

$status_boleh_dibatalkan = [
    'Menunggu',
    'Diterima'
];


if (
    !in_array(
        $booking['status_booking'],
        $status_boleh_dibatalkan,
        true
    )
) {

    header(
        "Location: index.php?error="
            . urlencode(
                "Pesanan sudah tidak dapat dibatalkan."
            )
    );

    exit();
}


// ================================
// UPDATE STATUS
// ================================

$status_baru = 'Dibatalkan';

$update = $koneksi->prepare(
    "UPDATE booking
     SET
        status_booking = ?,
        status_diubah_pada = NOW()
     WHERE id_booking = ?
     AND id_pelanggan = ?"
);

$update->bind_param(
    "sii",
    $status_baru,
    $id_booking,
    $id_pelanggan
);


if ($update->execute()) {

    $update->close();

    header(
        "Location: index.php?sukses="
            . urlencode("Pesanan berhasil dibatalkan.")
    );

    exit();
} else {

    $update->close();

    header(
        "Location: index.php?error="
            . urlencode("Gagal membatalkan pesanan.")
    );

    exit();
}
