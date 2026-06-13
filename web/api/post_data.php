<?php
// =====================================================
// API: TERIMA DATA DARI ESP32
// POST /api/post_data.php
// Content-Type: application/json
// =====================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/whatsapp.php';

// baca body JSON
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['device_id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid']);
    exit;
}

$db = getDB();

// sanitasi input
$device_id    = trim($data['device_id']);
$tpm          = (float)($data['tpm']          ?? 0);
$volume_sisa  = (float)($data['volume_sisa']  ?? 0);
$volume_awal  = (float)($data['volume_awal']  ?? 0);
$persen       = (float)($data['persen']       ?? 0);
$estimasi_jam = (int)  ($data['estimasi_jam'] ?? 0);
$estimasi_mnt = (int)  ($data['estimasi_mnt'] ?? 0);
$total_tetes  = (int)  ($data['total_tetes']  ?? 0);
$nurse_call   = (int)  ($data['nurse_call']   ?? 0);
$mode         = trim($data['mode'] ?? '500ml');

// validasi mode
$allowed_modes = ['500ml', '100ml', 'OTHER'];
if (!in_array($mode, $allowed_modes)) {
    $mode = '500ml';
}

// fallback volume_awal: jika 0 (OTHER belum terbaca loadcell),
// ambil volume_awal terakhir dari database
if ($volume_awal <= 0) {
    $lastAwal = $db->prepare("
        SELECT volume_awal FROM infus_data
        WHERE device_id = :device_id AND volume_awal > 0
        ORDER BY created_at DESC LIMIT 1
    ");
    $lastAwal->execute([':device_id' => $device_id]);
    $row = $lastAwal->fetch();
    $volume_awal = $row ? (float)$row['volume_awal'] : 500.0;
}

// simpan data infus
$stmt = $db->prepare("
    INSERT INTO infus_data
        (device_id, tpm, volume_sisa, volume_awal, persen,
         estimasi_jam, estimasi_mnt, total_tetes, nurse_call, mode)
    VALUES
        (:device_id, :tpm, :volume_sisa, :volume_awal, :persen,
         :estimasi_jam, :estimasi_mnt, :total_tetes, :nurse_call, :mode)
");

$stmt->execute([
    ':device_id'    => $device_id,
    ':tpm'          => $tpm,
    ':volume_sisa'  => $volume_sisa,
    ':volume_awal'  => $volume_awal,
    ':persen'       => $persen,
    ':estimasi_jam' => $estimasi_jam,
    ':estimasi_mnt' => $estimasi_mnt,
    ':total_tetes'  => $total_tetes,
    ':nurse_call'   => $nurse_call,
    ':mode'         => $mode,
]);

// log nurse call jika aktif
if ($nurse_call === 1) {

    // cek apakah sudah ada log ACTIVE untuk device ini (tanpa batas waktu)
    // sehingga tidak spam insert selama tombol ditekan terus
    $check = $db->prepare("
        SELECT id FROM nurse_call_log
        WHERE device_id = :device_id
          AND status = 1
        LIMIT 1
    ");
    $check->execute([':device_id' => $device_id]);

    if (!$check->fetch()) {
        // Belum ada log aktif → buat baru
        $logStmt = $db->prepare("
            INSERT INTO nurse_call_log (device_id, status)
            VALUES (:device_id, 1)
        ");
        $logStmt->execute([':device_id' => $device_id]);

        // ── Kirim notifikasi WhatsApp nurse call ──────────────
        triggerWhatsApp($db, $device_id, 'nurse_call', $volume_sisa, $persen);
    }

} else {
    // IoT mematikan nurse call (suster tekan tombol di perangkat)
    // → resolve semua log ACTIVE milik device ini
    $resolveStmt = $db->prepare("
        UPDATE nurse_call_log
        SET status      = 0,
            resolved_at = NOW(),
            resolved_by = 'device'
        WHERE device_id = :device_id
          AND status    = 1
    ");
    $resolveStmt->execute([':device_id' => $device_id]);
}

// ── TPM tinggi (> 80 tpm) — WA sekali per sesi ──────────────────────────
if ($tpm > 80 && $volume_sisa > 0) {
    $flagKey = 'tpm_high_alerted_' . $device_id;
    if (getSetting($flagKey, '0') !== '1') {
        setSetting($flagKey, '1');
        triggerWhatsApp($db, $device_id, 'tpm_high', $volume_sisa, $persen, $tpm);
    }
} else {
    // Reset flag ketika TPM kembali normal
    $flagKey = 'tpm_high_alerted_' . $device_id;
    if (getSetting($flagKey, '0') === '1') {
        setSetting($flagKey, '0');
        triggerWhatsApp($db, $device_id, 'resolved', $volume_sisa, $persen, $tpm, 'tpm_high');
    }
}

// ── Kirim notifikasi WhatsApp volume kritis (≤ 20 ml, sekali per sesi) ──
if ($volume_sisa > 0 && $volume_sisa <= 20) {
    $flagKey  = 'low_vol_alerted_' . $device_id;
    $alerted  = getSetting($flagKey, '0');
    if ($alerted !== '1') {
        setSetting($flagKey, '1');
        triggerWhatsApp($db, $device_id, 'low_volume', $volume_sisa, $persen);
    }
} else {
    $flagKey = 'low_vol_alerted_' . $device_id;
    if (getSetting($flagKey, '0') === '1') {
        setSetting($flagKey, '0');
        // Volume kembali normal (infus diganti) — beri tahu keluarga saja
        triggerWhatsApp($db, $device_id, 'resolved', $volume_sisa, $persen, 0, 'low_volume');
    }
}

// ── Kirim notifikasi WhatsApp infus macet (TPM = 0, ada sisa volume, sekali per sesi) ──
if ($tpm == 0 && $volume_sisa > 0) {
    $flagKey   = 'tpm_zero_since_' . $device_id;
    $sinceSecs = (int) getSetting($flagKey, '0');
    if ($sinceSecs === 0) {
        setSetting($flagKey, (string) time());
    } else {
        $elapsed    = time() - $sinceSecs;
        $alertedKey = 'tpm_zero_alerted_' . $device_id;
        if ($elapsed >= 15 && getSetting($alertedKey, '0') !== '1') {
            setSetting($alertedKey, '1');
            triggerWhatsApp($db, $device_id, 'tpm_zero', $volume_sisa, $persen);
        }
    }
} else {
    $flagKey    = 'tpm_zero_since_'   . $device_id;
    $alertedKey = 'tpm_zero_alerted_' . $device_id;
    $wasAlerted = getSetting($alertedKey, '0') === '1';
    if (getSetting($flagKey, '0') !== '0') setSetting($flagKey, '0');
    if ($wasAlerted) {
        setSetting($alertedKey, '0');
        // TPM kembali normal — beri tahu keluarga saja
        triggerWhatsApp($db, $device_id, 'resolved', $volume_sisa, $persen, $tpm, 'tpm_zero');
    }
}

// ── Helper: trigger WhatsApp ─────────────────────────────────────────────
// $resolvedType: 'low_volume' | 'tpm_zero' | 'tpm_high' — untuk template resolved
function triggerWhatsApp(PDO $db, string $device_id, string $type, float $volume, float $persen, float $tpm = 0, string $resolvedType = ''): void {
    $s = $db->prepare("SELECT no_suster, no_keluarga FROM devices WHERE device_id = :id");
    $s->execute([':id' => $device_id]);
    $dev = $s->fetch();
    if (!$dev) return;

    $noSuster   = trim($dev['no_suster']   ?? '');
    $noKeluarga = trim($dev['no_keluarga'] ?? '');

    // Notif 'resolved' hanya ke keluarga
    $isResolved = ($type === 'resolved');
    if ($isResolved) {
        if (empty($noKeluarga)) return;
        $targets = [$noKeluarga];
    } else {
        $targets = array_filter([$noSuster, $noKeluarga]);
        if (empty($targets)) return;
    }

    $templateKey = match($type) {
        'nurse_call'  => 'wa_nurse_call_msg',
        'low_volume'  => 'wa_low_volume_msg',
        'tpm_zero'    => 'wa_tpm_zero_msg',
        'tpm_high'    => 'wa_tpm_high_msg',
        'resolved'    => 'wa_resolved_msg',
        default       => 'wa_low_volume_msg',
    };
    $template = getSetting($templateKey, '');
    if (empty($template)) return;

    $full = $db->prepare("SELECT * FROM devices WHERE device_id = :id");
    $full->execute([':id' => $device_id]);
    $device = $full->fetch();
    if (!$device) return;

    // Keterangan masalah yang sudah teratasi
    $resolvedLabel = match($resolvedType) {
        'low_volume' => 'volume infus kembali normal (infus telah diganti)',
        'tpm_zero'   => 'infus kembali menetes normal',
        'tpm_high'   => 'kecepatan tetesan kembali normal',
        default      => 'kondisi kembali normal',
    };

    $message = renderWaMessage($template, [
        'pasien'         => $device['pasien']  ?: '-',
        'lokasi'         => $device['lokasi']  ?: '-',
        'volume'         => round($volume),
        'persen'         => round($persen),
        'tpm'            => round($tpm),
        'waktu'          => date('d/m/Y H:i:s'),
        'device'         => $device_id,
        'resolved_label' => $resolvedLabel,
    ]);

    sendWhatsApp(array_values($targets), $message);
}

// auto-register device jika belum ada, atau aktifkan kembali jika pernah dinonaktifkan
$devCheck = $db->prepare("
    SELECT id, aktif FROM devices WHERE device_id = :device_id
");
$devCheck->execute([':device_id' => $device_id]);
$existingDev = $devCheck->fetch();

if (!$existingDev) {
    // Device baru — daftarkan
    $devInsert = $db->prepare("
        INSERT INTO devices (device_id, nama, lokasi, pasien, aktif)
        VALUES (:device_id, :nama, '-', '-', 1)
    ");
    $devInsert->execute([
        ':device_id' => $device_id,
        ':nama'      => 'Infus ' . $device_id,
    ]);
} elseif ((int)$existingDev['aktif'] === 0) {
    // Device pernah dinonaktifkan — aktifkan kembali
    $devReactivate = $db->prepare("
        UPDATE devices SET aktif = 1 WHERE device_id = :device_id
    ");
    $devReactivate->execute([':device_id' => $device_id]);
}

// ── Pembersihan Data Otomatis Probabilistik (Peluang 1% Per Post) ──
if (mt_rand(1, 100) === 1) {
    try {
        $infusDays = (int)getSetting('db_infus_data_retention', '3');
        $logDays   = (int)getSetting('db_nurse_log_retention', '7');
        
        if ($infusDays > 0) {
            $pruneInfus = $db->prepare("DELETE FROM infus_data WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $pruneInfus->execute([':days' => $infusDays]);
        }
        
        if ($logDays > 0) {
            $pruneNurse = $db->prepare("DELETE FROM nurse_call_log WHERE status = 0 AND resolved_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $pruneNurse->execute([':days' => $logDays]);
        }
    } catch (\Throwable $e) {
        error_log("Database auto-pruning failed: " . $e->getMessage());
    }
}

echo json_encode([
    'status'  => 'ok',
    'message' => 'Data berhasil disimpan',
    'id'      => $db->lastInsertId(),
]);
