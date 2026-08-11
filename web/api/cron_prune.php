<?php
// =====================================================
// CRON JOB: PEMBERSIHAN & OPTIMASI DATABASE
// Jalankan via CLI: php cron_prune.php
// Atau via Web: http://your-domain/api/cron_prune.php?key=YOUR_SECRET_KEY
// =====================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';

// Batasi akses web hanya menggunakan API Key superadmin jika diakses melalui HTTP
if (php_sapi_name() !== 'cli') {
    $expectedKey = getSetting('iot_api_key', '');
    
    if (!empty($expectedKey)) {
        $providedKey = $_GET['key'] ?? '';
        if (empty($providedKey) || !hash_equals($expectedKey, $providedKey)) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Akses ditolak']);
            exit;
        }
    }
}

try {
    $db = getDB();
    
    // Ambil batas hari retensi dari pengaturan database
    $infusDays = (int)getSetting('db_infus_data_retention', '3');
    $logDays   = (int)getSetting('db_nurse_log_retention', '7');
    
    $deletedInfus = 0;
    $deletedNurse = 0;
    
    // 1. Bersihkan data infus lama
    if ($infusDays > 0) {
        $pruneInfus = $db->prepare("DELETE FROM infus_data WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $pruneInfus->execute([':days' => $infusDays]);
        $deletedInfus = $pruneInfus->rowCount();
    }
    
    // 2. Bersihkan log nurse call yang sudah selesai (status = 0)
    if ($logDays > 0) {
        $pruneNurse = $db->prepare("DELETE FROM nurse_call_log WHERE status = 0 AND resolved_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $pruneNurse->execute([':days' => $logDays]);
        $deletedNurse = $pruneNurse->rowCount();
    }
    
    // 3. Optimasi fisik tabel MySQL
    $db->query("OPTIMIZE TABLE infus_data");
    $db->query("OPTIMIZE TABLE nurse_call_log");
    
    $message = "Database pembersihan sukses! Terhapus: {$deletedInfus} data infus, {$deletedNurse} log nurse call.";
    
    // Output format berdasarkan SAPI
    if (php_sapi_name() === 'cli') {
        echo "[INFO] [" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'message' => $message,
            'details' => [
                'deleted_infus_data' => $deletedInfus,
                'deleted_nurse_call_log' => $deletedNurse,
                'infus_retention_days' => $infusDays,
                'nurse_retention_days' => $logDays
            ]
        ]);
    }
} catch (\Throwable $e) {
    $errMessage = "Gagal menjalankan cron database pruning: " . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        echo "[ERROR] [" . date('Y-m-d H:i:s') . "] " . $errMessage . "\n";
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $errMessage]);
    }
}
