"""Normalizacion y validacion de placas de Bolivia.

Formato vigente (RUAT, desde 2004): 3 o 4 digitos seguidos de 3 letras, p. ej. ``1234ABC`` o ``852KLM``.
Formato antiguo (todavia visible en algunos vehiculos): 2-3 letras seguidas de 3-4 digitos, p. ej. ``LPZ123``.

El OCR suele confundir caracteres parecidos. Como en el formato vigente la posicion define si se espera
un digito o una letra, se corrige por posicion: ``O->0, I->1, B->8, S->5, Z->2, G->6`` en la parte numerica
y la conversion inversa en la parte de letras.

Este modulo no depende de ninguna libreria externa para poder probarlo en cualquier PC.
"""
from __future__ import annotations

import re
from dataclasses import dataclass

# letra -> digito (la letra se leyo donde deberia haber un digito)
LETRA_A_DIGITO = {"O": "0", "Q": "0", "D": "0", "I": "1", "L": "1", "T": "1", "Z": "2",
                  "B": "8", "S": "5", "G": "6", "A": "4"}
# digito -> letra (el digito se leyo donde deberia haber una letra)
DIGITO_A_LETRA = {"0": "O", "1": "I", "2": "Z", "8": "B", "3": "B", "5": "S", "6": "G", "4": "A", "7": "T"}
MAX_CORRECCIONES_ANTIGUO = 2  # el formato antiguo solo se acepta con pocas correcciones

RE_VIGENTE = re.compile(r"^\d{3,4}[A-Z]{3}$")
RE_ANTIGUA = re.compile(r"^[A-Z]{2,3}\d{3,4}$")


@dataclass
class ResultadoPlaca:
    original: str
    normalizada: str
    valida: bool
    formato: str          # "vigente" | "antiguo" | "desconocido"
    correcciones: int     # cuantos caracteres se corrigieron por posicion

    def to_dict(self) -> dict:
        return self.__dict__.copy()


def limpiar(texto: str | None) -> str:
    """Mayusculas, sin espacios, guiones, puntos ni simbolos. Elimina el texto 'BOLIVIA' del marco."""
    if not texto:
        return ""
    t = texto.upper().replace("BOLIVIA", "")
    t = t.replace("Ñ", "N")
    return re.sub(r"[^A-Z0-9]", "", t)


def _corregir(t: str, n_digitos: int, digitos_primero: bool = True) -> tuple[str, int]:
    """Fuerza digitos en el bloque numerico y letras en el bloque alfabetico."""
    out, cambios = [], 0
    for i, ch in enumerate(t):
        en_bloque_num = (i < n_digitos) if digitos_primero else (i >= len(t) - n_digitos)
        if en_bloque_num and not ch.isdigit():
            nuevo = LETRA_A_DIGITO.get(ch, ch)
        elif not en_bloque_num and ch.isdigit():
            nuevo = DIGITO_A_LETRA.get(ch, ch)
        else:
            nuevo = ch
        cambios += nuevo != ch
        out.append(nuevo)
    return "".join(out), cambios


def normalizar_placa(texto: str | None) -> ResultadoPlaca:
    """Devuelve la placa normalizada y si cumple el formato boliviano."""
    t = limpiar(texto)
    if RE_VIGENTE.match(t):
        return ResultadoPlaca(texto or "", t, True, "vigente", 0)
    if RE_ANTIGUA.match(t):
        return ResultadoPlaca(texto or "", t, True, "antiguo", 0)

    candidatos = []
    # Formato vigente: 6 caracteres (3+3) o 7 (4+3)
    if len(t) in (6, 7):
        c, n = _corregir(t, len(t) - 3, digitos_primero=True)
        if RE_VIGENTE.match(c):
            candidatos.append((n, c, "vigente"))
    # Formato antiguo: 2-3 letras + 3-4 digitos
    for n_letras in (3, 2):
        n_dig = len(t) - n_letras
        if 3 <= n_dig <= 4:
            c, n = _corregir(t, n_dig, digitos_primero=False)
            if RE_ANTIGUA.match(c) and n <= MAX_CORRECCIONES_ANTIGUO:
                candidatos.append((n + 1, c, "antiguo"))   # penaliza levemente el formato antiguo
    # Si el OCR agrego un caracter basura al inicio o al final, se prueba recortando
    if not candidatos and len(t) == 8:
        for recorte in (t[1:], t[:-1]):
            r = normalizar_placa(recorte)
            if r.valida:
                return ResultadoPlaca(texto or "", r.normalizada, True, r.formato, r.correcciones + 1)

    if candidatos:
        n, c, formato = min(candidatos)
        return ResultadoPlaca(texto or "", c, True, formato, n)
    return ResultadoPlaca(texto or "", t, False, "desconocido", 0)


def variantes_ocr(placa: str, max_variantes: int = 64) -> list[str]:
    """Genera variantes intercambiando caracteres que el OCR confunde (O<->0, I<->1, B<->8, S<->5...).

    Se usa para sugerir "¿Quisiste decir...?" cuando la placa leida no existe en la base de datos.
    """
    pares = {"O": "0", "0": "O", "I": "1", "1": "I", "B": "8", "8": "B", "S": "5", "5": "S",
             "Z": "2", "2": "Z", "G": "6", "6": "G", "D": "0", "Q": "0"}
    t = limpiar(placa)
    variantes = {t}
    for i, ch in enumerate(t):
        if ch in pares:
            for v in list(variantes):
                variantes.add(v[:i] + pares[ch] + v[i + 1:])
                if len(variantes) >= max_variantes:
                    break
    variantes.discard(t)
    return sorted(variantes)
