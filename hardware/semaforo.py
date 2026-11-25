"""Cliente ligero del semaforo de caseta (LED verde / LED rojo / buzzer).

Modos:
  --demo                      simulacion en consola sin servidor (ciclo verde/rojo)
  --url ... --token ...       consulta la API publica cada segundo y simula los LEDs en consola
  --url ... --token ... --serial COM3   ademas envia 'G'/'R' a un Arduino (arduino_serial.ino)

Ejemplos:
  python semaforo.py --demo
  python semaforo.py --url https://peaje.tu-dominio.com --token <HARDWARE_TOKEN> --caseta 1
  python semaforo.py --url https://peaje.tu-dominio.com --token <HARDWARE_TOKEN> --serial COM3

Solo usa la biblioteca estandar (urllib); pyserial es opcional para el modo --serial.
"""
import argparse
import json
import sys
import time
import urllib.error
import urllib.request

VERDE, ROJO, AZUL, GRIS, RESET = ("\033[42m\033[30m", "\033[41m\033[97m", "\033[44m\033[97m", "\033[100m\033[97m",
                                  "\033[0m")


def mostrar(led: str, texto: str, buzzer: bool = False):
    color = {"verde": VERDE, "rojo": ROJO, "azul": AZUL}.get(led, GRIS)
    simbolo = {"verde": "● VERDE", "rojo": "● ROJO ", "azul": "◌ AZUL "}.get(led, "○ ---  ")
    pitido = "  🔊 BEEP-BEEP-BEEP" if buzzer else ""
    print(f"{time.strftime('%H:%M:%S')}  {color} {simbolo} {RESET}  {texto}{pitido}", flush=True)
    if buzzer:
        sys.stdout.write("\a")  # campana de la terminal


def consultar(url: str, token: str, caseta: int) -> dict:
    req = urllib.request.Request(f"{url.rstrip('/')}/api/v1/hardware/ultimo-evento?caseta_id={caseta}",
                                 headers={"X-Hardware-Token": token, "Accept": "application/json"})
    with urllib.request.urlopen(req, timeout=5) as r:
        return json.loads(r.read().decode("utf-8"))


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--url", help="URL publica de la web (subdominio HTTPS)")
    p.add_argument("--token", help="HARDWARE_TOKEN del .env de la web")
    p.add_argument("--caseta", type=int, default=1)
    p.add_argument("--intervalo", type=float, default=1.0)
    p.add_argument("--serial", help="puerto del Arduino (COM3, /dev/ttyUSB0)")
    p.add_argument("--demo", action="store_true", help="simulacion sin servidor")
    a = p.parse_args()

    puerto = None
    if a.serial:
        import serial  # pip install pyserial

        puerto = serial.Serial(a.serial, 9600, timeout=1)
        time.sleep(2)  # el Arduino se reinicia al abrir el puerto
        puerto.write(b"T")

    def enviar(led, texto, buzzer):
        mostrar(led, texto, buzzer)
        if puerto:
            puerto.write(b"G" if led == "verde" else b"R")

    if a.demo:
        eventos = [("verde", "1234ABC · Paso autorizado · Bs 5.00", False),
                   ("rojo", "9999ZZZ · ALERTA: no_registrada", True),
                   ("verde", "3456CDF · Paso autorizado · Bs 30.00", False),
                   ("rojo", "852KLM · ALERTA: saldo_insuficiente", True)]
        print("Modo demo (Ctrl+C para salir)")
        try:
            while True:
                for e in eventos:
                    enviar(*e)
                    time.sleep(3)
        except KeyboardInterrupt:
            return

    if not a.url or not a.token:
        p.error("usa --demo o indica --url y --token")
    print(f"Consultando {a.url} (caseta {a.caseta}) cada {a.intervalo}s. Ctrl+C para salir.")
    ultimo = None
    while True:
        try:
            s = consultar(a.url, a.token, a.caseta)
            if ultimo is None:
                ultimo = s.get("id")
                mostrar("apagado", f"Conectado. Último evento #{ultimo}: {s.get('texto')}")
            elif s.get("id") != ultimo:
                ultimo = s.get("id")
                tipo = s.get("tipo_senal")
                if tipo == "inferencia":
                    mostrar("azul", "Inferencia en curso…")
                elif tipo in ("ok", "alerta"):
                    led = "verde" if tipo == "ok" else "rojo"
                    enviar(led, f"{s.get('placa') or '-'} · {s.get('texto')} · Bs {float(s.get('monto') or 0):.2f}", tipo == "alerta")
        except urllib.error.HTTPError as e:
            mostrar("rojo", f"HTTP {e.code}: {'token de hardware inválido' if e.code == 401 else e.reason}")
            time.sleep(5)
        except Exception as e:  # noqa: BLE001
            mostrar("apagado", f"Sin conexión: {e}")
            time.sleep(3)
        except KeyboardInterrupt:
            break
        time.sleep(a.intervalo)


if __name__ == "__main__":
    main()
