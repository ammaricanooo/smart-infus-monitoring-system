// =====================================================
// DASHBOARD — AUTO REFRESH, CLOCK & NURSE CALL ALERT
// =====================================================

// ===== JAM REALTIME =====
function updateClock() {
  const now = new Date();
  const h   = String(now.getHours()).padStart(2, '0');
  const m   = String(now.getMinutes()).padStart(2, '0');
  const s   = String(now.getSeconds()).padStart(2, '0');
  const el  = document.getElementById('clockText');
  if (el) el.textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);
updateClock();

// ===== FORMAT TANGGAL WAKTU =====
function formatTime(dateStr) {
  if (!dateStr) return '--:--:--';
  const d   = new Date(dateStr);
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

// ===== ESCAPE HTML =====
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// =====================================================
// ===== NURSE CALL ALERT SYSTEM =======================
// =====================================================
const nurseActiveSet = new Set();
let audioCtx = null;

function getAudioCtx() {
  if (!audioCtx) {
    audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }
  if (audioCtx.state === 'suspended') {
    audioCtx.resume();
  }
  return audioCtx;
}

let ringtoneAudio = null;

function playRingtone() {
  return new Promise(resolve => {
    getAudioCtx();
    if (!ringtoneAudio) {
      ringtoneAudio = new Audio('assets/nurse-call.mp3');
      ringtoneAudio.preload = 'auto';
    }
    ringtoneAudio.pause();
    ringtoneAudio.currentTime = 0;
    ringtoneAudio.onended = () => resolve();
    ringtoneAudio.onerror = () => {
      console.warn('Gagal memutar nurse-call.mp3');
      resolve();
    };
    ringtoneAudio.play().catch(err => {
      console.warn('Audio play error:', err);
      resolve();
    });
  });
}

function speakAlert(pasienName, lokasi) {
  return new Promise(resolve => {
    if (!window.speechSynthesis) { resolve(); return; }
    window.speechSynthesis.cancel();
    const lokasiText = lokasi ? `, di ${lokasi},` : '';
    const text = `Perhatian. Pasien ${pasienName}${lokasiText} sedang membutuhkan bantuan. Segera menuju ${lokasi || 'lokasi pasien'}.`;
    const utt  = new SpeechSynthesisUtterance(text);
    const voices = window.speechSynthesis.getVoices();
    const idVoice = voices.find(v => v.lang === 'id-ID' || v.lang.startsWith('id'));
    if (idVoice) utt.voice = idVoice;
    utt.lang  = 'id-ID';
    utt.rate  = 0.88;
    utt.pitch = 1.0;
    utt.volume = 1.0;
    utt.onend   = () => resolve();
    utt.onerror = () => resolve();
    window.speechSynthesis.speak(utt);
  });
}

let alertRunning = false;
let alertLoop    = null;

async function triggerNurseAlert(deviceId, pasienName, lokasi) {
  if (alertRunning) return;
  alertRunning = true;
  showNurseToast(pasienName, lokasi, deviceId);
  try {
    await playRingtone();
    await sleep(300);
    await speakAlert(pasienName, lokasi);
    await sleep(1500);
  } catch (e) {
    console.warn('Alert error:', e);
  }
  alertRunning = false;
  if (nurseActiveSet.has(deviceId)) {
    alertLoop = setTimeout(() => triggerNurseAlert(deviceId, pasienName, lokasi), 500);
  }
}

function stopNurseAlert() {
  if (alertLoop) { clearTimeout(alertLoop); alertLoop = null; }
  alertRunning = false;
  if (window.speechSynthesis) window.speechSynthesis.cancel();
  if (ringtoneAudio) { ringtoneAudio.pause(); ringtoneAudio.currentTime = 0; }
}

function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

// ===== TOAST NOTIFIKASI NURSE CALL =====
function showNurseToast(pasienName, lokasi, deviceId) {
  const old = document.getElementById('nurse-toast');
  if (old) old.remove();

  const lokasiLabel = lokasi ? lokasi : 'Lokasi tidak diketahui';

  const toast = document.createElement('div');
  toast.id = 'nurse-toast';
  // Menyesuaikan style dengan elemen toast Tailwind CSS modern
  toast.innerHTML = `
    <div class="fixed bottom-6 left-6 right-6 sm:left-auto sm:right-6 sm:w-85 z-50 p-4 bg-red-50 border border-red-200 rounded-2xl shadow-xl shadow-red-900/10 transition-all duration-300 transform translate-y-0">
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 bg-red-500 text-white rounded-xl flex items-center justify-center flex-shrink-0 shadow-md shadow-red-500/20">
          <i class="bi bi-bell-fill text-sm"></i>
        </div>
        <div class="flex-1">
          <div class="text-[10px] font-black tracking-wider text-red-600 uppercase">Emergency Alert</div>
          <div class="text-sm font-extrabold text-slate-900 mt-0.5">
            <strong>${escHtml(pasienName)}</strong>
          </div>
          <div class="text-xs text-slate-600 font-medium mt-0.5">Memerlukan bantuan perawat segera!</div>
          <div class="text-[11px] text-red-600 font-bold mt-2 flex items-center gap-1">
            <i class="bi bi-geo-alt-fill"></i>
            <span>${escHtml(lokasiLabel)}</span>
          </div>
        </div>
        <button class="text-slate-400 hover:text-slate-600 transition-colors p-1" onclick="dismissNurseToast()">
          <i class="bi bi-x-lg text-xs"></i>
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(toast);

  setTimeout(() => {
    if (document.getElementById('nurse-toast')) dismissNurseToast();
  }, 8000);
}

function dismissNurseToast() {
  const t = document.getElementById('nurse-toast');
  if (t) {
    t.classList.add('opacity-0', 'translate-y-2');
    setTimeout(() => t.remove(), 400);
  }
}

function handleNurseCallState(deviceId, nurseCall, pasienName, lokasi) {
  const wasActive = nurseActiveSet.has(deviceId);
  const isActive  = nurseCall === 1;

  if (isActive && !wasActive) {
    nurseActiveSet.add(deviceId);
    triggerNurseAlert(deviceId, pasienName, lokasi);
  } else if (!isActive && wasActive) {
    nurseActiveSet.delete(deviceId);
    if (nurseActiveSet.size === 0) {
      stopNurseAlert();
      dismissNurseToast();
    }
  }
}

// =====================================================
// ===== LOW VOLUME TTS ALERT (0 < vol <= 10ml) ========
// =====================================================
const lowVolAlertedSet = new Set();

function handleLowVolumeAlert(deviceId, volumeSisa, pasienName, lokasi) {
  const vol = parseFloat(volumeSisa);
  const key = deviceId;

  if (vol > 0 && vol <= 10) {
    if (!lowVolAlertedSet.has(key)) {
      lowVolAlertedSet.add(key);
      speakLowVolume(pasienName, lokasi, vol);
      showLowVolumeToast(pasienName, lokasi, vol, deviceId);
    }
  } else {
    lowVolAlertedSet.delete(key);
  }
}

function speakLowVolume(pasienName, lokasi, vol) {
  if (!window.speechSynthesis) return;
  const doSpeak = () => {
    window.speechSynthesis.cancel();
    const lokasiText = lokasi ? ` di ${lokasi}` : '';
    const text = `Perhatian. Cairan infus pasien ${pasienName}${lokasiText} hampir habis. Sisa ${Math.round(vol)} mililiter. Segera ganti.`;
    const utt  = new SpeechSynthesisUtterance(text);
    const voices = window.speechSynthesis.getVoices();
    const idVoice = voices.find(v => v.lang === 'id-ID' || v.lang.startsWith('id'));
    if (idVoice) utt.voice = idVoice;
    utt.lang   = 'id-ID';
    utt.rate   = 0.85;
    utt.pitch  = 1.0;
    utt.volume = 1.0;
    window.speechSynthesis.speak(utt);
  };
  setTimeout(doSpeak, alertRunning ? 4000 : 300);
}

function showLowVolumeToast(pasienName, lokasi, vol, deviceId) {
  const toastId = 'low-vol-toast-' + deviceId;
  const old = document.getElementById(toastId);
  if (old) old.remove();

  const lokasiLabel = lokasi ? lokasi : 'Lokasi tidak diketahui';

  const toast = document.createElement('div');
  toast.id = toastId;
  toast.innerHTML = `
    <div class="fixed top-20 right-6 w-85 z-50 p-4 bg-amber-50 border border-amber-200 rounded-2xl shadow-xl shadow-amber-900/5 transition-all duration-300">
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 bg-amber-500 text-white rounded-xl flex items-center justify-center flex-shrink-0 shadow-md shadow-amber-500/20">
          <i class="bi bi-droplet-half text-sm"></i>
        </div>
        <div class="flex-1">
          <div class="text-[10px] font-black tracking-wider text-amber-600 uppercase">Volume Warning</div>
          <div class="text-sm font-extrabold text-slate-900 mt-0.5">
            <strong>${escHtml(pasienName)}</strong>
          </div>
          <div class="text-xs text-slate-600 font-medium mt-0.5">Cairan kritis sisa <span class="text-red-500 font-bold">${Math.round(vol)} ml</span></div>
          <div class="text-[11px] text-amber-700 font-bold mt-2 flex items-center gap-1">
            <i class="bi bi-geo-alt-fill"></i>
            <span>${escHtml(lokasiLabel)}</span>
          </div>
        </div>
        <button class="text-slate-400 hover:text-slate-600 transition-colors p-1" onclick="document.getElementById('${toastId}').remove()">
          <i class="bi bi-x-lg text-xs"></i>
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(toast);

  setTimeout(() => {
    const el = document.getElementById(toastId);
    if (el) { el.classList.add('opacity-0'); setTimeout(() => el.remove(), 400); }
  }, 12000);
}

// =====================================================
// ===== MONITOR CARD LIVE REFLECTION (TAILWIND) =======
// =====================================================
function updateCard(dev) {
  const card = document.getElementById('card-' + dev.device_id);
  if (!card) return;

  const persen     = parseFloat(dev.persen      ?? 0);
  const volumeSisa = parseFloat(dev.volume_sisa  ?? 0);
  const volumeAwal = parseFloat(dev.volume_awal  ?? 500);
  const tpm        = parseFloat(dev.tpm          ?? 0);
  const nurseCall  = parseInt(dev.nurse_call     ?? 0);
  const estJam     = parseInt(dev.estimasi_jam   ?? 0);
  const estMnt     = parseInt(dev.estimasi_mnt   ?? 0);
  const lastUpdate = dev.created_at || null;
  const online     = isOnline(lastUpdate);
  const pasienName = dev.pasien  || card.dataset.pasien  || 'Tidak Diketahui';
  const lokasi     = dev.lokasi  || card.dataset.lokasi  || '';

  // 1. Sinkronisasi Dinamis Bingkai Utama Card (Border & Ring Status)
  card.className = "border rounded-2xl p-5 relative overflow-hidden shadow-sm flex flex-col justify-between transition-all duration-500 ";
  if (nurseCall === 1) {
    card.classList.add('border-red-500', 'ring-4', 'ring-red-500/10', 'bg-red-50/30');
  } else if (persen <= 20) {
    card.classList.add('border-amber-400', 'ring-4', 'ring-amber-500/5', 'bg-amber-50/20');
  } else {
    card.classList.add('border-slate-200', 'hover:border-slate-300', 'bg-white');
  }

  // 2. Animasi Ketinggian Cairan Botol Grafis
  const bottle = card.querySelector('[data-role="bottle-liquid"]');
  if (bottle) {
    bottle.style.height = Math.min(100, Math.max(0, persen)) + '%';
    bottle.className = "absolute bottom-0 inset-x-0 transition-all duration-1000 ";
    if (nurseCall === 1)      bottle.classList.add('bg-red-400');
    else if (persen <= 20)  bottle.classList.add('bg-amber-400');
    else                    bottle.classList.add('bg-[#6b2072]/80'); // Ungu Perusahaan
  }

  // 3. Linear Progress Bar
  const bar = card.querySelector('[data-role="progress-bar"]');
  if (bar) {
    bar.style.width = Math.min(100, Math.max(0, persen)) + '%';
    bar.className = "h-full rounded-full transition-all duration-1000 ";
    if (nurseCall === 1)      bar.classList.add('bg-red-500');
    else if (persen <= 20)  bar.classList.add('bg-amber-500');
    else                    bar.classList.add('bg-[#6b2072]'); // Ungu Perusahaan
  }

  // 4. Teks Persentase Angka
  const persenEl = card.querySelector('[data-role="persen-text"]');
  if (persenEl) persenEl.textContent = persen.toFixed(0) + '%';

  // 5. Elemen Warning Alert Teks Kritis 20%
  const lowEl = card.querySelector('[data-role="low-warning"]');
  if (lowEl) {
    if (persen <= 20 && nurseCall !== 1) {
      lowEl.classList.remove('hidden');
    } else {
      lowEl.classList.add('hidden');
    }
  }

  // 6. Teks Sisa Volume / Volume Batas Awal
  const volDisplay = card.querySelector('[data-role="volume-display"]');
  if (volDisplay) {
    volDisplay.textContent = Math.round(volumeSisa);
    if (volDisplay.nextElementSibling) {
      volDisplay.nextElementSibling.textContent = '/' + Math.round(volumeAwal) + 'mL';
    }
  }

  // 7. Nilai Kecepatan Tetesan (TPM) & Mode Infus
  const tpmEl = card.querySelector('[data-role="tpm-value"]');
  if (tpmEl) tpmEl.textContent = Math.round(tpm);

  const modeEl = card.querySelector('[data-role="mode-badge"]');
  if (modeEl) modeEl.textContent = dev.mode || '-';

  // 8. Teks Estimasi Waktu Berakhir
  const estEl = card.querySelector('[data-role="estimasi-value"]');
  if (estEl) estEl.textContent = `${estJam}j ${estMnt}m`;

  // 9. Timestamp Data Terkini Masuk
  const lastEl = card.querySelector('[data-role="last-update"]');
  if (lastEl) lastEl.innerHTML = `<i class="bi bi-clock-history"></i> Update: ${formatTime(lastUpdate)}`;

  // 10. Label Kehadiran Jaringan Perangkat (Online / Offline)
  const onlineBadge = card.querySelector('[data-role="online-badge"]');
  if (onlineBadge) {
    onlineBadge.className = "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wider uppercase border ";
    if (online) {
      onlineBadge.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-200');
      onlineBadge.innerHTML = `<span class="w-1 h-1 rounded-full bg-emerald-500 animate-pulse"></span>Connected`;
    } else {
      onlineBadge.classList.add('bg-slate-100', 'text-slate-500', 'border-slate-200');
      onlineBadge.innerHTML = `<span class="w-1 h-1 rounded-full bg-slate-400"></span>Offline`;
    }
  }

  // 11. Manajemen Tampilan Badge "NURSE CALL" internal di dalam Card
  let nurseBadge = card.querySelector('[data-role="nurse-badge"]');
  if (nurseCall === 1) {
    if (!nurseBadge) {
      // Jika komponen badge belum ada dari server render, kita buat runtime
      const containerBadges = onlineBadge.parentElement;
      nurseBadge = document.createElement('span');
      nurseBadge.setAttribute('data-role', 'nurse-badge');
      nurseBadge.className = "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold text-white tracking-wider uppercase bg-red-500 animate-medical-pulse";
      nurseBadge.innerHTML = `<i class="bi bi-bell-fill text-[8px]"></i> NURSE CALL`;
      containerBadges.appendChild(nurseBadge);
    }
  } else {
    if (nurseBadge) nurseBadge.remove();
  }

  // Jalankan pipeline deteksi alarm suara bawaan
  handleNurseCallState(dev.device_id, nurseCall, pasienName, lokasi);
  handleLowVolumeAlert(dev.device_id, volumeSisa, pasienName, lokasi);
}

// =====================================================
// ===== UPDATE PANEL STATUS MATRIKS UTAMA (ATAS) ======
// =====================================================
function updateTopStats(allData) {
  let totalDevices = allData.length;
  let onlineCount  = 0;
  let lowCount     = 0;
  let nurseCount   = 0;

  allData.forEach(dev => {
    if (isOnline(dev.created_at))              onlineCount++;
    if (parseFloat(dev.persen ?? 0) <= 20)     lowCount++;
    if (parseInt(dev.nurse_call ?? 0) === 1)   nurseCount++;
  });

  const elTotal  = document.getElementById('stat-total');
  const elOnline = document.getElementById('stat-online');
  const elLow    = document.getElementById('stat-low');
  const elNurse  = document.getElementById('stat-nurse');
  const elCard   = document.getElementById('stat-nurse-card');

  if (elTotal)  elTotal.textContent  = totalDevices;
  if (elOnline) elOnline.textContent = onlineCount;
  
  if (elLow) {
    elLow.textContent = lowCount;
    if (lowCount > 0) {
      elLow.className = "text-3xl font-extrabold text-amber-500 mt-1";
    } else {
      elLow.className = "text-3xl font-extrabold text-slate-900 mt-1";
    }
  }
  
  if (elNurse) elNurse.textContent = nurseCount;

  // Efek transisi warna penuh pada Card Ringkasan Nurse Call Atas
  if (elCard) {
    if (nurseCount > 0) {
      elCard.className = "border p-5 rounded-2xl shadow-sm flex items-center justify-between transition-all duration-300 bg-red-500 text-white border-red-600 shadow-lg shadow-red-500/20";
      elCard.querySelector('span').className = "text-xs font-bold uppercase tracking-wider text-red-100";
      elCard.querySelector('.w-12').className = "w-12 h-12 rounded-xl flex items-center justify-center border bg-white/20 border-white/30 text-white animate-bounce";
      if (elNurse) elNurse.className = "text-3xl font-extrabold mt-1 text-white";
    } else {
      elCard.className = "border p-5 rounded-2xl shadow-sm flex items-center justify-between transition-all duration-300 bg-white border-slate-200 text-slate-900";
      elCard.querySelector('span').className = "text-xs font-bold uppercase tracking-wider text-slate-400";
      elCard.querySelector('.w-12').className = "w-12 h-12 rounded-xl flex items-center justify-center border bg-red-50 text-red-500 border-red-100";
      if (elNurse) elNurse.className = "text-3xl font-extrabold mt-1 text-red-500";
    }
  }
}

// =====================================================
// ===== KRONOLOGI LOG TABEL REALTIME (PIPELINE) =======
// =====================================================
async function updateNurseLog() {
  try {
    const res  = await fetch('api/get_nurse_log.php?limit=20&_=' + Date.now(), { cache: 'no-store' });
    const json = await res.json();
    if (json.status !== 'ok') return;

    const tbody = document.getElementById('nurse-log-tbody');
    if (!tbody) return;

    const countEl = document.getElementById('nurse-log-count');
    if (countEl) countEl.textContent = json.total;

    if (json.data.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="3" class="py-12 text-center text-xs font-bold text-slate-400 tracking-wider uppercase">
            <i class="bi bi-shield-check text-2xl block text-slate-300 mb-2"></i>
            Sistem Aman — Belum Ada Log Masuk
          </td>
        </tr>`;
      return;
    }

    tbody.innerHTML = json.data.map(log => `
      <tr class="hover:bg-slate-50/80 transition-colors">
        <td class="py-4 px-6 font-bold text-slate-500 tabular-nums">${formatTime(log.created_at)}</td>
        <td class="py-4 px-6">
          <div class="font-bold text-slate-900">${escHtml(log.pasien ?? 'Pasien Anonim')}</div>
          <div class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
            <i class="bi bi-geo-alt text-[11px]"></i>${escHtml(log.lokasi ?? '-')}
          </div>
        </td>
        <td class="py-4 px-6 font-semibold text-slate-400 font-mono text-xs">${escHtml(log.device_id)}</td>
      </tr>
    `).join('');

  } catch (e) {
    console.warn('Update nurse log gagal:', e);
  }
}

// =====================================================
// ===== GLOBAL ENGINE TRIGGER =========================
// =====================================================
async function refreshAll() {
  try {
    const res  = await fetch('api/get_latest.php?_=' + Date.now(), { cache: 'no-store' });
    const json = await res.json();
    if (json.status !== 'ok') return;
    json.data.forEach(dev => updateCard(dev));
    updateTopStats(json.data);
  } catch (e) {
    console.warn('Refresh gagal:', e);
  }
}

// Polling interval 3 detik standar rumah sakit
setInterval(refreshAll, 3000);
setInterval(updateNurseLog, 3000);

refreshAll();
updateNurseLog();

// =====================================================
// ===== UNLOCK AUDIO PERMISSION SECURITY ==============
// =====================================================
if (window.speechSynthesis) {
  window.speechSynthesis.getVoices();
  window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
}

document.addEventListener('click',      () => getAudioCtx(), { once: true });
document.addEventListener('touchstart', () => getAudioCtx(), { once: true });