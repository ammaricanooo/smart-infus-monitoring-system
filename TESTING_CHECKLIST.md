# Smart Infus - Testing Checklist ✅

## Quick Start

1. **Start Laragon** - Pastikan Apache & MySQL berjalan
2. **Access Dashboard** - Buka `http://localhost/infus_2/web/` di browser
3. **Browser** - Gunakan browser modern (Chrome, Firefox, Safari, Edge)

## 📋 Testing Checklist

### **Dashboard (index.php)**
- [ ] Page loads dengan layout yang rapi
- [ ] Navbar sticky saat scroll
- [ ] Stat cards (Total, Online, Volume Rendah, Nurse Call) menampilkan angka
- [ ] Device cards menampilkan dengan grid 3 kolom (desktop)
- [ ] Card info: Device name, location, patient name terlihat
- [ ] Online status badge berwarna hijau (online) atau abu-abu (offline)
- [ ] Volume progress bar animasi smooth
- [ ] TPM, Estimasi, Total Tetes terupdate
- [ ] Nurse call log table menampilkan data
- [ ] Responsive: Test di mobile (< 640px), tablet, desktop

### **Detail Monitor (detail.php)**  
- [ ] Page loads dengan info device lengkap
- [ ] 4 stat cards (TPM, Volume, Persentase, Estimasi) menampilkan
- [ ] Volume progress bar besar dengan persentase
- [ ] Chart TPM & Volume menampilkan grafik 50 data terakhir
- [ ] Chart update realtime setiap 5 detik
- [ ] History table menampilkan 50 data terakhir
- [ ] Responsive di semua ukuran screen
- [ ] Klik "Detail" dari dashboard membawa ke halaman detail

### **Device Management (devices.php)**
- [ ] Page loads dengan form dan table
- [ ] Form untuk tambah/edit device
- [ ] Device table menampilkan daftar device
- [ ] Tombol Detail, Edit, Delete berfungsi
- [ ] Delete device meminta konfirmasi
- [ ] Form validation bekerja (required fields)
- [ ] Success/error message muncul setelah submit
- [ ] Empty state menampilkan jika tidak ada device

### **Real-Time Features**
- [ ] Dashboard refresh setiap 5 detik (lihat data berubah)
- [ ] Clock di navbar menampilkan jam real-time
- [ ] Volume sisa terupdate real-time
- [ ] Persentase terupdate real-time
- [ ] TPM terupdate real-time
- [ ] Online status berubah saat device online/offline
- [ ] Chart update dengan data terbaru
- [ ] Nurse call alert muncul jika ada device dengan nurse call aktif

### **Visual & Design**
- [ ] Navbar gradient biru terlihat
- [ ] Font Inter/sans-serif terlihat
- [ ] Card shadows terlihat (elevation effect)
- [ ] Color coding:
  - Volume > 50% = Hijau (green)
  - Volume 20-50% = Orange (orange)
  - Volume < 20% = Merah (red) + blink animation
- [ ] Buttons memiliki hover effect
- [ ] Table rows memiliki hover effect
- [ ] Badges terlihat dengan warna yang berbeda
- [ ] Icons dari Bootstrap Icons terlihat

### **Navigation**
- [ ] Navbar links berfungsi (Dashboard, Kelola Device)
- [ ] Breadcrumb navigasi terlihat di detail & devices page
- [ ] Back button berfungsi
- [ ] Detail link dari dashboard ke detail monitor
- [ ] Edit link dari dashboard ke devices page

### **Responsive Design**
- [ ] **Mobile (< 640px)**
  - [ ] Single column layout
  - [ ] Navbar icons responsive
  - [ ] Card stacked vertically
  - [ ] Buttons full width
  - [ ] Table scroll horizontally

- [ ] **Tablet (640px - 1024px)**
  - [ ] 2 column layout untuk devices
  - [ ] Optimal spacing
  - [ ] All content visible

- [ ] **Desktop (> 1024px)**
  - [ ] 3 column layout untuk devices
  - [ ] Full navigation visible
  - [ ] Sidebar form sticky (devices page)

### **Browser Compatibility**
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari (Mac)
- [ ] Edge
- [ ] Mobile browsers (iOS Safari, Chrome Mobile)

### **Performance**
- [ ] Page load time < 2 detik
- [ ] No console errors F12 → Console tab
- [ ] No broken images
- [ ] Charts render smoothly
- [ ] Smooth animations saat scroll/hover

### **Accessibility**
- [ ] Can navigate with Tab key
- [ ] Button focus states visible
- [ ] Icons have visible labels
- [ ] Color not only indicator (text + badge)
- [ ] Text readable (contrast OK)

## 🐛 Bug Reporting

Jika menemukan bug, perhatikan:
1. **Screenshot** - Ambil screenshot error
2. **Browser** - Catat browser & version
3. **Steps** - Catat langkah untuk reproduce
4. **Console Error** - Lihat F12 → Console untuk error messages
5. **Expected** - Apa yang seharusnya terjadi

## 📊 Database Check

Pastikan database terisi dengan data:
```sql
-- Check devices table
SELECT COUNT(*) as total_devices FROM devices WHERE aktif = 1;

-- Check infus_data table  
SELECT COUNT(*) as total_data FROM infus_data;

-- Check nurse_call_log table
SELECT COUNT(*) as total_logs FROM nurse_call_log;
```

## 🔄 Realtime Data Test

1. **Arduino Side:**
   - Kirim sample data ke API `/api/post_data.php`
   - Data: device_id, tpm, volume_sisa, volume_awal, persen, mode

2. **Web Side:**
   - Buka dashboard & detail monitor
   - Data seharusnya update setiap 5 detik
   - Chart seharusnya menunjukkan trend yang benar

## 📱 Mobile Testing

Gunakan browser devtools untuk emulasi mobile:
1. F12 → Device Toolbar (Ctrl+Shift+M)
2. Pilih device (iPhone, Pixel, dll)
3. Test semua halaman & features
4. Verifikasi touch interactions

## ✨ Optional Enhancements

Saat ini didukung:
- Line chart realtime
- Status badges
- Nurse call alerts
- Progress bars
- Responsive grid
- Modern styling

Bisa ditambahkan di masa depan:
- Dark mode toggle
- Fullscreen chart
- Export data ke CSV/PDF
- Search/filter device
- Custom date ranges

## 📝 Notes

- **CSS Framework:** Sudah 100% Tailwind, Bootstrap dihapus
- **Chart:** Masih gunakan Chart.js (efektif untuk realtime data)
- **API:** Tidak ada perubahan, semua endpoint compatibility
- **Database:** Tidak ada perubahan schema
- **Sessions:** Jika ada, tetap berfungsi (di db.php)

## ✅ Final Sign-off

Kalau semua checklist ✅ green, aplikasi adalah:
- **Siap produksi**
- **UI/UX modern**  
- **Performance optimal**
- **Mobile-friendly**
- **Realtime functional**

---

**Happy Testing!** 🚀
