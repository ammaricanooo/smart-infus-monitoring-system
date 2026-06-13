<?php
// =====================================================
// HALAMAN SETTINGS — KONFIGURASI SISTEM (OPTIMIZED UX)
// =====================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/whatsapp.php';

// ===== AUTO-MIGRATION INDEXES =====
try {
    $db = getDB();
    
    // Check and create idx_device_created_at index on infus_data
    $idx1 = $db->query("SHOW INDEXES FROM infus_data WHERE Key_name = 'idx_device_created_at'")->fetch();
    if (!$idx1) {
        $db->exec("ALTER TABLE infus_data ADD INDEX idx_device_created_at (device_id, created_at)");
    }
    
    // Check and create idx_ncl_device_status index on nurse_call_log
    $idx2 = $db->query("SHOW INDEXES FROM nurse_call_log WHERE Key_name = 'idx_ncl_device_status'")->fetch();
    if (!$idx2) {
        $db->exec("ALTER TABLE nurse_call_log ADD INDEX idx_ncl_device_status (device_id, status)");
    }
} catch (\Throwable $e) {
    error_log("Failed to auto-migrate indexes: " . $e->getMessage());
}

$message = '';
$msgType = 'success';
$testResult = null;

// ===== HELPER ESCAPING =====
if (!function_exists('esc')) {
    function esc($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// ===== PROSES FORM SAVE & ACTION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $fields = [
            'wa_api_url',
            'wa_api_key',
            'wa_nurse_call_msg',
            'wa_low_volume_msg',
            'wa_tpm_zero_msg',
            'wa_tpm_high_msg',
            'wa_resolved_msg',
            'db_infus_data_retention',
            'db_nurse_log_retention',
        ];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                setSetting($f, trim($_POST[$f]));
            }
        }
        $message = 'Konfigurasi parameter klinis berhasil disimpan!';
    }

    // ===== TEST KIRIM WA =====
    elseif ($action === 'test_wa') {
        $testTarget = trim($_POST['test_target'] ?? '');
        if (empty($testTarget)) {
            $message = 'Masukkan nomor telepon tujuan untuk melakukan pengujian!';
            $msgType = 'danger';
        } else {
            $testMsg = "✅ *TEST NOTIFIKASI SYSTEM*\nSmart Infus — Central Monitor\nWaktu: " . date('d/m/Y H:i:s') . "\n\nIntegrasi API WhatsApp Gateway berhasil terhubung!";
            $result  = sendWhatsApp($testTarget, $testMsg);
            if ($result['success']) {
                $message    = "Pesan uji coba sukses terkirim ke target: $testTarget";
                $testResult = $result;
            } else {
                $message    = 'Koneksi API Gagal: ' . ($result['error'] ?? json_encode($result['response'] ?? ''));
                $msgType    = 'danger';
                $testResult = $result;
            }
        }
    }

    // ===== OPTIMIZE DATABASE =====
    elseif ($action === 'optimize_db') {
        try {
            $db = getDB();
            
            $infusDays = (int)getSetting('db_infus_data_retention', '3');
            $logDays   = (int)getSetting('db_nurse_log_retention', '7');
            
            // Delete old data
            $prune1 = $db->prepare("DELETE FROM infus_data WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $prune1->execute([':days' => $infusDays]);
            $deletedInfus = $prune1->rowCount();
            
            $prune2 = $db->prepare("DELETE FROM nurse_call_log WHERE status = 0 AND resolved_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $prune2->execute([':days' => $logDays]);
            $deletedNurse = $prune2->rowCount();
            
            // Rebuild/Optimize Tables
            $db->query("OPTIMIZE TABLE infus_data");
            $db->query("OPTIMIZE TABLE nurse_call_log");
            
            $message = "Database berhasil dioptimalkan! Terhapus: $deletedInfus data infus, $deletedNurse log panggilan.";
        } catch (\Exception $e) {
            $message = 'Gagal mengoptimalkan database: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

$settings   = getAllSettings();
$apiUrl     = $settings['wa_api_url']     ?? '';
$apiKey     = $settings['wa_api_key']     ?? '';
if (empty($apiKey) && !empty($settings['fonnte_token'] ?? '')) {
    $apiKey = $settings['fonnte_token'];
}
$msgNC      = $settings['wa_nurse_call_msg'] ?? '';
$msgLV      = $settings['wa_low_volume_msg'] ?? '';
$msgTPM     = $settings['wa_tpm_zero_msg']   ?? '';
$msgTPMH    = $settings['wa_tpm_high_msg']   ?? '';
$msgOK      = $settings['wa_resolved_msg']   ?? '';

$infusRetention = (int)($settings['db_infus_data_retention'] ?? 3);
$nurseRetention = (int)($settings['db_nurse_log_retention']  ?? 7);

if (empty($settings['db_infus_data_retention'])) {
    setSetting('db_infus_data_retention', '3');
    $infusRetention = 3;
}
if (empty($settings['db_nurse_log_retention'])) {
    setSetting('db_nurse_log_retention', '7');
    $nurseRetention = 7;
}

// Insert default template jika belum ada di DB
if (empty($msgTPM)) {
    $msgTPM = 'INFUS MACET 🔴\nPasien: {pasien}\nLokasi: {lokasi}\nSisa cairan: {volume} ml\nWaktu: {waktu}\n\nTidak ada tetesan terdeteksi. Periksa selang atau jarum infus segera.';
    setSetting('wa_tpm_zero_msg', $msgTPM);
}
if (empty($msgTPMH)) {
    $msgTPMH = 'TPM TERLALU CEPAT ⚡\nPasien: {pasien}\nLokasi: {lokasi}\nTPM saat ini: {tpm} tetes/menit\nWaktu: {waktu}\n\nKecepatan tetesan infus terlalu cepat. Harap periksa dan sesuaikan pengaturan.';
    setSetting('wa_tpm_high_msg', $msgTPMH);
}
if (empty($msgOK)) {
    $msgOK = 'KONDISI NORMAL ✅\nPasien: {pasien}\nLokasi: {lokasi}\nWaktu: {waktu}\n\nKabar baik! {resolved_label}. Tidak perlu khawatir.';
    setSetting('wa_resolved_msg', $msgOK);
}
$activePage = 'settings';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Settings — Central Monitoring Station</title>
  
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
        <a href="docs.php" class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all <?= $activePage==='docs' ? 'bg-[#6b2072]/10 text-[#6b2072]' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
          <i class="bi bi-book-half"></i><span>Dokumentasi</span>
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
    <a href="docs.php" class="flex flex-col items-center gap-0.5 text-[10px] font-bold transition-all <?= $activePage==='docs' ? 'text-[#6b2072]' : 'text-slate-500' ?>">
      <i class="bi bi-book-half text-lg"></i>
      <span>Dokumentasi</span>
    </a>
  </div>

  <!-- MAIN WORKSPACE CONTAINER -->
  <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">
    <!-- SYSTEM ALERT NOTIFICATION -->
    <?php if ($message): ?>
    <div class="p-4 mb-4 rounded-xl flex items-center gap-3 border transition-all <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
      <i class="bi bi-<?= $msgType === 'success' ? 'check2-circle' : 'exclamation-circle' ?> text-lg flex-shrink-0"></i>
      <span class="text-xs font-bold tracking-wide"><?= esc($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- TWO-COLUMN DASHBOARD LAYOUT FOR DESKTOP -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
      
      <!-- ================= LEFT COLUMN: CONFIGURATION FORM (7 COLS) ================= -->
      <div class="lg:col-span-7">
        <form method="POST" action="settings.php" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
          <input type="hidden" name="action" value="save_settings" />

          <!-- Card Header -->
          <div class="p-4.5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
            <div class="w-9 h-9 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center border border-emerald-100">
              <i class="bi bi-whatsapp text-base"></i>
            </div>
            <div>
              <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase">Konfigurasi WhatsApp Gateway</h2>
              <p class="text-[10px] font-bold text-slate-400 tracking-wide uppercase">
                WhatsApp Gateway: custom API / local gateway
              </p>
            </div>
          </div>

          <!-- Card Body Content -->
          <div class="p-5 flex flex-col gap-5">
            
            <!-- Field 1: Gateway URL -->
            <div>
              <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <i class="bi bi-link-45deg text-[#6b2072] mr-1"></i> Gateway API URL
              </label>
              <input type="text" name="wa_api_url" id="wa_api_url"
                     value="<?= esc($apiUrl) ?>"
                     placeholder="Contoh: http://localhost:3000/api/whatsapp"
                     class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5" />
            </div>

            <!-- Field 2: API Key -->
            <div>
              <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <i class="bi bi-key-fill text-[#6b2072] mr-1"></i> WhatsApp API Key
              </label>
              <div class="relative">
                <input type="password" name="wa_api_key" id="wa_api_key"
                       value="<?= esc($apiKey) ?>"
                       placeholder="Masukkan API Key Gateway WhatsApp..."
                       class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl pl-4 pr-12 py-2.5 text-xs font-semibold font-mono text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5" />
                <button type="button" onclick="toggleTokenVisibility()"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1 text-xs transition-colors">
                  <i class="bi bi-eye" id="token-eye-icon"></i>
                </button>
              </div>
            </div>

            <!-- Token Status Badge Indicator -->
            <div class="p-3.5 rounded-xl border flex items-center gap-3 <?= !empty($apiKey) ? 'bg-emerald-50/50 border-emerald-100 text-emerald-800' : 'bg-amber-50/50 border-amber-100 text-amber-800' ?>">
              <i class="bi bi-<?= !empty($apiKey) ? 'patch-check-fill text-emerald-600' : 'exclamation-triangle-fill text-amber-500' ?> text-base flex-shrink-0"></i>
              <div>
                <div class="text-xs font-bold"><?= !empty($apiKey) ? 'API Key Aktif' : 'API Key Belum Terpasang' ?></div>
                <div class="text-[10px] font-medium text-slate-500 mt-0.5 leading-relaxed">
                  <?= !empty($apiKey) ? 'Gateway siap menerima notifikasi nurse call dan peringatan kritis.' : 'Notifikasi WhatsApp tidak aktif sampai API Key terpasang.' ?>
                </div>
              </div>
            </div>

            <!-- SECTION HEADER: DATABASE RETENTION -->
            <div class="pt-4 border-t border-slate-100 mt-2">
              <h3 class="text-xs font-black text-slate-900 tracking-wider uppercase flex items-center gap-2 mb-3">
                <i class="bi bi-database-fill text-[#6b2072]"></i> Manajemen Retensi Data Database
              </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Field: Infus Data Retention -->
              <div>
                <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                  Retensi Data Infus (Hari)
                </label>
                <input type="number" name="db_infus_data_retention" min="1" max="180"
                       value="<?= esc($infusRetention) ?>"
                       class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5" />
                <span class="text-[9px] font-medium text-slate-400 mt-1 block">Rekomendasi: 3 hari (laju data cepat).</span>
              </div>

              <!-- Field: Nurse Call Log Retention -->
              <div>
                <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                  Retensi Log Perawat (Hari)
                </label>
                <input type="number" name="db_nurse_log_retention" min="1" max="365"
                       value="<?= esc($nurseRetention) ?>"
                       class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5" />
                <span class="text-[9px] font-medium text-slate-400 mt-1 block">Rekomendasi: 7 hari (log panggilan selesai).</span>
              </div>
            </div>

            <!-- Field 2: Template Nurse Call -->
            <div>
              <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <span class="w-1.5 h-1.5 bg-rose-500 rounded-full animate-pulse"></span> Template — Nurse Call Emergency
              </label>
              <textarea name="wa_nurse_call_msg" rows="3"
                        class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl p-3.5 text-xs font-semibold font-mono text-slate-700 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5 placeholder:text-slate-400"
                        placeholder="Contoh: Panggilan Suster dari kamar..."><?= esc($msgNC) ?></textarea>
              <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1">Variabel:</span>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{pasien}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{lokasi}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{waktu}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{device}</code>
              </div>
            </div>

            <!-- Field 3: Template Low Volume -->
            <div>
              <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Template — Volume Kritis (&le; 20%)
              </label>
              <textarea name="wa_low_volume_msg" rows="3"
                        class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl p-3.5 text-xs font-semibold font-mono text-slate-700 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5 placeholder:text-slate-400"
                        placeholder="Contoh: Peringatan volume sisa sedikit..."><?= esc($msgLV) ?></textarea>
              <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1">Variabel:</span>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{pasien}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{lokasi}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{volume}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{persen}</code>
              </div>
            </div>

            <!-- Field 4: Template TPM Macet -->
            <div>
              <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <span class="w-1.5 h-1.5 bg-purple-600 rounded-full"></span> Template — Infus Macet (TPM = 0)
              </label>
              <textarea name="wa_tpm_zero_msg" rows="3"
                        class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl p-3.5 text-xs font-semibold font-mono text-slate-700 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5 placeholder:text-slate-400"
                        placeholder="Contoh: Infus tidak menetes..."><?= esc($msgTPM) ?></textarea>
              <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1">Variabel:</span>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{pasien}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{lokasi}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{volume}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{waktu}</code>
              </div>
            </div>

            <!-- Field 5: Template TPM Terlalu Cepat -->
            <div>
              <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Template — TPM Terlalu Cepat (&gt; 80)
              </label>
              <textarea name="wa_tpm_high_msg" rows="3"
                        class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl p-3.5 text-xs font-semibold font-mono text-slate-700 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5 placeholder:text-slate-400"
                        placeholder="Contoh: Tetesan infus terlalu cepat..."><?= esc($msgTPMH) ?></textarea>
              <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1">Variabel:</span>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{pasien}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{lokasi}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{tpm}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{waktu}</code>
              </div>
            </div>

            <!-- Field 6: Template Kondisi Normal (ke keluarga saja) -->
            <div>
              <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Template — Kondisi Normal Kembali <span class="text-emerald-600">(ke keluarga saja)</span>
              </label>
              <textarea name="wa_resolved_msg" rows="3"
                        class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl p-3.5 text-xs font-semibold font-mono text-slate-700 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5 placeholder:text-slate-400"
                        placeholder="Contoh: Kondisi pasien sudah normal..."><?= esc($msgOK) ?></textarea>
              <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1">Variabel:</span>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{pasien}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{lokasi}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{waktu}</code>
                <code class="text-[9px] font-bold bg-slate-100 text-[#6b2072] border border-slate-200 px-1.5 py-0.5 rounded font-mono">{resolved_label}</code>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-2 border-t border-slate-100">
              <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-[#6b2072] hover:bg-[#541859] text-white rounded-xl text-xs font-bold tracking-wide shadow-md shadow-[#6b2072]/10 active:scale-[0.99] transition-all cursor-pointer">
                <i class="bi bi-save2-fill"></i> SIMPAN PARAMETER KLINIS
              </button>
            </div>

          </div>
        </form>
      </div>

      <!-- ================= RIGHT COLUMN: UTILITIES & TESTING (5 COLS) ================= -->
      <div class="lg:col-span-5 flex flex-col gap-5">
        
        <!-- TEST GATEWAY CARD -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
          <div class="p-4.5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center border border-blue-100">
              <i class="bi bi-send-check text-base"></i>
            </div>
            <div>
              <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase">Uji Validasi API</h2>
              <p class="text-[10px] font-bold text-slate-400 tracking-wide uppercase">Verifikasi transmisi data jaringan</p>
            </div>
          </div>

          <form method="POST" action="settings.php" class="p-5 flex flex-col gap-3.5">
            <input type="hidden" name="action" value="test_wa" />
            
            <div class="flex flex-col gap-2.5">
              <div class="relative flex-1">
                <i class="bi bi-telephone text-slate-400 absolute left-4 top-1/2 -translate-y-1/2 text-xs"></i>
                <input type="text" name="test_target"
                       placeholder="Target: 628123456789"
                       class="w-full bg-slate-50 border border-slate-200 focus:border-[#6b2072] focus:bg-white rounded-xl pl-10 pr-4 py-2.5 text-xs font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-[#6b2072]/5" />
              </div>
              <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold tracking-wide active:scale-95 transition-all cursor-pointer">
                <i class="bi bi-cursor-fill"></i> RUNNING GATEWAY TEST
              </button>
            </div>
            <span class="text-[9px] font-medium text-slate-400 leading-relaxed block">
              <i class="bi bi-exclamation-circle text-amber-500"></i> Gunakan kode negara tanpa spasi/simbol (Contoh: <span class="font-bold text-slate-600">628xxx</span>).
            </span>

            <!-- API Debug Response Panel (Hanya muncul jika test di-run) -->
            <?php if ($testResult !== null): ?>
            <div class="mt-1 p-3.5 bg-slate-900 rounded-xl border border-slate-800">
              <div class="text-[9px] font-black text-slate-500 tracking-widest uppercase mb-1.5">Response Logger:</div>
              <pre class="text-[11px] text-emerald-400 font-mono overflow-x-auto whitespace-pre-wrap max-h-40"><?= esc(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </div>
            <?php endif; ?>
          </form>
        </div>

        <!-- REPOSITIONED DOCUMENTATION PANEL -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
          <div class="p-4.5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2.5">
            <i class="bi bi-info-circle-fill text-amber-500 text-base"></i>
            <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase">Panduan & Trigger</h2>
          </div>
          
          <div class="p-5 flex flex-col gap-4">
            <!-- Sync step -->
            <div>
              <h3 class="text-[10px] font-black text-slate-400 tracking-wider uppercase mb-2">Sinkronisasi</h3>
              <ol class="flex flex-col gap-1.5 text-xs font-medium text-slate-600">
                <li class="flex gap-1.5"><span class="text-[#6b2072] font-bold">1.</span> Siapkan WhatsApp Gateway API yang akan menerima request dari sistem.</li>
                <li class="flex gap-1.5"><span class="text-[#6b2072] font-bold">2.</span> Masukkan URL gateway pada kolom "Gateway API URL".</li>
                <li class="flex gap-1.5"><span class="text-[#6b2072] font-bold">3.</span> Masukkan API Key yang digunakan untuk autentikasi request.</li>
              </ol>
            </div>

            <hr class="border-slate-100" />

            <!-- Logic context -->
            <div>
              <h3 class="text-[10px] font-black text-slate-400 tracking-wider uppercase mb-2">Aturan Notifikasi</h3>
              <ul class="flex flex-col gap-2 text-xs font-medium text-slate-600">
                <li class="flex gap-2 items-start">
                  <i class="bi bi-bell-fill text-rose-500 mt-0.5 text-xs flex-shrink-0"></i>
                  <span>Trigger <span class="font-bold">Nurse Call</span> aktif seketika saat tombol kecemasan pasien ditekan.</span>
                </li>
                <li class="flex gap-2 items-start">
                  <i class="bi bi-droplet-half text-amber-500 mt-0.5 text-xs flex-shrink-0"></i>
                  <span>Trigger <span class="font-bold">Volume Sisa</span> jalan otomatis jika infus terdeteksi sisa &le; 10 ml.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- DATABASE MAINTENANCE CARD -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
          <div class="p-4.5 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
            <div class="w-9 h-9 bg-purple-50 text-[#6b2072] rounded-xl flex items-center justify-center border border-purple-100">
              <i class="bi bi-database-fill-gear text-base"></i>
            </div>
            <div>
              <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase">Pemeliharaan Database</h2>
              <p class="text-[10px] font-bold text-slate-400 tracking-wide uppercase">Reklamasi & Optimasi Ruang Disk</p>
            </div>
          </div>

          <form method="POST" action="settings.php" class="p-5 flex flex-col gap-3.5">
            <input type="hidden" name="action" value="optimize_db" />
            
            <p class="text-xs text-slate-600 leading-relaxed">
              Tekan tombol di bawah untuk membersihkan data lama secara manual berdasarkan konfigurasi retensi hari dan merapikan indeks fisik MySQL (<span class="font-mono">OPTIMIZE TABLE</span>).
            </p>
            
            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#6b2072] hover:bg-[#541859] text-white rounded-xl text-xs font-bold tracking-wide active:scale-95 transition-all cursor-pointer shadow-md shadow-[#6b2072]/10">
              <i class="bi bi-tools"></i> OPTIMALKAN DATABASE SEKARANG
            </button>
            
            <span class="text-[9px] font-medium text-slate-400 leading-relaxed block">
              <i class="bi bi-info-circle text-[#6b2072]"></i> Proses ini akan mengunci tabel selama beberapa saat untuk merekonstruksi indeks database.
            </span>
          </form>
        </div>

      </div>
    </div>

  </main>

  <!-- MEDICAL WORKSTATION FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Smart Infus Monitoring System &bull; Clinical Station Workspace</p>
  </footer>

  <!-- ACTION LOGIC: VISIBILITY TOGGLE -->
  <script>
    function toggleTokenVisibility() {
      const input = document.getElementById('wa_api_key');
      const icon = document.getElementById('token-eye-icon');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
      }
    }
  </script>

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