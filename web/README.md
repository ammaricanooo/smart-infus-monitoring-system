# Smart Infus — Web Monitoring

Dashboard monitoring infus berbasis ESP32 + PHP Native + MySQL.

## Struktur Folder

```
web/
├── index.php              # Dashboard utama
├── detail.php             # Detail per device
├── devices.php            # Kelola device
├── .htaccess
├── config/
│   └── db.php             # Konfigurasi database
├── api/
│   ├── post_data.php      # Endpoint POST dari ESP32
│   ├── get_latest.php     # Ambil data terbaru
│   ├── get_history.php    # Riwayat data
│   └── get_nurse_log.php  # Log nurse call
├── assets/
│   ├── css/style.css
│   └── js/
│       ├── dashboard.js
│       └── detail.js
└── database/
    └── infus.sql          # Script SQL
```

## Cara Setup

### 1. Database

Buka phpMyAdmin atau MySQL CLI, jalankan:

```sql
SOURCE /path/to/web/database/infus.sql;
```

Atau import file `database/infus.sql` lewat phpMyAdmin.

### 2. Konfigurasi Database

Edit `config/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // password MySQL kamu
define('DB_NAME', 'smart_infus');
```

### 3. Taruh di Laragon

Copy folder `web/` ke:
```
C:\laragon\www\infus\
```

Akses di browser: `http://localhost/infus/`

### 4. Konfigurasi Arduino (ESP32)

Edit bagian ini di `INFUS_2_CONNECT_WEB.ino`:

```cpp
#define WIFI_SSID     "NAMA_WIFI_KAMU"
#define WIFI_PASSWORD "PASSWORD_WIFI"
#define SERVER_URL    "http://192.168.1.100/infus/api/post_data.php"
#define DEVICE_ID     "INFUS-01"
```

- `SERVER_URL` → ganti `192.168.1.100` dengan IP komputer kamu
  (cek dengan `ipconfig` di CMD)
- `DEVICE_ID` → ID unik tiap perangkat ESP32

### 5. Library Arduino yang Dibutuhkan

Install via Arduino Library Manager:
- `HX711` by bogde
- `Adafruit SSD1306`
- `Adafruit GFX Library`
- `ArduinoJson` by Benoit Blanchon

WiFi & HTTPClient sudah built-in di ESP32 board package.

## Endpoint API

| Method | URL | Deskripsi |
|--------|-----|-----------|
| POST | `/api/post_data.php` | Kirim data dari ESP32 |
| GET | `/api/get_latest.php` | Data terbaru semua device |
| GET | `/api/get_latest.php?device_id=X` | Data terbaru 1 device |
| GET | `/api/get_history.php?device_id=X` | Riwayat data |
| GET | `/api/get_nurse_log.php` | Log nurse call |

## Contoh Payload ESP32 → Server

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
