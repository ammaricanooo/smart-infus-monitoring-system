// =====================================================
// ================= SMART INFUS RTOS ===================
// =====================================================
// Fitur Web AP Config:
//   - Tahan BUTTON_PIN ≥ 5 detik (kapan saja, termasuk saat baru nyala)
//     → masuk Web AP config mode
//   - ESP32 buka hotspot "SmartInfus-Config" (pass: infus1234)
//   - Buka browser ke 192.168.4.1 untuk konfigurasi
//   - Setting (SSID, password, URL server, device ID) disimpan ke EEPROM
// =====================================================

#include "HX711.h"
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <WebServer.h>
#include <EEPROM.h>

// ================= EEPROM LAYOUT =================
// Addr  0 : valid flag (0xAB = valid)
// Addr  1 : panjang SSID (1 byte)
// Addr  2..33 : SSID (max 32 char)
// Addr 34 : panjang password (1 byte)
// Addr 35..98 : password (max 64 char)
// Addr 99 : panjang server URL (1 byte)
// Addr 100..227: server URL (max 128 char)
// Addr 228: panjang device ID (1 byte)
// Addr 229..260: device ID (max 32 char)
// Addr 261..264: berat kantong mode 500ml (float, 4 byte)
// Addr 265..268: berat kantong mode 100ml (float, 4 byte)
// Addr 269..272: berat kantong mode OTHER  (float, 4 byte)
// Total: 338 bytes

#define EEPROM_SIZE 338
#define EEPROM_FLAG_ADDR 0
#define EEPROM_VALID_FLAG 0xAB
#define EEPROM_SSID_LEN 1
#define EEPROM_SSID_DATA 2
#define EEPROM_PASS_LEN 34
#define EEPROM_PASS_DATA 35
#define EEPROM_URL_LEN 99
#define EEPROM_URL_DATA 100
#define EEPROM_ID_LEN 228
#define EEPROM_ID_DATA 229
#define EEPROM_BERAT500_ADDR 261
#define EEPROM_BERAT100_ADDR 265
#define EEPROM_BERATOTHER_ADDR 269
#define EEPROM_KEY_LEN 273
#define EEPROM_KEY_DATA 274

// ================= PIN CONFIG AP =================
// CONFIG digabung dengan BUTTON_PIN (pin 27)
// Tekan singkat (< 3 dtk) = ganti mode volume
// Tahan ≥ 5 detik          = masuk Web AP config mode
#define CONFIG_HOLD_MS 5000
#define AP_SSID "SmartInfus-Config"
#define AP_PASSWORD "infus1234"
#define AP_IP "192.168.4.1"

// ================= RUNTIME CONFIG (dari EEPROM) =================
char cfg_ssid[33] = "Redmi Note 14";
char cfg_password[65] = "aaaaaaab";
char cfg_server[129] = "http://10.124.61.130/infus_2/web/api/post_data.php";
char cfg_deviceId[33] = "INFUS-01";
char cfg_apiKey[65] = "";

// Berat kantong per mode (gram) — bisa berbeda tiap tipe kantong infus.
// Default 25.0g, bisa dikustomisasi via web config.
float cfg_berat500  = 25.0f;
float cfg_berat100  = 25.0f;
float cfg_beratOther = 25.0f;

// ================= WEB SERVER (hanya aktif di mode AP) =================
WebServer webServer(80);

// ================= HX711 =================
#define DOUT_PIN 5
#define SCK_PIN 4
// Berat kantong default (gram) — dioverride oleh cfg_berat* dari EEPROM
#define BERAT_KANTONG_DEFAULT 25.0f

HX711 scale;

#define CAL_500ML 1060.8f
#define CAL_100ML 935.1f
#define CAL_OTHER 998.0f

float calibrationFactor = CAL_500ML;

// ================= OLED ==================
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64

Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, -1);

// ================= BATTERY =================
#define BATTERY_PIN 34
// Voltage divider 10k:10k (1:1) di pin baterai -> tegangan di pin
// adalah setengah tegangan baterai sebenarnya, makanya dikali 2 lagi
// di taskBattery().
//
// Baterai: Cas Phytona mobil remote 4000mAh "6V" (NiMH/NiCd pack).
// Hasil ukur multimeter langsung di terminal baterai: penuh ~5.0-5.2V.
// (Label "6V" cuma rating charger, bukan tegangan aktual cell — wajar
// pada paket NiMH murah seperti ini.)
// BATTERY_EMPTY_MV adalah perkiraan konservatif (≈80% dari penuh) supaya
// peringatan "low battery" muncul sebelum baterai benar-benar habis.
// Disarankan dikalibrasi ulang: catat tegangan saat remote/alat mulai
// melemah, lalu ganti nilai BATTERY_EMPTY_MV dengan hasil itu agar lebih akurat.
#define BATTERY_DIVIDER_RATIO 2.0f
#define BATTERY_EMPTY_MV 4200.0f  // perkiraan cutoff aman (≈80% dari penuh)
#define BATTERY_FULL_MV 5200.0f   // hasil ukur langsung saat baterai penuh
#define BATTERY_SAMPLE_MS 5000    // interval sampling baterai

