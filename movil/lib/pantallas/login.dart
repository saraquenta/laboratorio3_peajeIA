import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../api.dart';
import '../config.dart';

/// Inicio de sesion exclusivamente con Google. El backend valida el idToken y la lista blanca.
class LoginPantalla extends StatefulWidget {
  const LoginPantalla({super.key, required this.alEntrar});
  final VoidCallback alEntrar;

  @override
  State<LoginPantalla> createState() => _LoginPantallaState();
}

class _LoginPantallaState extends State<LoginPantalla> {
  bool _cargando = false;
  String? _error;
  bool _inicializado = false;

  Future<void> _inicializar() async {
    if (_inicializado) return;
    await GoogleSignIn.instance.initialize(
      serverClientId: Config.googleServerClientId.isEmpty ? null : Config.googleServerClientId,
    );
    _inicializado = true;
  }

  Future<void> _entrar() async {
    setState(() {
      _cargando = true;
      _error = null;
    });
    try {
      await _inicializar();
      final cuenta = await GoogleSignIn.instance.authenticate(scopeHint: const ['email', 'profile']);
      final idToken = cuenta.authentication.idToken;
      if (idToken == null) {
        throw ApiException(0, 'Google no devolvió un idToken. Revisa GOOGLE_SERVER_CLIENT_ID (Web client ID).');
      }
      await Api.instancia.loginGoogle(idToken);
      widget.alEntrar();
    } on GoogleSignInException catch (e) {
      setState(() => _error = e.code == GoogleSignInExceptionCode.canceled
          ? 'Inicio de sesión cancelado.'
          : 'Google Sign-In: ${e.description ?? e.code.name}');
    } on ApiException catch (e) {
      await GoogleSignIn.instance.signOut();
      setState(() => _error = e.status == 403 ? 'Acceso denegado: ${e.mensaje}' : e.mensaje);
    } catch (e) {
      setState(() => _error = 'No se pudo conectar con ${Api.instancia.baseUrl}: $e');
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Text('🛣️', style: TextStyle(fontSize: 56)),
              Text('Peaje Inteligente', style: t.textTheme.headlineMedium),
              const SizedBox(height: 4),
              Text('Modo Caseta y Modo Consulta', style: t.textTheme.bodyMedium),
              const SizedBox(height: 32),
              FilledButton.icon(
                onPressed: _cargando ? null : _entrar,
                icon: _cargando
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.login),
                label: const Text('Ingresar con Google'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 16),
                Text(_error!, textAlign: TextAlign.center, style: TextStyle(color: t.colorScheme.error)),
              ],
              const SizedBox(height: 24),
              Text('Servidor: ${Api.instancia.baseUrl}', style: t.textTheme.bodySmall),
              TextButton(
                onPressed: () async {
                  final c = TextEditingController(text: Api.instancia.baseUrl);
                  final url = await showDialog<String>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('URL del backend (HTTPS)'),
                      content: TextField(controller: c, keyboardType: TextInputType.url),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancelar')),
                        FilledButton(onPressed: () => Navigator.pop(ctx, c.text), child: const Text('Guardar')),
                      ],
                    ),
                  );
                  if (url != null && url.isNotEmpty) {
                    await Api.instancia.guardarBaseUrl(url);
                    setState(() {});
                  }
                },
                child: const Text('Cambiar servidor'),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}
