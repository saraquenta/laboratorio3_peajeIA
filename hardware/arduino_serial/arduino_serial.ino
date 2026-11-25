/*
 * Variante para Arduino UNO/Nano (sin WiFi): recibe una letra por el puerto serie desde
 * hardware/puente_serial.py, que consulta la API publica del peaje.
 *
 *   'G' -> LED verde 3 s      'R' -> LED rojo 3 s + buzzer      'T' -> auto-prueba
 *
 * Cableado: D8 -> 220 ohm -> LED verde -> GND ; D9 -> 220 ohm -> LED rojo -> GND ; D10 -> buzzer activo -> GND
 */
const int PIN_VERDE = 8;
const int PIN_ROJO = 9;
const int PIN_BUZZER = 10;
unsigned long apagarEn = 0;

void apagar() { digitalWrite(PIN_VERDE, LOW); digitalWrite(PIN_ROJO, LOW); digitalWrite(PIN_BUZZER, LOW); }

void pitar(int veces) {
  for (int i = 0; i < veces; i++) { digitalWrite(PIN_BUZZER, HIGH); delay(150); digitalWrite(PIN_BUZZER, LOW); delay(100); }
}

void setup() {
  Serial.begin(9600);
  pinMode(PIN_VERDE, OUTPUT); pinMode(PIN_ROJO, OUTPUT); pinMode(PIN_BUZZER, OUTPUT);
  apagar();
  Serial.println("LISTO");
}

void loop() {
  if (apagarEn && millis() > apagarEn) { apagar(); apagarEn = 0; }
  if (Serial.available()) {
    char c = Serial.read();
    if (c == 'G') { apagar(); digitalWrite(PIN_VERDE, HIGH); apagarEn = millis() + 3000; Serial.println("OK G"); }
    else if (c == 'R') { apagar(); digitalWrite(PIN_ROJO, HIGH); pitar(3); apagarEn = millis() + 3000; Serial.println("OK R"); }
    else if (c == 'T') { digitalWrite(PIN_VERDE, HIGH); delay(400); apagar(); digitalWrite(PIN_ROJO, HIGH); delay(400); apagar(); pitar(1); Serial.println("OK T"); }
  }
}
