<?php
// =====================================================
// API: RIWAYAT DATA DEVICE
// GET /api/get_history.php?device_id=INFUS-01&limit=50
// =====================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/db.php';

$db        = getDB();
$device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : null;
$limit     = isset($_GET['limit'])     ? (int)$_GET['limit']     : 50;

if ($limit < 1 || $limit > 500) {
    $limit = 50;
}

if (!$device_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'device_id diperlukan']);
    exit;
}

$stmt = $db->prepare("
    SELECT
        id,
        tpm,
        volume_sisa,
        persen,
        estimasi_jam,
        estimasi_mnt,
        nurse_call,
        mode,
        created_at
    FROM infus_data
    WHERE device_id = :device_id
    ORDER BY created_at DESC
    LIMIT :limit
");

$stmt->bindValue(':device_id', $device_id, PDO::PARAM_STR);
$stmt->bindValue(':limit',     $limit,     PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();

// balik urutan supaya chart dari kiri ke kanan
$rows = array_reverse($rows);

echo json_encode([
    'status'    => 'ok',
    'device_id' => $device_id,
    'data'      => $rows,
    'total'     => count($rows),
]);
