<?php
// =====================================================
// API: KIRIM NOTIFIKASI WHATSAPP
// POST /api/send_whatsapp.php
// Dipanggil internal dari post_data.php
// Body JSON: { "device_id": "...", "type": "nurse_call"|"low_volume", "volume": 8, "persen": 5 }
// =====================================================

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/whatsapp.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['device_id']) || empty($data['type'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
    exit;
}

$db        = getDB();
$device_id = trim($data['device_id']);
$type      = trim($data['type']);   // 'nurse_call' | 'low_volume'
$volume    = (float)($data['volume']  ?? 0);
$persen    = (float)($data['persen']  ?? 0);

// Ambil data device
$devStmt = $db->prepare("SELECT * FROM devices WHERE device_id = :id AND aktif = 1");
$devStmt->execute([':id' => $device_id]);
$device = $devStmt->fetch();

if (!$device) {
    echo json_encode(['status' => 'error', 'message' => 'Device tidak ditemukan']);
    exit;
}

// Kumpulkan nomor tujuan (buang yang kosong)
$targets = array_filter([
    $device['no_suster']   ?? '',
    $device['no_keluarga'] ?? '',
]);

if (empty($targets)) {
    echo json_encode(['status' => 'skip', 'message' => 'Tidak ada nomor tujuan terdaftar']);
    exit;
}

// Pilih template pesan
$templateKey = ($type === 'nurse_call') ? 'wa_nurse_call_msg' : 'wa_low_volume_msg';
$template    = getSetting($templateKey, '');

if (empty($template)) {
    echo json_encode(['status' => 'skip', 'message' => 'Template pesan kosong']);
    exit;
}

// Render pesan
$message = renderWaMessage($template, [
    'pasien'  => $device['pasien']  ?: '-',
    'lokasi'  => $device['lokasi']  ?: '-',
    'volume'  => round($volume),
    'persen'  => round($persen),
    'waktu'   => date('d/m/Y H:i:s'),
    'device'  => $device_id,
]);

// Kirim
$result = sendWhatsApp(array_values($targets), $message);

echo json_encode([
    'status'  => $result['success'] ? 'ok' : 'error',
    'message' => $result['success'] ? 'WhatsApp terkirim' : ($result['error'] ?? 'Gagal kirim'),
    'detail'  => $result,
]);
