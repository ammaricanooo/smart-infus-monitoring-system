# TETES CERDAS: Sistem Pemantauan Infus Berbasis IoT untuk Keselamatan Pasien dan Efisiensi Tenaga Medis di Rumah Sakit

**Diajukan untuk Bogor Innovation Award**
**Tema: Kesehatan & Obat-obatan**

---

## LEMBAR IDENTITAS INOVASI

| | |
|---|---|
| **Nama Inovasi** | TETES CERDAS — Smart Infusion Monitoring System |
| **Kategori** | Kesehatan & Obat-obatan |
| **Jenis Inovasi** | Teknologi Tepat Guna berbasis IoT |
| **Tahun Pengembangan** | 2025 |
| **Status** | Prototipe Fungsional (Siap Uji Klinis) |

---

## BAB I — PENDAHULUAN

### 1.1 Latar Belakang

Cairan infus adalah salah satu tindakan medis paling umum di rumah sakit. Hampir setiap pasien rawat inap menerima terapi infus intravena sebagai bagian dari perawatan mereka — baik untuk rehidrasi, pemberian obat, maupun nutrisi parenteral. Namun di balik prosedur yang tampak sederhana ini, tersimpan risiko yang kerap luput dari perhatian.

Bayangkan seorang pasien yang berbaring sendirian di kamar rawat inap. Cairan infusnya hampir habis, tetapi perawat sedang menangani pasien lain di ujung koridor. Tidak ada alarm, tidak ada notifikasi. Ketika akhirnya perawat datang, kantong infus sudah kosong — darah pasien mulai mengalir balik ke selang. Kondisi ini, yang dikenal sebagai *blood backflow*, bukan hanya tidak nyaman, tetapi berpotensi menyebabkan komplikasi serius.

Situasi seperti ini bukan pengecualian. Ini adalah kenyataan sehari-hari di banyak fasilitas kesehatan di Indonesia, termasuk di Kota Bogor. Rasio perawat terhadap pasien yang tidak ideal — sering kali satu perawat menangani delapan hingga dua belas pasien sekaligus — membuat pemantauan infus secara manual menjadi tantangan besar. Perawat harus bolak-balik memeriksa setiap pasien, mengira-ngira kapan infus akan habis, dan mengandalkan ingatan serta intuisi untuk menentukan prioritas.

Di sinilah **TETES CERDAS** hadir sebagai jawaban.

### 1.2 Rumusan Masalah

Berdasarkan kondisi di lapangan, terdapat beberapa permasalahan utama yang ingin diselesaikan:

1. **Tidak ada sistem peringatan dini** ketika volume cairan infus mendekati habis, sehingga perawat sering terlambat mengganti kantong infus.
2. **Pemantauan manual tidak efisien** — perawat harus secara fisik mendatangi setiap pasien untuk mengecek kondisi infus, menghabiskan waktu dan tenaga yang seharusnya bisa digunakan untuk tindakan medis lain.
3. **Tidak ada data historis** mengenai laju tetesan dan konsumsi cairan per pasien, sehingga sulit melakukan evaluasi dan penyesuaian terapi.
4. **Keterlambatan respons terhadap panggilan darurat** pasien karena tidak ada sistem nurse call yang terintegrasi dengan monitoring infus.
5. **Beban kerja perawat yang tinggi** akibat tugas-tugas administratif dan pemantauan rutin yang seharusnya bisa diotomasi.

### 1.3 Tujuan Inovasi

1. Membangun sistem pemantauan infus real-time yang dapat diakses dari mana saja melalui dashboard web.
2. Memberikan peringatan otomatis kepada tenaga medis ketika volume cairan infus mencapai batas kritis (≤ 20%).
3. Mengintegrasikan fitur nurse call berbasis IoT yang memungkinkan pasien memanggil perawat dengan menekan tombol pada perangkat.
4. Menyediakan data historis laju tetesan dan konsumsi cairan untuk mendukung evaluasi terapi.
5. Mengurangi beban kerja perawat melalui otomasi pemantauan, sehingga mereka dapat fokus pada tindakan medis yang lebih bernilai.

