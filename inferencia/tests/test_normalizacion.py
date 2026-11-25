import pytest

from app.normalizacion import normalizar_placa, variantes_ocr


@pytest.mark.parametrize("entrada,esperada", [
    ("1234-ABC", "1234ABC"),
    ("1234 abc", "1234ABC"),
    ("852-KLM", "852KLM"),
    ("BOLIVIA 2345 BCD", "2345BCD"),
    ("I234ABC", "1234ABC"),     # I -> 1 en la parte numerica
    ("12O4ABC", "1204ABC"),     # O -> 0
    ("1234A8C", "1234ABC"),     # 8 -> B en la parte de letras
    ("S678XYZ", "5678XYZ"),     # S -> 5
    ("1234AB5", "1234ABS"),     # 5 -> S
    ("LPZ-123", "LPZ123"),      # formato antiguo
])
def test_normaliza_y_corrige(entrada, esperada):
    r = normalizar_placa(entrada)
    assert r.valida
    assert r.normalizada == esperada


@pytest.mark.parametrize("entrada", ["", None, "HOLA", "12", "ABCDEFGHIJ"])
def test_invalidas(entrada):
    assert not normalizar_placa(entrada).valida


def test_variantes_incluyen_confusiones():
    v = variantes_ocr("1234AB0")
    assert "1234ABO" in v
    assert "I234AB0" in v
