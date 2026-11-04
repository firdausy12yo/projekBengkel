<?php
/**
 * cek_jam.php
 * Mengembalikan daftar jam yang sudah penuh untuk tanggal + bengkel tertentu.
 * Dipakai form booking di index.php untuk menandai slot "(Penuh)".
 *
 * Contoh: cek_jam.php?tanggal=2026-11-10&bengkel=PitStop+Mitra+-+Bandung
 * Balasan: {"penuh":[9,10]}
 */
session_start();
include 'koneksi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Hanya untuk pelanggan yang sedang login
if (!isset($_SESSION['id_pelanggan'])) {
    http_response_code(401);
    echo json_encode(['penuh' => []]);
    exit;
}

// KUOTA_PER_JAM & STATUS_AKTIF_BOOKING diatur di konfigurasi_booking.php
require_once __DIR__ . '/konfigurasi_booking.php';

$tanggal = $_GET['tanggal'] ?? '';
$bengkel = trim($_GET['bengkel'] ?? '');

$tglValid = DateTime::createFromFormat('Y-m-d', $tanggal);

if (!$tglValid || $tglValid->format('Y-m-d') !== $tanggal || $bengkel === '') {
    http_response_code(400);
    echo json_encode(['penuh' => []]);
    exit;
}

$statusAktif = STATUS_AKTIF_BOOKING;
$placeholder = implode(',', array_fill(0, count($statusAktif), '?'));

$stmt = $koneksi->prepare(
    "SELECT jam_booking, COUNT(*) AS total
     FROM booking
     WHERE tanggal_booking = ?
       AND nama_bengkel = ?
       AND status_booking IN ($placeholder)
     GROUP BY jam_booking
     HAVING COUNT(*) >= ?"
);

$kuota = KUOTA_PER_JAM;
$params = array_merge([$tanggal, $bengkel], $statusAktif, [$kuota]);
$stmt->bind_param('ss' . str_repeat('s', count($statusAktif)) . 'i', ...$params);
$stmt->execute();
$hasil = $stmt->get_result();

$penuh = [];
while ($row = $hasil->fetch_assoc()) {
    $penuh[] = (int) $row['jam_booking'];
}

$stmt->close();

echo json_encode(['penuh' => $penuh]);
