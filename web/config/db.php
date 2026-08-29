<?php
// =====================================================
// KONFIGURASI DATABASE & ENVIRONMENT
// =====================================================

// Set timezone PHP ke WIB (Asia/Jakarta = UTC+7)
date_default_timezone_set('Asia/Jakarta');

if (!function_exists('loadEnv')) {
    /**
     * Helper untuk meload file .env secara mandiri
     */
    function loadEnv(string $path): void {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Abaikan komentar atau baris kosong
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            // Pisahkan key dan value
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                // Hapus tanda kutip jika ada
                $val = trim($val, '"\'');
                
                // Daftarkan ke environment PHP
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Cari file .env di level root proyek (rekomendasi) atau di level web/
if (file_exists(__DIR__ . '/../../.env')) {
    loadEnv(__DIR__ . '/../../.env');
} elseif (file_exists(__DIR__ . '/../.env')) {
    loadEnv(__DIR__ . '/../.env');
}

// Definisikan kredensial database dengan fallback ke nilai default lokal
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'smart_infus');


function getDB(): PDO {

    static $pdo = null;

    if ($pdo === null) {

        try {

            $dsn = 'mysql:host=' . DB_HOST
                 . ';dbname=' . DB_NAME
                 . ';charset=utf8mb4';

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Set timezone MySQL ke WIB (UTC+7)
            $pdo->exec("SET time_zone = '+07:00'");

        } catch (PDOException $e) {

            http_response_code(500);
            die(json_encode([
                'status'  => 'error',
                'message' => 'Koneksi database gagal: ' . $e->getMessage()
            ]));
        }
    }

    return $pdo;
}
