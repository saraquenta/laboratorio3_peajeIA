import 'dart:async';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api.dart';
import '../config.dart';

/// Modo Caseta: la camara captura frames (2-5 fps) y los envia a POST /api/v1/pasos/frame.
/// Muestra placa, categoria, ejes, pasajeros, tarifa y estado (verde = cobrado, rojo = alerta).
class CasetaPantalla extends StatefulWidget {
  const CasetaPantalla({super.key});

  @override
  State<CasetaPantalla> createState() => _CasetaPantallaState();
}

class _CasetaPantallaState extends State<CasetaPantalla> with WidgetsBindingObserver {
  CameraController? _camara;
  List<Map<String, dynamic>> _casetas = [];
  int? _casetaId;
  int _fps = 2;
  bool _activo = false;
  bool _ocupado = false;
  Timer? _timer;
  Map<String, dynamic>? _ultimo;
  String _estadoTexto = 'Detenido';
  int _enviados = 0;
  int _registrados = 0;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _iniciar();
  }

  Future<void> _iniciar() async {
    try {
      _casetas = await Api.instancia.casetas();
      _casetaId = _casetas.isNotEmpty ? _casetas.first['id'] as int : null;
    } catch (e) {
      _error = 'No se pudieron cargar las casetas: $e';
    }
    try {
      final camaras = await availableCameras();
      final trasera = camaras.firstWhere((c) => c.lensDirection == CameraLensDirection.back,
          orElse: () => camaras.first);
      final ctrl = CameraController(trasera, ResolutionPreset.medium, enableAudio: false,
          imageFormatGroup: ImageFormatGroup.jpeg);
      await ctrl.initialize();
      await ctrl.setFlashMode(FlashMode.off);
      _camara = ctrl;
    } catch (e) {
      _error = 'Cámara no disponible: $e';
    }
    if (mounted) setState(() {});
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.inactive || state == AppLifecycleState.paused) _detener();
  }

  void _alternar() => _activo ? _detener() : _arrancar();

  void _arrancar() {
    if (_camara == null || _casetaId == null) return;
    _timer?.cancel();
    _timer = Timer.periodic(Duration(milliseconds: (1000 / _fps).round()), (_) => _capturar());
    setState(() {
      _activo = true;
      _estadoTexto = 'Enviando a $_fps fps…';
    });
  }

  void _detener() {
    _timer?.cancel();
    if (mounted) {
      setState(() {
        _activo = false;
        _estadoTexto = 'Detenido';
      });
    }
  }

  /// Aviso en el telefono: vibracion + sonido del sistema (alerta) o clic (paso autorizado).
  void _avisar(Map<String, dynamic> r) {
    if (r['registrado'] != true) return;
    if ((r['senal'] as Map?)?['led'] == 'rojo') {
      HapticFeedback.heavyImpact();
      SystemSound.play(SystemSoundType.alert);
    } else {
      HapticFeedback.lightImpact();
      SystemSound.play(SystemSoundType.click);
    }
  }

  Future<void> _capturar({bool manual = false}) async {
    final cam = _camara;
    if (_casetaId == null || cam == null || !cam.value.isInitialized) return;
    if (_ocupado || cam.value.isTakingPicture) return;
    _ocupado = true; // si la red es lenta se saltan frames en vez de acumularlos
    try {
      final foto = await cam.takePicture();
      final bytes = await foto.readAsBytes();
      final r = await Api.instancia.enviarFrame(bytes, _casetaId!, manual: manual);
      _enviados++;
      if (r['registrado'] == true) _registrados++;
      _avisar(r);
      if (!mounted) return;
      setState(() {
        _error = r['error']?.toString();
        if ((r['deteccion'] as Map?)?['categoria'] != null || r['registrado'] == true) _ultimo = r;
        _estadoTexto = _textoMotivo(r['motivo']?.toString());
      });
    } on ApiException catch (e) {
      if (e.status == 401 || e.status == 403) _detener();
      if (mounted) setState(() => _error = e.mensaje);
    } catch (e) {
      if (mounted) setState(() => _error = 'Red: $e');
    } finally {
      _ocupado = false;
    }
  }

  String _textoMotivo(String? m) => switch (m) {
        'cobrado' => 'Paso cobrado ✔',
        'no_registrada' => 'ALERTA: placa no registrada',
        'saldo_insuficiente' => 'ALERTA: saldo insuficiente',
        'vehiculo_inactivo' => 'ALERTA: vehículo inactivo',
        'duplicado' => 'Ya registrado (mismo vehículo)',
        'placa_ilegible' => 'Placa ilegible',
        'sin_vehiculo' => 'Esperando vehículo…',
        'inferencia_no_disponible' => 'Inferencia no disponible',
        _ => 'Procesando…',
      };

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    _camara?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    final cam = _camara;
    return Column(children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
        child: Row(children: [
          Expanded(
            child: DropdownButtonFormField<int>(
              initialValue: _casetaId,
              decoration: const InputDecoration(labelText: 'Caseta', isDense: true, border: OutlineInputBorder()),
              items: [for (final c in _casetas) DropdownMenuItem(value: c['id'] as int, child: Text('${c['nombre']}'))],
              onChanged: _activo ? null : (v) => setState(() => _casetaId = v),
            ),
          ),
          const SizedBox(width: 8),
          DropdownButton<int>(
            value: _fps,
            items: [for (final f in Config.fpsDisponibles) DropdownMenuItem(value: f, child: Text('$f fps'))],
            onChanged: _activo ? null : (v) => setState(() => _fps = v ?? 2),
          ),
        ]),
      ),
      Expanded(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Stack(fit: StackFit.expand, children: [
              if (cam != null && cam.value.isInitialized)
                FittedBox(fit: BoxFit.cover, child: SizedBox(
                    width: cam.value.previewSize?.height ?? 480,
                    height: cam.value.previewSize?.width ?? 640,
                    child: CameraPreview(cam)))
              else
                Container(color: Colors.black12, child: Center(child: Text(_error ?? 'Iniciando cámara…'))),
              Positioned(left: 12, top: 12, child: Chip(
                avatar: Icon(_activo ? Icons.fiber_manual_record : Icons.pause, color: _activo ? Colors.red : null, size: 16),
                label: Text('$_estadoTexto · $_enviados env. · $_registrados reg.'),
              )),
            ]),
          ),
        ),
      ),
      _Resultado(r: _ultimo),
      if (_error != null)
        Padding(padding: const EdgeInsets.symmetric(horizontal: 12), child: Text(_error!, style: TextStyle(color: t.colorScheme.error), maxLines: 2)),
      Padding(
        padding: const EdgeInsets.all(12),
        child: Row(children: [
          Expanded(
            child: FilledButton.icon(
              style: FilledButton.styleFrom(backgroundColor: _activo ? t.colorScheme.error : null, padding: const EdgeInsets.all(14)),
              onPressed: cam == null || _casetaId == null ? null : _alternar,
              icon: Icon(_activo ? Icons.stop : Icons.play_arrow),
              label: Text(_activo ? 'Detener caseta' : 'Iniciar caseta'),
            ),
          ),
          const SizedBox(width: 8),
          // Captura manual: registra el paso aunque no haya placa legible (vehiculo sin placa -> alerta)
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(padding: const EdgeInsets.all(14)),
            onPressed: cam == null || _casetaId == null ? null : () => _capturar(manual: true),
            icon: const Icon(Icons.camera),
            label: const Text('Manual'),
          ),
        ]),
      ),
    ]);
  }
}

