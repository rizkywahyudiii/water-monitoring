// ===================== MONITORING AIR - VERSI PRESISI TINGGI =====================
// Update: Threshold Turbidity Baru & Ultrasonik Float (0.1 cm resolution)

// #define BLYNK_TEMPLATE_ID "TMPL67ie_LrqP"
// #define BLYNK_TEMPLATE_NAME "Monitoring Level Air"
// #define BLYNK_AUTH_TOKEN "zEGrLk5l-VohIgMae9uO83qbB94xuo5m"

#include <WiFi.h>
// #include <BlynkSimpleEsp32.h> // Blynk dimatikan sementara
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>

// ===================== KONFIGURASI OLED =====================
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// ===================== KONFIGURASI WIFI =====================
char ssid[] = "Galaxy";
char pass[] = "dandadan";

// ===================== KONFIGURASI SERVER =====================
const char* serverURL = "http://kel6.myiot.fun/api/sensor";

// ===================== PIN SENSOR =====================
#define TRIG 5
#define ECHO 18
#define TURBIDITY_PIN 34 

// ===================== PIN LED TRAFFIC LIGHT =====================
#define LED_RED 25
#define LED_YELLOW 26
#define LED_GREEN 27

// ===================== VARIABEL GLOBAL =====================
long duration;
float distance;       // Kita pakai float agar bisa baca koma (misal 12.5 cm)
int waterLevelPercent;
int turbidityValue;
float turbidityVoltage;
String turbidityStatus;
float predictedHours = 0.0;
String timeRemaining = "Menghitung...";

const float maxHeight = 14.0; // Ubah ke float untuk presisi
const float minHeight = 1.0;

// BlynkTimer timer;
unsigned long lastSendTime = 0;
const unsigned long SEND_INTERVAL = 10000; // Kirim data tiap 10 detik

// ===================== FUNGSI UPDATE OLED (RAPI & JELAS) =====================
void updateOLED() {
  display.clearDisplay();
  display.setTextColor(SSD1306_WHITE);

  // --- BARIS 1: LEVEL AIR (Font Besar) ---
  display.setTextSize(2); 
  display.setCursor(0, 0);
  display.print(waterLevelPercent);
  display.setTextSize(1);
  display.print(" %");

  // --- BARIS 1 (KANAN): JARAK (Presisi 1 Desimal) ---
  display.setTextSize(1);
  display.setCursor(75, 0); 
  display.print(distance, 1); // Tampilkan 1 angka di belakang koma (cth: 12.5)
  display.println(" cm");
  
  display.setCursor(75, 10);
  display.print("Jarak");

  // --- BARIS 2: STATUS KEKERUHAN ---
  display.setCursor(0, 28);
  display.setTextSize(1);
  display.print("Status: "); 
  display.println(turbidityStatus); 

  // --- BARIS 3: GARIS PEMISAH ---
  display.drawLine(0, 43, 128, 43, SSD1306_WHITE);

  // --- BARIS 4: ESTIMASI ---
  display.setCursor(0, 47);
  display.print("Est: ");
  display.println(timeRemaining); 

  display.display();
}

// ===================== FUNGSI LAMPU INDIKATOR =====================
void updateTrafficLight() {
  if (waterLevelPercent >= 50) {
    digitalWrite(LED_GREEN, HIGH);
    digitalWrite(LED_YELLOW, LOW);
    digitalWrite(LED_RED, LOW);
  } 
  else if (waterLevelPercent > 20 && waterLevelPercent < 50) {
    digitalWrite(LED_GREEN, LOW);
    digitalWrite(LED_YELLOW, HIGH);
    digitalWrite(LED_RED, LOW);
  } 
  else {
    digitalWrite(LED_GREEN, LOW);
    digitalWrite(LED_YELLOW, LOW);
    digitalWrite(LED_RED, HIGH);
  }
}

// ===================== FUNGSI KIRIM DATA KE SERVER =====================
bool sendToServer(float turbidity, float dist, int level) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("❌ WiFi not connected");
    return false;
  }
  
  HTTPClient http;
  http.begin(serverURL);
  http.addHeader("Content-Type", "application/json");

  String jsonBody = "{";
  jsonBody += "\"turbidity\":" + String(turbidity, 2) + ",";
  jsonBody += "\"distance\":" + String(dist, 1) + ","; // Kirim presisi
  jsonBody += "\"water_level\":" + String(level);
  jsonBody += "}";
  
  Serial.print("📤 POST: ");
  Serial.println(jsonBody);
  
  int httpResponseCode = http.POST(jsonBody);

  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("✅ Resp: " + response);

    // Parsing Prediksi
    int idxHours = response.indexOf("\"predicted_hours\":");
    if (idxHours > 0) {
      int start = idxHours + 18;
      int end = response.indexOf(",", start);
      if (end == -1) end = response.indexOf("}", start);
      String hoursStr = response.substring(start, end);
      predictedHours = hoursStr.toFloat();
    }

    int idxTime = response.indexOf("\"time\":\"");
    if (idxTime > 0) {
      int start = idxTime + 8;
      int end = response.indexOf("\"", start);
      timeRemaining = response.substring(start, end);
      Serial.println("🔮 Est: " + timeRemaining);
    }
    http.end();
    return true;
  } else {
    Serial.print("❌ Error: ");
    Serial.println(httpResponseCode);
    http.end();
    return false;
  }
}