// ================= IR ====================
#define IR_SENSOR_PIN 23
#define LED_PIN 2
#define DROPS_PER_ML 20.0

// ================= BUTTON =================
// Rangkaian: 3.3V → [tombol] → pin 27 → [resistor pull-down] → GND
// Logika: tombol DITEKAN = HIGH, tombol LEPAS = LOW
#define BUTTON_PIN 27
#define BUZZER 18
#define NURSE_BUTTON_PIN 19
// Tombol TARE: tekan sekali untuk nol-kan timbangan (tare HX711)
// Gunakan pin dengan INPUT_PULLUP → tombol ditekan = LOW
#define TARE_BUTTON_PIN 32

volatile uint8_t volumeMode = 0;
float currentVolumeAwal = 500.0;

// ================= GLOBAL =================
volatile uint32_t dropCount = 0;
volatile uint32_t totalDrops = 0;
volatile unsigned long lastTriggerTime = 0;

float tpm = 0;
float volumeSisaBerat = 0;
float persen = 0;
int hours = 0;
int minutes = 0;

volatile bool nurseCallActive = false;
unsigned long lastNursePress = 0;
bool lastNurseState = HIGH;

bool wifiConnected = false;
float batteryPercent = 100.0;  // di-update oleh taskBattery, dibaca taskOLED

// ================= BUZZER (shared resource) =================
// Buzzer dipakai oleh beberapa task (taskButton, taskNurseCall,
// runConfigMode). Mutex memastikan hanya satu "pemilik" buzzer pada satu
// waktu, supaya bip dari satu task tidak ketimpa/terganggu task lain.
SemaphoreHandle_t buzzerMutex;

void buzzerBeep(int onMs, int offMs = 0) {
  if (xSemaphoreTake(buzzerMutex, pdMS_TO_TICKS(50)) == pdTRUE) {
    digitalWrite(BUZZER, HIGH);
    vTaskDelay(pdMS_TO_TICKS(onMs));
    digitalWrite(BUZZER, LOW);
    if (offMs > 0) vTaskDelay(pdMS_TO_TICKS(offMs));
    xSemaphoreGive(buzzerMutex);
  }
}

void buzzerBeepN(int count, int onMs, int offMs) {
  for (int i = 0; i < count; i++) buzzerBeep(onMs, offMs);
}

// =====================================================
// ================= TASK HANDLES ======================
// Dibutuhkan agar runConfigMode() bisa suspend semua task
// =====================================================
TaskHandle_t hLoadCell = NULL;
TaskHandle_t hTPM = NULL;
TaskHandle_t hOLED = NULL;
TaskHandle_t hSerial = NULL;
TaskHandle_t hNurseCall = NULL;
TaskHandle_t hWiFi = NULL;
TaskHandle_t hHTTPPost = NULL;
TaskHandle_t hBattery = NULL;
TaskHandle_t hTare = NULL;

// =====================================================
// ================= WEB AP — HTML PAGE ================
// HTML dipindah ke config_html.h agar Arduino preprocessor
// tidak salah parse JavaScript di dalam raw string literal
// =====================================================

#include "config_html.h"

// =====================================================
// ================= WEB AP — ROUTE HANDLERS ===========
// =====================================================

void handleRoot() {
  webServer.send_P(200, "text/html", CONFIG_HTML);
}

void handleCurrent() {
  // Kirim nilai config saat ini sebagai JSON
  StaticJsonDocument<512> doc;
  doc["ssid"] = cfg_ssid;
  doc["pass"] = cfg_password;
  doc["url"] = cfg_server;
  doc["deviceId"] = cfg_deviceId;
  doc["apiKey"] = cfg_apiKey;
  doc["berat500"] = cfg_berat500;
  doc["berat100"] = cfg_berat100;
  doc["beratOther"] = cfg_beratOther;
  String json;
  serializeJson(doc, json);
  webServer.send(200, "application/json", json);
}

