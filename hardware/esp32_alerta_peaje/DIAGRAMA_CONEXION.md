# Diagrama de conexión — ESP32 DevKit (38 pines)

| Componente | Pin ESP32 | Conexión |
|---|---|---|
| LED **azul** (inferencia en curso) | **GPIO 25** | GPIO 25 → resistencia 220 Ω → ánodo (pata larga) → cátodo → GND |
| LED **verde** (paso autorizado) | **GPIO 26** | GPIO 26 → 220 Ω → LED → GND |
| LED **rojo** (alerta) | **GPIO 27** | GPIO 27 → 220 Ω → LED → GND |
| **Buzzer** activo 3.3–5 V | **GPIO 14** | GPIO 14 → (+) buzzer, (−) → GND |
| Alimentación | USB | cable USB al celular/cargador (no necesita la PC) |

```
                 ESP32 DevKit
             ┌──────────────────┐
             │           GPIO25 ├──[220Ω]──►|── LED AZUL ──┐
             │           GPIO26 ├──[220Ω]──►|── LED VERDE ─┤
             │           GPIO27 ├──[220Ω]──►|── LED ROJO ──┤
             │           GPIO14 ├────────────(+) BUZZER (−)┤
             │              GND ├──────────────────────────┘  (riel GND de la protoboard)
             └───────┬──────────┘
                     │ USB 5 V (cargador o power bank)
```

## Comportamiento

| `tipo_senal` del backend | LEDs | Buzzer |
|---|---|---|
| `inferencia` (la app envió un frame) | azul parpadea | — |
| `ok` (paso cobrado) | verde fijo 3 s | 1 beep |
| `alerta` (no registrada / sin saldo / placa ilegible / inactivo) | rojo parpadeo rápido 3 s | 3 beeps |
| `idle` | apagados | — |
| conectando al Wi-Fi | rojo parpadea lento | — |

Materiales: ESP32 DevKit V1 (38 pines), 3 LEDs de 5 mm (azul, verde, rojo), 3 resistencias de 220 Ω, 1 buzzer activo,
protoboard y cables dupont macho-macho.
