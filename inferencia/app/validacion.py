"""Contrato de los pesos propios (.pt) que produce /entrenamiento y que carga el servidor.

| Archivo              | task      | Clases                                            | Extra                                   |
|----------------------|-----------|---------------------------------------------------|-----------------------------------------|
| pesos/placas_yolo.pt | detect    | 1 clase (cualquier nombre: placa, license-plate)  | OCR posterior con EasyOCR               |
| pesos/vehiculos_seg.pt | segment | auto, bus, camion, minibus, moto (por NOMBRE)     | el orden de los indices no importa      |
| pesos/llantas_pose.pt  | pose    | 1 clase (llanta)                                  | kpt_shape [5, 3]: centro, contacto,     |
|                      |           |                                                   | superior, izquierda, derecha            |

Este modulo no importa torch ni ultralytics: recibe el modelo ya cargado (o cualquier objeto con
``task``, ``names`` y ``kpt_shape``) para poder probarlo en cualquier PC.
"""
from __future__ import annotations

CATEGORIAS = ["auto", "bus", "camion", "minibus", "moto"]  # orden alfabetico = orden de exportacion de Roboflow
KPT_NOMBRES = ["centro", "contacto", "superior", "izquierda", "derecha"]
KPT_SHAPE = [5, 3]
FLIP_IDX = [0, 1, 2, 4, 3]

# Variantes de nombres que suelen aparecer en Roboflow / datasets publicos -> clase interna
_ALIAS = {
    "auto": "auto", "car": "auto", "automovil": "auto", "automóvil": "auto", "auto particular": "auto",
    "vagoneta": "auto", "camioneta": "auto", "sedan": "auto", "suv": "auto", "pickup": "auto",
    "bus": "bus", "autobus": "bus", "autobús": "bus", "flota": "bus",
    "camion": "camion", "camión": "camion", "truck": "camion", "trailer": "camion",
    "minibus": "minibus", "minibús": "minibus", "van": "minibus", "micro": "minibus", "microbus": "minibus",
    "trufi": "minibus",
    "moto": "moto", "motocicleta": "moto", "motorcycle": "moto", "motorbike": "moto",
}


def normalizar_clase(nombre) -> str | None:
    """'Camión ', 'Auto_Particular', 'MOTO' -> 'camion', 'auto', 'moto'. None si no es una categoria del peaje."""
    n = str(nombre).strip().lower().replace("_", " ").replace("-", " ")
    return _ALIAS.get(n)


def _nombres(modelo) -> list[str]:
    names = getattr(modelo, "names", None) or {}
    return [str(names[k]) for k in sorted(names)] if isinstance(names, dict) else [str(n) for n in names]


def validar_vehiculos(modelo) -> dict:
    """M2: debe ser segmentacion y TODAS sus clases deben mapear a una de las 5 categorias."""
    nombres = _nombres(modelo)
    if getattr(modelo, "task", None) != "segment":
        raise ValueError(f"vehiculos_seg.pt debe ser un modelo de SEGMENTACION (YOLO11-seg); es task={modelo.task!r}")
    sin_mapa = [n for n in nombres if normalizar_clase(n) is None]
    if sin_mapa:
        raise ValueError(f"vehiculos_seg.pt tiene clases que no corresponden a {CATEGORIAS}: {sin_mapa}. "
                         "Renombralas en Roboflow o agrega el alias en app/validacion.py")
    mapeo = {n: normalizar_clase(n) for n in nombres}
    faltan = sorted(set(CATEGORIAS) - set(mapeo.values()))
    return {"clases": mapeo, "advertencia": f"no detecta: {faltan}" if faltan else None}


def validar_llantas(modelo) -> dict:
    """M3: debe ser pose con exactamente 5 keypoints (x, y, visibilidad)."""
    if getattr(modelo, "task", None) != "pose":
        raise ValueError(f"llantas_pose.pt debe ser un modelo de POSE (YOLO-pose); es task={modelo.task!r}")
    kpt = list(getattr(modelo, "kpt_shape", None) or [])
    if kpt != KPT_SHAPE:
        raise ValueError(f"llantas_pose.pt tiene kpt_shape={kpt}; se espera {KPT_SHAPE} "
                         f"(orden {KPT_NOMBRES})")
    return {"clases": _nombres(modelo), "kpt_shape": kpt, "orden_esperado": KPT_NOMBRES}


def validar_placas(modelo) -> dict:
    """M1: detector de cajas. Todas sus clases se tratan como 'placa'."""
    if getattr(modelo, "task", None) != "detect":
        raise ValueError(f"placas_yolo.pt debe ser un modelo de DETECCION (YOLO11); es task={modelo.task!r}")
    nombres = _nombres(modelo)
    return {"clases": nombres, "advertencia": "mas de una clase: todas se tratan como placa" if len(nombres) > 1 else None}


# Nombres que se cuentan como pasajero en pasajeros_seg.pt (M5)
_ALIAS_PERSONA = {"person", "persona", "personas", "people", "pasajero", "pasajeros", "passenger", "passengers",
                  "conductor", "driver", "rider", "motorbike rider", "motorcyclist", "pedestrian", "human", "occupant",
                  "ocupante"}


def es_persona(nombre) -> bool:
    n = str(nombre).strip().lower().replace("_", " ").replace("-", " ")
    return n in _ALIAS_PERSONA


def validar_pasajeros(modelo) -> dict:
    """M5: segmentacion de instancias con al menos una clase de persona. Devuelve los indices a contar."""
    if getattr(modelo, "task", None) != "segment":
        raise ValueError(f"pasajeros_seg.pt debe ser un modelo de SEGMENTACION (YOLO11-seg); es task={modelo.task!r}")
    names = getattr(modelo, "names", None) or {}
    pares = sorted(names.items()) if isinstance(names, dict) else list(enumerate(names))
    indices = [int(i) for i, n in pares if es_persona(n)]
    if not indices:
        raise ValueError(f"pasajeros_seg.pt no tiene ninguna clase de persona {sorted(_ALIAS_PERSONA)[:6]}...; "
                         f"clases: {[n for _, n in pares]}")
    return {"indices_persona": indices, "clases_persona": [str(names[i]) for i in indices],
            "otras_clases": [str(n) for i, n in pares if int(i) not in indices]}


def kpt_shape_de(modelo):
    """kpt_shape de un YOLO de ultralytics cargado (vive en model.model.yaml o en model.model.kpt_shape)."""
    m = getattr(modelo, "model", None)
    for fuente in (getattr(m, "kpt_shape", None), (getattr(m, "yaml", None) or {}).get("kpt_shape")):
        if fuente is not None:
            return list(fuente)
    return None
