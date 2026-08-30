<?php
// =====================================================
// SMART INFUS — DASHBOARD UTAMA (REFACTORED TAILWIND)
// =====================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireAccess('dashboard');

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
  $isDevOnline = $dev['last_update'] && (strtotime($dev['last_update']) >= time() - 30);
  if ($dev['nurse_call'] && $isDevOnline)                      $nurseCallCount++;
  if (
    $isDevOnline &&
    $dev['persen'] !== null &&
    $dev['persen'] <= 20
  ) {
    $lowVolumeCount++;
  }
  if ($isDevOnline)                                            $onlineCount++;
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

<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-primary/10 selection:text-primary">

  <div id="audio-blocked-modal" class="hidden fixed inset-0 z-100 bg-slate-950/70 backdrop-blur-md flex items-center justify-center p-4 select-none animate-fade-in">
    <div class="bg-white p-6 rounded-2xl shadow-2xl max-w-lg w-full border border-slate-200">

      <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-4">
        <div class="w-10 h-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center shrink-0 border border-red-100">
          <i class="bi bi-volume-mute-fill text-xl"></i>
        </div>
        <div>
          <h3 class="text-sm font-black text-slate-900 tracking-wide uppercase">Autoplay Audio Diblokir Browser</h3>
          <p class="text-[11px] font-bold text-red-500">Peringatan: Suara Alarm Nurse Call tidak akan berbunyi!</p>
        </div>
      </div>

      <div class="text-xs text-slate-600 space-y-3 leading-relaxed mb-6">
        <p>Untuk memastikan keselamatan pasien di bangsal perawatan, ikuti panduan bypass proteksi audio Chrome di komputer <i>Nurse Station</i> ini:</p>

        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 font-medium text-slate-700 space-y-2">
          <div class="flex gap-2">
            <span class="shrink-0 w-4 h-4 bg-slate-900 text-white rounded-full text-[9px] font-bold flex items-center justify-center">1</span>
            <span>Buka tab baru di Chrome, lalu ketik/salin tautan ini di address bar:
              <button
                type="button"
                onclick="copyChromeSettings(this)"
                class="inline-flex items-center gap-2 mt-1 font-mono font-bold hover:bg-slate-50 transition">
                <span class="flex items-center gap-2 bg-white border border-slate-200 rounded px-2 py-1 font-mono font-bold hover:bg-slate-50 transition">chrome://settings/content/sound</span>
                <span class="copy-status text-xs hidden text-gray-500">
                  Copied
                </span>
              </button>
            </span>
          </div>
          <div class="flex gap-2">
            <span class="shrink-0 w-4 h-4 bg-slate-900 text-white rounded-full text-[9px] font-bold flex items-center justify-center">2</span>
            <span>Scroll ke bawah ke menu <b class="text-slate-900">"Allowed to play sound"</b> (Diizinkan memutar suara) lalu klik <b class="text-slate-900">Add</b>.</span>
          </div>
          <div class="flex gap-2">
            <span class="shrink-0 w-4 h-4 bg-slate-900 text-white rounded-full text-[9px] font-bold flex items-center justify-center">3</span>
            <span class="flex flex-col">
              <span>Masukkan domain/URL dashboard ini:</span>
              <button
                type="button"
                onclick="copyUrlSettings(this)"
                class="inline-flex items-center gap-2 mt-1 font-mono font-bold hover:bg-slate-50 transition">
                <span class="flex items-center gap-2 bg-white border border-slate-200 rounded px-2 py-1 font-mono font-bold hover:bg-slate-50 transition"><?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" ?></span>
                <span class="copy-status text-xs hidden text-gray-500">
                  Copied
                </span>
              </button>
            </span>
          </div>
        </div>
        <p class="text-[11px] text-slate-400 italic">Setelah pengaturan di atas ditambahkan, muat ulang (refresh) halaman ini. Modal ini akan hilang secara otomatis.</p>
      </div>

      <div class="flex gap-3">
        <button onclick="window.location.reload()" class="flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all border border-slate-200">
          <i class="bi bi-arrow-clockwise mr-1"></i> CEK ULANG (REFRESH)
        </button>
        <button id="audio-allow-temp" onclick="document.getElementById('audio-blocked-modal').classList.add('hidden')" class="flex-1 py-2 bg-primary hover:bg-[#55195b] text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-primary/20">
          IZINKAN SEMENTARA
        </button>
      </div>

    </div>
  </div>

  <!-- Hidden alarm audio used for autoplay detection and manual triggering -->
  <audio
    id="alarm-sound"
    src="assets/nurse-call.mp3"
    preload="auto"
    playsinline>
  </audio>
  <script>
    function copyChromeSettings(btn) {
      navigator.clipboard.writeText(
        'chrome://settings/content/sound'
      );

      const status = btn.querySelector('.copy-status');
      status.classList.remove('hidden');

      setTimeout(() => {
        status.classList.add('hidden');
      }, 2000);
    }

    function copyUrlSettings(btn) {
      navigator.clipboard.writeText(
        '<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" ?>'
      );

      const status = btn.querySelector('.copy-status');
      status.classList.remove('hidden');

      setTimeout(() => {
        status.classList.add('hidden');
      }, 2000);
    }

    const alarmSound =
      document.getElementById('alarm-sound');

    const audioModal =
      document.getElementById('audio-blocked-modal');

    async function checkAutoplayPermission() {

      if (!alarmSound || !audioModal) return;

      try {

        await new Promise((resolve) => {

          if (alarmSound.readyState >= 3) {
            resolve();
          } else {

            alarmSound.addEventListener(
              'canplaythrough',
              resolve, {
                once: true
              }
            );
          }

        });

        alarmSound.muted = true;

        await alarmSound.play();

        alarmSound.pause();
        alarmSound.currentTime = 0;
        alarmSound.muted = false;

        audioModal.classList.add('hidden');

        console.log('Autoplay diizinkan');

      } catch (err) {

        console.error(err);

        audioModal.classList.remove('hidden');

        console.log('Autoplay diblokir');
      }
    }

    function unlockAudio() {

      const Ctx =
        window.AudioContext ||
        window.webkitAudioContext;

      if (Ctx) {

        try {

          if (!window.__audioCtx) {
            window.__audioCtx = new Ctx();
          }

          if (
            window.__audioCtx.state === 'suspended'
          ) {
            window.__audioCtx.resume();
          }

        } catch (e) {}
      }
    }

    window.addEventListener('load', () => {

      setTimeout(() => {
        checkAutoplayPermission();
      }, 300);

    });

    window.addEventListener('load', () => {

      checkAutoplayPermission();

      const allowBtn =
        document.getElementById(
          'audio-allow-temp'
        );

      if (allowBtn) {

        allowBtn.addEventListener('click', async () => {

          unlockAudio();

          try {

            await alarmSound.play();

            alarmSound.pause();
            alarmSound.currentTime = 0;

          } catch (e) {}

          audioModal.classList.add('hidden');
        });
      }
    });

    function triggerEmergencyAlarm(shouldPlay) {

      if (!alarmSound) return;

      unlockAudio();

      if (shouldPlay) {

        alarmSound.play().catch(err => {

          console.error(
            'Alarm gagal diputar',
            err
          );

          audioModal.classList.remove('hidden');
        });

      } else {

        alarmSound.pause();
        alarmSound.currentTime = 0;
      }
    }
  </script>

  <?php require __DIR__ . '/config/navbar.php'; ?>

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
          <span class="w-1.5 h-4 bg-primary rounded-full inline-block"></span>
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
        $isNurse     = (bool)$dev['nurse_call'] && $isOnline;

        // Semantic Rules (Updated with new percentage layout & color schemes)
        if (!$isOnline) {
          // Jika offline, beri tema redup/slate abu-abu
          $statusColor = 'border-slate-200 bg-slate-50/50';
          $barColor    = 'bg-slate-400';
          $liquidColor = 'bg-slate-300';
        } elseif ($isNurse) {
          // Kritis darurat (Nurse Call)
          $statusColor = 'border-red-500 ring-4 ring-red-500/10 bg-red-50/30';
          $barColor    = 'bg-red-500';
          $liquidColor = 'bg-red-400';
        } elseif ($persen > 30) {
          // > 30% = Cyan
          $statusColor = 'border-cyan-200 hover:border-cyan-300 bg-cyan-50/10';
          $barColor    = 'bg-cyan-500';
          $liquidColor = 'bg-cyan-400';
        } elseif ($persen > 20) {
          // 20-30% = Amber
          $statusColor = 'border-amber-200 hover:border-amber-300 bg-amber-50/10';
          $barColor    = 'bg-amber-500';
          $liquidColor = 'bg-amber-400';
        } else {
          // <= 20% = Kritis Volume Red
          $statusColor = 'border-red-400 ring-4 ring-red-500/5 bg-red-50/20';
          $barColor    = 'bg-red-500';
          $liquidColor = 'bg-red-400';
        }
      ?>
        <div id="card-<?= htmlspecialchars($dev['device_id']) ?>"
          data-pasien="<?= htmlspecialchars($dev['pasien']) ?>"
          data-lokasi="<?= htmlspecialchars($dev['lokasi']) ?>"
          data-last-created-at="<?= htmlspecialchars($dev['last_update'] ?? '') ?>"
          class="border rounded-2xl p-5 relative overflow-hidden shadow-sm flex flex-col justify-between transition-all <?= $statusColor ?>">

          <div>
            <!-- Header Cell: Badges + Action Buttons -->
            <div class="flex items-start justify-between gap-2 mb-4">
              <div class="flex items-center gap-3">
                <!-- Physical Bottle Indicator Simulation -->
                <div class="w-8 h-12 bg-slate-100 border-2 border-slate-200 rounded-t-md rounded-b-xl relative overflow-hidden shrink-0 shadow-inner">
                  <div data-role="bottle-liquid"
                    style="position:absolute; bottom:0; left:0; right:0; width:100%; height:<?= $persen ?>%; background:<?= !$isOnline ? '#94a3b8' : ($isNurse ? '#ef4444' : ($persen > 30 ? '#06b6d4' : ($persen > 20 ? '#f59e0b' : '#ef4444'))) ?>; transition:height 1s ease-in-out;">
                    <div style="width:100%; height:4px; background:rgba(255,255,255,0.2); position:absolute; top:0;"></div>
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
                <span data-role="online-badge" class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black <?= $isOnline ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                  <span class="w-1.5 h-1.5 rounded-full mr-1.5 <?= $isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' ?>"></span><?= $isOnline ? 'ONLINE' : 'OFFLINE' ?>
                </span>

                <?php if ($isNurse): ?>
                  <span data-role="nurse-badge" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold text-white tracking-wider uppercase bg-red-500 animate-medical-pulse">
                    <i class="bi bi-bell-fill text-[8px]"></i> NURSE CALL
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Patient Identity Attachment -->
            <div class="bg-slate-100/80 border border-slate-200/60 rounded-xl p-2.5 mb-4 flex items-center justify-between">
              <div class="flex items-center gap-2 truncate">
                <div class="w-6 h-6 bg-white border border-slate-200 rounded-full flex items-center justify-center shrink-0 text-slate-500">
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
                <span data-role="persen-text" class="text-slate-700 font-extrabold text-xs"><?= number_format($persen, 0) ?>%</span>
              </div>
              <div class="w-full h-2 bg-slate-100 border border-slate-200/80 rounded-full overflow-hidden">
                <div data-role="progress-bar"
                  style="height:100%; border-radius:9999px; transition:width .5s ease-in-out; width:<?= $persen ?>%; background:<?= !$isOnline ? '#cbd5e1' : ($isNurse ? '#ef4444' : ($persen > 30 ? '#06b6d4' : ($persen > 20 ? '#f59e0b' : '#ef4444'))) ?>;"></div>
              </div>

              <div data-role="low-warning" class="mt-2 text-[10px] font-bold text-red-500 flex items-center gap-1 <?= $persen <= 20 ? '' : 'hidden' ?>">
                <i class="bi bi-exclamation-triangle-fill"></i> Perhatian: Kritis, segera ganti infus baru!
              </div>
            </div>

            <!-- Time Frame Remaining Estimates -->
            <?php
              $isDevMacet = $isOnline && ((float)($dev['tpm'] ?? 0) === 0.0) && ((float)($dev['volume_sisa'] ?? 0) > 0);
            ?>
            <div class="bg-slate-50/60 border border-slate-200/40 rounded-xl px-3 py-2 flex items-center justify-between mb-4">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Estimasi Sisa Waktu</span>
              <span data-role="estimasi-value" class="text-xs font-extrabold text-slate-700 bg-white px-2 py-0.5 rounded-md border border-slate-200/60 shadow-sm tabular-nums">
                <?= $isDevMacet ? '<i class="bi bi-pause-circle mr-1 text-purple-500"></i><span class="text-purple-700 font-bold">Terhenti</span>' : ($dev['estimasi_jam'] . 'j ' . $dev['estimasi_mnt'] . 'm') ?>
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

    <!-- JADWAL PENGGANTIAN INFUS -->
    <div class="mt-10">
      <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-4">
        <div>
          <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
            <span class="w-1.5 h-4 bg-purple-500 rounded-full inline-block"></span>
            Jadwal Penggantian Infus
          </h2>
          <p class="text-[11px] text-slate-400 mt-0.5">Diurutkan dari yang paling cepat habis — hanya device aktif & online</p>
        </div>
        <span id="sched-count" class="text-[10px] font-black bg-purple-50 border border-purple-100 text-purple-600 px-2.5 py-1 rounded-full">
          <?php
            $onlineDevs = array_filter($devices, fn($d) =>
              $d['last_update'] && (strtotime($d['last_update']) >= time() - 30) &&
              ($d['persen'] !== null) && $d['persen'] > 0
            );
            echo count($onlineDevs) . ' device aktif';
          ?>
        </span>
      </div>

      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-400 tracking-wider uppercase">
                <th class="py-3 px-5">#</th>
                <th class="py-3 px-5">Pasien &amp; Lokasi</th>
                <th class="py-3 px-5">Sisa Cairan</th>
                <th class="py-3 px-5">Estimasi Habis</th>
                <th class="py-3 px-5">Target Waktu</th>
                <th class="py-3 px-5">Status</th>
                <th class="py-3 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody id="sched-tbody" class="divide-y divide-slate-100 text-sm">
              <?php
                // Ambil hanya device online dengan volume > 0, sort by estimasi menit total
                $schedDevs = array_filter($devices, fn($d) =>
                  $d['last_update'] && (strtotime($d['last_update']) >= time() - 30) &&
                  ($d['persen'] !== null) && $d['persen'] > 0
                );
                usort($schedDevs, function($a, $b) {
                  $isMacetA = (float)($a['tpm'] ?? 0) === 0.0 && (float)($a['volume_sisa'] ?? 0) > 0;
                  $isMacetB = (float)($b['tpm'] ?? 0) === 0.0 && (float)($b['volume_sisa'] ?? 0) > 0;
                  if ($isMacetA && !$isMacetB) return 1;
                  if (!$isMacetA && $isMacetB) return -1;
                  if ($isMacetA && $isMacetB) {
                    return ((float)($a['volume_sisa'] ?? 0) <=> (float)($b['volume_sisa'] ?? 0));
                  }
                  $mA = (int)($a['estimasi_jam'] ?? 0) * 60 + (int)($a['estimasi_mnt'] ?? 0);
                  $mB = (int)($b['estimasi_jam'] ?? 0) * 60 + (int)($b['estimasi_mnt'] ?? 0);
                  return $mA <=> $mB;
                });

                if (empty($schedDevs)):
              ?>
              <tr>
                <td colspan="7" class="py-12 text-center text-xs font-bold text-slate-400 uppercase tracking-wider">
                  <i class="bi bi-clock text-3xl block text-slate-300 mb-2"></i>
                  Belum ada device online dengan cairan aktif
                </td>
              </tr>
              <?php else:
                foreach ($schedDevs as $i => $dev):
                  $estJam  = (int)($dev['estimasi_jam'] ?? 0);
                  $estMnt  = (int)($dev['estimasi_mnt'] ?? 0);
                  $totalMnt = $estJam * 60 + $estMnt;
                  $persen  = (float)($dev['persen'] ?? 0);
                  $vol     = (float)($dev['volume_sisa'] ?? 0);
                  $volAwal = (float)($dev['volume_awal'] ?? 500);
                  $tpm     = (float)($dev['tpm'] ?? 0);
                  $isMacet = ($tpm === 0.0 && $vol > 0);

                  if ($isMacet) {
                    $urgency = 'macet';
                    $estDisplay = '<span class="text-purple-600 font-bold">Terhenti</span>';
                    $estSub = 'aliran macet (0 TPM)';
                    $targetDisplay = '<span class="text-slate-400 font-bold">—</span>';
                    $targetSub = 'aliran terhenti';
                  } else {
                    // Hitung target waktu penggantian
                    $targetTs  = time() + $totalMnt * 60;
                    $targetStr = date('H:i', $targetTs);
                    $targetDay = date('d/m', $targetTs);
                    $todayDay  = date('d/m');
                    $targetDisplay = $targetStr . ($targetDay !== $todayDay ? ' <span class="text-[10px] text-slate-400 ml-1">' . $targetDay . '</span>' : '');
                    $estDisplay = ($estJam > 0 ? $estJam . 'j ' : '') . $estMnt . 'm';
                    $estSub = 'dari sekarang';
                    $targetSub = 'estimasi habis';

                    // Kategori urgensi
                    if ($totalMnt <= 15) {
                      $urgency = 'critical';  // merah — harus segera
                    } elseif ($totalMnt <= 45) {
                      $urgency = 'warning';   // kuning — siapkan
                    } else {
                      $urgency = 'normal';    // hijau — aman
                    }
                  }

                  $urgencyStyle = match($urgency) {
                    'macet'    => ['bg' => '#faf5ff', 'border' => '#c084fc', 'text' => '#7c3aed', 'badge_bg' => '#f3e8ff', 'badge_text' => '#7c3aed', 'label' => 'MACET', 'icon' => 'exclamation-triangle-fill'],
                    'critical' => ['bg' => '#fef2f2', 'border' => '#fca5a5', 'text' => '#dc2626', 'badge_bg' => '#fee2e2', 'badge_text' => '#b91c1c', 'label' => 'SEGERA', 'icon' => 'exclamation-triangle-fill'],
                    'warning'  => ['bg' => '#fffbeb', 'border' => '#fcd34d', 'text' => '#d97706', 'badge_bg' => '#fef3c7', 'badge_text' => '#92400e', 'label' => 'SIAPKAN', 'icon' => 'clock-fill'],
                    default    => ['bg' => '#fff',    'border' => '#e2e8f0', 'text' => '#64748b', 'badge_bg' => '#f0fdf4', 'badge_text' => '#166534', 'label' => 'NORMAL', 'icon' => 'check-circle-fill'],
                  };
              ?>
              <tr id="sched-row-<?= htmlspecialchars($dev['device_id']) ?>"
                  style="background:<?= $urgencyStyle['bg'] ?>;border-left:3px solid <?= $urgencyStyle['border'] ?>;"
                  class="transition-colors">

                <!-- Nomor urut -->
                <td class="py-3.5 px-5">
                  <span class="text-xs font-black text-slate-500"><?= $i + 1 ?></span>
                </td>

                <!-- Pasien & Lokasi -->
                <td class="py-3.5 px-5">
                  <div class="font-bold text-slate-900 text-sm"><?= htmlspecialchars($dev['pasien'] ?: '—') ?></div>
                  <div class="text-[11px] text-slate-500 flex items-center gap-1 mt-0.5">
                    <i class="bi bi-geo-alt text-slate-400"></i><?= htmlspecialchars($dev['lokasi'] ?: '—') ?>
                    <span class="text-slate-300 mx-1">·</span>
                    <span class="font-mono text-slate-400"><?= htmlspecialchars($dev['device_id']) ?></span>
                  </div>
                </td>

                <!-- Sisa Cairan -->
                <td class="py-3.5 px-5">
                  <div class="flex items-center gap-2">
                    <!-- Mini bottle -->
                    <div class="w-4 h-8 bg-slate-100 border border-slate-200 rounded-t rounded-b-lg relative overflow-hidden flex-shrink-0">
                      <div style="position:absolute;bottom:0;left:0;right:0;height:<?= min(100,$persen) ?>%;background:<?= $persen > 30 ? '#06b6d4' : ($persen > 20 ? '#f59e0b' : '#ef4444') ?>;transition:height .5s;"></div>
                    </div>
                    <div>
                      <div class="text-sm font-black text-slate-900 tabular-nums"><?= number_format($vol, 0) ?> <span class="text-xs font-medium text-slate-400">ml</span></div>
                      <div class="text-[10px] font-bold" style="color:<?= $urgencyStyle['text'] ?>;"><?= number_format($persen, 0) ?>%</div>
                    </div>
                  </div>
                </td>

                <!-- Estimasi Habis -->
                <td class="py-3.5 px-5">
                  <div class="text-sm font-black text-slate-900 tabular-nums" data-sched-est="<?= htmlspecialchars($dev['device_id']) ?>">
                    <?= $estDisplay ?>
                  </div>
                  <div class="text-[10px] text-slate-400 mt-0.5"><?= $estSub ?></div>
                </td>

                <!-- Target Waktu -->
                <td class="py-3.5 px-5">
                  <div class="text-sm font-bold text-slate-900 tabular-nums" data-sched-target="<?= htmlspecialchars($dev['device_id']) ?>">
                    <?= $targetDisplay ?>
                  </div>
                  <div class="text-[10px] text-slate-400 mt-0.5"><?= $targetSub ?></div>
                </td>

                <!-- Status Badge -->
                <td class="py-3.5 px-5">
                  <span data-sched-badge="<?= htmlspecialchars($dev['device_id']) ?>"
                        style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:900;letter-spacing:.04em;background:<?= $urgencyStyle['badge_bg'] ?>;color:<?= $urgencyStyle['badge_text'] ?>;">
                    <i class="bi bi-<?= $urgencyStyle['icon'] ?>" style="font-size:9px;"></i>
                    <?= $urgencyStyle['label'] ?>
                  </span>
                </td>

                <!-- Aksi -->
                <td class="py-3.5 px-5 text-right">
                  <a href="detail.php?id=<?= urlencode($dev['device_id']) ?>"
                     class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all active:scale-95"
                     style="background:#0f172a;color:#fff;">
                     <i class="bi bi-bar-chart-fill"></i> Detail
                  </a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Legend -->
        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 flex flex-wrap gap-4 text-[10px] font-bold text-slate-500">
          <span class="flex items-center gap-1.5">
            <span style="width:10px;height:10px;border-radius:50%;background:#9333ea;display:inline-block;"></span> Macet (0 TPM) — Periksa aliran
          </span>
          <span class="flex items-center gap-1.5">
            <span style="width:10px;height:10px;border-radius:50%;background:#dc2626;display:inline-block;"></span> ≤ 15 menit — Segera ganti
          </span>
          <span class="flex items-center gap-1.5">
            <span style="width:10px;height:10px;border-radius:50%;background:#d97706;display:inline-block;"></span> 16–45 menit — Siapkan kantong baru
          </span>
          <span class="flex items-center gap-1.5">
            <span style="width:10px;height:10px;border-radius:50%;background:#16a34a;display:inline-block;"></span> &gt; 45 menit — Normal
          </span>
        </div>
      </div>
    </div>

    <!-- NURSE CALL ARCHIVE CHRONOLOGY LOGS -->
    <div class="mt-12">
      <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-4">
        <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
          <span class="w-1.5 h-4 bg-red-500 rounded-full inline-block"></span>
          Riwayat Panggilan Darurat (Nurse Call)
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

  <script src="assets/js/dashboard.js"></script>
</body>

</html>