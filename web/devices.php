<?php
/**
 * =====================================================
 * SMART INFUS — KELOLA PERANGKAT (REFACTORED TAILWIND)
 * =====================================================
 */

require_once __DIR__ . '/config/db.php';

$db      = getDB();
$message = '';
$msgType = 'success';

// --- DATA ESCAPING HELPER ---
if (!function_exists('esc')) {
    function esc($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// --- FORM CONTROLLER (POST BUSINESS LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action'] ?? '';
    $device_id   = trim($_POST['device_id'] ?? '');
    $nama        = trim($_POST['nama'] ?? '');
    $lokasi      = trim($_POST['lokasi'] ?? '');
    $pasien      = trim($_POST['pasien'] ?? '');
    $no_suster   = preg_replace('/\D/', '', $_POST['no_suster'] ?? '');
    $no_keluarga = preg_replace('/\D/', '', $_POST['no_keluarga'] ?? '');

    if ($action === 'add' && $device_id && $nama) {
        $check = $db->prepare("SELECT id FROM devices WHERE device_id = :id");
        $check->execute([':id' => $device_id]);

        if ($check->fetch()) {
            $message = "Device ID '" . $device_id . "' sudah terdaftar dalam sistem!";
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
            $message = "Perangkat '" . $nama . "' sukses didaftarkan!";
        }
    } 
    
    elseif ($action === 'edit' && $device_id && $nama) {
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
        $message = "Data perangkat '" . $nama . "' berhasil diperbarui!";
    } 
    
    elseif ($action === 'delete' && $device_id) {
        $stmt = $db->prepare("UPDATE devices SET aktif = 0 WHERE device_id = :id");
        $stmt->execute([':id' => $device_id]);
        $message = "Perangkat '" . $device_id . "' berhasil dihapus dari daftar aktif!";
    }
}

// --- DATA FETCHING (GET) ---
$editDevice = null;
if (isset($_GET['edit'])) {
    $editStmt = $db->prepare("SELECT * FROM devices WHERE device_id = :id");
    $editStmt->execute([':id' => $_GET['edit']]);
    $editDevice = $editStmt->fetch();
}

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
  <title>Kelola Device — Central Monitoring System</title>
  
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

      <!-- Realtime Clock Placeholder -->
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

  <!-- MAIN CONTENT CONTAINER -->
  <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    
    <!-- ALERT NOTIFICATION -->
    <?php if ($message): ?>
    <div class="mb-6 p-4 rounded-xl flex items-center gap-3 border transition-all <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200/80 text-emerald-800' : 'bg-rose-50 border-rose-200/80 text-rose-800' ?>">
      <i class="bi bi-<?= $msgType === 'success' ? 'check2-circle' : 'exclamation-circle' ?> text-lg flex-shrink-0"></i>
      <span class="text-xs font-bold tracking-wide"><?= esc($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- SPLIT GRID WORKSPACE -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
      
      <!-- FORM MODUL PANEL (LEFT) -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden lg:sticky lg:top-24">
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
          <div class="w-8 h-8 bg-[#6b2072]/10 rounded-lg flex items-center justify-center">
            <i class="bi bi-<?= $editDevice ? 'pencil-square text-amber-600' : 'plus-square-fill text-[#6b2072]' ?> text-sm"></i>
          </div>
          <div>
            <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase"><?= $editDevice ? 'Perbarui Perangkat' : 'Registrasi Modul' ?></h2>
            <p class="text-[10px] font-bold text-[#6b2072] tracking-wide uppercase">Workspace Parameter</p>
          </div>
        </div>

        <form method="POST" action="devices.php" class="p-5 flex flex-col gap-5">
          <input type="hidden" name="action" value="<?= $editDevice ? 'edit' : 'add' ?>" />

          <!-- Input: Device ID -->
          <div>
            <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5 flex items-center gap-1">
              <i class="bi bi-qr-code text-slate-500"></i> Serial / Device ID
            </label>
            <input type="text" name="device_id" placeholder="Contoh: INFUS-01" 
                   class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5 read-only:opacity-60 read-only:cursor-not-allowed"
                   value="<?= esc($editDevice['device_id'] ?? '') ?>" <?= $editDevice ? 'readonly' : '' ?> required />
          </div>

          <!-- Input: Nama Perangkat -->
          <div>
            <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5 flex items-center gap-1">
              <i class="bi bi-tag text-slate-500"></i> Nama Perangkat
            </label>
            <input type="text" name="nama" placeholder="Contoh: Unit Bed A1" 
                   class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                   value="<?= esc($editDevice['nama'] ?? '') ?>" required />
          </div>

          <!-- Input: Lokasi & Pasien -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5 flex items-center gap-1">
                <i class="bi bi-geo-alt text-slate-500"></i> Ruang / Lokasi
              </label>
              <input type="text" name="lokasi" placeholder="Kamar 3A" 
                     class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3 py-2.5 text-xs font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                     value="<?= esc($editDevice['lokasi'] ?? '') ?>" />
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5 flex items-center gap-1">
                <i class="bi bi-person text-slate-500"></i> Nama Pasien
              </label>
              <input type="text" name="pasien" placeholder="Nama Pasien" 
                     class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3 py-2.5 text-xs font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                     value="<?= esc($editDevice['pasien'] ?? '') ?>" />
            </div>
          </div>

          <!-- WHATSAPP INTEGRATION DIVIDER -->
          <div class="flex items-center gap-3 my-1">
            <div class="flex-1 h-px bg-slate-200"></div>
            <span class="text-[9px] font-bold text-slate-400 tracking-widest uppercase flex items-center gap-1">
              <i class="bi bi-whatsapp text-emerald-500"></i> WhatsApp Gateway
            </span>
            <div class="flex-1 h-px bg-slate-200"></div>
          </div>

          <!-- Input: WA Contacts -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1 flex items-center gap-1">
                <i class="bi bi-person-badge text-emerald-600"></i> No. Suster
              </label>
              <input type="tel" name="no_suster" placeholder="62812345678" 
                     class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3 py-2.5 text-xs font-bold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                     value="<?= esc($editDevice['no_suster'] ?? '') ?>" />
              <span class="text-[9px] text-slate-400 block mt-1">Gunakan kode 62</span>
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1 flex items-center gap-1">
                <i class="bi bi-people text-emerald-600"></i> No. Keluarga
              </label>
              <input type="tel" name="no_keluarga" placeholder="62898765432" 
                     class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-3 py-2.5 text-xs font-bold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5"
                     value="<?= esc($editDevice['no_keluarga'] ?? '') ?>" />
              <span class="text-[9px] text-slate-400 block mt-1">Gunakan kode 62</span>
            </div>
          </div>

          <!-- Form Actions Buttons -->
          <div class="flex items-center gap-2 pt-2">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#6b2072] hover:bg-[#541859] text-white rounded-xl text-xs font-bold shadow-md shadow-[#6b2072]/10 active:scale-95 transition-all cursor-pointer">
              <i class="bi bi-save2"></i> <?= $editDevice ? 'UPDATE PERANGKAT' : 'SIMPAN MODUL' ?>
            </button>
            <?php if ($editDevice): ?>
            <a href="devices.php" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 rounded-xl text-xs transition-all" title="Batal Edit">
              <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- TABLE LIST PANEL (RIGHT) -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden lg:grid-cols-1 lg:col-span-2">
        <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
              <span class="w-1.5 h-4 bg-[#6b2072] rounded-full inline-block"></span>
              Modul Perangkat Terregistrasi
            </h2>
          </div>
          <span class="text-xs bg-[#6b2072]/10 border border-[#6b2072]/20 text-[#6b2072] px-2.5 py-0.5 rounded-full font-bold">
            <?= count($devices) ?> Unit Total
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-200 text-[10px] font-bold text-slate-400 tracking-wider uppercase">
                <th class="py-3.5 px-5">Device &amp; Bangsal</th>
                <th class="py-3.5 px-5">Pasien Aktif</th>
                <th class="py-3.5 px-5">Notifikasi Alur</th>
                <th class="py-3.5 px-4 text-center">Data Log</th>
                <th class="py-3.5 px-5 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
              <?php foreach ($devices as $dev): 
                $isOnline = false;
                if ($dev['last_update']) {
                    $last = strtotime($dev['last_update']);
                    if ((time() - $last) < 30) {
                        $isOnline = true;
                    }
                }
                $hasSuster   = !empty(trim($dev['no_suster'] ?? ''));
                $hasKeluarga = !empty(trim($dev['no_keluarga'] ?? ''));
              ?>
              <tr class="hover:bg-slate-50/60 transition-colors">
                
                <!-- Col: Device & Location -->
                <td class="py-4 px-5">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 border rounded-xl flex items-center justify-center flex-shrink-0 <?= $isOnline ? 'bg-emerald-50 border-emerald-100 text-emerald-600' : 'bg-slate-100 border-slate-200 text-slate-400' ?>">
                      <i class="bi bi-broadcast text-sm <?= $isOnline ? 'animate-pulse' : '' ?>"></i>
                    </div>
                    <div>
                      <div class="font-bold text-slate-900 leading-tight"><?= esc($dev['nama']) ?></div>
                      <div class="flex items-center gap-1.5 mt-1">
                        <span class="text-[9px] font-black bg-slate-100 border border-slate-200 text-slate-500 px-1.5 py-0.5 rounded font-mono uppercase"><?= esc($dev['device_id']) ?></span>
                        <span class="text-xs font-bold text-[#6b2072]"><?= esc($dev['lokasi']) ?></span>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Col: Patient -->
                <td class="py-4 px-5">
                  <div class="font-bold text-slate-800"><?= esc($dev['pasien'] ?: '— Kosong —') ?></div>
                  <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                    <i class="bi bi-clock-history"></i>
                    <?= $dev['last_update'] ? date('H:i:s', strtotime($dev['last_update'])) : 'No signals' ?>
                  </div>
                </td>

                <!-- Col: WA Contact Status -->
                <td class="py-4 px-5">
                  <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-1.5 text-xs font-semibold <?= $hasSuster ? 'text-emerald-600' : 'text-slate-400' ?>">
                      <i class="bi bi-person-badge text-[10px]"></i>
                      <span><?= $hasSuster ? esc($dev['no_suster']) : 'Suster N/A' ?></span>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs font-semibold <?= $hasKeluarga ? 'text-emerald-600' : 'text-slate-400' ?>">
                      <i class="bi bi-people text-[10px]"></i>
                      <span><?= $hasKeluarga ? esc($dev['no_keluarga']) : 'Wali N/A' ?></span>
                    </div>
                  </div>
                </td>

                <!-- Col: Total Logs -->
                <td class="py-4 px-4 text-center">
                  <div class="text-base font-black text-slate-900 tabular-nums"><?= number_format($dev['total_data']) ?></div>
                  <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Packets</span>
                </td>

                <!-- Col: Actions -->
                <td class="py-4 px-5 text-right">
                  <div class="flex items-center justify-flex-end gap-1.5">
                    
                    <!-- Detail View Button -->
                    <a href="detail.php?id=<?= urlencode($dev['device_id']) ?>" title="Analisis Grafik"
                       class="w-8 h-8 bg-slate-100 hover:bg-slate-900 text-slate-600 hover:text-white rounded-xl flex items-center justify-center transition-all border border-slate-200 active:scale-90">
                      <i class="bi bi-bar-chart-fill text-xs"></i>
                    </a>

                    <!-- Edit Trigger Button -->
                    <a href="devices.php?edit=<?= urlencode($dev['device_id']) ?>" title="Modifikasi Konfigurasi"
                       class="w-8 h-8 bg-amber-50 border border-amber-200 text-amber-600 hover:bg-amber-500 hover:text-white rounded-xl flex items-center justify-center transition-all active:scale-90">
                      <i class="bi bi-pencil-fill text-[11px]"></i>
                    </a>

                    <!-- Delete Soft Form -->
                    <form method="POST" action="devices.php" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan unit <?= esc(addslashes($dev['nama'])) ?>?')">
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="device_id" value="<?= esc($dev['device_id']) ?>" />
                      <button type="submit" title="Drop Device"
                              class="w-8 h-8 bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-500 hover:text-white rounded-xl flex items-center justify-center cursor-pointer transition-all active:scale-90">
                        <i class="bi bi-trash3-fill text-[11px]"></i>
                      </button>
                    </form>

                  </div>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (empty($devices)): ?>
              <tr>
                <td colspan="5" class="py-16 text-center text-xs font-bold text-slate-400 tracking-wider uppercase">
                  <i class="bi bi-hdd-stack-fill text-3xl block text-slate-300 mb-2"></i>
                  Belum Ada Perangkat yang Didaftarkan
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

  <!-- WORKSTATION FOOTER -->
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
</body>
</html>