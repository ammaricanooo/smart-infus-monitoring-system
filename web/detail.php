<?php
// =====================================================
// HALAMAN DETAIL DEVICE
// =====================================================

require_once __DIR__ . '/config/db.php';

$device_id = isset($_GET['id']) ? trim($_GET['id']) : null;

if (!$device_id) {
  header('Location: index.php');
  exit;
}

$db = getDB();

// info device
$devStmt = $db->prepare("SELECT * FROM devices WHERE device_id = :id AND aktif = 1");
$devStmt->execute([':id' => $device_id]);
$device = $devStmt->fetch();

if (!$device) {
  header('Location: index.php');
  exit;
}

// data terbaru
$latestStmt = $db->prepare("
    SELECT * FROM infus_data
    WHERE device_id = :id
    ORDER BY created_at DESC
    LIMIT 1
");
$latestStmt->execute([':id' => $device_id]);
$latest = $latestStmt->fetch();

// riwayat 50 data terakhir untuk chart
$histStmt = $db->prepare("
    SELECT tpm, volume_sisa, persen, created_at
    FROM infus_data
    WHERE device_id = :id
    ORDER BY created_at DESC
    LIMIT 50
");
$histStmt->execute([':id' => $device_id]);
$history = array_reverse($histStmt->fetchAll());

// siapkan data chart
$chartLabels  = [];
$chartTPM     = [];
$chartVolume  = [];
$chartPersen  = [];

foreach ($history as $h) {
  $chartLabels[] = date('H:i:s', strtotime($h['created_at']));
  $chartTPM[]    = (float)$h['tpm'];
  $chartVolume[] = (float)$h['volume_sisa'];
  $chartPersen[] = (float)$h['persen'];
}

$persen     = $latest['persen']      ?? 0;
$volumeSisa = $latest['volume_sisa'] ?? 0;
$volumeAwal = $latest['volume_awal'] ?? 500;
$tpm        = $latest['tpm']         ?? 0;
$nurseCall  = $latest['nurse_call']  ?? 0;
$mode       = $latest['mode']        ?? '-';
$estJam     = $latest['estimasi_jam'] ?? 0;
$estMnt     = $latest['estimasi_mnt'] ?? 0;
$lastUpdate = $latest['created_at']   ?? null;
$isOnline   = $lastUpdate && (strtotime($lastUpdate) >= time() - 30);
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Detail <?= htmlspecialchars($device['nama']) ?> — Smart Infus</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f1117; color: #f1f5f9; min-height: 100vh; }
    ::-webkit-scrollbar { width: 5px; }
    ::-webkit-scrollbar-track { background: #0f1117; }
    ::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

    /* NAV */
    .si-nav { position: sticky; top: 0; z-index: 50; background: rgba(15,17,23,0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(255,255,255,0.06); }
    .si-nav-inner { max-width: 1280px; margin: 0 auto; padding: 0 24px; height: 60px; display: flex; align-items: center; gap: 32px; }
    .si-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
    .si-brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg, #2563eb, #3b82f6); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; box-shadow: 0 0 16px rgba(59,130,246,0.4); }
    .si-brand-name { font-size: 14px; font-weight: 800; color: #f1f5f9; letter-spacing: 0.05em; line-height: 1; }
    .si-brand-sub  { font-size: 9px; font-weight: 600; color: #3b82f6; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 2px; }
    .si-nav-links  { display: flex; align-items: center; gap: 4px; flex: 1; }
    .si-nav-link { display: flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; color: #64748b; text-decoration: none; transition: all 0.15s; }
    .si-nav-link:hover { color: #f1f5f9; background: rgba(255,255,255,0.06); }
    .si-nav-link.active { color: #3b82f6; background: rgba(59,130,246,0.12); }
    .si-nav-right { margin-left: auto; display: flex; align-items: center; gap: 12px; }

    /* CARDS */
    .si-card { background: #1a1d27; border: 1px solid rgba(255,255,255,0.07); border-radius: 16px; }
    .si-section-title { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .si-section-title::before { content: ''; width: 3px; height: 14px; background: #3b82f6; border-radius: 2px; display: inline-block; }

    /* BOTTLE */
    .bottle-container { width: 60px; height: 140px; background: #21253a; border-radius: 8px 8px 20px 20px; position: relative; border: 2px solid rgba(255,255,255,0.1); overflow: hidden; }
    .bottle-fluid { position: absolute; bottom: 0; width: 100%; transition: height 1s ease-in-out; }
    .bottle-fluid::after { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 8px; background: rgba(255,255,255,0.15); filter: blur(2px); }

    /* ANIMATIONS */
    .blink-red { animation: pulse-red 1.5s infinite; }
    @keyframes pulse-red { 0%{opacity:1} 50%{opacity:.5} 100%{opacity:1} }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

    /* BADGES */
    .si-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
    .si-badge-online  { background: rgba(16,185,129,0.15); color: #10b981; }
    .si-badge-offline { background: rgba(71,85,105,0.3);   color: #64748b; }
    .si-badge-nurse   { background: rgba(239,68,68,0.2);   color: #ef4444; }
    .hidden { display: none !important; }
  </style>
</head>
<body>

  <!-- NAVBAR -->
  <nav class="si-nav">
    <div class="si-nav-inner">
      <a href="index.php" class="si-brand">
        <div class="si-brand-icon"><i class="bi bi-droplet-fill"></i></div>
        <div>
          <div class="si-brand-name">SMART INFUS</div>
          <div class="si-brand-sub">Medical Monitor</div>
        </div>
      </a>
      <div class="si-nav-links">
        <a href="index.php" class="si-nav-link <?= $activePage==='dashboard'?'active':'' ?>"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a>
        <a href="devices.php" class="si-nav-link <?= $activePage==='devices'?'active':'' ?>"><i class="bi bi-cpu-fill"></i><span>Devices</span></a>
        <a href="settings.php" class="si-nav-link <?= $activePage==='settings'?'active':'' ?>"><i class="bi bi-sliders"></i><span>Settings</span></a>
      </div>
      <div class="si-nav-right">
        <a href="index.php" style="display:flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(255,255,255,0.06);border-radius:8px;color:#94a3b8;font-size:12px;font-weight:600;text-decoration:none;transition:all .15s" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
          <i class="bi bi-arrow-left"></i> Dashboard
        </a>
      </div>
    </div>
  </nav>

  <main style="max-width:1280px;margin:0 auto;padding:32px 24px">

    <!-- HEADER: Pasien Info Card -->
    <div class="si-card" style="padding:24px;margin-bottom:24px">
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:16px">
        <div>
          <div style="font-size:10px;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px">Identitas Pasien</div>
          <h2 style="font-size:22px;font-weight:900;color:#f1f5f9;line-height:1.2"><?= htmlspecialchars($device['pasien']) ?></h2>
          <div style="display:flex;gap:16px;margin-top:6px">
            <span style="font-size:12px;color:#64748b"><i class="bi bi-geo-alt-fill" style="margin-right:4px;color:#475569"></i><?= htmlspecialchars($device['lokasi']) ?></span>
            <span style="font-size:12px;color:#64748b"><i class="bi bi-cpu-fill" style="margin-right:4px;color:#475569"></i><?= htmlspecialchars($device['device_id']) ?></span>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <span style="font-size:10px;font-weight:700;background:#21253a;color:#94a3b8;padding:4px 12px;border-radius:8px;text-transform:uppercase;letter-spacing:.05em"><?= htmlspecialchars($mode) ?> MODE</span>
          <span id="d-online-badge" class="si-badge <?= $isOnline ? 'si-badge-online' : 'si-badge-offline' ?>">
            <span style="width:5px;height:5px;border-radius:50%;background:<?= $isOnline ? '#10b981' : '#64748b' ?>;display:inline-block"></span>
            <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?>
          </span>
          <?php if ($nurseCall): ?>
          <span class="si-badge si-badge-nurse"><i class="bi bi-bell-fill" style="font-size:8px"></i> NURSE CALL</span>
          <?php endif; ?>
          <button onclick="refreshDetail()" style="display:flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(59,130,246,0.12);border:none;border-radius:8px;color:#3b82f6;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s">
            <i class="bi bi-arrow-repeat"></i> REFRESH
          </button>
        </div>
      </div>
    </div>

    <!-- MAIN LAYOUT: Bottle + Stats -->
    <div style="display:grid;grid-template-columns:auto 1fr;gap:20px;margin-bottom:24px;align-items:start">

      <!-- Bottle Card -->
      <div class="si-card" style="padding:24px;display:flex;flex-direction:column;align-items:center;gap:16px;min-width:120px">
        <div class="bottle-container">
          <div class="bottle-fluid <?= $persen <= 20 ? 'blink-red' : '' ?>"
               style="height:<?= $persen ?>%;background:<?= $persen > 50 ? 'linear-gradient(0deg,#2563eb,#60a5fa)' : ($persen > 20 ? 'linear-gradient(0deg,#d97706,#f59e0b)' : 'linear-gradient(0deg,#dc2626,#f87171)') ?>"></div>
        </div>
        <div style="text-align:center">
          <div style="font-size:28px;font-weight:900;color:#f1f5f9;line-height:1"><?= number_format($persen,0) ?>%</div>
          <div style="font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-top:4px">Cairan Tersisa</div>
        </div>
      </div>

      <!-- 4 Stat Cards -->
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px">

        <!-- TPM -->
        <div class="si-card" style="padding:20px">
          <div style="width:36px;height:36px;background:rgba(239,68,68,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px">
            <i class="bi bi-droplet-half" style="color:#ef4444;font-size:16px"></i>
          </div>
          <div id="d-tpm" style="font-size:28px;font-weight:900;color:#f1f5f9;line-height:1"><?= number_format($tpm,0) ?></div>
          <div style="font-size:10px;font-weight:700;color:#ef4444;text-transform:uppercase;letter-spacing:.08em;margin-top:4px">Tetes Per Menit</div>
        </div>

        <!-- Volume -->
        <div class="si-card" style="padding:20px">
          <div style="width:36px;height:36px;background:rgba(16,185,129,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px">
            <i class="bi bi-water" style="color:#10b981;font-size:16px"></i>
          </div>
          <div style="font-size:28px;font-weight:900;color:#f1f5f9;line-height:1">
            <span id="d-volume"><?= number_format($volumeSisa,0) ?></span><span style="font-size:13px;font-weight:600;color:#475569"> ml</span>
          </div>
          <div style="font-size:10px;font-weight:700;color:#10b981;text-transform:uppercase;letter-spacing:.08em;margin-top:4px">Volume Sisa</div>
        </div>

        <!-- Estimasi -->
        <div class="si-card" style="padding:20px">
          <div style="width:36px;height:36px;background:rgba(139,92,246,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px">
            <i class="bi bi-clock-history" style="color:#8b5cf6;font-size:16px"></i>
          </div>
          <div id="d-estimasi" style="font-size:28px;font-weight:900;color:#f1f5f9;line-height:1"><?= $estJam ?>j <?= $estMnt ?>m</div>
          <div style="font-size:10px;font-weight:700;color:#8b5cf6;text-transform:uppercase;letter-spacing:.08em;margin-top:4px">Estimasi Habis</div>
        </div>

        <!-- Nurse Call -->
        <div id="d-nurse-card" class="si-card" style="padding:20px;<?= $nurseCall ? 'background:rgba(239,68,68,0.12);border-color:rgba(239,68,68,0.3)' : '' ?>">
          <div style="width:36px;height:36px;background:<?= $nurseCall ? 'rgba(239,68,68,0.2)' : 'rgba(71,85,105,0.2)' ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px">
            <i class="bi bi-bell-fill" style="color:<?= $nurseCall ? '#ef4444' : '#64748b' ?>;font-size:16px"></i>
          </div>
          <div id="d-nurse-status" style="font-size:18px;font-weight:900;color:<?= $nurseCall ? '#ef4444' : '#64748b' ?>;line-height:1"><?= $nurseCall ? 'EMERGENCY' : 'NORMAL' ?></div>
          <div style="font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-top:4px">Status Panggilan</div>
          <div id="d-nurse-hint" style="margin-top:6px;font-size:9px;font-weight:700;color:#ef4444;<?= $nurseCall ? '' : 'display:none' ?>">Matikan dari perangkat IoT</div>
        </div>

      </div>
    </div>

    <!-- CHART SECTION -->
    <div class="si-card" style="margin-bottom:24px">
      <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:14px;font-weight:800;color:#f1f5f9">Analisis Monitoring</div>
          <div style="font-size:10px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin-top:2px">Grafik Riwayat 50 Data Terakhir</div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
          <span id="d-last-update" style="font-size:10px;font-weight:600;color:#475569">
            <i class="bi bi-clock-history" style="margin-right:3px"></i>Update: <?= $lastUpdate ? date('H:i:s', strtotime($lastUpdate)) : '--:--:--' ?>
          </span>
          <span style="display:flex;align-items:center;gap:4px;font-size:10px;font-weight:700;color:#ef4444;background:rgba(239,68,68,0.1);padding:3px 8px;border-radius:6px">
            <span style="width:6px;height:6px;background:#ef4444;border-radius:50%;display:inline-block"></span> TPM
          </span>
          <span style="display:flex;align-items:center;gap:4px;font-size:10px;font-weight:700;color:#3b82f6;background:rgba(59,130,246,0.1);padding:3px 8px;border-radius:6px">
            <span style="width:6px;height:6px;background:#3b82f6;border-radius:50%;display:inline-block"></span> VOLUME
          </span>
        </div>
      </div>
      <div style="padding:24px;position:relative;height:320px">
        <canvas id="chartMain"></canvas>
      </div>
    </div>

    <!-- LOG TABLE -->
    <div class="si-card" style="overflow:hidden">
      <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div style="font-size:14px;font-weight:800;color:#f1f5f9;text-transform:uppercase;letter-spacing:.04em">Log Transmisi Data</div>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:10px;font-weight:700;color:#475569;background:rgba(255,255,255,0.05);padding:3px 10px;border-radius:20px">10 DATA TERBARU</span>
          <button onclick="exportCSV()" style="display:flex;align-items:center;gap:4px;padding:5px 12px;background:rgba(16,185,129,0.12);border:none;border-radius:8px;color:#10b981;font-size:10px;font-weight:700;cursor:pointer;text-transform:uppercase;letter-spacing:.06em">
            <i class="bi bi-download"></i> EXPORT CSV
          </button>
        </div>
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:rgba(255,255,255,0.04)">
              <th style="padding:14px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Waktu</th>
              <th style="padding:14px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">TPM</th>
              <th style="padding:14px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Volume Sisa</th>
              <th style="padding:14px 20px;text-align:right;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Status Level</th>
            </tr>
          </thead>
          <tbody id="log-tbody">
            <?php foreach (array_slice(array_reverse($history), 0, 10) as $h): ?>
            <tr style="border-top:1px solid rgba(255,255,255,0.04);transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
              <td style="padding:14px 20px;font-size:12px;font-weight:700;color:#64748b"><?= date('H:i:s', strtotime($h['created_at'])) ?></td>
              <td style="padding:14px 20px">
                <span style="font-size:14px;font-weight:900;color:#f1f5f9"><?= number_format($h['tpm']) ?></span>
                <span style="font-size:9px;font-weight:700;color:#475569;margin-left:3px">TPM</span>
              </td>
              <td style="padding:14px 20px">
                <span style="font-size:14px;font-weight:900;color:#f1f5f9"><?= number_format($h['volume_sisa']) ?></span>
                <span style="font-size:9px;font-weight:700;color:#475569;margin-left:3px">ML</span>
              </td>
              <td style="padding:14px 20px;text-align:right">
                <div style="display:inline-flex;align-items:center;gap:10px">
                  <div style="width:80px;height:5px;background:#21253a;border-radius:3px;overflow:hidden">
                    <div style="height:100%;width:<?= $h['persen'] ?>%;background:<?= $h['persen'] > 50 ? '#3b82f6' : ($h['persen'] > 20 ? '#f59e0b' : '#ef4444') ?>;border-radius:3px"></div>
                  </div>
                  <span style="font-size:11px;font-weight:800;color:#f1f5f9;min-width:32px;text-align:right"><?= number_format($h['persen'],0) ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <footer style="padding:32px 24px;text-align:center">
    <p style="font-size:10px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.2em">&copy; <?= date('Y') ?> Smart Infus Monitoring System</p>
  </footer>

  <script>
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartTPM    = <?= json_encode($chartTPM) ?>;
    const chartVolume = <?= json_encode($chartVolume) ?>;
    const deviceId    = <?= json_encode($device_id) ?>;
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="assets/js/detail.js"></script>
</body>
</html>
