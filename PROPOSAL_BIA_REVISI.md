# PROPOSAL INOVASI
## BOGOR INNOVATION AWARD
### Tema: Kesehatan & Obat-obatan

---

**Nama Inovasi:** SMART INFUS MONITORING SYSTEM
**Kategori:** Kesehatan & Obat-obatan
**Jenis Inovasi:** Teknologi Tepat Guna Berbasis IoT
**Tahun Pengembangan:** 2026
**Status:** Prototipe Fungsional

---

## 1. Sumber Inspirasi Inovasi

Inspirasi inovasi ini berasal dari pengalaman nyata yang dialami oleh keluarga pasien rawat inap. Suatu hari, seorang anggota keluarga yang sedang dirawat di rumah sakit mengalami kejadian yang mengkhawatirkan: cairan infus habis tanpa diketahui oleh perawat, hingga darah pasien mulai mengalir balik ke dalam selang infus. Kondisi ini dikenal dalam dunia medis sebagai *blood backflow* dan dapat menimbulkan komplikasi serius jika tidak segera ditangani.

Kejadian tersebut mendorong penelusuran lebih lanjut mengenai seberapa sering masalah ini terjadi. Dari berbagai sumber informasi dan artikel kesehatan, ditemukan bahwa kejadian serupa cukup sering terjadi di berbagai fasilitas kesehatan di Indonesia. Akar permasalahannya bukan semata-mata kelalaian perawat, melainkan ketidakseimbangan antara jumlah pasien yang harus dipantau dan jumlah tenaga perawat yang tersedia. Satu orang perawat sering kali harus menjaga delapan hingga dua belas pasien sekaligus, sehingga pemantauan infus satu per satu secara manual menjadi sangat berat.

Dari situasi inilah muncul pertanyaan: *mengapa belum ada alat terjangkau yang bisa memantau infus secara otomatis dan memberikan peringatan kepada perawat sebelum infus benar-benar habis?* Pertanyaan itulah yang menjadi titik awal lahirnya SMART INFUS MONITORING SYSTEM.

---

## 2. Rancang Bangun Inovasi

### Latar Belakang

Terapi infus intravena adalah prosedur medis yang paling umum dilakukan di rumah sakit. Hampir seluruh pasien rawat inap menerima cairan infus sebagai bagian dari perawatan mereka, baik untuk rehidrasi, pemberian obat, maupun nutrisi. Namun di balik prosedur yang tampak rutin ini, tersimpan potensi risiko yang sering kali diabaikan.

Di sebagian besar fasilitas kesehatan di Indonesia, pemantauan infus masih dilakukan secara manual. Perawat harus berkeliling dari kamar ke kamar untuk mengecek kondisi infus setiap pasien. Dengan rasio perawat terhadap pasien yang tidak ideal, cara ini tidak lagi memadai dan rawan terhadap kelalaian.

### Permasalahan

Terdapat lima permasalahan utama yang melatarbelakangi inovasi ini:

1. Tidak adanya sistem peringatan dini ketika volume cairan infus mendekati habis, sehingga perawat sering terlambat melakukan penggantian.
2. Pemantauan manual yang tidak efisien, mengharuskan perawat mendatangi setiap pasien secara fisik dan berulang-ulang.
3. Tidak tersedianya data historis mengenai laju tetesan dan konsumsi cairan per pasien, sehingga evaluasi terapi menjadi sulit.
4. Tidak adanya sistem panggilan darurat (*nurse call*) yang terintegrasi dengan pemantauan infus.
5. Tingginya beban kerja perawat akibat tugas-tugas pemantauan rutin yang sebenarnya dapat diotomasi.

### Maksud dan Tujuan

Inovasi ini bertujuan untuk:

1. Membangun sistem pemantauan infus secara *real-time* yang dapat diakses dari mana saja melalui *dashboard* berbasis web.
2. Memberikan peringatan otomatis kepada tenaga medis ketika volume cairan infus mencapai batas kritis (≤ 20% dari volume awal).
3. Mengintegrasikan fitur *nurse call* berbasis IoT, sehingga pasien dapat memanggil perawat melalui tombol yang terpasang langsung pada alat.
4. Menyediakan rekam data historis laju tetesan dan konsumsi cairan untuk mendukung evaluasi terapi.
5. Meringankan beban kerja perawat melalui otomasi pemantauan, agar tenaga medis dapat berfokus pada tindakan klinis yang lebih bernilai.