void handleSave() {
  if (!webServer.hasArg("ssid") || !webServer.hasArg("url") || !webServer.hasArg("deviceId")) {
    webServer.send(400, "application/json", "{\"ok\":false,\"msg\":\"Parameter tidak lengkap\"}");
    return;
  }

  String newSsid = webServer.arg("ssid");
  String newPass = webServer.arg("pass");
  String newUrl = webServer.arg("url");
  String newDeviceId = webServer.arg("deviceId");
  String newApiKey = webServer.arg("apiKey");

  // Validasi dasar
  if (newSsid.length() == 0 || newUrl.length() == 0 || newDeviceId.length() == 0) {
    webServer.send(400, "application/json", "{\"ok\":false,\"msg\":\"SSID, URL, dan Device ID tidak boleh kosong\"}");
    return;
  }

  // Salin ke buffer global
  strncpy(cfg_ssid, newSsid.c_str(), 32);
  cfg_ssid[32] = '\0';
  strncpy(cfg_password, newPass.c_str(), 64);
  cfg_password[64] = '\0';
  strncpy(cfg_server, newUrl.c_str(), 128);
  cfg_server[128] = '\0';
  strncpy(cfg_deviceId, newDeviceId.c_str(), 32);
  cfg_deviceId[32] = '\0';
  strncpy(cfg_apiKey, newApiKey.c_str(), 64);
  cfg_apiKey[64] = '\0';

  // Berat kantong per mode (dengan nilai default jika field kosong/invalid)
  auto parseFloat = [](const String &s, float def) -> float {
    if (s.length() == 0) return def;
    float v = s.toFloat();
    return (v > 0.0f && v < 500.0f) ? v : def;
  };
  cfg_berat500  = parseFloat(webServer.arg("berat500"),  25.0f);
  cfg_berat100  = parseFloat(webServer.arg("berat100"),  25.0f);
  cfg_beratOther = parseFloat(webServer.arg("beratOther"), 25.0f);

  saveConfig();

  Serial.println("[Config] Konfigurasi baru disimpan ke EEPROM.");
  Serial.print("[Config] SSID: ");
  Serial.println(cfg_ssid);
  Serial.print("[Config] URL: ");
  Serial.println(cfg_server);
  Serial.print("[Config] DeviceID: ");
  Serial.println(cfg_deviceId);
  Serial.printf("[Config] Berat kantong — 500ml:%.1fg  100ml:%.1fg  Other:%.1fg\n",
                cfg_berat500, cfg_berat100, cfg_beratOther);

  webServer.send(200, "application/json", "{\"ok\":true}");

  // Restart setelah 2 detik agar browser sempat terima response
  delay(2000);
  ESP.restart();
}

void handleNotFound() {
  webServer.sendHeader("Location", "/", true);
  webServer.send(302, "text/plain", "");
}

// =====================================================
// ================= EEPROM HELPERS ====================
// =====================================================

void eepromWriteStr(int addrLen, int addrData, const char *str, int maxLen) {
  int len = strlen(str);
  if (len > maxLen) len = maxLen;
  EEPROM.write(addrLen, (uint8_t)len);
  for (int i = 0; i < len; i++)
    EEPROM.write(addrData + i, (uint8_t)str[i]);
  EEPROM.write(addrData + len, 0);
  EEPROM.commit();
}

void eepromReadStr(int addrLen, int addrData, char *buf, int maxLen) {
  int len = (int)EEPROM.read(addrLen);
  if (len < 0 || len > maxLen) len = 0;
  for (int i = 0; i < len; i++)
    buf[i] = (char)EEPROM.read(addrData + i);
  buf[len] = '\0';
}

void eepromWriteFloat(int addr, float val) {
  uint8_t *p = (uint8_t *)&val;
  for (int i = 0; i < 4; i++) EEPROM.write(addr + i, p[i]);
  EEPROM.commit();
}

float eepromReadFloat(int addr, float def) {
  float val;
  uint8_t *p = (uint8_t *)&val;
  for (int i = 0; i < 4; i++) p[i] = EEPROM.read(addr + i);
  // Sanity check: nilai berat kantong yang masuk akal 1–500 gram
  if (val < 1.0f || val > 500.0f || isnan(val)) return def;
  return val;
}

void saveConfig() {
  EEPROM.write(EEPROM_FLAG_ADDR, EEPROM_VALID_FLAG);
  eepromWriteStr(EEPROM_SSID_LEN, EEPROM_SSID_DATA, cfg_ssid, 32);
  eepromWriteStr(EEPROM_PASS_LEN, EEPROM_PASS_DATA, cfg_password, 64);
  eepromWriteStr(EEPROM_URL_LEN, EEPROM_URL_DATA, cfg_server, 128);
  eepromWriteStr(EEPROM_ID_LEN, EEPROM_ID_DATA, cfg_deviceId, 32);
  eepromWriteStr(EEPROM_KEY_LEN, EEPROM_KEY_DATA, cfg_apiKey, 64);
  eepromWriteFloat(EEPROM_BERAT500_ADDR,   cfg_berat500);
  eepromWriteFloat(EEPROM_BERAT100_ADDR,   cfg_berat100);
  eepromWriteFloat(EEPROM_BERATOTHER_ADDR, cfg_beratOther);
  EEPROM.commit();
}

bool loadConfig() {
  if (EEPROM.read(EEPROM_FLAG_ADDR) != EEPROM_VALID_FLAG) return false;
  eepromReadStr(EEPROM_SSID_LEN, EEPROM_SSID_DATA, cfg_ssid, 32);
  eepromReadStr(EEPROM_PASS_LEN, EEPROM_PASS_DATA, cfg_password, 64);
  eepromReadStr(EEPROM_URL_LEN, EEPROM_URL_DATA, cfg_server, 128);
  eepromReadStr(EEPROM_ID_LEN, EEPROM_ID_DATA, cfg_deviceId, 32);
  eepromReadStr(EEPROM_KEY_LEN, EEPROM_KEY_DATA, cfg_apiKey, 64);
  cfg_berat500   = eepromReadFloat(EEPROM_BERAT500_ADDR,   25.0f);
  cfg_berat100   = eepromReadFloat(EEPROM_BERAT100_ADDR,   25.0f);
  cfg_beratOther = eepromReadFloat(EEPROM_BERATOTHER_ADDR, 25.0f);
  return true;
}

// =====================================================
// ================= MODE WEB AP — MAIN LOOP ===========
// =====================================================

