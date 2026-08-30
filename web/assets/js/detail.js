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
const ONLINE_THRESHOLD_MS = 15 * 1000;  // 15 detik — lebih responsif detect offline

function isOnline(dateStr) {
  if (!dateStr) return false;
  return (Date.now() - new Date(dateStr).getTime()) < ONLINE_THRESHOLD_MS;
}

// =====================================================
// ===== AUDIO & ALERT ENGINE ==========================
// =====================================================

const nurseActiveSet = new Set();
let audioCtx = null;
let ringtoneAudio = null;

// ── Global audio mutex ────────────────────────────────
// Hanya satu suara yang boleh jalan dalam satu waktu.
// nurse loop pegang mutex selama aktif.
// alert sekali (low vol, TPM) antri dan tunggu.
let _audioLocked = false;
const _audioQueue = [];   // [{priority, fn: async}]

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// Acquire lock — tunggu sampai bebas, lalu jalankan fn
async function withAudio(fn) {
  return new Promise((resolve) => {
    _audioQueue.push({ fn, resolve });
    _drainAudioQueue();
  });
}

async function _drainAudioQueue() {
  if (_audioLocked || _audioQueue.length === 0) return;
  _audioLocked = true;
  const { fn, resolve } = _audioQueue.shift();
  try { await fn(); } catch (e) { console.warn('audio fn err:', e); }
  _audioLocked = false;
  resolve();
  _drainAudioQueue();
}

function getAudioCtx() {
  if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  if (audioCtx.state === 'suspended') audioCtx.resume();
  return audioCtx;
}

// Ambil suara id-ID terbaik (lokal diutamakan)
function getIdVoice() {
  if (!window.speechSynthesis) return null;
  const voices = window.speechSynthesis.getVoices();
  return voices.find(v => v.lang === 'id-ID' && v.localService)
    || voices.find(v => v.lang === 'id-ID')
    || voices.find(v => v.lang.startsWith('id'))
    || null;
}

// Play ringtone — resolve saat selesai ATAU timeout 10 detik (safety)
function playRingtone() {
  return new Promise(resolve => {
    if (window.globalNotificationsActive || nurseLoopStopped) {
      resolve();
      return;
    }
    getAudioCtx();
    if (!ringtoneAudio) {
      ringtoneAudio = new Audio('assets/nurse-call.mp3');
      ringtoneAudio.preload = 'auto';
    }
    // Timeout safety: jika onended tidak fire dalam 10 detik, resolve anyway
    let done = false;
    const finish = () => { if (!done) { done = true; resolve(); } };

    ringtoneAudio.pause();
    ringtoneAudio.currentTime = 0;
    ringtoneAudio.onended = finish;
    ringtoneAudio.onerror = finish;
    // Jika durasi diketahui, timeout = durasi + 500ms, minimum 3 detik
    const timeout = ringtoneAudio.duration > 0
      ? (ringtoneAudio.duration * 1000 + 500)
      : 10000;
    setTimeout(finish, timeout);
    if (nurseLoopStopped) {
      finish();
      return;
    }
    ringtoneAudio.play().catch(finish);
  });
}

// TTS — resolve saat selesai bicara, dengan timeout safety 15 detik
function speak(text) {
  return new Promise(resolve => {
    if (window.globalNotificationsActive) { resolve(); return; }
    if (!window.speechSynthesis) { resolve(); return; }
    window.speechSynthesis.cancel();
    const utt = new SpeechSynthesisUtterance(text);
    const voice = getIdVoice();
    if (voice) utt.voice = voice;
    utt.lang = 'id-ID'; utt.rate = 0.88; utt.pitch = 1.0; utt.volume = 1.0;

    let done = false;
    const finish = () => { if (!done) { done = true; resolve(); } };
    utt.onend = finish;
    utt.onerror = finish;
    // Safety timeout — Chrome kadang tidak fire onend untuk teks panjang
    setTimeout(finish, 15000);

    window.speechSynthesis.speak(utt);
  });
}

// ── Nurse call loop — pegang mutex selama ada active ──
let nurseLoopRunning = false;
let nurseLoopStopped = false;