### Solusi yang Ditawarkan

SMART INFUS MONITORING SYSTEM adalah alat elektronik kompak yang dipasang pada tiang infus. Alat ini dilengkapi sensor berat untuk mengukur volume cairan secara akurat, sensor inframerah untuk menghitung laju tetesan per menit, layar kecil untuk menampilkan informasi secara lokal, tombol panggilan darurat untuk pasien, serta alarm bunyi. Seluruh data dikirim secara nirkabel ke server dan ditampilkan pada *dashboard* web yang dapat dipantau perawat dari ruang jaga maupun smartphone.

Ketika volume infus mendekati habis, sistem otomatis membunyikan alarm dan menampilkan peringatan visual berkedip di layar monitor perawat. Ketika pasien menekan tombol panggilan, notifikasi suara yang menyebutkan nama pasien dan nomor kamar akan berbunyi di ruang jaga.

---

## 3. Pendekatan Ilmiah dalam Proses Inovasi

### Pengamatan atas Lingkungan Sekitar

Pengamatan dilakukan secara langsung terhadap situasi di fasilitas kesehatan, khususnya pada alur kerja perawat dalam memantau pasien rawat inap. Ditemukan bahwa perawat menghabiskan porsi waktu yang signifikan hanya untuk berjalan dari satu kamar ke kamar lain guna mengecek kondisi infus, sebuah pekerjaan yang berulang dan dapat diotomasi. Selain itu, diamati pula bahwa sistem *nurse call* konvensional yang ada tidak mampu memberi informasi konteks kepada perawat tentang kondisi infus pasien yang memanggil.

### Pengumpulan Data dan Informasi

Beberapa hal yang dipelajari dan dikumpulkan sebagai dasar perancangan:

- **Berat standar kantong infus kosong:** Melalui penimbangan berbagai merek kantong infus yang tersedia di pasaran, diperoleh data bahwa berat rata-rata kantong infus kosong adalah sekitar 48–52 gram. Nilai ini dijadikan konstanta kalibrasi (50 gram) dalam perhitungan volume sisa.
- **Rumus Tetesan Per Menit (TPM):** Dipelajari rumus medis standar yang digunakan dokter dan perawat untuk menghitung laju infus, yaitu: TPM = (Volume (ml) × Faktor Tetes) / (Waktu dalam menit). Rumus ini menjadi dasar algoritma perhitungan estimasi waktu habis pada perangkat.
- **Standar keselamatan infus:** Dipelajari ambang batas kritis yang umumnya digunakan di klinis, yakni peringatan dini diberikan saat sisa cairan mencapai 20% dari volume awal, memberikan waktu sekitar 30–45 menit bagi perawat untuk bertindak pada laju tetesan normal (40–60 TPM).

### Uji Coba dan Eksperimen

Serangkaian pengujian dilakukan sebelum sistem dinyatakan layak:

- **Kalibrasi sensor berat (Load Cell):** Sensor berat diuji dengan membandingkan pembacaannya terhadap timbangan digital berstandar. Pengujian dilakukan menggunakan air steril dengan volume terukur (100 ml, 250 ml, 500 ml). Hasil: tingkat akurasi mencapai toleransi ±3–5 ml, memenuhi target yang ditetapkan.
- **Uji sensor tetesan (Inframerah):** Sensor inframerah diuji dengan menghitung tetesan secara manual dan dibandingkan dengan hitungan otomatis alat. Dilakukan dengan variasi laju tetesan lambat (20 TPM), normal (40 TPM), dan cepat (60 TPM). Hasil: tidak ada tetesan yang terlewat pada ketiga kondisi tersebut.
- **Uji stabilitas transmisi data:** Alat diuji untuk mengirim data ke server secara terus-menerus selama 8 jam penuh tanpa gangguan. Hasil: sistem stabil tanpa mengalami kegagalan pengiriman data.
- **Uji beban *dashboard*:** Dilakukan simulasi dengan 20 perangkat aktif secara bersamaan mengirim data ke satu server. Hasil: *dashboard* tetap responsif dan tidak mengalami penurunan performa yang berarti.

