# 🚀 WhatsApp Gateway API - Bot tanpa Fonnte

Dokumentasi lengkap untuk menggunakan Bot sebagai WhatsApp Gateway untuk mengirim pesan langsung tanpa perlu Fonnte atau service pihak ketiga lainnya.

---

## 📋 Daftar Isi

1. [Konsep & Keuntungan](#konsep--keuntungan)
2. [Setup & Konfigurasi](#setup--konfigurasi)
3. [REST API Endpoints](#rest-api-endpoints)
4. [Contoh Integrasi Web](#contoh-integrasi-web)
5. [Testing & Debugging](#testing--debugging)
6. [Troubleshooting](#troubleshooting)

---

## 🎯 Konsep & Keuntungan

### Cara Kerja

```
Web App (PHP/Node/Python)
        ↓ HTTP Request
        ↓ x-api-key + nomor + pesan
        ↓
    Bot API Gateway (Express)
        ↓
    Baileys Socket
        ↓
   WhatsApp Server
        ↓
   Nomor Tujuan ✅
```

### Keuntungan

✅ **Gratis** - Tidak ada biaya Fonnte/service lainnya  
✅ **Langsung** - Pesan dikirim via bot session yang sudah login  
✅ **Reliable** - Menggunakan Baileys yang stable  
✅ **Flexible** - API sendiri, bisa dikontrol 100%  
✅ **Scalable** - Bisa kirim bulk message  

### Kelemahan

⚠️ Session bot harus tetap online (disconnect = API tidak bisa)  
⚠️ WhatsApp bisa blok nomor jika spam/banyak failed send  
⚠️ Tidak ada history/tracking seperti Fonnte  

---

## 🔧 Setup & Konfigurasi

### 1. Update package.json (Express)

Express sudah ada di package.json untuk Baileys, pastikan verified:

```bash
npm list express
# Jika belum ada:
npm install express
```

### 2. Ganti API Key

**File: `.env`**

```env
API_KEY=your-super-secret-key-change-in-production
PORT=3000
```

Gunakan key yang kuat (random string, minimal 32 karakter).

### 3. Jalankan Bot

```bash
npm start
# Output akan menampilkan:
# 🌐 API Gateway running on http://localhost:3000
# 📡 WhatsApp API available at http://localhost:3000/api/whatsapp
```

### 4. Login WhatsApp

Scan QR code yang muncul di terminal dengan WhatsApp Anda.

Setelah login, API siap digunakan!

---

## 📡 REST API Endpoints

### Dokumentasi Lengkap

Base URL: `http://localhost:3000/api/whatsapp`

Semua request **wajib** include header:

```
x-api-key: your-secret-key
Content-Type: application/json
```

---

### 1. **Send Text Message (Single)**

Kirim pesan teks ke satu nomor.

**Endpoint:**
```
POST /api/whatsapp/send-text
```

**Request Body:**
```json
{
  "phone": "6281234567890",
  "message": "Halo, ini pesan test dari API!"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "phone": "6281234567890",
    "jid": "6281234567890@s.whatsapp.net",
    "messageId": "3EB0xxxx",
    "timestamp": 1716729600,
    "message": "Halo, ini pesan test dari API!..."
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "error": "Format nomor tidak valid: 08123 (gunakan format 62xxxxx)"
}
```

---

### 2. **Send Bulk Message**

Kirim pesan yang sama ke multiple nomor.

**Endpoint:**
```
POST /api/whatsapp/send-bulk
```

**Request Body:**
```json
{
  "phones": [
    "6281234567890",
    "6281234567891",
    "6281234567892"
  ],
  "message": "Pesan broadcast ke beberapa nomor"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "totalSent": 3,
    "totalFailed": 0,
    "results": [
      {
        "number": "6281234567890",
        "jid": "6281234567890@s.whatsapp.net",
        "messageId": "3EB0xxxx",
        "success": true
      },
      // ... nomor lainnya
    ],
    "failed": []
  }
}
```

---

### 3. **Send Button Message**

Kirim pesan dengan button/pilihan.

**Endpoint:**
```
POST /api/whatsapp/send-button
```

**Request Body:**
```json
{
  "phone": "6281234567890",
  "message": "Silakan pilih opsi di bawah:",
  "buttons": [
    {
      "buttonId": "btn_order",
      "buttonText": "🛒 Pesan Sekarang"
    },
    {
      "buttonId": "btn_info",
      "buttonText": "ℹ️ Informasi Produk"
    },
    {
      "buttonId": "btn_contact",
      "buttonText": "📞 Hubungi Admin"
    }
  ]
}
```

---

### 4. **Check Bot Status**

Cek apakah bot sudah connected ke WhatsApp.

**Endpoint:**
```
GET /api/whatsapp/status
```

**Response (Connected):**
```json
{
  "success": true,
  "data": {
    "connected": true,
    "status": "connected",
    "message": "✅ Bot siap mengirim pesan"
  }
}
```

**Response (Disconnected):**
```json
{
  "success": true,
  "data": {
    "connected": false,
    "status": "disconnected",
    "message": "❌ Bot belum terhubung ke WhatsApp"
  }
}
```

---

### 5. **Validate Phone Number**

Validasi format nomor WhatsApp.

**Endpoint:**
```
POST /api/whatsapp/validate-phone
```

**Request:**
```json
{
  "phone": "0812345678"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "phone": "0812345678",
    "valid": true,
    "formatted": "6281234567890@s.whatsapp.net",
    "message": "✅ Format nomor valid"
  }
}
```

---

## 📲 Contoh Integrasi Web

### PHP (Web Anda)

```php
<?php
class WhatsAppGateway {
    private $api_url = "http://localhost:3000/api/whatsapp";
    private $api_key = "your-secret-key";
    
    public function sendMessage($phone, $message) {
        $ch = curl_init($this->api_url . '/send-text');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'phone' => $phone,
                'message' => $message
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->api_key
            ],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $httpCode === 200,
            'data' => json_decode($response, true)
        ];
    }
    
    public function sendBulk($phones, $message) {
        $ch = curl_init($this->api_url . '/send-bulk');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'phones' => $phones,
                'message' => $message
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->api_key
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    public function checkStatus() {
        $ch = curl_init($this->api_url . '/status');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->api_key
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}

// Contoh Penggunaan
$wa = new WhatsAppGateway();

// Cek status
$status = $wa->checkStatus();
if ($status['data']['connected']) {
    echo "✅ Bot online\n";
} else {
    echo "❌ Bot offline\n";
    exit;
}

// Kirim pesan single
$result = $wa->sendMessage('6281234567890', 'Halo, pesanan Anda telah dikonfirmasi!');
if ($result['status']) {
    echo "✅ Pesan terkirim\n";
} else {
    echo "❌ " . $result['data']['error'] . "\n";
}

// Kirim bulk
$phones = ['6281234567890', '6281234567891'];
$bulk = $wa->sendBulk($phones, 'Promo spesial untuk Anda!');
echo "Terkirim: " . $bulk['data']['totalSent'] . ", Gagal: " . $bulk['data']['totalFailed'] . "\n";
?>
```

### JavaScript (Frontend/Backend)

```javascript
class WhatsAppGateway {
    constructor(apiUrl = "http://localhost:3000", apiKey = "your-secret-key") {
        this.apiUrl = apiUrl;
        this.apiKey = apiKey;
    }
    
    async sendText(phone, message) {
        return this._request('/api/whatsapp/send-text', {
            phone,
            message
        });
    }
    
    async sendBulk(phones, message) {
        return this._request('/api/whatsapp/send-bulk', {
            phones,
            message
        });
    }
    
    async sendButton(phone, message, buttons) {
        return this._request('/api/whatsapp/send-button', {
            phone,
            message,
            buttons
        });
    }
    
    async checkStatus() {
        return fetch(`${this.apiUrl}/api/whatsapp/status`, {
            headers: {
                'x-api-key': this.apiKey
            }
        }).then(r => r.json());
    }
    
    async validatePhone(phone) {
        return this._request('/api/whatsapp/validate-phone', { phone });
    }
    
    async _request(endpoint, data) {
        const response = await fetch(`${this.apiUrl}${endpoint}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'x-api-key': this.apiKey
            },
            body: JSON.stringify(data)
        });
        return response.json();
    }
}

// Penggunaan
const wa = new WhatsAppGateway("http://localhost:3000", "your-secret-key");

// Cek status
const status = await wa.checkStatus();
console.log(status.data.message);

// Kirim pesan
const result = await wa.sendText('6281234567890', 'Halo dari API!');
if (result.success) {
    console.log('✅ Pesan terkirim:', result.data.messageId);
} else {
    console.error('❌ Error:', result.error);
}

// Kirim bulk
const bulk = await wa.sendBulk(
    ['6281234567890', '6281234567891'],
    'Pesan broadcast'
);
console.log(`Terkirim: ${bulk.data.totalSent}, Gagal: ${bulk.data.totalFailed}`);

// Validasi nomor
const validation = await wa.validatePhone('0812345678');
if (validation.data.valid) {
    console.log('Nomor valid, format:', validation.data.formatted);
}
```

### Python

```python
import requests
import json

class WhatsAppGateway:
    def __init__(self, api_url="http://localhost:3000", api_key="your-secret-key"):
        self.api_url = api_url
        self.api_key = api_key
        self.headers = {
            "Content-Type": "application/json",
            "x-api-key": api_key
        }
    
    def send_text(self, phone, message):
        return self._request('/api/whatsapp/send-text', {
            'phone': phone,
            'message': message
        })
    
    def send_bulk(self, phones, message):
        return self._request('/api/whatsapp/send-bulk', {
            'phones': phones,
            'message': message
        })
    
    def send_button(self, phone, message, buttons):
        return self._request('/api/whatsapp/send-button', {
            'phone': phone,
            'message': message,
            'buttons': buttons
        })
    
    def check_status(self):
        response = requests.get(
            f"{self.api_url}/api/whatsapp/status",
            headers=self.headers
        )
        return response.json()
    
    def validate_phone(self, phone):
        return self._request('/api/whatsapp/validate-phone', {'phone': phone})
    
    def _request(self, endpoint, data):
        response = requests.post(
            f"{self.api_url}{endpoint}",
            json=data,
            headers=self.headers,
            timeout=10
        )
        return response.json()

# Penggunaan
wa = WhatsAppGateway()

# Cek status
status = wa.check_status()
print(status['data']['message'])

# Kirim pesan
result = wa.send_text('6281234567890', 'Halo dari Python!')
if result['success']:
    print('✅ Terkirim:', result['data']['messageId'])
else:
    print('❌ Error:', result['error'])

# Kirim bulk
bulk = wa.send_bulk(['6281234567890', '6281234567891'], 'Pesan broadcast')
print(f"Terkirim: {bulk['data']['totalSent']}, Gagal: {bulk['data']['totalFailed']}")
```

---

## 🧪 Testing & Debugging

### CURL Testing

**Cek status:**
```bash
curl http://localhost:3000/api/whatsapp/status \
  -H "x-api-key: your-secret-key"
```

**Send message:**
```bash
curl -X POST http://localhost:3000/api/whatsapp/send-text \
  -H "Content-Type: application/json" \
  -H "x-api-key: your-secret-key" \
  -d '{
    "phone": "6281234567890",
    "message": "Test message"
  }'
