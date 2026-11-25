/// Configuracion de la app. Los valores por defecto se inyectan al compilar:
///
///   flutter build apk --release \
///     --dart-define=API_BASE_URL=https://peaje.tu-dominio.com \
///     --dart-define=GOOGLE_SERVER_CLIENT_ID=xxxx.apps.googleusercontent.com
///
/// La URL del backend tambien se puede cambiar desde la pestana Ajustes (se guarda en el telefono).
/// Nunca apunta a localhost: el backend es la web publicada en el subdominio HTTPS.
class Config {
  static const String apiBaseUrlPorDefecto =
      String.fromEnvironment('API_BASE_URL', defaultValue: 'https://peaje.tu-dominio.com');

  /// "Web client ID" de Google Cloud (el mismo GOOGLE_CLIENT_ID de la web). Necesario en Android
  /// para que Google Sign-In devuelva un idToken que el backend pueda validar.
  static const String googleServerClientId =
      String.fromEnvironment('GOOGLE_SERVER_CLIENT_ID', defaultValue: '');

  /// Frames por segundo enviados en Modo Caseta (2 a 5).
  static const List<int> fpsDisponibles = [2, 3, 4, 5];
}
