// =====================================================
// ================= SMART INFUS RTOS ===================
// =====================================================

#include "HX711.h"
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ================= WIFI =================
#define WIFI_SSID        "Redmi Note 14"
#define WIFI_PASSWORD    "aaaaaaab"

// ================= SERVER =================
// Ganti dengan IP server Laragon kamu
#define SERVER_URL       "http://10.200.50.130/infus_2/web/api/post_data.php"
#define DEVICE_ID        "INFUS-01"

// ================= HX711 =================
#define DOUT_PIN         5
#define SCK_PIN          4
#define BERAT_KANTONG    25.0

HX711 scale;

// Kalibrasi dihitung ulang berdasarkan pengukuran aktual:
//   Mode 500ml → terbaca 1390.11 g, seharusnya 550 g  → 420 × (1390.11/550) = 1060.8
//   Mode 100ml → terbaca 333.97 g,  seharusnya 150 g  → 420 × (333.97/150)  =  935.1
// Karena satu sensor dipakai untuk semua mode, gunakan nilai per-mode
// agar akurasi optimal di masing-masing rentang berat.
#define CAL_500ML   1060.8f   // calibration factor untuk kantong 500 ml
#define CAL_100ML    935.1f   // calibration factor untuk kantong 100 ml
#define CAL_OTHER    998.0f   // rata-rata keduanya untuk mode OTHER/unknown

float calibrationFactor = CAL_500ML;  // default mode 500ml

// ================= OLED ==================
#define SCREEN_WIDTH     128
#define SCREEN_HEIGHT    64

Adafruit_SSD1306 display(
  SCREEN_WIDTH,
  SCREEN_HEIGHT,
  &Wire,
  -1
);

// ================= IR ====================
#define IR_SENSOR_PIN    23
#define LED_PIN          2

#define DROPS_PER_ML     20.0

// ================= BUTTON =================
#define BUTTON_PIN       15
#define BUZZER           18
#define NURSE_BUTTON_PIN 19

// mode volume
volatile uint8_t volumeMode = 0;

// 0 = 500ml
// 1 = 100ml
// 2 = OTHER

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

// debounce button
unsigned long lastButtonPress = 0;
bool lastButtonState = HIGH;

// nurse call
volatile bool nurseCallActive = false;

unsigned long lastNursePress = 0;
bool lastNurseState = HIGH;

// wifi status
bool wifiConnected = false;

// =====================================================
// ================= INTERRUPT ==========================
// =====================================================

void IRAM_ATTR onDropDetected() {

  unsigned long now = millis();

  // debounce
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

  // reset total tetesan
  totalDrops = 0;

  // mode 500 ml
  if (volumeMode == 0) {

    currentVolumeAwal  = 500.0;
    calibrationFactor  = CAL_500ML;
  }

  // mode 100 ml
  else if (volumeMode == 1) {

    currentVolumeAwal  = 100.0;
    calibrationFactor  = CAL_100ML;
  }

  // mode OTHER — ambil berat loadcell saat ini sebagai acuan
  // jika loadcell belum baca (0), set ke 0 dulu,
  // taskLoadCell akan update saat berat masuk
  else {

    calibrationFactor  = CAL_OTHER;
    currentVolumeAwal  =
      volumeSisaBerat > 0 ?
      volumeSisaBerat : 0.0;
  }

  // terapkan calibration factor baru ke sensor
  scale.set_scale(calibrationFactor);
}

// =====================================================
// ================= TASK BUTTON ========================
// =====================================================

void taskButton(void *pvParameters) {

  while (1) {

    bool reading = digitalRead(BUTTON_PIN);

    // tombol ditekan
    if (reading == LOW &&
        lastButtonState == HIGH &&
        millis() - lastButtonPress > 250) {

      lastButtonPress = millis();

      digitalWrite(BUZZER, HIGH);
      vTaskDelay(pdMS_TO_TICKS(100));
      digitalWrite(BUZZER, LOW);

      // pindah mode
      volumeMode++;

      if (volumeMode > 2)
        volumeMode = 0;

      updateVolumeMode();
    }

    lastButtonState = reading;

    vTaskDelay(pdMS_TO_TICKS(20));
  }
}

