# /hardware — alertas físicas de la caseta (ESP32 + LEDs + buzzer)

El ESP32 se conecta por **Wi-Fi** y consulta por **HTTPS** el backend **en la nube** cada segundo. No usa la PC
ni abre puertos: funciona con el hotspot del celular.

```
App móvil ──frame──► Web (subdominio HTTPS) ──► RunPod (inferencia)
                         │
                         └── GET /api/v1/hardware/ultimo-evento  ◄── ESP32 (cada 1 s, X-Hardware-Token)
```

| `tipo_senal` | Cuándo | ESP32 |
|---|---|---|
| `inferencia` | la app o el panel enviaron un frame | LED **azul** parpadea |
| `ok` | paso cobrado (placa registrada, activa y con saldo) | LED **verde** 3 s + 1 beep |
| `alerta` | no registrada (evasión), saldo insuficiente, inactivo o placa ilegible | LED **rojo** parpadeo + 3 beeps |
| `idle` | sin actividad | apagado |

Respuesta del endpoint:
```json
{ "id": 57, "tipo_senal": "alerta", "caseta_id": 1, "placa": "5751PDE", "monto": 0,
  "texto": "ALERTA: no_registrada", "timestamp": "2026-11-25T15:40:12-04:00" }
```

## ESP32 — `esp32_alerta_peaje/`

1. Arma el circuito: [`DIAGRAMA_CONEXION.md`](esp32_alerta_peaje/DIAGRAMA_CONEXION.md) (azul GPIO 25, verde 26, rojo 27, buzzer 14).
2. Arduino IDE 2.x → *Preferencias* → URL de placas `https://espressif.github.io/arduino-esp32/package_esp32_index.json`
   → *Gestor de placas* → **esp32 (Espressif)** → placa **ESP32 Dev Module**.
3. *Gestor de librerías* → **ArduinoJson** 7.x.
4. Copia `secrets.h.example` como **`secrets.h`** (está en `.gitignore`) y completa `WIFI_SSID`, `WIFI_PASSWORD`,
   `API_HOST` (dominio sin `https://`), `HARDWARE_TOKEN` (igual al `.env` de la web) y `CASETA_ID`.
5. **Subir**. Monitor serie 115200: `[wifi] OK` → `[sync] ultimo evento id=…` → `[evento] …` en cada paso.
6. En el panel, **Estado** muestra *ESP32: Conectado* con su RSSI.

## Sin hardware: simulador de consola

```powershell
python hardware/semaforo.py --demo
python hardware/semaforo.py --url https://peaje.tu-dominio.com --token TU_HARDWARE_TOKEN --caseta 1
```

## Arduino UNO/Nano (sin Wi-Fi) — `arduino_serial/`

Alternativa si no tienes ESP32: el Arduino recibe `G`/`R` por USB desde `semaforo.py --serial COM3`
(D8 verde, D9 rojo, D10 buzzer). En este caso sí necesita una PC conectada, por eso la opción recomendada es el ESP32.

## Seguridad

- `HARDWARE_TOKEN` es un token de **dispositivo** de solo lectura. Genera uno largo: `php -r "echo bin2hex(random_bytes(24));"`.
- `secrets.h` nunca se sube a Git. Sin `ROOT_CA` el ESP32 no valida el certificado (aceptable para la demo).
