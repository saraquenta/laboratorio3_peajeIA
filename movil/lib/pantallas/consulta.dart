import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../api.dart';

/// Modo Consulta: chat con el agente por texto o foto de placa (POST /api/v1/agente/chat).
class ConsultaPantalla extends StatefulWidget {
  const ConsultaPantalla({super.key});

  @override
  State<ConsultaPantalla> createState() => _ConsultaPantallaState();
}

class _Mensaje {
  _Mensaje(this.deMi, this.texto, {this.estado, this.sugerencias = const [], this.imagen});
  final bool deMi;
  final String texto;
  final String? estado;
  final List<String> sugerencias;
  final Uint8List? imagen;
}

class _ConsultaPantallaState extends State<ConsultaPantalla> {
  final _texto = TextEditingController();
  final _scroll = ScrollController();
  final _mensajes = <_Mensaje>[
    _Mensaje(false, 'Hola 👋 Escribe una placa (ej. 1234ABC) o toma una foto de la placa. Te diré si está '
        'registrada, si tiene pasos o si es una posible evasión.'),
  ];
  bool _esperando = false;

  Future<void> _enviar({String? texto, Uint8List? imagen}) async {
    if ((texto == null || texto.isEmpty) && imagen == null) return;
    setState(() {
      _mensajes.add(_Mensaje(true, texto ?? '📷 Foto de placa', imagen: imagen));
      _esperando = true;
    });
    _bajar();
    try {
      final r = await Api.instancia.chat(mensaje: texto, imagen: imagen);
      _mensajes.add(_Mensaje(false, '${r['respuesta']}', estado: r['estado'] as String?,
          sugerencias: ((r['sugerencias'] as List?) ?? []).map((e) => '$e').toList()));
    } catch (e) {
      _mensajes.add(_Mensaje(false, 'Error: $e'));
    } finally {
      if (mounted) setState(() => _esperando = false);
      _bajar();
    }
  }

  void _bajar() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) _scroll.animateTo(_scroll.position.maxScrollExtent, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
      });

  Future<void> _foto(ImageSource fuente) async {
    final x = await ImagePicker().pickImage(source: fuente, maxWidth: 1600, imageQuality: 85);
    if (x != null) await _enviar(imagen: await x.readAsBytes());
  }

  Color _colorEstado(String? e) => switch (e) {
        'Registrada y con paso' => Colors.green,
        'Registrada sin paso' => Colors.blue,
        'Identificada no registrada' => Colors.red,
        'No existe' => Colors.grey,
        _ => Colors.transparent,
      };

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    return Column(children: [
      Expanded(
        child: ListView.builder(
          controller: _scroll,
          padding: const EdgeInsets.all(12),
          itemCount: _mensajes.length + (_esperando ? 1 : 0),
          itemBuilder: (_, i) {
            if (i == _mensajes.length) return const Align(alignment: Alignment.centerLeft, child: Padding(padding: EdgeInsets.all(8), child: CircularProgressIndicator()));
            final m = _mensajes[i];
            return Align(
              alignment: m.deMi ? Alignment.centerRight : Alignment.centerLeft,
              child: Container(
                margin: const EdgeInsets.symmetric(vertical: 4),
                padding: const EdgeInsets.all(12),
                constraints: const BoxConstraints(maxWidth: 320),
                decoration: BoxDecoration(
                  color: m.deMi ? t.colorScheme.primary : t.colorScheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(14),
                  border: m.estado != null ? Border(left: BorderSide(color: _colorEstado(m.estado), width: 5)) : null,
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  if (m.imagen != null) ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.memory(m.imagen!, height: 140)),
                  if (m.estado != null)
                    Text(m.estado!.toUpperCase(), style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: _colorEstado(m.estado))),
                  Text(m.texto.replaceAll('**', ''), style: TextStyle(color: m.deMi ? t.colorScheme.onPrimary : null)),
                  if (m.sugerencias.isNotEmpty)
                    Wrap(spacing: 6, children: [
                      for (final s in m.sugerencias) ActionChip(label: Text(s), onPressed: () => _enviar(texto: s)),
                    ]),
                ]),
              ),
            );
          },
        ),
      ),
      Padding(
        padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
        child: Row(children: [
          IconButton(onPressed: _esperando ? null : () => _foto(ImageSource.camera), icon: const Icon(Icons.photo_camera)),
          IconButton(onPressed: _esperando ? null : () => _foto(ImageSource.gallery), icon: const Icon(Icons.photo_library_outlined)),
          Expanded(
            child: TextField(
              controller: _texto,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(hintText: 'Placa o pregunta…', border: OutlineInputBorder(), isDense: true),
              onSubmitted: (v) {
                _texto.clear();
                _enviar(texto: v.trim());
              },
            ),
          ),
          IconButton.filled(
            onPressed: _esperando ? null : () {
              final v = _texto.text.trim();
              _texto.clear();
              _enviar(texto: v);
            },
            icon: const Icon(Icons.send),
          ),
        ]),
      ),
    ]);
  }
}