// Tampilkan layar config di OLED
void showConfigScreen() {
  display.clearDisplay();
  display.fillRoundRect(0, 0, 128, 12, 3, WHITE);
  display.setTextColor(BLACK);
  display.setTextSize(1);
  display.setCursor(14, 2);
  display.print("WiFi CONFIG MODE");

  display.setTextColor(WHITE);
  display.setCursor(0, 16);
  display.print("Hubungkan ke WiFi:");
  display.setCursor(4, 26);
  display.setTextSize(1);
  display.print(AP_SSID);

  display.setCursor(0, 38);
  display.print("Password: " AP_PASSWORD);

  display.setCursor(0, 50);
  display.print("Buka: " AP_IP);

  display.display();
}

// Jalankan mode konfigurasi AP (blocking — tidak return sampai config selesai)
void runConfigMode() {
  Serial.println("\n[AP] Masuk mode konfigurasi Web AP");

  // ── Suspend semua task lain agar tidak ada konflik I2C/WiFi/WDT ──────
  // Urutan: hentikan pengiriman data dulu, lalu sensor, lalu UI
  if (hHTTPPost != NULL) vTaskSuspend(hHTTPPost);
  if (hWiFi != NULL) vTaskSuspend(hWiFi);
  if (hTPM != NULL) vTaskSuspend(hTPM);
  if (hLoadCell != NULL) vTaskSuspend(hLoadCell);
  if (hBattery != NULL) vTaskSuspend(hBattery);
  if (hNurseCall != NULL) vTaskSuspend(hNurseCall);
  if (hTare != NULL) vTaskSuspend(hTare);
  if (hSerial != NULL) vTaskSuspend(hSerial);
  if (hOLED != NULL) vTaskSuspend(hOLED);  // suspend OLED terakhir

  // Nonaktifkan interrupt IR agar tidak ada ISR yang jalan
  detachInterrupt(digitalPinToInterrupt(IR_SENSOR_PIN));

  // Pastikan buzzer dalam keadaan mati. Task yang baru disuspend di atas
  // bisa saja berhenti tepat di tengah sebuah bip (buzzer masih HIGH) —
  // tanpa baris ini buzzer berisiko macet berbunyi terus.
  digitalWrite(BUZZER, LOW);

  // Delay kecil agar semua task benar-benar berhenti
  delay(200);

  // Bunyi buzzer 2x tanda masuk config mode
  buzzerBeepN(2, 100, 150);

  // Matikan WiFi station dulu
  WiFi.disconnect(true);
  delay(300);

  // Buka Access Point
  WiFi.mode(WIFI_AP);
  IPAddress localIP(192, 168, 4, 1);
  IPAddress gateway(192, 168, 4, 1);
  IPAddress subnet(255, 255, 255, 0);
  WiFi.softAPConfig(localIP, gateway, subnet);
  WiFi.softAP(AP_SSID, AP_PASSWORD);

  Serial.print("[AP] IP: ");
  Serial.println(WiFi.softAPIP());

  // Daftarkan routes web server
  webServer.on("/", HTTP_GET, handleRoot);
  webServer.on("/current", HTTP_GET, handleCurrent);
  webServer.on("/save", HTTP_POST, handleSave);
  webServer.onNotFound(handleNotFound);
  webServer.begin();

  Serial.println("[AP] Web server berjalan di port 80");

  // Tampilkan layar config di OLED (langsung dari sini, bukan via task)
  showConfigScreen();

  // ── Loop utama config mode ────────────────────────────────────────────
  // Gunakan vTaskDelay agar FreeRTOS idle task tetap jalan (reset WDT)
  unsigned long lastOledUpdate = 0;
  while (true) {
    webServer.handleClient();

    if (millis() - lastOledUpdate > 1000) {
      lastOledUpdate = millis();
      showConfigScreen();
    }

    vTaskDelay(pdMS_TO_TICKS(10));  // yield ke FreeRTOS scheduler / WDT
  }
  // Loop tidak pernah return — handleSave() panggil ESP.restart()
}

// =====================================================
// ================= INTERRUPT ==========================
// =====================================================

void IRAM_ATTR onDropDetected() {
  unsigned long now = millis();
  if (now - lastTriggerTime > 120) {
    dropCount++;
    totalDrops++;
    lastTriggerTime = now;
  }
}

// =====================================================
// ================= UPDATE MODE ========================
// =====================================================

void updateVolumeMode() {
  totalDrops = 0;
  if (volumeMode == 0) {
    currentVolumeAwal = 500.0;
    calibrationFactor = CAL_500ML;
  } else if (volumeMode == 1) {
    currentVolumeAwal = 100.0;
    calibrationFactor = CAL_100ML;
  } else {
    calibrationFactor = CAL_OTHER;
    currentVolumeAwal = volumeSisaBerat > 0 ? volumeSisaBerat : 0.0;
  }
  scale.set_scale(calibrationFactor);
}

// =====================================================
// ================= TASK BUTTON ========================
// Rangkaian: 3.3V → [tombol] → pin 27 → [pull-down] → GND
// Ditekan = HIGH, Lepas = LOW
// Tekan singkat (< 3 dtk)  : ganti mode volume (500ml→100ml→OTHER)
// Tahan ≥ 5 detik           : masuk Web AP config mode
// =====================================================

