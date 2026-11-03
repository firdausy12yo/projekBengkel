-- =========================================================
-- PATCH VERIFIKASI EMAIL OTP - PitStop.id
-- Jalankan SATU KALI di phpMyAdmin pada database db_bengkel.
-- Akun lama tetap dianggap terverifikasi.
-- Akun baru yang dibuat melalui proses_daftar.php wajib OTP.
-- =========================================================

USE db_bengkel;

ALTER TABLE pelanggan
    ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 1
        AFTER password_hash,
    ADD COLUMN IF NOT EXISTS otp_hash VARCHAR(255) NULL
        AFTER email_verified,
    ADD COLUMN IF NOT EXISTS otp_expired_at DATETIME NULL
        AFTER otp_hash,
    ADD COLUMN IF NOT EXISTS otp_last_sent_at DATETIME NULL
        AFTER otp_expired_at,
    ADD COLUMN IF NOT EXISTS otp_attempts INT NOT NULL DEFAULT 0
        AFTER otp_last_sent_at;

-- Sesudah kolom dibuat, default akun BARU diubah menjadi belum terverifikasi.
-- Nilai akun lama yang sudah mendapat 1 tidak berubah.
ALTER TABLE pelanggan
    MODIFY COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0;
