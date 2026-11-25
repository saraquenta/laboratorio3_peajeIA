import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:peaje_movil/api.dart';
import 'package:peaje_movil/main.dart';

void main() {
  testWidgets('Sin token muestra el login con Google', (tester) async {
    SharedPreferences.setMockInitialValues({});
    await Api.instancia.cargar();
    await tester.pumpWidget(const PeajeApp());
    expect(find.text('Ingresar con Google'), findsOneWidget);
    expect(find.text('Peaje Inteligente'), findsOneWidget);
  });
}