```

**Send bulk:**
```bash
curl -X POST http://localhost:3000/api/whatsapp/send-bulk \
  -H "Content-Type: application/json" \
  -H "x-api-key: your-secret-key" \
  -d '{
    "phones": ["6281234567890", "6281234567891"],
    "message": "Bulk test"
  }'
```

### Postman Testing

1. Import `whatsapp-gateway-api.js` sebagai referensi endpoints
2. Set base URL: `http://localhost:3000`
3. Add header: `x-api-key: your-secret-key`
4. Test endpoints

---

## 🐛 Troubleshooting

### Error: "Bot belum terhubung ke WhatsApp"

**Penyebab:**
- Bot belum selesai login
- Session terputus

**Solusi:**
- Pastikan bot sudah menampilkan "Bot siap digunakan!" di console
- Jika terputus, bot akan auto-reconnect, tunggu hingga connected kembali

---

### Error: "Format nomor tidak valid"

**Format yang benar:**
- `6281234567890` ✅
- `62xx-xxxx-xxxx` (dengan format apapun bisa, akan di-parse)

**Format yang salah:**
- `0812345678` ❌ (gunakan format 62)
- `62012345678` ❌ (tidak boleh ada 0)
- `812345678` ❌ (kurang digit)

**Solusi:**
Gunakan endpoint `/validate-phone` untuk cek format sebelum kirim.

