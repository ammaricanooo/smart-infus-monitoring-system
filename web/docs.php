<?php
// =====================================================
// HALAMAN DOKUMENTASI SISTEM UNTUK PERAWAT (SUSTER)
// =====================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/auth.php';

requireAccess('docs');
$activePage = 'docs';

$_isLoggedIn  = isLoggedIn();
$_user        = getCurrentUser();
$_userRole    = $_user['role'] ?? '';
$_isSuperAdmin = ($_userRole === 'superadmin');

// ── DEFAULT DOKUMENTASI HTML ──────────────────────────
$defaultDocs = [
    'about' => '
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-5">
            <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center text-sm border border-blue-100">
              <i class="bi bi-info-circle-fill"></i>
            </div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">1. Tentang Smart Infus</h2>
          </div>
          
          <p class="text-slate-600 text-sm leading-relaxed">
            <strong>Smart Infus Monitoring System</strong> adalah platform pemantauan klinis terpusat yang dirancang untuk membantu tenaga medis (khususnya perawat) dalam mengawasi tetesan cairan infus pasien secara <em>real-time</em>.
          </p>
          <p class="text-slate-600 text-sm leading-relaxed mt-3">
            Sistem ini menggunakan modul sensor nirkabel (Wi-Fi) yang dipasang pada tiang infus pasien untuk mengirimkan data tetesan per menit (TPM), volume cairan sisa, serta status tombol panggilan darurat langsung ke stasiun perawat (Nurse Station) tanpa perlu mengecek ke kamar pasien secara konstan.
          </p>

          <!-- DESKRIPSI TOMBOL FISIK ALAT -->
          <div class="mt-6 border border-slate-100 rounded-xl p-4 bg-slate-50/50">
            <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-3 text-xs uppercase tracking-wider">
              <i class="bi bi-cpu text-[#6b2072]"></i>
              Tombol Fisik pada Perangkat Infus
            </h3>
            <p class="text-xs text-slate-600 mb-4 leading-relaxed">
              Pada perangkat keras (hardware) modul infus yang terpasang di tiang infus pasien, terdapat tiga tombol fisik utama yang dapat dioperasikan oleh perawat dan pasien:
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="flex items-start gap-3 bg-white p-3 rounded-lg border border-emerald-100 shadow-sm">
                <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 font-bold shadow-md shadow-emerald-500/20 text-xs">
                  M
                </div>
                <div>
                  <h4 class="font-bold text-slate-900 text-xs">Tombol Ganti Mode (Hijau)</h4>
                  <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                    Tombol berwarna <strong class="text-emerald-600">HIJAU</strong> digunakan oleh perawat untuk mengubah mode tetesan cairan infus (misalnya mode dewasa/anak atau laju aliran) sesuai kebutuhan klinis pasien.
                  </p>
                </div>
              </div>
              <div class="flex items-start gap-3 bg-white p-3 rounded-lg border border-red-100 shadow-sm">
                <div class="w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center flex-shrink-0 font-bold shadow-md shadow-red-500/20 text-xs animate-pulse">
                  N
                </div>
                <div>
                  <h4 class="font-bold text-slate-900 text-xs">Tombol Nurse Call (Merah)</h4>
                  <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                    Tombol berwarna <strong class="text-red-600">MERAH</strong> digunakan oleh pasien atau keluarga untuk memicu panggilan darurat ke stasiun perawat jika memerlukan bantuan klinis segera.
                  </p>
                </div>
              </div>
              <div class="flex items-start gap-3 bg-white p-3 rounded-lg border border-slate-300 shadow-sm">
                <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center flex-shrink-0 font-bold shadow-md shadow-slate-800/20 text-xs">
                  T
                </div>
                <div>
                  <h4 class="font-bold text-slate-900 text-xs">Tombol Tare (Hitam)</h4>
                  <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                    Tombol berwarna <strong class="text-slate-800">HITAM</strong> digunakan perawat untuk menera ulang (<em>tare</em>) sensor timbangan ke titik nol. Tekan setelah kantong infus baru dipasang agar pembacaan berat dimulai dari nol. Buzzer berbunyi 2× sebagai konfirmasi.
                  </p>
                </div>
              </div>
            </div>
          </div>
    ',
    'dashboard' => '
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-5">
            <div class="w-8 h-8 bg-indigo-50 text-indigo-600 rounded-lg flex items-center justify-center text-sm border border-indigo-100">
              <i class="bi bi-grid-1x2-fill"></i>
            </div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">2. Panduan Antarmuka Dashboard</h2>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
              <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full bg-[#6b2072]"></span>
                Stasiun Utama (Dashboard)
              </h3>
              <ul class="list-disc list-inside text-slate-600 space-y-2 text-xs leading-relaxed">
                <li><strong>Statistik Atas</strong>: Menampilkan jumlah alat aktif (Online), infus dalam kondisi kritis (sisa &le; 20%), dan panggilan darurat (Nurse Call) aktif.</li>
                <li><strong>Kartu Pasien (Device Card)</strong>: Representasi visual botol infus untuk tiap kasur. Warna cairan berubah dinamis sesuai sisa kapasitas infus.</li>
                <li><strong>Indikator Koneksi Live</strong>: Menunjukkan metode penerimaan data (SSE Realtime / Fallback Polling) untuk memastikan data selalu segar.</li>
              </ul>
            </div>

            <div class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
              <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full bg-[#6b2072]"></span>
                Halaman Rincian (Detail Page)
              </h3>
              <ul class="list-disc list-inside text-slate-600 space-y-2 text-xs leading-relaxed">
                <li><strong>Grafik Tren Analisis</strong>: Memvisualisasikan pergerakan laju tetes (TPM) dan sisa volume cairan selama beberapa waktu terakhir.</li>
                <li><strong>Estimasi Waktu Habis</strong>: Perhitungan matematis otomatis berapa lama sisa cairan infus dapat bertahan berdasarkan laju tetesan saat ini.</li>
                <li><strong>Ekspor Riwayat (CSV)</strong>: Tombol untuk mengunduh log transmisi aliran infus pasien dalam format file lembar kerja (spreadsheet).</li>
              </ul>
            </div>
          </div>
    ',
    'alarms' => '
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-5">
            <div class="w-8 h-8 bg-red-50 text-red-600 rounded-lg flex items-center justify-center text-sm border border-red-100">
              <i class="bi bi-bell-fill"></i>
            </div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">3. Jenis & Arti Alarm Sistem</h2>
          </div>

          <p class="text-slate-600 text-sm leading-relaxed mb-6">
            Sistem dilengkapi dengan alarm bunyi sirene klinis dan suara robot cerdas (TTS - Text-to-Speech) dalam Bahasa Indonesia. Berikut adalah kategori peringatan yang dapat berbunyi di komputer stasiun perawat atau ponsel:
          </p>

          <!-- Alarm Grid List -->
          <div class="flex flex-col gap-4">
            
            <!-- Nurse Call -->
            <div class="border border-red-100 rounded-xl p-4 bg-red-50/20 flex gap-4">
              <div class="w-10 h-10 bg-red-500 text-white rounded-lg flex items-center justify-center text-lg flex-shrink-0 animate-pulse">
                <i class="bi bi-bell-fill"></i>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-black text-red-600 uppercase tracking-wide">Panggilan Darurat</span>
                  <span class="text-[9px] font-black bg-red-600 text-white px-2 py-0.5 rounded-full">PRIORITAS TINGGI</span>
                </div>
                <h4 class="font-bold text-slate-900 text-sm mt-1">Panggilan Darurat Aktif (Nurse Call = 1)</h4>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                  <strong>Pemicu:</strong> Pasien menekan tombol darurat di kamar atau tiang infus.<br>
                  <strong>Indikator Visual:</strong> Bingkai kartu menyala merah cerah berkelip, botol berubah merah.<br>
                  <strong>Suara:</strong> Nada panggilan berbunyi terus menerus (loop) disusul suara: <em>"Perhatian. Pasien [Nama Pasien] di [Lokasi] sedang membutuhkan bantuan..."</em> secara berulang.
                </p>
              </div>
            </div>

            <!-- Volume Warning -->
            <div class="border border-amber-100 rounded-xl p-4 bg-amber-50/20 flex gap-4">
              <div class="w-10 h-10 bg-amber-500 text-white rounded-lg flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-droplet-half"></i>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-black text-amber-700 uppercase tracking-wide">Peringatan Volume</span>
                  <span class="text-[9px] font-black bg-amber-500 text-white px-2 py-0.5 rounded-full">PRIORITAS SEDANG</span>
                </div>
                <h4 class="font-bold text-slate-900 text-sm mt-1">Cairan Infus Hampir Habis (&le; 20%)</h4>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                  <strong>Pemicu:</strong> Sisa volume cairan riil menyentuh 20% ke bawah.<br>
                  <strong>Indikator Visual:</strong> Warna cairan di botol berubah menjadi merah berdenyut pelan, muncul lencana "HAMPIR HABIS" kuning.<br>
                  <strong>Suara:</strong> Notifikasi suara berbunyi satu kali: <em>"Perhatian. Cairan infus pasien [Nama Pasien] hampir habis. Sisa [X] mililiter. Segera ganti."</em> disertai pesan toast melayang di kanan atas.
                </p>
              </div>
            </div>

            <!-- TPM Macet -->
            <div class="border border-purple-100 rounded-xl p-4 bg-purple-50/20 flex gap-4">
              <div class="w-10 h-10 bg-purple-600 text-white rounded-lg flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-exclamation-triangle-fill"></i>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-black text-purple-700 uppercase tracking-wide">Aliran Terhenti</span>
                  <span class="text-[9px] font-black bg-purple-600 text-white px-2 py-0.5 rounded-full">PRIORITAS SEDANG</span>
                </div>
                <h4 class="font-bold text-slate-900 text-sm mt-1">Infus Macet / Tersumbat (0 TPM)</h4>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                  <strong>Pemicu:</strong> Laju tetesan terdeteksi 0 TPM selama lebih dari 10 detik, namun volume cairan masih tersisa.<br>
                  <strong>Indikator Visual:</strong> Muncul lencana ungu "MACET" di samping status perangkat.<br>
                  <strong>Suara:</strong> Notifikasi suara berbunyi satu kali: <em>"Perhatian. Pasien [Nama Pasien]... Infus macet. Sisa cairan [X] mililiter. Harap periksa segera."</em> dan toast melayang ungu.
                </p>
              </div>
            </div>

            <!-- TPM Cepat -->
            <div class="border border-amber-100 rounded-xl p-4 bg-amber-50/10 flex gap-4">
              <div class="w-10 h-10 bg-amber-600 text-white rounded-lg flex items-center justify-center text-lg flex-shrink-0">
                <i class="bi bi-speedometer2"></i>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-black text-amber-800 uppercase tracking-wide">Laju Berlebih</span>
                  <span class="text-[9px] font-black bg-amber-600 text-white px-2 py-0.5 rounded-full">PRIORITAS SEDANG</span>
                </div>
                <h4 class="font-bold text-slate-900 text-sm mt-1">Aliran Terlalu Cepat (&gt; 80 TPM)</h4>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed">
                  <strong>Pemicu:</strong> Laju aliran infus pasien terdeteksi melebihi 80 tetes per menit.<br>
                  <strong>Indikator Visual:</strong> Nilai angka TPM berubah oranye, muncul lencana kuning "CEPAT".<br>
                  <strong>Suara:</strong> Notifikasi suara berbunyi satu kali: <em>"Perhatian. Pasien [Nama Pasien]... Tetesan infus terlalu cepat, [X] tetes per menit. Harap periksa segera."</em> dan toast melayang oranye.
                </p>
              </div>
            </div>

          </div>
    ',
    'handling' => '
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-5">
            <div class="w-8 h-8 bg-emerald-50 text-emerald-600 rounded-lg flex items-center justify-center text-sm border border-emerald-100">
              <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">4. Alur Tindakan Respon Perawat</h2>
          </div>

          <div class="relative pl-6 border-l-2 border-[#6b2072]/20 flex flex-col gap-6 text-sm">
            
            <!-- Step 1 -->
            <div class="relative">
              <div class="absolute -left-[33px] top-0 w-4 h-4 rounded-full bg-[#6b2072] border-4 border-white"></div>
              <h4 class="font-bold text-slate-900">1. Identifikasi Pasien & Kamar</h4>
              <p class="text-xs text-slate-500 mt-1">Lihat nama pasien, nomor kasur/kamar, dan jenis alarm yang menyala di monitor utama Nurse Station.</p>
            </div>

            <!-- Step 2 -->
            <div class="relative">
              <div class="absolute -left-[33px] top-0 w-4 h-4 rounded-full bg-[#6b2072] border-4 border-white"></div>
              <h4 class="font-bold text-slate-900">2. Datangi Kamar Pasien</h4>
              <p class="text-xs text-slate-500 mt-1">Segera bergegas ke lokasi pasien. Jangan mengabaikan alarm berulang.</p>
            </div>

            <!-- Step 3 -->
            <div class="relative">
              <div class="absolute -left-[33px] top-0 w-4 h-4 rounded-full bg-[#6b2072] border-4 border-white"></div>
              <h4 class="font-bold text-slate-900">3. Lakukan Tindakan Klinis</h4>
              <ul class="list-disc list-inside text-xs text-slate-500 mt-1 space-y-1">
                <li>Jika <strong>Nurse Call</strong>: Tanyakan bantuan apa yang dibutuhkan pasien.</li>
                <li>Jika <strong>Volume Rendah</strong>: Siapkan botol infus baru dan segera ganti botol yang habis.</li>
                <li>Jika <strong>Macet (0 TPM)</strong>: Periksa apakah selang infus terlipat, ada sumbatan bekuan darah di ujung jarum, atau jarum bergeser keluar dari vena.</li>
                <li>Jika <strong>Terlalu Cepat (&gt;80 TPM)</strong>: Atur kembali roller clamp (klem gulung) pada selang infus ke laju tetesan yang sesuai instruksi dokter.</li>
              </ul>
            </div>

            <!-- Step 4 -->
            <div class="relative">
              <div class="absolute -left-[33px] top-0 w-4 h-4 rounded-full bg-[#6b2072] border-4 border-white"></div>
              <h4 class="font-bold text-slate-900">4. Reset Alarm Status</h4>
              <p class="text-xs text-slate-500 mt-1">Untuk panggilan Nurse Call, tekan tombol fisik Reset pada modul perangkat keras infus di dekat kasur pasien. Alarm pada web monitoring otomatis akan mati saat status kembali normal.</p>
            </div>

          </div>
    ',
    'maintenance' => '
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-5">
            <div class="w-8 h-8 bg-purple-50 text-[#6b2072] rounded-lg flex items-center justify-center text-sm border border-purple-100">
              <i class="bi bi-database-fill-gear"></i>
            </div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">5. Pemeliharaan & Retensi Database</h2>
          </div>
          
          <p class="text-slate-600 text-sm leading-relaxed">
            Untuk menjaga kestabilan dan performa stasiun monitoring infus dalam jangka panjang di lingkungan produksi, sistem ini dilengkapi dengan sistem pemeliharaan database otomatis dan manual.
          </p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm mt-5">
            <div class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
              <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full bg-[#6b2072]"></span>
                Retensi Data Otomatis
              </h3>
              <p class="text-slate-600 text-xs leading-relaxed">
                Sistem secara berkala menghapus baris data infus lama dan log panggilan perawat yang sudah terselesaikan (*resolved*) di latar belakang (berdasarkan jumlah hari retensi yang Anda tentukan di menu Settings). Pembersihan data otomatis ini berjalan secara probabilistik (peluang 1% setiap kali ada request data masuk dari modul infus).
              </p>
            </div>

            <div class="border border-slate-100 rounded-xl p-4 bg-slate-50/50">
              <h3 class="font-bold text-slate-800 flex items-center gap-2 mb-2">
                <span class="w-2 h-2 rounded-full bg-[#6b2072]"></span>
                Optimasi Manual (Optimize Now)
              </h3>
              <p class="text-slate-600 text-xs leading-relaxed">
                Tombol **Optimalkan Database Sekarang** di halaman Settings memungkinkan admin untuk membersihkan data lama secara instan dan memicu rekonstruksi indeks fisik database MySQL (<span class="font-mono">OPTIMIZE TABLE</span>). Hal ini berguna untuk memulihkan kapasitas penyimpanan ruang disk yang kosong.
              </p>
            </div>
          </div>
    ',
    'faq' => '
          <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-5">
            <div class="w-8 h-8 bg-purple-50 text-purple-600 rounded-lg flex items-center justify-center text-sm border border-purple-100">
              <i class="bi bi-question-circle-fill"></i>
            </div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">6. Tanya Jawab (FAQ) & Troubleshooting</h2>
          </div>

          <div class="flex flex-col gap-4 text-xs">
            
            <div class="border-b border-slate-100 pb-4">
              <h4 class="font-bold text-slate-900 flex items-center gap-1.5"><i class="bi bi-patch-question text-purple-600"></i> Mengapa suara alarm di monitor tidak berbunyi padahal ada indikator merah berkedip?</h4>
              <p class="text-slate-500 mt-2 leading-relaxed">
                Kebijakan keamanan peramban web modern (Google Chrome, Microsoft Edge, Safari) melarang halaman web memutar suara secara otomatis sebelum ada interaksi pengguna. <br>
                <strong>Solusi:</strong> Cukup klik atau sentuh area mana saja di halaman web monitor saat pertama kali dibuka untuk mengaktifkan akses audio peramban web.
              </p>
            </div>

            <div class="border-b border-slate-100 pb-4">
              <h4 class="font-bold text-slate-900 flex items-center gap-1.5"><i class="bi bi-patch-question text-purple-600"></i> Bisakah saya mematikan alarm suara panggilan perawat lewat komputer stasiun perawat?</h4>
              <p class="text-slate-500 mt-2 leading-relaxed">
                Tidak bisa. Alarm darurat panggilan perawat (Nurse Call) sengaja dirancang <strong>hanya dapat di-reset lewat tombol fisik pada alat infus di kamar pasien</strong>. <br>
                Hal ini adalah standar keselamatan pasien internasional (patient safety) untuk memastikan perawat benar-benar datang mendatangi kamar pasien untuk memberikan bantuan langsung, bukan sekadar mematikannya dari jauh.
              </p>
            </div>

            <div class="border-b border-slate-100 pb-4">
              <h4 class="font-bold text-slate-900 flex items-center gap-1.5"><i class="bi bi-patch-question text-purple-600"></i> Apa arti status "OFFLINE" abu-abu pada perangkat?</h4>
              <p class="text-slate-500 mt-2 leading-relaxed">
                Status <strong>OFFLINE</strong> berarti stasiun pemantau tidak menerima data baru dari alat infus selama 15 detik terakhir. <br>
                <strong>Kemungkinan Penyebab:</strong> Catu daya alat infus mati (baterai habis / kabel lepas), alat dimatikan oleh pasien/keluarga pasien, atau alat kehilangan sinyal jaringan Wi-Fi rumah sakit.
              </p>
            </div>

            <div class="pb-2">
              <h4 class="font-bold text-slate-900 flex items-center gap-1.5"><i class="bi bi-patch-question text-purple-600"></i> Bagaimana cara memperbarui data infus jika status koneksi tertunda?</h4>
              <p class="text-slate-500 mt-2 leading-relaxed">
                Sistem secara otomatis akan melakukan pembaruan secara <em>real-time</em> menggunakan koneksi SSE. Namun jika terjadi gangguan jaringan, sistem akan otomatis beralih ke metode <em>fallback polling</em> setiap 3 detik. Anda juga dapat menekan tombol <strong>REFRESH</strong> di navbar atau kartu untuk memicu pembaruan manual secara instan.
              </p>
            </div>

          </div>
    '
];

// ── SAVE ACTION (POST) ───────────────────────────────
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save_docs') {
    if ($_isSuperAdmin) {
        $keys = [
            'about'       => 'docs_sec_about',
            'dashboard'   => 'docs_sec_dashboard',
            'alarms'      => 'docs_sec_alarms',
            'handling'    => 'docs_sec_handling',
            'maintenance' => 'docs_sec_maintenance',
            'faq'         => 'docs_sec_faq'
        ];
        foreach ($keys as $arrKey => $dbKey) {
            if (isset($_POST[$dbKey])) {
                setSetting($dbKey, trim($_POST[$dbKey]));
            }
        }
        header('Location: docs.php?saved=1');
        exit;
    } else {
        header('Location: docs.php?error=unauthorized');
        exit;
    }
}

if (isset($_GET['saved'])) {
    $successMsg = 'Perubahan dokumentasi berhasil disimpan ke database!';
}

// ── LOAD FINAL CONTENT FROM DB/FALLBACK ────────────────
$secAbout       = getSetting('docs_sec_about', '');
$secDashboard   = getSetting('docs_sec_dashboard', '');
$secAlarms      = getSetting('docs_sec_alarms', '');
$secHandling    = getSetting('docs_sec_handling', '');
$secMaintenance = getSetting('docs_sec_maintenance', '');
$secFaq         = getSetting('docs_sec_faq', '');

if (empty($secAbout))       $secAbout       = $defaultDocs['about'];
if (empty($secDashboard))   $secDashboard   = $defaultDocs['dashboard'];
if (empty($secAlarms))      $secAlarms      = $defaultDocs['alarms'];
if (empty($secHandling))    $secHandling    = $defaultDocs['handling'];
if (empty($secMaintenance)) $secMaintenance = $defaultDocs['maintenance'];
if (empty($secFaq))         $secFaq         = $defaultDocs['faq'];

$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1' && $_isSuperAdmin;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dokumentasi Perawat — Smart Infus</title>
  
  <!-- Local Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/style.css" />
  
  <!-- Typography & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-[#6b2072]/10 selection:text-[#6b2072] font-sans antialiased">

  <?php require __DIR__ . '/config/navbar.php'; ?>

  <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1 relative">
    
    <!-- Toast Alert Success -->
    <?php if ($successMsg): ?>
      <div id="save-toast-banner" class="mb-6 flex items-center justify-between p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-bold shadow-sm transition-all duration-300">
        <div class="flex items-center gap-2.5">
          <div class="w-6 h-6 rounded-lg bg-emerald-500 text-white flex items-center justify-center text-xs shadow-sm shadow-emerald-500/10">
            <i class="bi bi-check2-circle text-sm"></i>
          </div>
          <span><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="document.getElementById('save-toast-banner').remove()" class="text-emerald-400 hover:text-emerald-600 transition-colors">
          <i class="bi bi-x-lg text-sm"></i>
        </button>
      </div>
      <script>
        setTimeout(() => {
          const banner = document.getElementById('save-toast-banner');
          if (banner) {
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(-10px)';
            setTimeout(() => banner.remove(), 300);
          }
        }, 8000);
      </script>
    <?php endif; ?>

    <!-- Hero / Title Header -->
    <div class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm mb-8 relative overflow-hidden">
      <div class="absolute -right-16 -top-16 w-40 h-40 bg-[#6b2072]/5 rounded-full blur-2xl"></div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative z-10">
        <div>
          <span class="text-[10px] font-black text-[#6b2072] uppercase tracking-widest bg-[#6b2072]/5 border border-[#6b2072]/10 px-3 py-1 rounded-full">Panduan Klinis</span>
          <h1 class="text-3xl font-black text-slate-900 tracking-tight mt-3">Dokumentasi & Panduan Perawat</h1>
          <p class="text-slate-500 text-sm mt-1">Panduan lengkap pengoperasian dan penanganan alarm pada Sistem Monitoring Infus Pintar (Smart Infus).</p>
        </div>
        <div class="flex items-center gap-3">
          <?php if ($_isSuperAdmin && !$isEditMode): ?>
            <a href="docs.php?edit=1" class="px-4 py-2.5 bg-[#6b2072] hover:bg-[#541859] text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-[#6b2072]/15 flex items-center gap-1.5">
              <i class="bi bi-pencil-square"></i> Edit Dokumentasi
            </a>
          <?php endif; ?>
          <div class="w-12 h-12 bg-purple-50 text-[#6b2072] rounded-xl flex items-center justify-center text-xl border border-purple-100 shadow-sm flex-shrink-0">
            <i class="bi bi-journal-medical"></i>
          </div>
        </div>
      </div>
    </div>

    <?php if ($isEditMode): ?>
      <!-- ═══════════════════════════════════════════════════
           EDIT MODE PANEL (SUPERADMIN ONLY)
           ═══════════════════════════════════════════════════ -->
      <div class="bg-white border border-slate-200/60 rounded-2xl p-6 shadow-sm mb-8 relative">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
          <div>
            <h2 class="text-lg font-black text-slate-900 tracking-tight uppercase">Mode Editor Dokumentasi</h2>
            <p class="text-xs text-slate-500 mt-1">Ubah atau sesuaikan konten setiap halaman panduan menggunakan tag HTML & kelas Tailwind CSS.</p>
          </div>
          <a href="docs.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all border border-slate-200 flex items-center gap-1.5">
            <i class="bi bi-arrow-left-short text-base"></i> Batal / Kembali
          </a>
        </div>

        <form method="POST" action="docs.php?action=save_docs" class="grid grid-cols-1 lg:grid-cols-4 gap-8">
          <!-- Left Column Tabs -->
          <div class="lg:col-span-1 flex flex-col gap-1.5" id="editor-tabs">
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-3 mb-1">Daftar Bagian</div>
            
            <button type="button" onclick="switchEditorTab('about')" id="tab-btn-about" class="editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left bg-purple-50 text-[#6b2072] border border-purple-100">
              <span class="truncate">1. Tentang Sistem</span>
              <i class="bi bi-chevron-right text-xs"></i>
            </button>
            <button type="button" onclick="switchEditorTab('dashboard')" id="tab-btn-dashboard" class="editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left text-slate-600 hover:bg-slate-50">
              <span class="truncate">2. Panduan Dashboard</span>
              <i class="bi bi-chevron-right text-xs"></i>
            </button>
            <button type="button" onclick="switchEditorTab('alarms')" id="tab-btn-alarms" class="editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left text-slate-600 hover:bg-slate-50">
              <span class="truncate">3. Jenis & Arti Alarm</span>
              <i class="bi bi-chevron-right text-xs"></i>
            </button>
            <button type="button" onclick="switchEditorTab('handling')" id="tab-btn-handling" class="editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left text-slate-600 hover:bg-slate-50">
              <span class="truncate">4. Respon Klinis Alarm</span>
              <i class="bi bi-chevron-right text-xs"></i>
            </button>
            <button type="button" onclick="switchEditorTab('maintenance')" id="tab-btn-maintenance" class="editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left text-slate-600 hover:bg-slate-50">
              <span class="truncate">5. Pemeliharaan DB</span>
              <i class="bi bi-chevron-right text-xs"></i>
            </button>
            <button type="button" onclick="switchEditorTab('faq')" id="tab-btn-faq" class="editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left text-slate-600 hover:bg-slate-50">
              <span class="truncate">6. Tanya Jawab (FAQ)</span>
              <i class="bi bi-chevron-right text-xs"></i>
            </button>

            <div class="mt-6 p-4 bg-slate-50 border border-slate-100 rounded-xl text-[11px] text-slate-500 leading-relaxed">
              <span class="font-bold text-slate-800 block mb-1">💡 Tips Pemulihan:</span>
              Kosongkan kotak teks pada bagian tertentu lalu klik simpan untuk memulihkan ke konten default bawaan Smart Infus.
            </div>
          </div>

          <!-- Right Column Fields -->
          <div class="lg:col-span-3 flex flex-col gap-6">
            
            <!-- Helper Editor Tab View -->
            <?php
            function _editorField(string $key, string $label, string $currentValue) {
                // Determine visibility class
                $vis = ($key === 'about') ? '' : 'hidden';
                return "
                <div id=\"tab-content-{$key}\" class=\"editor-tab-content {$vis} flex flex-col gap-4\">
                  <div>
                    <h3 class=\"text-sm font-black text-slate-900 tracking-wide\">Konten HTML: {$label}</h3>
                    <p class=\"text-[11px] text-slate-400 mt-0.5\">Format HTML & kelas CSS diperbolehkan. Gunakan visualisasi yang konsisten.</p>
                  </div>
                  <textarea
                    id=\"textarea-{$key}\"
                    name=\"docs_sec_{$key}\"
                    class=\"font-mono text-xs w-full h-[320px] p-4 bg-slate-900 text-slate-100 rounded-xl outline-none focus:ring-4 focus:ring-purple-900/15 focus:border-[#6b2072] border border-slate-700 transition-all\"
                    placeholder=\"Kosongkan untuk menggunakan konten default bawaan...\"
                  >" . htmlspecialchars($currentValue) . "</textarea>
                  
                  <!-- LIVE PREVIEW CONTAINER -->
                  <div class=\"border-t border-slate-100 pt-4 mt-2\">
                    <h4 class=\"text-xs font-bold text-slate-400 mb-2 uppercase tracking-widest flex items-center gap-1.5\">
                      <i class=\"bi bi-eye-fill text-[#6b2072]\"></i> Pratinjau Tampilan (Live Preview)
                    </h4>
                    <div id=\"preview-{$key}\" class=\"bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm overflow-hidden text-slate-800\">
                      {$currentValue}
                    </div>
                  </div>
                </div>
                ";
            }
            ?>

            <?= _editorField('about',       '1. Tentang Sistem',        $secAbout) ?>
            <?= _editorField('dashboard',   '2. Panduan Dashboard',    $secDashboard) ?>
            <?= _editorField('alarms',      '3. Jenis & Arti Alarm',    $secAlarms) ?>
            <?= _editorField('handling',    '4. Respon Klinis Alarm',   $secHandling) ?>
            <?= _editorField('maintenance', '5. Pemeliharaan DB',       $secMaintenance) ?>
            <?= _editorField('faq',         '6. Tanya Jawab (FAQ)',     $secFaq) ?>

            <div class="flex items-center gap-3 border-t border-slate-100 pt-6">
              <button type="submit" class="px-5 py-2.5 bg-[#6b2072] hover:bg-[#541859] text-white rounded-xl text-xs font-bold shadow-md shadow-[#6b2072]/15 transition-all">
                <i class="bi bi-check-circle mr-1"></i> SIMPAN PERUBAHAN
              </button>
              <a href="docs.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all border border-slate-200">
                BATAL
              </a>
            </div>

          </div>
        </form>
      </div>

      <script>
        function switchEditorTab(tabId) {
          // Hide all contents
          document.querySelectorAll('.editor-tab-content').forEach(el => el.classList.add('hidden'));
          // Show active content
          document.getElementById('tab-content-' + tabId).classList.remove('hidden');

          // Reset all tab button styles
          document.querySelectorAll('.editor-tab-btn').forEach(btn => {
            btn.className = 'editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left text-slate-600 hover:bg-slate-50';
          });
          // Highlight active button
          const activeBtn = document.getElementById('tab-btn-' + tabId);
          activeBtn.className = 'editor-tab-btn flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold transition-all text-left bg-purple-50 text-[#6b2072] border border-purple-100';
        }

        // Live Preview updates
        const docKeys = ['about', 'dashboard', 'alarms', 'handling', 'maintenance', 'faq'];
        docKeys.forEach(key => {
          const textarea = document.getElementById('textarea-' + key);
          const preview = document.getElementById('preview-' + key);
          textarea.addEventListener('input', () => {
            preview.innerHTML = textarea.value;
          });
        });
      </script>

    <?php else: ?>
      <!-- ═══════════════════════════════════════════════════
           PUBLIC DOCUMENTATION VIEW
           ═══════════════════════════════════════════════════ -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

        <!-- Main Documentation Content -->
        <div class="lg:col-span-3 flex flex-col gap-8">
          
          <!-- Section 1: About -->
          <section id="about" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
            <?= $secAbout ?>
          </section>

          <!-- Section 2: Dashboard -->
          <section id="dashboard" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
            <?= $secDashboard ?>
          </section>

          <!-- Section 3: Alarms -->
          <section id="alarms" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
            <?= $secAlarms ?>
          </section>

          <!-- Section 4: Response -->
          <section id="handling" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
            <?= $secHandling ?>
          </section>

          <!-- Section 5: Database Maintenance -->
          <section id="db-maintenance" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
            <?= $secMaintenance ?>
          </section>

          <!-- Section 6: FAQ -->
          <section id="faq" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm mb-6">
            <?= $secFaq ?>
          </section>

        </div>

        <!-- Side Quick Links Menu (Sticky) — kanan -->
        <div class="lg:col-span-1 order-first lg:order-last">
          <div class="bg-white border border-slate-200/60 rounded-2xl p-4 shadow-sm lg:sticky lg:top-24 flex flex-col gap-1">
            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-3 py-2">Daftar Isi</div>
            
            <a href="#about" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-[#6b2072] transition-all">
              <i class="bi bi-info-circle text-sm text-slate-400"></i>
              <span>1. Tentang Sistem</span>
            </a>
            
            <a href="#dashboard" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-[#6b2072] transition-all">
              <i class="bi bi-grid-1x2 text-sm text-slate-400"></i>
              <span>2. Panduan Dashboard</span>
            </a>
            
            <a href="#alarms" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-[#6b2072] transition-all">
              <i class="bi bi-bell text-sm text-slate-400"></i>
              <span>3. Jenis & Arti Alarm</span>
            </a>

            <a href="#handling" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-[#6b2072] transition-all">
              <i class="bi bi-heart-pulse text-sm text-slate-400"></i>
              <span>4. Respon Klinis Alarm</span>
            </a>

            <a href="#db-maintenance" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-[#6b2072] transition-all">
              <i class="bi bi-database text-sm text-slate-400"></i>
              <span>5. Pemeliharaan Database</span>
            </a>
            
            <a href="#faq" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 hover:text-[#6b2072] transition-all">
              <i class="bi bi-question-circle text-sm text-slate-400"></i>
              <span>6. Tanya Jawab (FAQ)</span>
            </a>
          </div>
        </div>

      </div>
    <?php endif; ?>

  </main>

  <!-- MEDICAL WORKSTATION FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Smart Infus Monitoring System &bull; Clinical Station Workspace</p>
  </footer>

</body>
</html>
