<?php
// =====================================================
// HALAMAN MONITOR KELUARGA — Akses via Token Privat
// Tidak memerlukan akun / login
// =====================================================

require_once __DIR__ . '/config/db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px;">
        <h2>Link tidak valid</h2><p>Tautan monitoring ini tidak valid. Silakan hubungi petugas medis.</p>
    </body></html>';
    exit;
}

$db = getDB();
$devStmt = $db->prepare("SELECT * FROM devices WHERE family_token = :token AND aktif = 1 LIMIT 1");
$devStmt->execute([':token' => $token]);
$device = $devStmt->fetch();

if (!$device) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px;">
        <h2 style="color:#dc2626;">Akses Ditolak</h2>
        <p>Tautan monitoring tidak ditemukan atau sudah tidak aktif.</p>
        <p>Silakan hubungi petugas untuk mendapatkan tautan baru.</p>
    </body></html>';
    exit;
}

$device_id = $device['device_id'];

// Data terbaru untuk render awal
$latestStmt = $db->prepare("SELECT * FROM infus_data WHERE device_id = :id ORDER BY created_at DESC LIMIT 1");
$latestStmt->execute([':id' => $device_id]);
$latest = $latestStmt->fetch();

// History 30 data untuk chart awal
$histStmt = $db->prepare("
    SELECT tpm, volume_sisa, persen, created_at
    FROM infus_data WHERE device_id = :id
    ORDER BY created_at DESC LIMIT 30
");
$histStmt->execute([':id' => $device_id]);
$history = array_reverse($histStmt->fetchAll());

// Riwayat nurse call (10 terakhir)
$nurseLogStmt = $db->prepare("
    SELECT id, status, created_at, resolved_at, resolved_by
    FROM nurse_call_log
    WHERE device_id = :id
    ORDER BY created_at DESC
    LIMIT 10
");
$nurseLogStmt->execute([':id' => $device_id]);
$nurseLogs = $nurseLogStmt->fetchAll();

$persen     = $latest['persen']       ?? 0;
$volumeSisa = $latest['volume_sisa']  ?? 0;
$volumeAwal = $latest['volume_awal']  ?? 500;
$tpm        = $latest['tpm']          ?? 0;
$estJam     = $latest['estimasi_jam'] ?? 0;
$estMnt     = $latest['estimasi_mnt'] ?? 0;
$lastUpdate = $latest['created_at']   ?? null;
$isOnline   = $lastUpdate && (strtotime($lastUpdate) >= time() - 30);

$chartLabels = [];
$chartTPM    = [];
$chartVolume = [];
foreach ($history as $h) {
    $chartLabels[] = date('H:i', strtotime($h['created_at']));
    $chartTPM[]    = (float)$h['tpm'];
    $chartVolume[] = (float)$h['volume_sisa'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Monitor Infus — <?= htmlspecialchars($device['pasien']) ?></title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <style>
    @keyframes fluid-blink { 0%,100%{opacity:1} 50%{opacity:.55} }
    @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
    @keyframes pulse-ring { 0%{box-shadow:0 0 0 0 rgba(239,68,68,.4)} 100%{box-shadow:0 0 0 14px rgba(239,68,68,0)} }
    .nurse-pulse { animation: pulse-ring 1.2s cubic-bezier(0.4,0,0.6,1) infinite; }
    body { padding-top: 64px; }
    @media(max-width:767px){ body { padding-top: 56px; padding-bottom: 0; } }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans antialiased selection:bg-[#6b2072]/10 selection:text-[#6b2072]">

  <!-- ── TOPBAR ── -->
  <div class="fixed top-0 left-0 right-0 z-40 h-16 flex items-center justify-between px-4 sm:px-6"
       style="background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);border-bottom:1px solid #e2e8f0;">
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-[#6b2072] text-white rounded-lg flex items-center justify-center flex-shrink-0">
        <i class="bi bi-droplet-fill text-sm"></i>
      </div>
      <div>
        <div class="text-[11px] font-black tracking-wider text-slate-900 uppercase leading-tight">Smart Infus</div>
        <div class="text-[9px] font-bold text-[#6b2072] tracking-widest uppercase">Monitor Keluarga</div>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <span id="m-online-badge"
        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[10px] font-black tracking-wider border
          <?= $isOnline ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' ?>">
        <span id="m-online-dot" class="w-1.5 h-1.5 rounded-full <?= $isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' ?>"></span>
        <span id="m-online-label"><?= $isOnline ? 'AKTIF' : 'OFFLINE' ?></span>
      </span>
      <div style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:5px 12px;">
        <span id="m-clock" class="text-sm font-bold text-slate-700 tabular-nums">--:--:--</span>
      </div>
    </div>
  </div>

  <!-- ── MAIN ── -->
  <main class="mx-auto w-full px-4 py-6 flex-1">

    <!-- Identitas Pasien -->
    <div id="m-header-card" class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm mb-5 transition-all duration-500">
      <div class="text-[10px] font-black text-[#6b2072] uppercase tracking-widest mb-1">Pasien Anda</div>
      <h1 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($device['pasien']) ?></h1>
      <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2">
        <span class="text-xs font-semibold text-slate-500 flex items-center gap-1">
          <i class="bi bi-geo-alt-fill text-slate-400"></i>
          <?= htmlspecialchars($device['lokasi']) ?>
        </span>
        <span id="m-last-update" class="text-xs font-medium text-slate-400 flex items-center gap-1">
          <i class="bi bi-clock-history"></i>
          <?= $lastUpdate ? 'Update: ' . date('H:i:s', strtotime($lastUpdate)) : 'Belum ada data' ?>
        </span>
      </div>
    </div>

    <!-- Status Perawat (hanya tampil saat aktif) -->
    <div id="m-nurse-alert" class="<?= ($isOnline && $latest && $latest['nurse_call']) ? '' : 'hidden' ?> 
      bg-red-50 border border-red-300 rounded-2xl p-5 mb-5 transition-all duration-300">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 bg-red-500 text-white rounded-xl flex items-center justify-center text-xl nurse-pulse flex-shrink-0">
          <i class="bi bi-bell-fill"></i>
        </div>
        <div>
          <div class="text-sm font-black text-red-700 uppercase tracking-wide">Perawat Dipanggil</div>
          <div class="text-xs text-red-600 mt-1">Pasien Anda memerlukan bantuan. Tim medis sudah dihubungi dan sedang menuju lokasi.</div>
        </div>
      </div>
    </div>

    <!-- Botol + Persen -->
    <div class="grid grid-cols-2 gap-4 mb-4">

      <!-- Botol Visual -->
      <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col items-center justify-center gap-4">
        <div class="relative w-20 h-44 bg-slate-100 border-2 border-slate-200/80 rounded-t-xl rounded-b-3xl overflow-hidden shadow-inner shadow-slate-200/50">
          <div id="m-bottle-fluid"
            style="position:absolute;bottom:0;left:0;right:0;width:100%;height:<?= $persen ?>%;transition:height 1s ease-in-out;
              background:<?= !$isOnline ? '#94a3b8' : ($persen > 50 ? 'linear-gradient(to top,#6b2072,#a855f7)' : ($persen > 20 ? 'linear-gradient(to top,#d97706,#f59e0b)' : 'linear-gradient(to top,#dc2626,#f87171)')) ?>;
              <?= ($isOnline && $persen <= 20) ? 'animation:fluid-blink 1.5s infinite;' : '' ?>">
            <div style="position:absolute;top:0;left:0;right:0;height:6px;background:rgba(255,255,255,.2);"></div>
          </div>
        </div>
        <div class="text-center">
          <div id="m-persen" class="text-3xl font-black text-slate-900"><?= number_format($persen, 0) ?>%</div>
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mt-0.5">Sisa Cairan</div>
        </div>
      </div>

      <!-- Info Cards -->
      <div class="flex flex-col gap-3">

        <!-- Volume -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex-1">
          <div class="w-8 h-8 bg-emerald-50 border border-emerald-100 text-emerald-500 rounded-lg flex items-center justify-center text-sm mb-2">
            <i class="bi bi-water"></i>
          </div>
          <div class="text-xl font-black text-slate-900">
            <span id="m-volume"><?= number_format($volumeSisa, 0) ?></span>
            <span class="text-xs font-bold text-slate-400">/ <?= number_format($volumeAwal, 0) ?> ml</span>
          </div>
          <div class="text-[10px] font-black text-emerald-600 uppercase tracking-wide mt-0.5">Volume Sisa</div>
        </div>

        <!-- Estimasi -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex-1">
          <div class="w-8 h-8 bg-purple-50 border border-purple-100 text-purple-500 rounded-lg flex items-center justify-center text-sm mb-2">
            <i class="bi bi-clock-history"></i>
          </div>
          <div id="m-estimasi" class="text-xl font-black text-slate-900"><?= $estJam ?>j <?= $estMnt ?>m</div>
          <div class="text-[10px] font-black text-purple-600 uppercase tracking-wide mt-0.5">Estimasi Selesai</div>
        </div>

      </div>
    </div>

    <!-- Status kondisi singkat -->
    <div id="m-status-card" class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm mb-5 flex items-center gap-3 transition-all">
      <div id="m-status-icon" class="w-10 h-10 rounded-xl flex items-center justify-center text-base flex-shrink-0 bg-emerald-50 border border-emerald-100 text-emerald-600">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <div>
        <div id="m-status-title" class="text-sm font-black text-slate-900">Kondisi Normal</div>
        <div id="m-status-desc" class="text-xs text-slate-500 mt-0.5">Infus berjalan lancar. Tidak ada yang perlu dikhawatirkan.</div>
      </div>
    </div>

    <!-- Chart Tren -->
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden mb-5">
      <div class="px-5 pt-5 pb-2 border-b border-slate-100">
        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">Tren Volume (30 Data Terakhir)</h3>
        <p class="text-[10px] text-slate-400 mt-0.5">Grafik diperbarui setiap 10 detik</p>
      </div>
      <div class="px-3 py-4 h-48">
        <canvas id="m-chart"></canvas>
      </div>
    </div>

    <!-- Riwayat Pemanggilan Suster -->
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden mb-5">
      <div class="px-5 pt-5 pb-3 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <i class="bi bi-bell-fill text-rose-500"></i> Riwayat Pemanggilan Suster
          </h3>
          <p class="text-[10px] text-slate-400 mt-0.5">10 panggilan terakhir</p>
        </div>
        <span id="m-nurse-log-count" class="text-[10px] font-black bg-rose-50 border border-rose-100 text-rose-600 px-2 py-0.5 rounded-full">
          <?= count($nurseLogs) ?> log
        </span>
      </div>

      <?php if (empty($nurseLogs)): ?>
      <div class="py-14 text-center">
        <i class="bi bi-bell-slash text-4xl text-slate-200 block mb-3"></i>
        <p class="text-xs font-bold text-slate-400">Belum ada riwayat pemanggilan</p>
      </div>
      <?php else: ?>
      <div id="m-nurse-log-list" class="divide-y divide-slate-100">
        <?php foreach ($nurseLogs as $log):
          $isActive   = (int)$log['status'] === 1;
          $createdFmt = date('d/m/Y H:i:s', strtotime($log['created_at']));
          $resolvedFmt = $log['resolved_at'] ? date('d/m/Y H:i:s', strtotime($log['resolved_at'])) : null;
          $resolvedBy  = $log['resolved_by'] ?? '';

          // Hitung durasi
          $durasi = '';
          if ($resolvedFmt && $log['resolved_at']) {
              $diff = strtotime($log['resolved_at']) - strtotime($log['created_at']);
              if ($diff < 60)       $durasi = $diff . ' detik';
              elseif ($diff < 3600) $durasi = floor($diff / 60) . ' menit ' . ($diff % 60) . ' detik';
              else                  $durasi = floor($diff / 3600) . ' jam ' . floor(($diff % 3600) / 60) . ' menit';
          }
        ?>
        <div class="px-5 py-4 flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center text-sm flex-shrink-0 mt-0.5
            <?= $isActive ? 'bg-red-100 border border-red-200 text-red-600' : 'bg-slate-100 border border-slate-200 text-slate-400' ?>">
            <i class="bi bi-bell<?= $isActive ? '-fill animate-pulse' : '' ?>"></i>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <?php if ($isActive): ?>
              <span class="text-[10px] font-black bg-red-100 text-red-700 border border-red-200 px-2 py-0.5 rounded-full uppercase tracking-wide animate-pulse">
                Aktif
              </span>
              <?php else: ?>
              <span class="text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full uppercase tracking-wide">
                Selesai
              </span>
              <?php endif; ?>
              <span class="text-xs font-bold text-slate-700"><?= $createdFmt ?></span>
            </div>
            <?php if (!$isActive && $resolvedFmt): ?>
            <div class="text-[11px] text-slate-500 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
              <span class="flex items-center gap-1">
                <i class="bi bi-check2-circle text-emerald-500"></i>
                Diselesaikan: <?= $resolvedFmt ?>
              </span>
              <?php if ($durasi): ?>
              <span class="flex items-center gap-1">
                <i class="bi bi-clock text-slate-400"></i>
                Durasi: <?= $durasi ?>
              </span>
              <?php endif; ?>
              <?php if ($resolvedBy): ?>
              <span class="flex items-center gap-1">
                <i class="bi bi-person text-slate-400"></i>
                <?= htmlspecialchars($resolvedBy === 'device' ? 'Tombol perangkat' : $resolvedBy) ?>
              </span>
              <?php endif; ?>
            </div>
            <?php elseif ($isActive): ?>
            <div class="text-[11px] text-red-500 font-semibold mt-1">
              <i class="bi bi-hourglass-split"></i> Menunggu respons suster...
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Info kaki -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 flex items-start gap-3">
      <i class="bi bi-info-circle-fill text-[#6b2072] flex-shrink-0 mt-0.5"></i>
      <p class="text-xs text-slate-600 leading-relaxed">
        Halaman ini hanya menampilkan data pasien Anda. Anda akan mendapat notifikasi WhatsApp otomatis
        bila ada kondisi yang memerlukan perhatian. Untuk informasi lebih lanjut, hubungi petugas ruangan.
      </p>
    </div>

  </main>

  <!-- Footer -->
  <footer class="text-center py-4 mt-4">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Smart Infus Monitoring System &bull; Family View</p>
  </footer>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
    const TOKEN = <?= json_encode($token) ?>;
    const REFRESH_INTERVAL = 10000; // 10 detik

    // ── Clock ──────────────────────────────────────────────
    (function tickClock() {
      function pad(n) { return String(n).padStart(2, '0'); }
      function update() {
        const now = new Date();
        const el = document.getElementById('m-clock');
        if (el) el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
      }
      update();
      setInterval(update, 1000);
    })();

    // ── Chart ─────────────────────────────────────────────
    const ctx = document.getElementById('m-chart').getContext('2d');
    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
          label: 'Volume (ml)',
          data: <?= json_encode($chartVolume) ?>,
          borderColor: '#6b2072',
          backgroundColor: 'rgba(107,32,114,.06)',
          borderWidth: 2.5,
          pointRadius: 2,
          pointBackgroundColor: '#6b2072',
          tension: 0.35,
          fill: true,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15,23,42,.95)',
            titleColor: '#fff',
            bodyColor: '#cbd5e1',
            padding: 10,
            titleFont: { size: 11, weight: 'bold', family: 'Plus Jakarta Sans' },
            bodyFont:  { size: 11, family: 'Plus Jakarta Sans' },
            callbacks: {
              label: ctx => `${Math.round(ctx.parsed.y)} ml`
            }
          }
        },
        scales: {
          x: {
            ticks: { maxTicksLimit: 6, font: { size: 10, family: 'Plus Jakarta Sans' }, color: '#94a3b8' },
            grid: { color: 'rgba(241,245,249,.8)' }
          },
          y: {
            ticks: { font: { size: 10, family: 'Plus Jakarta Sans', weight: 'bold' }, color: '#6b2072', mirror: true, z: 10 },
            grid: { color: 'rgba(241,245,249,.8)' }
          }
        }
      }
    });

    // ── Format Waktu ─────────────────────────────────────
    function formatTime(str) {
      if (!str) return '--:--:--';
      const d = new Date(str);
      return [d.getHours(), d.getMinutes(), d.getSeconds()].map(n => String(n).padStart(2,'0')).join(':');
    }

    // ── Update Status Card ────────────────────────────────
    function updateStatusCard(data, online) {
      const card   = document.getElementById('m-status-card');
      const icon   = document.getElementById('m-status-icon');
      const title  = document.getElementById('m-status-title');
      const desc   = document.getElementById('m-status-desc');

      if (!online) {
        card.style.borderColor = '';
        icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-base flex-shrink-0 bg-slate-100 border border-slate-200 text-slate-400';
        icon.innerHTML = '<i class="bi bi-wifi-off"></i>';
        title.textContent = 'Perangkat Offline';
        desc.textContent = 'Tidak ada sinyal dari perangkat. Tim medis sedang memantau.';
        return;
      }

      const vol = parseFloat(data.volume_sisa || 0);
      const tpm = parseFloat(data.tpm || 0);

      if (vol <= 20 && vol > 0) {
        card.style.cssText = 'border-color:#fcd34d;background:#fffbeb;';
        icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-base flex-shrink-0 bg-amber-100 border border-amber-200 text-amber-600';
        icon.innerHTML = '<i class="bi bi-droplet-half"></i>';
        title.textContent = 'Cairan Hampir Habis';
        desc.textContent = `Tersisa ${Math.round(vol)} ml. Tim medis akan segera mengganti kantong infus.`;
      } else if (tpm === 0 && vol > 0) {
        card.style.cssText = 'border-color:#d8b4fe;background:#faf5ff;';
        icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-base flex-shrink-0 bg-purple-100 border border-purple-200 text-purple-600';
        icon.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i>';
        title.textContent = 'Infus Perlu Diperhatikan';
        desc.textContent = 'Tim medis telah diberitahu dan sedang menangani.';
      } else if (vol === 0) {
        card.style.cssText = 'border-color:#a7f3d0;background:#ecfdf5;';
        icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-base flex-shrink-0 bg-emerald-100 border border-emerald-200 text-emerald-600';
        icon.innerHTML = '<i class="bi bi-check2-all"></i>';
        title.textContent = 'Infus Selesai';
        desc.textContent = 'Cairan infus sudah habis. Silakan hubungi perawat jika diperlukan.';
      } else {
        card.style.cssText = '';
        icon.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-base flex-shrink-0 bg-emerald-50 border border-emerald-100 text-emerald-600';
        icon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
        title.textContent = 'Kondisi Normal';
        desc.textContent = 'Infus berjalan lancar. Tidak ada yang perlu dikhawatirkan.';
      }
    }

    // ── Main Refresh ─────────────────────────────────────
    async function refresh() {
      try {
        const res  = await fetch(`api/get_monitor.php?token=${encodeURIComponent(TOKEN)}&_=${Date.now()}`, { cache: 'no-store' });
        const json = await res.json();
        if (json.status !== 'ok' || !json.data) return;

        const dev = json.data;
        const persen     = parseFloat(dev.persen     || 0);
        const vol        = parseFloat(dev.volume_sisa || 0);
        const volAwal    = parseFloat(dev.volume_awal || 500);
        const tpm        = parseFloat(dev.tpm        || 0);
        const estJam     = parseInt(dev.estimasi_jam  || 0);
        const estMnt     = parseInt(dev.estimasi_mnt  || 0);
        const nurseCall  = parseInt(dev.nurse_call    || 0);
        const lastUpdate = dev.created_at || null;
        const online     = dev.is_online !== undefined ? Boolean(dev.is_online) : ((Date.now() - new Date(lastUpdate).getTime()) < 30000);

        // Online badge
        const badge = document.getElementById('m-online-badge');
        const dot   = document.getElementById('m-online-dot');
        const lbl   = document.getElementById('m-online-label');
        if (online) {
          badge.className = badge.className.replace(/bg-slate-\S+|text-slate-\S+|border-slate-\S+/g,'');
          badge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:8px;font-size:10px;font-weight:900;letter-spacing:.05em;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;';
          dot.style.cssText = 'width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;animation:pulse 1.5s infinite;';
          lbl.textContent = 'AKTIF';
        } else {
          badge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:8px;font-size:10px;font-weight:900;letter-spacing:.05em;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;';
          dot.style.cssText = 'width:6px;height:6px;border-radius:50%;background:#94a3b8;display:inline-block;';
          lbl.textContent = 'OFFLINE';
        }

        // Last update
        const lastEl = document.getElementById('m-last-update');
        if (lastEl) lastEl.innerHTML = `<i class="bi bi-clock-history"></i> Update: ${formatTime(lastUpdate)}`;

        // Volume & persen
        const volEl  = document.getElementById('m-volume');
        const perEl  = document.getElementById('m-persen');
        const estEl  = document.getElementById('m-estimasi');
        if (volEl)  volEl.textContent = Math.round(vol);
        if (perEl)  perEl.textContent = persen.toFixed(0) + '%';
        if (estEl)  estEl.textContent = `${estJam}j ${estMnt}m`;

        // Botol visual
        const fluid = document.getElementById('m-bottle-fluid');
        if (fluid) {
          fluid.style.height = Math.min(100, Math.max(0, persen)) + '%';
          if (!online) {
            fluid.style.background = '#94a3b8';
            fluid.style.animation  = 'none';
          } else if (persen <= 20) {
            fluid.style.background = 'linear-gradient(to top, #dc2626, #f87171)';
            fluid.style.animation  = 'fluid-blink 1.5s infinite';
          } else if (persen <= 50) {
            fluid.style.background = 'linear-gradient(to top, #d97706, #f59e0b)';
            fluid.style.animation  = 'none';
          } else {
            fluid.style.background = 'linear-gradient(to top, #6b2072, #a855f7)';
            fluid.style.animation  = 'none';
          }
        }

        // Header card — merah saat nurse call
        const headerCard = document.getElementById('m-header-card');
        if (headerCard) {
          if (nurseCall === 1 && online) {
            headerCard.style.cssText = 'border:1.5px solid #fca5a5;background:rgba(254,242,242,.5);border-radius:1rem;padding:1.25rem;margin-bottom:1.25rem;';
          } else {
            headerCard.style.cssText = '';
          }
        }

        // Nurse call alert
        const nurseAlert = document.getElementById('m-nurse-alert');
        if (nurseAlert) {
          if (nurseCall === 1 && online) nurseAlert.classList.remove('hidden');
          else                           nurseAlert.classList.add('hidden');
        }

        // Status card
        updateStatusCard(dev, online);

      } catch (e) {
        console.warn('Monitor refresh error:', e);
      }
    }

    // ── Chart Refresh ─────────────────────────────────────
    async function refreshChart() {
      try {
        const res  = await fetch(`api/get_monitor.php?token=${encodeURIComponent(TOKEN)}&history=1&limit=30&_=${Date.now()}`, { cache: 'no-store' });
        const json = await res.json();
        if (json.status !== 'ok') return;
        const data = json.data;

        chart.data.labels           = data.map(h => { const d = new Date(h.created_at); return [d.getHours(),d.getMinutes()].map(n=>String(n).padStart(2,'0')).join(':'); });
        chart.data.datasets[0].data = data.map(h => parseFloat(h.volume_sisa));
        chart.update('none');
      } catch (e) { console.warn('Chart refresh error:', e); }
    }

    // ── Nurse Log Refresh ────────────────────────────────
    async function refreshNurseLog() {
      try {
        const res  = await fetch(`api/get_monitor.php?token=${encodeURIComponent(TOKEN)}&nurse_log=1&limit=10&_=${Date.now()}`, { cache: 'no-store' });
        const json = await res.json();
        if (json.status !== 'ok') return;

        const logs = json.data;
        const list  = document.getElementById('m-nurse-log-list');
        const count = document.getElementById('m-nurse-log-count');

        if (count) count.textContent = logs.length + ' log';
        if (!list) return;

        if (logs.length === 0) {
          list.innerHTML = `<div style="padding:56px 0;text-align:center;">
            <i class="bi bi-bell-slash" style="font-size:2.25rem;color:#e2e8f0;display:block;margin-bottom:12px;"></i>
            <p style="font-size:12px;font-weight:700;color:#94a3b8;">Belum ada riwayat pemanggilan</p>
          </div>`;
          return;
        }

        list.innerHTML = logs.map(log => {
          const isActive  = parseInt(log.status) === 1;
          const created   = formatDateTime(log.created_at);
          const resolved  = log.resolved_at ? formatDateTime(log.resolved_at) : null;
          const resolvedBy = log.resolved_by || '';

          // Durasi
          let durasi = '';
          if (log.resolved_at && log.created_at) {
            const diff = Math.round((new Date(log.resolved_at) - new Date(log.created_at)) / 1000);
            if (diff < 60)       durasi = diff + ' detik';
            else if (diff < 3600) durasi = Math.floor(diff/60) + ' menit ' + (diff%60) + ' detik';
            else                  durasi = Math.floor(diff/3600) + ' jam ' + Math.floor((diff%3600)/60) + ' menit';
          }

          const statusBadge = isActive
            ? `<span style="font-size:10px;font-weight:900;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.05em;">Aktif</span>`
            : `<span style="font-size:10px;font-weight:900;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.05em;">Selesai</span>`;

          const iconStyle = isActive
            ? 'background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;'
            : 'background:#f1f5f9;border:1px solid #e2e8f0;color:#94a3b8;';

          let detail = '';
          if (!isActive && resolved) {
            detail += `<div style="font-size:11px;color:#64748b;margin-top:4px;display:flex;flex-wrap:wrap;gap:8px;">`;
            detail += `<span><i class="bi bi-check2-circle" style="color:#10b981;"></i> Selesai: ${resolved}</span>`;
            if (durasi) detail += `<span><i class="bi bi-clock" style="color:#94a3b8;"></i> ${durasi}</span>`;
            if (resolvedBy) {
              const byLabel = resolvedBy === 'device' ? 'Tombol perangkat' : resolvedBy;
              detail += `<span><i class="bi bi-person" style="color:#94a3b8;"></i> ${byLabel}</span>`;
            }
            detail += `</div>`;
          } else if (isActive) {
            detail = `<div style="font-size:11px;color:#ef4444;font-weight:600;margin-top:4px;"><i class="bi bi-hourglass-split"></i> Menunggu respons suster...</div>`;
          }

          return `
            <div style="padding:16px 20px;display:flex;align-items:flex-start;gap:12px;border-bottom:1px solid #f1f5f9;">
              <div style="width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;margin-top:2px;${iconStyle}">
                <i class="bi bi-bell${isActive ? '-fill' : ''}"></i>
              </div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                  ${statusBadge}
                  <span style="font-size:12px;font-weight:700;color:#334155;">${created}</span>
                </div>
                ${detail}
              </div>
            </div>`;
        }).join('');

      } catch (e) { console.warn('Nurse log refresh error:', e); }
    }

    function formatDateTime(str) {
      if (!str) return '-';
      const d = new Date(str);
      const date = [d.getDate(), d.getMonth()+1, d.getFullYear()].map((n,i) => i<2 ? String(n).padStart(2,'0') : n).join('/');
      const time = [d.getHours(), d.getMinutes(), d.getSeconds()].map(n => String(n).padStart(2,'0')).join(':');
      return `${date} ${time}`;
    }

    // ── Init ──────────────────────────────────────────────
    refresh();
    refreshChart();
    refreshNurseLog();
    setInterval(refresh, REFRESH_INTERVAL);
    setInterval(refreshChart, 60000);       // chart tiap 1 menit
    setInterval(refreshNurseLog, 30000);    // nurse log tiap 30 detik
  </script>

</body>
</html>
