<?php
// =====================================================
// SMART INFUS — DASHBOARD UTAMA
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
  if ($dev['nurse_call'])                                  $nurseCallCount++;
  if ($dev['persen'] !== null && $dev['persen'] <= 20)     $lowVolumeCount++;
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
  <title>Smart Infus — Dashboard</title>
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
    .si-clock { font-size: 13px; font-weight: 700; color: #f1f5f9; font-variant-numeric: tabular-nums; }

    /* CARDS */
    .si-card { background: #1a1d27; border: 1px solid rgba(255,255,255,0.07); border-radius: 16px; }
    .si-section-title { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.1em; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .si-section-title::before { content: ''; width: 3px; height: 14px; background: #3b82f6; border-radius: 2px; display: inline-block; }

    /* DEVICE CARD */
    .device-card { overflow: hidden; position: relative; transition: transform .2s, box-shadow .2s; }
    .device-card:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(0,0,0,0.4); }

    /* BOTTLE */
    .infusion-bottle { border: 2px solid rgba(255,255,255,0.1); border-radius: 6px 6px 12px 12px; position: relative; overflow: hidden; background: #21253a; flex-shrink: 0; }
    .infusion-liquid { position: absolute; bottom: 0; left: 0; right: 0; transition: height 1s ease-in-out; }

    /* PROGRESS BARS */
    .bar-blue   { background: linear-gradient(90deg, #2563eb, #60a5fa); }
    .bar-orange { background: linear-gradient(90deg, #d97706, #f59e0b); }
    .bar-red    { background: linear-gradient(90deg, #dc2626, #f87171); animation: blinkBar 1s ease-in-out infinite; }
    @keyframes blinkBar { 0%,100%{opacity:1} 50%{opacity:.5} }

    /* NURSE CALL ANIMATIONS */
    .pulse-danger { animation: pulseDanger 2s infinite; }
    @keyframes pulseDanger { 0%{box-shadow:0 0 0 0 rgba(239,68,68,.5)} 70%{box-shadow:0 0 0 14px rgba(239,68,68,0)} 100%{box-shadow:0 0 0 0 rgba(239,68,68,0)} }
    .nurse-ring { box-shadow: 0 0 0 3px rgba(239,68,68,.6); animation: nurseGlow 1.4s ease-in-out infinite; }
    @keyframes nurseGlow { 0%,100%{box-shadow:0 0 0 3px rgba(239,68,68,.5)} 50%{box-shadow:0 0 0 8px rgba(239,68,68,.15)} }
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
        <span id="clockText" class="si-clock">--:--:--</span>
      </div>
    </div>
  </nav>

  <main style="max-width:1280px;margin:0 auto;padding:32px 24px">

    <!-- STAT CARDS -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:32px">

      <!-- Total -->
      <div class="si-card" style="padding:20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <span style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em">Total Unit</span>
          <div style="width:36px;height:36px;background:rgba(59,130,246,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-layers" style="color:#3b82f6;font-size:16px"></i>
          </div>
        </div>
        <div id="stat-total" style="font-size:36px;font-weight:900;color:#f1f5f9;line-height:1"><?= $totalDevices ?></div>
        <div style="font-size:11px;color:#475569;margin-top:4px">Perangkat terdaftar</div>
      </div>

      <!-- Online -->
      <div class="si-card" style="padding:20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <span style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em">Online</span>
          <div style="width:36px;height:36px;background:rgba(16,185,129,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-wifi" style="color:#10b981;font-size:16px"></i>
          </div>
        </div>
        <div id="stat-online" style="font-size:36px;font-weight:900;color:#10b981;line-height:1"><?= $onlineCount ?></div>
        <div style="font-size:11px;color:#475569;margin-top:4px">Aktif &lt; 30 detik</div>
      </div>

      <!-- Low Volume -->
      <div class="si-card" style="padding:20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <span style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em">Low Vol</span>
          <div style="width:36px;height:36px;background:rgba(245,158,11,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-droplet-half" style="color:#f59e0b;font-size:16px"></i>
          </div>
        </div>
        <div id="stat-low" style="font-size:36px;font-weight:900;color:#f59e0b;line-height:1"><?= $lowVolumeCount ?></div>
        <div style="font-size:11px;color:#475569;margin-top:4px">Volume ≤ 20%</div>
      </div>

      <!-- Emergency -->
      <div id="stat-nurse-card" class="si-card <?= $nurseCallCount > 0 ? 'pulse-danger' : '' ?>" style="padding:20px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
          <span style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em">Emergency</span>
          <div style="width:36px;height:36px;background:rgba(239,68,68,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-bell-fill" style="color:#ef4444;font-size:16px"></i>
          </div>
        </div>
        <div id="stat-nurse" style="font-size:36px;font-weight:900;color:#ef4444;line-height:1"><?= $nurseCallCount ?></div>
        <div style="font-size:11px;color:#475569;margin-top:4px">Nurse call aktif</div>
      </div>

    </div>

    <!-- SECTION HEADER -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
      <div class="si-section-title" style="margin-bottom:0">Monitoring Real-time</div>
      <button onclick="refreshAll()" style="display:flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);border-radius:10px;color:#94a3b8;font-size:11px;font-weight:700;cursor:pointer;transition:all .15s" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
        <i class="bi bi-arrow-repeat"></i> REFRESH
      </button>
    </div>

    <!-- DEVICE GRID -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px">
      <?php foreach ($devices as $dev):
        $persen      = $dev['persen'] ?? 0;
        $isOnline    = $dev['last_update'] && (strtotime($dev['last_update']) >= time() - 30);
        $isNurse     = (bool)$dev['nurse_call'];
        $barClass    = $persen > 50 ? 'bar-blue' : ($persen > 20 ? 'bar-orange' : 'bar-red');
        $liqColor    = $persen > 50 ? 'linear-gradient(0deg,#2563eb,#60a5fa)' : ($persen > 20 ? 'linear-gradient(0deg,#d97706,#f59e0b)' : 'linear-gradient(0deg,#dc2626,#f87171)');
        $topGrad     = $isNurse ? 'linear-gradient(90deg,#dc2626,#f97316)' : 'linear-gradient(90deg,#2563eb,#60a5fa)';
      ?>
      <div id="card-<?= htmlspecialchars($dev['device_id']) ?>"
           data-pasien="<?= htmlspecialchars($dev['pasien']) ?>"
           data-lokasi="<?= htmlspecialchars($dev['lokasi']) ?>"
           class="si-card device-card <?= $isNurse ? 'nurse-ring' : '' ?>">

        <!-- accent bar top -->
        <div data-role="card-top" style="height:3px;background:<?= $topGrad ?>"></div>

        <div style="padding:18px">

          <!-- Row 1: bottle + nama + badges -->
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
            <div style="display:flex;align-items:center;gap:12px">
              <div class="infusion-bottle" style="width:36px;height:56px">
                <div data-role="bottle-liquid" class="infusion-liquid" style="height:<?= $persen ?>%;background:<?= $liqColor ?>"></div>
              </div>
              <div>
                <div style="font-size:13px;font-weight:700;color:#f1f5f9"><?= htmlspecialchars($dev['nama']) ?></div>
                <div style="font-size:11px;color:#475569;margin-top:2px"><i class="bi bi-geo-alt" style="margin-right:2px"></i><?= htmlspecialchars($dev['lokasi']) ?></div>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
              <span data-role="online-badge" class="si-badge <?= $isOnline ? 'si-badge-online' : 'si-badge-offline' ?>">
                <span style="width:5px;height:5px;border-radius:50%;background:<?= $isOnline ? '#10b981' : '#64748b' ?>;display:inline-block"></span>
                <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?>
              </span>
              <span data-role="nurse-badge" class="si-badge si-badge-nurse <?= $isNurse ? '' : 'hidden' ?>">
                <i class="bi bi-bell-fill" style="font-size:8px"></i> NURSE CALL
              </span>
            </div>
          </div>

          <!-- Row 2: pasien info -->
          <div style="background:#21253a;border-radius:10px;padding:10px 12px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:26px;height:26px;background:rgba(59,130,246,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center">
                <i class="bi bi-person-fill" style="color:#3b82f6;font-size:11px"></i>
              </div>
              <span style="font-size:13px;font-weight:600;color:#e2e8f0"><?= htmlspecialchars($dev['pasien']) ?></span>
            </div>
            <span data-role="mode-badge" style="font-size:10px;font-weight:700;background:#0f1117;color:#94a3b8;padding:2px 8px;border-radius:6px"><?= htmlspecialchars($dev['mode'] ?? '-') ?></span>
          </div>

          <!-- Row 3: progress bar -->
          <div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
              <span style="font-size:10px;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.06em">Volume</span>
              <span data-role="persen-text" style="font-size:11px;font-weight:800;color:#f1f5f9"><?= number_format($persen,0) ?>%</span>
            </div>
            <div style="height:6px;background:#21253a;border-radius:3px;overflow:hidden">
              <div data-role="progress-bar" class="<?= $barClass ?>" style="height:100%;border-radius:3px;width:<?= $persen ?>%;transition:width .5s"></div>
            </div>
            <div data-role="low-warning" style="margin-top:6px;font-size:10px;font-weight:700;color:#ef4444;display:flex;align-items:center;gap:4px" class="<?= $persen <= 20 ? '' : 'hidden' ?>">
              <i class="bi bi-exclamation-triangle-fill"></i> Volume hampir habis!
            </div>
          </div>

          <!-- Row 4: TPM + Volume grid -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
            <div style="background:#21253a;border-radius:10px;padding:10px;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">TPM</div>
              <div data-role="tpm-value" style="font-size:20px;font-weight:900;color:#f1f5f9"><?= number_format($dev['tpm'] ?? 0) ?></div>
            </div>
            <div style="background:#21253a;border-radius:10px;padding:10px;text-align:center">
              <div style="font-size:9px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Sisa</div>
              <div style="font-size:20px;font-weight:900;color:#f1f5f9">
                <span data-role="volume-display"><?= number_format($dev['volume_sisa'] ?? 0) ?></span><span style="font-size:10px;color:#475569"> ml</span>
              </div>
            </div>
          </div>

          <!-- Row 5: estimasi -->
          <div style="background:#21253a;border-radius:10px;padding:8px 12px;display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <span style="font-size:10px;font-weight:600;color:#475569;text-transform:uppercase">Estimasi Habis</span>
            <span data-role="estimasi-value" style="font-size:13px;font-weight:800;color:#f1f5f9"><?= $dev['estimasi_jam'] ?>j <?= $dev['estimasi_mnt'] ?>m</span>
          </div>

          <!-- Row 6: footer -->
          <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid rgba(255,255,255,0.06)">
            <span data-role="last-update" style="font-size:10px;color:#475569">
              <i class="bi bi-clock-history" style="margin-right:3px"></i><?= $dev['last_update'] ? date('H:i:s', strtotime($dev['last_update'])) : 'Belum ada data' ?>
            </span>
            <div style="display:flex;gap:6px">
              <a href="devices.php?edit=<?= urlencode($dev['device_id']) ?>" style="padding:6px 10px;background:#21253a;border-radius:8px;color:#f59e0b;font-size:11px;font-weight:700;text-decoration:none;border:1px solid rgba(245,158,11,0.2)">
                <i class="bi bi-pencil-fill"></i>
              </a>
              <a href="detail.php?id=<?= urlencode($dev['device_id']) ?>" style="padding:6px 14px;background:#2563eb;border-radius:8px;color:white;font-size:11px;font-weight:700;text-decoration:none">
                DETAIL
              </a>
            </div>
          </div>

        </div><!-- /padding -->

        <!-- Nurse overlay -->
        <div data-role="nurse-overlay" class="<?= $isNurse ? '' : 'hidden' ?>" style="position:absolute;inset:0;background:rgba(239,68,68,0.06);pointer-events:none;animation:pulse 2s infinite"></div>

      </div>
      <?php endforeach; ?>
    </div>

    <!-- NURSE CALL LOG -->
    <div style="margin-top:48px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div class="si-section-title" style="margin-bottom:0">
          Riwayat Nurse Call
          <span id="nurse-log-count" style="font-size:10px;font-weight:700;background:rgba(255,255,255,0.08);color:#94a3b8;padding:2px 8px;border-radius:20px;margin-left:4px"><?= count($nurseLogs) ?></span>
        </div>
        <span style="font-size:10px;font-weight:700;color:#475569;display:flex;align-items:center;gap:6px">
          <span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;animation:pulse 2s infinite"></span>REALTIME
        </span>
      </div>

      <div class="si-card" style="overflow:hidden">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:rgba(255,255,255,0.04)">
              <th style="padding:14px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Waktu</th>
              <th style="padding:14px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Pasien &amp; Lokasi</th>
              <th style="padding:14px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Device ID</th>
            </tr>
          </thead>
          <tbody id="nurse-log-tbody">
            <?php foreach ($nurseLogs as $log): ?>
            <tr style="border-top:1px solid rgba(255,255,255,0.04);transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
              <td style="padding:14px 20px;font-size:12px;font-weight:700;color:#94a3b8"><?= date('H:i:s', strtotime($log['created_at'])) ?></td>
              <td style="padding:14px 20px">
                <div style="font-size:13px;font-weight:700;color:#f1f5f9"><?= htmlspecialchars($log['pasien'] ?? 'Unknown') ?></div>
                <div style="font-size:10px;color:#475569;margin-top:2px"><?= htmlspecialchars($log['lokasi'] ?? '-') ?></div>
              </td>
              <td style="padding:14px 20px;font-size:11px;font-weight:600;color:#475569"><?= htmlspecialchars($log['device_id']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($nurseLogs)): ?>
            <tr>
              <td colspan="3" style="padding:48px 20px;text-align:center;font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.1em">Belum ada log</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>

  <footer style="padding:32px 24px;text-align:center">
    <p style="font-size:10px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.2em">&copy; <?= date('Y') ?> Smart Infus Monitoring System</p>
  </footer>

  <script>
    // Realtime clock
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
