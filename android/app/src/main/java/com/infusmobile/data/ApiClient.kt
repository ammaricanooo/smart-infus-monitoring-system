package com.infusmobile.data

import com.infusmobile.AppConfig
import com.infusmobile.model.Device
import com.infusmobile.model.HistoryPoint
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.BufferedReader
import java.io.InputStreamReader
import java.net.HttpURLConnection
import java.net.URL

suspend fun fetchLatestDevices(): Result<List<Device>> = withContext(Dispatchers.IO) {
    runCatching {
        val url = URL(AppConfig.LATEST_ENDPOINT)
        val response = url.openConnection().let { conn ->
            conn as HttpURLConnection
            conn.requestMethod = "GET"
            conn.connectTimeout = 15_000
            conn.readTimeout = 15_000
            conn.inputStream.bufferedReader().use(BufferedReader::readText)
        }
        val json = JSONObject(response)
        if (json.optString("status") != "ok") {
            throw IllegalStateException(json.optString("message", "unknown response"))
        }
        val data = json.optJSONArray("data") ?: JSONArray()
        (0 until data.length()).map { i -> parseDevice(data.getJSONObject(i)) }
    }
}

suspend fun fetchDeviceDetail(deviceId: String): Result<Device?> = withContext(Dispatchers.IO) {
    runCatching {
        val url = URL("${AppConfig.LATEST_ENDPOINT}?device_id=${encode(deviceId)}")
        val response = url.openConnection().let { conn ->
            conn as HttpURLConnection
            conn.requestMethod = "GET"
            conn.connectTimeout = 15_000
            conn.readTimeout = 15_000
            conn.inputStream.bufferedReader().use(BufferedReader::readText)
        }
        val json = JSONObject(response)
        if (json.optString("status") != "ok") {
            throw IllegalStateException(json.optString("message", "unknown response"))
        }
        val data = json.optJSONObject("data") ?: return@runCatching null
        parseDevice(data)
    }
}

suspend fun fetchDeviceHistory(deviceId: String, limit: Int = 50): Result<List<HistoryPoint>> = withContext(Dispatchers.IO) {
    runCatching {
        val url = URL("${AppConfig.HISTORY_ENDPOINT}?device_id=${encode(deviceId)}&limit=$limit")
        val response = url.openConnection().let { conn ->
            conn as HttpURLConnection
            conn.requestMethod = "GET"
            conn.connectTimeout = 15_000
            conn.readTimeout = 15_000
            conn.inputStream.bufferedReader().use(BufferedReader::readText)
        }
        val json = JSONObject(response)
        if (json.optString("status") != "ok") {
            throw IllegalStateException(json.optString("message", "unknown response"))
        }
        val data = json.optJSONArray("data") ?: JSONArray()
        (0 until data.length()).map { i -> parseHistoryPoint(data.getJSONObject(i)) }
    }
}

private fun encode(value: String): String = java.net.URLEncoder.encode(value, "UTF-8")

private fun parseDevice(json: JSONObject): Device {
    return Device(
        deviceId = json.optString("device_id"),
        nama = json.optString("nama"),
        lokasi = json.optString("lokasi"),
        pasien = json.optString("pasien"),
        tpm = json.optInt("tpm"),
        volumeSisa = json.optInt("volume_sisa"),
        volumeAwal = json.optInt("volume_awal"),
        persen = json.optInt("persen"),
        estimasiJam = json.optInt("estimasi_jam"),
        estimasiMnt = json.optInt("estimasi_mnt"),
        totalTetes = json.optInt("total_tetes"),
        nurseCall = json.optInt("nurse_call"),
        mode = json.optString("mode"),
        createdAt = json.optString("created_at"),
    )
}

private fun parseHistoryPoint(json: JSONObject): HistoryPoint {
    return HistoryPoint(
        id = json.optInt("id"),
        tpm = json.optInt("tpm"),
        volumeSisa = json.optInt("volume_sisa"),
        persen = json.optInt("persen"),
        estimasiJam = json.optInt("estimasi_jam"),
        estimasiMnt = json.optInt("estimasi_mnt"),
        nurseCall = json.optInt("nurse_call"),
        mode = json.optString("mode"),
        createdAt = json.optString("created_at"),
    )
}
