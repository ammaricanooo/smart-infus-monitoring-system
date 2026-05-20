<?php
// =====================================================
// HALAMAN KELOLA DEVICE
// =====================================================

require_once __DIR__ . '/config/db.php';

$db      = getDB();
$message = '';
$msgType = 'success';

// ===== PROSES FORM =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $action    = $_POST['action']    ?? '';
  $device_id = trim($_POST['device_id'] ?? '');
  $nama      = trim($_POST['nama']      ?? '');
  $lokasi    = trim($_POST['lokasi']    ?? '');
  $pasien    = trim($_POST['pasien']    ?? '');
  $no_suster   = preg_replace('/\D/', '', $_POST['no_suster']   ?? '');
  $no_keluarga = preg_replace('/\D/', '', $_POST['no_keluarga'] ?? '');

  if ($action === 'add' && $device_id && $nama) {

    $check = $db->prepare("SELECT id FROM devices WHERE device_id = :id");
    $check->execute([':id' => $device_id]);

    if ($check->fetch()) {
      $message = "Device ID '$device_id' sudah terdaftar!";
      $msgType = 'danger';
    } else {
      $stmt = $db->prepare("
                INSERT INTO devices (device_id, nama, lokasi, pasien, no_suster, no_keluarga)
                VALUES (:device_id, :nama, :lokasi, :pasien, :no_suster, :no_keluarga)
            ");
      $stmt->execute([
        ':device_id'   => $device_id,
        ':nama'        => $nama,
        ':lokasi'      => $lokasi,
        ':pasien'      => $pasien,
        ':no_suster'   => $no_suster,
        ':no_keluarga' => $no_keluarga,
      ]);
      $message = "Device '$nama' berhasil ditambahkan!";
    }
  } elseif ($action === 'edit' && $device_id && $nama) {

    $stmt = $db->prepare("
            UPDATE devices
            SET nama = :nama, lokasi = :lokasi, pasien = :pasien,
                no_suster = :no_suster, no_keluarga = :no_keluarga
            WHERE device_id = :device_id
        ");
    $stmt->execute([
      ':device_id'   => $device_id,
      ':nama'        => $nama,
      ':lokasi'      => $lokasi,
      ':pasien'      => $pasien,
      ':no_suster'   => $no_suster,
      ':no_keluarga' => $no_keluarga,
    ]);
    $message = "Device '$nama' berhasil diperbarui!";
  } elseif ($action === 'delete' && $device_id) {

    $stmt = $db->prepare("UPDATE devices SET aktif = 0 WHERE device_id = :id");
    $stmt->execute([':id' => $device_id]);
    $message = "Device '$device_id' berhasil dihapus!";
  }
}

// edit mode
$editDevice = null;
if (isset($_GET['edit'])) {
  $editStmt = $db->prepare("SELECT * FROM devices WHERE device_id = :id");
  $editStmt->execute([':id' => $_GET['edit']]);
  $editDevice = $editStmt->fetch();
}

// ambil semua device
$devices = $db->query("
    SELECT d.*,
        (SELECT COUNT(*) FROM infus_data WHERE device_id = d.device_id) AS total_data,
        (SELECT created_at FROM infus_data WHERE device_id = d.device_id
         ORDER BY created_at DESC LIMIT 1) AS last_update
    FROM devices d
    WHERE d.aktif = 1
    ORDER BY d.id ASC
")->fetchAll();

$activePage = 'devices';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Kelola Device — Smart Infus</title>
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

    /* INPUTS */
    .si-input { width: 100%; background: #21253a; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 10px 14px; color: #f1f5f9; font-size: 13px; font-family: inherit; transition: border-color .15s, box-shadow .15s; outline: none; }
    .si-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
    .si-input::placeholder { color: #475569; }
    .si-input:disabled, .si-input[readonly] { opacity: .5; cursor: not-allowed; }
    .si-label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .08em; display: block; margin-bottom: 6px; }

    /* BUTTONS */
    .si-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; transition: all .15s; text-decoration: none; font-family: inherit; }
    .si-btn-primary { background: #2563eb; color: white; }
    .si-btn-primary:hover { background: #1d4ed8; box-shadow: 0 0 16px rgba(37,99,235,0.4); }
    .si-btn-ghost { background: rgba(255,255,255,0.06); color: #94a3b8; border: 1px solid rgba(255,255,255,0.08); }
    .si-btn-ghost:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
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
      <div class="si-nav-right"></div>
    </div>
  </nav>

  <div style="max-width:1280px;margin:0 auto;padding:32px 24px">

    <!-- ALERT -->
    <?php if ($message): ?>
    <div style="margin-bottom:24px;padding:14px 18px;border-radius:12px;display:flex;align-items:center;gap:10px;<?= $msgType === 'success' ? 'background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);color:#10b981' : 'background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#ef4444' ?>">
      <i class="bi bi-<?= $msgType === 'success' ? 'check2-circle' : 'exclamation-circle' ?>" style="font-size:16px;flex-shrink:0"></i>
      <span style="font-size:13px;font-weight:700"><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:360px 1fr;gap:24px;align-items:start">

      <!-- FORM PANEL (Left) -->
      <div class="si-card" style="overflow:hidden;position:sticky;top:80px">
        <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;background:rgba(59,130,246,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-<?= $editDevice ? 'pencil-square' : 'plus-square-fill' ?>" style="color:#3b82f6;font-size:14px"></i>
          </div>
          <div style="font-size:13px;font-weight:800;color:#f1f5f9;text-transform:uppercase;letter-spacing:.04em">
            <?= $editDevice ? 'Perbarui Data' : 'Tambah Unit Baru' ?>
          </div>
        </div>

        <form method="POST" action="devices.php" style="padding:24px;display:flex;flex-direction:column;gap:16px">
          <input type="hidden" name="action" value="<?= $editDevice ? 'edit' : 'add' ?>" />

          <div>
            <label class="si-label"><i class="bi bi-qr-code" style="margin-right:4px"></i>Serial ID / Device ID</label>
            <input type="text" name="device_id" class="si-input"
              placeholder="INFUS-01"
              value="<?= htmlspecialchars($editDevice['device_id'] ?? '') ?>"
              <?= $editDevice ? 'readonly' : '' ?> required />
          </div>

          <div>
            <label class="si-label"><i class="bi bi-tag" style="margin-right:4px"></i>Nama Perangkat</label>
            <input type="text" name="nama" class="si-input"
              placeholder="Unit Bed A1"
              value="<?= htmlspecialchars($editDevice['nama'] ?? '') ?>" required />
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="si-label"><i class="bi bi-geo-alt" style="margin-right:4px"></i>Lokasi/Ruang</label>
              <input type="text" name="lokasi" class="si-input"
                placeholder="Kamar 3A"
                value="<?= htmlspecialchars($editDevice['lokasi'] ?? '') ?>" />
            </div>
            <div>
              <label class="si-label"><i class="bi bi-person" style="margin-right:4px"></i>Pasien</label>
              <input type="text" name="pasien" class="si-input"
                placeholder="Nama Pasien"
                value="<?= htmlspecialchars($editDevice['pasien'] ?? '') ?>" />
            </div>
          </div>

          <!-- WA Divider -->
          <div style="display:flex;align-items:center;gap:12px;margin:4px 0">
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.06)"></div>
            <span style="font-size:10px;font-weight:700;color:#475569;display:flex;align-items:center;gap:6px">
              <i class="bi bi-whatsapp" style="color:#25d366"></i>WhatsApp
            </span>
            <div style="flex:1;height:1px;background:rgba(255,255,255,0.06)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="si-label"><i class="bi bi-person-badge" style="margin-right:4px;color:#25d366"></i>No. Suster</label>
              <input type="tel" name="no_suster" class="si-input"
                placeholder="628123456789"
                value="<?= htmlspecialchars($editDevice['no_suster'] ?? '') ?>" />
              <div style="font-size:10px;color:#475569;margin-top:4px">Format: 628xxx</div>
            </div>
            <div>
              <label class="si-label"><i class="bi bi-people" style="margin-right:4px;color:#25d366"></i>No. Keluarga</label>
              <input type="tel" name="no_keluarga" class="si-input"
                placeholder="628987654321"
                value="<?= htmlspecialchars($editDevice['no_keluarga'] ?? '') ?>" />
              <div style="font-size:10px;color:#475569;margin-top:4px">Format: 628xxx</div>
            </div>
          </div>

          <div style="display:flex;gap:10px;padding-top:4px">
            <button type="submit" class="si-btn si-btn-primary" style="flex:1;justify-content:center">
              <i class="bi bi-save2"></i> <?= $editDevice ? 'SIMPAN' : 'DAFTARKAN' ?>
            </button>
            <?php if ($editDevice): ?>
            <a href="devices.php" class="si-btn si-btn-ghost" style="padding:10px 14px">
              <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- TABLE PANEL (Right) -->
      <div class="si-card" style="overflow:hidden">
        <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;justify-content:space-between">
          <div>
            <div style="font-size:14px;font-weight:800;color:#f1f5f9;text-transform:uppercase;letter-spacing:.04em">Daftar Perangkat</div>
            <div style="font-size:10px;font-weight:600;color:#475569;margin-top:2px">Total: <?= count($devices) ?> Unit Terdaftar</div>
          </div>
        </div>

        <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse">
            <thead>
              <tr style="background:rgba(255,255,255,0.04)">
                <th style="padding:12px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Device &amp; Lokasi</th>
                <th style="padding:12px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Pasien</th>
                <th style="padding:12px 20px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Kontak WA</th>
                <th style="padding:12px 20px;text-align:center;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Data</th>
                <th style="padding:12px 20px;text-align:right;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($devices as $dev):
                $isOnline = false;
                if ($dev['last_update']) {
                  $last = strtotime($dev['last_update']);
                  if ((time() - $last) < 60) $isOnline = true;
                }
              ?>
              <tr style="border-top:1px solid rgba(255,255,255,0.04);transition:background .15s" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                <td style="padding:14px 20px">
                  <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:34px;height:34px;border-radius:8px;background:<?= $isOnline ? 'rgba(16,185,129,0.15)' : 'rgba(71,85,105,0.2)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                      <i class="bi bi-broadcast" style="color:<?= $isOnline ? '#10b981' : '#475569' ?>;font-size:14px"></i>
                    </div>
                    <div>
                      <div style="font-size:13px;font-weight:700;color:#f1f5f9"><?= htmlspecialchars($dev['nama']) ?></div>
                      <div style="display:flex;align-items:center;gap:6px;margin-top:3px">
                        <span style="font-size:9px;font-weight:700;background:#21253a;color:#64748b;padding:1px 6px;border-radius:4px;text-transform:uppercase"><?= htmlspecialchars($dev['device_id']) ?></span>
                        <span style="font-size:10px;color:#3b82f6;font-weight:600"><?= htmlspecialchars($dev['lokasi']) ?></span>
                      </div>
                    </div>
                  </div>
                </td>
                <td style="padding:14px 20px">
                  <div style="font-size:13px;font-weight:600;color:#e2e8f0"><?= htmlspecialchars($dev['pasien'] ?: '— Tidak Ada —') ?></div>
                  <div style="font-size:10px;color:#475569;margin-top:2px">
                    <i class="bi bi-clock-history" style="margin-right:2px"></i>
                    <?= $dev['last_update'] ? date('H:i', strtotime($dev['last_update'])) : 'Belum ada data' ?>
                  </div>
                </td>
                <td style="padding:14px 20px">
                  <?php
                    $hasSuster   = !empty(trim($dev['no_suster']   ?? ''));
                    $hasKeluarga = !empty(trim($dev['no_keluarga'] ?? ''));
                  ?>
                  <div style="display:flex;flex-direction:column;gap:4px">
                    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:<?= $hasSuster ? '#10b981' : '#475569' ?>">
                      <i class="bi bi-person-badge"></i>
                      <span style="font-weight:600"><?= $hasSuster ? htmlspecialchars($dev['no_suster']) : '— belum diisi —' ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:<?= $hasKeluarga ? '#10b981' : '#475569' ?>">
                      <i class="bi bi-people"></i>
                      <span style="font-weight:600"><?= $hasKeluarga ? htmlspecialchars($dev['no_keluarga']) : '— belum diisi —' ?></span>
                    </div>
                  </div>
                </td>
                <td style="padding:14px 20px;text-align:center">
                  <div style="font-size:16px;font-weight:900;color:#f1f5f9"><?= number_format($dev['total_data']) ?></div>
                  <div style="font-size:9px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em">Paket</div>
                </td>
                <td style="padding:14px 20px;text-align:right">
                  <div style="display:flex;gap:6px;justify-content:flex-end">
                    <a href="detail.php?id=<?= urlencode($dev['device_id']) ?>" title="Lihat Detail"
                       style="width:32px;height:32px;background:rgba(59,130,246,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#3b82f6;text-decoration:none;transition:all .15s"
                       onmouseover="this.style.background='#2563eb';this.style.color='white'" onmouseout="this.style.background='rgba(59,130,246,0.12)';this.style.color='#3b82f6'">
                      <i class="bi bi-bar-chart-fill" style="font-size:12px"></i>
                    </a>
                    <a href="devices.php?edit=<?= urlencode($dev['device_id']) ?>" title="Edit Device"
                       style="width:32px;height:32px;background:rgba(245,158,11,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#f59e0b;text-decoration:none;transition:all .15s"
                       onmouseover="this.style.background='#d97706';this.style.color='white'" onmouseout="this.style.background='rgba(245,158,11,0.12)';this.style.color='#f59e0b'">
                      <i class="bi bi-pencil-fill" style="font-size:11px"></i>
                    </a>
                    <form method="POST" action="devices.php" style="display:inline" onsubmit="return confirm('Hapus device <?= htmlspecialchars(addslashes($dev['nama'])) ?> dari sistem?')">
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="device_id" value="<?= htmlspecialchars($dev['device_id']) ?>" />
                      <button type="submit" title="Hapus Device"
                              style="width:32px;height:32px;background:rgba(239,68,68,0.12);border:none;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#ef4444;cursor:pointer;transition:all .15s"
                              onmouseover="this.style.background='#dc2626';this.style.color='white'" onmouseout="this.style.background='rgba(239,68,68,0.12)';this.style.color='#ef4444'">
                        <i class="bi bi-trash3-fill" style="font-size:11px"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($devices)): ?>
              <tr>
                <td colspan="5" style="padding:60px 20px;text-align:center">
                  <i class="bi bi-hdd-stack" style="font-size:40px;color:#334155;display:block;margin-bottom:12px"></i>
                  <p style="font-size:11px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.1em">Database Kosong</p>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /grid -->
  </div><!-- /container -->

  <footer style="padding:32px 24px;text-align:center">
    <p style="font-size:10px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.2em">&copy; <?= date('Y') ?> Smart Infus Monitoring System</p>
  </footer>
</body>
</html>