### Inovasi yang Dihasilkan

Dari seluruh proses di atas, dihasilkan sebuah sistem terintegrasi yang menggabungkan perangkat keras IoT dengan perangkat lunak berbasis web, mampu memantau banyak pasien sekaligus dari satu titik pengamatan, dengan biaya per unit di bawah Rp 300.000.

---

## 4. Faktor Pembeda dan Kebaruan Inovasi

SMART INFUS MONITORING SYSTEM memiliki sejumlah keunggulan yang membedakannya dari cara konvensional maupun produk komersial yang ada:

| Aspek | Cara Konvensional (Manual) | Sistem Komersial Impor | SMART INFUS MONITORING SYSTEM |
|---|---|---|---|
| Biaya per unit | Rp 0 (tenaga manusia) | Rp 15.000.000 – Rp 50.000.000 | ± Rp 278.000 |
| Pemantauan jarak jauh | Tidak bisa | Bisa | Bisa |
| Pantau banyak pasien sekaligus | Tidak bisa | Terbatas | Tidak terbatas |
| *Nurse call* terintegrasi | Tidak ada | Sebagian ada | Ada, terintegrasi penuh |
| Rekam data historis | Tidak ada | Ada | Ada |
| Bisa dikustomisasi | Tidak | Tidak | Ya (kode sumber terbuka) |
| Ketergantungan vendor | — | Tinggi | Tidak ada |

Kebaruan utama inovasi ini terletak pada beberapa aspek berikut:

1. **Pendekatan dua sensor secara bersamaan:** Menggunakan sensor berat *dan* sensor tetesan secara paralel, sehingga kedua data saling memvalidasi dan menghasilkan estimasi yang lebih akurat dibanding menggunakan satu sensor saja.

2. **Estimasi waktu habis yang dinamis:** Estimasi dihitung ulang setiap siklus berdasarkan laju tetesan aktual saat itu, bukan nilai tetap. Ini penting karena laju infus dapat berubah akibat posisi tubuh pasien atau tekanan darah.

3. **Pendaftaran perangkat otomatis:** Perangkat baru langsung terdaftar di sistem tanpa perlu konfigurasi manual di server, sehingga mudah diperluas ke banyak kamar tanpa keahlian teknis khusus.

4. **Sistem *nurse call* anti-duplikasi:** Dirancang agar pencatatan log panggilan darurat tidak tercatat ganda saat tombol ditekan terus-menerus, menjaga keandalan data untuk keperluan audit klinis.

5. **Biaya sangat terjangkau:** Menggunakan komponen elektronik yang tersedia di pasar lokal, dengan total biaya per unit kurang dari Rp 300.000 — lebih dari 98% lebih murah dibanding sistem komersial impor.

---

## 5. Manfaat Sebelum dan Sesudah Adanya Inovasi

| Aspek | Sebelum Ada Inovasi | Sesudah Ada Inovasi |
|---|---|---|
| **Cara Pemantauan Infus** | Perawat harus berkeliling ke setiap kamar secara fisik setiap 30–60 menit hanya untuk mengecek sisa cairan infus | Pemantauan dilakukan secara terpusat dan *real-time* melalui *dashboard* web di ruang jaga atau smartphone perawat |
| **Keselamatan Pasien** | Risiko *blood backflow* dan emboli udara tinggi jika perawat terlambat datang atau pasien tertidur | Sistem memberikan peringatan dini (alarm suara dan visual) saat cairan ≤ 20%, memberikan waktu aman bagi perawat untuk bertindak |
| **Sistem Panggilan Darurat** | Bel konvensional tanpa informasi konteks; pasien harus berteriak atau keluarga mendatangi ruang perawat | Terintegrasi pada alat infus dengan notifikasi suara otomatis yang menyebutkan nama pasien dan nomor kamar di ruang jaga |
| **Efisiensi Waktu Perawat** | Waktu produktif perawat tersita untuk mobilitas pengecekan rutin yang berulang | Menghemat hingga 70% waktu kontrol rutin, perawat dapat berfokus pada tindakan klinis yang lebih bernilai |
| **Pencatatan Data Medis** | Konsumsi cairan dicatat secara manual di kertas, rentan terhadap kesalahan dan sulit ditelusuri | Data laju tetesan dan volume tercatat otomatis dalam basis data, dapat diekspor ke format CSV untuk keperluan audit medis dan akreditasi |
| **Kondisi Saat Perawat Tidak Ada** | Tidak ada sistem yang memberi tahu jika infus hampir habis saat perawat sedang di tempat lain | Alarm lokal berbunyi di kamar pasien dan notifikasi muncul di *dashboard* secara bersamaan |

