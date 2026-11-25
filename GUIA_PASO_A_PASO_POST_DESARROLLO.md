# 🧭 Guía paso a paso post-desarrollo

Manual en **orden cronológico** de todo lo que falta hacer, desde el código terminado hasta grabar el video.
El código ya está listo y probado: web **47 pruebas ✔**, inferencia **41 pruebas ✔**, Flutter `analyze` sin errores y
`flutter build apk` compila. Tus 3 modelos entrenados (`placas_yolo.pt`, `vehiculos_seg.pt`, `llantas_pose.pt`) ya
**pasan la validación** del servidor.

> **Regla de oro (lo dijo el docente):** en la demo **nada** puede depender de tu PC: ni `localhost`, ni `127.0.0.1`,
> ni IPs privadas (`192.168.*`, `10.*`), ni túneles a tu computadora. Todo vive en GitHub, Colab, RunPod, tu dominio
> HTTPS, Pusher y el ESP32. El docente probará desde su celular. **Tu PC puede estar apagada durante la defensa.**

| # | Paso | Resultado al final | Tiempo |
|---|---|---|---|
| 0 | Publicar el repositorio | `https://github.com/saraquenta/laboratorio3_peajeIA` público | 10 min |
| 1 | Métricas y (opcional) modelo de pasajeros en Colab | capturas de métricas + `pasajeros_seg.pt` (opcional) | 30–60 min |
| 2 | RunPod: servidor de inferencia + 3 entornos | `https://<POD_ID>-8000.proxy.runpod.net/health` → `ok` + evidencias | 40–60 min |
| 3 | Google Cloud (OAuth) | Web Client ID/Secret + cliente Android | 20 min |
| 4 | Pusher + Gemini | claves de tiempo real y del agente | 10 min |
| 5 | Web en dominio HTTPS | panel en `https://...` con datos sembrados | 30–60 min |
| 6 | APK | `app-release.apk` instalado en el celular | 15 min |
| 7 | ESP32 | LEDs y buzzer reaccionando a los pasos | 30 min |
| 8 | Pruebas de extremo a extremo | checklist completo | 30 min |
| 9 | Carpeta de Drive + exportar BD | carpeta lista para el docente | 20 min |
| 10 | Video (8–12 min) | video subido a Drive | 1–2 h |

