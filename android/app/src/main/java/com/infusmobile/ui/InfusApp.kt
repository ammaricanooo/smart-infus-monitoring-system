package com.infusmobile.ui

import android.net.Uri
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
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
private fun DeviceListScreen(onDeviceSelected: (Device) -> Unit) {
    val state = remember { mutableStateOf<UiState<List<Device>>>(UiState.Loading) }
    val scope = rememberCoroutineScope()

    LaunchedEffect(Unit) {
        scope.launch {
            state.value = UiState.Loading
            state.value = fetchLatestDevices().fold(
                onSuccess = { UiState.Success(it) },
                onFailure = { UiState.Error(it.message ?: "Gagal memuat data") }
            )
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(title = { Text("Infus Mobile Dashboard") })
        }
    ) { padding ->
        Surface(modifier = Modifier.padding(padding)) {
            when (val current = state.value) {
                is UiState.Loading -> CenteredProgress()
                is UiState.Error -> ErrorContent(message = current.message) {
                    scope.launch {
                        state.value = UiState.Loading
                        state.value = fetchLatestDevices().fold(
                            onSuccess = { UiState.Success(it) },
                            onFailure = { UiState.Error(it.message ?: "Gagal memuat data") }
                        )
                    }
                }
                is UiState.Success -> DeviceListContent(current.data, onDeviceSelected)
            }
        }
    }
}

@Composable
private fun DeviceListContent(devices: List<Device>, onDeviceSelected: (Device) -> Unit) {
    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        item {
            Text(
                text = "Perangkat Aktif",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold
            )
            Spacer(modifier = Modifier.padding(8.dp))
        }
        items(devices) { device ->
            DeviceCard(device = device, onClick = { onDeviceSelected(device) })
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DeviceCard(device: Device, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        border = BorderStroke(2.dp, MaterialTheme.colorScheme.primary)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(horizontalArrangement = Arrangement.SpaceBetween, modifier = Modifier.fillMaxWidth()) {
                Text(device.nama, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Text(device.deviceId, style = MaterialTheme.typography.bodyMedium)
            }
            Spacer(modifier = Modifier.padding(4.dp))
            Text("Pasien: ${device.pasien}", style = MaterialTheme.typography.bodyMedium)
            Text("Lokasi: ${device.lokasi}", style = MaterialTheme.typography.bodyMedium)
            Spacer(modifier = Modifier.padding(8.dp))
            Text("Volume sisa: ${device.volumeSisa} ml", style = MaterialTheme.typography.bodyMedium)
            Text("Persen: ${device.persen}%", style = MaterialTheme.typography.bodyMedium)
            Text("Mode: ${device.mode}", style = MaterialTheme.typography.bodyMedium)
            if (device.nurseCall == 1) {
                Spacer(modifier = Modifier.padding(4.dp))
                Text("Nurse Call aktif", color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun DeviceDetailScreen(deviceId: String, deviceName: String, onBack: () -> Unit) {
    var detailState by remember { mutableStateOf<UiState<Device?>>(UiState.Loading) }
    var historyState by remember { mutableStateOf<UiState<List<HistoryPoint>>>(UiState.Loading) }
    val scope = rememberCoroutineScope()

    LaunchedEffect(deviceId) {
        scope.launch {
            detailState = fetchDeviceDetail(deviceId).fold(
                onSuccess = { UiState.Success(it) },
                onFailure = { UiState.Error(it.message ?: "Gagal memuat detail") }
            )
            historyState = fetchDeviceHistory(deviceId).fold(
                onSuccess = { UiState.Success(it) },
                onFailure = { UiState.Error(it.message ?: "Gagal memuat riwayat") }
            )
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(deviceName) },
                navigationIcon = {
                    Button(onClick = onBack) { Text("Kembali") }
                }
            )
        }
    ) { padding ->
        Surface(modifier = Modifier.padding(padding)) {
            Column(modifier = Modifier.padding(16.dp)) {
                when (val state = detailState) {
                    is UiState.Loading -> CenteredProgress()
                    is UiState.Error -> ErrorContent(state.message) { onBack() }
                    is UiState.Success -> {
                        state.data?.let { device ->
                            Text("Status terakhir", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                            Spacer(modifier = Modifier.padding(8.dp))
                            DeviceCard(device = device, onClick = {})
                        } ?: Text("Tidak ada data terbaru untuk perangkat ini.")
                    }
                }

                Spacer(modifier = Modifier.padding(12.dp))
                Text("Riwayat terakhir", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Spacer(modifier = Modifier.padding(8.dp))

                when (val state = historyState) {
                    is UiState.Loading -> CenteredProgress()
                    is UiState.Error -> ErrorContent(state.message) {
                        scope.launch {
                            historyState = UiState.Loading
                            historyState = fetchDeviceHistory(deviceId).fold(
                                onSuccess = { UiState.Success(it) },
                                onFailure = { UiState.Error(it.message ?: "Gagal memuat riwayat") }
                            )
                        }
                    }
                    is UiState.Success -> HistoryList(state.data)
                }
            }
        }
    }
}

@Composable
private fun HistoryList(history: List<HistoryPoint>) {
    if (history.isEmpty()) {
        Text("Riwayat kosong", style = MaterialTheme.typography.bodyMedium)
        return
    }

    LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        items(history) { entry ->
            Card(border = BorderStroke(1.dp, MaterialTheme.colorScheme.primary)) {
                Column(modifier = Modifier.padding(14.dp)) {
                    Text(entry.createdAt, style = MaterialTheme.typography.bodySmall)
                    Spacer(modifier = Modifier.padding(4.dp))
                    Text("Persen: ${entry.persen}%", style = MaterialTheme.typography.bodyMedium)
                    Text("Volume sisa: ${entry.volumeSisa} ml", style = MaterialTheme.typography.bodyMedium)
                    Text("TPM: ${entry.tpm}", style = MaterialTheme.typography.bodyMedium)
                    Text("Mode: ${entry.mode}", style = MaterialTheme.typography.bodyMedium)
                    if (entry.nurseCall == 1) {
                        Spacer(modifier = Modifier.padding(4.dp))
                        Text("Nurse call aktif", color = MaterialTheme.colorScheme.error)
                    }
                }
            }
        }
    }
}

@Composable
private fun CenteredProgress() {
    Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator()
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
        Text(message, style = MaterialTheme.typography.bodyLarge)
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
