package com.infusmobile.ui

import android.net.Uri
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.infusmobile.data.fetchDeviceDetail
import com.infusmobile.data.fetchDeviceHistory
import com.infusmobile.data.fetchLatestDevices
import com.infusmobile.model.Device
import com.infusmobile.model.HistoryPoint
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay

object InfusColors {
    val Blue900 = Color(0xFF1E3A8A)
    val Blue700 = Color(0xFF1D4ED8)
    val Blue500 = Color(0xFF3B82F6)
    val Blue50 = Color(0xFFEFF6FF)
    val Slate900 = Color(0xFF0F172A)
    val Slate700 = Color(0xFF334155)
    val Slate500 = Color(0xFF64748B)
    val Slate300 = Color(0xFFCBD5E1)
    val Slate100 = Color(0xFFF1F5F9)
    val Slate50 = Color(0xFFF8FAFC)
    val SuccessGreen = Color(0xFF16A34A)
    val SuccessBg = Color(0xFFDCFCE7)
    val WarningOrange = Color(0xFFD97706)
    val WarningBg = Color(0xFFFEF3C7)
    val ErrorRed = Color(0xFFDC2626)
    val ErrorBg = Color(0xFFFEE2E2)
}

sealed class Screen(val route: String) {
    object DeviceList : Screen("device_list")
    object DeviceDetail : Screen("device_detail/{deviceId}/{deviceName}") {
        fun createRoute(deviceId: String, deviceName: String) =
            "device_detail/${Uri.encode(deviceId)}/${Uri.encode(deviceName)}"
    }
}

@Composable
fun InfusApp() {
    val navController = rememberNavController()

    NavHost(navController = navController, startDestination = Screen.DeviceList.route) {
        composable(Screen.DeviceList.route) {
            DeviceListScreen(onDeviceSelected = { device ->
                navController.navigate(Screen.DeviceDetail.createRoute(device.deviceId, device.nama))
            })
        }
        composable(Screen.DeviceDetail.route) { backStackEntry ->
            val deviceId = backStackEntry.arguments?.getString("deviceId") ?: ""
            val deviceName = backStackEntry.arguments?.getString("deviceName") ?: "Detail"
            DeviceDetailScreen(deviceId = deviceId, deviceName = deviceName, onBack = {
                navController.popBackStack()
            })
        }
    }
}

@Composable
fun rememberInfiniteTransitionAlpha(initialValue: Float = 0.3f, duration: Int = 1000): Float {
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val alpha by infiniteTransition.animateFloat(
        initialValue = initialValue,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(duration, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "alpha"
    )
    return alpha
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ModernTopAppBar(
    title: String,
    navigationIcon: @Composable (() -> Unit)? = null
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                brush = Brush.horizontalGradient(
                    colors = listOf(InfusColors.Blue900, InfusColors.Blue700)
                )
            )
    ) {
        TopAppBar(
            title = {
                Text(
                    text = title,
                    color = Color.White,
                    fontWeight = FontWeight.Bold,
                    style = MaterialTheme.typography.titleLarge
                )
            },
            navigationIcon = {
                navigationIcon?.invoke()
            },
            colors = TopAppBarDefaults.topAppBarColors(
                containerColor = Color.Transparent,
                navigationIconContentColor = Color.White,
                titleContentColor = Color.White,
                actionIconContentColor = Color.White
            ),
            modifier = Modifier.statusBarsPadding()
        )
    }
}

@Composable
private fun DeviceListScreen(onDeviceSelected: (Device) -> Unit) {
    val state = remember { mutableStateOf<UiState<List<Device>>>(UiState.Loading) }

    LaunchedEffect(Unit) {
        while (true) {
            val result = fetchLatestDevices().fold(
                onSuccess = { UiState.Success(it) },
                onFailure = {
                    val currentVal = state.value
                    if (currentVal is UiState.Success) currentVal else UiState.Error(it.message ?: "Gagal memuat data")
                }
            )
            state.value = result
            delay(5000)
        }
    }

    Scaffold(
        topBar = {
            ModernTopAppBar(title = "Infus Mobile Dashboard")
        }
    ) { padding ->
        Surface(
            modifier = Modifier.padding(padding),
            color = InfusColors.Slate50
        ) {
            when (val current = state.value) {
                is UiState.Loading -> CenteredProgress()
                is UiState.Error -> ErrorContent(message = current.message) {
                    state.value = UiState.Loading
                }
                is UiState.Success -> DeviceListContent(current.data, onDeviceSelected)
            }
        }
    }
}

