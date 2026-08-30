<?php
// =====================================================
// API: DATA UNTUK HALAMAN MONITOR KELUARGA
// GET /api/get_monitor.php?token=xxx[&history=1&limit=50]
// Tidak butuh login — dilindungi family_token per device
// =====================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token tidak valid']);
    exit;
}

$db = getDB();

// Validasi token
$devStmt = $db->prepare("SELECT * FROM devices WHERE family_token = :token AND aktif = 1 LIMIT 1");
$devStmt->execute([':token' => $token]);
$device = $devStmt->fetch();

if (!$device) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token tidak ditemukan atau perangkat tidak aktif']);
    exit;
}

$device_id = $device['device_id'];

// Jika diminta history
if (!empty($_GET['history'])) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    if ($limit < 1 || $limit > 200) $limit = 50;

    $stmt = $db->prepare("
        SELECT tpm, volume_sisa, persen, estimasi_jam, estimasi_mnt, nurse_call, mode, created_at
        FROM infus_data
        WHERE device_id = :device_id
        ORDER BY created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':device_id', $device_id, PDO::PARAM_STR);
    $stmt->bindValue(':limit',     $limit,     PDO::PARAM_INT);
    $stmt->execute();
    $rows = array_reverse($stmt->fetchAll());

    echo json_encode([
        'status'    => 'ok',
        'device_id' => $device_id,
        'pasien'    => $device['pasien'],
        'data'      => $rows,
        'total'     => count($rows),
    ]);
    exit;
}

// Jika diminta riwayat nurse call
if (!empty($_GET['nurse_log'])) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if ($limit < 1 || $limit > 50) $limit = 10;

    $stmt = $db->prepare("
        SELECT id, status, created_at, resolved_at, resolved_by
        FROM nurse_call_log
        WHERE device_id = :device_id
        ORDER BY created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':device_id', $device_id, PDO::PARAM_STR);
    $stmt->bindValue(':limit',     $limit,     PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo json_encode([
        'status' => 'ok',
        'data'   => $rows,
        'total'  => count($rows),
    ]);
    exit;
}

// Data terbaru saja
$stmt = $db->prepare("
    SELECT
        d.device_id, d.nama, d.lokasi, d.pasien,
        COALESCE(d.target_tpm, 20) AS target_tpm,
        COALESCE(d.tpm_tolerance, 5) AS tpm_tolerance,
        i.tpm, i.volume_sisa, i.volume_awal, i.persen,
        i.estimasi_jam, i.estimasi_mnt, i.total_tetes,
        i.nurse_call, i.mode, i.created_at
    FROM devices d
    LEFT JOIN infus_data i
        ON i.id = (
            SELECT id FROM infus_data
            WHERE device_id = d.device_id
            ORDER BY created_at DESC
            LIMIT 1
        )
    WHERE d.device_id = :device_id
");
$stmt->execute([':device_id' => $device_id]);
$result = $stmt->fetch();

if ($result) {
    $isOnline = false;
    if (!empty($result['created_at'])) {
        $isOnline = (time() - strtotime($result['created_at'])) < 30;
    }
    $result['is_online'] = $isOnline;
    $tgt = (int)$result['target_tpm'];
    $tol = (int)$result['tpm_tolerance'];
    $result['tpm_min'] = max(1, $tgt - $tol);
    $result['tpm_max'] = $tgt + $tol;
    // Hapus info sensitif — keluarga tidak perlu tahu device_id teknis
    unset($result['device_id']);
}

echo json_encode([
    'status' => 'ok',
    'data'   => $result ?: null,
]);
