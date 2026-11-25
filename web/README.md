# /web — Panel, API REST, base de datos y agente (Laravel 13)

Aplicación web del Sistema Inteligente de Control de Peaje. Pensada para **hosting compartido o VPS**:
vistas Blade + CSS/JS planos (sin build de Node), MySQL, tiempo real con Pusher o sondeo.

| Página | Ruta | Rol |
|---|---|---|
| Panel en tiempo real (pasos, recaudación, alertas, pasajeros) | `/panel` | ambos |
| Vehículos (CRUD: placa, propietario, categoría, ejes, saldo, activo) | `/vehiculos` | lectura: ambos · escritura: admin |
| Pasos (historial con imagen de evidencia) | `/pasos` | ambos |
| Alertas (listado y gestión) | `/alertas` | ambos |
| Agente (página + burbuja flotante en todas las páginas) | `/chat` | ambos |
| Estado (¿responde `INFERENCE_URL`?) | `/estado` | ambos (configurar: admin) |
| Tarifas · Casetas · Lista blanca | `/admin/*` | admin |

API REST v1 documentada en [`../docs/openapi.yaml`](../docs/openapi.yaml).

## Ejecutar en local (solo desarrollo)

```powershell
cd web
composer install
copy .env.example .env      # luego APP_ENV=local, AUTH_DEV_LOGIN=true para entrar sin Google
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

Con `APP_ENV=local` y `AUTH_DEV_LOGIN=true` la pantalla de login muestra un acceso de pruebas con un correo de la
lista blanca (p. ej. `doc.alvaro.pinto.ma@unifranz.edu.bo`). **En producción no existe**: el login es solo con Google.

Para probar sin GPU puedes levantar la inferencia en modo simulado (`INFERENCE_MODE=mock`, ver `../inferencia/README.md`)
y poner `INFERENCE_URL=http://127.0.0.1:8000` **solo en tu `.env` local**.

## Pruebas

```powershell
php artisan test
```

47 pruebas: lista blanca y docente administrador, protección de todas las rutas API salvo `/auth/google`,
cobro/alertas/duplicados/ejes adicionales en `/pasos/frame`, captura manual (placa ilegible), evidencia anotada,
los 4 estados del agente y "¿Quisiste decir…?", Gemini con respaldo determinista, ESP32 (`ultimo-evento`),
exportación de la BD, CRUD de vehículos y roles.

## Comandos del proyecto

```bash
php artisan peaje:exportar-bd [--dir=ruta]   # BD a .sql + .json (carpeta de recursos / Drive)
php artisan peaje:sembrar-si-vacio           # seeders solo si la BD está vacía (despliegues automáticos)
```

Despliegue con Docker (Render u otro): `web/Dockerfile` + `web/docker-entrypoint.sh` + `render.yaml` en la raíz.

## Estructura

```
app/Services/
  PlacaService.php          normalización boliviana + variantes OCR + sugerencias
  InferenciaService.php     cliente de INFERENCE_URL (/health, /predict)
  PasoService.php           frame → inferencia → tarifa → cobro/alerta → evidencia → tiempo real → hardware
  ConsultaPlacaService.php  los 4 estados
  AgenteService.php         intención + datos de la BD (+ redacción opcional con Claude)
  GoogleAuthService.php     verificación de id_token + lista blanca
  RealtimeService.php       Pusher (opcional)
  HardwareService.php       señal LED/buzzer y webhook opcional
database/seeders/DatabaseSeeder.php   docente admin, 3 casetas, 5 tarifas, 24 vehículos, pasos y alertas de ejemplo
```

Despliegue en subdominio HTTPS: [`../docs/despliegue.md`](../docs/despliegue.md).
