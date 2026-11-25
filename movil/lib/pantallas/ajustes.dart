import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../api.dart';

class AjustesPantalla extends StatefulWidget {
  const AjustesPantalla({super.key, required this.alSalir});
  final VoidCallback alSalir;

  @override
  State<AjustesPantalla> createState() => _AjustesPantallaState();
}

class _AjustesPantallaState extends State<AjustesPantalla> {
  late final TextEditingController _url = TextEditingController(text: Api.instancia.baseUrl);
  String? _estado;

  Future<void> _probar() async {
    setState(() => _estado = 'Consultando…');
    try {
      final r = await Api.instancia.estado();
      final inf = r['inferencia'] as Map;
      setState(() => _estado = inf['en_linea'] == true
          ? 'Inferencia en línea (${inf['latencia_ms']} ms)\n${inf['url']}'
          : 'Inferencia sin respuesta: ${inf['detalle']}');
    } catch (e) {
      setState(() => _estado = 'Error: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = Api.instancia.usuario ?? {};
    return ListView(padding: const EdgeInsets.all(16), children: [
      ListTile(
        leading: CircleAvatar(
          backgroundImage: u['avatar_url'] != null ? NetworkImage(u['avatar_url'] as String) : null,
          child: u['avatar_url'] == null ? const Icon(Icons.person) : null,
        ),
        title: Text('${u['nombre'] ?? u['email'] ?? ''}'),
        subtitle: Text('${u['email'] ?? ''} · ${u['rol'] ?? ''}'),
      ),
      const SizedBox(height: 12),
      TextField(controller: _url, keyboardType: TextInputType.url,
          decoration: const InputDecoration(labelText: 'URL del backend (HTTPS)', border: OutlineInputBorder())),
      const SizedBox(height: 8),
      Row(children: [
        FilledButton.tonal(
          onPressed: () async {
            await Api.instancia.guardarBaseUrl(_url.text);
            if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('URL guardada')));
          },
          child: const Text('Guardar URL'),
        ),
        const SizedBox(width: 8),
        OutlinedButton(onPressed: _probar, child: const Text('Probar inferencia')),
      ]),
      if (_estado != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(_estado!)),
      const Divider(height: 32),
      FilledButton.icon(
        style: FilledButton.styleFrom(backgroundColor: Theme.of(context).colorScheme.error),
        onPressed: () async {
          await Api.instancia.salir();
          try {
            await GoogleSignIn.instance.signOut();
          } catch (_) {}
          widget.alSalir();
        },
        icon: const Icon(Icons.logout),
        label: const Text('Cerrar sesión'),
      ),
    ]);
  }
}
