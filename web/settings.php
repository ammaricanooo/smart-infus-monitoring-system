<?php
// =====================================================
// HALAMAN SETTINGS — KONFIGURASI SISTEM
// =====================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/whatsapp.php';

$message = '';
$msgType = 'success';
$testResult = null;

// ===== PROSES FORM SAVE =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $fields = [
            'fonnte_token',
            'wa_nurse_call_msg',
            'wa_low_volume_msg',
        ];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                setSetting($f, trim($_POST[$f]));
            }
        }
        $message = 'Pengaturan berhasil disimpan!';
    }

    // ===== TEST KIRIM WA =====
    elseif ($action === 'test_wa') {
        $testTarget = trim($_POST['test_target'] ?? '');
        if (empty($testTarget)) {
            $message = 'Masukkan nomor tujuan untuk test!';
            $msgType = 'danger';
        } else {
            $testMsg = "✅ *TEST NOTIFIKASI*\nSmart Infus Monitoring System\nWaktu: " . date('d/m/Y H:i:s') . "\n\nKonfigurasi WhatsApp berhasil!";
            $result  = sendWhatsApp($testTarget, $testMsg);
            if ($result['success']) {
                $message    = "Pesan test berhasil dikirim ke $testTarget!";
                $testResult = $result;
            } else {
                $message    = 'Gagal kirim: ' . ($result['error'] ?? json_encode($result['response'] ?? ''));
                $msgType    = 'danger';
                $testResult = $result;
            }
        }
    }
}

