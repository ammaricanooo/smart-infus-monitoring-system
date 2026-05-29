<?php
// =====================================================
// SMART INFUS — DASHBOARD UTAMA (REFACTORED TAILWIND)
// =====================================================

require_once __DIR__ . '/config/db.php';

$db = getDB();

$stmt = $db->query("
    SELECT
        d.id, d.device_id, d.nama, d.lokasi, d.pasien,
        i.tpm, i.volume_sisa, i.volume_awal, i.persen,
        i.estimasi_jam, i.estimasi_mnt, i.total_tetes,
        i.nurse_call, i.mode, i.created_at AS last_update
    FROM devices d
    LEFT JOIN infus_data i
        ON i.id = (
            SELECT id FROM infus_data
            WHERE device_id = d.device_id
            ORDER BY created_at DESC LIMIT 1
        )
    WHERE d.aktif = 1
    ORDER BY d.id ASC
");
$devices = $stmt->fetchAll();

$totalDevices   = count($devices);
$nurseCallCount = 0;
$lowVolumeCount = 0;
$onlineCount    = 0;

foreach ($devices as $dev) {
    if ($dev['nurse_call'])                                                 $nurseCallCount++;
    if ($dev['persen'] !== null && $dev['persen'] <= 20)                     $lowVolumeCount++;
    if ($dev['last_update'] && strtotime($dev['last_update']) >= time() - 30) $onlineCount++;
}

$logStmt = $db->query("
    SELECT n.*, d.nama, d.lokasi, d.pasien
    FROM nurse_call_log n
    LEFT JOIN devices d ON d.device_id = n.device_id
    ORDER BY n.created_at DESC
    LIMIT 20
");
$nurseLogs = $logStmt->fetchAll();
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Smart Infus — Central Monitoring System</title>
  
  <!-- Local Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/style.css" />
  
  <!-- Typography & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-[#6b2072]/10 selection:text-[#6b2072] pb-16 md:pb-0">

  <!-- TOP CLINICAL NAVBAR -->
  <nav class="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      
      <!-- Brand Identity -->
      <a href="index.php" class="flex items-center gap-3 group">
        <div class="w-10 h-10 bg-[#6b2072] text-white rounded-xl flex items-center justify-center shadow-lg shadow-[#6b2072]/20 transition-transform group-hover:scale-105">
          <i class="bi bi-droplet-fill text-lg"></i>
        </div>
        <div>
          <div class="text-xs font-black tracking-wider text-slate-900 uppercase">Smart Infus</div>
          <div class="text-[10px] font-bold text-[#6b2072] tracking-widest uppercase">Central Station</div>
        </div>
      </a>

      <!-- Navigation Menu -->
      <div class="hidden md:flex items-center gap-1">
        <a href="index.php" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all <?= $activePage==='dashboard' ? 'bg-[#6b2072]/10 text-[#6b2072]' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
          <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>
        <a href="devices.php" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all <?= $activePage==='devices' ? 'bg-[#6b2072]/10 text-[#6b2072]' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
          <i class="bi bi-cpu-fill"></i><span>Devices</span>
        </a>
        <a href="settings.php" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all <?= $activePage==='settings' ? 'bg-[#6b2072]/10 text-[#6b2072]' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
          <i class="bi bi-sliders"></i><span>Settings</span>
        </a>
      </div>

      <!-- Realtime Clock & Status Counter -->
      <div class="flex items-center gap-4">
        <div class="bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
          <span id="clockText" class="text-sm font-bold text-slate-700 tabular-nums">--:--:--</span>
        </div>
      </div>
    </div>
  </nav>

  <!-- MOBILE BOTTOM NAVIGATION -->
  <div class="fixed bottom-0 left-0 right-0 z-50 bg-white/90 backdrop-blur-md border-t border-slate-200/80 px-6 py-2 flex md:hidden justify-around items-center shadow-lg">
    <a href="index.php" class="flex flex-col items-center gap-0.5 text-[10px] font-bold transition-all <?= $activePage==='dashboard' ? 'text-[#6b2072]' : 'text-slate-500' ?>">
      <i class="bi bi-grid-1x2-fill text-lg"></i>
      <span>Dashboard</span>
    </a>
    <a href="devices.php" class="flex flex-col items-center gap-0.5 text-[10px] font-bold transition-all <?= $activePage==='devices' ? 'text-[#6b2072]' : 'text-slate-500' ?>">
      <i class="bi bi-cpu-fill text-lg"></i>
      <span>Devices</span>
    </a>
    <a href="settings.php" class="flex flex-col items-center gap-0.5 text-[10px] font-bold transition-all <?= $activePage==='settings' ? 'text-[#6b2072]' : 'text-slate-500' ?>">
      <i class="bi bi-sliders text-lg"></i>
      <span>Settings</span>
    </a>
  </div>

  <!-- MAIN DASHBOARD CONTENT -->
  <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">

    <!-- HOSPITAL CLINICAL OVERVIEW (STATISTICS) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

      <!-- Stat: Total Devices -->
      <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Unit Monitor</span>
          <div id="stat-total" class="text-3xl font-extrabold text-slate-900 mt-1"><?= $totalDevices ?></div>
          <p class="text-[11px] text-slate-500 mt-1">Perangkat terkonfigurasi</p>
        </div>
        <div class="w-12 h-12 bg-slate-100 text-slate-600 rounded-xl flex items-center justify-center border border-slate-200">
          <i class="bi bi-layers-half text-xl"></i>
        </div>
      </div>

      <!-- Stat: Online Station -->
      <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Koneksi Aktif</span>
          <div id="stat-online" class="text-3xl font-extrabold text-emerald-600 mt-1"><?= $onlineCount ?></div>
          <p class="text-[11px] text-slate-500 mt-1">Sinyal aktual &lt; 30d</p>
        </div>
        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center border border-emerald-100">
          <i class="bi bi-wifi text-xl"></i>
        </div>
      </div>

      <!-- Stat: Low Volume Alert -->
      <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex items-center justify-between">
        <div>
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kritis (&le; 20%)</span>
          <div id="stat-low" class="text-3xl font-extrabold <?= $lowVolumeCount > 0 ? 'text-amber-500' : 'text-slate-900' ?> mt-1"><?= $lowVolumeCount ?></div>
          <p class="text-[11px] text-slate-500 mt-1">Butuh pergantian segera</p>
        </div>
        <div class="w-12 h-12 <?= $lowVolumeCount > 0 ? 'bg-amber-50 text-amber-500 border-amber-100' : 'bg-slate-100 text-slate-600 border-slate-200' ?> rounded-xl flex items-center justify-center border">
          <i class="bi bi-droplet-half text-xl"></i>
        </div>
      </div>

      <!-- Stat: Emergency Emergency (Nurse Call) -->
      <div id="stat-nurse-card" class="border p-5 rounded-2xl shadow-sm flex items-center justify-between transition-all <?= $nurseCallCount > 0 ? 'bg-red-500 text-white border-red-600 shadow-lg shadow-red-500/20' : 'bg-white border-slate-200 text-slate-900' ?>">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider <?= $nurseCallCount > 0 ? 'text-red-100' : 'text-slate-400' ?>">Panggilan Darurat</span>
          <div id="stat-nurse" class="text-3xl font-extrabold mt-1 <?= $nurseCallCount > 0 ? 'text-white' : 'text-red-500' ?>"><?= $nurseCallCount ?></div>
          <p class="text-[11px] mt-1 <?= $nurseCallCount > 0 ? 'text-red-100' : 'text-slate-500' ?>">Nurse Call aktif</p>
        </div>
        <div class="w-12 h-12 rounded-xl flex items-center justify-center border <?= $nurseCallCount > 0 ? 'bg-white/20 border-white/30 text-white animate-bounce' : 'bg-red-50 text-red-500 border-red-100' ?>">
          <i class="bi bi-bell-fill text-xl"></i>
        </div>
      </div>

    </div>

    <!-- MAIN MONITOR GRID MODULES -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-4 mb-6 gap-3">
      <div>
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
          <span class="w-1.5 h-4 bg-[#6b2072] rounded-full inline-block"></span>
          Bangsal Perawatan Real-time
        </h2>
      </div>
      <button onclick="refreshAll()" class="inline-flex w-fit items-center gap-2 px-3.5 py-1.5 bg-white border border-slate-200 hover:border-slate-300 rounded-xl text-xs font-bold text-slate-600 shadow-sm cursor-pointer hover:bg-slate-50 active:scale-95 transition-all">
        <i class="bi bi-arrow-repeat"></i> SINKRONISASI DATA
      </button>
    </div>

    <!-- MONITORING CELLS -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($devices as $dev):
        $persen      = $dev['persen'] ?? 0;
        $isOnline    = $dev['last_update'] && (strtotime($dev['last_update']) >= time() - 30);
        $isNurse     = (bool)$dev['nurse_call'];
        
        // Semantic Rules
        if ($isNurse) {
            $statusColor = 'border-red-500 ring-4 ring-red-500/10 bg-red-50/30';
            $barColor    = 'bg-red-500';
            $liquidColor = 'bg-red-400';
        } elseif ($persen <= 10 && $persen > 0) {
            $statusColor = 'border-amber-400 ring-4 ring-amber-500/5 bg-amber-50/20';
            $barColor    = 'bg-amber-500';
            $liquidColor = 'bg-amber-400';
        } else {
            $statusColor = 'border-slate-200 hover:border-slate-300 bg-white';
            $barColor    = 'bg-[#6b2072]'; // Warna Korporat Ungu
            $liquidColor = 'bg-[#6b2072]/80';
        }
      ?>
      <div id="card-<?= htmlspecialchars($dev['device_id']) ?>"
           data-pasien="<?= htmlspecialchars($dev['pasien']) ?>"
           data-lokasi="<?= htmlspecialchars($dev['lokasi']) ?>"
           class="border rounded-2xl p-5 relative overflow-hidden shadow-sm flex flex-col justify-between transition-all <?= $statusColor ?>">

        <div>
          <!-- Header Cell: Badges + Action Buttons -->
          <div class="flex items-start justify-between gap-2 mb-4">
            <div class="flex items-center gap-3">
              <!-- Physical Bottle Indicator Simulation -->
              <div class="w-8 h-12 bg-slate-100 border-2 border-slate-200 rounded-t-md rounded-b-xl relative overflow-hidden flex-shrink-0 shadow-inner">
                <div data-role="bottle-liquid" class="absolute bottom-0 inset-x-0 transition-all duration-1000 <?= $liquidColor ?>" style="height: <?= $persen ?>%">
                  <div class="w-full h-1 bg-white/20 absolute top-0"></div>
                </div>
              </div>
              <div>
                <h3 class="text-sm font-bold text-slate-900 leading-tight"><?= htmlspecialchars($dev['nama']) ?></h3>
                <p class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                  <i class="bi bi-geo-alt"></i><?= htmlspecialchars($dev['lokasi']) ?>
                </p>
              </div>
            </div>

            <!-- System Network Status Indicators -->
            <div class="flex flex-col items-end gap-1.5">
              <span data-role="online-badge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wider uppercase border <?= $isOnline ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' ?>">
                <span class="w-1 h-1 rounded-full <?= $isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' ?>"></span>
                <?= $isOnline ? 'Connected' : 'Offline' ?>
              </span>
              
              <?php if($isNurse): ?>
              <span data-role="nurse-badge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold text-white tracking-wider uppercase bg-red-500 animate-medical-pulse">
                <i class="bi bi-bell-fill text-[8px]"></i> NURSE CALL
              </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Patient Identity Attachment -->
          <div class="bg-slate-100/80 border border-slate-200/60 rounded-xl p-2.5 mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2 truncate">
              <div class="w-6 h-6 bg-white border border-slate-200 rounded-full flex items-center justify-center flex-shrink-0 text-slate-500">
                <i class="bi bi-person-fill text-xs"></i>
              </div>
              <span class="text-xs font-bold text-slate-700 truncate"><?= htmlspecialchars($dev['pasien']) ?></span>
            </div>
            <span data-role="mode-badge" class="text-[10px] font-extrabold bg-white px-2 py-0.5 rounded-md border border-slate-200 text-slate-500 uppercase tracking-wide"><?= htmlspecialchars($dev['mode'] ?? '-') ?></span>
          </div>

          <!-- Precise Quantities (TPM & Volume Metrics) -->
          <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-slate-50 border border-slate-200/60 rounded-xl p-2.5 text-center">
              <span class="text-[9px] font-bold text-slate-400 tracking-wider uppercase block">Flow Rate</span>
              <div class="text-xl font-black text-slate-900 mt-0.5">
                <span data-role="tpm-value"><?= number_format($dev['tpm'] ?? 0) ?></span>
                <span class="text-xs font-medium text-slate-400">TPM</span>
              </div>
            </div>
            <div class="bg-slate-50 border border-slate-200/60 rounded-xl p-2.5 text-center">
              <span class="text-[9px] font-bold text-slate-400 tracking-wider uppercase block">Sisa Cairan</span>
              <!-- SESUDAH (BIARKAN SEPERTI INI DI INDEX.PHP) -->
<div class="text-xl font-black text-slate-900 mt-0.5">
  <span data-role="volume-display"><?= number_format($dev['volume_sisa'] ?? 0) ?></span><span class="text-xs font-medium text-slate-400">/<?= number_format($dev['volume_awal'] ?? 0) ?>mL</span>
</div>
            </div>
          </div>

          <!-- Volumetric Progress Linear Bars -->
          <div class="mb-4">
            <div class="flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-1">
              <span>Rasio Infus</span>
              <span data-role="persen-text" class="text-slate-700 font-extrabold text-xs"><?= number_format($persen,0) ?>%</span>
            </div>
            <div class="w-full h-2 bg-slate-100 border border-slate-200/80 rounded-full overflow-hidden">
              <div data-role="progress-bar" class="h-full rounded-full transition-all duration-1000 <?= $barColor ?>" style="width: <?= $persen ?>%"></div>
            </div>
            
            <?php if ($persen <= 20): ?>
            <div data-role="low-warning" class="mt-2 text-[10px] font-bold text-red-500 flex items-center gap-1">
              <i class="bi bi-exclamation-triangle-fill"></i> Perhatian: Kritis, segera ganti infus baru!
            </div>
            <?php endif; ?>
          </div>

          <!-- Time Frame Remaining Estimates -->
          <div class="bg-slate-50/60 border border-slate-200/40 rounded-xl px-3 py-2 flex items-center justify-between mb-4">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Estimasi Sisa Waktu</span>
            <span data-role="estimasi-value" class="text-xs font-extrabold text-slate-700 bg-white px-2 py-0.5 rounded-md border border-slate-200/60 shadow-sm tabular-nums">
              <?= $dev['estimasi_jam'] ?>j <?= $dev['estimasi_mnt'] ?>m
            </span>
          </div>
        </div>

        <!-- Cell Interactive Footer Control -->
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 mt-2">
          <span data-role="last-update" class="text-[10px] font-medium text-slate-400 flex items-center gap-1">
            <i class="bi bi-clock-history"></i> Update: <?= $dev['last_update'] ? date('H:i:s', strtotime($dev['last_update'])) : 'N/A' ?>
          </span>
          <div class="flex items-center gap-2">
            <a href="devices.php?edit=<?= urlencode($dev['device_id']) ?>" class="p-2 bg-white border border-slate-200 hover:border-amber-300 rounded-xl text-amber-500 hover:bg-amber-50 active:scale-90 transition-all text-xs" title="Edit Device">
              <i class="bi bi-pencil-fill"></i>
            </a>
            <a href="detail.php?id=<?= urlencode($dev['device_id']) ?>" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-sm active:scale-95 transition-all">
              PERIKSA
            </a>
          </div>
        </div>

      </div>
      <?php endforeach; ?>
    </div>

    <!-- NURSE CALL ARCHIVE CHRONOLOGY LOGS -->
    <div class="mt-12">
      <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-4">
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
          <span class="w-1.5 h-4 bg-red-500 rounded-full inline-block"></span>
          Kronologi Panggilan Darurat (Nurse Call)
          <span id="nurse-log-count" class="text-xs bg-slate-100 border border-slate-200 text-slate-600 px-2 py-0.5 rounded-full font-bold ml-1"><?= count($nurseLogs) ?></span>
        </h2>
        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5 flex items-center gap-1 tracking-wider">
          <span class="w-1 h-1 bg-emerald-500 rounded-full animate-ping"></span> LIVE PIPELINE
        </span>
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 tracking-wider uppercase">
                <th class="py-3.5 px-6">Waktu Kejadian</th>
                <th class="py-3.5 px-6">Identitas Pasien / Lokasi Kamar</th>
                <th class="py-3.5 px-6">Kode Modul Device</th>
              </tr>
            </thead>
            <tbody id="nurse-log-tbody" class="divide-y divide-slate-100 text-sm">
              <?php foreach ($nurseLogs as $log): ?>
              <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-6 font-bold text-slate-500 tabular-nums"><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
                <td class="py-4 px-6">
                  <div class="font-bold text-slate-900"><?= htmlspecialchars($log['pasien'] ?? 'Pasien Anonim') ?></div>
                  <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                    <i class="bi bi-geo-alt text-[11px]"></i><?= htmlspecialchars($log['lokasi'] ?? '-') ?>
                  </div>
                </td>
                <td class="py-4 px-6 font-semibold text-slate-400 font-mono text-xs"><?= htmlspecialchars($log['device_id']) ?></td>
              </tr>
              <?php endforeach; ?>
              
              <?php if (empty($nurseLogs)): ?>
              <tr>
                <td colspan="3" class="py-12 text-center text-xs font-bold text-slate-400 tracking-wider uppercase">
                  <i class="bi bi-shield-check text-2xl block text-slate-300 mb-2"></i>
                  Sistem Aman — Belum Ada Log Masuk
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>

  <!-- MEDICAL WORKSTATION FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Smart Infus Monitoring System &bull; Clinical Station Workspace</p>
  </footer>

  <!-- REALTIME JAVASCRIPT CLOCK PIPELINE -->
  <script>
    function updateClock() {
      const now = new Date();
      const h = String(now.getHours()).padStart(2,'0');
      const m = String(now.getMinutes()).padStart(2,'0');
      const s = String(now.getSeconds()).padStart(2,'0');
      const el = document.getElementById('clockText');
      if (el) el.textContent = h + ':' + m + ':' + s;
    }
    updateClock();
    setInterval(updateClock, 1000);
  </script>
  <script src="assets/js/dashboard.js"></script>
</body>
</html>