<?php
// =====================================================
// HALAMAN DOKUMENTASI SISTEM UNTUK PERAWAT (SUSTER)
// =====================================================

$activePage = 'docs';
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
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-[#6b2072]/10 selection:text-[#6b2072] pb-16 md:pb-0 font-sans antialiased">

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

      <!-- Realtime Clock -->
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

  <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 flex-1 relative">
    
    <!-- Hero / Title Header -->
    <div class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm mb-8 relative overflow-hidden">
      <div class="absolute -right-16 -top-16 w-40 h-40 bg-[#6b2072]/5 rounded-full blur-2xl"></div>
      <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative z-10">
        <div>
          <span class="text-[10px] font-black text-[#6b2072] uppercase tracking-widest bg-[#6b2072]/5 border border-[#6b2072]/10 px-3 py-1 rounded-full">Panduan Klinis</span>
          <h1 class="text-3xl font-black text-slate-900 tracking-tight mt-3">Dokumentasi & Panduan Perawat</h1>
          <p class="text-slate-500 text-sm mt-1">Panduan lengkap pengoperasian dan penanganan alarm pada Sistem Monitoring Infus Pintar (Smart Infus).</p>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-12 h-12 bg-purple-50 text-[#6b2072] rounded-xl flex items-center justify-center text-xl border border-purple-100 shadow-sm">
            <i class="bi bi-journal-medical"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
      
      <!-- Side Quick Links Menu (Sticky) -->
      <div class="lg:sticky top-24 lg:col-span-1">
        <div class="bg-white border border-slate-200/60 rounded-2xl p-4 shadow-sm sticky top-24 flex flex-col gap-1">
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

      <!-- Main Documentation Content -->
      <div class="lg:col-span-3 flex flex-col gap-8">
        
        <!-- Section 1: About -->
        <section id="about" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
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
        </section>

        <!-- Section 2: Dashboard -->
        <section id="dashboard" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
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
        </section>

        <!-- Section 3: Alarms -->
        <section id="alarms" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
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
        </section>

        <!-- Section 4: Response -->
        <section id="handling" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
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
        </section>

        <!-- Section 5: Database Maintenance -->
        <section id="db-maintenance" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm">
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
        </section>

        <!-- Section 6: FAQ -->
        <section id="faq" class="bg-white border border-slate-200/60 rounded-2xl p-6 sm:p-8 shadow-sm mb-6">
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
        </section>

      </div>

    </div>

  </main>

  <!-- MEDICAL WORKSTATION FOOTER -->
  <footer class="bg-white border-t border-slate-200 py-6 mt-12 text-center">
    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">&copy; <?= date('Y') ?> Smart Infus Monitoring System &bull; Clinical Station Workspace</p>
  </footer>

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
