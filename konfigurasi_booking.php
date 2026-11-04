<?php
/**
 * konfigurasi_booking.php
 * Pengaturan booking yang dipakai bersama oleh proses_booking.php dan cek_jam.php,
 * supaya tampilan (slot "Penuh") dan validasi server selalu sama.
 */

// Jumlah booking aktif maksimal per jam untuk satu bengkel.
const KUOTA_PER_JAM = 2;

// Status yang dihitung memakai slot jam.
const STATUS_AKTIF_BOOKING = ['Menunggu', 'Diterima', 'Diproses'];

// Jam operasional yang boleh dipilih (sesuai dropdown di form).
const JAM_OPERASIONAL = [8, 9, 10, 11, 13, 14, 15, 16];

// Batas jumlah layanan dalam satu booking.
const MAKS_LAYANAN_PER_BOOKING = 6;