// =====================================================
// ================= TASK NURSE CALL ===================
// =====================================================

void taskNurseCall(void *pvParameters) {

  while (1) {

    bool reading = digitalRead(NURSE_BUTTON_PIN);

    // toggle saat ditekan
    if (reading == LOW &&
        lastNurseState == HIGH &&
        millis() - lastNursePress > 250) {
          digitalWrite(BUZZER, HIGH);
          delay(100);
          digitalWrite(BUZZER, LOW);

      lastNursePress = millis();

      nurseCallActive = !nurseCallActive;
    }

    lastNurseState = reading;

    // alarm nurse call
    if (nurseCallActive) {

      digitalWrite(BUZZER, LOW);
      vTaskDelay(pdMS_TO_TICKS(150));

      digitalWrite(BUZZER, LOW);
      vTaskDelay(pdMS_TO_TICKS(150));
    }
    else {

      vTaskDelay(pdMS_TO_TICKS(30));
    }
  }
}

// =====================================================
// ================= TASK HX711 =========================
// =====================================================

void taskLoadCell(void *pvParameters) {

  while (1) {

    float beratTotal = scale.get_units(3);

    if (beratTotal < 0)
      beratTotal = 0;

    float beratBersih =
      beratTotal - BERAT_KANTONG;

    if (beratBersih < 0)
      beratBersih = 0;

    volumeSisaBerat = beratBersih;

    // mode OTHER: volume awal mengikuti berat saat ini secara dinamis
    // hanya update jika berat naik (infus baru dipasang)
    if (volumeMode == 2) {
      if (volumeSisaBerat > currentVolumeAwal) {
        currentVolumeAwal = volumeSisaBerat;
      }
    }

    // batas maksimum untuk mode 500ml dan 100ml
    if (volumeMode != 2) {

      if (volumeSisaBerat >
          currentVolumeAwal) {

        volumeSisaBerat =
          currentVolumeAwal;
      }
    }

    // persen
    if (currentVolumeAwal > 0) {

      persen =
        (volumeSisaBerat /
         currentVolumeAwal) * 100.0;
    }

    if (persen > 100)
      persen = 100;

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

    // interval 3 detik
    tpm = tempDrops * 20.0;

    // estimasi sisa waktu
    float ml_used =
      totalDrops / DROPS_PER_ML;

    float ml_remaining =
      currentVolumeAwal - ml_used;

    if (ml_remaining < 0)
      ml_remaining = 0;

    float ml_per_min =
      tpm / DROPS_PER_ML;

    float remaining_minutes = 0;

    if (ml_per_min > 0)
      remaining_minutes =
        ml_remaining / ml_per_min;

    hours =
      (int)(remaining_minutes / 60);

    minutes =
      (int)remaining_minutes % 60;

    vTaskDelay(pdMS_TO_TICKS(3000));
  }
}

// =====================================================
// ================= TASK OLED ==========================
// =====================================================

