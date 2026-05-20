<?php
// =====================================================
// KONFIGURASI DATABASE
// =====================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_infus');

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