async function nurseAlertLoop() {
  if (nurseLoopRunning) return;
  nurseLoopRunning = true;
  nurseLoopStopped = false;

  while (nurseActiveSet.size > 0 && !nurseLoopStopped) {
    const devIds = Array.from(nurseActiveSet);
    for (const devId of devIds) {
      if (nurseActiveSet.size === 0 || nurseLoopStopped) break;

      const pasien = document.body.dataset.detailPasien || 'Pasien';
      const lokasi = document.body.dataset.detailLokasi || '';
      const lokasiText = lokasi ? `, di ${lokasi},` : '';
      const teks = `Perhatian. Pasien ${pasien}${lokasiText} sedang membutuhkan bantuan. Segera menuju ${lokasi || 'lokasi pasien'}.`;

      _audioLocked = true;

      try {
        if (!nurseLoopStopped) await playRingtone();

        if (!nurseLoopStopped) {
          await sleep(200);
          await speak(teks);
        }

        if (!nurseLoopStopped) {
          await sleep(1000);
        }
      }
      catch (e) {
        console.warn('nurse loop err:', e);
      }
      finally {
        _audioLocked = false;
        _drainAudioQueue();
      }
    }
  }

  nurseLoopRunning = false;  // ← wajib reset di sini agar loop bisa mulai lagi
}

function stopNurseLoop() {
  nurseLoopStopped = true;
  nurseLoopRunning = false;  // ← reset paksa agar loop bisa restart kalau perlu
  if (ringtoneAudio) { ringtoneAudio.pause(); ringtoneAudio.currentTime = 0; }
  if (window.speechSynthesis) window.speechSynthesis.cancel();
  _audioLocked = false;
  _drainAudioQueue();
}

function handleNurseCallState(deviceId, nurseCall, pasienName, lokasi, online) {
  const wasActive = nurseActiveSet.has(deviceId);
  const isActive = nurseCall === 1 && online;
  if (isActive && !wasActive) {
    nurseActiveSet.add(deviceId);
    showNurseToast(pasienName, lokasi, deviceId);
    nurseAlertLoop();
  } else if (!isActive && wasActive) {
    nurseActiveSet.delete(deviceId);
    if (nurseActiveSet.size === 0) {
      stopNurseLoop();
      dismissNurseToast();
    }
  }
}

// =====================================================
// ===== TOAST SYSTEM — TERPUSAT, TIDAK TUMPUK =========
// =====================================================
// Semua toast dikelola dalam satu container vertikal.
// Di mobile: muncul di bawah layar (bottom sheet style).
// Di desktop: pojok kanan atas.

function getToastContainer() {
  let c = document.getElementById('toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'toast-container';
    // Mobile: bawah tengah. Desktop: kanan atas via media query simulasi inline
    c.style.cssText = [
      'position:fixed',
      'z-index:9999',
      'display:flex',
      'flex-direction:column',
      'gap:8px',
      'pointer-events:none',
      // Default: kanan atas untuk desktop
      'top:72px', 'right:16px', 'left:auto', 'bottom:auto',
      'width:320px',
      'max-width:calc(100vw - 32px)',
    ].join(';');
    // Override ke bawah di mobile
    if (window.innerWidth < 640) {
      c.style.top = 'auto';
      c.style.bottom = '72px';   // di atas mobile bottom nav
      c.style.right = '8px';
      c.style.left = '8px';
      c.style.width = 'auto';
    }
    document.body.appendChild(c);
    // Update posisi saat resize
    window.addEventListener('resize', () => {
      if (window.innerWidth < 640) {
        c.style.top = 'auto'; c.style.bottom = '72px';
        c.style.right = '8px'; c.style.left = '8px'; c.style.width = 'auto';
      } else {
        c.style.top = '72px'; c.style.bottom = 'auto';
        c.style.right = '16px'; c.style.left = 'auto'; c.style.width = '320px';
      }
    });
  }
  return c;
}