// ===================== FUNGSI BACA SENSOR (UTAMA) =====================
void sendSensor() {
  // === 1. Sensor Ultrasonik (Mode Presisi Float) ===
  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);

  duration = pulseIn(ECHO, HIGH);
  // Rumus: (durasi * 0.034) / 2. Hasilnya Float (ada komanya)
  distance = duration * 0.034 / 2.0;

  // Hitung Persen dengan Matematika Float (Biar halus)
  float waterHeight = maxHeight - distance;
  
  // Rumus Persen Manual (Bukan map integer)
  // (Tinggi Air / Total Range) * 100
  float percentFloat = (waterHeight / (maxHeight - minHeight)) * 100.0;
  
  // Konversi ke Int setelah perhitungan selesai
  waterLevelPercent = (int)percentFloat;
  waterLevelPercent = constrain(waterLevelPercent, 0, 100);

  // === 2. Sensor Kekeruhan (LOGIKA BARU >1800, >1750) ===
  turbidityValue = analogRead(TURBIDITY_PIN);
  turbidityVoltage = turbidityValue * (3.3 / 4095.0); 

  // THRESHOLD BARU:
  // Jernih: > 1800
  // Agak Keruh: 1751 - 1800
  // Keruh: <= 1750
  
  if (turbidityValue > 1800) {
    turbidityStatus = "JERNIH";
  } 
  else if (turbidityValue > 1750) {
    turbidityStatus = "AGAK KERUH";
  } 
  else {
    turbidityStatus = "KERUH";
  }

  // === Update Output ===
  updateTrafficLight();
  
  bool serverSuccess = sendToServer(turbidityVoltage, distance, waterLevelPercent);
  if (serverSuccess) Serial.println("✅ DB OK");
  else Serial.println("⚠️ DB Fail");

  // === Blynk (OFF) ===
  // Blynk.virtualWrite(V0, waterLevelPercent);
  // Blynk.virtualWrite(V1, String(distance, 1) + " cm"); // Kirim desimal
  // Blynk.virtualWrite(V6, turbidityVoltage);
  // Blynk.virtualWrite(V7, turbidityStatus);

  updateOLED();

  // Debug
  Serial.print("Jarak: ");
  Serial.print(distance, 1); // 1 angka belakang koma
  Serial.print("cm | Lvl: ");
  Serial.print(waterLevelPercent);
  Serial.print("% | ADC: ");
  Serial.print(turbidityValue);
  Serial.print(" | Stat: ");
  Serial.println(turbidityStatus);
}

// ===================== FUNGSI DISPLAY REALTIME (CEPAT) =====================
void updateDisplayRealtime() {
  // Baca Ultrasonik
  digitalWrite(TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG, LOW);

  duration = pulseIn(ECHO, HIGH);
  distance = duration * 0.034 / 2.0;

  float waterHeight = maxHeight - distance;
  float percentFloat = (waterHeight / (maxHeight - minHeight)) * 100.0;
  
  waterLevelPercent = (int)percentFloat;
  waterLevelPercent = constrain(waterLevelPercent, 0, 100);

  // Baca Turbidity
  turbidityValue = analogRead(TURBIDITY_PIN);

  if (turbidityValue > 1800) {
    turbidityStatus = "JERNIH";
  } 
  else if (turbidityValue > 1750) {
    turbidityStatus = "AGAK KERUH";
  } 
  else {
    turbidityStatus = "KERUH";
  }

  updateOLED();
}

// ===================== SETUP =====================
void setup() {
  Serial.begin(115200);
  delay(1000);

  // Init OLED
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println(F("OLED gagal!"));
    for (;;);
  }
  
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  display.println("Connecting WiFi...");
  display.display();
  
  pinMode(TRIG, OUTPUT);
  pinMode(ECHO, INPUT);
  pinMode(TURBIDITY_PIN, INPUT);
  
  pinMode(LED_RED, OUTPUT);
  pinMode(LED_YELLOW, OUTPUT);
  pinMode(LED_GREEN, OUTPUT);
  
  digitalWrite(LED_RED, LOW);
  digitalWrite(LED_YELLOW, LOW);
  digitalWrite(LED_GREEN, LOW);

  // WiFi
  WiFi.begin(ssid, pass);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ WiFi OK");
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("WiFi Connected!");
    display.print("IP: ");
    display.println(WiFi.localIP());
    display.display();
    delay(2000);
  } else {
    display.clearDisplay();
    display.setCursor(0, 0);
    display.println("WiFi Failed!");
    display.display();
  }

  configTime(7 * 3600, 0, "pool.ntp.org", "time.google.com");

  // Blynk.begin(BLYNK_AUTH_TOKEN, ssid, pass); // OFF
  
  lastSendTime = millis();
}

// ===================== LOOP =====================
void loop() {
  // Blynk.run(); // OFF
  
  unsigned long currentTime = millis();
  
  // 1. Update Layar (Cepat: 0.5 detik)
  static unsigned long lastDisplayUpdate = 0;
  if (currentTime - lastDisplayUpdate >= 500) {
    updateDisplayRealtime();
    lastDisplayUpdate = currentTime;
  }
  
  // 2. Kirim Data Server (Lambat: 10 detik)
  if (currentTime - lastSendTime >= SEND_INTERVAL) {
    sendSensor();
    lastSendTime = currentTime;
  }
}