@Composable
private fun DeviceListContent(devices: List<Device>, onDeviceSelected: (Device) -> Unit) {
    val total = devices.size
    val online = devices.count { it.isOnline }
    val lowVol = devices.count { it.isOnline && it.persen <= 20 }
    val nurseCall = devices.count { it.isOnline && it.nurseCall == 1 }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        contentPadding = PaddingValues(bottom = 24.dp)
    ) {
        item {
            DashboardStats(total = total, online = online, lowVol = lowVol, nurseCall = nurseCall)
        }
        item {
            Column(modifier = Modifier.padding(horizontal = 16.dp)) {
                Text(
                    text = "Daftar Perangkat Aktif",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = InfusColors.Slate900
                )
                Spacer(modifier = Modifier.height(2.dp))
                Text(
                    text = "Realtime update setiap 5 detik",
                    style = MaterialTheme.typography.bodySmall,
                    color = InfusColors.Slate500
                )
            }
        }
        if (devices.isEmpty()) {
            item {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(32.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Tidak ada perangkat aktif",
                        style = MaterialTheme.typography.bodyLarge,
                        color = InfusColors.Slate500
                    )
                }
            }
        } else {
            items(devices, key = { it.deviceId }) { device ->
                Box(modifier = Modifier.padding(horizontal = 16.dp)) {
                    DeviceCard(device = device, onClick = { onDeviceSelected(device) })
                }
            }
        }
    }
}

@Composable
private fun DashboardStats(
    total: Int,
    online: Int,
    lowVol: Int,
    nurseCall: Int
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .horizontalScroll(rememberScrollState())
            .padding(horizontal = 16.dp, vertical = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        StatCard(
            title = "Total Device",
            value = total.toString(),
            bgColor = InfusColors.Blue50,
            textColor = InfusColors.Blue900
        )
        StatCard(
            title = "Device Online",
            value = online.toString(),
            bgColor = InfusColors.SuccessBg,
            textColor = InfusColors.SuccessGreen
        )
        StatCard(
            title = "Volume Rendah",
            value = lowVol.toString(),
            bgColor = InfusColors.WarningBg,
            textColor = InfusColors.WarningOrange
        )
        StatCard(
            title = "Nurse Call",
            value = nurseCall.toString(),
            bgColor = InfusColors.ErrorBg,
            textColor = InfusColors.ErrorRed
        )
    }
}

