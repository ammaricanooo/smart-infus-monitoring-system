<?php
// =====================================================
// HALAMAN SETTINGS — KONFIGURASI SISTEM (OPTIMIZED UX)
// =====================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/whatsapp.php';
require_once __DIR__ . '/config/auth.php';

requireAccess('settings'); // hanya superadmin yang boleh akses

// ===== AUTO-MIGRATION INDEXES =====
try {
    $db = getDB();
    $idx1 = $db->query("SHOW INDEXES FROM infus_data WHERE Key_name = 'idx_device_created_at'")->fetch();
    if (!$idx1) {
        $db->exec("ALTER TABLE infus_data ADD INDEX idx_device_created_at (device_id, created_at)");
    }
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

if (!function_exists('esc')) {
    function esc($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// ===== PROSES FORM SAVE & ACTION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_gateway') {
        $provider = in_array($_POST['wa_provider'] ?? '', ['custom', 'fonnte']) ? $_POST['wa_provider'] : 'custom';
        setSetting('wa_provider', $provider);

        // Simpan konfigurasi kedua provider sekaligus
        foreach (['wa_api_url', 'wa_api_key', 'fonnte_token', 'iot_api_key', 'app_url'] as $f) {
            if (isset($_POST[$f])) setSetting($f, trim($_POST[$f]));
        }
        setSetting('login_required', isset($_POST['login_required']) ? '1' : '0');
        $message = 'Konfigurasi koneksi & keamanan berhasil disimpan!';
    }

    elseif ($action === 'save_retention') {
        foreach (['db_infus_data_retention', 'db_nurse_log_retention'] as $f) {
            if (isset($_POST[$f])) setSetting($f, trim($_POST[$f]));
        }
        $message = 'Pengaturan retensi database berhasil disimpan!';
    }

    elseif ($action === 'save_templates') {
        $fields = [
            'wa_nurse_call_msg_suster', 'wa_nurse_call_msg_keluarga',
            'wa_low_volume_msg_suster', 'wa_low_volume_msg_keluarga',
            'wa_tpm_zero_msg_suster',   'wa_tpm_zero_msg_keluarga',
            'wa_tpm_high_msg_suster',   'wa_tpm_high_msg_keluarga',
            'wa_resolved_msg_keluarga', 'wa_resolved_msg_suster',
            'wa_welcome_keluarga',
        ];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) setSetting($f, trim($_POST[$f]));
        }
        $message = 'Template pesan WhatsApp berhasil disimpan!';
    }

    elseif ($action === 'test_wa') {
        $testTarget = trim($_POST['test_target'] ?? '');
        if (empty($testTarget)) {
            $message = 'Masukkan nomor telepon tujuan untuk melakukan pengujian!';
            $msgType = 'danger';
        } else {
            $testMsg = "✅ *TEST NOTIFIKASI SYSTEM*\nSmart Infus — Central Monitor\nWaktu: " . date('d/m/Y H:i:s') . "\n\nIntegrasi API WhatsApp Gateway berhasil terhubung!";
            $result  = sendWhatsApp($testTarget, $testMsg);
            if ($result['success']) {
                $message    = "Pesan uji coba sukses terkirim ke: $testTarget";
                $testResult = $result;
            } else {
                $message    = 'Koneksi API Gagal: ' . ($result['error'] ?? json_encode($result['response'] ?? ''));
                $msgType    = 'danger';
                $testResult = $result;
            }
        }
    }

    elseif ($action === 'optimize_db') {
        try {
            $db = getDB();
            $infusDays = (int)getSetting('db_infus_data_retention', '3');
            $logDays   = (int)getSetting('db_nurse_log_retention', '7');

            $prune1 = $db->prepare("DELETE FROM infus_data WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $prune1->execute([':days' => $infusDays]);
            $deletedInfus = $prune1->rowCount();

            $prune2 = $db->prepare("DELETE FROM nurse_call_log WHERE status = 0 AND resolved_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $prune2->execute([':days' => $logDays]);
            $deletedNurse = $prune2->rowCount();

            $db->query("OPTIMIZE TABLE infus_data");
            $db->query("OPTIMIZE TABLE nurse_call_log");

            $message = "Database dioptimalkan! Terhapus: {$deletedInfus} data infus, {$deletedNurse} log panggilan.";
        } catch (\Exception $e) {
            $message = 'Gagal mengoptimalkan database: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }

    elseif ($action === 'prune_device') {
        $pruneDeviceId = trim($_POST['prune_device_id'] ?? '');
        $pruneType     = $_POST['prune_type'] ?? 'all'; // 'infus' | 'nurse' | 'all'

        if (empty($pruneDeviceId)) {
            $message = 'Pilih perangkat terlebih dahulu.';
            $msgType = 'danger';
        } else {
            try {
                $db = getDB();

                // Pastikan device valid
                $devCheck = $db->prepare("SELECT nama FROM devices WHERE device_id = :id");
                $devCheck->execute([':id' => $pruneDeviceId]);
                $devRow = $devCheck->fetch();

                if (!$devRow) {
                    $message = 'Perangkat tidak ditemukan.';
                    $msgType = 'danger';
                } else {
                    $devNama        = $devRow['nama'];
                    $deletedInfus   = 0;
                    $deletedNurse   = 0;

                    if ($pruneType === 'infus' || $pruneType === 'all') {
                        $s = $db->prepare("DELETE FROM infus_data WHERE device_id = :id");
                        $s->execute([':id' => $pruneDeviceId]);
                        $deletedInfus = $s->rowCount();
                    }

                    if ($pruneType === 'nurse' || $pruneType === 'all') {
                        $s = $db->prepare("DELETE FROM nurse_call_log WHERE device_id = :id AND status = 0");
                        $s->execute([':id' => $pruneDeviceId]);
                        $deletedNurse = $s->rowCount();
                    }

                    $db->query("OPTIMIZE TABLE infus_data");
                    if ($pruneType === 'nurse' || $pruneType === 'all') {
                        $db->query("OPTIMIZE TABLE nurse_call_log");
                    }

                    $parts = [];
                    if ($deletedInfus > 0) $parts[] = "{$deletedInfus} data sensor";
                    if ($deletedNurse > 0) $parts[] = "{$deletedNurse} log nurse call";
                    $detail  = !empty($parts) ? implode(', ', $parts) : 'tidak ada data';
                    $message = "Data perangkat '{$devNama}' berhasil dihapus ({$detail}).";
                }
            } catch (\Exception $e) {
                $message = 'Gagal menghapus data: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
} // end if POST

$settings      = getAllSettings();

// Ambil daftar devices + jumlah data untuk fitur prune per device
try {
    $allDevices = getDB()->query("
        SELECT d.device_id, d.nama, d.lokasi, d.pasien,
               (SELECT COUNT(*) FROM infus_data   WHERE device_id = d.device_id) AS cnt_infus,
               (SELECT COUNT(*) FROM nurse_call_log WHERE device_id = d.device_id AND status = 0) AS cnt_nurse
        FROM devices d
        WHERE d.aktif = 1
        ORDER BY d.nama ASC
    ")->fetchAll();
} catch (\Throwable $e) {
    $allDevices = [];
}
$waProvider    = $settings['wa_provider'] ?? 'custom';
$apiUrl        = $settings['wa_api_url'] ?? '';
$apiKey        = $settings['wa_api_key'] ?? '';
$fonnte_token  = $settings['fonnte_token'] ?? '';
// Backward compat: jika provider belum tersimpan tapi fonnte_token ada, deteksi otomatis
if (empty($waProvider) && !empty($fonnte_token) && empty($apiUrl)) {
    $waProvider = 'fonnte';
}
$loginRequired = $settings['login_required'] ?? '0';

$msgNCSuster    = $settings['wa_nurse_call_msg_suster']   ?? '';
$msgNCKeluarga  = $settings['wa_nurse_call_msg_keluarga'] ?? '';
$msgLVSuster    = $settings['wa_low_volume_msg_suster']   ?? '';
$msgLVKeluarga  = $settings['wa_low_volume_msg_keluarga'] ?? '';
$msgTPMSuster   = $settings['wa_tpm_zero_msg_suster']     ?? '';
$msgTPMKeluarga = $settings['wa_tpm_zero_msg_keluarga']   ?? '';
$msgTPMHSuster  = $settings['wa_tpm_high_msg_suster']     ?? '';
$msgTPMHKeluarga= $settings['wa_tpm_high_msg_keluarga']   ?? '';
$msgOKKeluarga  = $settings['wa_resolved_msg_keluarga']   ?? '';
$msgOKSuster    = $settings['wa_resolved_msg_suster']     ?? '';
$msgWelcome     = $settings['wa_welcome_keluarga']         ?? '';

$infusRetention = (int)($settings['db_infus_data_retention'] ?? 3);
$nurseRetention = (int)($settings['db_nurse_log_retention']  ?? 7);

if (empty($settings['db_infus_data_retention'])) { setSetting('db_infus_data_retention', '3'); $infusRetention = 3; }
if (empty($settings['db_nurse_log_retention']))  { setSetting('db_nurse_log_retention',  '7'); $nurseRetention = 7; }

ensureUsersTable();
$activePage = 'settings';

// Detect active tab from URL param (default: gateway)
$activeTab = $_GET['tab'] ?? 'gateway';
if (!in_array($activeTab, ['gateway', 'database', 'templates'])) $activeTab = 'gateway';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Settings — Smart Infus Central</title>
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <style>
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .settings-tab-btn { transition: all .18s ease; }
    .settings-tab-btn.active {
      background: #6b2072;
      color: #fff;
      box-shadow: 0 4px 14px rgba(107,32,114,.22);
    }
    .settings-tab-btn:not(.active):hover {
      background: #f1f5f9;
      color: #0f172a;
    }
    .template-accordion { cursor: pointer; user-select: none; }
    .template-body { display: none; }
    .template-body.open { display: block; }
    .template-chevron { transition: transform .2s; }
    .template-chevron.open { transform: rotate(180deg); }
    /* Textarea monospace override */
    textarea.mono { font-family: 'Menlo', 'Consolas', 'Courier New', monospace; }

    /* Provider card: selected state */
    .provider-card {
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 14px;
      transition: border-color .18s, background .18s, box-shadow .18s;
      cursor: pointer;
    }
    .provider-card:hover {
      border-color: rgba(107,32,114,.35);
    }
    .provider-card.selected {
      border-color: #6b2072;
      background: #f7eef9;
      box-shadow: 0 0 0 3px rgba(107,32,114,.12), 0 2px 8px rgba(107,32,114,.10);
    }
    .provider-card.selected .provider-title {
      color: #6b2072;
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-primary/10 selection:text-primary">

  <?php require __DIR__ . '/config/navbar.php'; ?>

  <main class="max-w-5xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1">

    <!-- ── PAGE HEADER ── -->
    <div class="bg-white border border-slate-200/60 rounded-2xl p-6 shadow-sm mb-6 relative overflow-hidden">
      <div class="absolute -right-12 -top-12 w-32 h-32 bg-primary/5 rounded-full blur-2xl pointer-events-none"></div>
      <div class="flex items-center justify-between gap-4 relative z-10">
        <div>
          <span class="text-[10px] font-black text-primary uppercase tracking-widest bg-primary/5 border border-primary/10 px-3 py-1 rounded-full">Superadmin</span>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight mt-3">Pengaturan Sistem</h1>
          <p class="text-slate-500 text-sm mt-1">Konfigurasi koneksi, keamanan, database, dan template pesan notifikasi klinis.</p>
        </div>
        <div class="w-12 h-12 bg-purple-50 text-primary rounded-xl flex items-center justify-center text-xl border border-purple-100 shadow-sm shrink-0">
          <i class="bi bi-sliders"></i>
        </div>
      </div>
    </div>

    <!-- ── GLOBAL ALERT ── -->
    <?php if ($message): ?>
    <div id="settings-alert" class="p-4 mb-5 rounded-xl flex items-center gap-3 border transition-all <?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
      <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 <?= $msgType === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' ?>">
        <i class="bi bi-<?= $msgType === 'success' ? 'check2-circle' : 'exclamation-circle' ?> text-sm"></i>
      </div>
      <span class="text-xs font-bold tracking-wide flex-1"><?= esc($message) ?></span>
      <button onclick="document.getElementById('settings-alert').remove()" class="text-slate-400 hover:text-slate-600 transition-colors shrink-0">
        <i class="bi bi-x-lg text-sm"></i>
      </button>
    </div>
    <script>
      setTimeout(() => {
        const el = document.getElementById('settings-alert');
        if (el) { el.style.opacity = '0'; el.style.transform = 'translateY(-6px)'; setTimeout(() => el.remove(), 300); }
      }, 7000);
    </script>
    <?php endif; ?>

    <!-- ── TAB NAVIGATION ── -->
    <div class="flex flex-wrap gap-2 mb-6">
      <button onclick="switchTab('gateway')" id="tab-gateway"
        class="settings-tab-btn <?= $activeTab === 'gateway' ? 'active' : '' ?> flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold">
        <i class="bi bi-whatsapp"></i> Koneksi & Keamanan
      </button>
      <button onclick="switchTab('database')" id="tab-database"
        class="settings-tab-btn <?= $activeTab === 'database' ? 'active' : '' ?> flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600">
        <i class="bi bi-database-fill"></i> Database
      </button>
      <button onclick="switchTab('templates')" id="tab-templates"
        class="settings-tab-btn <?= $activeTab === 'templates' ? 'active' : '' ?> flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600">
        <i class="bi bi-chat-text-fill"></i> Template Pesan WA
      </button>
    </div>

    <!-- ═══════════════════════════════════════════
         TAB 1 — KONEKSI & KEAMANAN
         ═══════════════════════════════════════════ -->
    <div id="panel-gateway" class="tab-panel <?= $activeTab === 'gateway' ? 'active' : '' ?>">
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">

        <!-- FORM: WhatsApp Gateway + Security -->
        <div class="lg:col-span-3">
          <form method="POST" action="settings.php?tab=gateway" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <input type="hidden" name="action" value="save_gateway" />

            <!-- Section: WA Provider Selector -->
            <div class="p-5 border-b border-slate-100">
              <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase flex items-center gap-2 mb-4">
                <div class="w-7 h-7 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center border border-emerald-100 shrink-0">
                  <i class="bi bi-whatsapp text-sm"></i>
                </div>
                WhatsApp Gateway
              </h2>

              <!-- Provider toggle cards -->
              <p class="text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-3">Pilih Provider Aktif</p>
              <div class="grid grid-cols-2 gap-3 mb-5">
                <!-- Custom Gateway -->
                <label class="relative cursor-pointer" onclick="switchProvider('custom')">
                  <input type="radio" name="wa_provider" value="custom" id="prov_custom"
                         <?= $waProvider === 'custom' ? 'checked' : '' ?>
                         class="sr-only" />
                  <div id="card-custom" class="provider-card <?= $waProvider === 'custom' ? 'selected' : '' ?>">
                    <div class="flex items-center gap-2.5 mb-1.5">
                      <i class="bi bi-server text-base text-primary"></i>
                      <span class="text-xs font-black text-slate-800 provider-title">Custom Gateway</span>
                    </div>
                    <p class="text-[10px] text-slate-500 leading-relaxed">gateway pribadi lainnya.</p>
                    <div class="mt-2 text-[9px] font-bold <?= (!empty($apiUrl) && !empty($apiKey)) ? 'text-emerald-600' : 'text-slate-400' ?>">
                      <i class="bi bi-<?= (!empty($apiUrl) && !empty($apiKey)) ? 'check-circle-fill' : 'dash-circle' ?>"></i>
                      <?= (!empty($apiUrl) && !empty($apiKey)) ? 'Terkonfigurasi' : 'Belum dikonfigurasi' ?>
                    </div>
                  </div>
                </label>

                <!-- Fonnte -->
                <label class="relative cursor-pointer" onclick="switchProvider('fonnte')">
                  <input type="radio" name="wa_provider" value="fonnte" id="prov_fonnte"
                         <?= $waProvider === 'fonnte' ? 'checked' : '' ?>
                         class="sr-only" />
                  <div id="card-fonnte" class="provider-card <?= $waProvider === 'fonnte' ? 'selected' : '' ?>">
                    <div class="flex items-center gap-2.5 mb-1.5">
                      <i class="bi bi-cloud-fill text-base text-sky-500"></i>
                      <span class="text-xs font-black text-slate-800 provider-title">Fonnte</span>
                    </div>
                    <p class="text-[10px] text-slate-500 leading-relaxed">Layanan cloud resmi — <a href="https://fonnte.com" target="_blank" class="text-sky-600 underline" onclick="event.stopPropagation()">fonnte.com</a></p>
                    <div class="mt-2 text-[9px] font-bold <?= !empty($fonnte_token) ? 'text-emerald-600' : 'text-slate-400' ?>">
                      <i class="bi bi-<?= !empty($fonnte_token) ? 'check-circle-fill' : 'dash-circle' ?>"></i>
                      <?= !empty($fonnte_token) ? 'Token terpasang' : 'Token belum diisi' ?>
                    </div>
                  </div>
                </label>
              </div>

              <!-- Panel: Custom Gateway fields -->
              <div id="cfg-custom" class="<?= $waProvider !== 'custom' ? 'hidden' : '' ?> flex flex-col gap-4">
                <div class="p-3 bg-purple-50 border border-purple-100 rounded-xl text-[10px] text-purple-800 font-medium leading-relaxed flex gap-2">
                  <i class="bi bi-info-circle-fill shrink-0 mt-0.5 text-primary"></i>
                  Isi URL endpoint API dan API Key gateway Anda. Sistem akan mengirim <code class="font-mono bg-white/70 px-1 rounded">POST</code> dengan JSON body ke URL ini.
                </div>
                <div>
                  <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                    <i class="bi bi-link-45deg text-primary mr-1"></i> Gateway API URL
                  </label>
                  <input type="text" name="wa_api_url" id="wa_api_url"
                         value="<?= esc($apiUrl) ?>"
                         placeholder="http://localhost:3000/api/whatsapp"
                         class="w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-primary/5" />
                  <div class="text-[9px] text-slate-400 mt-1">URL base gateway. Endpoint <code class="font-mono">/send-text</code> atau <code class="font-mono">/send-bulk</code> ditambahkan otomatis.</div>
                </div>
                <div>
                  <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                    <i class="bi bi-key-fill text-primary mr-1"></i> API Key
                  </label>
                  <div class="relative">
                    <input type="password" name="wa_api_key" id="wa_api_key"
                           value="<?= esc($apiKey) ?>"
                           placeholder="x-api-key gateway Anda..."
                           class="w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl pl-4 pr-12 py-2.5 text-xs font-semibold font-mono text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-primary/5" />
                    <button type="button" onclick="toggleTokenVisibility('wa_api_key','token-eye-icon')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1 text-xs transition-colors">
                      <i class="bi bi-eye" id="token-eye-icon"></i>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Panel: Fonnte fields -->
              <div id="cfg-fonnte" class="<?= $waProvider !== 'fonnte' ? 'hidden' : '' ?> flex flex-col gap-4">
                <div class="p-3 bg-sky-50 border border-sky-100 rounded-xl text-[10px] text-sky-800 font-medium leading-relaxed flex gap-2">
                  <i class="bi bi-info-circle-fill shrink-0 mt-0.5 text-sky-500"></i>
                  Daftarkan perangkat WhatsApp di <a href="https://fonnte.com" target="_blank" class="underline font-bold">fonnte.com</a>, lalu salin token API dari dashboard Fonnte ke field di bawah.
                </div>
                <div>
                  <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                    <i class="bi bi-key-fill text-sky-500 mr-1"></i> Fonnte API Token
                  </label>
                  <div class="relative">
                    <input type="password" name="fonnte_token" id="fonnte_token"
                           value="<?= esc($fonnte_token) ?>"
                           placeholder="Token dari dashboard Fonnte..."
                           class="w-full bg-slate-50 border border-slate-200 focus:border-sky-400 focus:bg-white rounded-xl pl-4 pr-12 py-2.5 text-xs font-semibold font-mono text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-sky-500/10" />
                    <button type="button" onclick="toggleTokenVisibility('fonnte_token','fonnte-eye-icon')"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1 text-xs transition-colors">
                      <i class="bi bi-eye" id="fonnte-eye-icon"></i>
                    </button>
                  </div>
                  <div class="text-[9px] text-slate-400 mt-1">Token didapat dari menu <strong>Devices → Token</strong> di dashboard Fonnte.</div>
                </div>
                <!-- Fonnte docs quick link -->
                <div class="flex items-center gap-2 text-[10px] text-sky-700 font-semibold">
                  <i class="bi bi-box-arrow-up-right"></i>
                  <a href="https://fonnte.com/docs" target="_blank" class="hover:underline">Buka dokumentasi Fonnte API</a>
                </div>
              </div>

              <!-- Active provider status badge -->
              <?php
              $activeOk = ($waProvider === 'fonnte') ? !empty($fonnte_token) : (!empty($apiUrl) && !empty($apiKey));
              ?>
              <div class="mt-4 p-3.5 rounded-xl border flex items-center gap-3 <?= $activeOk ? 'bg-emerald-50/50 border-emerald-100 text-emerald-800' : 'bg-amber-50/50 border-amber-100 text-amber-800' ?>">
                <i class="bi bi-<?= $activeOk ? 'patch-check-fill text-emerald-600' : 'exclamation-triangle-fill text-amber-500' ?> text-base shrink-0"></i>
                <div>
                  <div class="text-xs font-bold">
                    <?= $activeOk ? 'Provider Aktif: ' . ($waProvider === 'fonnte' ? 'Fonnte' : 'Custom Gateway') : 'Provider Belum Siap' ?>
                  </div>
                  <div class="text-[10px] font-medium text-slate-500 mt-0.5">
                    <?= $activeOk ? 'Siap mengirim notifikasi klinis.' : 'Lengkapi konfigurasi provider yang dipilih.' ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section: Keamanan -->
            <div class="p-5 border-b border-slate-100">
              <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase flex items-center gap-2 mb-4">
                <div class="w-7 h-7 bg-purple-50 text-primary rounded-lg flex items-center justify-center border border-purple-100 shrink-0">
                  <i class="bi bi-shield-lock-fill text-sm"></i>
                </div>
                Keamanan & Akses
              </h2>

              <div class="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                <label class="flex items-center gap-3 cursor-pointer">
                  <div class="relative shrink-0">
                    <input type="checkbox" name="login_required" id="login_required" value="1" <?= $loginRequired === '1' ? 'checked' : '' ?>
                           class="sr-only peer" />
                    <div class="w-10 h-5 bg-slate-200 peer-checked:bg-primary rounded-full transition-colors"></div>
                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                  </div>
                  <div>
                    <div class="text-xs font-bold text-slate-800">Wajib Login untuk Semua Halaman</div>
                    <div class="text-[10px] font-medium text-slate-500 mt-0.5 leading-relaxed">
                      <span class="text-emerald-600 font-bold">OFF</span>: Dashboard & Docs bebas diakses publik.<br>
                      <span class="text-primary font-bold">ON</span>: Semua halaman wajib login.
                    </div>
                  </div>
              </div>
              
              <!-- IoT API Key -->
              <div class="mt-5 pt-5 border-t border-slate-200/60">
                <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                  <i class="bi bi-link-45deg text-primary mr-1"></i> URL Aplikasi (untuk Link Monitor Keluarga)
                </label>
                <input type="text" name="app_url" id="app_url"
                       value="<?= esc($settings['app_url'] ?? '') ?>"
                       placeholder="https://namadomain.com"
                       class="w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-primary/5" />
                <div class="text-[9px] font-medium text-slate-500 mt-1.5 leading-relaxed">
                  <i class="bi bi-info-circle text-blue-500"></i>
                  URL ini digunakan untuk membuat tautan <code class="font-mono">monitor.php?token=...</code> yang dikirim ke keluarga. Kosongkan jika ingin sistem mendeteksi otomatis.
                </div>
              </div>

              <!-- IoT API Key -->
              <div class="mt-5 pt-5 border-t border-slate-200/60">
                <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                  <i class="bi bi-key-fill text-primary mr-1"></i> API Key IoT (Keamanan Perangkat)
                </label>
                <div class="relative">
                  <input type="password" name="iot_api_key" id="iot_api_key"
                         value="<?= esc($settings['iot_api_key'] ?? '') ?>"
                         placeholder="Masukkan API Key untuk alat infus..."
                         class="w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl pl-4 pr-12 py-2.5 text-xs font-semibold font-mono text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-primary/5" />
                  <button type="button" onclick="toggleIotTokenVisibility()"
                          class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1 text-xs transition-colors">
                    <i class="bi bi-eye" id="iot-token-eye-icon"></i>
                  </button>
                </div>
                <div class="text-[9px] font-medium text-slate-500 mt-1.5 leading-relaxed">
                  <i class="bi bi-shield-check text-emerald-700"></i>
                  API Key ini harus dicocokkan dengan konfigurasi pada mikrokontroler (ESP32) agar data infus terverifikasi.
                </div>
              </div>
            </div>

            <!-- Save Button -->
            <div class="p-5">
              <button type="submit" style="background:#6b2072;color:#fff;" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 text-white rounded-xl text-xs font-bold tracking-wide shadow-md active:scale-[0.99] transition-all cursor-pointer hover:opacity-90">
                <i class="bi bi-save2-fill"></i> SIMPAN KONEKSI & KEAMANAN
              </button>
            </div>
          </form>
        </div>

        <!-- SIDEBAR: Test Gateway -->
        <div class="lg:col-span-2 flex flex-col gap-5">

          <!-- Test WA Card -->
          <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
              <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center border border-blue-100">
                <i class="bi bi-send-check text-sm"></i>
              </div>
              <div>
                <h3 class="text-xs font-black text-slate-900 tracking-wider uppercase">Uji Koneksi API</h3>
                <p class="text-[10px] text-slate-400 font-medium">Kirim pesan uji ke nomor tujuan</p>
              </div>
            </div>
            <form method="POST" action="settings.php?tab=gateway" class="p-4 flex flex-col gap-3">
              <input type="hidden" name="action" value="test_wa" />
              <div class="relative">
                <i class="bi bi-telephone text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 text-xs"></i>
                <input type="text" name="test_target"
                       placeholder="Target: 628123456789"
                       class="w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-slate-800 placeholder:text-slate-400 outline-none transition-all focus:ring-4 focus:ring-primary/5" />
              </div>
              <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold tracking-wide active:scale-95 transition-all cursor-pointer">
                <i class="bi bi-cursor-fill"></i> KIRIM UJI COBA
              </button>
              <span class="text-[9px] font-medium text-slate-400 leading-relaxed">
                <i class="bi bi-exclamation-circle text-amber-500"></i> Gunakan kode negara tanpa spasi (Contoh: <span class="font-bold text-slate-600">628xxx</span>).
              </span>

              <?php if ($testResult !== null): ?>
              <div class="mt-1 p-3.5 bg-slate-900 rounded-xl border border-slate-800">
                <div class="text-[9px] font-black text-slate-500 tracking-widest uppercase mb-1.5">Response Logger:</div>
                <pre class="text-[11px] text-emerald-400 font-mono overflow-x-auto whitespace-pre-wrap max-h-40"><?= esc(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
              </div>
              <?php endif; ?>
            </form>
          </div>

          <!-- Quick Guide -->
          <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-2.5">
              <i class="bi bi-info-circle-fill text-amber-500 text-base"></i>
              <h3 class="text-xs font-black text-slate-900 tracking-wider uppercase">Panduan Singkat</h3>
            </div>
            <div class="p-4 flex flex-col gap-3">
              <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Custom Gateway</p>
              <ol class="flex flex-col gap-2 text-xs font-medium text-slate-600">
                <li class="flex gap-2 items-start">
                  <span class="w-4 h-4 rounded-full bg-primary text-white text-[9px] font-black flex items-center justify-center shrink-0 mt-0.5">1</span>
                  Jalankan gateway (Baileys / whatsapp-web.js) di server Anda.
                </li>
                <li class="flex gap-2 items-start">
                  <span class="w-4 h-4 rounded-full bg-primary text-white text-[9px] font-black flex items-center justify-center shrink-0 mt-0.5">2</span>
                  Pilih <strong>Custom Gateway</strong>, isi URL &amp; API Key.
                </li>
                <li class="flex gap-2 items-start">
                  <span class="w-4 h-4 rounded-full bg-primary text-white text-[9px] font-black flex items-center justify-center shrink-0 mt-0.5">3</span>
                  Tekan "Kirim Uji Coba" untuk validasi.
                </li>
              </ol>
              <div class="border-t border-slate-100 pt-3">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Fonnte (backup / utama)</p>
                <ol class="flex flex-col gap-2 text-xs font-medium text-slate-600">
                  <li class="flex gap-2 items-start">
                    <span class="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] font-black flex items-center justify-center shrink-0 mt-0.5">1</span>
                    Daftar di <a href="https://fonnte.com" target="_blank" class="text-sky-600 underline">fonnte.com</a> &amp; sambungkan WA.
                  </li>
                  <li class="flex gap-2 items-start">
                    <span class="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] font-black flex items-center justify-center shrink-0 mt-0.5">2</span>
                    Pilih <strong>Fonnte</strong>, paste token dari dashboard.
                  </li>
                  <li class="flex gap-2 items-start">
                    <span class="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] font-black flex items-center justify-center shrink-0 mt-0.5">3</span>
                    Simpan &amp; uji coba — bisa dipakai saat gateway lokal mati.
                  </li>
                </ol>
              </div>
              <div class="border-t border-slate-100 pt-3 flex flex-col gap-1.5 text-xs text-slate-600">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Pemicu Otomatis</p>
                <div class="flex gap-2 items-start">
                  <i class="bi bi-bell-fill text-rose-500 mt-0.5 text-xs shrink-0"></i>
                  <span><span class="font-bold">Nurse Call</span> — terkirim seketika saat tombol ditekan.</span>
                </div>
                <div class="flex gap-2 items-start">
                  <i class="bi bi-droplet-half text-amber-500 mt-0.5 text-xs shrink-0"></i>
                  <span><span class="font-bold">Volume Kritis</span> — saat sisa cairan ≤ 20 ml.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════
         TAB 2 — DATABASE
         ═══════════════════════════════════════════ -->
    <div id="panel-database" class="tab-panel <?= $activeTab === 'database' ? 'active' : '' ?>">
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">

        <!-- Retention Form -->
        <div class="lg:col-span-3">
          <form method="POST" action="settings.php?tab=database" class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <input type="hidden" name="action" value="save_retention" />
            <div class="p-5 border-b border-slate-100">
              <h2 class="text-xs font-black text-slate-900 tracking-wider uppercase flex items-center gap-2 mb-4">
                <div class="w-7 h-7 bg-purple-50 text-primary rounded-lg flex items-center justify-center border border-purple-100 shrink-0">
                  <i class="bi bi-database-fill text-sm"></i>
                </div>
                Retensi Data Otomatis
              </h2>
              <p class="text-xs text-slate-500 leading-relaxed mb-5">
                Tentukan berapa lama data historis disimpan sebelum dihapus secara otomatis oleh sistem di latar belakang.
              </p>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <!-- Infus Retention -->
                <div class="p-4 border border-slate-100 rounded-xl bg-slate-50/50">
                  <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-2">
                    <i class="bi bi-droplet-fill text-primary mr-1"></i> Data Infus
                  </label>
                  <div class="flex items-center gap-3">
                    <input type="number" name="db_infus_data_retention" min="1" max="180"
                           value="<?= esc($infusRetention) ?>"
                           class="w-20 bg-white border border-slate-200 focus:border-primary rounded-xl px-3 py-2 text-sm font-black text-slate-800 outline-none transition-all focus:ring-4 focus:ring-primary/5 text-center" />
                    <span class="text-xs font-bold text-slate-500">Hari</span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-2">Rekomendasi: <strong>3 hari</strong><br>(laju data tinggi)</p>
                </div>

                <!-- Nurse Log Retention -->
                <div class="p-4 border border-slate-100 rounded-xl bg-slate-50/50">
                  <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-2">
                    <i class="bi bi-clipboard2-pulse-fill text-primary mr-1"></i> Log Nurse Call
                  </label>
                  <div class="flex items-center gap-3">
                    <input type="number" name="db_nurse_log_retention" min="1" max="365"
                           value="<?= esc($nurseRetention) ?>"
                           class="w-20 bg-white border border-slate-200 focus:border-primary rounded-xl px-3 py-2 text-sm font-black text-slate-800 outline-none transition-all focus:ring-4 focus:ring-primary/5 text-center" />
                    <span class="text-xs font-bold text-slate-500">Hari</span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-2">Rekomendasi: <strong>7 hari</strong><br>(log panggilan selesai)</p>
                </div>
              </div>
            </div>
            <div class="p-5">
              <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold tracking-wide shadow-md shadow-primary/10 active:scale-[0.99] transition-all cursor-pointer">
                <i class="bi bi-save2-fill"></i> SIMPAN PENGATURAN RETENSI
              </button>
            </div>
          </form>
        </div>

        <!-- Optimize Card -->
        <div class="lg:col-span-2 flex flex-col gap-5">
          <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
              <div class="w-8 h-8 bg-purple-50 text-primary rounded-xl flex items-center justify-center border border-purple-100">
                <i class="bi bi-database-fill-gear text-sm"></i>
              </div>
              <div>
                <h3 class="text-xs font-black text-slate-900 tracking-wider uppercase">Optimasi Manual</h3>
                <p class="text-[10px] text-slate-400 font-medium">Reklamasi & defragmentasi disk</p>
              </div>
            </div>
            <form method="POST" action="settings.php?tab=database" class="p-4 flex flex-col gap-3.5">
              <input type="hidden" name="action" value="optimize_db" />
              <p class="text-xs text-slate-600 leading-relaxed">
                Hapus data lama secara manual sesuai batas retensi, lalu jalankan <code class="font-mono text-[10px] bg-slate-100 text-primary px-1 py-0.5 rounded">OPTIMIZE TABLE</code> untuk merekonstruksi indeks fisik MySQL.
              </p>
              <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl text-[10px] font-medium text-amber-800 flex gap-2">
                <i class="bi bi-exclamation-triangle-fill shrink-0 mt-0.5"></i>
                Proses ini mengunci tabel sebentar. Jalankan saat trafik rendah.
              </div>
              <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold tracking-wide active:scale-95 transition-all cursor-pointer shadow-md shadow-primary/10">
                <i class="bi bi-tools"></i> OPTIMALKAN SEKARANG
              </button>
            </form>
          </div>

          <!-- DB Info card -->
          <!-- DB Info card -->
          <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4">
            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Info Pembersihan Otomatis</h3>
            <div class="flex flex-col gap-2 text-xs text-slate-600 leading-relaxed">
              <div class="flex gap-2 items-start">
                <i class="bi bi-recycle text-emerald-600 shrink-0 mt-0.5"></i>
                Berjalan secara probabilistik (1% peluang per request masuk dari alat infus).
              </div>
              <div class="flex gap-2 items-start">
                <i class="bi bi-clock-history text-primary shrink-0 mt-0.5"></i>
                Hanya menghapus log panggilan yang sudah <strong>terselesaikan</strong> (status resolved).
              </div>
            </div>
          </div>

        </div> <!-- end sidebar col -->
      </div> <!-- end grid -->

      <!-- ── Hapus Data Per Perangkat — full width ── -->
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mt-5">
        <div class="p-4 border-b border-slate-100 flex items-center gap-3" style="background:#fff7f7;">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center border flex-shrink-0"
               style="background:#fee2e2;border-color:#fca5a5;color:#dc2626;">
            <i class="bi bi-cpu-fill text-sm"></i>
          </div>
          <div>
            <h3 class="text-xs font-black text-slate-900 tracking-wider uppercase">Hapus Data Per Perangkat</h3>
            <p class="text-[10px] text-slate-400 font-medium">Pilih device yang datanya ingin dibersihkan</p>
          </div>
        </div>
        <form method="POST" action="settings.php?tab=database" class="p-5" onsubmit="return confirmPrune(this)">
          <input type="hidden" name="action" value="prune_device" />

          <div class="grid grid-cols-1 md:grid-cols-3 gap-5 items-start">

            <!-- Kolom 1: Pilih Perangkat + Info -->
            <div class="flex flex-col gap-3">
              <div>
                <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                  <i class="bi bi-cpu mr-1" style="color:#6b2072;"></i> Perangkat
                </label>
                <select name="prune_device_id" id="prune_device_id" required
                        onchange="updatePruneInfo(this)"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 outline-none">
                  <option value="">— Pilih perangkat —</option>
                  <?php foreach ($allDevices as $dev): ?>
                  <option value="<?= esc($dev['device_id']) ?>"
                          data-nama="<?= esc($dev['nama']) ?>"
                          data-pasien="<?= esc($dev['pasien'] ?: '-') ?>"
                          data-cnt-infus="<?= (int)$dev['cnt_infus'] ?>"
                          data-cnt-nurse="<?= (int)$dev['cnt_nurse'] ?>">
                    <?= esc($dev['nama']) ?><?= $dev['pasien'] ? ' — ' . esc($dev['pasien']) : '' ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div id="prune-info" class="hidden p-3 rounded-xl text-[11px] text-slate-600"
                   style="background:#f8fafc;border:1px solid #e2e8f0;">
                <div class="flex justify-between mb-1.5">
                  <span class="font-bold text-slate-500">Data sensor:</span>
                  <span id="prune-info-infus" class="font-black text-slate-800">—</span>
                </div>
                <div class="flex justify-between">
                  <span class="font-bold text-slate-500">Log nurse call selesai:</span>
                  <span id="prune-info-nurse" class="font-black text-slate-800">—</span>
                </div>
              </div>
            </div>

            <!-- Kolom 2: Tipe Data -->
            <div>
              <label class="block text-[10px] font-bold text-slate-400 tracking-wider uppercase mb-1.5">
                <i class="bi bi-trash3 mr-1" style="color:#ef4444;"></i> Data yang Dihapus
              </label>
              <div class="flex flex-col gap-2">
                <?php foreach ([
                  ['all',   'Semua data (sensor + nurse call)'],
                  ['infus', 'Data sensor infus saja'],
                  ['nurse', 'Log nurse call selesai saja'],
                ] as [$val, $label]): ?>
                <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors">
                  <input type="radio" name="prune_type" value="<?= $val ?>"
                         <?= $val === 'all' ? 'checked' : '' ?>
                         style="accent-color:#6b2072;width:14px;height:14px;flex-shrink:0;" />
                  <span class="text-xs font-semibold text-slate-700"><?= $label ?></span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Kolom 3: Warning + Tombol -->
            <div class="flex flex-col gap-3">
              <div class="p-3 rounded-xl text-[10px] font-medium leading-relaxed flex gap-2"
                   style="background:#fff1f2;border:1px solid #fecdd3;color:#9f1239;">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-0.5"></i>
                Data yang dihapus <strong>tidak bisa dikembalikan</strong>. Pastikan sudah memilih perangkat yang tepat.
              </div>
              <button type="submit"
                      style="width:100%;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 16px;background:#dc2626;color:#fff;border:none;border-radius:12px;font-size:12px;font-weight:700;letter-spacing:.03em;cursor:pointer;box-shadow:0 4px 12px rgba(220,38,38,.25);"
                      onmouseover="this.style.background='#b91c1c'"
                      onmouseout="this.style.background='#dc2626'">
                <i class="bi bi-trash3-fill"></i> HAPUS DATA PERANGKAT
              </button>
            </div>

          </div>
        </form>
      </div>

    </div> <!-- end panel-database -->

    <!-- ═══════════════════════════════════════════
         TAB 3 — TEMPLATE PESAN WA
         ═══════════════════════════════════════════ -->
    <div id="panel-templates" class="tab-panel <?= $activeTab === 'templates' ? 'active' : '' ?>">
      <form method="POST" action="settings.php?tab=templates" class="flex flex-col gap-5">
        <input type="hidden" name="action" value="save_templates" />

        <!-- Header info -->
        <div class="bg-white border border-slate-200/60 rounded-2xl p-5 shadow-sm">
          <div class="flex items-start gap-4">
            <div class="w-9 h-9 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center border border-emerald-100 shrink-0">
              <i class="bi bi-whatsapp text-base"></i>
            </div>
            <div class="flex-1">
              <h2 class="text-sm font-black text-slate-900 tracking-wide uppercase">Template Pesan WhatsApp</h2>
              <p class="text-xs text-slate-500 mt-1">Setiap alarm memiliki dua versi pesan: <strong>Suster</strong> (teknis) dan <strong>Keluarga</strong> (umum). Klik nama alarm untuk membuka atau menutup template.</p>
              <div class="flex flex-wrap gap-1.5 mt-3">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1 mt-0.5">Variabel global:</span>
                <?php foreach (['{pasien}','{lokasi}','{waktu}','{device}','{volume}','{persen}','{tpm}','{resolved_label}'] as $v): ?>
                <code class="text-[9px] font-bold bg-slate-100 text-primary border border-slate-200 px-1.5 py-0.5 rounded font-mono"><?= $v ?></code>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <?php
        // Helper to render a template accordion
        function templateBlock(
            string $id, string $color, string $dotColor, string $bgColor, string $borderColor,
            string $icon, string $title,
            string $nameSuster, string $valSuster,
            ?string $nameKeluarga, ?string $valKeluarga,
            array $vars
        ): void {
            $open = 'open'; // default all open
            echo "
            <div class=\"bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden\">
              <div class=\"template-accordion flex items-center justify-between p-4 border-b border-slate-100 bg-{$bgColor}/30 hover:bg-{$bgColor}/60 transition-colors\" onclick=\"toggleAccordion('{$id}')\">
                <div class=\"flex items-center gap-3\">
                  <div class=\"w-8 h-8 bg-{$color}-50 text-{$dotColor} rounded-lg flex items-center justify-center border border-{$color}-100 shrink-0\">
                    <i class=\"bi bi-{$icon} text-sm\"></i>
                  </div>
                  <div>
                    <h3 class=\"text-xs font-black text-slate-900 uppercase tracking-wide\">{$title}</h3>
                    <p class=\"text-[10px] text-slate-400 font-medium mt-0.5\">Suster + Keluarga</p>
                  </div>
                </div>
                <i class=\"bi bi-chevron-down template-chevron {$open} text-slate-400\" id=\"chevron-{$id}\"></i>
              </div>
              <div class=\"template-body {$open} p-4 flex flex-col gap-4\" id=\"body-{$id}\">
                <div class=\"grid grid-cols-1 md:grid-cols-2 gap-4\">";

            // Suster
            echo "
                  <div>
                    <label class=\"flex items-center gap-1.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5\">
                      <span class=\"w-1.5 h-1.5 rounded-full bg-{$dotColor}\"></span> Template Suster
                    </label>
                    <textarea name=\"{$nameSuster}\" rows=\"5\" class=\"mono w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl p-3 text-xs text-slate-700 outline-none transition-all focus:ring-4 focus:ring-primary/5\">" . htmlspecialchars($valSuster) . "</textarea>
                  </div>";

            // Keluarga (optional)
            if ($nameKeluarga !== null) {
                echo "
                  <div>
                    <label class=\"flex items-center gap-1.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5\">
                      <span class=\"w-1.5 h-1.5 rounded-full bg-{$dotColor}\"></span> Template Keluarga
                    </label>
                    <textarea name=\"{$nameKeluarga}\" rows=\"5\" class=\"mono w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl p-3 text-xs text-slate-700 outline-none transition-all focus:ring-4 focus:ring-primary/5\">" . htmlspecialchars($valKeluarga ?? '') . "</textarea>
                  </div>";
            } else {
                echo "<div class=\"flex items-center justify-center p-4 bg-slate-50 rounded-xl border border-slate-100\">
                  <p class=\"text-[10px] text-slate-400 font-medium text-center\"><i class=\"bi bi-people-fill text-slate-300 text-xl block mb-1\"></i>Hanya untuk keluarga — tidak ada template suster terpisah.</p>
                </div>";
            }

            // Variable hints
            echo "
                </div>
                <div class=\"flex flex-wrap gap-1 items-center border-t border-slate-100 pt-3\">
                  <span class=\"text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1\">Variabel:</span>";
            foreach ($vars as $v) {
                echo "<code class=\"text-[9px] font-bold bg-slate-100 text-primary border border-slate-200 px-1.5 py-0.5 rounded font-mono\">{$v}</code>";
            }
            echo "
                </div>
              </div>
            </div>";
        }
        ?>

        <?php templateBlock(
            'nurse_call', 'rose', 'rose-500', 'rose', 'rose',
            'bell-fill', '🚨 Nurse Call — Panggilan Darurat',
            'wa_nurse_call_msg_suster', $msgNCSuster,
            'wa_nurse_call_msg_keluarga', $msgNCKeluarga,
            ['{pasien}','{lokasi}','{waktu}','{device}']
        ); ?>

        <?php templateBlock(
            'low_vol', 'amber', 'amber-500', 'amber', 'amber',
            'droplet-half', '⚠️ Volume Kritis — Cairan ≤ 20 ml',
            'wa_low_volume_msg_suster', $msgLVSuster,
            'wa_low_volume_msg_keluarga', $msgLVKeluarga,
            ['{pasien}','{lokasi}','{volume}','{persen}','{waktu}']
        ); ?>

        <?php templateBlock(
            'tpm_zero', 'purple', 'purple-600', 'purple', 'purple',
            'exclamation-triangle-fill', '🔴 Infus Macet — TPM = 0',
            'wa_tpm_zero_msg_suster', $msgTPMSuster,
            'wa_tpm_zero_msg_keluarga', $msgTPMKeluarga,
            ['{pasien}','{lokasi}','{volume}','{waktu}']
        ); ?>

        <?php templateBlock(
            'tpm_high', 'amber', 'amber-600', 'amber', 'amber',
            'speedometer2', '⚡ TPM Terlalu Cepat — > 80',
            'wa_tpm_high_msg_suster', $msgTPMHSuster,
            'wa_tpm_high_msg_keluarga', $msgTPMHKeluarga,
            ['{pasien}','{lokasi}','{tpm}','{waktu}']
        ); ?>

        <!-- Resolved — Suster + Keluarga -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
          <div class="template-accordion flex items-center justify-between p-4 border-b border-slate-100 bg-emerald-50/30 hover:bg-emerald-50/60 transition-colors" onclick="toggleAccordion('resolved')">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center border border-emerald-100 shrink-0">
                <i class="bi bi-check-circle-fill text-sm"></i>
              </div>
              <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">✅ Kondisi Normal Kembali</h3>
                <p class="text-[10px] text-slate-400 font-medium mt-0.5">Ke suster & keluarga pasien</p>
              </div>
            </div>
            <i class="bi bi-chevron-down template-chevron open text-slate-400" id="chevron-resolved"></i>
          </div>
          <div class="template-body open p-4 flex flex-col gap-4" id="body-resolved">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Template Suster
                </label>
                <textarea name="wa_resolved_msg_suster" rows="5" class="mono w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl p-3 text-xs text-slate-700 outline-none transition-all focus:ring-4 focus:ring-primary/5"><?= esc($msgOKSuster) ?></textarea>
              </div>
              <div>
                <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Template Keluarga
                </label>
                <textarea name="wa_resolved_msg_keluarga" rows="5" class="mono w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl p-3 text-xs text-slate-700 outline-none transition-all focus:ring-4 focus:ring-primary/5"><?= esc($msgOKKeluarga) ?></textarea>
              </div>
            </div>
            <div class="flex flex-wrap gap-1 items-center border-t border-slate-100 pt-3">
              <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1">Variabel:</span>
              <?php foreach (['{pasien}','{lokasi}','{waktu}','{resolved_label}'] as $v): ?>
              <code class="text-[9px] font-bold bg-slate-100 text-primary border border-slate-200 px-1.5 py-0.5 rounded font-mono"><?= $v ?></code>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Welcome Keluarga -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
          <div class="template-accordion flex items-center justify-between p-4 border-b border-slate-100 bg-blue-50/30 hover:bg-blue-50/60 transition-colors" onclick="toggleAccordion('welcome')">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center border border-blue-100 shrink-0">
                <i class="bi bi-link-45deg text-sm"></i>
              </div>
              <div>
                <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide">🔗 Selamat Datang + Link Monitor</h3>
                <p class="text-[10px] text-slate-400 font-medium mt-0.5">Dikirim ke keluarga saat nomor pertama didaftarkan</p>
              </div>
            </div>
            <i class="bi bi-chevron-down template-chevron open text-slate-400" id="chevron-welcome"></i>
          </div>
          <div class="template-body open p-4 flex flex-col gap-4" id="body-welcome">
            <div class="p-3 bg-blue-50 border border-blue-100 rounded-xl text-[10px] text-blue-800 font-medium leading-relaxed flex gap-2">
              <i class="bi bi-info-circle-fill shrink-0 mt-0.5 text-blue-500"></i>
              Pesan ini dikirim otomatis saat nomor keluarga pertama kali dimasukkan ke data perangkat.
              Gunakan variabel <code class="font-mono bg-white/70 px-1 rounded">{monitor_url}</code> untuk menyertakan tautan monitoring privat.
            </div>
            <div class="max-w-md">
              <label class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Template Keluarga
              </label>
              <textarea name="wa_welcome_keluarga" rows="7" class="mono w-full bg-slate-50 border border-slate-200 focus:border-primary focus:bg-white rounded-xl p-3 text-xs text-slate-700 outline-none transition-all focus:ring-4 focus:ring-primary/5"><?= esc($msgWelcome) ?></textarea>
            </div>
            <div class="flex flex-wrap gap-1 items-center border-t border-slate-100 pt-3">
              <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider mr-1">Variabel:</span>
              <?php foreach (['{pasien}','{lokasi}','{waktu}','{monitor_url}'] as $v): ?>
              <code class="text-[9px] font-bold bg-slate-100 text-primary border border-slate-200 px-1.5 py-0.5 rounded font-mono"><?= $v ?></code>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Save Templates Button -->
        <div class="flex items-center gap-3">
          <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-3 bg-primary hover:bg-primary-hover text-white rounded-xl text-xs font-bold tracking-wide shadow-md shadow-primary/10 active:scale-[0.99] transition-all cursor-pointer">
            <i class="bi bi-save2-fill"></i> SIMPAN SEMUA TEMPLATE
          </button>
          <button type="button" onclick="expandAll()" class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all border border-slate-200">
            <i class="bi bi-arrows-expand mr-1"></i> Buka Semua
          </button>
          <button type="button" onclick="collapseAll()" class="px-4 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all border border-slate-200">
            <i class="bi bi-arrows-collapse mr-1"></i> Tutup Semua
          </button>
        </div>

      </form>
    </div>

  </main>

  <!-- MEDICAL WORKSTATION FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Smart Infus Monitoring System &bull; Clinical Station Workspace</p>
  </footer>

  <script>
    // ── Tab switching ──
    const tabIds = ['gateway', 'database', 'templates'];

    function switchTab(id) {
      tabIds.forEach(t => {
        const btn = document.getElementById('tab-' + t);
        const panel = document.getElementById('panel-' + t);
        if (t === id) {
          btn.classList.add('active');
          panel.classList.add('active');
        } else {
          btn.classList.remove('active');
          panel.classList.remove('active');
        }
      });
      // Update URL without reload
      history.replaceState(null, '', 'settings.php?tab=' + id);
    }

    // ── Accordion toggles ──
    const accordionIds = ['nurse_call', 'low_vol', 'tpm_zero', 'tpm_high', 'resolved', 'welcome'];

    function toggleAccordion(id) {
      const body = document.getElementById('body-' + id);
      const chevron = document.getElementById('chevron-' + id);
      body.classList.toggle('open');
      chevron.classList.toggle('open');
    }

    function expandAll() {
      accordionIds.forEach(id => {
        document.getElementById('body-' + id).classList.add('open');
        document.getElementById('chevron-' + id).classList.add('open');
      });
    }

    function collapseAll() {
      accordionIds.forEach(id => {
        document.getElementById('body-' + id).classList.remove('open');
        document.getElementById('chevron-' + id).classList.remove('open');
      });
    }

    // ── Token visibility (generic) ──
    function toggleTokenVisibility(inputId, iconId) {
      const input = document.getElementById(inputId);
      const icon  = document.getElementById(iconId);
      if (!input || !icon) return;
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
      }
    }

    // Backward compat untuk pemanggilan lama tanpa argumen
    function toggleIotTokenVisibility() {
      toggleTokenVisibility('iot_api_key', 'iot-token-eye-icon');
    }

    // ── Provider switcher ──
    function switchProvider(provider) {
      // Tampilkan/sembunyikan panel config
      document.getElementById('cfg-custom').classList.toggle('hidden', provider !== 'custom');
      document.getElementById('cfg-fonnte').classList.toggle('hidden', provider !== 'fonnte');

      // Update selected state pada card
      document.getElementById('card-custom').classList.toggle('selected', provider === 'custom');
      document.getElementById('card-fonnte').classList.toggle('selected', provider === 'fonnte');

      // Pastikan radio button ikut ter-check
      document.getElementById('prov_' + provider).checked = true;
    }

    // Init saat halaman load (jaga-jaga jika PHP render beda)
    (function() {
      const active = document.querySelector('input[name="wa_provider"]:checked');
      if (active) switchProvider(active.value);
    })();

    // ── Prune per device ──
    function updatePruneInfo(sel) {
      const opt   = sel.options[sel.selectedIndex];
      const info  = document.getElementById('prune-info');
      if (!opt.value) { info.classList.add('hidden'); return; }

      const cntInfus = parseInt(opt.dataset.cntInfus || 0).toLocaleString('id-ID');
      const cntNurse = parseInt(opt.dataset.cntNurse || 0).toLocaleString('id-ID');

      document.getElementById('prune-info-infus').textContent = cntInfus + ' baris';
      document.getElementById('prune-info-nurse').textContent = cntNurse + ' baris';
      info.classList.remove('hidden');
    }

    // ── Modal engine ──────────────────────────────────────
    function openModal({ icon, iconBg, iconColor, title, subtitle, body, buttons }) {
      document.getElementById('kiro-modal-icon').style.cssText      = `background:${iconBg};color:${iconColor};`;
      document.getElementById('kiro-modal-icon').innerHTML           = `<i class="bi bi-${icon}"></i>`;
      document.getElementById('kiro-modal-title').textContent        = title;
      document.getElementById('kiro-modal-subtitle').textContent     = subtitle || '';
      document.getElementById('kiro-modal-body').innerHTML           = body;

      const footer = document.getElementById('kiro-modal-footer');
      footer.innerHTML = '';
      buttons.forEach(btn => {
        const el = document.createElement('button');
        el.innerHTML   = btn.label;
        el.style.cssText = btn.style;
        el.className   = 'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold tracking-wide transition-all active:scale-95 cursor-pointer';
        el.onclick     = () => { closeModal(); btn.action && btn.action(); };
        footer.appendChild(el);
      });

      document.getElementById('kiro-modal-overlay').classList.add('open');
      document.addEventListener('keydown', _modalEscHandler);
    }

    function closeModal() {
      document.getElementById('kiro-modal-overlay').classList.remove('open');
      document.removeEventListener('keydown', _modalEscHandler);
    }

    function _modalEscHandler(e) { if (e.key === 'Escape') closeModal(); }

    // ── Ganti alert & confirm prune ───────────────────────
    let _pendingPruneForm = null;

    function confirmPrune(form) {
      const sel  = form.querySelector('#prune_device_id');
      const opt  = sel.options[sel.selectedIndex];
      const type = form.querySelector('input[name="prune_type"]:checked')?.value || 'all';

      if (!opt.value) {
        gModal({
          icon: 'exclamation-triangle-fill', iconBg: '#fffbeb', iconColor: '#d97706',
          title: 'Pilih Perangkat', sub: 'Perangkat belum dipilih',
          body: '<p>Silakan pilih perangkat dari dropdown terlebih dahulu sebelum melanjutkan.</p>',
          buttons: [{ label: '<i class="bi bi-check2"></i> Mengerti', style: 'background:#6b2072;color:#fff;', action: null }]
        });
        return false;
      }

      const typeLabel = {
        all:   'Semua data (sensor + nurse call)',
        infus: 'Data sensor infus saja',
        nurse: 'Log nurse call selesai saja',
      };
      const nama     = opt.dataset.nama   || opt.value;
      const pasien   = opt.dataset.pasien || '-';
      const cntInfus = parseInt(opt.dataset.cntInfus || 0).toLocaleString('id-ID');
      const cntNurse = parseInt(opt.dataset.cntNurse || 0).toLocaleString('id-ID');

      _pendingPruneForm = form;

      gModal({
        icon: 'trash3-fill', iconBg: '#fee2e2', iconColor: '#dc2626',
        title: 'Konfirmasi Hapus Data', sub: 'Tindakan ini tidak dapat dibatalkan',
        body: `
          <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;margin-bottom:12px;font-size:12px;line-height:1.7;">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#64748b;font-weight:600;">Perangkat</span><span style="font-weight:800;color:#0f172a;">${nama}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#64748b;font-weight:600;">Pasien</span><span style="font-weight:800;color:#0f172a;">${pasien}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#64748b;font-weight:600;">Tipe Data</span><span style="font-weight:800;color:#dc2626;">${typeLabel[type]}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="color:#64748b;font-weight:600;">Baris Sensor</span><span style="font-weight:700;color:#334155;">${cntInfus}</span></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:#64748b;font-weight:600;">Baris Nurse Log</span><span style="font-weight:700;color:#334155;">${cntNurse}</span></div>
          </div>
          <p style="font-size:12px;color:#9f1239;font-weight:600;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-exclamation-circle-fill"></i>
            Data yang dihapus <strong>tidak bisa dikembalikan</strong>.
          </p>`,
        buttons: [
          { label: 'Batal', style: 'background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;', action: () => { _pendingPruneForm = null; } },
          { label: '<i class="bi bi-trash3-fill"></i> Ya, Hapus', style: 'background:#dc2626;color:#fff;', action: () => { if (_pendingPruneForm) _pendingPruneForm.submit(); } },
        ]
      });

      return false;
    }
  </script>

</body>
</html>