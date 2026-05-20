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

// ===== FORMAT TANGGAL =====
function formatDate(dateStr) {
  if (!dateStr) return 'Belum ada data';
  const d  = new Date(dateStr);
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const yy = d.getFullYear();
  const hh = String(d.getHours()).padStart(2, '0');
  const mi = String(d.getMinutes()).padStart(2, '0');
  const ss = String(d.getSeconds()).padStart(2, '0');
  return `${dd}/${mm}/${yy} ${hh}:${mi}:${ss}`;
}

// ===== FORMAT WAKTU SINGKAT =====
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

// ===== ESCAPE HTML =====
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// =====================================================
// ===== NURSE CALL ALERT SYSTEM ===================
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

// ===== TOAST NOTIFIKASI =====
function showNurseToast(pasienName, lokasi, deviceId) {
  const old = document.getElementById('nurse-toast');
  if (old) old.remove();

  const lokasiLabel = lokasi ? lokasi : 'Lokasi tidak diketahui';

  const toast = document.createElement('div');
  toast.id = 'nurse-toast';
  toast.innerHTML = `
    <div class="fixed bottom-6 left-6 right-6 sm:left-auto sm:right-6 sm:w-80 z-50 p-4 bg-red-50 border border-red-200 rounded-lg shadow-lg">
      <div class="flex items-start gap-3">
        <div class="text-red-600 text-2xl flex-shrink-0">
          <i class="bi bi-bell-fill"></i>
        </div>
        <div class="flex-1">
          <div class="font-bold text-red-800">🚨 NURSE CALL</div>
          <div class="text-red-700 text-sm mt-1">
            <strong>${escHtml(pasienName)}</strong> membutuhkan bantuan!
          </div>
          <div class="text-red-600 text-xs mt-1 flex items-center gap-1">
            <i class="bi bi-geo-alt-fill"></i>
            <span>${escHtml(lokasiLabel)}</span>
          </div>
        </div>
        <button class="text-red-400 hover:text-red-600" onclick="dismissNurseToast()">
          <i class="bi bi-x-lg"></i>
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
    t.classList.add('opacity-0', 'transition-opacity');
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

// Simpan state per device agar tidak spam TTS
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
    // reset jika sudah diisi ulang / volume normal
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
    <div class="fixed top-6 right-6 w-80 z-50 p-4 bg-orange-50 border border-orange-300 rounded-xl shadow-xl">
      <div class="flex items-start gap-3">
        <div class="text-orange-500 text-2xl flex-shrink-0">
          <i class="bi bi-droplet-half"></i>
        </div>
        <div class="flex-1">
          <div class="font-black text-orange-800 text-sm">⚠️ VOLUME KRITIS</div>
          <div class="text-orange-700 text-xs mt-1">
            <strong>${escHtml(pasienName)}</strong> — sisa <strong>${Math.round(vol)} ml</strong>. Segera ganti!
          </div>
          <div class="text-orange-600 text-xs mt-1 flex items-center gap-1">
            <i class="bi bi-geo-alt-fill"></i>
            <span>${escHtml(lokasiLabel)}</span>
          </div>
        </div>
        <button class="text-orange-400 hover:text-orange-600 text-xs" onclick="document.getElementById('${toastId}').remove()">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
  `;
  document.body.appendChild(toast);

  setTimeout(() => {
    const el = document.getElementById(toastId);
    if (el) { el.style.opacity = '0'; el.style.transition = 'opacity 0.4s'; setTimeout(() => el.remove(), 400); }
  }, 12000);
}