void taskButton(void *pvParameters) {
  unsigned long pressStart = 0;
  bool wasPressing = false;
  bool configTriggered = false;
  bool warnBeeped = false;

  while (1) {
    bool reading = digitalRead(BUTTON_PIN);  // HIGH = ditekan

    if (reading == HIGH) {
      if (!wasPressing) {
        pressStart = millis();
        wasPressing = true;
        configTriggered = false;
        warnBeeped = false;
      }

      unsigned long held = millis() - pressStart;

      // Buzzer peringatan 1x di detik ke-3, kasih tahu user kalau terus
      // ditahan akan masuk config mode
      if (held >= 3000 && !warnBeeped && !configTriggered) {
        warnBeeped = true;
        buzzerBeep(400);
      }

      // Tahan ≥ CONFIG_HOLD_MS → masuk config mode
      if (held >= CONFIG_HOLD_MS && !configTriggered) {
        configTriggered = true;
        buzzerBeepN(3, 80, 80);
        Serial.println("[Config] Tombol ditahan 5 detik -> masuk config mode!");
        runConfigMode();  // blocking — tidak return sampai ESP.restart()
      }

    } else {
      // Tombol dilepas (LOW)
      if (wasPressing && !configTriggered) {
        unsigned long held = millis() - pressStart;
        if (held >= 30 && held < 3000) {
          buzzerBeep(100);
          volumeMode++;
          if (volumeMode > 2) volumeMode = 0;
          updateVolumeMode();
        }
      }
      wasPressing = false;
      configTriggered = false;
      warnBeeped = false;
    }

    vTaskDelay(pdMS_TO_TICKS(20));
  }
}

// =====================================================
// ================= TASK NURSE CALL ===================
// Tekan tombol = toggle panggilan aktif/nonaktif, bip SEKALI saat ditekan.
// Tidak ada bunyi berkelanjutan selama panggilan aktif (supaya tidak
// berisik di ruangan) — status panggilan tetap terlihat lewat indikator
// "CALL" yang berkedip di OLED (lihat taskOLED).
//
// Debounce berlapis:
//   1. Pin harus stabil LOW selama NURSE_DEBOUNCE_STABLE_MS sebelum dianggap valid
//   2. Cooldown NURSE_COOLDOWN_MS setelah trigger, cegah re-trigger dari noise charger
//   Ini mencegah false trigger akibat ground loop / ripple saat pengisian daya.
// =====================================================

#define NURSE_DEBOUNCE_STABLE_MS  50   // pin harus stabil selama ini (ms)
#define NURSE_COOLDOWN_MS        500   // jeda minimum antar trigger (ms)

void taskNurseCall(void *pvParameters) {
  unsigned long lowSince = 0;      // kapan pertama kali pin terbaca LOW
  bool stableTriggered = false;    // sudah trigger di siklus tekan ini?

  while (1) {
    bool reading = digitalRead(NURSE_BUTTON_PIN);

    if (reading == LOW) {
      if (lastNurseState == HIGH) {
        // Baru turun → catat waktu mulai
        lowSince = millis();
        stableTriggered = false;
      }

      // Cek apakah sudah stabil LOW cukup lama DAN cooldown sudah lewat
      if (!stableTriggered &&
          (millis() - lowSince >= NURSE_DEBOUNCE_STABLE_MS) &&
          (millis() - lastNursePress >= NURSE_COOLDOWN_MS)) {

        stableTriggered = true;
        lastNursePress = millis();
        nurseCallActive = !nurseCallActive;
        buzzerBeep(100);
        Serial.println(nurseCallActive ? "[NurseCall] Panggilan AKTIF" : "[NurseCall] Panggilan dibatalkan");
      }
    } else {
      // Pin HIGH → reset state
      stableTriggered = false;
    }

    lastNurseState = reading;
    vTaskDelay(pdMS_TO_TICKS(10));  // polling lebih cepat untuk deteksi stabil
  }
}

// =====================================================
// ================= TASK TARE ==========================
// Pin TARE_BUTTON_PIN (32), INPUT_PULLUP.
// Tombol ditekan = LOW.
// Tekan sekali → scale.tare() + bip 2x + reset totalDrops.
// Debounce 300ms supaya tidak double-trigger.
// =====================================================

void taskTare(void *pvParameters) {
  bool lastState = HIGH;
  while (1) {
    bool reading = digitalRead(TARE_BUTTON_PIN);
    if (reading == LOW && lastState == HIGH) {
      Serial.println("[Tare] Tare dipicu — mengganti titik nol timbangan...");
      scale.tare();          // blok ~400ms, tapi di task tersendiri tidak masalah
      totalDrops = 0;
      buzzerBeepN(2, 80, 80);
      Serial.println("[Tare] Selesai.");
      vTaskDelay(pdMS_TO_TICKS(300));  // debounce
    }
    lastState = reading;
    vTaskDelay(pdMS_TO_TICKS(30));
  }
}

// =====================================================
// ================= TASK HX711 =========================
// =====================================================

