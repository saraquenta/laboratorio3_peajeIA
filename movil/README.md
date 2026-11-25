# /movil — App Flutter (Modo Caseta + Modo Consulta)

- **Google Sign-In** (google_sign_in 7): obtiene un `idToken` y lo canjea en `POST /api/v1/auth/google`.
- **Modo Caseta**: cámara trasera, envía frames a **2–5 fps** a `POST /api/v1/pasos/frame` y muestra placa, categoría,
  ejes, pasajeros, tarifa y estado (borde verde = cobrado, rojo = alerta). Salta frames si la red está lenta.
- **Modo Consulta**: chat con el agente por texto o foto (cámara/galería) → `POST /api/v1/agente/chat`, con los 4 estados
  y botones de "¿Quisiste decir…?".
- **Ajustes**: URL del backend (HTTPS), prueba de inferencia y cierre de sesión.

## Compilar

```powershell
cd movil
flutter pub get
flutter analyze
flutter test
# APK de prueba
flutter build apk --debug --dart-define=API_BASE_URL=https://peaje.tu-dominio.com --dart-define=GOOGLE_SERVER_CLIENT_ID=XXXX.apps.googleusercontent.com
# APK para la demo
flutter build apk --release --dart-define=API_BASE_URL=https://peaje.tu-dominio.com --dart-define=GOOGLE_SERVER_CLIENT_ID=XXXX.apps.googleusercontent.com
```

Salida: `build/app/outputs/flutter-apk/app-debug.apk` (o `app-release.apk`). Instala con `adb install -r <apk>` o copiándolo al teléfono.

## Google Sign-In en Android

1. En Google Cloud crea un cliente OAuth **Android** con el paquete `bo.edu.unifranz.peaje_movil` y la **SHA-1** del keystore que firma el APK
   (debug: `keytool -list -v -keystore %USERPROFILE%\.android\debug.keystore -alias androiddebugkey -storepass android`).
2. Pasa el **Client ID web** (el mismo `GOOGLE_CLIENT_ID` de la web) como `GOOGLE_SERVER_CLIENT_ID`.
   Así el `idToken` lleva `aud` = Client ID web, que el backend valida con `GOOGLE_CLIENT_IDS`.
3. Si al entrar aparece "Acceso denegado", el correo no está en la lista blanca (*Administración → Lista blanca* en la web).

No se necesita `google-services.json` (no se usa Firebase). Ningún secreto se compila en el APK.
