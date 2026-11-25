<?php

namespace Database\Seeders;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /** Correo del docente: OBLIGATORIO como administrador en la lista blanca. */
    public const DOCENTE = 'doc.alvaro.pinto.ma@unifranz.edu.bo';

    /**
     * Placas bolivianas reales fotografiadas para el laboratorio (primeras evidencias del sistema).
     * archivo en database/seeders/evidencias => [placa, categoria, propietario, registrada]
     * 5751PDE queda SIN registrar: demuestra "Identificada no registrada" (posible evasion).
     */
    public const FOTOS = [
        '01_4047CKE.jpg' => ['4047CKE', 'auto', 'Rodrigo Mamani Choque', true],
        '02_5988ZPG.jpg' => ['5988ZPG', 'auto', 'Valeria Gutiérrez Rojas', true],
        '03_5710ZXL.jpg' => ['5710ZXL', 'auto', 'Marcelo Quispe Huanca', true],
        '04_1534PHK.jpg' => ['1534PHK', 'minibus', 'Servicio de Ambulancias La Paz', true],
        '05_2327KFH.jpg' => ['2327KFH', 'minibus', 'Sindicato Mixto Villa Fátima', true],
        '06_2504CZK.jpg' => ['2504CZK', 'auto', 'Carla Apaza Limachi', true],
        '07_5585IYY.jpg' => ['5585IYY', 'minibus', 'Turismo Illimani Tours', true],
        '08_2874ABG.jpg' => ['2874ABG', 'minibus', 'Cooperativa 6 de Marzo', true],
        '09_5736TKE.jpg' => ['5736TKE', 'auto', 'Daniela Torrez Calle', true],
        '10_503XKE.jpg' => ['503XKE', 'auto', 'Hugo Vargas Ticona', true],
        '11_5592UTF.jpg' => ['5592UTF', 'auto', 'Ana Belén Condori', true],
        '12_2164GNP.jpg' => ['2164GNP', 'auto', 'Jorge Luis Alanoca', true],
        '13_5720DYG.jpg' => ['5720DYG', 'auto', 'Paola Chura Mendoza', true],
        '14_2374YSL.jpg' => ['2374YSL', 'auto', 'Radio Taxi Sopocachi', true],
        '15_5218IZI.jpg' => ['5218IZI', 'auto', 'Lucía Fernández Nina', true],
        '16_5239BEA.jpg' => ['5239BEA', 'minibus', 'Sindicato Litoral Línea 234', true],
        '17_3620YKI.jpg' => ['3620YKI', 'minibus', 'Sindicato San Cristóbal', true],
        '18_5751PDE.jpg' => ['5751PDE', 'auto', null, false],
        '19_5705KUE.jpg' => ['5705KUE', 'auto', 'Gabriel Ramos Quisbert', true],
    ];

    public function run(): void
    {
        // ---------------- Usuarios (lista blanca Google) ----------------
        Usuario::updateOrCreate(['email_google' => self::DOCENTE],
            ['nombre' => 'Ing. Alvaro Pinto Mamani', 'rol' => Usuario::ADMIN, 'activo' => true]);
        foreach (config('peaje.admin_emails') as $e) {
            Usuario::updateOrCreate(['email_google' => mb_strtolower($e)], ['rol' => Usuario::ADMIN, 'activo' => true]);
        }
        foreach (config('peaje.operador_emails') as $e) {
            Usuario::firstOrCreate(['email_google' => mb_strtolower($e)], ['rol' => Usuario::OPERADOR, 'activo' => true]);
        }
        Usuario::firstOrCreate(['email_google' => 'operador.demo@gmail.com'],
            ['nombre' => 'Operador de demostración', 'rol' => Usuario::OPERADOR, 'activo' => true]);

        // ---------------- Casetas ----------------
        $casetas = collect([
            ['codigo' => 'C-01', 'nombre' => 'Caseta Achica Arriba', 'ubicacion' => 'Carretera La Paz – Oruro, km 18'],
            ['codigo' => 'C-02', 'nombre' => 'Caseta Senkata', 'ubicacion' => 'El Alto, salida a Oruro'],
            ['codigo' => 'C-03', 'nombre' => 'Caseta Huarina', 'ubicacion' => 'Carretera La Paz – Copacabana'],
        ])->map(fn ($c) => Caseta::updateOrCreate(['codigo' => $c['codigo']], $c + ['activa' => true]));

        // ---------------- Tarifas (Bs) ----------------
        $tarifas = [
            'moto' => [2, 2, 0], 'auto' => [5, 2, 0], 'minibus' => [8, 2, 0], 'bus' => [15, 2, 5], 'camion' => [20, 2, 10],
        ];
        foreach ($tarifas as $cat => [$base, $incl, $extra]) {
            Tarifa::updateOrCreate(['categoria' => $cat], ['monto_base' => $base, 'ejes_incluidos' => $incl, 'monto_eje_adicional' => $extra]);
        }

        // ---------------- 24 vehiculos de prueba ----------------
        $vehiculos = [
            ['1234ABC', 'Juan Carlos Mamani Quispe', 'auto', 2, 150.00],
            ['2345BCD', 'María Fernanda Choque Limachi', 'auto', 2, 80.50],
            ['3456CDF', 'Transportes Illimani S.R.L.', 'camion', 3, 500.00],
            ['4567DFG', 'Luis Alberto Condori', 'minibus', 2, 45.00],
            ['5678FGH', 'Cooperativa 16 de Julio', 'bus', 2, 320.00],
            ['6789GHJ', 'Ana Lucía Huanca', 'moto', 2, 20.00],
            ['852KLM', 'Pedro Ticona Apaza', 'auto', 2, 3.00],          // saldo insuficiente
            ['963LMN', 'Rosa Mamani de Flores', 'auto', 2, 60.00],
            ['1478MNP', 'Distribuidora Andina', 'camion', 5, 900.00],
            ['2589NPR', 'Carlos Eduardo Rojas', 'auto', 2, 0.00],       // sin saldo
            ['3690PRS', 'Sindicato Mixto Pacajes', 'minibus', 2, 120.00],
            ['4701RST', 'Flota Copacabana', 'bus', 3, 410.00],
            ['5812STV', 'Jorge Quisbert Laura', 'moto', 2, 15.00],
            ['6923TVW', 'Gabriela Torrez Vargas', 'auto', 2, 95.00],
            ['7034VWX', 'Agroexport Altiplano', 'camion', 4, 750.00],
            ['8145WXY', 'Miguel Ángel Calle', 'auto', 2, 33.00],
            ['9256XYZ', 'Lucía Paredes Aliaga', 'minibus', 2, 70.00],
            ['1357BDF', 'Ramiro Gutiérrez', 'auto', 2, 210.00],
            ['2468CFH', 'Trans Oruro Express', 'bus', 2, 600.00],
            ['3579DGJ', 'Elena Ramos Callisaya', 'moto', 2, 8.00],
            ['4680FHK', 'Constructora Sajama', 'camion', 3, 1200.00],
            ['5791GJL', 'Daniel Alanoca', 'auto', 2, 40.00, false],    // inactivo
            ['6802HKM', 'Patricia Nina Mendoza', 'auto', 2, 55.00],
            ['LPZ123', 'Teodoro Vela (placa antigua)', 'auto', 2, 25.00],
        ];
        foreach (self::FOTOS as [$placa, $categoria, $propietario, $registrada]) {
            if ($registrada) {
                $vehiculos[] = [$placa, $propietario, $categoria, 2, 100.00];
            }
        }
        foreach ($vehiculos as $v) {
            Vehiculo::updateOrCreate(['placa' => $v[0]], ['propietario' => $v[1], 'categoria' => $v[2], 'ejes' => $v[3],
                'saldo' => $v[4], 'activo' => $v[5] ?? true]);
        }

        // ---------------- Pasos y alertas de ejemplo (para que el panel y el agente tengan datos) ----------------
        if (Paso::count() > 0) {
            return;
        }
        $this->sembrarFotos($casetas[0]);
        $operador = Usuario::where('email_google', 'operador.demo@gmail.com')->first();
        $ejemplos = [
            // placa, caseta, minutos atras, pasajeros
            ['1234ABC', 0, 300, 2], ['3456CDF', 0, 240, 1], ['5678FGH', 1, 200, 24], ['4567DFG', 2, 150, 9],
            ['1478MNP', 1, 120, 1], ['6789GHJ', 0, 90, 1], ['2345BCD', 2, 60, 3], ['7034VWX', 0, 30, 2],
            ['9999ZZZ', 1, 45, 1],   // no registrada -> "Identificada no registrada" (posible evasion)
            ['852KLM', 0, 20, 1],    // saldo insuficiente
        ];
        foreach ($ejemplos as [$placa, $ci, $min, $pax]) {
            $v = Vehiculo::where('placa', $placa)->first();
            $cat = $v?->categoria ?? 'auto';
            $ejes = $v?->ejes ?? 2;
            $monto = Tarifa::where('categoria', $cat)->first()->calcular($ejes);
            $alerta = ! $v ? 'no_registrada' : ((float) $v->saldo < $monto ? 'saldo_insuficiente' : null);
            $fecha = now()->subMinutes($min);
            $paso = Paso::create([
                'caseta_id' => $casetas[$ci]->id, 'vehiculo_id' => $v?->id, 'usuario_id' => $operador?->id,
                'placa_leida' => $placa, 'placa' => $placa, 'confianza_ocr' => 0.9, 'categoria' => $cat, 'ejes' => $ejes,
                'pasajeros' => $pax, 'monto' => $alerta ? 0 : $monto, 'estado' => $alerta ? 'alerta' : 'cobrado',
                'latencia_ms' => 150, 'datos_inferencia' => ['ejes_fuente' => 'semilla'],
            ]);
            $paso->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();
            if ($alerta) {
                $a = Alerta::create(['paso_id' => $paso->id, 'caseta_id' => $casetas[$ci]->id, 'placa' => $placa, 'tipo' => $alerta,
                    'mensaje' => $alerta === 'no_registrada' ? "Placa {$placa} identificada pero no registrada: posible evasión."
                        : "Saldo insuficiente para {$placa}."]);
                $a->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();
            } elseif ($v) {
                $v->decrement('saldo', $monto);
            }
        }
    }

    /**
     * Un paso por cada foto real (18/11/2026, Caseta 1) con la imagen como evidencia publica en storage.
     * Las registradas quedan cobradas; la no registrada genera su alerta ya atendida (no altera las pendientes).
     */
    private function sembrarFotos(Caseta $caseta): void
    {
        $origen = database_path('seeders/evidencias');
        $i = 0;
        foreach (self::FOTOS as $archivo => [$placa, $categoria, , $registrada]) {
            if (! is_file("{$origen}/{$archivo}")) {
                continue;
            }
            $ruta = "evidencias/semilla/{$archivo}";
            Storage::disk('public')->put($ruta, file_get_contents("{$origen}/{$archivo}"));
            $v = $registrada ? Vehiculo::where('placa', $placa)->first() : null;
            $monto = Tarifa::where('categoria', $categoria)->first()->calcular(2);
            $fecha = Carbon::parse('2026-11-18 19:50:00')->addMinutes($i++);
            $paso = Paso::create([
                'caseta_id' => $caseta->id, 'vehiculo_id' => $v?->id, 'placa_leida' => $placa, 'placa' => $placa,
                'confianza_ocr' => 0.9, 'categoria' => $categoria, 'ejes' => 2, 'pasajeros' => 1,
                'monto' => $v ? $monto : 0, 'estado' => $v ? 'cobrado' : 'alerta',
                'imagen_url' => Storage::disk('public')->url($ruta),
                'datos_inferencia' => ['ejes_fuente' => 'semilla', 'origen' => 'foto inicial del laboratorio'],
            ]);
            $paso->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();
            if ($v) {
                $v->decrement('saldo', $monto);
            } else {
                $a = Alerta::create(['paso_id' => $paso->id, 'caseta_id' => $caseta->id, 'placa' => $placa, 'tipo' => 'no_registrada',
                    'mensaje' => "Placa {$placa} identificada pero no registrada: posible evasión.", 'estado' => 'atendida',
                    'atendida_en' => $fecha]);
                $a->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();
            }
        }
    }
}
