// =====================================================
// DETAIL PAGE — CHART & AUTO REFRESH (REALTIME)
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
        backgroundColor: 'rgba(220,38,38,.08)',
        borderWidth: 2.5,
        pointRadius: 4,
        pointBackgroundColor: '#dc2626',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        tension: 0.3,
        fill: true,
        yAxisID: 'yTPM',
      },
      {
        label: 'Volume Sisa (ml)',
        data: chartVolume,
        borderColor: '#059669',
        backgroundColor: 'rgba(5,150,105,.08)',
        borderWidth: 2.5,
        pointRadius: 4,
        pointBackgroundColor: '#059669',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        tension: 0.3,
        fill: true,
        yAxisID: 'yVol',
      },
    ],
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: {
        position: 'top',
        labels: {
          font: { size: 13, weight: 'bold' },
          usePointStyle: true,
          padding: 15,
          color: '#1e293b',
        },
      },
      tooltip: {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleColor: '#fff',
        bodyColor: '#cbd5e1',
        borderColor: '#64748b',
        borderWidth: 1,
        padding: 12,
        titleFont: { size: 13, weight: 'bold' },
        bodyFont: { size: 12 },
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
        ticks: { maxTicksLimit: 12, font: { size: 11 } },
        grid: { color: 'rgba(0,0,0,.05)' },
      },
      yTPM: {
        type: 'linear',
        position: 'left',
        title: {
          display: true,
          text: 'TPM (Tetes/Menit)',
          font: { size: 12, weight: 'bold' },
          color: '#dc2626',
        },
        grid: { color: 'rgba(0,0,0,.05)' },
        ticks: { font: { size: 11 }, color: '#dc2626' },
      },
      yVol: {
        type: 'linear',
        position: 'right',
        title: {
          display: true,
          text: 'Volume (ml)',
          font: { size: 12, weight: 'bold' },
          color: '#059669',
        },
        grid: { drawOnChartArea: false },
        ticks: { font: { size: 11 }, color: '#059669' },
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
    const tpm        = parseFloat(dev.tpm          || 0);
    const estJam     = parseInt(dev.estimasi_jam   || 0);
    const estMnt     = parseInt(dev.estimasi_mnt   || 0);
    const nurseCall  = parseInt(dev.nurse_call     || 0);
    const lastUpdate = dev.created_at || null;
    const online     = isOnline(lastUpdate);

    // --- update stat cards ---
    const dTpm  = document.getElementById('d-tpm');
    const dVol  = document.getElementById('d-volume');
    const dEst  = document.getElementById('d-estimasi');

    if (dTpm)  dTpm.textContent  = Math.round(tpm);
    if (dVol)  dVol.innerHTML   = `${Math.round(volumeSisa)} <span class="text-sm font-bold text-slate-400">ml</span>`;
    if (dEst)  dEst.textContent  = `${estJam}j ${estMnt}m`;

    // --- update bottle visual ---
    const bottle = document.querySelector('.bottle-fluid');
    if (bottle) {
      bottle.style.height = Math.min(100, Math.max(0, persen)) + '%';
      bottle.className = 'bottle-fluid';
      if (persen <= 20) bottle.classList.add('blink-red');
      else              bottle.style.background = 'linear-gradient(to right, #3b82f6, #60a5fa)';
    }

    // --- update persen text di bottle ---
    const persenText = document.querySelector('.text-4xl.font-black.text-slate-800');
    if (persenText) persenText.textContent = persen.toFixed(0) + '%';

    // --- update online badge ---
    const onlineBadge = document.getElementById('d-online-badge');
    if (onlineBadge) {
      if (online) {
        onlineBadge.className = 'px-4 py-2 bg-emerald-100 text-emerald-700 rounded-xl text-xs font-black uppercase tracking-widest';
        onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full inline-block mr-1 bg-emerald-500"></span>Online';
      } else {
        onlineBadge.className = 'px-4 py-2 bg-slate-100 text-slate-500 rounded-xl text-xs font-black uppercase tracking-widest';
        onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full inline-block mr-1 bg-slate-400"></span>Offline';
      }
    }

    // --- update last update indicator ---
    const lastEl = document.getElementById('d-last-update');
    if (lastEl) {
      lastEl.innerHTML = `<i class="bi bi-clock-history mr-1"></i>Update: ${formatTime(lastUpdate)}`;
      lastEl.classList.remove('hidden');
    }

    // --- update nurse call card ---
    const nurseCard   = document.getElementById('d-nurse-card');
    const nurseStatus = document.getElementById('d-nurse-status');
    const nurseHint   = document.getElementById('d-nurse-hint');

    if (nurseCard && nurseStatus) {
      if (nurseCall === 1) {
        nurseCard.className = 'bg-red-600 text-white p-5 rounded-2xl transition-all border border-transparent';
        nurseStatus.textContent = 'EMERGENCY CALL';
        if (nurseHint) nurseHint.classList.remove('hidden');
      } else {
        nurseCard.className = 'bg-slate-50 text-slate-400 p-5 rounded-2xl transition-all border border-transparent';
        nurseStatus.textContent = 'NORMAL';
        if (nurseHint) nurseHint.classList.add('hidden');
      }
    }

    // --- ambil history terbaru untuk chart (50 data) ---
    const histRes  = await fetch(`api/get_history.php?device_id=${encodeURIComponent(deviceId)}&limit=50&_=${Date.now()}`, { cache: 'no-store' });
    const histJson = await histRes.json();

    if (histJson.status === 'ok' && histJson.data.length > 0) {
      cachedHistory = histJson.data;

      // update chart
      const labels  = histJson.data.map(h => formatTime(h.created_at));
      const tpmArr  = histJson.data.map(h => parseFloat(h.tpm));
      const volArr  = histJson.data.map(h => parseFloat(h.volume_sisa));

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

  // tampilkan dari terbaru ke terlama, max 10
  const reversed = [...data].reverse().slice(0, 10);

  tbody.innerHTML = reversed.map(h => {
    const persen = parseFloat(h.persen || 0);
    const barColor = persen > 20 ? 'bg-blue-500' : 'bg-red-500';
    return `
      <tr class="hover:bg-slate-50/50 transition-all">
        <td class="px-8 py-4 text-xs font-bold text-slate-500 tracking-tighter">${formatTime(h.created_at)}</td>
        <td class="px-8 py-4"><span class="text-sm font-black text-slate-800">${Math.round(h.tpm)}</span> <span class="text-[9px] font-bold text-slate-400 ml-1">TPM</span></td>
        <td class="px-8 py-4"><span class="text-sm font-black text-slate-800">${Math.round(h.volume_sisa)}</span> <span class="text-[9px] font-bold text-slate-400 ml-1">ML</span></td>
        <td class="px-8 py-4 text-right">
          <div class="inline-flex items-center gap-3">
            <div class="w-24 h-1.5 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full ${barColor}" style="width: ${Math.min(100, persen)}%"></div>
            </div>
            <span class="text-xs font-black text-slate-700 w-8">${persen.toFixed(0)}%</span>
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

// Refresh chart setiap 5 detik
setInterval(refreshDetail, 5000);

// ===== REFRESH LOG TABEL — setiap 10 menit =====
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

setInterval(refreshLogTable, 600000); // setiap 10 menit
refreshLogTable();                    // load awal