$settings = getAllSettings();
$token    = $settings['fonnte_token']      ?? '';
$msgNC    = $settings['wa_nurse_call_msg'] ?? '';
$msgLV    = $settings['wa_low_volume_msg'] ?? '';
$activePage = 'settings';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Settings — Smart Infus</title>
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
    .si-input-mono { font-family: 'Courier New', monospace; }
    .si-label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .08em; display: block; margin-bottom: 6px; }

    /* BUTTONS */
    .si-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 10px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; transition: all .15s; text-decoration: none; font-family: inherit; }
    .si-btn-primary { background: #2563eb; color: white; }
    .si-btn-primary:hover { background: #1d4ed8; box-shadow: 0 0 16px rgba(37,99,235,0.4); }
    .si-btn-success { background: #059669; color: white; }
    .si-btn-success:hover { background: #047857; }
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

  <div style="max-width:800px;margin:0 auto;padding:32px 24px;display:flex;flex-direction:column;gap:24px">

    <!-- ALERT -->
    <?php if ($message): ?>
    <div style="padding:14px 18px;border-radius:12px;display:flex;align-items:center;gap:10px;<?= $msgType === 'success' ? 'background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);color:#10b981' : 'background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.25);color:#ef4444' ?>">
      <i class="bi bi-<?= $msgType === 'success' ? 'check2-circle' : 'exclamation-circle' ?>" style="font-size:16px;flex-shrink:0"></i>
      <span style="font-size:13px;font-weight:700"><?= htmlspecialchars($message) ?></span>
    </div>
    <?php endif; ?>

    <!-- FONNTE CONFIG CARD -->
    <form method="POST" action="settings.php">
      <input type="hidden" name="action" value="save_settings" />

      <div class="si-card" style="overflow:hidden">
        <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:12px">
          <div style="width:36px;height:36px;background:rgba(37,211,102,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
            <i class="bi bi-whatsapp" style="color:#25d366;font-size:16px"></i>
          </div>
          <div>
            <div style="font-size:14px;font-weight:800;color:#f1f5f9">Konfigurasi WhatsApp (Fonnte)</div>
            <div style="font-size:11px;color:#475569;margin-top:2px">
              Dapatkan token di <a href="https://fonnte.com" target="_blank" style="color:#25d366;font-weight:700;text-decoration:none">fonnte.com</a> → Dashboard → Device → Token
            </div>
          </div>
        </div>

        <div style="padding:24px;display:flex;flex-direction:column;gap:20px">

          <!-- Token -->
          <div>
            <label class="si-label"><i class="bi bi-key-fill" style="margin-right:4px"></i>Fonnte API Token</label>
            <div style="position:relative">
              <input type="password" name="fonnte_token" id="fonnte_token"
                     value="<?= htmlspecialchars($token) ?>"
                     placeholder="Masukkan token Fonnte kamu..."
                     class="si-input si-input-mono"
                     style="padding-right:44px" />
              <button type="button" onclick="toggleToken()"
                      style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#475569;cursor:pointer;padding:4px;transition:color .15s"
                      onmouseover="this.style.color='#94a3b8'" onmouseout="this.style.color='#475569'">
                <i class="bi bi-eye" id="token-eye"></i>
              </button>
            </div>
            <div style="font-size:10px;color:#475569;margin-top:5px">
              <i class="bi bi-info-circle" style="margin-right:3px"></i>Token bersifat rahasia. Jangan bagikan ke orang lain.
            </div>
          </div>

          <!-- Status token -->
          <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;<?= !empty($token) ? 'background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2)' : 'background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.2)' ?>">
            <i class="bi bi-<?= !empty($token) ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>" style="font-size:16px;color:<?= !empty($token) ? '#10b981' : '#f59e0b' ?>;flex-shrink:0"></i>
            <div>
              <div style="font-size:12px;font-weight:700;color:<?= !empty($token) ? '#10b981' : '#f59e0b' ?>">
                <?= !empty($token) ? 'Token sudah dikonfigurasi' : 'Token belum dikonfigurasi' ?>
              </div>
              <div style="font-size:10px;color:<?= !empty($token) ? '#059669' : '#d97706' ?>;margin-top:2px">
                <?= !empty($token) ? 'Notifikasi WhatsApp siap digunakan' : 'Isi token untuk mengaktifkan notifikasi WhatsApp' ?>
              </div>
            </div>
          </div>

          <!-- Template Nurse Call -->
          <div>
            <label class="si-label">
              <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#ef4444;margin-right:6px;vertical-align:middle"></span>
              Template Pesan — Nurse Call
            </label>
            <textarea name="wa_nurse_call_msg" rows="5"
                      class="si-input si-input-mono"
                      placeholder="Template pesan nurse call..."><?= htmlspecialchars($msgNC) ?></textarea>
            <div style="font-size:10px;color:#475569;margin-top:5px">
              Placeholder:
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{pasien}</code>
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{lokasi}</code>
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{waktu}</code>
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{device}</code>
            </div>
          </div>

          <!-- Template Low Volume -->
          <div>
            <label class="si-label">
              <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#f59e0b;margin-right:6px;vertical-align:middle"></span>
              Template Pesan — Volume Kritis (≤ 10 ml)
            </label>
            <textarea name="wa_low_volume_msg" rows="6"
                      class="si-input si-input-mono"
                      placeholder="Template pesan volume kritis..."><?= htmlspecialchars($msgLV) ?></textarea>
            <div style="font-size:10px;color:#475569;margin-top:5px">
              Placeholder:
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{pasien}</code>
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{lokasi}</code>
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{volume}</code>
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{persen}</code>
              <code style="background:#21253a;padding:1px 5px;border-radius:4px;font-size:10px">{waktu}</code>
            </div>
          </div>

          <!-- Simpan -->
          <button type="submit" class="si-btn si-btn-primary" style="width:100%;justify-content:center;padding:12px 20px;font-size:13px">
            <i class="bi bi-save2-fill"></i> SIMPAN PENGATURAN
          </button>

        </div>
      </div>
    </form>

    <!-- TEST WA CARD -->
    <div class="si-card" style="overflow:hidden">
      <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:12px">
        <div style="width:36px;height:36px;background:rgba(59,130,246,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center">
          <i class="bi bi-send-check" style="color:#3b82f6;font-size:16px"></i>
        </div>
        <div>
          <div style="font-size:14px;font-weight:800;color:#f1f5f9">Test Kirim WhatsApp</div>
          <div style="font-size:11px;color:#475569;margin-top:2px">Kirim pesan test untuk memverifikasi konfigurasi token</div>
        </div>
      </div>

      <form method="POST" action="settings.php" style="padding:24px">
        <input type="hidden" name="action" value="test_wa" />
        <div style="display:flex;gap:10px">
          <div style="flex:1;position:relative">
            <i class="bi bi-phone" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#475569;font-size:13px"></i>
            <input type="text" name="test_target"
                   placeholder="628123456789 (format internasional)"
                   class="si-input"
                   style="padding-left:36px" />
          </div>
          <button type="submit" class="si-btn si-btn-success" style="white-space:nowrap">
            <i class="bi bi-whatsapp"></i> KIRIM TEST
          </button>
        </div>
        <div style="font-size:10px;color:#475569;margin-top:6px">
          <i class="bi bi-info-circle" style="margin-right:3px"></i>
          Gunakan format internasional tanpa tanda + (contoh: 628123456789)
        </div>

        <?php if ($testResult !== null): ?>
        <div style="margin-top:16px;padding:14px;background:#21253a;border-radius:10px;border:1px solid rgba(255,255,255,0.06)">
          <div style="font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px">Response API:</div>
          <pre style="font-size:11px;color:#94a3b8;font-family:'Courier New',monospace;overflow-x:auto;white-space:pre-wrap"><?= htmlspecialchars(json_encode($testResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
        <?php endif; ?>
      </form>
    </div>

    <!-- PANDUAN CARD -->
    <div class="si-card" style="overflow:hidden">
      <div style="padding:20px 24px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:10px">
        <i class="bi bi-lightbulb-fill" style="color:#f59e0b;font-size:18px"></i>
        <div style="font-size:14px;font-weight:800;color:#f1f5f9">Panduan Penggunaan</div>
      </div>
      <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:24px">
        <div>
          <div style="font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px">Cara Mendapatkan Token Fonnte</div>
          <ol style="list-style:none;display:flex;flex-direction:column;gap:8px">
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="color:#3b82f6;font-weight:700;flex-shrink:0">1.</span> Daftar di <span style="color:#3b82f6;font-weight:600">fonnte.com</span>
            </li>
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="color:#3b82f6;font-weight:700;flex-shrink:0">2.</span> Tambahkan device WhatsApp kamu
            </li>
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="color:#3b82f6;font-weight:700;flex-shrink:0">3.</span> Scan QR code untuk menghubungkan WA
            </li>
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="color:#3b82f6;font-weight:700;flex-shrink:0">4.</span> Salin token dari halaman Device
            </li>
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="color:#3b82f6;font-weight:700;flex-shrink:0">5.</span> Tempel token di kolom di atas
            </li>
          </ol>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.1em;margin-bottom:12px">Kapan Notifikasi Dikirim?</div>
          <ul style="list-style:none;display:flex;flex-direction:column;gap:8px">
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="flex-shrink:0">🚨</span> Saat tombol Nurse Call ditekan pasien
            </li>
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="flex-shrink:0">⚠️</span> Saat volume infus ≤ 10 ml (sekali per sesi)
            </li>
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="flex-shrink:0">📱</span> Dikirim ke nomor suster &amp; keluarga pasien
            </li>
            <li style="display:flex;gap:8px;font-size:12px;color:#64748b">
              <span style="flex-shrink:0">🔄</span> Alert volume reset otomatis saat infus diganti
            </li>
          </ul>
        </div>
      </div>
    </div>

  </div><!-- /container -->

  <footer style="padding:32px 24px;text-align:center">
    <p style="font-size:10px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:.2em">&copy; <?= date('Y') ?> Smart Infus Monitoring System</p>
  </footer>

  <script>
    function toggleToken() {
      const inp = document.getElementById('fonnte_token');
      const eye = document.getElementById('token-eye');
      if (inp.type === 'password') {
        inp.type = 'text';
        eye.className = 'bi bi-eye-slash';
      } else {
        inp.type = 'password';
        eye.className = 'bi bi-eye';
      }
    }
  </script>
</body>
</html>
