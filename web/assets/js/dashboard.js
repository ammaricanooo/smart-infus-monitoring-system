// =====================================================
// DASHBOARD — SSE REALTIME + FALLBACK POLLING
// =====================================================

// ===== JAM REALTIME =====
function updateClock() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2, '0');
  const m = String(now.getMinutes()).padStart(2, '0');
  const s = String(now.getSeconds()).padStart(2, '0');
  const el = document.getElementById('clockText');
  if (el) el.textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);
updateClock();

// ===== FORMAT WAKTU =====
function formatTime(dateStr) {
  if (!dateStr) return '--:--:--';
  const d = new Date(dateStr);
  return [d.getHours(), d.getMinutes(), d.getSeconds()]
    .map(n => String(n).padStart(2, '0')).join(':');
}

const ONLINE_THRESHOLD_MS = 15 * 1000;  // 15 detik — lebih responsif detect offline

// ===== CEK ONLINE =====
function isOnline(dateStr) {
  if (!dateStr) return false;
  return (Date.now() - new Date(dateStr).getTime()) < ONLINE_THRESHOLD_MS;
}

// ===== ESCAPE HTML =====
function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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
    if (nurseLoopStopped) {
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

      const card = document.getElementById('card-' + devId);
      const pasien = card?.dataset.pasien || 'Pasien';
      const lokasi = card?.dataset.lokasi || '';
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

// Buat toast element dan masukkan ke container
// type: 'error'|'warning'|'info'|'success'
function createToast(id, html, autoClose = 10000) {
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
  const card = document.getElementById('card-' + deviceId);

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

// Track devices whose TPM is abnormal (0 = clogged, >80 = too fast)
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


// =====================================================
// ===== UPDATE CARD ===================================
// =====================================================

function updateCard(dev) {
  const card = document.getElementById('card-' + dev.device_id);
  if (!card) return;

  const persen = parseFloat(dev.persen ?? 0);
  const volumeSisa = parseFloat(dev.volume_sisa ?? 0);
  const volumeAwal = parseFloat(dev.volume_awal ?? 500);
  const tpm = parseFloat(dev.tpm ?? 0);
  const nurseCall = parseInt(dev.nurse_call ?? 0);
  const estJam = parseInt(dev.estimasi_jam ?? 0);
  const estMnt = parseInt(dev.estimasi_mnt ?? 0);
  const lastUpdate = dev.created_at || null;
  // Prefer server-side is_online to avoid client clock drift
  const online = (dev.is_online !== undefined) ? Boolean(dev.is_online) : isOnline(lastUpdate);
  const idx = latestDevices.findIndex(
    d => d.device_id === dev.device_id
  );

  if (idx !== -1) {
    latestDevices[idx].is_online = online;
  }
  const pasienName = dev.pasien || card.dataset.pasien || 'Tidak Diketahui';
  const lokasi = dev.lokasi || card.dataset.lokasi || '';

  // Simpan timestamp client saat updateCard dipanggil dengan status online.
  // Ini menghindari bug parsing timezone MySQL datetime di offline-watcher.
  if (online) {
    card.dataset.lastReceivedMs = String(Date.now());
  }
  // Tetap simpan created_at untuk tampilan last-update
  if (lastUpdate) card.dataset.lastCreatedAt = lastUpdate;

  // top accent bar — pakai inline style, tidak bergantung class CSS custom
  const cardTop = card.querySelector('[data-role="card-top"]');
  if (cardTop) {
    if (nurseCall === 1 && online) {
      cardTop.style.cssText = 'height:4px; border-radius:1rem 1rem 0 0; background:linear-gradient(90deg,#dc2626,#f97316);';
    } else {
      cardTop.style.cssText = 'height:4px; border-radius:1rem 1rem 0 0; background:linear-gradient(90deg,#6b2072,#a855f7);';
    }
  }

  // bottle liquid — pakai inline style agar position:absolute bottom:0 tidak hilang
  const bottle = card.querySelector('[data-role="bottle-liquid"]');
  if (bottle) {
    const h = Math.min(100, Math.max(0, persen));
    bottle.style.height = h + '%';
    bottle.style.position = 'absolute';
    bottle.style.bottom = '0';
    bottle.style.left = '0';
    bottle.style.right = '0';
    bottle.style.width = '100%';
    bottle.style.transition = 'height 1s ease-in-out';

    if (!online) {
      // Tetap pertahankan abu-abu jika perangkat mati
      bottle.style.background = '#94a3b8';
      bottle.style.animation = 'none';
    } else if (nurseCall === 1) {
      // Tetap pertahankan merah darurat jika ada Nurse Call
      bottle.style.background = '#ef4444'; // Menggunakan #ef4444 agar seragam dengan warna <=20%
      bottle.style.animation = 'none';
    } else if (persen > 30) {
      // > 75% = Cyan
      bottle.style.background = '#06b6d4';
      bottle.style.animation = 'none';
    } else if (persen > 20) {
      // 20-50% = Amber
      bottle.style.background = '#f59e0b';
      bottle.style.animation = 'none';
    } else {
      // <= 20% = Red + Efek Pulse Kritis
      bottle.style.background = '#ef4444';
      bottle.style.animation = 'pulse 2s cubic-bezier(.4,0,.6,1) infinite';
    }
  }

  // progress bar — pakai inline style agar tidak butuh class bar-blue/bar-orange/bar-red
  const progressBar = card.querySelector('[data-role="progress-bar"]');
  if (progressBar) {
    const w = Math.min(100, Math.max(0, persen));
    progressBar.style.width = w + '%';
    progressBar.style.height = '100%';
    progressBar.style.borderRadius = '9999px';
    progressBar.style.transition = 'width .5s ease-in-out';

    if (!online) {
      progressBar.style.background = '#cbd5e1'; // Abu-abu jika offline
    } else if (nurseCall === 1) {
      progressBar.style.background = '#ef4444'; // Merah jika Nurse Call
    } else if (persen > 30) {
      progressBar.style.background = '#06b6d4'; // > 30% = Cyan
    } else if (persen > 20) {
      progressBar.style.background = '#f59e0b'; // 20-30% = Amber
    } else {
      progressBar.style.background = '#ef4444'; // <= 20% = Red
    }
  }

  const persenEl = card.querySelector('[data-role="persen-text"]');
  if (persenEl) persenEl.textContent = persen.toFixed(0) + '%';

  const lowEl = card.querySelector('[data-role="low-warning"]');
  if (lowEl) {
    lowEl.classList.toggle(
      'hidden',
      !online || persen > 20
    );
  }

  const volDisplay = card.querySelector('[data-role="volume-display"]');
  if (volDisplay) volDisplay.textContent = Math.round(volumeSisa);
  const volSuffix = volDisplay?.nextElementSibling;
  if (volSuffix) volSuffix.textContent = ` / ${Math.round(volumeAwal)} ml`;

  const modeEl = card.querySelector('[data-role="mode-badge"]');
  if (modeEl) modeEl.textContent = dev.mode || '-';

  const tpmEl = card.querySelector('[data-role="tpm-value"]');
  if (tpmEl) tpmEl.textContent = Math.round(tpm);

  const estEl = card.querySelector('[data-role="estimasi-value"]');
  if (estEl) {
    if (online && tpm === 0 && volumeSisa > 0) {
      estEl.innerHTML = `<i class="bi bi-pause-circle mr-1 text-purple-500"></i><span class="text-purple-700 font-bold">Terhenti</span>`;
    } else {
      estEl.innerHTML = `<i class="bi bi-clock-history mr-1 text-slate-400"></i>${estJam}j ${estMnt}m`;
    }
  }

  const lastEl = card.querySelector('[data-role="last-update"]');
  if (lastEl) lastEl.innerHTML = `<i class="bi bi-clock-history mr-1"></i>${formatTime(lastUpdate)}`;

  // online badge
  const onlineBadge = card.querySelector('[data-role="online-badge"]');
  if (onlineBadge) {
    if (online) {
      onlineBadge.className = 'inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-emerald-100 text-emerald-700';
      onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full mr-1.5 bg-emerald-500 animate-pulse"></span>ONLINE';
    } else {
      onlineBadge.className = 'inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-slate-100 text-slate-500';
      onlineBadge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full mr-1.5 bg-slate-400"></span>OFFLINE';
    }
  }

  // nurse call badge & ring — buat/hapus dinamis karena card awal mungkin tidak punya badge ini
  let nurseBadge = card.querySelector('[data-role="nurse-badge"]');
  const showNurse = nurseCall === 1 && online;
  if (showNurse) {
    if (!nurseBadge) {
      nurseBadge = document.createElement('span');
      nurseBadge.setAttribute('data-role', 'nurse-badge');
      // pakai inline style agar tidak butuh Tailwind build untuk class animasi
      nurseBadge.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:700;color:#fff;background:#ef4444;text-transform:uppercase;letter-spacing:.05em;';
      nurseBadge.innerHTML = '<i class="bi bi-bell-fill" style="font-size:8px"></i> NURSE CALL';
      const badgeContainer = card.querySelector('[data-role="online-badge"]')?.parentElement;
      if (badgeContainer) badgeContainer.appendChild(nurseBadge);
    }
  } else {
    if (nurseBadge) nurseBadge.remove();
  }
  // ── border + background card — update realtime sesuai kondisi ──
  if (!online) {
    // Kondisi Offline: Redup/Slate Abu-abu
    card.style.cssText = 'border:1.5px solid #cbd5e1; background:rgba(248,250,252,0.5); border-radius:1rem; padding:1.25rem; position:relative; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; transition:all .3s;';
  } else if (showNurse) {
    // Kondisi Emergency (Nurse Call): Merah Cerah + Ring Shadow Kuat
    card.style.cssText = 'border:1.5px solid #ef4444; background:rgba(254,242,242,0.5); box-shadow:0 0 0 4px rgba(239,68,68,0.1); border-radius:1rem; padding:1.25rem; position:relative; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; transition:all .3s;';
  } else if (persen > 30) {
    // Kondisi > 30%: Cyan
    card.style.cssText = 'border:1.5px solid #a5f3fc; background:rgba(236,254,255,0.4); border-radius:1rem; padding:1.25rem; position:relative; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; transition:all .3s;';
  } else if (persen > 20) {
    // Kondisi 20 - 30%: Amber
    card.style.cssText = 'border:1.5px solid #fde68a; background:rgba(255,251,235,0.4); border-radius:1rem; padding:1.25rem; position:relative; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; transition:all .3s;';
  } else {
    // Kondisi <= 20%: Kritis Volume (Merah Lembut + Ring Shadow Tipis)
    card.style.cssText = 'border:1.5px solid #fca5a5; background:rgba(254,242,242,0.3); box-shadow:0 0 0 4px rgba(239,68,68,0.05); border-radius:1rem; padding:1.25rem; position:relative; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; transition:all .3s;';
  }
  const overlay = card.querySelector('[data-role="nurse-overlay"]');
  if (overlay) overlay.classList.toggle('hidden', !showNurse);

  // ── badge TPM=0 (infus macet) ──
  const isMacet = online && parseFloat(tpm) === 0 && parseFloat(dev.volume_sisa ?? 0) > 0;
  let macetBadge = card.querySelector('[data-role="tpm-zero-badge"]');
  if (isMacet) {
    if (!macetBadge) {
      macetBadge = document.createElement('span');
      macetBadge.setAttribute('data-role', 'tpm-zero-badge');
      macetBadge.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;font-size:9px;font-weight:700;color:#fff;background:#7c3aed;text-transform:uppercase;letter-spacing:.05em;';
      macetBadge.innerHTML = '<i class="bi bi-exclamation-triangle-fill" style="font-size:8px"></i>MACET';
      const onlineBadgeEl = card.querySelector('[data-role="online-badge"]');
      if (onlineBadgeEl?.parentElement) {
        onlineBadgeEl.parentElement.insertBefore(macetBadge, onlineBadgeEl.nextSibling);
      }
    }
  } else {
    if (macetBadge) macetBadge.remove();
  }

  handleNurseCallState(dev.device_id, nurseCall, pasienName, lokasi, online);
  handleLowVolumeAlert(dev.device_id, persen, volumeSisa, pasienName, lokasi, online);
  handleTpmZeroAlert(dev.device_id, tpm, volumeSisa, pasienName, lokasi, online);
}

// =====================================================
// ===== UPDATE STAT CARDS =============================
// =====================================================

function updateTopStats(allData) {
  let onlineCount = 0, lowCount = 0, nurseCount = 0;
  allData.forEach(dev => {
    // Gunakan is_online dari server agar konsisten dengan badge di card
    const online = (dev.is_online !== undefined) ? Boolean(dev.is_online) : isOnline(dev.created_at);
    if (online) onlineCount++;
    if (
      online &&
      parseFloat(dev.persen ?? 0) <= 20
    ) {
      lowCount++;
    }
    if (parseInt(dev.nurse_call ?? 0) === 1 && online) nurseCount++;  // nurse hanya dihitung jika online
  });

  const elOnline = document.getElementById('stat-online');
  const elLow = document.getElementById('stat-low');
  const elNurse = document.getElementById('stat-nurse');
  const elCard = document.getElementById('stat-nurse-card');

  if (elOnline) elOnline.textContent = onlineCount;

  // ── Kritis stat card ──
  if (elLow) {
    elLow.textContent = lowCount;
    elLow.style.color = lowCount > 0 ? '#f59e0b' : '#0f172a';
    // Update icon container beside it
    const lowIco = elLow.closest('div')?.parentElement?.querySelector('.w-12');
    if (lowIco) {
      lowIco.style.cssText = lowCount > 0
        ? 'width:3rem;height:3rem;border-radius:.75rem;display:flex;align-items:center;justify-content:center;background:#fffbeb;border:1px solid #fde68a;color:#f59e0b;'
        : 'width:3rem;height:3rem;border-radius:.75rem;display:flex;align-items:center;justify-content:center;background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;';
    }
  }

  if (elNurse) elNurse.textContent = nurseCount;

  // ── Stat card Panggilan Darurat — update bg/teks/icon realtime ──
  if (elCard) {
    if (nurseCount > 0) {
      elCard.style.cssText = 'background:#ef4444; border-color:#dc2626; box-shadow:0 10px 15px -3px rgba(239,68,68,.25);';
      // label
      const lbl = elCard.querySelector('span.text-xs');
      if (lbl) { lbl.style.color = '#fee2e2'; }
      // angka
      if (elNurse) { elNurse.style.color = '#ffffff'; }
      // sub-text
      const sub = elCard.querySelector('p');
      if (sub) { sub.style.color = '#fecaca'; }
      // icon container
      const ico = elCard.querySelector('.w-12');
      if (ico) {
        ico.style.cssText = 'width:3rem; height:3rem; border-radius:.75rem; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.3); color:#fff; animation:bounce 1s infinite;';
      }
    } else {
      elCard.style.cssText = 'background:#ffffff; border-color:#e2e8f0; box-shadow:0 1px 3px 0 rgba(0,0,0,.06);';
      const lbl = elCard.querySelector('span.text-xs');
      if (lbl) { lbl.style.color = '#94a3b8'; }
      if (elNurse) { elNurse.style.color = '#ef4444'; }
      const sub = elCard.querySelector('p');
      if (sub) { sub.style.color = '#64748b'; }
      const ico = elCard.querySelector('.w-12');
      if (ico) {
        ico.style.cssText = 'width:3rem; height:3rem; border-radius:.75rem; display:flex; align-items:center; justify-content:center; background:#fef2f2; border:1px solid #fee2e2; color:#ef4444; animation:none;';
      }
    }
  }
}

// =====================================================
// ===== UPDATE NURSE LOG TABLE ========================
// =====================================================

function renderNurseLog(data) {
  const tbody = document.getElementById('nurse-log-tbody');
  const countEl = document.getElementById('nurse-log-count');
  if (!tbody) return;
  if (countEl) countEl.textContent = data.length;
  if (data.length === 0) {
    tbody.innerHTML = '<tr class="log-row"><td colspan="3" class="px-6 py-12 text-center text-[11px] font-bold uppercase opacity-40">Belum ada log</td></tr>';
    return;
  }
  tbody.innerHTML = data.map(log => `
    <tr class="log-row border-t log-divider">
      <td class="px-6 py-4 text-xs font-bold">${formatTime(log.created_at)}</td>
      <td class="px-6 py-4">
        <div class="text-sm font-bold">${escHtml(log.pasien ?? 'Unknown')}</div>
        <div class="text-[10px] opacity-50 font-medium mt-0.5">${escHtml(log.lokasi ?? '-')}</div>
      </td>
      <td class="px-6 py-4 text-[10px] font-bold opacity-60">${escHtml(log.device_id)}</td>
    </tr>`).join('');
}

// =====================================================
// ===== SSE ENGINE ====================================
// =====================================================

let sseSource = null;
let sseOk = false;       // true setelah SSE berhasil terima event pertama
let fallbackTimer = null;        // polling fallback jika SSE gagal
let sseIndicator = null;        // elemen indikator koneksi di navbar
let latestDevices = [];


// ── Buat / buat ulang elemen indikator koneksi ────────
function ensureIndicator() {
  sseIndicator = document.getElementById('sse-indicator');
}

function setIndicator(status) {
  const dot = document.getElementById('sse-dot');
  const label = document.getElementById('sse-label');
  const wrap = document.getElementById('sse-indicator');
  if (!dot || !label || !wrap) return;

  // Gunakan inline style agar tidak bergantung pada Tailwind purge/build
  const map = {
    connecting: { bg: '#fefce8', border: '#fde047', dotBg: '#facc15', txt: 'Menghubungkan…', lblColor: '#ca8a04', anim: true },
    live: { bg: '#f0fdf4', border: '#86efac', dotBg: '#22c55e', txt: 'Live', lblColor: '#16a34a', anim: true },
    fallback: { bg: '#fffbeb', border: '#fcd34d', dotBg: '#f59e0b', txt: 'Polling', lblColor: '#d97706', anim: false },
    error: { bg: '#fef2f2', border: '#fca5a5', dotBg: '#ef4444', txt: 'Terputus', lblColor: '#dc2626', anim: false },
  };
  const cfg = map[status] ?? map.connecting;

  wrap.style.background = cfg.bg;
  wrap.style.borderColor = cfg.border;
  wrap.style.display = 'flex';      // override 'hidden' tanpa butuh Tailwind

  dot.style.background = cfg.dotBg;
  dot.style.animation = cfg.anim ? 'pulse 2s cubic-bezier(0.4,0,0.6,1) infinite' : 'none';

  label.textContent = cfg.txt;
  label.style.color = cfg.lblColor;
}

// ── Proses payload SSE 'update' ───────────────────────
function handleSseUpdate(payload) {
  const { devices, nurse_log } = payload;

  if (Array.isArray(devices)) {
    latestDevices = devices;
    devices.forEach(dev => updateCard(dev));
    updateTopStats(latestDevices);
    updateSchedule(latestDevices);
  }

  if (Array.isArray(nurse_log)) {
    renderNurseLog(nurse_log);
  }
}

// ── Buka koneksi SSE ──────────────────────────────────
function openSSE() {
  if (sseSource) { sseSource.close(); sseSource = null; }
  ensureIndicator();
  setIndicator('connecting');

  sseSource = new EventSource('api/sse.php');

  sseSource.addEventListener('update', (e) => {
    try {
      const payload = JSON.parse(e.data);
      handleSseUpdate(payload);
      if (!sseOk) {
        sseOk = true;
        stopFallbackPolling();   // SSE berhasil, hentikan fallback
      }
      setIndicator('live');
    } catch (err) {
      console.warn('SSE parse error:', err);
    }
  });

  sseSource.addEventListener('ping', () => {
    // Keepalive — tidak perlu aksi
  });

  sseSource.onerror = () => {
    setIndicator('error');
    sseSource.close();
    sseSource = null;
    sseOk = false;
    startFallbackPolling();
    // Coba reconnect SSE setelah 5 detik
    setTimeout(openSSE, 5000);
  };
}

// ── Fallback polling (jika SSE tidak tersedia / error) ─
function startFallbackPolling() {
  if (fallbackTimer) return;
  setIndicator('fallback');
  fallbackPollingCycle();   // jalankan segera
  fallbackTimer = setInterval(fallbackPollingCycle, 3000);
}

function stopFallbackPolling() {
  if (fallbackTimer) { clearInterval(fallbackTimer); fallbackTimer = null; }
}

async function fallbackPollingCycle() {
  try {
    const [devRes, logRes] = await Promise.all([
      fetch('api/get_latest.php?_=' + Date.now(), { cache: 'no-store' }),
      fetch('api/get_nurse_log.php?limit=20&_=' + Date.now(), { cache: 'no-store' }),
    ]);
    const devJson = await devRes.json();
    const logJson = await logRes.json();
    if (devJson.status === 'ok') {
      latestDevices = devJson.data;
      devJson.data.forEach(dev => updateCard(dev));
      updateTopStats(latestDevices);
      updateSchedule(latestDevices);
    }
    if (logJson.status === 'ok') renderNurseLog(logJson.data);
  } catch (e) {
    console.warn('Fallback polling gagal:', e);
  }
}

// ── Refresh manual (tombol REFRESH di dashboard) ──────
function refreshAll() {
  if (sseOk) {
    // SSE aktif — snapshot sudah realtime, tapi bisa force poll sekali
    fallbackPollingCycle();
  } else {
    fallbackPollingCycle();
  }
}

// =====================================================
// ===== BOOT ==========================================
// =====================================================

// Cek dukungan SSE
if (typeof EventSource !== 'undefined') {
  openSSE();
} else {
  // Browser tidak support SSE (sangat jarang)
  ensureIndicator();
  startFallbackPolling();
}

// ── Offline-watcher: deteksi timeout dan update stat + visual ────────
// Saat device tidak dapat data 30+ detik, set is_online=false dan update UI.
// Hanya update SEKALI saat transition (tidak ada fluktuasi).
const _deviceOfflineState = new Set();  // Track device yang sudah di-set offline

setInterval(() => {
  let statNeedsUpdate = false;

  document.querySelectorAll('[id^="card-"]').forEach(card => {
    const cardId = card.id.replace('card-', '');
    const lastMs = parseInt(card.dataset.lastReceivedMs || '0');
    if (!lastMs) return;

    const isTimedOut = (Date.now() - lastMs) >= ONLINE_THRESHOLD_MS;

    // Transition: online → offline (belum di-mark sebelumnya)
    if (isTimedOut && !_deviceOfflineState.has(cardId)) {
      _deviceOfflineState.add(cardId);

      // Update latestDevices dengan is_online=false
      const idx = latestDevices.findIndex(dev => dev.device_id === cardId);
      if (idx !== -1) {
        latestDevices[idx] = { ...latestDevices[idx], is_online: false };
        statNeedsUpdate = true;
      }

      // Update card visual (border, bottle, bar, badge, alerts)
      const dev = latestDevices[idx];
      if (dev) updateCard(dev);

      // Clear alert states
      if (nurseActiveSet.has(cardId)) {
        nurseActiveSet.delete(cardId);
        if (nurseActiveSet.size === 0) { stopNurseLoop(); dismissNurseToast(); }
      }
      tpmZeroSince.delete(cardId);
      tpmAlertedSet.delete(cardId);
      tpmHighAlerted.delete(cardId);
      clearTpmZeroToast(cardId);
      removeToast('low-vol-toast-' + cardId);
      lowVolAlertedSet.delete(cardId);
    }
    // Transition: offline → online (dapat data baru)
    else if (!isTimedOut && _deviceOfflineState.has(cardId)) {
      _deviceOfflineState.delete(cardId);
    }
  });

  // Update stat counter jika ada perubahan
  if (statNeedsUpdate) {
    updateTopStats(latestDevices);
  }
}, 1000);

// ── Immediate first-fetch — so status is correct on first render ──
// This fires immediately on boot and corrects any stale PHP-rendered values.
fallbackPollingCycle();

// ── Audio unlock ────────────
if (window.speechSynthesis) {
  window.speechSynthesis.getVoices();
  window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
}
document.addEventListener('click', () => getAudioCtx(), { once: true });
document.addEventListener('touchstart', () => getAudioCtx(), { once: true });

// =====================================================
// ===== JADWAL PENGGANTIAN INFUS ======================
// =====================================================

function updateSchedule(allData) {
  const tbody   = document.getElementById('sched-tbody');
  const countEl = document.getElementById('sched-count');
  if (!tbody) return;

  // Filter: online & volume > 0
  const active = allData.filter(dev => {
    const online = (dev.is_online !== undefined)
      ? Boolean(dev.is_online)
      : isOnline(dev.created_at);
    return online && parseFloat(dev.persen ?? 0) > 0;
  });

  if (countEl) countEl.textContent = active.length + ' device aktif';

  if (active.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="padding:48px 0;text-align:center;font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">
      <i class="bi bi-clock" style="font-size:2rem;display:block;color:#cbd5e1;margin-bottom:8px;"></i>
      Belum ada device online dengan cairan aktif
    </td></tr>`;
    return;
  }

  // Sort: running devices first by estimasi total menit terkecil; macet devices at the end
  active.sort((a, b) => {
    const isMacetA = parseFloat(a.tpm ?? 0) === 0 && parseFloat(a.volume_sisa ?? 0) > 0;
    const isMacetB = parseFloat(b.tpm ?? 0) === 0 && parseFloat(b.volume_sisa ?? 0) > 0;

    if (isMacetA && !isMacetB) return 1;
    if (!isMacetA && isMacetB) return -1;
    if (isMacetA && isMacetB) {
      return (parseFloat(a.volume_sisa ?? 0) - parseFloat(b.volume_sisa ?? 0));
    }

    const mA = (parseInt(a.estimasi_jam ?? 0) * 60) + parseInt(a.estimasi_mnt ?? 0);
    const mB = (parseInt(b.estimasi_jam ?? 0) * 60) + parseInt(b.estimasi_mnt ?? 0);
    return mA - mB;
  });

  const pad = n => String(n).padStart(2, '0');

  tbody.innerHTML = active.map((dev, i) => {
    const estJam = parseInt(dev.estimasi_jam ?? 0);
    const estMnt = parseInt(dev.estimasi_mnt ?? 0);
    const total  = estJam * 60 + estMnt;
    const persen = parseFloat(dev.persen ?? 0);
    const vol    = parseFloat(dev.volume_sisa ?? 0);
    const tpm    = parseFloat(dev.tpm ?? 0);
    const isMacet = tpm === 0 && vol > 0;

    let targetStr, targetDay, estDisplay, estSub, targetSub;
    let urgBg, urgBorder, urgText, urgBadgeBg, urgBadgeText, urgLabel, urgIcon;

    if (isMacet) {
      urgBg = '#faf5ff'; urgBorder = '#c084fc'; urgText = '#7c3aed';
      urgBadgeBg = '#f3e8ff'; urgBadgeText = '#7c3aed';
      urgLabel = 'MACET'; urgIcon = 'exclamation-triangle-fill';
      estDisplay = '<span style="color:#7c3aed;font-weight:900;">Terhenti</span>';
      estSub = 'aliran macet (0 TPM)';
      targetStr = '<span style="color:#94a3b8;font-weight:700;">—</span>';
      targetDay = '';
      targetSub = 'aliran terhenti';
    } else {
      // Target waktu penggantian
      const targetDate = new Date(Date.now() + total * 60 * 1000);
      targetStr  = `${pad(targetDate.getHours())}:${pad(targetDate.getMinutes())}`;
      const today      = new Date();
      const isToday    = targetDate.getDate()    === today.getDate()
                      && targetDate.getMonth()   === today.getMonth()
                      && targetDate.getFullYear()=== today.getFullYear();
      targetDay  = isToday ? ''
        : `<span style="font-size:10px;color:#94a3b8;margin-left:4px;">${pad(targetDate.getDate())}/${pad(targetDate.getMonth()+1)}</span>`;
      estDisplay  = (estJam > 0 ? estJam + 'j ' : '') + estMnt + 'm';
      estSub = 'dari sekarang';
      targetSub = 'estimasi habis';

      // Urgensi
      if (total <= 15) {
        urgBg = '#fef2f2'; urgBorder = '#fca5a5'; urgText = '#dc2626';
        urgBadgeBg = '#fee2e2'; urgBadgeText = '#b91c1c';
        urgLabel = 'SEGERA'; urgIcon = 'exclamation-triangle-fill';
      } else if (total <= 45) {
        urgBg = '#fffbeb'; urgBorder = '#fcd34d'; urgText = '#d97706';
        urgBadgeBg = '#fef3c7'; urgBadgeText = '#92400e';
        urgLabel = 'SIAPKAN'; urgIcon = 'clock-fill';
      } else {
        urgBg = '#fff'; urgBorder = '#e2e8f0'; urgText = '#64748b';
        urgBadgeBg = '#f0fdf4'; urgBadgeText = '#166534';
        urgLabel = 'NORMAL'; urgIcon = 'check-circle-fill';
      }
    }

    const bottleColor = persen > 30 ? '#06b6d4' : (persen > 20 ? '#f59e0b' : '#ef4444');

    return `
      <tr id="sched-row-${escHtml(dev.device_id)}"
          style="background:${urgBg};border-left:3px solid ${urgBorder};transition:background .3s;">
        <td style="padding:12px 20px;">
          <span style="font-size:12px;font-weight:900;color:#64748b;">${i + 1}</span>
        </td>
        <td style="padding:12px 20px;">
          <div style="font-size:13px;font-weight:700;color:#0f172a;">${escHtml(dev.pasien || '—')}</div>
          <div style="font-size:11px;color:#64748b;margin-top:2px;display:flex;align-items:center;gap:4px;">
            <i class="bi bi-geo-alt" style="font-size:10px;color:#94a3b8;"></i>${escHtml(dev.lokasi || '—')}
            <span style="color:#cbd5e1;margin:0 2px;">·</span>
            <span style="font-family:monospace;color:#94a3b8;font-size:10px;">${escHtml(dev.device_id)}</span>
          </div>
        </td>
        <td style="padding:12px 20px;">
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:14px;height:28px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:3px 3px 6px 6px;position:relative;overflow:hidden;flex-shrink:0;">
              <div style="position:absolute;bottom:0;left:0;right:0;height:${Math.min(100,persen)}%;background:${bottleColor};transition:height .5s;"></div>
            </div>
            <div>
              <div style="font-size:13px;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums;">
                ${Math.round(vol)} <span style="font-size:11px;font-weight:500;color:#94a3b8;">ml</span>
              </div>
              <div style="font-size:10px;font-weight:700;color:${urgText};">${persen.toFixed(0)}%</div>
            </div>
          </div>
        </td>
        <td style="padding:12px 20px;">
          <div style="font-size:13px;font-weight:900;color:#0f172a;font-variant-numeric:tabular-nums;">${estDisplay}</div>
          <div style="font-size:10px;color:#94a3b8;margin-top:2px;">${estSub}</div>
        </td>
        <td style="padding:12px 20px;">
          <div style="font-size:13px;font-weight:700;color:#0f172a;font-variant-numeric:tabular-nums;">
            ${targetStr}${targetDay}
          </div>
          <div style="font-size:10px;color:#94a3b8;margin-top:2px;">${targetSub}</div>
        </td>
        <td style="padding:12px 20px;">
          <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:900;letter-spacing:.04em;background:${urgBadgeBg};color:${urgBadgeText};">
            <i class="bi bi-${urgIcon}" style="font-size:9px;"></i> ${urgLabel}
          </span>
        </td>
        <td style="padding:12px 20px;text-align:right;">
          <a href="detail.php?id=${encodeURIComponent(dev.device_id)}"
             style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:10px;font-size:11px;font-weight:700;background:#0f172a;color:#fff;text-decoration:none;">
            <i class="bi bi-bar-chart-fill"></i> Detail
          </a>
        </td>
      </tr>`;
  }).join('');
}
