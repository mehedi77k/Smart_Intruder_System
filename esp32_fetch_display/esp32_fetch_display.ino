#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
LiquidCrystal_I2C lcd(0x27, 16, 2);

const char* ssid     = "*********";
const char* password = "**********";


const char* INTRUDER_URL  = "http://your_device_ip/smart_intruder_api/fetch_latest.php";
const char* MODE_URL      = "http://your_device_ip/smart_intruder_api/get_system_status.php";
const char* SET_MODE_URL  = "http://your_device_ip/smart_intruder_api/set_mode.php";
const char* HEARTBEAT_URL = "http://your_device_ip/smart_intruder_api/update_heartbeat.php";


#define LED_PIN              5
#define BUZZER_PIN           18
#define AT_HOME_BUTTON_PIN   19
#define NOT_HOME_BUTTON_PIN  23

String currentMode = "AT_HOME";
int intruderCount = 0;

String lastDisplayedMode = "";
int lastDisplayedIntruder = -1;

unsigned long lastModeFetch = 0;
unsigned long lastIntruderFetch = 0;
unsigned long lastHeartbeatTime = 0;
unsigned long lastBlinkTime = 0;
unsigned long ignoreModeFetchUntil = 0;

const unsigned long MODE_FETCH_INTERVAL     = 1000;
const unsigned long INTRUDER_FETCH_INTERVAL = 1000;
const unsigned long HEARTBEAT_INTERVAL      = 5000;
const unsigned long BLINK_INTERVAL          = 200;
const unsigned long DEBOUNCE_DELAY          = 50;
const unsigned long MODE_FETCH_GUARD_MS     = 1200;

bool lastAtHomeReading = HIGH;
bool stableAtHomeState = HIGH;
unsigned long lastAtHomeDebounceTime = 0;

bool lastNotHomeReading = HIGH;
bool stableNotHomeState = HIGH;
unsigned long lastNotHomeDebounceTime = 0;

bool ledBlinkState = false;

String normalizeMode(String mode) {
  mode.trim();
  mode.toUpperCase();

  if (mode == "AT_HOME" || mode == "AT HOME" || mode == "HOME") {
    return "AT_HOME";
  }

  if (mode == "NOT_AT_HOME" || mode == "NOT AT HOME" || mode == "AWAY" || mode == "NOTHOME") {
    return "NOT_AT_HOME";
  }

  return "AT_HOME";
}

void printLine(byte row, String text) {
  if (text.length() > 16) {
    text = text.substring(0, 16);
  }

  while (text.length() < 16) {
    text += " ";
  }

  lcd.setCursor(0, row);
  lcd.print(text);
}

void showMessage(String line1, String line2, int waitMs = 0) {
  lcd.clear();
  printLine(0, line1);
  printLine(1, line2);

  if (waitMs > 0) {
    delay(waitMs);
  }
}

void connectWiFi() {
  showMessage("Connecting WiFi", "Please wait...");

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("WiFi Connected");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());

  showMessage("WiFi Connected", WiFi.localIP().toString(), 1200);
  lcd.clear();
}

bool reconnectWiFiIfNeeded() {
  if (WiFi.status() == WL_CONNECTED) {
    return true;
  }

  digitalWrite(LED_PIN, LOW);
  digitalWrite(BUZZER_PIN, LOW);

  showMessage("WiFi Lost", "Reconnecting...");
  Serial.println("WiFi disconnected. Reconnecting...");

  WiFi.disconnect();
  WiFi.begin(ssid, password);

  unsigned long startAttempt = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - startAttempt < 10000) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi reconnected");
    showMessage("WiFi Connected", WiFi.localIP().toString(), 1200);
    lcd.clear();

    sendHeartbeat();
    fetchCurrentMode();
    fetchIntruderCount();
    updateDisplay();

    lastHeartbeatTime = millis();
    lastModeFetch = millis();
    lastIntruderFetch = millis();
    return true;
  }

  delay(1000);
  return false;
}

bool sendHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  HTTPClient http;
  http.begin(HEARTBEAT_URL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(3000);

  String body = "device=handheld_esp32";
  int httpCode = http.POST(body);

  Serial.print("Heartbeat HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode >= 200 && httpCode < 300) {
    String payload = http.getString();
    Serial.println("Heartbeat Response:");
    Serial.println(payload);
    http.end();
    return true;
  }

  http.end();
  return false;
}

bool fetchIntruderCount() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  HTTPClient http;
  http.begin(INTRUDER_URL);
  http.setTimeout(3000);

  int httpCode = http.GET();

  Serial.print("Intruder API HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode >= 200 && httpCode < 300) {
    String payload = http.getString();

    Serial.println("Intruder Payload:");
    Serial.println(payload);

    DynamicJsonDocument doc(512);
    DeserializationError error = deserializeJson(doc, payload);

    if (!error && doc.is<JsonObject>()) {
      intruderCount = doc["detection"] | 0;
      http.end();
      return true;
    } else if (error) {
      Serial.print("Intruder JSON Error: ");
      Serial.println(error.c_str());
    }
  }

  http.end();
  return false;
}

bool fetchCurrentMode() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  HTTPClient http;
  http.begin(MODE_URL);
  http.setTimeout(3000);

  int httpCode = http.GET();

  Serial.print("Mode API HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode >= 200 && httpCode < 300) {
    String payload = http.getString();

    Serial.println("Mode Payload:");
    Serial.println(payload);

    DynamicJsonDocument doc(1536);
    DeserializationError error = deserializeJson(doc, payload);

    if (!error) {
      const char* modeValue = doc["current_mode"] | "AT_HOME";
      currentMode = normalizeMode(String(modeValue));
      http.end();
      return true;
    } else {
      Serial.print("Mode JSON Error: ");
      Serial.println(error.c_str());
    }
  }

  http.end();
  return false;
}