function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Buat toast element dan masukkan ke container
function createToast(id, html, autoClose = 10000) {
  if (window.globalNotificationsActive) { return null; }
  const old = document.getElementById(id);
  if (old) old.remove();

  const wrap = document.createElement('div');
  wrap.id = id;
  wrap.style.cssText = 'pointer-events:all; transition:opacity .3s, transform .3s; opacity:0; transform:translateY(8px);';
  wrap.innerHTML = html;

  const container = getToastContainer();
  container.appendChild(wrap);

  // Animasi masuk
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      wrap.style.opacity = '1';
      wrap.style.transform = 'translateY(0)';
    });
  });

  if (autoClose > 0) {
    setTimeout(() => removeToast(id), autoClose);
  }
  return wrap;
}

function removeToast(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.opacity = '0';
  el.style.transform = 'translateY(8px)';
  setTimeout(() => el.remove(), 300);
}

// ── Template tiap tipe toast ──────────────────────────
function toastHTML(icon, bgColor, borderColor, labelColor, label, title, body, locationText, closeCall) {
  return `
    <div style="background:${bgColor};border:1px solid ${borderColor};border-radius:14px;padding:14px;box-shadow:0 10px 25px rgba(0,0,0,.12);">
      <div style="display:flex;align-items:flex-start;gap:10px;">
        <div style="width:36px;height:36px;background:${labelColor};color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;">
          <i class="bi bi-${icon}"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:10px;font-weight:900;letter-spacing:.05em;color:${labelColor};text-transform:uppercase;">${label}</div>
          <div style="font-size:13px;font-weight:800;color:#0f172a;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${title}</div>
          <div style="font-size:11px;color:#475569;margin-top:2px;">${body}</div>
          ${locationText ? `<div style="font-size:11px;color:${labelColor};font-weight:700;margin-top:6px;display:flex;align-items:center;gap:4px;"><i class="bi bi-geo-alt-fill"></i>${locationText}</div>` : ''}
        </div>
        <button onclick="${closeCall}" style="color:#94a3b8;background:none;border:none;cursor:pointer;padding:2px;flex-shrink:0;font-size:12px;">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>`;
}

// ===== TOAST NURSE CALL =====
function showNurseToast(pasienName, lokasi, deviceId) {
  const html = toastHTML(
    'bell-fill', '#fef2f2', '#fca5a5', '#ef4444',
    'Emergency Alert',
    escHtml(pasienName),
    'Memerlukan bantuan perawat segera!',
    escHtml(lokasi || 'Lokasi tidak diketahui'),
    'dismissNurseToast()'
  );
  createToast('nurse-toast', html, 0);   // 0 = tidak auto-close selama nurse call aktif
}

function dismissNurseToast() { removeToast('nurse-toast'); }

const lowVolAlertedSet = new Set();

function handleLowVolumeAlert(deviceId, persen, volumeSisa, pasienName, lokasi, online) {
  if (!online) {
    lowVolAlertedSet.delete(deviceId);
    removeToast('low-vol-toast-' + deviceId);
    return;
  }
  const pct = parseFloat(persen) || 0;
  const vol = parseFloat(volumeSisa) || 0;

  // Peringatan jika sisa persentase <= 20% (atau sisa <= 20 ml) dan cairan masih ada (> 0)
  const isLow = (pct > 0 && pct <= 20) || (pct === 0 && vol > 0 && vol <= 20);

  if (isLow && vol > 0) {
    if (!lowVolAlertedSet.has(deviceId)) {
      lowVolAlertedSet.add(deviceId);
      speakLowVolume(pasienName, lokasi, vol, pct);
      showLowVolumeToast(pasienName, lokasi, vol, pct, deviceId);
    }
  } else {
    lowVolAlertedSet.delete(deviceId);
  }
}

function speakLowVolume(pasienName, lokasi, vol, pct) {
  if (!window.speechSynthesis) return;
  if (nurseActiveSet.size > 0) return;   // nurse loop sedang jalan, skip
  const lokasiText = lokasi ? ` di ${lokasi}` : '';
  const pctText = pct > 0 ? ` sisa ${Math.round(pct)} persen,` : '';
  withAudio(() => speak(
    `Perhatian. Cairan infus pasien ${pasienName}${lokasiText} hampir habis.${pctText} Tersisa ${Math.round(vol)} mililiter. Segera ganti kantong infus.`
  ));
}