### 1.4 Manfaat Inovasi

**Bagi Pasien:**
- Keamanan lebih terjamin karena infus tidak akan dibiarkan kosong tanpa penanganan
- Respons perawat lebih cepat berkat sistem nurse call terintegrasi
- Kenyamanan meningkat karena tidak perlu khawatir mengawasi infus sendiri

**Bagi Tenaga Medis:**
- Pemantauan banyak pasien sekaligus dari satu layar dashboard
- Notifikasi otomatis mengurangi kebutuhan ronde manual yang berulang
- Data historis membantu pengambilan keputusan klinis yang lebih baik

**Bagi Rumah Sakit:**
- Efisiensi operasional meningkat
- Potensi pengurangan insiden medis akibat keterlambatan penggantian infus
- Dokumentasi otomatis mendukung akreditasi dan audit klinis

---

## BAB II — DESKRIPSI INOVASI

### 2.1 Gambaran Umum Sistem

**TETES CERDAS** adalah sistem pemantauan infus cerdas yang menggabungkan teknologi Internet of Things (IoT) dengan antarmuka web berbasis dashboard. Sistem ini terdiri dari dua komponen utama yang bekerja secara sinergis:

**Komponen Perangkat Keras (Hardware):**
Sebuah modul elektronik kompak yang dipasang pada tiang infus, dilengkapi dengan sensor berat (*load cell*) untuk mengukur volume cairan secara akurat, sensor inframerah untuk menghitung laju tetesan per menit (TPM), layar OLED untuk tampilan lokal, tombol nurse call untuk panggilan darurat, dan buzzer sebagai alarm lokal. Semua komponen ini dikendalikan oleh mikrokontroler ESP32 yang memiliki kemampuan koneksi WiFi bawaan.

**Komponen Perangkat Lunak (Software):**
Dashboard web berbasis PHP dan MySQL yang dapat diakses melalui browser dari komputer atau smartphone perawat. Dashboard ini menampilkan kondisi semua pasien secara real-time, lengkap dengan grafik historis, estimasi waktu habis, dan log panggilan darurat.

### 2.2 Cara Kerja Sistem

Sistem bekerja dalam siklus yang sederhana namun efektif:

**1. Pengukuran di Sisi Perangkat**

Sensor *load cell* HX711 mengukur berat kantong infus setiap 500 milidetik. Berat ini dikonversi menjadi volume cairan dalam mililiter dengan memperhitungkan berat kantong kosong (50 gram). Secara bersamaan, sensor inframerah mendeteksi setiap tetes cairan yang melewati *drip chamber* dan menghitung laju tetesan per menit (TPM).

Dari dua data ini, sistem secara otomatis menghitung:
- **Volume sisa** dalam mililiter
- **Persentase cairan tersisa** terhadap volume awal
- **Estimasi waktu habis** berdasarkan laju tetesan saat ini

Semua kalkulasi ini berjalan secara paralel menggunakan arsitektur *Real-Time Operating System* (RTOS) pada ESP32, memastikan tidak ada data yang terlewat meskipun banyak proses berjalan bersamaan.

**2. Transmisi Data ke Server**

Setiap detik, ESP32 mengirimkan paket data JSON ke server melalui protokol HTTP POST. Paket data ini berisi seluruh parameter pemantauan termasuk status nurse call. Server menerima data, menyimpannya ke database MySQL, dan secara otomatis mendaftarkan perangkat baru jika belum terdaftar.

**3. Visualisasi di Dashboard**

Dashboard web memperbarui tampilan setiap 5 detik secara otomatis. Perawat dapat melihat kondisi semua pasien dalam satu layar — lengkap dengan indikator visual berupa progress bar volume, badge status online/offline, dan animasi peringatan untuk kondisi kritis. Ketika volume infus mencapai ≤ 20%, sistem menampilkan peringatan visual berkedip dan membunyikan notifikasi suara di browser perawat.

