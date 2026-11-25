"""M5: contrato de pasajeros_seg.pt y conteo dentro del vehiculo (sin GPU ni ultralytics)."""
from types import SimpleNamespace as M

import numpy as np
import pytest

from app.modelos import Pipeline
from app.validacion import es_persona, validar_pasajeros


def test_contrato_pasajeros_indices_de_persona():
    r = validar_pasajeros(M(task="segment", names={0: "persona", 1: "auto", 2: "bus"}, kpt_shape=None))
    assert r["indices_persona"] == [0] and r["otras_clases"] == ["auto", "bus"]
    r = validar_pasajeros(M(task="segment", names={0: "car", 1: "Person", 2: "driver"}, kpt_shape=None))
    assert r["indices_persona"] == [1, 2]


def test_contrato_pasajeros_rechaza():
    with pytest.raises(ValueError, match="persona"):
        validar_pasajeros(M(task="segment", names={0: "car"}, kpt_shape=None))
    with pytest.raises(ValueError, match="SEGMENTACION"):
        validar_pasajeros(M(task="detect", names={0: "person"}, kpt_shape=None))


def test_es_persona():
    assert es_persona("Pasajero") and es_persona("motorbike-rider") and not es_persona("auto")


class _Arr:
    def __init__(self, a):
        self.a = np.array(a, dtype=float)

    def cpu(self):
        return self

    def numpy(self):
        return self.a


class _ModeloFalso:
    """Imita la salida de ultralytics y registra con que clases/imgsz se llamo."""

    def __init__(self, cajas):
        self.cajas, self.llamada = cajas, None

    def predict(self, img, **kw):
        self.llamada = kw
        return [M(boxes=_Cajas(self.cajas))]


class _Cajas:
    def __init__(self, c):
        self.xyxy = _Arr(c if c else np.zeros((0, 4)))
        self._n = len(c)

    def __len__(self):
        return self._n


def test_cuenta_solo_personas_dentro_del_vehiculo_con_modelo_propio():
    p = Pipeline()
    p._personas = _ModeloFalso([[110, 110, 150, 180], [120, 100, 170, 190], [500, 100, 560, 300]])  # 2 dentro, 1 fuera
    p._idx_personas, p.pasajeros_fuente = [3], "propio"
    n, cajas = p._pasajeros(np.zeros((400, 640, 3), np.uint8), {"clase": "auto", "box": [100, 90, 400, 200]})
    assert n == 2 and len(cajas) == 2
    assert p._personas.llamada["classes"] == [3]   # usa los indices de persona del modelo propio
    assert p._personas.llamada["imgsz"] == 640


def test_sin_vehiculo_no_cuenta():
    p = Pipeline()
    p._personas = _ModeloFalso([[0, 0, 10, 10]])
    assert p._pasajeros(np.zeros((10, 10, 3), np.uint8), None) == (0, [])
