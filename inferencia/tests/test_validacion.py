from types import SimpleNamespace as M

import pytest

from app.validacion import normalizar_clase, validar_llantas, validar_placas, validar_vehiculos


def test_m2_roboflow_alfabetico_y_variantes():
    # Orden de exportacion de Roboflow (alfabetico) con nombres "sucios"
    m = M(task="segment", names={0: "Auto", 1: "Bus", 2: "Camión ", 3: "Minibús", 4: "moto"}, kpt_shape=None)
    r = validar_vehiculos(m)
    assert set(r["clases"].values()) == {"auto", "bus", "camion", "minibus", "moto"}
    assert r["advertencia"] is None


def test_m2_clase_desconocida_falla_con_mensaje():
    with pytest.raises(ValueError, match="no corresponden"):
        validar_vehiculos(M(task="segment", names={0: "auto", 1: "bicicleta"}, kpt_shape=None))


def test_m2_debe_ser_segmentacion():
    with pytest.raises(ValueError, match="SEGMENTACION"):
        validar_vehiculos(M(task="detect", names={0: "auto"}, kpt_shape=None))


def test_m3_kpt_shape():
    assert validar_llantas(M(task="pose", names={0: "llanta"}, kpt_shape=[5, 3]))["kpt_shape"] == [5, 3]
    with pytest.raises(ValueError, match="kpt_shape"):
        validar_llantas(M(task="pose", names={0: "person"}, kpt_shape=[17, 3]))


def test_m1_detect():
    assert validar_placas(M(task="detect", names={0: "license-plate"}, kpt_shape=None))["advertencia"] is None
    with pytest.raises(ValueError):
        validar_placas(M(task="segment", names={0: "placa"}, kpt_shape=None))


@pytest.mark.parametrize("n,esperado", [("Auto_Particular", "auto"), ("TRUFI", "minibus"), ("camion", "camion"),
                                        ("persona", None)])
def test_normalizar_clase(n, esperado):
    assert normalizar_clase(n) == esperado