---

## 6. Pendekatan Mengenalkan Inovasi ke Masyarakat

Strategi pengenalan inovasi dilakukan secara bertahap melalui empat pendekatan:

**Tahap 1 — Demonstrasi kepada Pemangku Kebijakan**
Melakukan audiensi dan demonstrasi langsung (*live demo*) di hadapan Dinas Kesehatan Kota Bogor dan pengelola RSUD Kota Bogor. Tujuannya adalah menunjukkan secara nyata cara kerja alat, manfaatnya, dan efisiensi biayanya kepada pengambil keputusan di tingkat institusi.

**Tahap 2 — Uji Coba Terbatas (***Pilot Project***)**
Memasang prototipe secara gratis di satu bangsal atau klinik rawat inap berskala kecil di Kota Bogor selama dua minggu. Selama periode ini, dikumpulkan testimoni langsung dari perawat dan pasien sebagai bahan evaluasi dan bukti manfaat nyata di lapangan.

**Tahap 3 — Pelatihan Teknis**
Mengadakan sesi pelatihan singkat bagi staf keperawatan mengenai cara membaca *dashboard*, pengoperasian alat, dan penanganan dasar jika terjadi gangguan koneksi. Pelatihan dirancang sesederhana mungkin sehingga dapat dikuasai tanpa latar belakang teknis.

**Tahap 4 — Publikasi Terbuka**
Mendokumentasikan seluruh desain, rangkaian elektronik, dan kode sumber secara terbuka di repositori GitHub. Hal ini memungkinkan komunitas teknologi, mahasiswa, dan pengembang lain di seluruh Indonesia untuk mereplikasi, memodifikasi, dan mengembangkan inovasi ini sesuai kebutuhan daerah masing-masing.

---

## 7. Lama Waktu Pengembangan

Inovasi ini dikembangkan dalam total waktu **5 minggu** sejak ide awal hingga prototipe fungsional siap diuji, dengan rincian sebagai berikut:

| Minggu | Tahapan | Kegiatan Utama |
|---|---|---|
| Minggu 1 | Identifikasi Masalah | Pengamatan lapangan, penelusuran literatur, wawancara dengan tenaga medis, pemetaan alur kerja dan titik masalah |
| Minggu 1–2 | Perancangan Sistem | Perancangan arsitektur perangkat keras dan perangkat lunak, pemilihan komponen, skema basis data, dan kerangka tampilan *dashboard* |
| Minggu 2–3 | Pengembangan Prototipe | Perakitan perangkat keras, penulisan program mikrokontroler, pengembangan *server* API, dan pembangunan tampilan *dashboard* web |
| Minggu 3–4 | Integrasi dan Pengujian | Penyatuan seluruh komponen, pengujian menyeluruh (*end-to-end*), kalibrasi sensor, simulasi beban banyak perangkat, perbaikan masalah yang ditemukan |
| Minggu 4–5 | Validasi dan Dokumentasi | Pengujian dalam kondisi mendekati nyata, pengumpulan umpan balik, penyempurnaan antarmuka, penyusunan dokumentasi teknis lengkap |

---

## 8. Keuntungan Ekonomi

Inovasi ini memberikan keuntungan ekonomi yang nyata bagi fasilitas kesehatan dari tiga sisi:

**a. Penghematan Belanja Modal (CapEx)**
Sistem monitoring infus komersial yang diimpor dari luar negeri umumnya dijual seharga Rp 15.000.000 hingga Rp 50.000.000 per unit. SMART INFUS MONITORING SYSTEM menawarkan kemampuan yang setara dengan biaya produksi hanya sekitar Rp 278.000 per unit — penghematan lebih dari 98%. Fasilitas kesehatan tingkat pertama seperti puskesmas dan klinik yang selama ini tidak mampu memiliki sistem monitoring digital, kini berpeluang mendapatkan akses terhadap teknologi tersebut.

