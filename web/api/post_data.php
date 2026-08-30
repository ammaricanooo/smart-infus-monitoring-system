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

// ── Verifikasi API Key menggunakan pengaturan superadmin (iot_api_key) ──────────────
$expectedApiKey = getSetting('iot_api_key', '');
if (!empty($expectedApiKey)) {
    // 1. Cek dari Header HTTP (X-API-Key)
    $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    // 2. Cek dari Query Parameter (?api_key=...)
    if (empty($providedKey)) {
        $providedKey = $_GET['api_key'] ?? '';
    }
    
    // 3. Cek dari JSON Body
    if (empty($providedKey) && is_array($data)) {
        $providedKey = $data['api_key'] ?? '';
    }
    
    // Bandingkan dengan aman dari timing attack
    if (empty($providedKey) || !hash_equals($expectedApiKey, $providedKey)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: API Key tidak valid atau tidak disertakan']);
        exit;
    }
}

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

// ── Ambil parameter klinis pasien (target_tpm & tpm_tolerance) ─────────
$devInfoStmt = $db->prepare("SELECT target_tpm, tpm_tolerance FROM devices WHERE device_id = :id");
$devInfoStmt->execute([':id' => $device_id]);
$devRow = $devInfoStmt->fetch();

$targetTpm    = $devRow && isset($devRow['target_tpm']) ? (int)$devRow['target_tpm'] : (int)getSetting('default_target_tpm', '20');
$tpmTolerance = $devRow && isset($devRow['tpm_tolerance']) ? (int)$devRow['tpm_tolerance'] : (int)getSetting('default_tpm_tolerance', '5');
$tpmMin       = max(1, $targetTpm - $tpmTolerance);
$tpmMax       = $targetTpm + $tpmTolerance;

// ── TPM tinggi (> Max TPM pasien) — WA sekali per sesi ──────────────────
if ($tpm > $tpmMax && $volume_sisa > 0) {
    $flagKey = 'tpm_high_alerted_' . $device_id;
    if (getSetting($flagKey, '0') !== '1') {
        setSetting($flagKey, '1');
        triggerWhatsApp($db, $device_id, 'tpm_high', $volume_sisa, $persen, $tpm, '', $targetTpm, $tpmTolerance);
    }
} else {
    // Reset flag ketika TPM tidak lagi tinggi
    $flagKey = 'tpm_high_alerted_' . $device_id;
    if (getSetting($flagKey, '0') === '1') {
        setSetting($flagKey, '0');
        triggerWhatsApp($db, $device_id, 'resolved', $volume_sisa, $persen, $tpm, 'tpm_high', $targetTpm, $tpmTolerance);
    }
}

// ── TPM rendah (> 0 tapi < Min TPM pasien) — WA sekali per sesi ─────────
if ($tpm > 0 && $tpm < $tpmMin && $volume_sisa > 0) {
    $flagKey = 'tpm_low_alerted_' . $device_id;
    if (getSetting($flagKey, '0') !== '1') {
        setSetting($flagKey, '1');
        triggerWhatsApp($db, $device_id, 'tpm_low', $volume_sisa, $persen, $tpm, '', $targetTpm, $tpmTolerance);
    }
} else {
    // Reset flag ketika TPM tidak lagi rendah
    $flagKey = 'tpm_low_alerted_' . $device_id;
    if (getSetting($flagKey, '0') === '1') {
        setSetting($flagKey, '0');
        triggerWhatsApp($db, $device_id, 'resolved', $volume_sisa, $persen, $tpm, 'tpm_low', $targetTpm, $tpmTolerance);
    }
}

// ── Kirim notifikasi WhatsApp volume kritis (≤ 20 ml, sekali per sesi) ──
if ($volume_sisa > 0 && $volume_sisa <= 20) {
    $flagKey  = 'low_vol_alerted_' . $device_id;
    $alerted  = getSetting($flagKey, '0');
    if ($alerted !== '1') {
        setSetting($flagKey, '1');
        triggerWhatsApp($db, $device_id, 'low_volume', $volume_sisa, $persen, $tpm, '', $targetTpm, $tpmTolerance);
    }
} else {
    $flagKey = 'low_vol_alerted_' . $device_id;
    if (getSetting($flagKey, '0') === '1') {
        setSetting($flagKey, '0');
        // Volume kembali normal (infus diganti) — beri tahu keluarga saja
        triggerWhatsApp($db, $device_id, 'resolved', $volume_sisa, $persen, 0, 'low_volume', $targetTpm, $tpmTolerance);
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
            triggerWhatsApp($db, $device_id, 'tpm_zero', $volume_sisa, $persen, $tpm, '', $targetTpm, $tpmTolerance);
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
        triggerWhatsApp($db, $device_id, 'resolved', $volume_sisa, $persen, $tpm, 'tpm_zero', $targetTpm, $tpmTolerance);
    }
}

