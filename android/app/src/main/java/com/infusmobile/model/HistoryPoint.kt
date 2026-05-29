package com.infusmobile.model

data class HistoryPoint(
    val id: Int,
    val tpm: Int,
    val volumeSisa: Int,
    val persen: Int,
    val estimasiJam: Int,
    val estimasiMnt: Int,
    val nurseCall: Int,
    val mode: String,
    val createdAt: String,
)