**b. Penghematan Biaya Operasional (OpEx)**
Dengan berkurangnya kebutuhan ronde manual, waktu kerja perawat dapat dialokasikan lebih efisien untuk tindakan klinis yang lebih bernilai. Secara ekonomi, pengurangan mobilitas rutin ini berpotensi menekan biaya lembur dan mengoptimalkan produktivitas tenaga medis.

**c. Pengurangan Risiko Kerugian Finansial Akibat Insiden Medis**
Insiden infus habis yang mengakibatkan komplikasi dapat berujung pada tuntutan hukum atau klaim asuransi yang merugikan fasilitas kesehatan. Dengan sistem peringatan dini yang andal, risiko terjadinya insiden tersebut dapat diminimalkan secara signifikan.

---

## 9. Anggaran yang Dibutuhkan

### Biaya per Unit Perangkat

| Komponen | Estimasi Harga |
|---|---|
| ESP32 Development Board | Rp 65.000 |
| Load Cell 1 kg + Modul HX711 | Rp 35.000 |
| Sensor Inframerah (IR) | Rp 8.000 |
| Layar OLED SSD1306 | Rp 25.000 |
| Komponen pendukung (PCB, kabel, casing, tombol, buzzer, step-down, baterai) | Rp 145.000 |
| **Total per unit** | **± Rp 278.000** |

### Anggaran Keseluruhan untuk Tahap Prototipe dan Pengenalan

| Kebutuhan | Volume | Harga Satuan | Total |
|---|---|---|---|
| Komponen perangkat keras IoT (2 unit prototipe) | 2 unit | Rp 278.000 | Rp 556.000 |
| Casing custom (cetak 3D / akrilik) | 2 unit | Rp 75.000 | Rp 150.000 |
| Sewa server cloud & domain (3 bulan pengujian) | 3 bulan | Rp 50.000 | Rp 150.000 |
| Cairan infus & selang untuk kalibrasi | 5 set | Rp 25.000 | Rp 125.000 |
| Transportasi observasi & validasi lapangan | — | — | Rp 150.000 |
| **Total Anggaran** | | | **± Rp 1.131.000** |

Catatan: Biaya server untuk skala produksi dapat menggunakan infrastruktur komputer yang sudah ada di rumah sakit (*on-premise*), sehingga tidak memerlukan biaya tambahan berulang.

---

## 10. Rencana Pengembangan Inovasi

| Fase | Periode | Target dan Kegiatan |
|---|---|---|
| **Jangka Pendek** | 0–3 Bulan | Finalisasi prototipe; pengajuan izin uji klinis; *pilot project* di satu bangsal rawat inap (10–15 tempat tidur); evaluasi berdasarkan umpan balik pengguna nyata; penyusunan panduan instalasi dan pelatihan tenaga medis |
| **Jangka Menengah** | 3–12 Bulan | Ekspansi ke seluruh bangsal di fasilitas mitra; pengembangan fitur notifikasi melalui smartphone (WhatsApp/push notification); integrasi dengan Sistem Informasi Manajemen Rumah Sakit (SIMRS); replikasi ke fasilitas kesehatan lain di Kota Bogor; pengajuan sertifikasi alat kesehatan ke Kementerian Kesehatan |
| **Jangka Panjang** | 12 Bulan ke atas | Produksi massal dengan optimasi biaya komponen; distribusi ke seluruh fasilitas kesehatan di Kota Bogor; pengembangan versi yang memenuhi standar alat kesehatan Kelas II; replikasi ke kota dan kabupaten lain di Jawa Barat |

Prioritas pengembangan fitur ke depan meliputi:
- Integrasi analitik prediktif untuk memperkirakan jadwal penggantian infus secara proaktif
- Penambahan sensor suhu cairan untuk memastikan cairan berada pada suhu yang aman
- Pengembangan aplikasi mobile khusus untuk perawat
- Sinkronisasi data pasien secara otomatis dengan rekam medis elektronik

---

*Proposal ini diajukan sebagai bagian dari Bogor Innovation Award.*
*Seluruh kode sumber tersedia dan dapat diverifikasi secara terbuka.*