@Composable
private fun StatCard(
    title: String,
    value: String,
    bgColor: Color,
    textColor: Color,
    modifier: Modifier = Modifier
) {
    Card(
        colors = CardDefaults.cardColors(containerColor = bgColor),
        shape = RoundedCornerShape(12.dp),
        modifier = modifier
            .width(130.dp)
            .padding(vertical = 2.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.bodySmall,
                color = textColor.copy(alpha = 0.8f),
                fontWeight = FontWeight.Medium,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = value,
                style = MaterialTheme.typography.titleLarge,
                color = textColor,
                fontWeight = FontWeight.Bold
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DeviceCard(device: Device, onClick: () -> Unit) {
    val pulseAlpha = rememberInfiniteTransitionAlpha(initialValue = 0.4f, duration = 800)
    val isLowVolume = device.isOnline && device.persen <= 20
    val isNurseCallActive = device.isOnline && device.nurseCall == 1

    val borderStroke = when {
        isNurseCallActive -> BorderStroke(2.dp, InfusColors.ErrorRed.copy(alpha = pulseAlpha))
        isLowVolume -> BorderStroke(1.5.dp, InfusColors.WarningOrange.copy(alpha = pulseAlpha))
        else -> BorderStroke(1.dp, InfusColors.Slate300)
    }

    val cardBgColor = when {
        isNurseCallActive -> InfusColors.ErrorBg.copy(alpha = 0.15f)
        isLowVolume -> InfusColors.WarningBg.copy(alpha = 0.15f)
        else -> Color.White
    }

    Card(
        onClick = onClick,
        shape = RoundedCornerShape(14.dp),
        border = borderStroke,
        colors = CardDefaults.cardColors(containerColor = cardBgColor),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = device.nama,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = InfusColors.Slate900
                )

                val statusText = if (device.isOnline) "Online" else "Offline"
                val badgeBg = if (device.isOnline) InfusColors.SuccessBg else InfusColors.Slate100
                val badgeTextColor = if (device.isOnline) InfusColors.SuccessGreen else InfusColors.Slate500

                Surface(
                    color = badgeBg,
                    shape = RoundedCornerShape(20.dp)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(6.dp)
                                .background(badgeTextColor, CircleShape)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = statusText,
                            style = MaterialTheme.typography.bodySmall,
                            color = badgeTextColor,
                            fontWeight = FontWeight.SemiBold
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(10.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Pasien: ${device.pasien.ifEmpty { "Tidak ada pasien" }}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = InfusColors.Slate700,
                        fontWeight = FontWeight.Medium
                    )
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = "Lokasi: ${device.lokasi}",
                        style = MaterialTheme.typography.bodySmall,
                        color = InfusColors.Slate500
                    )
                }

                Surface(
                    color = InfusColors.Blue50,
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text(
                        text = device.mode.uppercase(),
                        color = InfusColors.Blue700,
                        style = MaterialTheme.typography.bodySmall,
                        fontWeight = FontWeight.Bold,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            val animatedProgress by animateFloatAsState(
                targetValue = (device.persen / 100f).coerceIn(0f, 1f),
                label = "progress"
            )

            val progressBarColor = when {
                isLowVolume -> InfusColors.WarningOrange
                else -> InfusColors.Blue500
            }

            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            text = "Volume Infus Sisa",
                            style = MaterialTheme.typography.bodySmall,
                            color = InfusColors.Slate500,
                            fontWeight = FontWeight.Medium
                        )
                        if (isLowVolume) {
                            Spacer(modifier = Modifier.width(6.dp))
                            Box(
                                modifier = Modifier
                                    .background(InfusColors.ErrorBg, RoundedCornerShape(4.dp))
                                    .padding(horizontal = 6.dp, vertical = 2.dp)
                                    .alpha(pulseAlpha)
                            ) {
                                Text(
                                    text = "SISA RENDAH",
                                    color = InfusColors.ErrorRed,
                                    style = MaterialTheme.typography.bodySmall,
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 9.sp
                                )
                            }
                        }
                    }
                    Text(
                        text = "${device.persen}% (${device.volumeSisa}/${device.volumeAwal} ml)",
                        style = MaterialTheme.typography.bodySmall,
                        fontWeight = FontWeight.Bold,
                        color = if (isLowVolume) InfusColors.ErrorRed else InfusColors.Slate900
                    )
                }

                LinearProgressIndicator(
                    progress = animatedProgress,
                    color = progressBarColor,
                    trackColor = InfusColors.Slate100,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(8.dp)
                )
            }

            Spacer(modifier = Modifier.height(14.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f), horizontalAlignment = Alignment.Start) {
                    Text(
                        text = "KECEPATAN",
                        style = MaterialTheme.typography.bodySmall,
                        color = InfusColors.Slate500,
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = "${device.tpm} TPM",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = InfusColors.Slate900
                    )
                }

                Column(modifier = Modifier.weight(1.2f), horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "ESTIMASI SISA",
                        style = MaterialTheme.typography.bodySmall,
                        color = InfusColors.Slate500,
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(2.dp))
                    val estimasiStr = if (device.estimasiJam == 0 && device.estimasiMnt == 0) {
                        "Selesai"
                    } else {
                        "${device.estimasiJam}j ${device.estimasiMnt}m"
                    }
                    Text(
                        text = estimasiStr,
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = InfusColors.Slate900
                    )
                }

                Column(modifier = Modifier.weight(1f), horizontalAlignment = Alignment.End) {
                    Text(
                        text = "TOTAL TETES",
                        style = MaterialTheme.typography.bodySmall,
                        color = InfusColors.Slate500,
                        fontSize = 9.sp,
                        fontWeight = FontWeight.Bold
                    )
                    Spacer(modifier = Modifier.height(2.dp))
                    Text(
                        text = "${device.totalTetes}",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = InfusColors.Slate900
                    )
                }
            }

            if (isNurseCallActive) {
                Spacer(modifier = Modifier.height(12.dp))
                Surface(
                    color = InfusColors.ErrorBg,
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, InfusColors.ErrorRed.copy(alpha = pulseAlpha)),
                    modifier = Modifier
                        .fillMaxWidth()
                        .alpha(pulseAlpha)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(10.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Notifications,
                            contentDescription = "Nurse Call",
                            tint = InfusColors.ErrorRed,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "PANGGILAN DARURAT (NURSE CALL) AKTIF",
                            style = MaterialTheme.typography.bodySmall,
                            color = InfusColors.ErrorRed,
                            fontWeight = FontWeight.Bold,
                            fontSize = 10.sp
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun DeviceDetailScreen(deviceId: String, deviceName: String, onBack: () -> Unit) {
    var detailState by remember { mutableStateOf<UiState<Device?>>(UiState.Loading) }
    var historyState by remember { mutableStateOf<UiState<List<HistoryPoint>>>(UiState.Loading) }

    LaunchedEffect(deviceId) {
        while (true) {
            detailState = fetchDeviceDetail(deviceId).fold(
                onSuccess = { UiState.Success(it) },
                onFailure = {
                    val current = detailState
                    if (current is UiState.Success) current else UiState.Error(it.message ?: "Gagal memuat detail")
                }
            )
            historyState = fetchDeviceHistory(deviceId).fold(
                onSuccess = { UiState.Success(it) },
                onFailure = {
                    val current = historyState
                    if (current is UiState.Success) current else UiState.Error(it.message ?: "Gagal memuat riwayat")
                }
            )
            delay(5000)
        }
    }

    Scaffold(
        topBar = {
            ModernTopAppBar(
                title = deviceName,
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(
                            imageVector = Icons.Default.ArrowBack,
                            contentDescription = "Kembali",
                            tint = Color.White
                        )
                    }
                }
            )
        }
    ) { padding ->
        Surface(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            color = InfusColors.Slate50
        ) {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                verticalArrangement = Arrangement.spacedBy(16.dp),
                contentPadding = PaddingValues(16.dp)
            ) {
                item {
                    when (val state = detailState) {
                        is UiState.Loading -> Box(modifier = Modifier.fillMaxWidth().height(100.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                        is UiState.Error -> Text(state.message, color = InfusColors.ErrorRed, modifier = Modifier.padding(16.dp))
                        is UiState.Success -> {
                            state.data?.let { device ->
                                DetailHeaderCard(device = device)
                            } ?: Text("Perangkat tidak ditemukan", modifier = Modifier.padding(16.dp))
                        }
                    }
                }

                item {
                    when (val state = detailState) {
                        is UiState.Success -> {
                            state.data?.let { device ->
                                DetailMetricGrid(device = device)
                            }
                        }
                        else -> {}
                    }
                }

                item {
                    Card(
                        colors = CardDefaults.cardColors(containerColor = Color.White),
                        shape = RoundedCornerShape(12.dp),
                        border = BorderStroke(1.dp, InfusColors.Slate300),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Text(
                                text = "Grafik Realtime (TPM & Volume)",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = InfusColors.Slate900
                            )
                            Spacer(modifier = Modifier.height(2.dp))
                            Text(
                                text = "Visualisasi data real-time",
                                style = MaterialTheme.typography.bodySmall,
                                color = InfusColors.Slate500
                            )
                            Spacer(modifier = Modifier.height(16.dp))

                            when (val state = historyState) {
                                is UiState.Loading -> Box(modifier = Modifier.fillMaxWidth().height(160.dp), contentAlignment = Alignment.Center) { CircularProgressIndicator() }
                                is UiState.Error -> Text(state.message, color = InfusColors.ErrorRed)
                                is UiState.Success -> {
                                    if (state.data.isEmpty()) {
                                        Box(modifier = Modifier.fillMaxWidth().height(160.dp), contentAlignment = Alignment.Center) {
                                            Text("Belum ada riwayat data", style = MaterialTheme.typography.bodyMedium, color = InfusColors.Slate500)
                                        }
                                    } else {
                                        HistoryChart(history = state.data)
                                    }
                                }
                            }
                        }
                    }
                }

                item {
                    Text(
                        text = "Riwayat Pengukuran Terbaru",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = InfusColors.Slate900,
                        modifier = Modifier.padding(top = 8.dp)
                    )
                }

                when (val state = historyState) {
                    is UiState.Success -> {
                        if (state.data.isEmpty()) {
                            item {
                                Text("Riwayat kosong", style = MaterialTheme.typography.bodyMedium, color = InfusColors.Slate500)
                            }
                        } else {
                            val reversedHistory = state.data.reversed()
                            items(reversedHistory) { point ->
                                HistoryRowItem(point = point)
                            }
                        }
                    }
                    is UiState.Loading -> item { Box(modifier = Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) { CircularProgressIndicator() } }
                    is UiState.Error -> item { Text(state.message, color = InfusColors.ErrorRed) }
                }
            }
        }
    }
}

