// =====================================================
// DETAIL PAGE — CHART & AUTO REFRESH (REALTIME TAILWIND)
// =====================================================

// ===== CHART UTAMA (TPM + Volume) =====
const ctxMain = document.getElementById('chartMain').getContext('2d');

const mainChart = new Chart(ctxMain, {
  type: 'line',
  data: {
    labels: chartLabels,
    datasets: [
      {
        label: 'TPM (Tetes/Menit)',
        data: chartTPM,
        borderColor: '#dc2626',
        backgroundColor: 'rgba(220,38,38,.04)',
        borderWidth: 2.5,
        pointRadius: 3,
        pointBackgroundColor: '#dc2626',
        pointBorderColor: '#fff',
        pointBorderWidth: 1.5,
        tension: 0.3,
        fill: true,
        yAxisID: 'yTPM',
      },
      {
        label: 'Volume Sisa (ml)',
        data: chartVolume,
        borderColor: '#059669',
        backgroundColor: 'rgba(5,150,105,.04)',
        borderWidth: 2.5,
        pointRadius: 3,
        pointBackgroundColor: '#059669',
        pointBorderColor: '#fff',
        pointBorderWidth: 1.5,
        tension: 0.3,
        fill: true,
        yAxisID: 'yVol',
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        display: false // Kita sembunyikan karena sudah diwakili oleh badge indikator HTML di atas canvas
      },
      tooltip: {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleColor: '#fff',
        bodyColor: '#cbd5e1',
        borderColor: '#e2e8f0',
        borderWidth: 1,
        padding: 12,
        titleFont: { size: 12, weight: 'bold', family: 'Plus Jakarta Sans' },
        bodyFont: { size: 11, family: 'Plus Jakarta Sans' },
        displayColors: true,
        callbacks: {
          title: function(context) {
            return 'Waktu: ' + context[0].label;
          },
        },
      },
    },
    scales: {
      x: {
        bounds: 'data',
        ticks: { 
          maxTicksLimit: 10, 
          font: { size: 10, family: 'Plus Jakarta Sans' }, 
          color: '#94a3b8' 
        },
        grid: { color: 'rgba(241,245,249,0.8)' },
      },
      yTPM: {
        type: 'linear',
        position: 'left',
        grid: { color: 'rgba(241,245,249,0.8)' },
        ticks: { 
          font: { size: 10, family: 'Plus Jakarta Sans', weight: 'bold' }, 
          color: '#dc2626',
          mirror: true, // BUAT ANGKA SUMBU Y MASUK KE DALAM GRAFIK AGAR HEMAT RUANG
          z: 10        // Memastikan angka berada di atas garis grafik
        },
      },
      yVol: {
        type: 'linear',
        position: 'right',
        grid: { drawOnChartArea: false },
        ticks: { 
          font: { size: 10, family: 'Plus Jakarta Sans', weight: 'bold' }, 
          color: '#059669',
          mirror: true,
          z: 10
        },
      },
    },
  },
});