**Archivos que ya tienes en tu PC** (`C:\Users\Katerin\pesos_finales\`): `placas_yolo.pt`, `vehiculos_seg.pt`,
`llantas_pose.pt` e `inferencia.zip` (código del servidor + los 3 pesos dentro de `pesos/`).

---

## Paso 0 · Publicar el repositorio en GitHub

El repositorio local ya tiene el commit hecho con tu usuario (`saraquenta`). Falta autenticar GitHub **una sola vez**
(GitHub ya no acepta la contraseña para `git push`; se usa el inicio de sesión por navegador):

```powershell
gh auth login --hostname github.com --git-protocol https --web
```
1. Elige *Login with a web browser*, copia el código de 8 caracteres y pégalo en la página que se abre.
2. Inicia sesión como **saraquenta** y autoriza *GitHub CLI*.
3. Luego publica:
   ```powershell
   cd C:\Users\Katerin\laboratorio3
   gh auth setup-git
   git push -u origin main
   ```
4. En <https://github.com/saraquenta/laboratorio3_peajeIA> → *Settings* → verifica que sea **Public**.
5. Comprueba que **no** aparezcan `.env`, `secrets.h`, `*.pt`, `*.jks`, `key.properties` (están en `.gitignore`).

> Si alguna vez subes un secreto por error: **cámbialo** (genera uno nuevo). Borrarlo en un commit nuevo no basta.

---

## Paso 1 · Colab: métricas y modelo de pasajeros (bono)

Tus 3 modelos ya están entrenados. Para la entrega necesitas **capturas de las métricas** y, para el bono de
pasajeros, puedes entrenar el **M5** (si no lo haces, el servidor usa `yolo11s-seg.pt` de COCO automáticamente y el
conteo de pasajeros igual funciona).

### 1.1 Preparar Colab
1. <https://colab.research.google.com> → *Archivo → Abrir cuaderno → GitHub* → pega
   `https://github.com/saraquenta/laboratorio3_peajeIA` → abre el notebook.
2. *Entorno de ejecución → Cambiar tipo de entorno → **T4 GPU*** → Guardar.
3. Icono 🔑 **Secretos** (barra izquierda) → *Agregar secreto* → nombre `ROBOFLOW_API_KEY` → valor: tu clave de
   <https://app.roboflow.com> → *Settings → API Keys → Private API Key* → activa **Acceso del notebook**.
   **Nunca** la pegues dentro del notebook.

### 1.2 Capturas de métricas (de lo que ya entrenaste)
Las métricas de tus pesos (leídas de los `.pt`): M1 mAP50 **0.995**; M2 mAP50 máscara **0.859**; M3 mAP50 pose
**0.923**. Si aún tienes la sesión de Colab de entrenamiento, captura de cada notebook:
* `02_M2_segmentacion_vehiculos` → sección 4 (mAP por clase + **matriz de confusión**).
* `03_M3_keypoints_llantas_ejes` → mAP de keypoints y, si tienes `ejes_reales.csv`, la **exactitud de ejes** (`EVALUAR_EJES = True`).
* `04_M1_placas_ocr` → mAP y, con `placas_reales.csv`, **exactitud OCR y CER** (`EVALUAR_OCR = True`).
  Para el CSV puedes usar las 19 fotos de `web/database/seeders/evidencias/` (el nombre del archivo trae la placa real).

Guárdalas en Drive (`/recursos/metricas/`) y completa `docs/plantilla_informe_tecnico.md`.

### 1.3 (Bono) M5 pasajeros — `05_M5_pasajeros_segmentacion.ipynb`
1. En Roboflow Universe busca un dataset de **Instance Segmentation** con clase `person` y vehículos (sugerido:
   `work-7oioc/vehicle-gtmvy`, versión 2 — **ábrelo y confirma** que existe, que es de segmentación y que tiene personas).
2. En la celda 1 deja `FUENTE = 'roboflow'` y ajusta `WORKSPACE`, `PROYECTO`, `VERSION`.
3. *Ejecutar todo* (~30–45 min). La celda 2 filtra solo personas + vehículos; la 6 valida y exporta
   **`pasajeros_seg.pt`** a tu Drive (`MyDrive/peaje/pesos/`) y lo descarga.
4. Muévelo a `C:\Users\Katerin\pesos_finales\`.

---

## Paso 2 · RunPod: servidor de inferencia (bono RunPod) + entornos (bono entornos)

### 2.1 Crear el Pod
1. <https://www.runpod.io> → *Billing* → carga crédito (USD 10 alcanza; una RTX A4000/A5000 cuesta ~USD 0.2–0.5/h).
2. *Pods → **Deploy*** → GPU **RTX A4000 / A5000 / 3090 / 4090** (16–24 GB).
3. *Template:* **RunPod PyTorch** (2.x, Python 3.11).
4. *Edit Template:*
   * **Container Disk:** 30 GB · **Volume Disk:** 30 GB en `/workspace` (persiste al detener el Pod).
   * **Expose HTTP Ports:** `8888,8000` (8888 = Jupyter, **8000 = la API**).
   * **Environment Variables:** `API_TOKEN` = un texto largo. Genéralo en PowerShell:
     ```powershell
     python -c "import secrets; print(secrets.token_urlsafe(32))"
     ```
     Guárdalo: es el `INFERENCE_TOKEN` de la web.
5. **Deploy On-Demand** → cuando diga *Running*, anota el **POD_ID** (p. ej. `abc123xyz`).
   Tu URL será **`https://abc123xyz-8000.proxy.runpod.net`**.

### 2.2 Subir el código y los pesos
*Connect → **Jupyter Lab***. Dos opciones:

**A) Con el zip (más simple):** arrastra `C:\Users\Katerin\pesos_finales\inferencia.zip` al panel de archivos de
Jupyter (carpeta `/workspace`). Luego *File → New → Terminal*:
```bash
mkdir -p /workspace/peaje && cd /workspace/peaje && unzip -qo /workspace/inferencia.zip
ls inferencia/pesos     # placas_yolo.pt  vehiculos_seg.pt  llantas_pose.pt  README.md
```
**B) Con git:**
```bash
cd /workspace && git clone https://github.com/saraquenta/laboratorio3_peajeIA.git peaje
```
y arrastra los `.pt` desde `pesos_finales` a `/workspace/peaje/inferencia/pesos/` en el panel de Jupyter.

