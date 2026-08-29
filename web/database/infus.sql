-- =====================================================
-- DATABASE SMART INFUS
-- =====================================================

CREATE DATABASE IF NOT EXISTS `smart_infus`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `smart_infus`;

-- =====================================================
-- TABLE: devices
-- =====================================================

CREATE TABLE IF NOT EXISTS `devices` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `device_id`    VARCHAR(50)  NOT NULL UNIQUE,
  `nama`         VARCHAR(100) NOT NULL DEFAULT 'Infus',
  `lokasi`       VARCHAR(100) NOT NULL DEFAULT '-',
  `pasien`       VARCHAR(100) NOT NULL DEFAULT '-',
  `no_suster`    VARCHAR(20)  NOT NULL DEFAULT '',
  `no_keluarga`  VARCHAR(20)  NOT NULL DEFAULT '',
  `aktif`        TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: settings (konfigurasi sistem)
-- =====================================================

CREATE TABLE IF NOT EXISTS `settings` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `key_name`    VARCHAR(100) NOT NULL UNIQUE,
  `key_value`   TEXT         NOT NULL DEFAULT '',
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data default settings
INSERT IGNORE INTO `settings` (`key_name`, `key_value`) VALUES
  ('wa_api_url',          ''),
  ('wa_api_key',          ''),
  ('fonnte_token',        ''),
  ('wa_nurse_call_msg',   'NURSE CALL 🚨\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nSegera menuju lokasi pasien.'),
  ('wa_low_volume_msg',   'PERINGATAN INFUS ⚠️\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml ({persen}%)\nWaktu: {waktu}\n\nSegera ganti kantong infus.'),
  ('wa_tpm_zero_msg',     'INFUS MACET 🔴\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml\nWaktu: {waktu}\n\nTidak ada tetesan terdeteksi. Periksa selang atau jarum infus segera.'),
  ('wa_tpm_high_msg',     'TPM TERLALU CEPAT ⚡\nPasien: {pasien}\nLokasi: {lokasi}\nTPM saat ini: {tpm} tetes/menit\nWaktu: {waktu}\n\nKecepatan tetesan infus terlalu cepat. Harap periksa dan sesuaikan pengaturan.'),
  ('wa_resolved_msg',     'KONDISI NORMAL ✅\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nKabar baik! {resolved_label}. Tidak perlu khawatir.');

-- =====================================================
-- TABLE: infus_data (data realtime dari ESP32)
-- =====================================================

CREATE TABLE IF NOT EXISTS `infus_data` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `device_id`    VARCHAR(50)  NOT NULL,
  `tpm`          FLOAT        NOT NULL DEFAULT 0,
  `volume_sisa`  FLOAT        NOT NULL DEFAULT 0,
  `volume_awal`  FLOAT        NOT NULL DEFAULT 500,
  `persen`       FLOAT        NOT NULL DEFAULT 0,
  `estimasi_jam` INT(11)      NOT NULL DEFAULT 0,
  `estimasi_mnt` INT(11)      NOT NULL DEFAULT 0,
  `total_tetes`  INT(11)      NOT NULL DEFAULT 0,
  `nurse_call`   TINYINT(1)   NOT NULL DEFAULT 0,
  `mode`         VARCHAR(20)  NOT NULL DEFAULT '500ml',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- TABLE: nurse_call_log
-- =====================================================

