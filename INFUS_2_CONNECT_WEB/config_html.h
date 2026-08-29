#pragma once
// =====================================================
// CONFIG PAGE HTML — disimpan terpisah agar Arduino
// preprocessor tidak salah parse JS di dalam raw string
// =====================================================

const char CONFIG_HTML[] PROGMEM = R"rawhtml(
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Smart Infus - Konfigurasi</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
     background:#f1f5f9;min-height:100vh;display:flex;align-items:center;
     justify-content:center;padding:16px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.10);
      padding:28px 24px;max-width:400px;width:100%}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:24px}
.logo{width:40px;height:40px;background:#6b2072;border-radius:10px;
      display:flex;align-items:center;justify-content:center}
.logo svg{fill:#fff;width:20px;height:20px}
.brand-text h1{font-size:13px;font-weight:900;color:#0f172a;
               text-transform:uppercase;letter-spacing:.05em}
.brand-text p{font-size:10px;font-weight:700;color:#6b2072;
              text-transform:uppercase;letter-spacing:.08em}
h2{font-size:14px;font-weight:800;color:#0f172a;margin-bottom:4px}
.sub{font-size:11px;color:#94a3b8;margin-bottom:20px}
label{display:block;font-size:10px;font-weight:700;color:#94a3b8;
      text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px}
.field{margin-bottom:16px}
.row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px}
.row3 .field{margin-bottom:0}
input{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;
      font-size:13px;font-weight:600;color:#0f172a;outline:none;background:#f8fafc;
      transition:border-color .2s,box-shadow .2s}
input:focus{border-color:#6b2072;background:#fff;
            box-shadow:0 0 0 4px rgba(107,32,114,.08)}
.hint{font-size:10px;color:#94a3b8;margin-top:4px}
hr{border:none;border-top:1px solid #f1f5f9;margin:18px 0}
button{width:100%;padding:12px;background:#6b2072;color:#fff;border:none;
       border-radius:10px;font-size:13px;font-weight:800;cursor:pointer;
       letter-spacing:.04em;text-transform:uppercase;
       box-shadow:0 4px 12px rgba(107,32,114,.25);transition:background .2s}
button:hover{background:#541859}
button:active{transform:scale(.98)}
button:disabled{background:#a78baa;cursor:not-allowed}
.alert{padding:10px 14px;border-radius:10px;font-size:12px;font-weight:700;
       margin-bottom:16px;display:none}
.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
.err{background:#fef2f2;color:#991b1b;border:1px solid #fca5a5}
.badge{display:inline-block;padding:3px 10px;border-radius:6px;font-size:10px;
       font-weight:800;text-transform:uppercase;
       background:#fef9c3;color:#854d0e;border:1px solid #fde68a}
.section-title{font-size:11px;font-weight:800;color:#6b2072;
               text-transform:uppercase;letter-spacing:.06em;
               margin-bottom:12px;margin-top:2px}
</style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="logo">
      <svg viewBox="0 0 24 24"><path d="M12 2C8 8 5 11 5 15a7 7 0 0014 0c0-4-3-7-7-13z"/></svg>
    </div>
    <div class="brand-text">
      <h1>Smart Infus</h1>
      <p>Konfigurasi Perangkat</p>
    </div>
  </div>
  <div class="badge">Mode AP - 192.168.4.1</div>
  <br><br>
  <h2>Pengaturan WiFi dan Server</h2>
  <p class="sub">Simpan konfigurasi ke memori perangkat (EEPROM)</p>
  <div id="al" class="alert"></div>
  <form id="frm">
    <div class="field">
      <label>Nama WiFi (SSID)</label>
      <input type="text" id="ssid" name="ssid" maxlength="32"
             placeholder="Nama jaringan WiFi" required>
    </div>
    <div class="field">
      <label>Password WiFi</label>
      <input type="password" id="pass" name="pass" maxlength="64"
             placeholder="Password WiFi" autocomplete="new-password">
      <div class="hint">Kosongkan jika jaringan terbuka (open network)</div>
    </div>
    <hr>
    <div class="field">
      <label>URL Server</label>
      <input type="url" id="url" name="url" maxlength="128"
             placeholder="http://192.168.x.x/infus_2/web/api/post_data.php" required>
      <div class="hint">URL lengkap endpoint post_data.php di server</div>
    </div>
    <div class="field">
      <label>Device ID</label>
      <input type="text" id="did" name="did" maxlength="32"
             placeholder="INFUS-01" required>
      <div class="hint">ID unik perangkat ini (huruf kapital, tanpa spasi)</div>
    </div>
    <div class="field">
      <label>API Key (IoT Security)</label>
      <input type="password" id="apikey" name="apikey" maxlength="64"
             placeholder="Masukkan API Key...">
      <div class="hint">Isi jika server mengaktifkan pengaman IOT_API_KEY</div>
    </div>
    <hr>
    <p class="section-title">Berat Kantong Infus (gram)</p>
    <div class="hint" style="margin-bottom:12px">
      Timbang kantong kosong masing-masing mode, isi angkanya di sini.
      Nilai berbeda tiap merk/ukuran kantong.
    </div>
    <div class="row3">
      <div class="field">
        <label>500 ml</label>
        <input type="number" id="berat500" name="berat500"
               min="1" max="499" step="0.1" placeholder="25.0" required>
      </div>
      <div class="field">
        <label>100 ml</label>
        <input type="number" id="berat100" name="berat100"
               min="1" max="499" step="0.1" placeholder="25.0" required>
      </div>
      <div class="field">
        <label>Other</label>
        <input type="number" id="beratOther" name="beratOther"
               min="1" max="499" step="0.1" placeholder="25.0" required>
      </div>
    </div>
    <div class="hint" style="margin-bottom:16px">Satuan gram (g). Rentang valid: 1 – 499 g</div>
    <button type="submit" id="btn">SIMPAN DAN RESTART</button>
  </form>
</div>
<script>
var $ = function(s){return document.querySelector(s);};
function showMsg(msg,ok){
  var a=$('#al');
  a.textContent=msg;
  a.className='alert '+(ok?'ok':'err');
  a.style.display='block';
}
var ctrl=new AbortController();
var tid=setTimeout(function(){ctrl.abort();},4000);
fetch('/current',{signal:ctrl.signal})
  .then(function(r){clearTimeout(tid);return r.json();})
  .then(function(d){
    $('#ssid').value=d.ssid||'';
    $('#pass').value=d.pass||'';
    $('#url').value=d.url||'';
    $('#did').value=d.deviceId||'';
    $('#apikey').value=d.apiKey||'';
    $('#berat500').value=d.berat500||25;
    $('#berat100').value=d.berat100||25;
    $('#beratOther').value=d.beratOther||25;
  })
  .catch(function(){clearTimeout(tid);/* gagal load current — form tetap bisa diisi manual */});
$('#frm').addEventListener('submit',function(e){
  e.preventDefault();
  var btn=$('#btn');
  btn.disabled=true;
  btn.textContent='Menyimpan...';
  var body=new URLSearchParams();
  body.append('ssid',$('#ssid').value);
  body.append('pass',$('#pass').value);
  body.append('url',$('#url').value);
  body.append('deviceId',$('#did').value);
  body.append('apiKey',$('#apikey').value);
  body.append('berat500',$('#berat500').value);
  body.append('berat100',$('#berat100').value);
  body.append('beratOther',$('#beratOther').value);
  fetch('/save',{method:'POST',body:body})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.ok){
        showMsg('Berhasil! Perangkat restart dalam 3 detik...',true);
        btn.textContent='Merestart Perangkat...';
      } else {
        showMsg('Gagal: '+d.msg,false);
        btn.disabled=false;
        btn.textContent='SIMPAN DAN RESTART';
      }
    })
    .catch(function(){
      showMsg('Koneksi ke perangkat gagal.',false);
      btn.disabled=false;
      btn.textContent='SIMPAN DAN RESTART';
    });
});
</script>
</body>
</html>
)rawhtml";