void taskOLED(void *pvParameters) {

  while (1) {

    display.clearDisplay();

    // ===== FRAME =====
    display.drawRoundRect(
      0, 0, 128, 64, 5, WHITE
    );

    // ===== HEADER =====
    display.fillRoundRect(
      0, 0, 128, 12, 5, WHITE
    );

    display.setTextColor(BLACK);
    display.setTextSize(1);

    display.setCursor(28, 2);
    display.println("SMART INFUS");

    display.setTextColor(WHITE);

    // ===== MODE =====
    display.setCursor(5, 14);

    display.print("MODE:");

    if (volumeMode == 0) {

      display.print("500ml");
    }
    else if (volumeMode == 1) {

      display.print("100ml");
    }
    else {

      display.print("OTHER");
    }

    // ===== WIFI STATUS =====
    display.setCursor(90, 14);

    if (wifiConnected) {
      display.print("WiFi OK");
    } else {
      display.print("No WiFi");
    }

    // ===== NURSE CALL =====
    if (nurseCallActive) {

      if ((millis() / 250) % 2) {

        display.fillRoundRect(
          88, 14, 35, 10, 2, WHITE
        );

        display.setTextColor(BLACK);

        display.setCursor(94, 16);
        display.print("CALL");

        display.setTextColor(WHITE);
      }
    }

    // ===== ICON INFUS =====
    display.drawLine(120, 14, 120, 28, WHITE);

    int dropY =
      (millis() / 300) % 2 ? 25 : 30;

    display.fillCircle(
      120,
      dropY,
      2,
      WHITE
    );

    display.fillTriangle(
      118, dropY,
      122, dropY,
      120, dropY + 4,
      WHITE
    );

    // ===== VOLUME =====
    display.setCursor(5, 24);

    display.print("SISA:");

    display.print(volumeSisaBerat, 0);

    display.print("|");

    if (volumeMode == 2) {

      display.print("AUTO");
      display.print((int)currentVolumeAwal);
    }
    else {

      display.print((int)currentVolumeAwal);
      display.print("ml");
    }

    // ===== PROGRESS =====
    display.drawRoundRect(
      5, 36, 118, 10, 3, WHITE
    );

    int bar =
      map((int)persen, 0, 100, 0, 114);

    display.fillRoundRect(
      7, 38, bar, 6, 2, WHITE
    );

    // ===== TPM =====
    display.setCursor(5, 50);

    display.print("TPM:");
    display.print(tpm, 0);

    // ===== ESTIMASI =====
    display.setCursor(65, 50);

    display.print(hours);
    display.print("j ");

    display.print(minutes);
    display.print("m");

    // ===== WARNING =====
    if (persen <= 10) {

      if ((millis() / 300) % 2) {

        display.fillRoundRect(
          95, 2, 30, 8, 2, BLACK
        );

        display.setCursor(100, 2);
        display.setTextColor(WHITE);
        display.print("LOW");
      }
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

    Serial.print("MODE : ");

    if (volumeMode == 0)
      Serial.println("500ml");

    else if (volumeMode == 1)
      Serial.println("100ml");

    else
      Serial.println("OTHER");

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

    Serial.print("Nurse Call : ");

    if (nurseCallActive)
      Serial.println("AKTIF");
    else
      Serial.println("NONAKTIF");

    Serial.print("WiFi : ");
    Serial.println(wifiConnected ? "TERHUBUNG" : "TERPUTUS");

    Serial.println("=======================\n");

    vTaskDelay(pdMS_TO_TICKS(2000));
  }
}

// =====================================================
// ================= TASK WIFI ==========================
// =====================================================

void taskWiFi(void *pvParameters) {

  // koneksi awal
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("Menghubungkan ke WiFi");

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
  }
  else {

    wifiConnected = false;
    Serial.println("\nGagal terhubung WiFi!");
  }

  while (1) {

    // cek koneksi setiap 10 detik
    if (WiFi.status() != WL_CONNECTED) {

      wifiConnected = false;
      Serial.println("WiFi terputus, mencoba reconnect...");

      WiFi.reconnect();

      vTaskDelay(pdMS_TO_TICKS(5000));
    }
    else {

      wifiConnected = true;
    }

    vTaskDelay(pdMS_TO_TICKS(10000));
  }
}

// =====================================================
// ================= TASK HTTP POST =====================
// =====================================================

