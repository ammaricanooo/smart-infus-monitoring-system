<?php
// =====================================================
// HALAMAN DETAIL DEVICE — TAMPILAN PREMIUM MEDIS
// =====================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireAccess('dashboard');

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

$targetTpm  = (int)($device['target_tpm'] ?? 20);
$tpmTol     = (int)($device['tpm_tolerance'] ?? 5);
$tpmMin     = max(1, $targetTpm - $tpmTol);
$tpmMax     = $targetTpm + $tpmTol;

$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Detail <?= htmlspecialchars($device['nama']) ?> — Smart Infus</title>
  
  <!-- Local Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/style.css" />
  
  <!-- Typography & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-[#6b2072]/10 selection:text-[#6b2072] font-sans antialiased"
  data-detail-pasien="<?= htmlspecialchars($device['pasien']) ?>"
  data-detail-lokasi="<?= htmlspecialchars($device['lokasi']) ?>"
  data-target-tpm="<?= $targetTpm ?>"
  data-tpm-tol="<?= $tpmTol ?>">

  <?php require __DIR__ . '/config/navbar.php'; ?>

  <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">

    <!-- HEADER: Pasien Info Card -->
    <div id="detail-header-card" class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm mb-6 transition-all duration-500 <?= ($nurseCall && $isOnline) ? 'border-red-200 bg-red-50/20 ring-4 ring-red-500/5' : '' ?>">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <div class="text-[10px] font-black text-[#6b2072] uppercase tracking-widest mb-1">Identitas Pasien</div>
          <h2 class="text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($device['pasien']) ?></h2>
          <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2">
            <span class="text-xs font-semibold text-slate-500 flex items-center gap-1">
              <i class="bi bi-geo-alt-fill text-slate-400"></i><?= htmlspecialchars($device['lokasi']) ?>
            </span>
            <span class="text-xs font-medium text-slate-400 flex items-center gap-1 font-mono">
              <i class="bi bi-cpu-fill text-slate-300"></i><?= htmlspecialchars($device['device_id']) ?>
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-bold text-sky-700 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-md">
              <i class="bi bi-speedometer2 text-sky-600"></i>Target: <?= $targetTpm ?> ± <?= $tpmTol ?> TPM
            </span>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 self-stretch sm:self-auto">
          <span id="d-mode-badge" class="text-[10px] font-black bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg uppercase tracking-wider border border-slate-200/50">
            <?= htmlspecialchars($mode) ?> MODE
          </span>
          <span id="d-online-badge" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black tracking-wider border <?= $isOnline ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' ?>"></span>
            <?= $isOnline ? 'CONNECTED' : 'OFFLINE' ?>
          </span>
          
          <button onclick="refreshDetail()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 border border-blue-100 text-blue-600 text-[10px] font-black tracking-wider rounded-lg transition-colors">
            <i class="bi bi-arrow-repeat"></i> REFRESH
          </button>
        </div>
      </div>
    </div>

    <!-- MAIN LAYOUT: Bottle + Stats Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">

      <!-- Bottle Card -->
      <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm flex flex-col items-center justify-center gap-4 lg:col-span-1">
        <div class="w-20 h-44 bg-slate-100 border-2 border-slate-200/80 rounded-t-xl rounded-b-3xl relative overflow-hidden shadow-inner shadow-slate-200/50">
          <div id="d-bottle-fluid"
               style="position:absolute; bottom:0; left:0; right:0; width:100%; height:<?= $persen ?>%; transition:height 1s ease-in-out; background:<?= !$isOnline ? '#94a3b8' : ($persen > 50 ? 'linear-gradient(to top,#6b2072,#a855f7)' : ($persen > 20 ? 'linear-gradient(to top,#d97706,#f59e0b)' : 'linear-gradient(to top,#dc2626,#f87171)')) ?>; <?= ($isOnline && $persen <= 20) ? 'animation:fluid-blink 1.5s infinite;' : '' ?>">
            <div style="position:absolute; top:0; left:0; right:0; width:100%; height:6px; background:rgba(255,255,255,0.2);"></div>
          </div>
        </div>
        <div class="text-center">
          <div id="d-persen-text" class="text-3xl font-black text-slate-900 tracking-tight"><?= number_format($persen,0) ?>%</div>
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Sisa Ketinggian</div>
        </div>
      </div>

      <!-- 4 Stat Cards Matrix -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:col-span-3">

        <!-- Card TPM -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <div class="w-10 h-10 bg-red-50 border border-red-100 text-red-500 rounded-xl flex items-center justify-center text-base">
              <i class="bi bi-droplet-half"></i>
            </div>
            <span id="d-tpm-badge" class="text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-md">
              Target: <?= $targetTpm ?> ± <?= $tpmTol ?>
            </span>
          </div>
          <div class="mt-4">
            <div id="d-tpm" class="text-4xl font-black text-slate-900 tracking-tight"><?= number_format($tpm,0) ?></div>
            <div class="text-[10px] font-black text-red-600 uppercase tracking-widest mt-1 flex items-center justify-between">
              <span>Tetes Per Menit (TPM)</span>
              <span id="d-tpm-range" class="text-slate-400 font-mono text-[9px] font-semibold">Rentang: <?= $tpmMin ?>–<?= $tpmMax ?></span>
            </div>
          </div>
        </div>

        <!-- Card Volume -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
          <div class="w-10 h-10 bg-emerald-50 border border-emerald-100 text-emerald-500 rounded-xl flex items-center justify-center text-base">
            <i class="bi bi-water"></i>
          </div>
          <div class="mt-4">
            <div class="text-4xl font-black text-slate-900 tracking-tight">
              <span id="d-volume"><?= number_format($volumeSisa,0) ?></span><span class="text-sm font-bold text-slate-400 tracking-normal ml-0.5">/<?= number_format($volumeAwal,0) ?>ml</span>
            </div>
            <div class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mt-1">Volume Riil Sisa</div>
          </div>
        </div>

        <!-- Card Estimasi -->
        <?php
          $isDetailMacet = $isOnline && ((float)$tpm === 0.0) && ((float)$volumeSisa > 0);
        ?>
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
          <div class="w-10 h-10 bg-purple-50 border border-purple-100 text-purple-500 rounded-xl flex items-center justify-center text-base">
            <i class="bi bi-clock-history"></i>
          </div>
          <div class="mt-4">
            <div id="d-estimasi" class="text-4xl font-black text-slate-900 tracking-tight"><?= $isDetailMacet ? '<span class="text-purple-600">Terhenti</span>' : "{$estJam}j {$estMnt}m" ?></div>
            <div class="text-[10px] font-black text-purple-600 uppercase tracking-widest mt-1"><?= $isDetailMacet ? 'Aliran Macet (0 TPM)' : 'Estimasi Operasional' ?></div>
          </div>
        </div>

        <!-- Card Nurse Call Status -->
        <div id="d-nurse-card" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all duration-300 <?= $nurseCall ? 'bg-red-50 border-red-200' : '' ?>">
          <div id="d-nurse-icon-box" class="w-10 h-10 rounded-xl flex items-center justify-center text-base border <?= $nurseCall ? 'bg-red-500 text-white border-red-600 animate-bounce' : 'bg-slate-50 text-slate-400 border-slate-100' ?>">
            <i class="bi bi-bell-fill"></i>
          </div>
          <div class="mt-4">
            <div id="d-nurse-status" class="text-xl font-black tracking-tight <?= $nurseCall ? 'text-red-600' : 'text-slate-400' ?>">
              <?= $nurseCall ? 'EMERGENCY ALERT' : 'STANDBY NORMAL' ?>
            </div>
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Status Respon Perawat</div>
            <div id="d-nurse-hint" class="text-[10px] text-red-500 font-bold mt-1 flex items-center gap-1 <?= $nurseCall ? '' : 'hidden' ?>">
              <span class="w-1 h-1 rounded-full bg-red-500 animate-ping"></span> Reset via Hardware Perangkat
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- CHART ANALYTICS SECTION -->
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-6 overflow-hidden">
      <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 class="text-sm font-black text-slate-900 tracking-wide uppercase">Analisis Metrik</h3>
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Grafik Pemantauan Tren Berkelanjutan (50 Data Terakhir)</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <span id="d-last-update" class="text-[10px] font-bold text-slate-400 border border-slate-200/60 bg-slate-50 px-2.5 py-1 rounded-lg">
            <i class="bi bi-clock-history mr-1"></i>Update Terakhir: <?= $lastUpdate ? date('H:i:s', strtotime($lastUpdate)) : '--:--:--' ?>
          </span>
          <span class="inline-flex items-center gap-1.5 text-[10px] font-black text-red-600 bg-red-50 border border-red-100 px-2 py-1 rounded-md">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> TPM
          </span>
          <span class="inline-flex items-center gap-1.5 text-[10px] font-black text-blue-600 bg-blue-50 border border-blue-100 px-2 py-1 rounded-md">
            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> VOLUME
          </span>
        </div>
      </div>
      <div class="px-0 py-6 relative h-80 w-full">
        <canvas id="chartMain"></canvas>
      </div>
    </div>

    <!-- LOG TABLES SECTION -->
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-100 flex items-center justify-between gap-4">
        <div>
          <h3 class="text-sm font-black text-slate-900 tracking-wide uppercase">Log Aliran Transmisi</h3>
          <span class="text-[9px] font-black text-[#6b2072] border border-[#6b2072]/20 bg-[#6b2072]/5 px-2 py-0.5 rounded-md mt-1 inline-block uppercase tracking-wider">10 Data Terkini</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button onclick="exportExcel()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-[10px] font-black tracking-wider rounded-xl transition-colors uppercase shadow-sm">
            <i class="bi bi-file-earmark-excel-fill text-emerald-600"></i> Export Excel
          </button>
          <button onclick="exportPDF()" class="inline-flex items-center gap-1.5 px-3 py-2 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 text-[10px] font-black tracking-wider rounded-xl transition-colors uppercase shadow-sm">
            <i class="bi bi-file-earmark-pdf-fill text-rose-600"></i> Export PDF Laporan
          </button>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left">
          <thead>
            <tr class="bg-slate-50/70 border-b border-slate-100">
              <th class="p-4 pl-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Waktu Ambil</th>
              <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Laju Tetesan (TPM)</th>
              <th class="p-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Volume Sisa</th>
              <th class="p-4 pr-6 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Status Level</th>
            </tr>
          </thead>
          <tbody id="log-tbody" class="divide-y divide-slate-100/70">
            <?php foreach (array_slice(array_reverse($history), 0, 10) as $h): ?>
            <tr class="hover:bg-slate-50/50 transition-colors">
              <td class="p-4 pl-6 text-xs font-bold text-slate-500 font-mono tabular-nums"><?= date('H:i:s', strtotime($h['created_at'])) ?></td>
              <td class="p-4">
                <span class="text-sm font-black text-slate-900 font-mono"><?= number_format($h['tpm']) ?></span>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide ml-0.5">TPM</span>
              </td>
              <td class="p-4">
                <span class="text-sm font-black text-slate-900 font-mono"><?= number_format($h['volume_sisa']) ?></span>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide ml-0.5">ml</span>
              </td>
              <td class="p-4 pr-6 text-right">
                <div class="inline-flex items-center gap-3">
                  <div class="w-20 h-1.5 bg-slate-100 border border-slate-200/40 rounded-full overflow-hidden hidden sm:block">
                    <div class="h-full rounded-full transition-all duration-500" style="width:<?= $h['persen'] ?>%; background:<?= $h['persen'] > 50 ? '#6b2072' : ($h['persen'] > 20 ? '#f59e0b' : '#ef4444') ?>;"></div>
                  </div>
                  <span class="text-xs font-black text-slate-900 font-mono min-w-[35px] text-right"><?= number_format($h['persen'],0) ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <!-- MEDICAL WORKSTATION FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Smart Infus Monitoring System &bull; Clinical Station Workspace</p>
  </footer>

  <script>
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartTPM    = <?= json_encode($chartTPM) ?>;
    const chartVolume = <?= json_encode($chartVolume) ?>;
    const deviceId    = <?= json_encode($device_id) ?>;
    const deviceMetadata = {
      deviceId: <?= json_encode($device['device_id']) ?>,
      namaAlat: <?= json_encode($device['nama']) ?>,
      pasien: <?= json_encode($device['pasien']) ?>,
      lokasi: <?= json_encode($device['lokasi']) ?>,
      mode: <?= json_encode($mode) ?>,
      volumeAwal: <?= json_encode($volumeAwal) ?>,
      petugas: <?= json_encode(getCurrentUser()['nama'] ?? 'Petugas Medis') ?>
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <!-- SheetJS for Clean Excel Export -->
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <!-- jsPDF & AutoTable for Medical Report PDF Export -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
  <script src="assets/js/detail.js"></script>
</body>
</html>