void taskLoadCell(void *pvParameters) {
  while (1) {
    float beratTotal = scale.get_units(3);
    if (beratTotal < 0) beratTotal = 0;

    // Pilih berat kantong sesuai mode aktif
    float beratKantong;
    if (volumeMode == 0)      beratKantong = cfg_berat500;
    else if (volumeMode == 1) beratKantong = cfg_berat100;
    else                      beratKantong = cfg_beratOther;

    float beratBersih = beratTotal - beratKantong;
    if (beratBersih < 0) beratBersih = 0;
    volumeSisaBerat = beratBersih;
    if (volumeMode == 2) {
      if (volumeSisaBerat > currentVolumeAwal) currentVolumeAwal = volumeSisaBerat;
    }
    if (volumeMode != 2) {
      if (volumeSisaBerat > currentVolumeAwal) volumeSisaBerat = currentVolumeAwal;
    }
    if (currentVolumeAwal > 0)
      persen = (volumeSisaBerat / currentVolumeAwal) * 100.0;
    if (persen > 100) persen = 100;
    vTaskDelay(pdMS_TO_TICKS(500));
  }
}

// =====================================================
// ================= TASK TPM ===========================
// =====================================================

void taskTPM(void *pvParameters) {
  while (1) {
    uint32_t tempDrops = dropCount;
    dropCount = 0;
    tpm = tempDrops * 20.0;
    float ml_remaining = volumeSisaBerat;
    float ml_per_min = tpm / DROPS_PER_ML;
    float remaining_minutes = 0;
    if (ml_per_min > 0) remaining_minutes = ml_remaining / ml_per_min;
    hours = (int)(remaining_minutes / 60);
    minutes = (int)remaining_minutes % 60;
    vTaskDelay(pdMS_TO_TICKS(3000));
  }
}

// =====================================================
// ================= TASK BATTERY =======================
// Sampling baterai dipindah ke task tersendiri (sebelumnya dipanggil
// langsung di dalam taskOLED setiap 150ms, yang berarti 8x pembacaan ADC
// + log serial ikut jalan ~6-7x/detik — tidak perlu dan bikin taskOLED
// nge-blok lebih lama dari seharusnya). Di sini cukup sampling tiap
// BATTERY_SAMPLE_MS, hasilnya disimpan ke `batteryPercent` global yang
// tinggal dibaca taskOLED tanpa kerja tambahan.
// =====================================================

void taskBattery(void *pvParameters) {
  static float filteredPercent = -1.0;

  while (1) {
    // Ambil rata-rata 8 sampel untuk kurangi noise ADC ESP32
    long sum = 0;
    for (int i = 0; i < 8; i++) {
      sum += analogReadMilliVolts(BATTERY_PIN);
      vTaskDelay(pdMS_TO_TICKS(2));
    }
    float pinMv = sum / 8.0f;                     // mV di pin ADC
    float batMv = pinMv * BATTERY_DIVIDER_RATIO;  // mV baterai sebenarnya

    float percent;
    if (batMv < 500.0f) {
      // Tegangan sangat rendah → kemungkinan tidak ada baterai (hanya USB)
      percent = 100.0f;
    } else {
      percent = (batMv - BATTERY_EMPTY_MV) / (BATTERY_FULL_MV - BATTERY_EMPTY_MV) * 100.0f;
      if (percent > 100.0f) percent = 100.0f;
      if (percent < 0.0f) percent = 0.0f;
    }

    // EMA low-pass filter agar persentase tidak melompat-lompat
    if (filteredPercent < 0.0f) filteredPercent = percent;
    else filteredPercent = filteredPercent * 0.95f + percent * 0.05f;

    batteryPercent = filteredPercent;

    Serial.print("[BAT] pin=");
    Serial.print(pinMv, 0);
    Serial.print("mV bat=");
    Serial.print(batMv, 0);
    Serial.print("mV percent=");
    Serial.print(filteredPercent, 0);
    Serial.println("%");

    vTaskDelay(pdMS_TO_TICKS(BATTERY_SAMPLE_MS));
  }
}

// =====================================================
// ================= TASK OLED ==========================
// =====================================================