**4. Sistem Nurse Call**

Pasien dapat menekan tombol nurse call pada perangkat kapan saja. Sinyal ini langsung dikirim ke server dan ditampilkan sebagai peringatan darurat di dashboard — disertai suara alarm dan notifikasi *text-to-speech* yang menyebutkan nama pasien dan lokasi kamar. Perawat dapat merespons dan mematikan alarm langsung dari perangkat IoT setelah menangani pasien.

### 2.3 Spesifikasi Teknis

**Perangkat Keras:**

| Komponen | Spesifikasi |
|---|---|
| Mikrokontroler | ESP32 (dual-core 240 MHz, WiFi 802.11 b/g/n) |
| Sensor Berat | HX711 + Load Cell 1 kg (akurasi ±1 gram) |
| Sensor Tetesan | Sensor Inframerah (IR) dengan interrupt hardware |
| Layar | OLED SSD1306 128×64 pixel |
| Konektivitas | WiFi 2.4 GHz |
| Antarmuka Pengguna | 2 tombol (mode volume + nurse call), 1 buzzer |
| Catu Daya | 5V DC (adaptor atau power bank) |

**Perangkat Lunak:**

| Komponen | Teknologi |
|---|---|
| Firmware IoT | C++ dengan FreeRTOS (Arduino Framework) |
| Backend Server | PHP 8 Native |
| Database | MySQL / MariaDB |
| Frontend Dashboard | Tailwind CSS, Chart.js, JavaScript ES6 |
| Protokol Komunikasi | HTTP REST API (JSON) |
| Update Real-time | AJAX Polling (interval 5 detik) |

**Mode Volume yang Didukung:**
- Mode 500 ml (standar infus dewasa)
- Mode 100 ml (infus anak / dosis kecil)
- Mode OTHER (otomatis menyesuaikan dengan berat awal kantong)

### 2.4 Fitur Unggulan

**Dashboard Multi-Device:**
Satu dashboard dapat memantau puluhan perangkat sekaligus. Setiap perangkat ditampilkan sebagai kartu yang berisi semua informasi penting — nama pasien, lokasi kamar, volume sisa, laju tetesan, estimasi waktu habis, dan status koneksi.

**Deteksi Status Online/Offline:**
Sistem secara otomatis mendeteksi apakah perangkat masih aktif berdasarkan waktu pengiriman data terakhir. Jika tidak ada data dalam 30 detik, perangkat ditandai sebagai *offline* — membantu perawat mengetahui jika ada perangkat yang bermasalah.

**Grafik Historis Real-time:**
Halaman detail setiap pasien menampilkan grafik garis yang menunjukkan tren laju tetesan (TPM) dan volume cairan dari 50 data terakhir. Grafik ini diperbarui otomatis setiap 5 detik, memberikan gambaran visual yang jelas tentang perkembangan terapi infus.

**Log Nurse Call:**
Setiap panggilan darurat dicatat secara otomatis beserta waktu, nama pasien, dan lokasi. Log ini dapat digunakan untuk evaluasi respons time perawat dan audit klinis.

**Export Data CSV:**
Data historis dapat diekspor dalam format CSV untuk keperluan dokumentasi, pelaporan, atau analisis lebih lanjut.

**Tampilan Lokal di Perangkat:**
Layar OLED pada perangkat menampilkan informasi penting secara lokal — berguna ketika perawat berada di samping pasien dan ingin melihat data tanpa membuka dashboard.

---

## BAB III — KEUNGGULAN DAN KEBARUAN

### 3.1 Keunggulan Dibanding Solusi yang Ada

Saat ini, pemantauan infus di sebagian besar rumah sakit di Indonesia masih dilakukan secara manual. Beberapa rumah sakit besar mungkin memiliki sistem monitoring, tetapi umumnya berupa perangkat medis impor dengan harga yang sangat tinggi — tidak terjangkau untuk puskesmas, klinik, atau rumah sakit kelas C dan D.

