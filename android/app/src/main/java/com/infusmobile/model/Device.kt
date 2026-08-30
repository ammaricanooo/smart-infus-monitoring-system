package com.infusmobile.model

data class Device(
    val deviceId: String,
    val nama: String,
    val lokasi: String,
    val pasien: String,
    val targetTpm: Int = 20,
    val tpmTolerance: Int = 5,
    val tpm: Int,
    val volumeSisa: Int,
    val volumeAwal: Int,
    val persen: Int,
    val estimasiJam: Int,
    val estimasiMnt: Int,
    val totalTetes: Int,
    val nurseCall: Int,
    val mode: String,
    val createdAt: String,
    val isOnline: Boolean,
)