void taskOLED(void *pvParameters) {
  while (1) {
    display.clearDisplay();
    display.drawRoundRect(0, 0, 128, 64, 5, WHITE);
    display.fillRoundRect(0, 0, 128, 12, 5, WHITE);
    display.setTextColor(BLACK);
    display.setTextSize(1);
    display.setCursor(28, 2);
    display.println("SMART INFUS");

    // Battery icon (nilai diambil dari taskBattery, bukan dihitung di sini)
    display.drawRect(4, 2, 14, 8, BLACK);
    display.fillRect(18, 4, 2, 4, BLACK);
    int batWidth = map((int)batteryPercent, 0, 100, 0, 10);
    if (batWidth > 0) display.fillRect(6, 4, batWidth, 4, BLACK);

    display.setTextColor(WHITE);
    display.setCursor(6, 14);
    display.print("MODE:");
    if (volumeMode == 0) display.print("500ml");
    else if (volumeMode == 1) display.print("100ml");
    else display.print("OTHER");

    display.setCursor(88, 14);
    if (wifiConnected) display.print("WiFi");
    else display.print("OFF");

    if (nurseCallActive && (millis() / 250) % 2) {
      display.fillRoundRect(92, 24, 30, 10, 2, WHITE);
      display.setTextColor(BLACK);
      display.setCursor(96, 26);
      display.print("CALL");
      display.setTextColor(WHITE);
    }

    // Animasi tetes
    display.drawLine(120, 14, 120, 20, WHITE);
    unsigned long timeSinceLastDrop = millis() - lastTriggerTime;
    if (timeSinceLastDrop < 500) {
      int dropY = 20 + (timeSinceLastDrop * 12 / 500);
      display.fillCircle(120, dropY, 1, WHITE);
      display.fillTriangle(119, dropY, 121, dropY, 120, dropY - 2, WHITE);
    } else {
      display.fillCircle(120, 20, 1, WHITE);
    }

    display.setCursor(6, 24);
    display.print("SISA:");
    display.print(volumeSisaBerat, 0);
    display.print("|");
    if (volumeMode == 2) {
      if ((int)currentVolumeAwal > 0) {
        display.print((int)currentVolumeAwal);
        display.print("ml");
      } else {
        display.print("AUTO");
      }
    } else {
      display.print((int)currentVolumeAwal);
      display.print("ml");
    }

    display.drawRoundRect(6, 36, 116, 10, 3, WHITE);
    int bar = map((int)persen, 0, 100, 0, 112);
    display.fillRoundRect(8, 38, bar, 6, 2, WHITE);

    display.setCursor(6, 50);
    display.print("TPM:");
    display.print(tpm, 0);
    display.setCursor(75, 50);
    display.print(hours);
    display.print("j ");
    display.print(minutes);
    display.print("m");

    if (persen <= 10 && (millis() / 300) % 2) {
      display.fillRoundRect(100, 2, 25, 8, 2, BLACK);
      display.setCursor(104, 2);
      display.setTextColor(WHITE);
      display.print("LOW");
    }
    display.display();
    vTaskDelay(pdMS_TO_TICKS(150));
  }
}

// =====================================================
// ================= TASK SERIAL ========================
// =====================================================

void taskSerial(void *pvParameters) {
  while (1) {
    Serial.println("===== SMART INFUS =====");
    if (volumeMode == 0) Serial.println("MODE : 500ml");
    else if (volumeMode == 1) Serial.println("MODE : 100ml");
    else Serial.println("MODE : OTHER");
    Serial.print("TPM : ");
    Serial.println(tpm);
    Serial.print("Sisa : ");
    Serial.print(volumeSisaBerat);
    Serial.println(" ml");
    Serial.print("Estimasi : ");
    Serial.print(hours);
    Serial.print(" jam ");
    Serial.print(minutes);
    Serial.println(" menit");
    Serial.println(nurseCallActive ? "Nurse Call : AKTIF" : "Nurse Call : NONAKTIF");
    Serial.println(wifiConnected ? "WiFi : TERHUBUNG" : "WiFi : TERPUTUS");
    Serial.print("Device ID : ");
    Serial.println(cfg_deviceId);
    Serial.println("=======================\n");
    vTaskDelay(pdMS_TO_TICKS(2000));
  }
}

// =====================================================
// ================= TASK WIFI ==========================
// =====================================================

void taskWiFi(void *pvParameters) {
  WiFi.begin(cfg_ssid, cfg_password);
  Serial.print("Menghubungkan ke WiFi: ");
  Serial.println(cfg_ssid);
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED && retry < 20) {
    vTaskDelay(pdMS_TO_TICKS(500));
    Serial.print(".");
    retry++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    wifiConnected = true;
    Serial.println("\nWiFi Terhubung!");
    Serial.print("IP: ");
    Serial.println(WiFi.localIP());
  } else {
    wifiConnected = false;
    Serial.println("\nGagal terhubung WiFi!");
  }
  while (1) {
    if (WiFi.status() != WL_CONNECTED) {
      wifiConnected = false;
      Serial.println("WiFi terputus, mencoba reconnect...");
      WiFi.reconnect();
      vTaskDelay(pdMS_TO_TICKS(5000));
    } else {
      wifiConnected = true;
    }
    vTaskDelay(pdMS_TO_TICKS(10000));
  }
}

// =====================================================
// ================= TASK HTTP POST =====================
// =====================================================

void taskHTTPPost(void *pvParameters) {
  vTaskDelay(pdMS_TO_TICKS(5000));  // tunggu WiFi siap
  while (1) {
    if (wifiConnected) {
      HTTPClient http;
      http.begin(cfg_server);
      http.addHeader("Content-Type", "application/json");
      if (strlen(cfg_apiKey) > 0) {
        http.addHeader("X-API-Key", cfg_apiKey);
      }

      StaticJsonDocument<256> doc;
      doc["device_id"] = cfg_deviceId;
      doc["tpm"] = (int)tpm;
      doc["volume_sisa"] = (int)volumeSisaBerat;
      doc["volume_awal"] = (int)currentVolumeAwal;
      doc["persen"] = (int)persen;
      doc["estimasi_jam"] = hours;
      doc["estimasi_mnt"] = minutes;
      doc["total_tetes"] = (int)totalDrops;
      doc["nurse_call"] = nurseCallActive ? 1 : 0;
      doc["battery"] = (int)batteryPercent;
      if (volumeMode == 0) doc["mode"] = "500ml";
      else if (volumeMode == 1) doc["mode"] = "100ml";
      else doc["mode"] = "OTHER";

      String payload;
      serializeJson(doc, payload);
      Serial.print("Mengirim data: ");
      Serial.println(payload);

      int httpCode = http.POST(payload);
      if (httpCode == HTTP_CODE_OK) {
        String response = http.getString();
        Serial.print("Response: ");
        Serial.println(response);
      } else {
        Serial.print("HTTP Error: ");
        Serial.println(httpCode);
      }
      http.end();
    } else {
      Serial.println("Skip HTTP: WiFi tidak terhubung");
    }
    vTaskDelay(pdMS_TO_TICKS(1000));
  }
}