function showLowVolumeToast(pasienName, lokasi, vol, pct, deviceId) {
  const pctText = pct > 0 ? ` (${Math.round(pct)}%)` : '';
  const html = toastHTML(
    'droplet-half', '#fffbeb', '#fcd34d', '#d97706',
    'Volume Warning',
    escHtml(pasienName),
    `Sisa <b style="color:#dc2626">${Math.round(vol)} ml${pctText}</b> — segera ganti!`,
    escHtml(lokasi || 'Lokasi tidak diketahui'),
    `removeToast('low-vol-toast-${deviceId}')`
  );
  createToast('low-vol-toast-' + deviceId, html, 12000);
}

// =====================================================
// ===== TPM ALERT SYSTEM ==============================
// =====================================================

const tpmZeroSince = new Map();  // deviceId → timestamp first detected
const tpmAlertedSet = new Set();  // deviceId → already alerted
const tpmHighAlerted = new Set();  // deviceId → high-TPM already alerted

function clearTpmZeroToast(deviceId) { removeToast('tpm-toast-' + deviceId); }

function showTpmToast(pasienName, lokasi, toastKey, message, color) {
  const isPurple = color === 'purple';
  const html = toastHTML(
    'speedometer2',
    isPurple ? '#faf5ff' : '#fffbeb',
    isPurple ? '#c084fc' : '#fcd34d',
    isPurple ? '#9333ea' : '#d97706',
    'TPM Warning',
    escHtml(pasienName),
    escHtml(message),
    escHtml(lokasi || 'Lokasi tidak diketahui'),
    `removeToast('tpm-toast-${toastKey}')`
  );
  createToast('tpm-toast-' + toastKey, html, 14000);
}

function speakTpmAlert(pasienName, lokasi, message) {
  if (!window.speechSynthesis) return;
  if (nurseActiveSet.size > 0) return;   // nurse loop sedang jalan, skip
  const lokasiText = lokasi ? ` di ${lokasi}` : '';
  withAudio(() => speak(`Perhatian. Pasien ${pasienName}${lokasiText}. ${message}`));
}

function handleTpmZeroAlert(deviceId, tpm, volumeSisa, pasienName, lokasi, online) {
  const t = parseFloat(tpm) || 0;
  const vol = parseFloat(volumeSisa) || 0;

  // ── Clear all TPM alerts when offline ──
  if (!online) {
    tpmZeroSince.delete(deviceId);
    tpmAlertedSet.delete(deviceId);
    tpmHighAlerted.delete(deviceId);
    clearTpmZeroToast(deviceId);
    return;
  }

  // ── HIGH TPM alert (>80 tpm = too fast) ──
  if (t > 80 && vol > 0) {
    if (!tpmHighAlerted.has(deviceId)) {
      tpmHighAlerted.add(deviceId);
      const msg = `Tetesan infus terlalu cepat: ${Math.round(t)} tpm. Harap periksa segera.`;
      showTpmToast(pasienName, lokasi, deviceId + '-high', msg, 'amber');
      speakTpmAlert(pasienName, lokasi, `Tetesan infus terlalu cepat, ${Math.round(t)} tetes per menit. Harap periksa segera.`);
    }
    return;
  } else {
    tpmHighAlerted.delete(deviceId);
  }

  // ── LOW / ZERO TPM alert (=0 with fluid remaining = clogged) ──
  if (t === 0 && vol > 0) {
    if (!tpmZeroSince.has(deviceId)) {
      tpmZeroSince.set(deviceId, Date.now());
    }
    // Trigger alert after 10 seconds of sustained 0 tpm
    const elapsed = Date.now() - tpmZeroSince.get(deviceId);
    if (elapsed >= 10000 && !tpmAlertedSet.has(deviceId)) {
      tpmAlertedSet.add(deviceId);
      const msg = `Infus kemungkinan macet (0 tpm). Sisa cairan ${Math.round(vol)} ml. Harap periksa segera.`;
      showTpmToast(pasienName, lokasi, deviceId, msg, 'purple');
      speakTpmAlert(pasienName, lokasi, `Infus macet. Sisa cairan ${Math.round(vol)} mililiter. Harap periksa segera.`);
    }
  } else {
    // TPM normal — clear state
    tpmZeroSince.delete(deviceId);
    tpmAlertedSet.delete(deviceId);
    clearTpmZeroToast(deviceId);
  }
}


