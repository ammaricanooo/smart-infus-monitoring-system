package com.infusmobile

object AppConfig {
    // Jika aplikasi dijalankan di emulator Android Studio,
    // gunakan 10.0.2.2 untuk mengakses localhost host PC.
    const val BASE_API_URL = "http://10.0.2.2/infus_2/web"
    const val LATEST_ENDPOINT = "$BASE_API_URL/api/get_latest.php"
    const val HISTORY_ENDPOINT = "$BASE_API_URL/api/get_history.php"
}