@Composable
private fun DetailHeaderCard(device: Device) {
    val pulseAlpha = rememberInfiniteTransitionAlpha(initialValue = 0.4f, duration = 800)
    Card(
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = RoundedCornerShape(12.dp),
        border = BorderStroke(1.dp, InfusColors.Slate300),
        modifier = Modifier.fillMaxWidth()
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = device.nama,
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                        color = InfusColors.Slate900
                    )
                    Text(
                        text = "ID: ${device.deviceId}",
                        style = MaterialTheme.typography.bodySmall,
                        color = InfusColors.Slate500
                    )
                }

                val statusText = if (device.isOnline) "Online" else "Offline"
                val badgeBg = if (device.isOnline) InfusColors.SuccessBg else InfusColors.Slate100
                val badgeTextColor = if (device.isOnline) InfusColors.SuccessGreen else InfusColors.Slate500

                Surface(color = badgeBg, shape = RoundedCornerShape(20.dp)) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .background(badgeTextColor, CircleShape)
                        )
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = statusText,
                            style = MaterialTheme.typography.bodyMedium,
                            color = badgeTextColor,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(12.dp))
            Spacer(modifier = Modifier.height(1.dp).fillMaxWidth().background(InfusColors.Slate100))
            Spacer(modifier = Modifier.height(12.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text("PASIEN", style = MaterialTheme.typography.bodySmall, color = InfusColors.Slate500, fontWeight = FontWeight.Bold)
                    Text(device.pasien.ifEmpty { "Tidak ada pasien" }, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, color = InfusColors.Slate900)
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("LOKASI", style = MaterialTheme.typography.bodySmall, color = InfusColors.Slate500, fontWeight = FontWeight.Bold)
                    Text(device.lokasi, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold, color = InfusColors.Slate900)
                }
            }

            if (device.isOnline && device.nurseCall == 1) {
                Spacer(modifier = Modifier.height(12.dp))
                Surface(
                    color = InfusColors.ErrorBg,
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, InfusColors.ErrorRed),
                    modifier = Modifier
                        .fillMaxWidth()
                        .alpha(pulseAlpha)
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.padding(12.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Notifications,
                            contentDescription = "Nurse Call",
                            tint = InfusColors.ErrorRed,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(10.dp))
                        Text(
                            text = "NURSE CALL AKTIF! PANGGILAN DARURAT",
                            style = MaterialTheme.typography.bodyMedium,
                            color = InfusColors.ErrorRed,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun DetailMetricGrid(device: Device) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard(
                title = "TPM (KECEPATAN)",
                value = "${device.tpm} TPM",
                iconColor = InfusColors.ErrorRed,
                bgColor = InfusColors.ErrorBg,
                modifier = Modifier.weight(1f)
            )
            MetricCard(
                title = "VOLUME SISA",
                value = "${device.volumeSisa} ml",
                iconColor = InfusColors.SuccessGreen,
                bgColor = InfusColors.SuccessBg,
                modifier = Modifier.weight(1f)
            )
        }
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            MetricCard(
                title = "PERSENTASE",
                value = "${device.persen}%",
                iconColor = InfusColors.Blue500,
                bgColor = InfusColors.Blue50,
                modifier = Modifier.weight(1f)
            )
            val estimasiStr = if (device.estimasiJam == 0 && device.estimasiMnt == 0) {
                "Selesai"
            } else {
                "${device.estimasiJam}j ${device.estimasiMnt}m"
            }
            MetricCard(
                title = "ESTIMASI WAKTU",
                value = estimasiStr,
                iconColor = InfusColors.WarningOrange,
                bgColor = InfusColors.WarningBg,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

@Composable
private fun MetricCard(
    title: String,
    value: String,
    iconColor: Color,
    bgColor: Color,
    modifier: Modifier = Modifier
) {
    Card(
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = RoundedCornerShape(12.dp),
        border = BorderStroke(1.dp, InfusColors.Slate300),
        modifier = modifier
    ) {
        Row(
            modifier = Modifier.padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(32.dp)
                    .background(bgColor, CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Box(
                    modifier = Modifier
                        .size(8.dp)
                        .background(iconColor, CircleShape)
                )
            }
            Spacer(modifier = Modifier.width(12.dp))
            Column {
                Text(
                    text = title,
                    style = MaterialTheme.typography.bodySmall,
                    color = InfusColors.Slate500,
                    fontWeight = FontWeight.Bold,
                    fontSize = 9.sp
                )
                Text(
                    text = value,
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = FontWeight.Bold,
                    color = InfusColors.Slate900
                )
            }
        }
    }
}

@Composable
private fun HistoryChart(history: List<HistoryPoint>) {
    val pointsCount = history.size
    if (pointsCount <= 1) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(160.dp),
            contentAlignment = Alignment.Center
        ) {
            Text("Data riwayat tidak cukup", color = InfusColors.Slate500)
        }
        return
    }

    Column {
        Canvas(
            modifier = Modifier
                .fillMaxWidth()
                .height(150.dp)
        ) {
            val width = size.width
            val height = size.height
            val paddingX = 20f
            val paddingY = 20f
            val chartWidth = width - 2 * paddingX
            val chartHeight = height - 2 * paddingY

            val maxTpm = (history.maxOfOrNull { it.tpm } ?: 60).coerceAtLeast(60).toFloat()
            val maxVol = (history.maxOfOrNull { it.volumeSisa } ?: 500).coerceAtLeast(500).toFloat()

            val gridLines = 4
            for (i in 0..gridLines) {
                val y = paddingY + i * (chartHeight / gridLines)
                drawLine(
                    color = InfusColors.Slate100,
                    start = androidx.compose.ui.geometry.Offset(paddingX, y),
                    end = androidx.compose.ui.geometry.Offset(width - paddingX, y),
                    strokeWidth = 1.dp.toPx()
                )
            }

            val tpmPath = Path()
            val volPath = Path()
            val tpmAreaPath = Path()
            val volAreaPath = Path()

            history.forEachIndexed { index, point ->
                val x = paddingX + index * (chartWidth / (pointsCount - 1))

                val tpmRatio = if (maxTpm > 0) point.tpm / maxTpm else 0f
                val yTpm = height - paddingY - tpmRatio * chartHeight
                if (index == 0) {
                    tpmPath.moveTo(x, yTpm)
                    tpmAreaPath.moveTo(x, height - paddingY)
                    tpmAreaPath.lineTo(x, yTpm)
                } else {
                    tpmPath.lineTo(x, yTpm)
                    tpmAreaPath.lineTo(x, yTpm)
                }

                val volRatio = if (maxVol > 0) point.volumeSisa / maxVol else 0f
                val yVol = height - paddingY - volRatio * chartHeight
                if (index == 0) {
                    volPath.moveTo(x, yVol)
                    volAreaPath.moveTo(x, height - paddingY)
                    volAreaPath.lineTo(x, yVol)
                } else {
                    volPath.lineTo(x, yVol)
                    volAreaPath.lineTo(x, yVol)
                }

                if (index == pointsCount - 1) {
                    tpmAreaPath.lineTo(x, height - paddingY)
                    tpmAreaPath.close()
                    volAreaPath.lineTo(x, height - paddingY)
                    volAreaPath.close()
                }
            }

            drawPath(
                path = volAreaPath,
                brush = Brush.verticalGradient(
                    colors = listOf(InfusColors.SuccessBg.copy(alpha = 0.4f), Color.Transparent)
                )
            )

            drawPath(
                path = tpmAreaPath,
                brush = Brush.verticalGradient(
                    colors = listOf(InfusColors.ErrorBg.copy(alpha = 0.4f), Color.Transparent)
                )
            )

            drawPath(
                path = volPath,
                color = InfusColors.SuccessGreen,
                style = Stroke(width = 2.dp.toPx(), join = StrokeJoin.Round)
            )

            drawPath(
                path = tpmPath,
                color = InfusColors.ErrorRed,
                style = Stroke(width = 2.dp.toPx(), join = StrokeJoin.Round)
            )
        }

        Spacer(modifier = Modifier.height(10.dp))

        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(8.dp)
                    .background(InfusColors.ErrorRed, CircleShape)
            )
            Spacer(modifier = Modifier.width(6.dp))
            Text(
                text = "TPM (Tetes/Menit)",
                style = MaterialTheme.typography.bodySmall,
                fontWeight = FontWeight.Medium,
                color = InfusColors.Slate700
            )

            Spacer(modifier = Modifier.width(20.dp))

            Box(
                modifier = Modifier
                    .size(8.dp)
                    .background(InfusColors.SuccessGreen, CircleShape)
            )
            Spacer(modifier = Modifier.width(6.dp))
            Text(
                text = "Volume Sisa (ml)",
                style = MaterialTheme.typography.bodySmall,
                fontWeight = FontWeight.Medium,
                color = InfusColors.Slate700
            )
        }
    }
}