CREATE TABLE IF NOT EXISTS `nurse_call_log` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `device_id`   VARCHAR(50)  NOT NULL,
  `status`      TINYINT(1)   NOT NULL DEFAULT 1,
  `resolved_at` TIMESTAMP    NULL DEFAULT NULL,
  `resolved_by` VARCHAR(20)  NOT NULL DEFAULT '',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ncl_device_id` (`device_id`),
  KEY `idx_ncl_status`    (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- MIGRATION: tambahkan kolom resolved_at & resolved_by
-- Jalankan jika tabel sudah ada sebelumnya:
-- ALTER TABLE `nurse_call_log`
--   ADD COLUMN `resolved_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`,
--   ADD COLUMN `resolved_by` VARCHAR(20) NOT NULL DEFAULT '' AFTER `resolved_at`;
-- =====================================================

-- =====================================================
-- DATA AWAL
-- =====================================================

INSERT INTO `devices` (`device_id`, `nama`, `lokasi`, `pasien`) VALUES
  ('INFUS-01', 'Infus Bed 1', 'Ruang Mawar', 'Pasien A'),
  ('INFUS-02', 'Infus Bed 2', 'Ruang Melati', 'Pasien B'),
  ('INFUS-03', 'Infus Bed 3', 'Ruang Anggrek', 'Pasien C');

-- =====================================================
-- MIGRATION: jalankan jika tabel devices sudah ada
-- =====================================================
-- ALTER TABLE `devices`
--   ADD COLUMN `no_suster`   VARCHAR(20) NOT NULL DEFAULT '' AFTER `pasien`,
--   ADD COLUMN `no_keluarga` VARCHAR(20) NOT NULL DEFAULT '' AFTER `no_suster`;
--
-- CREATE TABLE IF NOT EXISTS `settings` (
--   `id`         INT(11)      NOT NULL AUTO_INCREMENT,
--   `key_name`   VARCHAR(100) NOT NULL UNIQUE,
--   `key_value`  TEXT         NOT NULL DEFAULT '',
--   `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
--   PRIMARY KEY (`id`)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- INSERT IGNORE INTO `settings` (`key_name`, `key_value`) VALUES
--   ('fonnte_token',        ''),
--   ('wa_nurse_call_msg',   'NURSE CALL 🚨\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nSegera menuju lokasi pasien.'),
--   ('wa_low_volume_msg',   'PERINGATAN INFUS ⚠️\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml ({persen}%)\nWaktu: {waktu}\n\nSegera ganti kantong infus.');
-- =====================================================

-- =====================================================
-- MIGRATION: users & new settings (auth system v2)
-- =====================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `nama`       VARCHAR(100) NOT NULL DEFAULT '',
  `role`       ENUM('superadmin','admin','nurse') NOT NULL DEFAULT 'nurse',
  `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Superadmin default (password: admin123)
INSERT IGNORE INTO `users` (`username`, `password`, `nama`, `role`) VALUES
  ('superadmin', '$2y$10$aqs.JSQ6D/s2Ezjk7WUOGuXTaJcdRSNOKxx64I1UP0rjj3I.RlakK', 'Super Administrator', 'superadmin');

-- Settings baru: login_required + template WA suster vs keluarga
INSERT IGNORE INTO `settings` (`key_name`, `key_value`) VALUES
  ('login_required',            '0'),
  ('wa_nurse_call_msg_suster',  'NURSE CALL 🚨\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nSegera menuju lokasi pasien.'),
  ('wa_nurse_call_msg_keluarga','PEMBERITAHUAN 🔔\nPasien {pasien} di {lokasi} membutuhkan bantuan perawat.\nWaktu: {waktu}\n\nTim medis sedang menuju lokasi.'),
  ('wa_low_volume_msg_suster',  'PERINGATAN INFUS ⚠️\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml ({persen}%)\nWaktu: {waktu}\n\nSegera ganti kantong infus.'),
  ('wa_low_volume_msg_keluarga','INFO INFUS ℹ️\nCairan infus {pasien} di {lokasi} hampir habis ({persen}%).\nWaktu: {waktu}\n\nTim medis sedang menangani.'),
  ('wa_tpm_zero_msg_suster',    'INFUS MACET 🔴\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml\nWaktu: {waktu}\n\nTidak ada tetesan terdeteksi. Periksa selang segera.'),
  ('wa_tpm_zero_msg_keluarga',  'INFO TEKNIS ℹ️\nPerangkat infus {pasien} di {lokasi} mendeteksi anomali.\nWaktu: {waktu}\n\nTim medis sedang menangani.'),
  ('wa_tpm_high_msg_suster',    'TPM TERLALU CEPAT ⚡\nPasien: {pasien}\nLokasi: {lokasi}\nTPM: {tpm} tetes/menit\nWaktu: {waktu}\n\nHarap periksa dan sesuaikan pengaturan.'),
  ('wa_tpm_high_msg_keluarga',  'INFO TEKNIS ℹ️\nPerangkat infus {pasien} di {lokasi} membutuhkan penyesuaian.\nWaktu: {waktu}\n\nTim medis sedang menangani.'),
  ('wa_resolved_msg_keluarga',  'KONDISI NORMAL ✅\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nKabar baik! {resolved_label}. Tidak perlu khawatir.');

-- =====================================================
-- MIGRATION: USERS TABLE + NEW SETTINGS
-- Jalankan setelah schema awal sudah ada
-- (atau jalankan full SQL dari awal untuk fresh install)
-- =====================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `nama`       VARCHAR(100) NOT NULL DEFAULT '',
  `role`       ENUM('superadmin','admin','nurse') NOT NULL DEFAULT 'nurse',
  `aktif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Superadmin default (password: admin123)