bool setModeOnServer(const String& requestedMode) {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }

  String newMode = normalizeMode(requestedMode);

  HTTPClient http;
  http.begin(SET_MODE_URL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(3000);

  String body = "mode=" + newMode + "&source=esp32";
  int httpCode = http.POST(body);

  Serial.print("Set Mode HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode >= 200 && httpCode < 300) {
    String payload = http.getString();

    Serial.println("Set Mode Response:");
    Serial.println(payload);

    DynamicJsonDocument doc(1024);
    DeserializationError error = deserializeJson(doc, payload);

    if (!error) {
      const char* statusValue = doc["status"] | "error";

      if (String(statusValue) == "success") {
        const char* modeValue = doc["current_mode"] | newMode.c_str();
        currentMode = normalizeMode(String(modeValue));
        http.end();
        return true;
      } else {
        Serial.println("Server returned non-success status");
      }
    } else {
      Serial.print("Set Mode JSON Error: ");
      Serial.println(error.c_str());
    }
  }

  http.end();
  return false;
}

void updateDisplay() {
  if (currentMode == lastDisplayedMode && intruderCount == lastDisplayedIntruder) {
    return;
  }

  printLine(0, "Mode:" + currentMode);
  printLine(1, "Intruder:" + String(intruderCount));

  lastDisplayedMode = currentMode;
  lastDisplayedIntruder = intruderCount;
}

void controlOutputs() {
  if (intruderCount == 0) {
    digitalWrite(BUZZER_PIN, LOW);
    digitalWrite(LED_PIN, HIGH);
    return;
  }

  digitalWrite(BUZZER_PIN, HIGH);

  if (millis() - lastBlinkTime >= BLINK_INTERVAL) {
    lastBlinkTime = millis();
    ledBlinkState = !ledBlinkState;
    digitalWrite(LED_PIN, ledBlinkState ? HIGH : LOW);
  }
}

void requestModeChange(const String& targetMode) {
  String normalizedTarget = normalizeMode(targetMode);

  if (normalizedTarget == currentMode) {
    Serial.println("Requested mode already active");
    return;
  }

  Serial.print("Requesting mode change -> ");
  Serial.println(normalizedTarget);

  showMessage("Updating Mode...", normalizedTarget);

  bool ok = setModeOnServer(normalizedTarget);

  if (ok) {
    ignoreModeFetchUntil = millis() + MODE_FETCH_GUARD_MS;
    updateDisplay();
    Serial.println("Mode updated from button");
  } else {
    showMessage("Mode Update Fail", "Check Server", 1000);
    fetchCurrentMode();
    updateDisplay();
  }
}

void handleModeButtons() {
  
  bool atReading = digitalRead(AT_HOME_BUTTON_PIN);

  if (atReading != lastAtHomeReading) {
    lastAtHomeDebounceTime = millis();
  }

  if ((millis() - lastAtHomeDebounceTime) > DEBOUNCE_DELAY) {
    if (atReading != stableAtHomeState) {
      stableAtHomeState = atReading;

      if (stableAtHomeState == LOW) {
        requestModeChange("AT_HOME");
      }
    }
  }

  lastAtHomeReading = atReading;

  bool notReading = digitalRead(NOT_HOME_BUTTON_PIN);

  if (notReading != lastNotHomeReading) {
    lastNotHomeDebounceTime = millis();
  }

  if ((millis() - lastNotHomeDebounceTime) > DEBOUNCE_DELAY) {
    if (notReading != stableNotHomeState) {
      stableNotHomeState = notReading;

      if (stableNotHomeState == LOW) {
        requestModeChange("NOT_AT_HOME");
      }
    }
  }

  lastNotHomeReading = notReading;
}

void setup() {
  Serial.begin(115200);

  pinMode(LED_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(AT_HOME_BUTTON_PIN, INPUT_PULLUP);
  pinMode(NOT_HOME_BUTTON_PIN, INPUT_PULLUP);

  digitalWrite(LED_PIN, LOW);
  digitalWrite(BUZZER_PIN, LOW);

  lcd.init();
  lcd.backlight();


  stableAtHomeState = digitalRead(AT_HOME_BUTTON_PIN);
  lastAtHomeReading = stableAtHomeState;

  stableNotHomeState = digitalRead(NOT_HOME_BUTTON_PIN);
  lastNotHomeReading = stableNotHomeState;

  connectWiFi();

  fetchCurrentMode();
  fetchIntruderCount();
  sendHeartbeat();
  updateDisplay();

  lastHeartbeatTime = millis();
  lastModeFetch = millis();
  lastIntruderFetch = millis();
}


void loop() {
  if (!reconnectWiFiIfNeeded()) {
    return;
  }

  unsigned long now = millis();

  handleModeButtons();

  if (now - lastHeartbeatTime >= HEARTBEAT_INTERVAL) {
    lastHeartbeatTime = now;
    sendHeartbeat();
  }

  if (now - lastIntruderFetch >= INTRUDER_FETCH_INTERVAL) {
    lastIntruderFetch = now;
    fetchIntruderCount();
  }

  if (now >= ignoreModeFetchUntil && (now - lastModeFetch >= MODE_FETCH_INTERVAL)) {
    lastModeFetch = now;
    fetchCurrentMode();
  }

  updateDisplay();
  controlOutputs();

  delay(20);
}