@Composable
private fun HistoryRowItem(point: HistoryPoint) {
    val isLowVolume = point.persen <= 20
    val isNurseCallActive = point.nurseCall == 1

    val borderStroke = when {
        isNurseCallActive -> BorderStroke(1.dp, InfusColors.ErrorRed)
        isLowVolume -> BorderStroke(1.dp, InfusColors.WarningOrange)
        else -> BorderStroke(1.dp, InfusColors.Slate100)
    }

    val cardBg = when {
        isNurseCallActive -> InfusColors.ErrorBg.copy(alpha = 0.05f)
        isLowVolume -> InfusColors.WarningBg.copy(alpha = 0.05f)
        else -> Color.White
    }

    Card(
        shape = RoundedCornerShape(10.dp),
        border = borderStroke,
        colors = CardDefaults.cardColors(containerColor = cardBg),
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 2.dp)
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = point.createdAt,
                    style = MaterialTheme.typography.bodySmall,
                    color = InfusColors.Slate500,
                    fontWeight = FontWeight.Medium
                )

                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Surface(
                        color = InfusColors.Slate100,
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Text(
                            text = point.mode.uppercase(),
                            style = MaterialTheme.typography.bodySmall,
                            color = InfusColors.Slate700,
                            fontWeight = FontWeight.Bold,
                            fontSize = 8.sp,
                            modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                        )
                    }

                    if (isNurseCallActive) {
                        Surface(
                            color = InfusColors.ErrorRed,
                            shape = RoundedCornerShape(6.dp)
                        ) {
                            Text(
                                text = "NURSE CALL",
                                style = MaterialTheme.typography.bodySmall,
                                color = Color.White,
                                fontWeight = FontWeight.Bold,
                                fontSize = 8.sp,
                                modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text("PERSEN", style = MaterialTheme.typography.bodySmall, color = InfusColors.Slate500, fontSize = 8.sp)
                    Text(
                        text = "${point.persen}%",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = if (isLowVolume) InfusColors.ErrorRed else InfusColors.Slate900
                    )
                }
                Column {
                    Text("VOLUME SISA", style = MaterialTheme.typography.bodySmall, color = InfusColors.Slate500, fontSize = 8.sp)
                    Text(
                        text = "${point.volumeSisa} ml",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = if (isLowVolume) InfusColors.ErrorRed else InfusColors.Slate900
                    )
                }
                Column(horizontalAlignment = Alignment.End) {
                    Text("KECEPATAN", style = MaterialTheme.typography.bodySmall, color = InfusColors.Slate500, fontSize = 8.sp)
                    Text(
                        text = "${point.tpm} TPM",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = InfusColors.Slate900
                    )
                }
            }
        }
    }
}

@Composable
private fun CenteredProgress() {
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = InfusColors.Blue900)
    }
}

@Composable
private fun ErrorContent(message: String, onRetry: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.Center,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(message, style = MaterialTheme.typography.bodyLarge, color = InfusColors.Slate700)
        Spacer(modifier = Modifier.padding(8.dp))
        Button(onClick = onRetry) {
            Text("Muat ulang")
        }
    }
}

private sealed class UiState<out T> {
    object Loading : UiState<Nothing>()
    data class Success<T>(val data: T) : UiState<T>()
    data class Error(val message: String) : UiState<Nothing>()
}