// ===== FORMAT WAKTU =====
function formatTime(dateStr) {
  if (!dateStr) return '--:--:--';
  const d  = new Date(dateStr);
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${hh}:${mi}:${ss}`;
}

// ===== CEK ONLINE (30 detik) =====
function isOnline(dateStr) {
  if (!dateStr) return false;
  return (Date.now() - new Date(dateStr).getTime()) / 1000 < 30;
}

// ===== CACHE DATA HISTORY UNTUK EXPORT =====
let cachedHistory = [];

// ===== AUTO REFRESH (REALTIME) =====
async function refreshDetail() {
  try {
    const res  = await fetch(`api/get_latest.php?device_id=${encodeURIComponent(deviceId)}&_=${Date.now()}`, { cache: 'no-store' });
    const json = await res.json();

    if (json.status !== 'ok' || !json.data) return;

    const dev = json.data;

    const persen     = parseFloat(dev.persen      || 0);
    const volumeSisa = parseFloat(dev.volume_sisa  || 0);
    const volumeAwal = parseFloat(dev.volume_awal  || 500);
    const tpm        = parseFloat(dev.tpm          || 0);
    const estJam     = parseInt(dev.estimasi_jam   || 0);
    const estMnt     = parseInt(dev.estimasi_mnt   || 0);
    const nurseCall  = parseInt(dev.nurse_call     || 0);
    const lastUpdate = dev.created_at || null;
    const online     = isOnline(lastUpdate);

    // --- 1. Update Stat Cards Text ---
    const dTpm = document.getElementById('d-tpm');
    const dVol = document.getElementById('d-volume');
    const dEst = document.getElementById('d-estimasi');

    if (dTpm) dTpm.textContent = Math.round(tpm);
    if (dVol) dVol.innerHTML = Math.round(volumeSisa); // Angka utama sisa mili
    if (dEst) dEst.textContent = `${estJam}j ${estMnt}m`;

    // --- 2. Update Bottle Visual Fluid & Color Level ---
    const bottleFluid = document.getElementById('d-bottle-fluid');
    if (bottleFluid) {
      bottleFluid.style.height = Math.min(100, Math.max(0, persen)) + '%';
      
      // Reset Class Animasi & Atur Gradasi Warna Berdasarkan Persentase Infus
      bottleFluid.className = "absolute bottom-0 inset-x-0 transition-all duration-1000 ease-in-out";
      if (persen <= 20) {
        bottleFluid.classList.add('animate-fluid-blink');
        bottleFluid.style.background = 'linear-gradient(to top, #dc2626, #f87171)'; // Merah kritis
      } else if (persen <= 50) {
        bottleFluid.style.background = 'linear-gradient(to top, #d97706, #f59e0b)'; // Oranye peringatan
      } else {
        bottleFluid.style.background = 'linear-gradient(to top, #6b2072, #a855f7)'; // Ungu normal medis
      }
    }

    // --- 3. Update Persen Text ---
    const persenText = document.getElementById('d-persen-text');
    if (persenText) persenText.textContent = persen.toFixed(0) + '%';

    // --- 4. Update Header Layout (Emergency Alert State) ---
    const headerCard = document.getElementById('detail-header-card');
    if (headerCard) {
      if (nurseCall === 1) {
        headerCard.className = "bg-white border border-red-200 rounded-2xl p-6 shadow-sm mb-6 transition-all duration-500 bg-red-50/20 ring-4 ring-red-500/5";
      } else {
        headerCard.className = "bg-white border border-slate-100 rounded-2xl p-6 shadow-sm mb-6 transition-all duration-500";
      }
    }

    // --- 5. Update Badges (Online, Mode, Nurse Call) ---
    const onlineBadge = document.getElementById('d-online-badge');
    if (onlineBadge) {
      if (online) {
        onlineBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black tracking-wider border bg-emerald-50 text-emerald-700 border-emerald-200';
        onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>CONNECTED';
      } else {
        onlineBadge.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black tracking-wider border bg-slate-100 text-slate-500 border-slate-200';
        onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>OFFLINE';
      }
    }

    const dNurseBadge = document.getElementById('d-nurse-badge');
    if (dNurseBadge) {
      if (nurseCall === 1) dNurseBadge.classList.remove('hidden');
      else dNurseBadge.classList.add('hidden');
    }

    // --- 6. Update Last Update Indicator ---
    const lastEl = document.getElementById('d-last-update');
    if (lastEl) {
      lastEl.innerHTML = `<i class="bi bi-clock-history mr-1"></i>Update Terakhir: ${formatTime(lastUpdate)}`;
    }

    // --- 7. Update Nurse Call Card Status ---
    const nurseCard    = document.getElementById('d-nurse-card');
    const nurseIconBox = document.getElementById('d-nurse-icon-box');
    const nurseStatus  = document.getElementById('d-nurse-status');
    const nurseHint    = document.getElementById('d-nurse-hint');

    if (nurseCard && nurseStatus) {
      if (nurseCall === 1) {
        nurseCard.className = 'bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all duration-300 bg-red-50 border-red-200';
        if (nurseIconBox) nurseIconBox.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-base border bg-red-500 text-white border-red-600 animate-bounce';
        nurseStatus.className = 'text-xl font-black tracking-tight text-red-600';
        nurseStatus.textContent = 'EMERGENCY ALERT';
        if (nurseHint) nurseHint.classList.remove('hidden');
      } else {
        nurseCard.className = 'bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all duration-300';
        if (nurseIconBox) nurseIconBox.className = 'w-10 h-10 rounded-xl flex items-center justify-center text-base border bg-slate-50 text-slate-400 border-slate-100';
        nurseStatus.className = 'text-xl font-black tracking-tight text-slate-400';
        nurseStatus.textContent = 'STANDBY NORMAL';
        if (nurseHint) nurseHint.classList.add('hidden');
      }
    }

    // --- 8. Fetch Realtime History for Chart Refresh (50 Data) ---
    const histRes  = await fetch(`api/get_history.php?device_id=${encodeURIComponent(deviceId)}&limit=50&_=${Date.now()}`, { cache: 'no-store' });
    const histJson = await histRes.json();

    if (histJson.status === 'ok' && histJson.data.length > 0) {
      cachedHistory = histJson.data;

      const labels = histJson.data.map(h => formatTime(h.created_at));
      const tpmArr = histJson.data.map(h => parseFloat(h.tpm));
      const volArr = histJson.data.map(h => parseFloat(h.volume_sisa));

      mainChart.data.labels           = labels;
      mainChart.data.datasets[0].data = tpmArr;
      mainChart.data.datasets[1].data = volArr;
      mainChart.update('none');
    }

  } catch (e) {
    console.warn('Refresh detail gagal:', e);
  }
}

// ===== UPDATE LOG TABEL (max 10 data terbaru) =====
function updateLogTable(data) {
  const tbody = document.getElementById('log-tbody');
  if (!tbody) return;

  const reversed = [...data].reverse().slice(0, 10);

  tbody.innerHTML = reversed.map(h => {
    const persen = parseFloat(h.persen || 0);
    const barColor = persen > 50 ? '#6b2072' : (persen > 20 ? '#f59e0b' : '#ef4444');
    return `
      <tr class="hover:bg-slate-50/50 transition-colors">
        <td class="p-4 pl-6 text-xs font-bold text-slate-500 font-mono tabular-nums">${formatTime(h.created_at)}</td>
        <td class="p-4">
          <span class="text-sm font-black text-slate-900 font-mono">${Math.round(h.tpm)}</span>
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide ml-0.5">TPM</span>
        </td>
        <td class="p-4">
          <span class="text-sm font-black text-slate-900 font-mono">${Math.round(h.volume_sisa)}</span>
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide ml-0.5">ml</span>
        </td>
        <td class="p-4 pr-6 text-right">
          <div class="inline-flex items-center gap-3">
            <div class="w-20 h-1.5 bg-slate-100 border border-slate-200/40 rounded-full overflow-hidden hidden sm:block">
              <div class="h-full rounded-full transition-all duration-500" style="width:${Math.min(100, persen)}%; background:${barColor};"></div>
            </div>
            <span class="text-xs font-black text-slate-900 font-mono min-w-[35px] text-right">${persen.toFixed(0)}%</span>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// ===== EXPORT CSV =====
function exportCSV() {
  if (cachedHistory.length === 0) {
    alert('Belum ada data untuk diekspor.');
    return;
  }

  const headers = ['Waktu', 'TPM', 'Volume Sisa (ml)', 'Persen (%)', 'Estimasi Jam', 'Estimasi Menit', 'Nurse Call'];
  const rows = [...cachedHistory].reverse().map(h => [
    h.created_at,
    h.tpm,
    h.volume_sisa,
    parseFloat(h.persen).toFixed(1),
    h.estimasi_jam,
    h.estimasi_mnt,
    h.nurse_call ? 'Ya' : 'Tidak',
  ]);

  const csvContent = [headers, ...rows]
    .map(row => row.map(v => `"${String(v).replace(/"/g, '""')}"`).join(','))
    .join('\n');

  const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `infus_${deviceId}_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

// Realtime loop interval utama (per 5 detik)
setInterval(refreshDetail, 5000);

// ===== REFRESH LOG TABEL — setiap 5 menit (dioptimalkan dari 10 mnt agar sinkron) =====
async function refreshLogTable() {
  try {
    const res  = await fetch(`api/get_history.php?device_id=${encodeURIComponent(deviceId)}&limit=10&_=${Date.now()}`, { cache: 'no-store' });
    const json = await res.json();
    if (json.status === 'ok' && json.data.length > 0) {
      cachedHistory = json.data;
      updateLogTable(json.data);
    }
  } catch (e) {
    console.warn('Refresh log tabel gagal:', e);
  }
}

setInterval(refreshLogTable, 300000); // 5 Menit
refreshLogTable(); // Panggilan pertama pasca halaman dimuat