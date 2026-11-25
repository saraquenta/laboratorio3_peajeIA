import 'package:flutter/material.dart';

import 'api.dart';
import 'pantallas/ajustes.dart';
import 'pantallas/caseta.dart';
import 'pantallas/consulta.dart';
import 'pantallas/login.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Api.instancia.cargar();
  runApp(const PeajeApp());
}

class PeajeApp extends StatefulWidget {
  const PeajeApp({super.key});

  @override
  State<PeajeApp> createState() => _PeajeAppState();
}

class _PeajeAppState extends State<PeajeApp> {
  bool get _autenticado => Api.instancia.token != null;

  void _refrescar() => setState(() {});

  @override
  Widget build(BuildContext context) {
    final semilla = const Color(0xFF1F5EFF);
    return MaterialApp(
      title: 'Peaje Inteligente',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(colorSchemeSeed: semilla, useMaterial3: true),
      darkTheme: ThemeData(colorSchemeSeed: semilla, brightness: Brightness.dark, useMaterial3: true),
      home: _autenticado ? Inicio(alSalir: _refrescar) : LoginPantalla(alEntrar: _refrescar),
    );
  }
}

class Inicio extends StatefulWidget {
  const Inicio({super.key, required this.alSalir});
  final VoidCallback alSalir;

  @override
  State<Inicio> createState() => _InicioState();
}

class _InicioState extends State<Inicio> {
  int _indice = 0;

  @override
  Widget build(BuildContext context) {
    final paginas = [const CasetaPantalla(), const ConsultaPantalla(), AjustesPantalla(alSalir: widget.alSalir)];
    return Scaffold(
      body: SafeArea(child: IndexedStack(index: _indice, children: paginas)),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _indice,
        onDestinationSelected: (i) => setState(() => _indice = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.videocam_outlined), selectedIcon: Icon(Icons.videocam), label: 'Caseta'),
          NavigationDestination(icon: Icon(Icons.chat_bubble_outline), selectedIcon: Icon(Icons.chat_bubble), label: 'Consulta'),
          NavigationDestination(icon: Icon(Icons.settings_outlined), selectedIcon: Icon(Icons.settings), label: 'Ajustes'),
        ],
      ),
    );
  }
}