INSERT IGNORE INTO `users` (`username`, `password`, `nama`, `role`) VALUES
  ('superadmin', '$2y$10$aqs.JSQ6D/s2Ezjk7WUOGuXTaJcdRSNOKxx64I1UP0rjj3I.RlakK', 'Super Administrator', 'superadmin');

-- Settings baru
INSERT IGNORE INTO `settings` (`key_name`, `key_value`) VALUES
  ('login_required',             '0'),
  ('wa_nurse_call_msg_suster',   'NURSE CALL 🚨\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nSegera menuju lokasi pasien.'),
  ('wa_nurse_call_msg_keluarga', 'PEMBERITAHUAN 🔔\nPasien {pasien} di {lokasi} membutuhkan bantuan perawat.\nWaktu: {waktu}\n\nTim medis sedang menuju lokasi.'),
  ('wa_low_volume_msg_suster',   'PERINGATAN INFUS ⚠️\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml ({persen}%)\nWaktu: {waktu}\n\nSegera ganti kantong infus.'),
  ('wa_low_volume_msg_keluarga', 'INFO INFUS ℹ️\nCairan infus {pasien} di {lokasi} hampir habis ({persen}%).\nWaktu: {waktu}\n\nTim medis sedang menangani.'),
  ('wa_tpm_zero_msg_suster',     'INFUS MACET 🔴\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml\nWaktu: {waktu}\n\nTidak ada tetesan terdeteksi. Periksa selang segera.'),
  ('wa_tpm_zero_msg_keluarga',   'INFO TEKNIS ℹ️\nPerangkat infus {pasien} di {lokasi} mendeteksi anomali.\nWaktu: {waktu}\n\nTim medis sedang menangani.'),
  ('wa_tpm_high_msg_suster',     'TPM TERLALU CEPAT ⚡\nPasien: {pasien}\nLokasi: {lokasi}\nTPM: {tpm} tetes/menit\nWaktu: {waktu}\n\nHarap periksa dan sesuaikan pengaturan.'),
  ('wa_tpm_high_msg_keluarga',   'INFO TEKNIS ℹ️\nPerangkat infus {pasien} di {lokasi} membutuhkan penyesuaian.\nWaktu: {waktu}\n\nTim medis sedang menangani.'),
  ('wa_resolved_msg_keluarga',   'KONDISI NORMAL ✅\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nKabar baik! {resolved_label}. Tidak perlu khawatir.');

-- =====================================================
-- MIGRATION: wa_provider (dual gateway support)
-- Tambahkan setting ini jika belum ada
-- =====================================================
INSERT IGNORE INTO `settings` (`key_name`, `key_value`) VALUES
  ('wa_provider', 'custom');

-- Untuk instalasi lama yang sudah pakai fonnte_token dan
-- belum punya wa_api_url, jalankan query ini untuk migrasi
-- otomatis ke provider fonnte:
-- UPDATE `settings` SET `key_value` = 'fonnte'
--   WHERE `key_name` = 'wa_provider'
--     AND EXISTS (SELECT 1 FROM (SELECT key_value FROM settings WHERE key_name = 'fonnte_token') t WHERE t.key_value != '')
--     AND NOT EXISTS (SELECT 1 FROM (SELECT key_value FROM settings WHERE key_name = 'wa_api_url') t WHERE t.key_value != '');

-- =====================================================
-- MIGRATION: family_token + new settings (v3)
-- Tambah kolom family_token ke devices (link monitor keluarga)
-- =====================================================

-- Tambah kolom family_token jika belum ada:
-- ALTER TABLE `devices`
--   ADD COLUMN `family_token` VARCHAR(64) NOT NULL DEFAULT '' AFTER `no_keluarga`;

-- Generate token unik untuk semua device yang ada:
-- UPDATE devices SET family_token = LOWER(REPLACE(UUID(), '-', '')) WHERE family_token = '';

-- Settings baru (v3)
INSERT IGNORE INTO `settings` (`key_name`, `key_value`) VALUES
  ('wa_resolved_msg_suster',  'KONDISI NORMAL ✅\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\n{resolved_label}. Tidak perlu menuju ruangan.'),
  ('wa_welcome_keluarga',     'Halo! 👋\nAnda terdaftar sebagai kontak keluarga untuk pasien *{pasien}* di *{lokasi}*.\n\nAnda bisa memantau kondisi infus secara langsung melalui tautan berikut:\n{monitor_url}\n\n_Tautan ini khusus untuk Anda. Jangan bagikan ke orang lain._'),
  ('app_url',                 '');
