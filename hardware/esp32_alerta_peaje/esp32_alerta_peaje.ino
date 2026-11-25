/*
 * ESP32 de alertas de la caseta - Sistema Inteligente de Control de Peaje (IAM-811)
 *
 *   LED AZUL  (GPIO 25) -> inferencia en curso (parpadeo)
 *   LED VERDE (GPIO 26) -> paso autorizado (fijo 3 s + 1 beep)
 *   LED ROJO  (GPIO 27) -> alerta: placa no registrada / sin saldo / placa ilegible (parpadeo rapido + 3 beeps)
 *   BUZZER    (GPIO 14)
 *
 * Se conecta por Wi-Fi y consulta por HTTPS, cada 1 s, el backend EN LA NUBE (sin PC intermedia):
 *   GET https://API_HOST/api/v1/hardware/ultimo-evento?caseta_id=N&rssi=-60    (cabecera X-Hardware-Token)
 *   -> {"id": 57, "tipo_senal": "idle|inferencia|ok|alerta", "placa": "4047CKE", "monto": 5, ...}
 *
 * Arduino IDE 2.x: placa "ESP32 Dev Module" (paquete esp32 de Espressif) + libreria ArduinoJson 7.x.
 * Configuracion: copia secrets.h.example -> secrets.h y completa Wi-Fi, dominio, token y caseta.
 */
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "secrets.h"

const int PIN_AZUL = 25;
const int PIN_VERDE = 26;
const int PIN_ROJO = 27;
const int PIN_BUZZER = 14;
const unsigned long INTERVALO_MS = 1000;

long ultimoId = -1;          // ultimo evento ya mostrado
String senalActual = "idle";
unsigned long finSenal = 0;  // cuando apagar el LED verde/rojo

void apagarTodo() {
  digitalWrite(PIN_AZUL, LOW);
  digitalWrite(PIN_VERDE, LOW);
  digitalWrite(PIN_ROJO, LOW);
  digitalWrite(PIN_BUZZER, LOW);
}

void beep(int veces, int ms) {
  for (int i = 0; i < veces; i++) {
    digitalWrite(PIN_BUZZER, HIGH); delay(ms);
    digitalWrite(PIN_BUZZER, LOW);  delay(90);
  }
}

void autoPrueba() {  // al encender: azul, verde, rojo y un beep para comprobar el cableado
  int pines[] = {PIN_AZUL, PIN_VERDE, PIN_ROJO};
  for (int p : pines) { digitalWrite(p, HIGH); delay(300); digitalWrite(p, LOW); }
  beep(1, 120);
}

void conectarWifi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("[wifi] conectando a "); Serial.println(WIFI_SSID);
  while (WiFi.status() != WL_CONNECTED) {
    digitalWrite(PIN_ROJO, !digitalRead(PIN_ROJO));  // rojo parpadea mientras conecta
    delay(400); Serial.print(".");
  }
  digitalWrite(PIN_ROJO, LOW);
  Serial.printf("\n[wifi] OK  IP %s  RSSI %d dBm\n", WiFi.localIP().toString().c_str(), WiFi.RSSI());
}

// Efectos visuales continuos segun la senal actual (se llama en cada vuelta del loop)
void animar() {
  unsigned long t = millis();
  if (senalActual == "inferencia") {
    digitalWrite(PIN_AZUL, (t / 250) % 2);            // parpadeo azul
  } else if (senalActual == "alerta" && t < finSenal) {
    digitalWrite(PIN_ROJO, (t / 120) % 2);            // parpadeo rojo rapido
  } else if (senalActual == "ok" && t < finSenal) {
    digitalWrite(PIN_VERDE, HIGH);                    // verde fijo
  } else if (t >= finSenal && senalActual != "inferencia") {
    apagarTodo();
  }
}

void aplicar(const String& tipo, long id, const char* placa) {
  apagarTodo();
  senalActual = tipo;
  Serial.printf("[evento] id=%ld senal=%s placa=%s\n", id, tipo.c_str(), placa ? placa : "-");
  if (tipo == "ok") {
    finSenal = millis() + 3000;
    digitalWrite(PIN_VERDE, HIGH);
    beep(1, 120);
  } else if (tipo == "alerta") {
    finSenal = millis() + 3000;
    beep(3, 160);
  } else {
    finSenal = 0;
  }
}

void consultar() {
  WiFiClientSecure cliente;
  if (strlen(ROOT_CA) > 0) cliente.setCACert(ROOT_CA); else cliente.setInsecure();
  HTTPClient http;
  http.setTimeout(4000);
  String url = String("https://") + API_HOST + "/api/v1/hardware/ultimo-evento?caseta_id=" + CASETA_ID + "&rssi=" + WiFi.RSSI();
  if (!http.begin(cliente, url)) return;
  http.addHeader("X-Hardware-Token", HARDWARE_TOKEN);
  http.addHeader("Accept", "application/json");
  int codigo = http.GET();
  if (codigo == 200) {
    JsonDocument doc;
    if (!deserializeJson(doc, http.getString())) {
      long id = doc["id"] | 0;
      String tipo = doc["tipo_senal"] | "idle";
      if (ultimoId == -1) {
        ultimoId = id;  // al arrancar no repite un evento viejo
        Serial.printf("[sync] ultimo evento id=%ld\n", id);
      } else if (id != ultimoId) {
        ultimoId = id;
        aplicar(tipo, id, doc["placa"] | "-");
      } else if (tipo == "idle" && senalActual == "inferencia") {
        aplicar("idle", id, nullptr);   // termino la inferencia sin paso registrado
      }
    }
  } else {
    Serial.printf("[http] codigo %d %s\n", codigo, codigo == 401 ? "(HARDWARE_TOKEN incorrecto)" : "");
  }
  http.end();
}

void setup() {
  Serial.begin(115200);
  pinMode(PIN_AZUL, OUTPUT); pinMode(PIN_VERDE, OUTPUT); pinMode(PIN_ROJO, OUTPUT); pinMode(PIN_BUZZER, OUTPUT);
  apagarTodo();
  autoPrueba();
  conectarWifi();
}

unsigned long proxima = 0;
void loop() {
  if (WiFi.status() != WL_CONNECTED) { conectarWifi(); }
  if (millis() >= proxima) {
    proxima = millis() + INTERVALO_MS;
    consultar();
  }
  animar();
  delay(20);
}