**TETES CERDAS** menawarkan pendekatan yang berbeda:

| Aspek | Cara Konvensional | Sistem Komersial Impor | TETES CERDAS |
|---|---|---|---|
| Biaya per unit | Rp 0 (manual) | Rp 15–50 juta | < Rp 500 ribu |
| Pemantauan jarak jauh | ✗ | ✓ | ✓ |
| Multi-pasien sekaligus | ✗ | ✓ (terbatas) | ✓ (tidak terbatas) |
| Nurse call terintegrasi | ✗ | Sebagian | ✓ |
| Data historis | ✗ | ✓ | ✓ |
| Dapat dikustomisasi | ✗ | ✗ | ✓ (open source) |
| Ketergantungan vendor | - | Tinggi | Tidak ada |

### 3.2 Aspek Kebaruan (Novelty)

Kebaruan utama TETES CERDAS terletak pada **integrasi menyeluruh** antara pemantauan fisik (sensor berat + sensor tetesan) dengan sistem informasi berbasis web yang dapat diakses secara real-time. Beberapa aspek yang membedakan sistem ini:

1. **Dual-sensor approach**: Menggunakan dua sensor secara bersamaan — load cell untuk volume dan IR untuk laju tetesan — sehingga data yang dihasilkan lebih akurat dan saling memvalidasi.

2. **Arsitektur RTOS**: Firmware menggunakan FreeRTOS dengan delapan task paralel yang berjalan secara bersamaan, memastikan tidak ada data yang terlewat dan sistem tetap responsif.

3. **Auto-registration perangkat**: Perangkat baru secara otomatis terdaftar di sistem ketika pertama kali mengirim data, tanpa perlu konfigurasi manual di server.

4. **Estimasi waktu habis dinamis**: Estimasi dihitung ulang setiap siklus berdasarkan laju tetesan aktual saat itu, bukan berdasarkan nilai tetap — sehingga lebih akurat ketika laju tetesan berubah.

5. **Nurse call dengan anti-spam**: Sistem mencegah duplikasi log nurse call selama tombol ditekan terus-menerus, memastikan database tetap bersih dan log dapat diandalkan untuk audit.

### 3.3 Skalabilitas

Arsitektur sistem dirancang untuk mudah dikembangkan:
- Satu server dapat menangani ratusan perangkat secara bersamaan
- Penambahan perangkat baru tidak memerlukan perubahan konfigurasi server
- Dashboard responsif dan dapat diakses dari smartphone, tablet, maupun komputer
- Kode sumber terbuka dan terdokumentasi, memudahkan pengembangan lebih lanjut

---

## BAB IV — DAMPAK DAN RELEVANSI

### 4.1 Relevansi dengan Kondisi Kota Bogor

Kota Bogor memiliki beberapa rumah sakit umum daerah, puluhan klinik, dan ratusan fasilitas kesehatan tingkat pertama. Dengan populasi lebih dari satu juta jiwa dan terus bertumbuh, kebutuhan akan layanan kesehatan yang efisien dan berkualitas semakin mendesak.

Berdasarkan data Kementerian Kesehatan, rasio perawat terhadap tempat tidur di banyak rumah sakit daerah masih di bawah standar WHO. Kondisi ini membuat setiap inovasi yang dapat meringankan beban kerja perawat tanpa mengorbankan keselamatan pasien menjadi sangat relevan dan bernilai tinggi.

TETES CERDAS secara langsung menjawab tantangan ini dengan biaya implementasi yang sangat terjangkau — jauh di bawah anggaran pengadaan peralatan medis konvensional.

### 4.2 Dampak Langsung