// ===== CACHE DATA HISTORY UNTUK EXPORT =====
let cachedHistory = [];
let _nurseCallAlerted = false;  // Track saat ini ada nurse call alert

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
    // Prefer server-side is_online to avoid client clock drift
    const online     = (dev.is_online !== undefined) ? Boolean(dev.is_online) : isOnline(lastUpdate);

    const pasienName = dev.pasien || document.body.dataset.detailPasien || 'Pasien';
    const lokasi     = dev.lokasi || document.body.dataset.detailLokasi || '';

    // Save to body dataset for quick global access
    document.body.dataset.detailPasien = pasienName;
    document.body.dataset.detailLokasi = lokasi;

    // Simpan lastUpdate ke DOM agar offline-watcher bisa baca
    document.body.dataset.detailLastUpdate = lastUpdate || '';

    // --- 1. Update Stat Cards Text ---
    const dTpm = document.getElementById('d-tpm');
    const dVol = document.getElementById('d-volume');
    const dEst = document.getElementById('d-estimasi');

    if (dTpm) {
      dTpm.textContent = Math.round(tpm);
      // Selalu hapus badge lama dulu agar tidak double atau salah tipe
      const oldBadge = document.getElementById('d-tpm-warning');
      if (oldBadge) oldBadge.remove();
      dTpm.style.color = '';

      if (online && tpm === 0 && volumeSisa > 0) {
        dTpm.style.color = '#9333ea';
        const badge = document.createElement('span');
        badge.id = 'd-tpm-warning';
        badge.style.cssText = 'display:inline-flex;align-items:center;gap:3px;margin-left:6px;padding:2px 7px;border-radius:8px;font-size:10px;font-weight:900;background:#f3e8ff;color:#9333ea;border:1px solid #d8b4fe;vertical-align:middle;';
        badge.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="font-size:8px"></i> MACET';
        dTpm.parentElement.appendChild(badge);
      } else if (online && tpm > 80) {
        dTpm.style.color = '#d97706';
        const badge = document.createElement('span');
        badge.id = 'd-tpm-warning';
        badge.style.cssText = 'display:inline-flex;align-items:center;gap:3px;margin-left:6px;padding:2px 7px;border-radius:8px;font-size:10px;font-weight:900;background:#fffbeb;color:#d97706;border:1px solid #fcd34d;vertical-align:middle;';
        badge.innerHTML = '<i class="bi bi-speedometer2" style="font-size:8px"></i> CEPAT';
        dTpm.parentElement.appendChild(badge);
      }
    }
    if (dVol) dVol.innerHTML = Math.round(volumeSisa);
    if (dEst) {
      if (online && tpm === 0 && volumeSisa > 0) {
        dEst.innerHTML = '<span style="color:#9333ea;">Terhenti</span>';
      } else {
        dEst.textContent = `${estJam}j ${estMnt}m`;
      }
    }

    // --- 2. Update Bottle Visual Fluid & Color Level ---
    const bottleFluid = document.getElementById('d-bottle-fluid');
    if (bottleFluid) {
      const h = Math.min(100, Math.max(0, persen));
      // Pakai inline style agar position:absolute bottom:0 tidak bergantung pada Tailwind build
      bottleFluid.style.position   = 'absolute';
      bottleFluid.style.bottom     = '0';
      bottleFluid.style.left       = '0';
      bottleFluid.style.right      = '0';
      bottleFluid.style.width      = '100%';
      bottleFluid.style.height     = h + '%';
      bottleFluid.style.transition = 'height 1s ease-in-out';

      if (!online) {
        bottleFluid.style.background = '#94a3b8';
        bottleFluid.style.animation  = 'none';
      } else if (persen <= 20) {
        bottleFluid.style.background = 'linear-gradient(to top, #dc2626, #f87171)';
        bottleFluid.style.animation  = 'fluid-blink 1.5s infinite';
      } else if (persen <= 50) {
        bottleFluid.style.background = 'linear-gradient(to top, #d97706, #f59e0b)';
        bottleFluid.style.animation  = 'none';
      } else {
        bottleFluid.style.background = 'linear-gradient(to top, #6b2072, #a855f7)';
        bottleFluid.style.animation  = 'none';
      }
    }

    // --- 3. Update Persen Text ---
    const persenText = document.getElementById('d-persen-text');
    if (persenText) persenText.textContent = persen.toFixed(0) + '%';

    // --- 4. Update Header Layout (Emergency Alert State) ---
    // Gunakan inline style agar tidak bergantung pada Tailwind build
    const headerCard = document.getElementById('detail-header-card');
    if (headerCard) {
      const showNurseAlert = nurseCall === 1 && online;
      if (showNurseAlert) {
        headerCard.style.cssText = 'border:1.5px solid #fca5a5; background:rgba(254,242,242,0.3); box-shadow:0 0 0 4px rgba(239,68,68,0.06); border-radius:1rem; padding:1.5rem; margin-bottom:1.5rem; transition:all .3s;';
      } else {
        headerCard.style.cssText = 'border:1px solid #f1f5f9; background:#ffffff; box-shadow:0 1px 3px 0 rgba(0,0,0,.04); border-radius:1rem; padding:1.5rem; margin-bottom:1.5rem; transition:all .3s;';
      }
    }

    // --- 5. Update Badges (Online, Mode, Nurse Call) ---
    const onlineBadge = document.getElementById('d-online-badge');
    if (onlineBadge) {
      if (online) {
        onlineBadge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:10px;font-weight:900;letter-spacing:.05em;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;';
        onlineBadge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;"></span>CONNECTED';
      } else {
        onlineBadge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:10px;font-weight:900;letter-spacing:.05em;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;';
        onlineBadge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>OFFLINE';
      }
    }

    // Nurse badge — HANYA tampil jika online DAN nurse call aktif
    const dNurseBadge = document.getElementById('d-nurse-badge');
    if (dNurseBadge) {
      if (nurseCall === 1 && online) dNurseBadge.classList.remove('hidden');
      else                           dNurseBadge.classList.add('hidden');
    }

    // --- 6. Update Last Update Indicator ---
    const lastEl = document.getElementById('d-last-update');
    if (lastEl) {
      lastEl.innerHTML = `<i class="bi bi-clock-history mr-1"></i>Update Terakhir: ${formatTime(lastUpdate)}`;
    }

    // --- 7. Update Nurse Call Card Status ---
    // Nurse call hanya aktif jika device online — jika offline, paksa ke STANDBY
    const nurseCard    = document.getElementById('d-nurse-card');
    const nurseIconBox = document.getElementById('d-nurse-icon-box');
    const nurseStatus  = document.getElementById('d-nurse-status');
    const nurseHint    = document.getElementById('d-nurse-hint');

    const isNurseActive = nurseCall === 1 && online;

    if (nurseCard && nurseStatus) {
      if (isNurseActive) {
        nurseCard.style.cssText = 'background:#fef2f2;border:1px solid #fecaca;border-radius:1rem;padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;transition:all .3s;';
        if (nurseIconBox) nurseIconBox.style.cssText = 'width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#ef4444;color:#fff;border:1px solid #dc2626;animation:bounce 1s infinite;';
        nurseStatus.style.color   = '#dc2626';
        nurseStatus.textContent   = 'EMERGENCY ALERT';
        if (nurseHint) nurseHint.classList.remove('hidden');
      } else {
        nurseCard.style.cssText = 'background:#ffffff;border:1px solid #f1f5f9;border-radius:1rem;padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;transition:all .3s;';
        if (nurseIconBox) nurseIconBox.style.cssText = 'width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#94a3b8;border:1px solid #f1f5f9;animation:none;';
        nurseStatus.style.color   = '#94a3b8';
        nurseStatus.textContent   = 'STANDBY NORMAL';
        if (nurseHint) nurseHint.classList.add('hidden');
      }
    }

    if (online) {
      document.body.dataset.lastReceivedMs = String(Date.now());
    }

    handleNurseCallState(deviceId, nurseCall, pasienName, lokasi, online);
    handleLowVolumeAlert(deviceId, persen, volumeSisa, pasienName, lokasi, online);
    handleTpmZeroAlert(deviceId, tpm, volumeSisa, pasienName, lokasi, online);

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
refreshDetail();   // panggil segera saat halaman load