// =====================================================
// ===== UPDATE CARD ===================================
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

  // --- top accent bar ---
  const cardTop = card.querySelector('[data-role="card-top"]');
  if (cardTop) {
    cardTop.className = 'device-card-top' + (nurseCall === 1 ? ' danger' : '');
  }

  // --- bottle liquid ---
  const bottle = card.querySelector('[data-role="bottle-liquid"]');
  if (bottle) {
    bottle.style.height = Math.min(100, Math.max(0, persen)) + '%';
    bottle.className = 'infusion-liquid transition-all duration-1000';
    if (persen > 50)       bottle.classList.add('bg-blue-400');
    else if (persen > 20)  bottle.classList.add('bg-orange-400');
    else                   bottle.classList.add('bg-red-500', 'animate-pulse');
  }

  // --- progress bar ---
  const bar = card.querySelector('[data-role="progress-bar"]');
  if (bar) {
    bar.style.width = Math.min(100, Math.max(0, persen)) + '%';
    bar.className = 'h-full rounded-full transition-all duration-500';
    if (persen > 50)       bar.classList.add('bar-blue');
    else if (persen > 20)  bar.classList.add('bar-orange');
    else                   bar.classList.add('bar-red');
  }

  // --- persen text ---
  const persenEl = card.querySelector('[data-role="persen-text"]');
  if (persenEl) persenEl.textContent = persen.toFixed(0) + '%';

  // --- low volume warning ---
  const lowEl = card.querySelector('[data-role="low-warning"]');
  if (lowEl) lowEl.classList.toggle('hidden', persen > 20);

  // --- volume sisa / volume awal ---
  const volDisplay = card.querySelector('[data-role="volume-display"]');
  if (volDisplay) volDisplay.textContent = Math.round(volumeSisa);
  const volSuffix = volDisplay ? volDisplay.nextElementSibling : null;
  if (volSuffix) volSuffix.textContent = ` / ${Math.round(volumeAwal)} ml`;

  // --- mode badge ---
  const modeEl = card.querySelector('[data-role="mode-badge"]');
  if (modeEl) modeEl.textContent = dev.mode || '-';

  // --- TPM ---
  const tpmEl = card.querySelector('[data-role="tpm-value"]');
  if (tpmEl) tpmEl.textContent = Math.round(tpm);

  // --- Estimasi ---
  const estEl = card.querySelector('[data-role="estimasi-value"]');
  if (estEl) estEl.innerHTML = `<i class="bi bi-clock-history mr-1 text-slate-400"></i>${estJam}j ${estMnt}m`;

  // --- Last update ---
  const lastEl = card.querySelector('[data-role="last-update"]');
  if (lastEl) lastEl.innerHTML = `<i class="bi bi-clock-history mr-1"></i>${formatTime(lastUpdate)}`;

  // --- Online badge ---
  const onlineBadge = card.querySelector('[data-role="online-badge"]');
  if (onlineBadge) {
    if (online) {
      onlineBadge.className = 'inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-emerald-100 text-emerald-700';
      onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full mr-1.5 bg-emerald-500"></span>ONLINE';
    } else {
      onlineBadge.className = 'inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-slate-100 text-slate-500';
      onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full mr-1.5 bg-slate-400"></span>OFFLINE';
    }
  }

  // --- Nurse Call badge ---
  const nurseBadge = card.querySelector('[data-role="nurse-badge"]');
  if (nurseBadge) nurseBadge.classList.toggle('hidden', nurseCall !== 1);

  // --- Nurse Call ring & overlay ---
  card.classList.toggle('nurse-ring', nurseCall === 1);
  const overlay = card.querySelector('[data-role="nurse-overlay"]');
  if (overlay) overlay.classList.toggle('hidden', nurseCall !== 1);

  // --- Handle nurse call alert ---
  handleNurseCallState(dev.device_id, nurseCall, pasienName, lokasi);

  // --- Handle low volume TTS alert (0 < vol <= 10ml) ---
  handleLowVolumeAlert(dev.device_id, volumeSisa, pasienName, lokasi);
}

// =====================================================
// ===== UPDATE STAT CARDS ATAS ========================
// =====================================================

function updateTopStats(allData) {
  let onlineCount = 0;
  let lowCount    = 0;
  let nurseCount  = 0;

  allData.forEach(dev => {
    if (isOnline(dev.created_at))              onlineCount++;
    if (parseFloat(dev.persen ?? 0) <= 20)     lowCount++;
    if (parseInt(dev.nurse_call ?? 0) === 1)   nurseCount++;
  });

  const elOnline = document.getElementById('stat-online');
  const elLow    = document.getElementById('stat-low');
  const elNurse  = document.getElementById('stat-nurse');
  const elCard   = document.getElementById('stat-nurse-card');

  if (elOnline) elOnline.textContent = onlineCount;
  if (elLow)    elLow.textContent    = lowCount;
  if (elNurse)  elNurse.textContent  = nurseCount;

  if (elCard) elCard.classList.toggle('pulse-danger', nurseCount > 0);
}

// =====================================================
// ===== UPDATE NURSE LOG (REALTIME) ===================
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
      tbody.innerHTML = '<tr class="log-row"><td colspan="3" class="px-6 py-12 text-center text-[11px] font-bold uppercase opacity-40">Belum ada log</td></tr>';
      return;
    }

    tbody.innerHTML = json.data.map(log => `
      <tr class="log-row border-t log-divider">
        <td class="px-6 py-4 text-xs font-bold">${formatTime(log.created_at)}</td>
        <td class="px-6 py-4">
          <div class="text-sm font-bold">${escHtml(log.pasien ?? 'Unknown')}</div>
          <div class="text-[10px] opacity-50 font-medium mt-0.5">${escHtml(log.lokasi ?? '-')}</div>
        </td>
        <td class="px-6 py-4 text-[10px] font-bold opacity-60">${escHtml(log.device_id)}</td>
      </tr>
    `).join('');

  } catch (e) {
    console.warn('Update nurse log gagal:', e);
  }
}

// =====================================================
// ===== MAIN REFRESH ==================================
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

// =====================================================
// ===== INTERVAL ======================================
// =====================================================

setInterval(refreshAll,          3000);
setInterval(updateNurseLog,      3000);   // realtime

refreshAll();
updateNurseLog();

// =====================================================
// ===== INISIALISASI VOICES & AUDIO UNLOCK ============
// =====================================================

if (window.speechSynthesis) {
  window.speechSynthesis.getVoices();
  window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
}

document.addEventListener('click',      () => getAudioCtx(), { once: true });
document.addEventListener('touchstart', () => getAudioCtx(), { once: true });
