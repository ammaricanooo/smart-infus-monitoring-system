(function() {
  // 1. Cek apakah ini dashboard utama (index.php atau root)
  // Halaman index.php sudah memiliki penanganan suara & toast sendiri.
  const isMainDashboard = (window.activePage === 'dashboard' && !window.isDetailPage);
  if (isMainDashboard) {
    return;
  }

  // Tandai bahwa notifikasi global aktif untuk dideteksi oleh detail.js
  window.globalNotificationsActive = true;

  // ===== CONFIGURATION & STATE =====
  const nurseActiveSet = new Set();
  let audioCtx = null;
  let ringtoneAudio = null;
  let _audioLocked = false;
  const _audioQueue = [];
  let nurseLoopRunning = false;
  let nurseLoopStopped = false;

  const lowVolAlertedSet = new Set();
  const tpmZeroSince = new Map();
  const tpmAlertedSet = new Set();
  const tpmHighAlerted = new Set();

  let sseSource = null;
  let sseOk = false;
  let fallbackTimer = null;

  // ===== JAM & AUDIO UTILITIES =====
  function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

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

  function getIdVoice() {
    if (!window.speechSynthesis) return null;
    const voices = window.speechSynthesis.getVoices();
    return voices.find(v => v.lang === 'id-ID' && v.localService)
      || voices.find(v => v.lang === 'id-ID')
      || voices.find(v => v.lang.startsWith('id'))
      || null;
  }

  function playRingtone() {
    return new Promise(resolve => {
      if (nurseLoopStopped) { resolve(); return; }
      getAudioCtx();
      if (!ringtoneAudio) {
        ringtoneAudio = new Audio('assets/nurse-call.mp3');
        ringtoneAudio.preload = 'auto';
      }
      let done = false;
      const finish = () => { if (!done) { done = true; resolve(); } };

      ringtoneAudio.pause();
      ringtoneAudio.currentTime = 0;
      ringtoneAudio.onended = finish;
      ringtoneAudio.onerror = finish;

      const timeout = ringtoneAudio.duration > 0
        ? (ringtoneAudio.duration * 1000 + 500)
        : 10000;
      setTimeout(finish, timeout);

      if (nurseLoopStopped) { finish(); return; }
      ringtoneAudio.play().catch(finish);
    });
  }

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
      setTimeout(finish, 15000);

      window.speechSynthesis.speak(utt);
    });
  }

  // ===== ALERT HANDLERS =====
  async function nurseAlertLoop() {
    if (nurseLoopRunning) return;
    nurseLoopRunning = true;
    nurseLoopStopped = false;

    while (nurseActiveSet.size > 0 && !nurseLoopStopped) {
      const devIds = Array.from(nurseActiveSet);
      for (const devId of devIds) {
        if (nurseActiveSet.size === 0 || nurseLoopStopped) break;

        const devInfo = getDeviceCachedData(devId);
        const pasien = devInfo?.pasien || 'Pasien';
        const lokasi = devInfo?.lokasi || '';
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
        } catch (e) {
          console.warn('nurse loop err:', e);
        } finally {
          _audioLocked = false;
          _drainAudioQueue();
        }
      }
    }

    nurseLoopRunning = false;
  }

  // Expose reset trigger globally for integration
  window.globalStopNurseLoop = function() {
    stopNurseLoop();
  };

  function stopNurseLoop() {
    nurseLoopStopped = true;
    nurseLoopRunning = false;
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

  function handleLowVolumeAlert(deviceId, volumeSisa, pasienName, lokasi, online) {
    if (!online) {
      lowVolAlertedSet.delete(deviceId);
      removeToast('low-vol-toast-' + deviceId);
      return;
    }
    const vol = parseFloat(volumeSisa) || 0;
    if (vol > 0 && vol <= 20) {
      if (!lowVolAlertedSet.has(deviceId)) {
        lowVolAlertedSet.add(deviceId);
        speakLowVolume(pasienName, lokasi, vol);
        showLowVolumeToast(pasienName, lokasi, vol, deviceId);
      }
    } else {
      lowVolAlertedSet.delete(deviceId);
    }
  }

  function speakLowVolume(pasienName, lokasi, vol) {
    if (!window.speechSynthesis) return;
    if (nurseActiveSet.size > 0) return;
    const lokasiText = lokasi ? ` di ${lokasi}` : '';
    withAudio(() => speak(
      `Perhatian. Cairan infus pasien ${pasienName}${lokasiText} hampir habis. Sisa ${Math.round(vol)} mililiter. Segera ganti.`
    ));
  }

  function speakTpmAlert(pasienName, lokasi, message) {
    if (!window.speechSynthesis) return;
    if (nurseActiveSet.size > 0) return;
    const lokasiText = lokasi ? ` di ${lokasi}` : '';
    withAudio(() => speak(`Perhatian. Pasien ${pasienName}${lokasiText}. ${message}`));
  }

  function handleTpmZeroAlert(deviceId, tpm, volumeSisa, pasienName, lokasi, online) {
    const t = parseFloat(tpm) || 0;
    const vol = parseFloat(volumeSisa) || 0;

    if (!online) {
      tpmZeroSince.delete(deviceId);
      tpmAlertedSet.delete(deviceId);
      tpmHighAlerted.delete(deviceId);
      clearTpmZeroToast(deviceId);
      return;
    }

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

    if (t === 0 && vol > 0) {
      if (!tpmZeroSince.has(deviceId)) {
        tpmZeroSince.set(deviceId, Date.now());
      }
      const elapsed = Date.now() - tpmZeroSince.get(deviceId);
      if (elapsed >= 10000 && !tpmAlertedSet.has(deviceId)) {
        tpmAlertedSet.add(deviceId);
        const msg = `Infus kemungkinan macet (0 tpm). Sisa cairan ${Math.round(vol)} ml. Harap periksa segera.`;
        showTpmToast(pasienName, lokasi, deviceId, msg, 'purple');
        speakTpmAlert(pasienName, lokasi, `Infus macet. Sisa cairan ${Math.round(vol)} mililiter. Harap periksa segera.`);
      }
    } else {
      tpmZeroSince.delete(deviceId);
      tpmAlertedSet.delete(deviceId);
      clearTpmZeroToast(deviceId);
    }
  }

  function clearTpmZeroToast(deviceId) {
    removeToast('tpm-toast-' + deviceId);
    removeToast('tpm-toast-' + deviceId + '-high');
  }

  // ===== TOAST UTILITIES =====
  function getToastContainer() {
    let c = document.getElementById('toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toast-container';
      c.style.cssText = [
        'position:fixed',
        'z-index:9999',
        'display:flex',
        'flex-direction:column',
        'gap:8px',
        'pointer-events:none',
        'top:72px', 'right:16px', 'left:auto', 'bottom:auto',
        'width:320px',
        'max-width:calc(100vw - 32px)',
      ].join(';');
      if (window.innerWidth < 640) {
        c.style.top = 'auto';
        c.style.bottom = '72px';
        c.style.right = '8px';
        c.style.left = '8px';
        c.style.width = 'auto';
      }
      document.body.appendChild(c);
    }
    return c;
  }

  function createToast(id, html, autoClose = 10000) {
    const old = document.getElementById(id);
    if (old) old.remove();

    const wrap = document.createElement('div');
    wrap.id = id;
    wrap.style.cssText = 'pointer-events:all; transition:opacity .3s, transform .3s; opacity:0; transform:translateY(8px);';
    wrap.innerHTML = html;

    const container = getToastContainer();
    container.appendChild(wrap);

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

  function escHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function toastHTML(icon, bgColor, borderColor, labelColor, label, title, body, locationText, closeCallStr) {
    return `
      <div style="background:${bgColor};border:1px solid ${borderColor};border-radius:14px;padding:14px;box-shadow:0 10px 25px rgba(0,0,0,.12); font-family: 'Plus Jakarta Sans', sans-serif;">
        <div style="display:flex;align-items:flex-start;gap:10px;">
          <div style="width:36px;height:36px;background:${labelColor};color:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:14px;">
            <i class="bi bi-${icon}"></i>
          </div>
          <div style="flex:1;min-width:0;text-align:left;">
            <div style="font-size:10px;font-weight:900;letter-spacing:.05em;color:${labelColor};text-transform:uppercase;">${label}</div>
            <div style="font-size:13px;font-weight:800;color:#0f172a;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${title}</div>
            <div style="font-size:11px;color:#475569;margin-top:2px;">${body}</div>
            ${locationText ? `<div style="font-size:11px;color:${labelColor};font-weight:700;margin-top:6px;display:flex;align-items:center;gap:4px;"><i class="bi bi-geo-alt-fill"></i>${locationText}</div>` : ''}
          </div>
          <button onclick="${closeCallStr}" style="color:#94a3b8;background:none;border:none;cursor:pointer;padding:2px;flex-shrink:0;font-size:12px;">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>`;
  }

  function showNurseToast(pasienName, lokasi, deviceId) {
    const html = toastHTML(
      'bell-fill', '#fef2f2', '#fca5a5', '#ef4444',
      'Emergency Alert',
      escHtml(pasienName),
      'Memerlukan bantuan perawat segera!',
      escHtml(lokasi || 'Lokasi tidak diketahui'),
      'window.globalDismissNurseToast()'
    );
    createToast('nurse-toast', html, 0);
  }

  window.globalDismissNurseToast = function() { removeToast('nurse-toast'); };
  window.globalRemoveToast = function(id) { removeToast(id); };

  function showLowVolumeToast(pasienName, lokasi, vol, deviceId) {
    const html = toastHTML(
      'droplet-half', '#fffbeb', '#fcd34d', '#d97706',
      'Volume Warning',
      escHtml(pasienName),
      `Sisa <b style="color:#dc2626">${Math.round(vol)} ml</b> — segera ganti!`,
      escHtml(lokasi || 'Lokasi tidak diketahui'),
      `window.globalRemoveToast('low-vol-toast-${deviceId}')`
    );
    createToast('low-vol-toast-' + deviceId, html, 12000);
  }

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
      `window.globalRemoveToast('tpm-toast-${toastKey}')`
    );
    createToast('tpm-toast-' + toastKey, html, 14000);
  }

  // ===== DEVICE DATA CACHE =====
  let cachedDevices = [];
  function getDeviceCachedData(deviceId) {
    return cachedDevices.find(d => d.device_id === deviceId);
  }

  // ===== REALTIME DATA PROCESSOR =====
  const ONLINE_THRESHOLD_MS = 15 * 1000;
  function isOnline(dateStr) {
    if (!dateStr) return false;
    return (Date.now() - new Date(dateStr).getTime()) < ONLINE_THRESHOLD_MS;
  }

  function processDeviceUpdates(devices) {
    if (!Array.isArray(devices)) return;
    cachedDevices = devices;

    devices.forEach(dev => {
      const lastUpdate = dev.created_at || dev.last_update;
      const online = (dev.is_online !== undefined) ? dev.is_online : isOnline(lastUpdate);

      const nurseCall = parseInt(dev.nurse_call || 0);
      const volumeSisa = parseFloat(dev.volume_sisa || 0);
      const tpm = parseFloat(dev.tpm || 0);
      const pasienName = dev.pasien || 'Pasien';
      const lokasi = dev.lokasi || '';

      handleNurseCallState(dev.device_id, nurseCall, pasienName, lokasi, online);
      handleLowVolumeAlert(dev.device_id, volumeSisa, pasienName, lokasi, online);
      handleTpmZeroAlert(dev.device_id, tpm, volumeSisa, pasienName, lokasi, online);
    });
  }

  // ===== SSE & POLLING SYSTEM =====
  function openSSE() {
    if (sseSource) { sseSource.close(); sseSource = null; }
    sseSource = new EventSource('api/sse.php');

    sseSource.addEventListener('update', (e) => {
      try {
        const payload = JSON.parse(e.data);
        if (payload && Array.isArray(payload.devices)) {
          processDeviceUpdates(payload.devices);
        }
        if (!sseOk) {
          sseOk = true;
          stopFallbackPolling();
        }
      } catch (err) {
        console.warn('Global SSE parse error:', err);
      }
    });

    sseSource.onerror = () => {
      sseSource.close();
      sseSource = null;
      sseOk = false;
      startFallbackPolling();
      setTimeout(openSSE, 5000);
    };
  }

  function startFallbackPolling() {
    if (fallbackTimer) return;
    fallbackPollingCycle();
    fallbackTimer = setInterval(fallbackPollingCycle, 3000);
  }

  function stopFallbackPolling() {
    if (fallbackTimer) { clearInterval(fallbackTimer); fallbackTimer = null; }
  }

  async function fallbackPollingCycle() {
    try {
      const res = await fetch('api/get_latest.php?_=' + Date.now(), { cache: 'no-store' });
      const json = await res.json();
      if (json.status === 'ok' && Array.isArray(json.data)) {
        processDeviceUpdates(json.data);
      }
    } catch (e) {
      console.warn('Global Fallback polling gagal:', e);
    }
  }

  // Boot
  if (typeof EventSource !== 'undefined') {
    openSSE();
  } else {
    startFallbackPolling();
  }

  // Check audio bypass on interaction
  const enableAudio = () => {
    getAudioCtx();
    document.removeEventListener('click', enableAudio);
    document.removeEventListener('touchstart', enableAudio);
  };
  document.addEventListener('click', enableAudio);
  document.addEventListener('touchstart', enableAudio);
})();
