<?php
// =====================================================
// API: AMBIL DATA TERBARU SEMUA DEVICE
// GET /api/get_latest.php
// GET /api/get_latest.php?device_id=INFUS-01
// =====================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/db.php';

$db        = getDB();
$device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;

if ($device_id) {

    // data terbaru 1 device
    $stmt = $db->prepare("
        SELECT
            d.device_id,
            d.nama,
            d.lokasi,
            d.pasien,
            i.tpm,
            i.volume_sisa,
            i.volume_awal,
            i.persen,
            i.estimasi_jam,
            i.estimasi_mnt,
            i.total_tetes,
            i.nurse_call,
            i.mode,
            i.created_at
        FROM devices d
        LEFT JOIN infus_data i
            ON i.id = (
                SELECT id FROM infus_data
                WHERE device_id = d.device_id
                ORDER BY created_at DESC
                LIMIT 1
            )
        WHERE d.device_id = :device_id
          AND d.aktif = 1
    ");
    $stmt->execute([':device_id' => $device_id]);
    $result = $stmt->fetch();

    echo json_encode([
        'status' => 'ok',
        'data'   => $result ?: null,
    ]);

} else {

    // semua device aktif + data terbaru
    $stmt = $db->query("
        SELECT
            d.device_id,
            d.nama,
            d.lokasi,
            d.pasien,
            i.tpm,
            i.volume_sisa,
            i.volume_awal,
            i.persen,
            i.estimasi_jam,
            i.estimasi_mnt,
            i.total_tetes,
            i.nurse_call,
            i.mode,
            i.created_at
        FROM devices d
        LEFT JOIN infus_data i
            ON i.id = (
                SELECT id FROM infus_data
                WHERE device_id = d.device_id
                ORDER BY created_at DESC
                LIMIT 1
            )
        WHERE d.aktif = 1
        ORDER BY d.id ASC
    ");

    $rows = $stmt->fetchAll();

    echo json_encode([
        'status' => 'ok',
        'data'   => $rows,
        'total'  => count($rows),
    ]);
}
