# Despliegue paso a paso

Orden recomendado: **1) Google OAuth → 2) Web en subdominio HTTPS → 3) Inferencia en RunPod → 4) App móvil → 5) Hardware.**

## 1. Google OAuth 2.0

1. <https://console.cloud.google.com> → crea un proyecto → *APIs y servicios* → **Pantalla de consentimiento OAuth**
   (tipo *Externo*, agrega tu correo y el del docente como usuarios de prueba mientras esté en modo *Testing*).
2. *Credenciales* → **Crear ID de cliente OAuth** → tipo **Aplicación web**:
   - Orígenes autorizados: `https://peaje.tu-dominio.com`
   - URI de redirección: `https://peaje.tu-dominio.com/auth/google/callback`
   - Copia **Client ID** → `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_IDS`; **Client secret** → `GOOGLE_CLIENT_SECRET` (solo en el `.env` del servidor).
3. Otra credencial tipo **Android**: paquete `bo.edu.unifranz.peaje_movil` y la huella **SHA-1** del keystore con que firmas el APK:
   ```powershell
   keytool -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
   ```
   (para el APK *release*, usa la SHA-1 de tu keystore de release). No hay secreto que copiar: Google identifica la app por paquete + SHA-1.
4. La app usa el **Client ID web** como `GOOGLE_SERVER_CLIENT_ID` para recibir un `id_token` con `aud` = ese ID.

## 2. Web (Laravel) en un subdominio HTTPS

Requisitos: PHP ≥ 8.3 con `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`; MySQL/MariaDB; Composer (o subir `vendor/`).

### Opción A — Hosting compartido con cPanel

1. *Dominios* → crea el subdominio `peaje.tu-dominio.com` con **raíz de documentos** `peaje/web/public`.
2. *SSL/TLS Status* → **Run AutoSSL** (Let's Encrypt) para el subdominio. Activa *Forzar HTTPS*.
3. *Bases de datos MySQL* → crea BD + usuario con todos los privilegios.
4. Sube el repositorio (Git Version Control de cPanel → *Clone* desde tu GitHub público, o sube un zip) a `~/peaje`.
5. *Terminal* de cPanel (o SSH):
   ```bash
   cd ~/peaje/web
   composer install --no-dev --optimize-autoloader      # si no hay Composer: súbelo con vendor/ ya instalado
   cp .env.example .env && nano .env                     # ver tabla de variables
   php artisan key:generate
   php artisan migrate --seed --force                    # crea tablas + 24 vehículos + docente admin
   php artisan storage:link                              # evidencias públicas en /storage
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   Si el hosting no permite `storage:link`, crea el enlace simbólico desde *Administrador de archivos* o define `FILESYSTEM_DISK=public` y un symlink `public/storage → ../storage/app/public`.
6. Si no puedes cambiar la raíz del subdominio, apunta el subdominio a `public_html/peaje` y copia ahí el contenido de `web/public`,
   editando `index.php` para que las rutas `__DIR__.'/../vendor'` y `'/../bootstrap'` apunten a `~/peaje/web`.

### Opción B — VPS (Ubuntu + Nginx)

```bash
sudo apt install nginx mariadb-server php8.3-fpm php8.3-{mysql,mbstring,xml,curl,zip} composer certbot python3-certbot-nginx
git clone https://github.com/saraquenta/laboratorio3_peajeIA.git /var/www/peaje
cd /var/www/peaje/web && composer install --no-dev -o && cp .env.example .env && php artisan key:generate
php artisan migrate --seed --force && php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
# server { server_name peaje.tu-dominio.com; root /var/www/peaje/web/public; index index.php;
#   location / { try_files $uri $uri/ /index.php?$query_string; }
#   location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; } }
sudo certbot --nginx -d peaje.tu-dominio.com
```

### Variables del `.env` de producción

| Variable | Valor |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://peaje.tu-dominio.com` |
| `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `mysql` + datos de cPanel |
| `SESSION_SECURE_COOKIE` | `true` |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_CLIENT_IDS` | del paso 1 |
| `SEED_ADMIN_EMAILS` | tu correo (opcional; el del docente ya está) |
| `INFERENCE_URL`, `INFERENCE_TOKEN` | del Pod (también editable en **Estado**) |
| `PUSHER_APP_ID/KEY/SECRET/CLUSTER` | opcional: <https://dashboard.pusher.com> → *Channels* (plan gratuito) |
| `HARDWARE_TOKEN` | token largo aleatorio |
| `ANTHROPIC_API_KEY` | opcional (redacción del agente) |
| `AUTH_DEV_LOGIN` | `false` (el login de desarrollo solo existe con `APP_ENV=local`) |

### Tiempo real en hosting compartido

Los hostings compartidos no permiten procesos WebSocket permanentes. Por eso:
- **Con Pusher** (recomendado): PHP solo hace un `POST` HTTPS a Pusher en cada paso y el navegador recibe el evento por WebSocket.
- **Sin Pusher**: el panel consulta `/panel/datos` cada 3 s. Funciona en cualquier hosting sin configurar nada.

## 3. Inferencia en RunPod

Sigue [`inferencia/GUIA_RUNPOD.md`](../inferencia/GUIA_RUNPOD.md). Al final copia `INFERENCE_URL` y `INFERENCE_TOKEN`
en **Estado → Configurar inferencia** (administrador) y verifica el indicador *En línea*.

## 4. App móvil

```powershell
cd movil
flutter pub get
flutter build apk --release --dart-define=API_BASE_URL=https://peaje.tu-dominio.com --dart-define=GOOGLE_SERVER_CLIENT_ID=XXXX.apps.googleusercontent.com
```
APK: `movil/build/app/outputs/flutter-apk/app-release.apk`. Detalles en [`movil/README.md`](../movil/README.md).

## 5. Hardware

Ver [`hardware/README.md`](../hardware/README.md).

## Lista de verificación final

- [ ] `https://peaje.tu-dominio.com/login` muestra *Ingresar con Google* con candado HTTPS.
- [ ] El docente entra como **administrador**; un correo no autorizado ve **Acceso denegado**.
- [ ] **Estado**: inferencia *En línea*, BD OK, Google configurado.
- [ ] La app registra un paso y el **panel** se actualiza sin recargar.
- [ ] Una placa no registrada genera alerta y enciende el **LED rojo**.
- [ ] El chat responde los 4 estados y sugiere "¿Quisiste decir…?".