Si entrenaste M5, sube también `pasajeros_seg.pt` a esa misma carpeta.

### 2.3 Los 3 entornos con evidencia (bono de entornos)
En la terminal de Jupyter:
```bash
apt-get update -qq && apt-get install -y -qq tmux
tmux new -s entornos
cd /workspace/peaje/inferencia
bash setup_runpod.sh todos        # 15-25 min: peaje-venv (pip), peaje-conda y peaje-mamba
ls evidencias/
```
Debe haber `evidencia_entorno_runpod-venv.txt`, `-conda.txt`, `-mamba.txt` (+ `.json`). Ábrelos y verifica:
`cuda_disponible : True`, el nombre de la GPU y `M1_placas / M2_vehiculos / M3_llantas -> OK`.
Descárgalos (clic derecho → *Download*) a Drive `/recursos/entornos/` y toma **captura de la terminal**.
(`Ctrl+B` y luego `D` sale de tmux sin cortar el proceso; `tmux attach -t entornos` para volver.)

> Si `cuda_disponible` sale `False`: el driver del host es antiguo para la rueda de torch cu124. Despliega otro Pod
> filtrando por CUDA ≥ 12.4.

### 2.4 Levantar el servidor de producción
```bash
tmux new -s peaje
cd /workspace/peaje/inferencia
export API_TOKEN="<el mismo token del paso 2.1>"
bash setup_runpod.sh servidor venv       # o conda / mamba
```
Imprime `INFERENCE_URL = https://<POD_ID>-8000.proxy.runpod.net`. La primera vez tarda 1–3 min (descarga YOLOE,
EasyOCR y `yolo11s-seg.pt`). Sal con `Ctrl+B`, `D` (sigue corriendo).

**Alternativa sin entornos:** `export API_TOKEN=...; bash start_runpod.sh` (usa el Python de la plantilla).

### 2.5 Verificar desde el celular (con datos móviles, no el Wi-Fi de casa)
`https://<POD_ID>-8000.proxy.runpod.net/health` debe mostrar:
```json
{"estado": "ok", "cuda": true, "modelos": {"M1_placas": "propio:placas_yolo.pt + EasyOCR",
 "M2_vehiculos": "propio:vehiculos_seg.pt", "M3_llantas": "propio:llantas_pose.pt (5 kp)", "M5_pasajeros": "..."}}
```
Prueba de inferencia: importa `docs/postman_collection.json` en Postman → carpeta *Inferencia (RunPod directo)* →
variables `INFERENCE_URL` e `INFERENCE_TOKEN` → `inferir` con una foto de `web/database/seeders/evidencias/`.
Debe devolver la placa, la categoría y `imagen_anotada_base64`.

> **Costos:** *Stop* al Pod cuando no lo uses (el volumen `/workspace` y el **POD_ID se conservan**, la URL no cambia).
> Antes de la defensa, enciéndelo 15 min antes y repite 2.4. El docente pidió que **ya esté levantado**.
> **Respaldo Colab:** `inferencia/colab_servidor.ipynb` (GPU T4) da una URL `https://…trycloudflare.com`; en la web
> solo cambias `INFERENCE_URL` en *Estado* (la URL de Colab cambia en cada sesión).

---

## Paso 3 · Google Cloud Console (OAuth 2.0)

1. <https://console.cloud.google.com> → crea el proyecto **Peaje IA**.
2. *APIs y servicios → **Pantalla de consentimiento de OAuth*** (Google Auth Platform):
   tipo **Externo** · nombre *Peaje IA* · correo de asistencia `saraquenta2@gmail.com` · permisos `openid`, `email`, `profile`.
   * **Publícala (En producción)**, o si la dejas *En prueba* agrega como **usuarios de prueba**:
     `doc.alvaro.pinto.ma@unifranz.edu.bo`, `saraquenta2@gmail.com` y un correo "intruso" para mostrar *Acceso denegado*.