class _Resultado extends StatelessWidget {
  const _Resultado({required this.r});
  final Map<String, dynamic>? r;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context);
    if (r == null) {
      return const Card(margin: EdgeInsets.symmetric(horizontal: 12), child: ListTile(title: Text('Sin detecciones todavía')));
    }
    final det = (r!['deteccion'] as Map?) ?? {};
    final paso = r!['paso'] as Map?;
    final led = (r!['senal'] as Map?)?['led'];
    final color = led == 'verde' ? Colors.green : (led == 'rojo' ? Colors.red : t.colorScheme.outline);
    final veh = r!['vehiculo'] as Map?;
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12),
      shape: RoundedRectangleBorder(side: BorderSide(color: color, width: 3), borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(color: const Color(0xFFFFF8D6), border: Border.all(color: const Color(0xFFD9C46A)),
                  borderRadius: BorderRadius.circular(6)),
              child: Text('${paso?['placa'] ?? det['placa_normalizada'] ?? '—'}',
                  style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.bold, fontSize: 20, color: Colors.black)),
            ),
            const SizedBox(width: 10),
            Expanded(child: Text(veh?['propietario']?.toString() ?? 'No registrada', overflow: TextOverflow.ellipsis)),
            Icon(led == 'verde' ? Icons.check_circle : Icons.warning_amber_rounded, color: color, size: 30),
          ]),
          const SizedBox(height: 8),
          Wrap(spacing: 16, runSpacing: 4, children: [
            Text('Categoría: ${det['categoria'] ?? paso?['categoria'] ?? '—'}'),
            Text('Ejes: ${det['ejes'] ?? paso?['ejes'] ?? '—'}'),
            Text('Pasajeros: ${det['pasajeros'] ?? '—'}'),
            Text('Tarifa: Bs ${(r!['tarifa'] as num?)?.toStringAsFixed(2) ?? '—'}'),
            Text('OCR: ${((det['confianza_ocr'] as num? ?? 0) * 100).round()}%'),
            if (det['latencia_ms'] != null) Text('${det['latencia_ms']} ms'),
          ]),
          if (r!['alerta'] != null) ...[
            const SizedBox(height: 6),
            Text('${(r!['alerta'] as Map)['mensaje']}', style: const TextStyle(color: Colors.red)),
          ],
          if (veh != null) Text('Saldo: Bs ${(veh['saldo'] as num).toStringAsFixed(2)}', style: t.textTheme.bodySmall),
        ]),
      ),
    );
  }
}