// =====================================================
// ================= SETUP ==============================
// =====================================================

void setup() {
  Serial.begin(115200);

  // ===== EEPROM =====
  EEPROM.begin(EEPROM_SIZE);
  if (loadConfig()) {
    Serial.println("[EEPROM] Konfigurasi dimuat dari EEPROM.");
    Serial.print("  SSID: ");
    Serial.println(cfg_ssid);
    Serial.print("  URL: ");
    Serial.println(cfg_server);
    Serial.print("  DeviceID: ");
    Serial.println(cfg_deviceId);
    Serial.printf("  Berat kantong — 500ml:%.1fg  100ml:%.1fg  Other:%.1fg\n",
                  cfg_berat500, cfg_berat100, cfg_beratOther);
  } else {
    Serial.println("[EEPROM] Belum ada konfigurasi, pakai default.");
  }

  // ===== BUZZER & BUTTON =====
  pinMode(BUTTON_PIN, INPUT);  // pull-down eksternal, tombol ditekan = HIGH
  pinMode(BUZZER, OUTPUT);
  digitalWrite(BUZZER, LOW);
  buzzerMutex = xSemaphoreCreateMutex();

  // ===== ADC BATERAI =====
  // Default attenuation ESP32 (0dB, max ~1.1V) tidak cukup untuk tegangan
  // baterai yang sudah dibagi 2 (1.6-2.1V). Pakai 11dB supaya pin bisa
  // baca sampai ~3.3V.
  analogSetPinAttenuation(BATTERY_PIN, ADC_11db);
  pinMode(BATTERY_PIN, INPUT);

  // ===== HX711 =====
  scale.begin(DOUT_PIN, SCK_PIN);
  scale.set_scale(calibrationFactor);
  delay(2000);
  scale.tare();

  // ===== OLED =====
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    while (1)
      ;
  }
  display.setTextWrap(false);

  // ===== IR =====
  pinMode(IR_SENSOR_PIN, INPUT_PULLUP);
  pinMode(LED_PIN, OUTPUT);

  // ===== NURSE BUTTON =====
  pinMode(NURSE_BUTTON_PIN, INPUT_PULLUP);
  // Baca state awal — jika LOW di sini berarti ada masalah hardware
  // (short ke GND, atau noise charger sudah aktif sebelum task jalan)
  delay(10);
  Serial.print("[NurseCall] State awal pin 19: ");
  Serial.println(digitalRead(NURSE_BUTTON_PIN) == HIGH ? "HIGH (normal)" : "LOW (periksa hardware)");

  // ===== TARE BUTTON =====
  pinMode(TARE_BUTTON_PIN, INPUT_PULLUP);  // tombol ditekan = LOW

  attachInterrupt(digitalPinToInterrupt(IR_SENSOR_PIN), onDropDetected, FALLING);

  updateVolumeMode();

  // =====================================================
  // ================= CREATE TASKS ======================
  // =====================================================

  xTaskCreatePinnedToCore(taskLoadCell, "LoadCell", 4000, NULL, 1, &hLoadCell, 1);
  xTaskCreatePinnedToCore(taskTPM, "TPM", 4000, NULL, 1, &hTPM, 1);
  xTaskCreatePinnedToCore(taskOLED, "OLED", 6000, NULL, 1, &hOLED, 0);
  xTaskCreatePinnedToCore(taskSerial, "Serial", 4000, NULL, 1, &hSerial, 0);
  xTaskCreatePinnedToCore(taskButton, "Button", 3000, NULL, 1, NULL, 0);
  xTaskCreatePinnedToCore(taskNurseCall, "NurseCall", 2000, NULL, 1, &hNurseCall, 0);
  xTaskCreatePinnedToCore(taskTare, "Tare", 2500, NULL, 1, &hTare, 0);
  xTaskCreatePinnedToCore(taskWiFi, "WiFi", 4000, NULL, 1, &hWiFi, 0);
  xTaskCreatePinnedToCore(taskHTTPPost, "HTTPPost", 8000, NULL, 1, &hHTTPPost, 0);
  xTaskCreatePinnedToCore(taskBattery, "Battery", 2500, NULL, 1, &hBattery, 0);
}

// =====================================================
// ================= LOOP ===============================
// =====================================================

void loop() {
  // kosong — semua logic di FreeRTOS tasks
}