**Keselamatan Pasien:**
Dengan peringatan dini ketika volume infus mencapai 20%, perawat memiliki waktu yang cukup untuk menyiapkan dan mengganti kantong infus sebelum habis. Ini secara langsung mengurangi risiko *blood backflow*, emboli udara, dan komplikasi lain akibat infus habis tanpa penanganan.

**Efisiensi Tenaga Medis:**
Satu perawat yang sebelumnya harus melakukan ronde manual setiap 30 menit untuk memeriksa 10 pasien, kini dapat memantau semua pasien dari satu layar. Waktu yang dihemat dapat dialihkan untuk tindakan medis yang lebih bernilai dan interaksi langsung dengan pasien.

**Kualitas Dokumentasi:**
Data yang tercatat secara otomatis dan terstruktur memudahkan pembuatan laporan, mendukung proses akreditasi rumah sakit, dan menyediakan bukti audit yang dapat diandalkan.

### 4.3 Potensi Pengembangan Lebih Lanjut

Sistem ini dirancang sebagai fondasi yang dapat dikembangkan lebih jauh:

- **Integrasi dengan sistem informasi rumah sakit (SIMRS)** untuk sinkronisasi data pasien secara otomatis
- **Notifikasi mobile** melalui aplikasi smartphone atau WhatsApp API untuk perawat yang sedang tidak di depan komputer
- **Analitik prediktif** menggunakan data historis untuk mengoptimalkan jadwal penggantian infus
- **Sensor suhu cairan** untuk memastikan cairan infus berada pada suhu yang tepat
- **Integrasi dengan sistem antrian** untuk manajemen sumber daya perawat yang lebih baik

---

## BAB V — METODOLOGI PENGEMBANGAN

### 5.1 Tahapan Pengembangan

**Tahap 1 — Identifikasi Masalah (Bulan 1)**
Observasi langsung di fasilitas kesehatan untuk memahami alur kerja perawat, titik-titik masalah dalam pemantauan infus, dan kebutuhan pengguna akhir. Wawancara dengan perawat dan tenaga medis untuk mendapatkan perspektif dari lapangan.

**Tahap 2 — Perancangan Sistem (Bulan 1–2)**
Perancangan arsitektur hardware dan software, pemilihan komponen elektronik, perancangan skema database, dan pembuatan wireframe dashboard. Pada tahap ini juga dilakukan studi literatur mengenai standar keselamatan infus dan regulasi alat kesehatan.

**Tahap 3 — Pengembangan Prototipe (Bulan 2–3)**
Perakitan perangkat keras, penulisan firmware ESP32 dengan arsitektur RTOS, pengembangan backend API, dan pembangunan dashboard web. Pengujian unit dilakukan pada setiap komponen secara terpisah.

**Tahap 4 — Integrasi dan Pengujian (Bulan 3–4)**
Integrasi seluruh komponen sistem, pengujian end-to-end, kalibrasi sensor, dan pengujian performa dengan simulasi beban banyak perangkat. Perbaikan bug dan optimasi berdasarkan hasil pengujian.

**Tahap 5 — Validasi dan Dokumentasi (Bulan 4–5)**
Pengujian prototipe dalam kondisi mendekati nyata, pengumpulan umpan balik dari calon pengguna, penyempurnaan antarmuka, dan penyusunan dokumentasi teknis lengkap.

### 5.2 Metode Pengujian

**Akurasi Sensor:**
Pengujian akurasi load cell dilakukan dengan membandingkan pembacaan sensor terhadap timbangan digital terstandar. Target akurasi: ±5 ml untuk volume di atas 50 ml.

**Keandalan Koneksi:**
Pengujian stabilitas koneksi WiFi dalam kondisi jaringan rumah sakit yang padat, termasuk uji *reconnect* otomatis ketika koneksi terputus.

**Performa Dashboard:**
Pengujian beban dengan simulasi 20+ perangkat aktif secara bersamaan untuk memastikan dashboard tetap responsif.

**Pengujian Fungsional:**
Verifikasi setiap fitur — peringatan volume rendah, nurse call, estimasi waktu, grafik historis, dan export data — berjalan sesuai spesifikasi.

