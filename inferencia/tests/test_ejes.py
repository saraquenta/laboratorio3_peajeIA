import numpy as np

from app.ejes import contar_ejes


def llanta(cx, cy, r):
    """Llanta circular vista de lado: 5 keypoints [centro, contacto, superior, izquierda, derecha]."""
    kps = np.array([[cx, cy, 1], [cx, cy + r, 1], [cx, cy - r, 1], [cx - r, cy, 1], [cx + r, cy, 1]], float)
    return {"conf": 0.9, "box": [cx - r, cy - r, cx + r, cy + r], "kps": kps}


def test_auto_dos_ejes():
    vehiculo = [0, 0, 400, 160]
    ejes, vista, _ = contar_ejes(vehiculo, [llanta(70, 130, 30), llanta(330, 130, 30)])
    assert ejes == 2 and vista == "lateral"


def test_camion_tres_ejes_con_rueda_doble():
    vehiculo = [0, 0, 900, 300]
    ruedas = [llanta(100, 250, 45), llanta(600, 250, 45), llanta(640, 250, 45),  # 600/640: rueda doble
              llanta(780, 250, 45)]
    ejes, _, _ = contar_ejes(vehiculo, ruedas)
    assert ejes == 3


def test_sin_llantas():
    ejes, _, nota = contar_ejes([0, 0, 100, 100], [])
    assert ejes is None and "Sin llantas" in nota
