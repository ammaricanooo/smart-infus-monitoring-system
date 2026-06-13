<?php
// =====================================================
// SSE: SERVER-SENT EVENTS — REALTIME PUSH
// GET /api/sse.php
//
// Browser membuka satu koneksi persisten.
// Server push event setiap ada perubahan data:
//   event: update  → data semua device terbaru
//   event: ping    → keepalive tiap 15 detik
// =====================================================

// ── Matikan semua output buffering Apache / PHP ──────
@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 0);
@ini_set('implicit_flush', 1);

while (ob_get_level()) ob_end_clean();

// ── Header SSE ───────────────────────────────────────
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');          // matikan nginx buffering juga
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

// ── Helper: kirim satu SSE event ─────────────────────
function sseEvent(string $event, mixed $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "event: {$event}\n";
    echo "data: {$json}\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

// ── Helper: ambil snapshot semua device dari DB ───────
function fetchSnapshot(PDO $db): array
{
    $rows = $db->query("
        SELECT
            d.device_id, d.nama, d.lokasi, d.pasien,
            d.created_at AS device_created_at,
            i.tpm, i.volume_sisa, i.volume_awal, i.persen,
            i.estimasi_jam, i.estimasi_mnt, i.total_tetes,
            i.nurse_call, i.mode, i.created_at,
            -- nurse call log aktif
            (SELECT COUNT(*) FROM nurse_call_log
             WHERE device_id = d.device_id AND status = 1) AS nurse_active_log
        FROM devices d
        LEFT JOIN infus_data i
            ON i.id = (
                SELECT id FROM infus_data
                WHERE device_id = d.device_id
                ORDER BY created_at DESC LIMIT 1
            )
        WHERE d.aktif = 1
        ORDER BY d.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $isOnline = false;
        
        if (!empty($r['created_at'])) {
            // Device pernah kirim data — check berdasarkan last data timestamp
            // Threshold 15 detik: lebih responsif untuk detect offline
            $isOnline = (time() - strtotime($r['created_at'])) < 15;
        } else if (!empty($r['device_created_at'])) {
            // Device belum pernah kirim data — check device age
            // Jika device sudah ada > 2 menit tapi belum ada data, langsung offline
            $deviceAge = time() - strtotime($r['device_created_at']);
            $isOnline = ($deviceAge < 120);  // 2 menit grace period
        }
        
        $r['is_online'] = $isOnline;
    }
    unset($r);

    return $rows;
}

// ── Helper: ambil nurse call log terbaru ──────────────
function fetchNurseLog(PDO $db): array
{
    return $db->query("
        SELECT n.device_id, n.status, n.created_at,
               d.nama, d.lokasi, d.pasien
        FROM nurse_call_log n
        LEFT JOIN devices d ON d.device_id = n.device_id
        ORDER BY n.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ── Hash snapshot untuk deteksi perubahan ─────────────
function snapshotHash(array $rows): string
{
    // PENTING: Tidak menyertakan created_at agar tidak push tiap detik.
    // is_online dihitung dari created_at tapi nilainya (true/false) DIMASUKKAN
    // sehingga perubahan online→offline tetap terdeteksi.
    $sig = '';
    foreach ($rows as $r) {
        $sig .= ($r['device_id'] ?? '')
            . ($r['tpm'] ?? '')
            . ($r['volume_sisa'] ?? '')
            . ($r['persen'] ?? '')
            . ($r['nurse_call'] ?? '')
            . ($r['nurse_active_log'] ?? '')
            . ($r['created_at'] ?? '')
            . ($r['is_online'] ? '1' : '0');
    }
    return md5($sig);
}

// ── Batas waktu eksekusi PHP (5 menit, browser reconnect otomatis) ────
set_time_limit(300);

$db          = getDB();
$lastHash    = '';
$lastLogHash = '';
$lastPing    = time();
$loopDelay   = 500_000;   // 0.5 detik antar cek DB (microseconds)
$_lastOnlineStatus = [];   // Track status online/offline setiap device

// Kirim snapshot awal langsung saat koneksi dibuka
$snapshot    = fetchSnapshot($db);
$nurseLog    = fetchNurseLog($db);
$lastHash    = snapshotHash($snapshot);
$lastLogHash = md5(json_encode($nurseLog));

// Initialize online status tracker
foreach ($snapshot as $dev) {
    $_lastOnlineStatus[$dev['device_id']] = $dev['is_online'];
}

sseEvent('update', ['devices' => $snapshot, 'nurse_log' => $nurseLog]);

// ── Loop utama ────────────────────────────────────────
while (true) {

    // Cek apakah client masih konek
    if (connection_aborted()) break;

    try {
        $snapshot = fetchSnapshot($db);
        $newHash  = snapshotHash($snapshot);

        // Deteksi perubahan status online/offline per device untuk force push
        $onlineStatusChanged = false;
        if (!empty($_lastOnlineStatus)) {
            foreach ($snapshot as $dev) {
                $devId = $dev['device_id'];
                $oldStatus = $_lastOnlineStatus[$devId] ?? null;
                $newStatus = $dev['is_online'];
                if ($oldStatus !== $newStatus && $oldStatus !== null) {
                    $onlineStatusChanged = true;
                    break;
                }
            }
        }
        // Store current online status untuk deteksi perubahan di loop berikutnya
        $_lastOnlineStatus = [];
        foreach ($snapshot as $dev) {
            $_lastOnlineStatus[$dev['device_id']] = $dev['is_online'];
        }

        // Query nurse log HANYA jika device data berubah atau setiap 10 detik
        $now = time();
        $logChanged = false;
        if ($newHash !== $lastHash || ($now - $lastPing) >= 10) {
            $nurseLog    = fetchNurseLog($db);
            $newLogHash  = md5(json_encode($nurseLog));
            $logChanged  = ($newLogHash !== $lastLogHash);
        } else {
            $newLogHash = $lastLogHash;
        }

        // Push update jika: data berubah, log berubah, atau status online berubah
        if ($newHash !== $lastHash || $logChanged || $onlineStatusChanged) {
            $lastHash    = $newHash;
            $lastLogHash = $newLogHash;
            sseEvent('update', ['devices' => $snapshot, 'nurse_log' => $nurseLog]);
        }
    } catch (\Throwable $e) {
        // Reconnect DB jika koneksi mati (timeout MySQL)
        try {
            $db = getDB();
        } catch (\Throwable) {
        }
    }

    // Ping keepalive setiap 15 detik agar koneksi tidak di-timeout proxy/browser
    if (time() - $lastPing >= 15) {
        sseEvent('ping', ['t' => time()]);
        $lastPing = time();
    }

    usleep($loopDelay);
}