---

## BAB VI — RENCANA IMPLEMENTASI

### 6.1 Rencana Jangka Pendek (0–6 Bulan)

- Finalisasi prototipe dan dokumentasi teknis
- Pengajuan izin uji klinis ke fasilitas kesehatan mitra
- Pilot project di satu bangsal rawat inap (10–15 tempat tidur)
- Evaluasi dan penyempurnaan berdasarkan umpan balik pengguna nyata
- Penyusunan panduan instalasi dan pelatihan untuk tenaga medis

### 6.2 Rencana Jangka Menengah (6–18 Bulan)

- Ekspansi ke seluruh bangsal di fasilitas kesehatan mitra
- Pengembangan fitur notifikasi mobile
- Integrasi dengan SIMRS yang sudah ada
- Replikasi ke fasilitas kesehatan lain di Kota Bogor
- Pengajuan sertifikasi alat kesehatan ke Kemenkes

### 6.3 Rencana Jangka Panjang (18 Bulan ke atas)

- Produksi massal dengan biaya yang lebih rendah melalui optimasi komponen
- Distribusi ke seluruh fasilitas kesehatan di Kota Bogor
- Pengembangan versi yang memenuhi standar alat kesehatan kelas II
- Potensi replikasi ke kota/kabupaten lain di Jawa Barat

### 6.4 Estimasi Biaya Implementasi

**Biaya per Unit Perangkat:**

| Komponen | Estimasi Harga |
|---|---|
| ESP32 Development Board | Rp 65.000 |
| Load Cell 1 kg + HX711 | Rp 35.000 |
| Sensor IR | Rp 8.000 |
| OLED SSD1306 | Rp 25.000 |
| Komponen pendukung (PCB, kabel, casing, tombol, buzzer) | Rp 80.000 |
| **Total per unit** | **± Rp 213.000** |

**Biaya Server (untuk seluruh rumah sakit):**
Sistem dapat berjalan di server lokal (on-premise) menggunakan komputer yang sudah ada, atau di cloud dengan biaya hosting mulai Rp 100.000–300.000 per bulan.
Sistem dapat berjalan di server lokal (on-premise) menggunakan komputer yang sudah ada, atau di cloud dengan biaya hosting mulai Rp 100.000–300.000 per bulan.

Dibandingkan dengan sistem monitoring infus komersial yang harganya puluhan juta rupiah per unit, TETES CERDAS menawarkan penghematan biaya yang sangat signifikan — lebih dari 98%.

---

## BAB VII — PENUTUP

### 7.1 Kesimpulan

TETES CERDAS adalah bukti bahwa inovasi teknologi tidak harus mahal untuk memberikan dampak yang besar. Dengan memanfaatkan komponen elektronik yang terjangkau dan teknologi perangkat lunak modern, sistem ini mampu menghadirkan kemampuan pemantauan infus yang selama ini hanya dimiliki oleh rumah sakit besar dengan anggaran besar.

Lebih dari sekadar alat teknologi, TETES CERDAS adalah upaya nyata untuk meningkatkan keselamatan pasien dan meringankan beban tenaga medis yang setiap hari bekerja keras melayani masyarakat. Di Kota Bogor yang terus berkembang, inovasi seperti ini bukan kemewahan — ini adalah kebutuhan.

Kami percaya bahwa setiap tetes cairan infus yang terpantau dengan baik adalah satu langkah lebih dekat menuju layanan kesehatan yang lebih aman, lebih efisien, dan lebih manusiawi.

### 7.2 Harapan

Melalui Bogor Innovation Award, kami berharap inovasi ini mendapat pengakuan dan dukungan untuk dapat diimplementasikan secara nyata di fasilitas kesehatan Kota Bogor. Dengan dukungan tersebut, TETES CERDAS dapat berkembang dari prototipe menjadi solusi yang benar-benar dirasakan manfaatnya oleh pasien dan tenaga medis di Kota Bogor.