// ── Helper: trigger WhatsApp ─────────────────────────────────────────────
// $resolvedType: 'low_volume' | 'tpm_zero' | 'tpm_low' | 'tpm_high' — untuk template resolved
function triggerWhatsApp(PDO $db, string $device_id, string $type, float $volume, float $persen, float $tpm = 0, string $resolvedType = '', int $targetTpm = 20, int $tpmTol = 5): void {
    $s = $db->prepare("SELECT no_suster, no_keluarga FROM devices WHERE device_id = :id");
    $s->execute([':id' => $device_id]);
    $dev = $s->fetch();
    if (!$dev) return;

    $noSuster   = trim($dev['no_suster']   ?? '');
    $noKeluarga = trim($dev['no_keluarga'] ?? '');

    $full = $db->prepare("SELECT * FROM devices WHERE device_id = :id");
    $full->execute([':id' => $device_id]);
    $device = $full->fetch();
    if (!$device) return;

    // Keterangan masalah yang sudah teratasi
    $resolvedLabel = match($resolvedType) {
        'low_volume' => 'volume infus kembali normal (infus telah diganti)',
        'tpm_zero'   => 'infus kembali menetes normal',
        'tpm_low'    => 'kecepatan tetesan kembali ke batas normal',
        'tpm_high'   => 'kecepatan tetesan kembali normal',
        default      => 'kondisi kembali normal',
    };

    $vars = [
        'pasien'         => $device['pasien']  ?: '-',
        'lokasi'         => $device['lokasi']  ?: '-',
        'volume'         => round($volume),
        'persen'         => round($persen),
        'tpm'            => round($tpm),
        'target_tpm'     => $targetTpm,
        'tpm_tol'        => $tpmTol,
        'waktu'          => date('d/m/Y H:i:s'),
        'device'         => $device_id,
        'resolved_label' => $resolvedLabel,
    ];

    // Map type ke setting key suster & keluarga
    $isResolved = ($type === 'resolved');

    if ($isResolved) {
        // Resolved: ke keluarga DAN ke suster (template terpisah)
        if (!empty($noKeluarga)) {
            $templateKey = 'wa_resolved_msg_keluarga';
            $template    = getSetting($templateKey, getSetting('wa_resolved_msg', ''));
            if (!empty($template)) {
                sendWhatsApp([$noKeluarga], renderWaMessage($template, $vars));
            }
        }
        if (!empty($noSuster)) {
            $templateSuster = getSetting('wa_resolved_msg_suster', '');
            if (!empty($templateSuster)) {
                sendWhatsApp([$noSuster], renderWaMessage($templateSuster, $vars));
            }
        }
        return;
    } else {
        // Kirim dua request terpisah: satu ke suster, satu ke keluarga
        $keyMap = [
            'nurse_call' => ['suster' => 'wa_nurse_call_msg_suster',  'keluarga' => 'wa_nurse_call_msg_keluarga'],
            'low_volume' => ['suster' => 'wa_low_volume_msg_suster',   'keluarga' => 'wa_low_volume_msg_keluarga'],
            'tpm_zero'   => ['suster' => 'wa_tpm_zero_msg_suster',     'keluarga' => 'wa_tpm_zero_msg_keluarga'],
            'tpm_low'    => ['suster' => 'wa_tpm_low_msg_suster',      'keluarga' => 'wa_tpm_low_msg_keluarga'],
            'tpm_high'   => ['suster' => 'wa_tpm_high_msg_suster',     'keluarga' => 'wa_tpm_high_msg_keluarga'],
        ];

        // Fallback ke old single key jika new key belum ada di settings
        $fallbackKey = match($type) {
            'nurse_call' => 'wa_nurse_call_msg',
            'low_volume' => 'wa_low_volume_msg',
            'tpm_zero'   => 'wa_tpm_zero_msg',
            'tpm_high'   => 'wa_tpm_high_msg',
            default      => '',
        };

        $keys = $keyMap[$type] ?? null;
        if (!$keys) return;

        // Kirim ke suster
        if (!empty($noSuster)) {
            $tmplSuster = getSetting($keys['suster'], getSetting($fallbackKey, ''));
            if (!empty($tmplSuster)) {
                $msgSuster = renderWaMessage($tmplSuster, $vars);
                sendWhatsApp([$noSuster], $msgSuster);
            }
        }

        // Kirim ke keluarga
        if (!empty($noKeluarga)) {
            $tmplKeluarga = getSetting($keys['keluarga'], getSetting($fallbackKey, ''));
            if (!empty($tmplKeluarga)) {
                $msgKeluarga = renderWaMessage($tmplKeluarga, $vars);
                sendWhatsApp([$noKeluarga], $msgKeluarga);
            }
        }
    }
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

// ── Pembersihan Data Otomatis Terjadwal (Offloaded) ──
// Logika pembersihan otomatis (probabilistik 1%) telah dipindahkan ke skrip mandiri
// di `/api/cron_prune.php` untuk meningkatkan performa respon API alat infus.
// Jalankan skrip tersebut via Cron Job server secara berkala.

echo json_encode([
    'status'  => 'ok',
    'message' => 'Data berhasil disimpan',
    'id'      => $db->lastInsertId(),
]);