---

### Error: "Unauthorized - Invalid API Key"

**Penyebab:**
- API key di header tidak cocok dengan `.env`

**Solusi:**
```bash
# Pastikan x-api-key header sama dengan env
echo $API_KEY
```

---

### Pesan tidak terkirim tapi tidak ada error

**Penyebab:**
- Nomor tidak registered WhatsApp
- Nomor di-block oleh WhatsApp
- Account di-limit

**Solusi:**
- Validasi nomor dengan endpoint `/validate-phone`
- Tunggu beberapa saat sebelum re-send
- Jangan spam send ke nomor yang sama

---

### Bot terputus dari WhatsApp

**Penyebab:**
- Session lama / belum login ulang
- WhatsApp login di perangkat lain
- Network disconnect

**Solusi:**
Bot akan **auto-reconnect** dalam 2 detik. API akan kembali normal otomatis.

Jika persistent:
1. Delete folder `sessions/`
2. Restart bot
3. Scan QR code lagi

---

## 📊 Format Nomor Reference

Konversi format dan contoh:

| Format Input | Format Valid | Contoh |
|---|---|---|
| 0812345678 | ✅ 6281234567890 | Nomor lokal Indonesia |
| 62812345678 | ✅ 6281234567890 | Dengan kode negara |
| +628123456789 | ✅ 6281234567890 | Dengan + |
| 8123456789 | ✅ 6281234567890 | Tanpa 0 & kode negara |

---

## 🔐 Security Tips

1. **Ganti API Key**
   ```env
   API_KEY=SecureRandomKey123456789abcdefghijk
   ```

2. **Rate Limiting** (Opsional)
   ```javascript
   import rateLimit from 'express-rate-limit';
   
   const limiter = rateLimit({
     windowMs: 15 * 60 * 1000,
     max: 100 // 100 requests per 15 minutes
   });
   app.use('/api/whatsapp', limiter);
   ```

3. **HTTPS untuk Production**
   Gunakan reverse proxy (nginx, Apache) untuk HTTPS

4. **IP Whitelist** (Opsional)
   ```javascript
   const allowedIPs = ['127.0.0.1', '192.168.1.x'];
   ```

---

## 📞 Support

Jika ada masalah:
1. Check console output bot untuk error messages
2. Validasi nomor dengan `/validate-phone`
3. Cek status bot dengan `/status`
4. Pastikan API key benar
5. Cek network/firewall

---

**Created:** 26 Mei 2026  
**Last Updated:** 26 Mei 2026  
**Version:** 1.0.0  

Selamat menggunakan WhatsApp Gateway! 🚀