---

## LAMPIRAN

### Lampiran A — Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────┐
│                    PERANGKAT IoT (ESP32)                  │
│                                                           │
│  [Load Cell] ──► [HX711] ──► Volume & Persentase         │
│  [Sensor IR] ──────────────► Laju Tetesan (TPM)          │
│  [Tombol Mode] ────────────► Pilih Mode Volume           │
│  [Tombol Nurse] ───────────► Aktifkan Nurse Call         │
│  [OLED Display] ◄──────────── Tampilan Lokal             │
│  [Buzzer] ◄────────────────── Alarm Lokal                │
│                                                           │
│  ESP32 RTOS (8 Task Paralel):                            │
│  LoadCell | TPM | OLED | Serial | Button |               │
│  NurseCall | WiFi | HTTPPost                             │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTP POST (JSON) setiap 1 detik
                       ▼
┌─────────────────────────────────────────────────────────┐
│                    SERVER (PHP + MySQL)                   │
│                                                           │
│  POST /api/post_data.php  ◄── Terima data dari ESP32     │
│  GET  /api/get_latest.php ──► Data terbaru semua device  │
│  GET  /api/get_history.php──► Riwayat data per device    │
│  GET  /api/get_nurse_log.php► Log panggilan darurat      │
└──────────────────────┬──────────────────────────────────┘
                       │ AJAX Polling (5 detik)
                       ▼
┌─────────────────────────────────────────────────────────┐
│                  DASHBOARD WEB (Browser)                  │
│                                                           │
│  index.php   ── Monitoring semua pasien (multi-device)   │
│  detail.php  ── Detail + grafik historis per pasien      │
│  devices.php ── Manajemen perangkat & data pasien        │
└─────────────────────────────────────────────────────────┘
```

### Lampiran B — Struktur Data yang Dikirim Perangkat

```json
{
  "device_id":    "INFUS-01",
  "tpm":          40,
  "volume_sisa":  350,
  "volume_awal":  500,
  "persen":       70,
  "estimasi_jam": 2,
  "estimasi_mnt": 30,
  "total_tetes":  3000,
  "nurse_call":   0,
  "mode":         "500ml"
}
```

### Lampiran C — Daftar Komponen Utama

| No | Komponen | Fungsi |
|---|---|---|
| 1 | ESP32 | Mikrokontroler utama + koneksi WiFi |
| 2 | HX711 + Load Cell | Mengukur berat/volume cairan infus |
| 3 | Sensor Inframerah | Menghitung tetesan per menit |
| 4 | OLED SSD1306 | Tampilan informasi lokal di perangkat |
| 5 | Tombol Mode | Memilih kapasitas kantong infus |
| 6 | Tombol Nurse Call | Pasien memanggil perawat |
| 7 | Buzzer | Alarm lokal di perangkat |

### Lampiran D — Teknologi yang Digunakan

**Firmware (C++ / Arduino Framework):**
- FreeRTOS untuk manajemen task paralel
- Library HX711 untuk pembacaan load cell
- Library Adafruit SSD1306 untuk layar OLED
- Library ArduinoJson untuk serialisasi data
- WiFi & HTTPClient (built-in ESP32)

**Backend (PHP 8 + MySQL):**
- PHP Native tanpa framework (ringan dan cepat)
- PDO untuk koneksi database yang aman
- REST API dengan format JSON
- Prepared statements untuk keamanan query

**Frontend (HTML + JavaScript):**
- Tailwind CSS untuk antarmuka modern dan responsif
- Chart.js untuk visualisasi grafik historis
- Vanilla JavaScript dengan AJAX untuk update real-time
- Web Speech API untuk notifikasi suara nurse call

---

*Proposal ini disusun sebagai bagian dari pengajuan inovasi untuk Bogor Innovation Award.*
*Seluruh kode sumber tersedia dan dapat diverifikasi.*
