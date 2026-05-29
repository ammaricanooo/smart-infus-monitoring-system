# Infus Mobile Android

A native Android app scaffold for the Infus monitoring system.

## Struktur

- `android/` — Android Gradle project
- `android/app/` — aplikasi utama
- `android/app/src/main/java/com/infusmobile/` — kode Kotlin
- `android/app/src/main/res/` — resource UI

## Cara Jalankan

1. Buka `android/` di Android Studio.
2. Sinkronkan Gradle.
3. Jalankan app pada emulator atau perangkat.

## Ubah API Backend

Jika backend web kamu berada di emulator Android Studio, biarkan `BASE_API_URL` di `com.infusmobile.AppConfig`:

```kotlin
const val BASE_API_URL = "http://10.0.2.2/infus_2/web"
```

Jika menggunakan perangkat fisik atau jaringan lokal, ganti dengan IP komputer:

```kotlin
const val BASE_API_URL = "http://192.168.x.x/infus_2/web"
```

## Fitur awal

- Daftar perangkat aktif
- Detail status infus per perangkat
- Riwayat data infus per perangkat

## Endpoint yang dipakai

- `GET /api/get_latest.php`
- `GET /api/get_latest.php?device_id=...`
- `GET /api/get_history.php?device_id=...&limit=50`