void taskHTTPPost(void *pvParameters) {

  // tunggu WiFi siap dulu
  vTaskDelay(pdMS_TO_TICKS(5000));

  while (1) {

    if (wifiConnected) {

      HTTPClient http;

      http.begin(SERVER_URL);
      http.addHeader("Content-Type", "application/json");

      // buat JSON payload
      StaticJsonDocument<256> doc;

      doc["device_id"]    = DEVICE_ID;
      doc["tpm"]          = (int)tpm;
      doc["volume_sisa"]  = (int)volumeSisaBerat;
      doc["volume_awal"]  = (int)currentVolumeAwal;
      doc["persen"]       = (int)persen;
      doc["estimasi_jam"] = hours;
      doc["estimasi_mnt"] = minutes;
      doc["total_tetes"]  = (int)totalDrops;
      doc["nurse_call"]   = nurseCallActive ? 1 : 0;

      // mode string
      if (volumeMode == 0)
        doc["mode"] = "500ml";
      else if (volumeMode == 1)
        doc["mode"] = "100ml";
      else
        doc["mode"] = "OTHER";

      String payload;
      serializeJson(doc, payload);

      Serial.print("Mengirim data: ");
      Serial.println(payload);

      int httpCode = http.POST(payload);

      if (httpCode == HTTP_CODE_OK) {

        String response = http.getString();
        Serial.print("Response: ");
        Serial.println(response);
      }
      else {

        Serial.print("HTTP Error: ");
        Serial.println(httpCode);
      }

      http.end();
    }
    else {

      Serial.println("Skip HTTP: WiFi tidak terhubung");
    }

    // kirim setiap 5 detik
    vTaskDelay(pdMS_TO_TICKS(1000));
  }
}

// =====================================================
// ================= SETUP ==============================
// =====================================================

void setup() {

  Serial.begin(115200);

  // ===== HX711 =====
  scale.begin(DOUT_PIN, SCK_PIN);
  scale.set_scale(calibrationFactor);  // CAL_500ML (default mode awal)

  delay(2000);

  scale.tare();  // nol-kan timbangan (tanpa beban)

  // ===== OLED =====
  if (!display.begin(
        SSD1306_SWITCHCAPVCC,
        0x3C
      )) {

    while (1);
  }

  // ===== IR =====
  pinMode(IR_SENSOR_PIN, INPUT_PULLUP);

  pinMode(LED_PIN, OUTPUT);

  // ===== BUZZER =====
  pinMode(BUZZER, OUTPUT);

  // ===== BUTTON =====
  pinMode(BUTTON_PIN, INPUT_PULLUP);

  // ===== NURSE BUTTON =====
  pinMode(NURSE_BUTTON_PIN, INPUT_PULLUP);

  attachInterrupt(
    digitalPinToInterrupt(IR_SENSOR_PIN),
    onDropDetected,
    FALLING
  );

  // set default mode
  updateVolumeMode();

  // =====================================================
  // ================= CREATE TASK =======================
  // =====================================================

  xTaskCreatePinnedToCore(
    taskLoadCell,
    "LoadCell",
    4000,
    NULL,
    1,
    NULL,
    1
  );

  xTaskCreatePinnedToCore(
    taskTPM,
    "TPM",
    4000,
    NULL,
    1,
    NULL,
    1
  );

  xTaskCreatePinnedToCore(
    taskOLED,
    "OLED",
    6000,
    NULL,
    1,
    NULL,
    0
  );

  xTaskCreatePinnedToCore(
    taskSerial,
    "Serial",
    4000,
    NULL,
    1,
    NULL,
    0
  );

  xTaskCreatePinnedToCore(
    taskButton,
    "Button",
    2000,
    NULL,
    1,
    NULL,
    0
  );

  xTaskCreatePinnedToCore(
    taskNurseCall,
    "NurseCall",
    2000,
    NULL,
    1,
    NULL,
    0
  );

  xTaskCreatePinnedToCore(
    taskWiFi,
    "WiFi",
    4000,
    NULL,
    1,
    NULL,
    0
  );

  xTaskCreatePinnedToCore(
    taskHTTPPost,
    "HTTPPost",
    8000,
    NULL,
    1,
    NULL,
    0
  );
}

// =====================================================
// ================= LOOP ===============================
// =====================================================

void loop() {

  // kosong
}
