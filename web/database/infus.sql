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
  ('fonnte_token',        ''),
  ('wa_nurse_call_msg',   'NURSE CALL 🚨\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nSegera menuju lokasi pasien.'),
  ('wa_low_volume_msg',   'PERINGATAN INFUS ⚠️\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml ({persen}%)\nWaktu: {waktu}\n\nSegera ganti kantong infus.');

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
