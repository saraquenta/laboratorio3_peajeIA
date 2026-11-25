import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import 'config.dart';

class ApiException implements Exception {
  ApiException(this.status, this.mensaje);
  final int status;
  final String mensaje;
  @override
  String toString() => mensaje;
}

/// Cliente de la API REST del backend (/api/v1). Autenticacion: token Bearer obtenido con Google.
class Api {
  Api._();
  static final Api instancia = Api._();

  String baseUrl = Config.apiBaseUrlPorDefecto;
  String? token;
  Map<String, dynamic>? usuario;
  final http.Client _http = http.Client();

  Future<void> cargar() async {
    final p = await SharedPreferences.getInstance();
    baseUrl = p.getString('baseUrl') ?? Config.apiBaseUrlPorDefecto;
    token = p.getString('token');
    final u = p.getString('usuario');
    usuario = u != null ? jsonDecode(u) as Map<String, dynamic> : null;
  }

  Future<void> guardarBaseUrl(String url) async {
    baseUrl = url.trim().replaceAll(RegExp(r'/+$'), '');
    final p = await SharedPreferences.getInstance();
    await p.setString('baseUrl', baseUrl);
  }

  Uri _uri(String ruta) => Uri.parse('$baseUrl/api/v1/$ruta');

  Map<String, String> get _cabeceras => {
        'Accept': 'application/json',
        if (token != null) 'Authorization': 'Bearer $token',
      };

  Map<String, dynamic> _procesar(http.Response r) {
    Map<String, dynamic> cuerpo;
    try {
      cuerpo = jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException(r.statusCode, 'Respuesta inválida del servidor (${r.statusCode})');
    }
    if (r.statusCode >= 400) {
      throw ApiException(r.statusCode, (cuerpo['message'] ?? 'Error ${r.statusCode}').toString());
    }
    return cuerpo;
  }

  /// POST /auth/google con el idToken de Google Sign-In. Aplica la lista blanca del backend.
  Future<Map<String, dynamic>> loginGoogle(String idToken) async {
    final r = await _http.post(_uri('auth/google'),
        headers: {..._cabeceras, 'Content-Type': 'application/json'},
        body: jsonEncode({'id_token': idToken, 'dispositivo': 'app-movil'}));
    final j = _procesar(r);
    token = j['token'] as String;
    usuario = j['usuario'] as Map<String, dynamic>;
    final p = await SharedPreferences.getInstance();
    await p.setString('token', token!);
    await p.setString('usuario', jsonEncode(usuario));
    return j;
  }

  Future<void> salir() async {
    try {
      await _http.post(_uri('auth/salir'), headers: _cabeceras).timeout(const Duration(seconds: 5));
    } catch (_) {}
    token = null;
    usuario = null;
    final p = await SharedPreferences.getInstance();
    await p.remove('token');
    await p.remove('usuario');
  }

  Future<List<Map<String, dynamic>>> casetas() async {
    final j = _procesar(await _http.get(_uri('casetas'), headers: _cabeceras));
    return (j['casetas'] as List).cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> resumenCaseta(int id) async =>
      _procesar(await _http.get(_uri('casetas/$id/resumen'), headers: _cabeceras));

  /// POST /pasos/frame (multipart) - Modo Caseta.
  /// [manual] = captura del operador: el backend registra el paso aunque la placa sea ilegible (alerta).
  Future<Map<String, dynamic>> enviarFrame(Uint8List jpeg, int casetaId, {bool manual = false}) async {
    final req = http.MultipartRequest('POST', _uri('pasos/frame'))
      ..headers.addAll(_cabeceras)
      ..fields['caseta_id'] = '$casetaId'
      ..fields['manual'] = manual ? '1' : '0'
      ..files.add(http.MultipartFile.fromBytes('imagen', jpeg, filename: 'frame.jpg'));
    final r = await http.Response.fromStream(await _http.send(req).timeout(const Duration(seconds: 40)));
    if (r.statusCode == 503) {
      final j = jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>;
      return j; // inferencia caida: se muestra en pantalla sin cortar el bucle
    }
    return _procesar(r);
  }

  /// POST /agente/chat - Modo Consulta (texto y/o foto).
  Future<Map<String, dynamic>> chat({String? mensaje, Uint8List? imagen}) async {
    final req = http.MultipartRequest('POST', _uri('agente/chat'))..headers.addAll(_cabeceras);
    if (mensaje != null && mensaje.isNotEmpty) req.fields['mensaje'] = mensaje;
    if (imagen != null) req.files.add(http.MultipartFile.fromBytes('imagen', imagen, filename: 'placa.jpg'));
    final r = await http.Response.fromStream(await _http.send(req).timeout(const Duration(seconds: 60)));
    return _procesar(r);
  }

  Future<Map<String, dynamic>> estado() async => _procesar(await _http.get(_uri('estado'), headers: _cabeceras));
}
