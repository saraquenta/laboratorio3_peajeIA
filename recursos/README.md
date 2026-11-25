# /recursos — base de datos exportable

| Archivo | Contenido |
|---|---|
| `base_de_datos/peaje_bd_semilla.sql` | INSERTs portables (MySQL / PostgreSQL / SQLite) de la semilla: docente administrador, 3 casetas, 5 tarifas, 42 vehículos, 29 pasos (19 con las fotos reales de placas bolivianas como evidencia) y alertas |
| `base_de_datos/peaje_bd_semilla.json` | Los mismos datos en JSON |

Se generan con `php artisan peaje:exportar-bd --dir=../recursos/base_de_datos` (desde `web/`).
**Después de grabar el video** vuelve a exportar en el servidor (`php artisan peaje:exportar-bd`) para incluir los pasos
de la demo y sube los dos archivos a la carpeta de Google Drive (`/base_de_datos`). Los tokens no se exportan.
Las fotos de evidencia están en `web/database/seeders/evidencias/` y se publican en `/storage/evidencias/semilla/`.