// ── Offline-watcher: cek setiap 1 detik untuk update VISUAL saat timeout ────
// Saat device tidak dapat update 30+ detik, set offline dan clear alerts.
const _detailOfflineState = new Set();

setInterval(() => {
  const lastUpdate = document.body.dataset.detailLastUpdate;
  if (!lastUpdate) return;

  const isTimedOut = (Date.now() - new Date(lastUpdate).getTime()) >= ONLINE_THRESHOLD_MS;

  // Transition: online → offline (belum di-mark sebelumnya)
  if (isTimedOut && !_detailOfflineState.has('device')) {
    _detailOfflineState.add('device');

    // Clear nurse call & alerts
    const dNurseBadge  = document.getElementById('d-nurse-badge');
    const nurseCard    = document.getElementById('d-nurse-card');
    const nurseStatus  = document.getElementById('d-nurse-status');
    const nurseHint    = document.getElementById('d-nurse-hint');
    const nurseIconBox = document.getElementById('d-nurse-icon-box');
    const headerCard   = document.getElementById('detail-header-card');

    if (dNurseBadge) dNurseBadge.classList.add('hidden');
    if (nurseHint) nurseHint.classList.add('hidden');
    if (nurseCard) nurseCard.style.cssText = 'background:#ffffff;border:1px solid #f1f5f9;border-radius:1rem;padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;transition:all .3s;';
    if (nurseStatus) { nurseStatus.style.color = '#94a3b8'; nurseStatus.textContent = 'STANDBY NORMAL'; }
    if (nurseIconBox) nurseIconBox.style.cssText = 'width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#f8fafc;color:#94a3b8;border:1px solid #f1f5f9;animation:none;';
    if (headerCard) headerCard.style.cssText = 'border:1px solid #f1f5f9; background:#ffffff; box-shadow:0 1px 3px 0 rgba(0,0,0,.04); border-radius:1rem; padding:1.5rem; margin-bottom:1.5rem; transition:all .3s;';

    // Update online badge ke OFFLINE
    const onlineBadge = document.getElementById('d-online-badge');
    if (onlineBadge && onlineBadge.innerHTML.includes('CONNECTED')) {
      onlineBadge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:10px;font-weight:900;letter-spacing:.05em;background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;';
      onlineBadge.innerHTML = '<span style="width:6px;height:6px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>OFFLINE';
    }

    // Reset TTS alert flag
    _nurseCallAlerted = false;

    // Clear alarm states & loops immediately
    if (nurseActiveSet.has(deviceId)) {
      nurseActiveSet.delete(deviceId);
      if (nurseActiveSet.size === 0) { stopNurseLoop(); dismissNurseToast(); }
    }
    tpmZeroSince.delete(deviceId);
    tpmAlertedSet.delete(deviceId);
    tpmHighAlerted.delete(deviceId);
    clearTpmZeroToast(deviceId);
    removeToast('low-vol-toast-' + deviceId);
    lowVolAlertedSet.delete(deviceId);
  }
  // Transition: offline → online (dapat data baru)
  else if (!isTimedOut && _detailOfflineState.has('device')) {
    _detailOfflineState.delete('device');
  }
}, 1000);

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

// ── Audio unlock ────────────
if (window.speechSynthesis) {
  window.speechSynthesis.getVoices();
  window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
}
document.addEventListener('click', () => getAudioCtx(), { once: true });
document.addEventListener('touchstart', () => getAudioCtx(), { once: true });