3. *Credenciales → Crear credenciales → ID de cliente OAuth → **Aplicación web***:
   * Orígenes autorizados de JavaScript: `https://TU-DOMINIO` (el del Paso 5)
   * URI de redireccionamiento: `https://TU-DOMINIO/auth/google/callback`
   * Copia el **Client ID** → `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_IDS`, y el **Client secret** → `GOOGLE_CLIENT_SECRET`
     (solo en el `.env`/variables del servidor, nunca en Git).
4. *Crear credenciales → ID de cliente OAuth → **Android***:
   * Nombre del paquete: **`bo.edu.unifranz.peaje_movil`**
   * **SHA-1** del certificado con que firmas el APK. Con la clave de depuración (la que usa `flutter build apk` si no
     configuras otra):
     ```powershell
     keytool -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
     ```
     Copia la línea `SHA1:`. (Si `keytool` no se encuentra: está en `C:\Program Files\Eclipse Adoptium\jdk-17...\bin\`.)
5. La app **no** usa `google-services.json`: usa el **Client ID web** como `GOOGLE_SERVER_CLIENT_ID` al compilar.

---

## Paso 4 · Pusher (tiempo real) y Gemini (agente)

**Pusher Channels** (gratis: 200 mil mensajes/día):
1. <https://dashboard.pusher.com> → *Channels → Create app* → nombre `peaje-ia` → cluster **`sa1`** (São Paulo).
2. *App Keys* → copia `app_id`, `key`, `secret` → `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER=sa1`.
3. Nada más: el panel usa el canal **privado** `private-peaje`, autorizado en `/broadcasting/auth` solo para usuarios
   con sesión. Sin Pusher el panel igual funciona (actualiza cada 3 s).

**Gemini** (gratis con límites):
1. <https://aistudio.google.com/apikey> → *Create API key* → `GEMINI_API_KEY`.
2. `AGENTE_PROVEEDOR=gemini`, `GEMINI_MODEL=gemini-2.5-flash` (si Google retira ese modelo, pon el *flash* vigente).
3. Sin clave o si falla, el agente responde igual en modo determinista (`fuente: plantilla`): **nunca inventa datos**.

---

## Paso 5 · Web en un dominio HTTPS

Genera antes el token del ESP32 (lo pondrás en el `.env` y en `secrets.h`):
```powershell
php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
```

Elige **una** opción. El docente sugirió contratar un hosting de 1 mes con dominio; Render es la alternativa gratuita.

### Opción A · Hosting compartido con cPanel (lo que sugirió el docente)
1. Compra hosting con PHP 8.3 + dominio. *Dominios → Crear subdominio* `peaje.tu-dominio.com` con **raíz del documento**
   `laboratorio3_peajeIA/web/public`.
2. *Seleccionar versión de PHP* → **8.3**; activa `pdo_mysql`, `mbstring`, `curl`, `fileinfo`, `intl`, `zip`, `bcmath`.
   *Opciones*: `upload_max_filesize=10M`, `post_max_size=12M`.
3. *Git™ Version Control → Create* → Clone URL `https://github.com/saraquenta/laboratorio3_peajeIA.git` → carpeta
   `laboratorio3_peajeIA`.
4. *Bases de datos MySQL* → crea BD + usuario con todos los privilegios.
5. *Terminal* de cPanel:
   ```bash
   cd ~/laboratorio3_peajeIA/web
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && nano .env          # valores de la tabla de abajo
   php artisan key:generate
   php artisan migrate --seed --force         # docente admin + tu correo + 42 vehículos + 19 fotos reales
   php artisan storage:link
   php artisan optimize
   ```
   Sin Composer en el hosting: en tu PC `cd web; composer install --no-dev -o`, comprime `vendor\` y súbelo con el
   *Administrador de archivos* (es solo empaquetar: nada queda corriendo en tu PC).
6. *SSL/TLS Status → Run AutoSSL* → HTTPS activo.

### Opción B · Render (gratis, sin administrar servidor)
1. En tu PC genera la clave: `cd web; php artisan key:generate --show` → copia `base64:...`.
2. <https://dashboard.render.com> → **New → Blueprint** → conecta GitHub → repo `laboratorio3_peajeIA` → Render lee
   `render.yaml` y crea **peaje-ia-db** (PostgreSQL) y **peaje-ia** (Docker, carpeta `web/`).
3. Completa las variables `sync: false` con la tabla de abajo (`APP_URL = https://peaje-ia.onrender.com`). **Apply**
   (primer build 5–8 min). En *Logs* debe aparecer `Tabla usuarios vacía: ejecutando seeders…` y `Peaje IA escuchando`.
4. Límites del plan gratuito: **se duerme tras ~15 min** sin tráfico (primer acceso ~1 min; mientras el ESP32 consulte
   no se duerme), el disco es efímero (las fotos semilla se reponen solas; las evidencias nuevas se pierden al
   redeploy: **exporta la BD y descarga evidencias justo después del video**) y la PostgreSQL gratuita caduca a los
   ~30 días. Para la defensa conviene el plan *Starter* o la opción A.
5. (Opcional) Dominio propio: *Settings → Custom Domains* → CNAME `peaje` → `peaje-ia.onrender.com`.

### Variables del `.env` (o de Render)
| Variable | Valor |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://TU-DOMINIO` (con https) |
| `DB_CONNECTION` (+ `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) | `mysql` con los datos de cPanel (Render: lo pone solo) |
| `SESSION_SECURE_COOKIE` | `true` |
| `ADMIN_EMAIL` | `saraquenta2@gmail.com` |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_IDS` / `GOOGLE_CLIENT_SECRET` | Paso 3 |
| `INFERENCE_URL` / `INFERENCE_TOKEN` | `https://<POD_ID>-8000.proxy.runpod.net` / `API_TOKEN` del Pod |
| `HARDWARE_TOKEN` | el token generado arriba |
| `PUSHER_APP_ID` / `PUSHER_APP_KEY` / `PUSHER_APP_SECRET` / `PUSHER_APP_CLUSTER` | Paso 4 (`sa1`) |
| `AGENTE_PROVEEDOR` / `GEMINI_API_KEY` | `gemini` / Paso 4 |
| `AUTH_DEV_LOGIN` | `false` |

### 5.1 Verificaciones
1. Abre `https://TU-DOMINIO` → **Ingresar con Google** con `saraquenta2@gmail.com` → entras como administradora.
2. *Administración → Lista blanca*: `doc.alvaro.pinto.ma@unifranz.edu.bo` = **administrador, activo**.
3. *Estado*: Inferencia **En línea** (si no pusiste la URL en el `.env`, pégala en *Configurar inferencia*), Base de datos OK,
   Google configurado, Tiempo real *Pusher Channels*, Agente *BD + Gemini*.
4. *Panel → Probar con una foto* → sube `web/database/seeders/evidencias/01_4047CKE.jpg` → aparece en la lista
   **sin recargar** y la evidencia guardada es la **imagen anotada** (máscara, placa, keypoints K1…Kn).
5. Con una cuenta de Google **no** autorizada → pantalla **Acceso denegado**.

---

## Paso 6 · APK

```powershell
cd C:\Users\Katerin\laboratorio3\movil
flutter clean; flutter pub get
flutter build apk --release --dart-define=API_BASE_URL=https://TU-DOMINIO --dart-define=GOOGLE_SERVER_CLIENT_ID=<WEB_CLIENT_ID>.apps.googleusercontent.com
```
* Salida: `movil\build\app\outputs\flutter-apk\app-release.apk` → súbelo a Drive `/apk` como `peaje-ia.apk`.
* Instalar: cópialo al celular y ábrelo (permite *Instalar apps desconocidas*), o `adb install -r ...apk` con depuración USB.
* Primera vez: acepta el permiso de **cámara**. En *Ajustes* de la app → *Probar inferencia* debe decir *en línea*.
* `DEVELOPER_ERROR` / código 10 al iniciar sesión: la SHA-1 registrada no es la del APK o el `GOOGLE_SERVER_CLIENT_ID`
  no es el **Client ID web** del mismo proyecto.

---

## Paso 7 · ESP32 (bono hardware)

1. Circuito: [`hardware/esp32_alerta_peaje/DIAGRAMA_CONEXION.md`](hardware/esp32_alerta_peaje/DIAGRAMA_CONEXION.md)
   — azul GPIO 25, verde 26, rojo 27 (cada uno con 220 Ω a GND), buzzer GPIO 14.
2. Arduino IDE 2.x → placa **esp32 (Espressif)** → **ESP32 Dev Module** → librería **ArduinoJson 7.x**.
3. Copia `secrets.h.example` como **`secrets.h`** (no se sube a Git) y completa: `WIFI_SSID`, `WIFI_PASSWORD`
   (2.4 GHz o hotspot del celular), `API_HOST` (dominio **sin** `https://`), `HARDWARE_TOKEN`, `CASETA_ID` (1).
4. **Subir** → Monitor serie 115200: `[wifi] OK` → `[sync] ultimo evento id=…`.
5. Registra un paso (app o panel): **azul** parpadea (inferencia) → **verde + 1 beep** (autorizado) o **rojo + 3 beeps**
   (alerta). En *Estado* aparece **ESP32: Conectado** con su RSSI.

---

## Paso 8 · Pruebas de extremo a extremo

### 8.1 Checklist "cero local"
- [ ] `APP_URL`, `INFERENCE_URL` y el callback de Google son `https://` públicos (ni `localhost` ni IPs privadas).
- [ ] `/health` del Pod responde desde los **datos móviles** del celular.
- [ ] La app se compiló con `API_BASE_URL=https://…` (en *Ajustes* se ve tu dominio).
- [ ] El ESP32 funciona con el **hotspot del celular** y `API_HOST` es tu dominio.
- [ ] Con tu PC **apagada** todo sigue funcionando.

### 8.2 Funcionalidad (placas reales de la semilla)
| Prueba | Cómo | Esperado |
|---|---|---|
| Login | docente / tu cuenta / cuenta ajena | administrador / administradora / **Acceso denegado** (web y app) |
| Modo Caseta | apunta a la foto de `4047CKE` (pantalla o impresa) a 2–3 fps | placa, categoría, ejes, pasajeros, Bs, **verde**; panel y ESP32 reaccionan |
| Evasión | foto de `5751PDE` (u otra placa no registrada) | alerta *no registrada*, **rojo + 3 beeps** |
| Sin placa | botón **Manual** apuntando a un vehículo sin placa visible | paso con alerta *placa ilegible* |
| Agente estado 1 | `¿pasó la 4047-CKE?` | **Registrada y con paso** (fecha, caseta, categoría, monto) |
| Agente estado 2 | `6802HKM` | **Registrada sin paso** |
| Agente estado 3 | `5751PDE` | **Identificada no registrada** (posible evasión) |
| Agente estado 4 | `4047CKF` | **No existe** + **¿Quisiste decir 4047CKE?** |
| Errores OCR | `S592UTF` (S↔5) y `2874A3G` (3↔B) | se corrigen a `5592UTF` y `2874ABG` |
| Foto en el agente | Modo Consulta → foto nítida / foto borrosa | estado de la placa / "envía otra foto" |
| Tiempo real | app y panel en pantalla dividida | el paso aparece sin recargar |

---

## Paso 9 · Carpeta de Google Drive y BD exportada

En el servidor (terminal de cPanel; en Render, pestaña *Shell* — disponible en planes de pago):
```bash
cd ~/laboratorio3_peajeIA/web && php artisan peaje:exportar-bd
# storage/app/exports/peaje_bd_AAAAmmdd_HHMMSS.sql y .json  -> descárgalos
```
Repite **después de grabar** para que incluya los pasos de la demo. La semilla ya está en `recursos/base_de_datos/`.

```text
Laboratorio_Final_Peaje/
├── video/demo_peaje_ia.mp4
├── apk/peaje-ia.apk
├── base_de_datos/peaje_bd_*.sql, peaje_bd_*.json
├── recursos/metricas/      (capturas de Colab: mAP, matriz de confusión, OCR, ejes)
├── recursos/entornos/      (evidencia_entorno_runpod-venv|conda|mamba .txt/.json + captura)
├── recursos/datasets/      (enlaces de Roboflow + las 19 fotos de placas)
├── recursos/hardware/      (foto del circuito)
├── capturas/               (panel, app, agente, estado, RunPod)
└── enlaces.txt             (repo GitHub, dominio, URL de RunPod)
```
Completa la tabla **Enlaces de la entrega** del `README.md` y haz `git commit` + `git push`.

---

## Paso 10 · Guion del video (8–12 min)

Prepara: Pod encendido (`/health` ok), panel abierto como administradora, app instalada, ESP32 en el hotspot y
visible en cámara, las fotos de las placas en otra pantalla o impresas, y un video real de vehículos (para ejes).

| Tiempo | Qué mostrar | Evidencia |
|---|---|---|
| 0:00–0:40 | Portada + diagrama de `docs/arquitectura.md` | "Todo corre en la nube" |
| 0:40–1:40 | Login Google (tu cuenta) → panel. Cuenta ajena → **Acceso denegado**. Lista blanca: docente administrador | Autenticación + roles |
| 1:40–2:40 | Dominio con candado HTTPS · RunPod *Running* · `/health` (GPU + modelos `propio:`) · *Estado*: cambiar `INFERENCE_URL` en vivo | **Bono RunPod** |
| 2:40–3:30 | Colab: métricas M1, M2 (matriz), M3 (ejes) y M5 | Entrenamiento |
| 3:30–5:10 | App **Modo Caseta** a 2–3 fps sobre `4047CKE` → tarjeta verde con placa, categoría, ejes, pasajeros, Bs; luego `5751PDE` → rojo + sonido | M1, M2, M3 en tiempo real |
| 5:10–6:00 | Panel en pantalla dividida: paso sin recargar, contadores, alerta, *Atender*, evidencia anotada (máscara, placa, K1…Kn) | Tiempo real + BD |
| 6:00–6:40 | ESP32: azul → verde + beep → rojo + 3 beeps; Monitor serie con la URL HTTPS | **Bono hardware** |
| 6:40–7:20 | Minibús con ocupantes: `pasajeros: N`, relleno opaco sobre las personas, columna *Pasajeros* | **Bono pasajeros** (sin rostros) |
| 7:20–8:50 | Agente (burbuja + app): `4047-CKE` (1), `6802HKM` (2), `5751PDE` (3), `4047CKF` (4 + ¿Quisiste decir?), `S592UTF`, foto borrosa | Agente sin alucinaciones |
| 8:50–9:40 | CRUD: crear vehículo `3456-bcd` (se normaliza), editar saldo, tarifas por ejes, casetas; operador sin permisos | Roles |
| 9:40–10:40 | Terminal de RunPod con las 3 evidencias de entornos + `requirements-venv.txt`, `environment-conda.yml`, `environment-mamba.yml` | **Bono entornos** |
| 10:40–11:30 | GitHub público (estructura, `.gitignore`, `.env.example`), `docs/openapi.yaml` y Postman, BD exportada, carpeta Drive | Entregables |
| 11:30–12:00 | Cierre: 3 modelos + 4 bonos, limitaciones, mejoras | — |

Graba con OBS (pantalla + celular con *scrcpy*). Haz un ensayo completo antes.

---

## Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| `/health` da 502 | servidor no iniciado o escuchando en 127.0.0.1 | `tmux attach -t peaje`; debe usar `--host 0.0.0.0` (lo hace `setup_runpod.sh`) |
| `/health` → `"estado": "error"` con `NO ENCAJA` | un `.pt` no corresponde (p. ej. nombres cambiados) | revisa `python diagnostico_entorno.py`; usa los nombres exactos |
| *Estado*: "Inferencia respondió 401" | `INFERENCE_TOKEN` distinto del `API_TOKEN` del Pod | copia el mismo valor |
| Panel dice "sondeo cada 3 s" | Pusher vacío o cluster distinto | revisa `PUSHER_*`, `php artisan config:clear` |
| `redirect_uri_mismatch` | callback no registrado | agrega `https://TU-DOMINIO/auth/google/callback` en Google Cloud |
| App: Acceso denegado con tu correo | no está en la lista blanca o inactivo | revisa `ADMIN_EMAIL` o agrégalo en *Lista blanca* |
| App: `DEVELOPER_ERROR` | SHA-1 o Client ID incorrectos | Paso 3.4 y 3.5 |
| Imágenes de evidencia rotas | falta `storage:link` | `php artisan storage:link` |
| ESP32 `[http] codigo -1` | Wi-Fi 5 GHz o `API_HOST` con `https://` | red 2.4 GHz; `API_HOST` solo el dominio |
| ESP32 `codigo 401` | `HARDWARE_TOKEN` distinto | iguala `secrets.h` y `.env` |
| Placas mal leídas | foto lejana/borrosa | acércate; el agente sugiere la placa correcta |
