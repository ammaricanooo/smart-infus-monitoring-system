<?php
// =====================================================
// API: LOG NURSE CALL
// GET /api/get_nurse_log.php?limit=20
// =====================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/db.php';

$db    = getDB();
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($limit < 1 || $limit > 200) {
    $limit = 20;
}

$stmt = $db->prepare("
    SELECT
        n.id,
        n.device_id,
        n.status,
        n.resolved_at,
        n.resolved_by,
        d.nama,
        d.lokasi,
        d.pasien,
        n.created_at
    FROM nurse_call_log n
    LEFT JOIN devices d ON d.device_id = n.device_id
    ORDER BY n.created_at DESC
    LIMIT :limit
");

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll();

echo json_encode([
    'status' => 'ok',
    'data'   => $rows,
    'total'  => count($rows),
]);
