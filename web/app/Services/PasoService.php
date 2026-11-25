<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Tarifa;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Flujo de un frame enviado por la app movil (modo caseta):
 *   frame -> inferencia (M1+M2+M3+pasajeros) -> normaliza placa -> busca vehiculo -> calcula tarifa
 *   -> cobra (descuenta saldo) o genera alerta -> guarda evidencia -> tiempo real + hardware.
 */
class PasoService
{
    public function __construct(
        private InferenciaService $inferencia,
        private RealtimeService $realtime,
        private HardwareService $hardware,
    ) {}

    public static function tarifa(?string $categoria, ?int $ejes): float
    {
        $t = Tarifa::where('categoria', $categoria ?: 'auto')->first();

        return $t ? $t->calcular($ejes) : 0.0;
    }

    /**
     * @return array{registrado:bool, motivo:string, deteccion:array, paso:?array, alerta:?array, tarifa:?float, senal:array}
     */
    public function procesarFrame(UploadedFile $imagen, Caseta $caseta, ?Usuario $usuario, bool $registrar = true,
                                  bool $manual = false): array
    {
        // LED azul del ESP32: inferencia en curso
        $this->hardware->registrar('inferencia', $caseta->id, null, 0, 'Inferencia en curso');
        try {
            $det = $this->inferencia->predecir($imagen, anotar: $registrar);
        } catch (\Throwable $e) {
            return ['registrado' => false, 'motivo' => 'inferencia_no_disponible', 'error' => $e->getMessage(),
                'deteccion' => [], 'paso' => null, 'alerta' => null, 'tarifa' => null,
                'senal' => ['led' => 'rojo', 'buzzer' => true, 'texto' => 'Inferencia no disponible']];
        }

        $anotada = $det['imagen_anotada_base64'] ?? null;
        unset($det['imagen_anotada_base64']);   // no se reenvia la imagen en el JSON de respuesta
        $norm = PlacaService::normalizar($det['placa_normalizada'] ?? $det['placa'] ?? '');
        $det['placa_normalizada'] = $norm['valida'] ? $norm['normalizada'] : ($det['placa_normalizada'] ?? null);
        $conf = (float) ($det['confianza_ocr'] ?? 0);
        $vehiculo = $norm['valida'] ? Vehiculo::where('placa', $norm['normalizada'])->first() : null;
        $categoria = $det['categoria'] ?? $vehiculo?->categoria;
        $ejes = $det['ejes'] ?? $vehiculo?->ejes;
        $tarifa = $categoria ? self::tarifa($categoria, $ejes) : null;

        $base = ['deteccion' => $det, 'tarifa' => $tarifa, 'registrado' => false, 'paso' => null, 'alerta' => null,
            'vehiculo' => $vehiculo ? ['placa' => $vehiculo->placa, 'propietario' => $vehiculo->propietario,
                'saldo' => (float) $vehiculo->saldo, 'activo' => $vehiculo->activo] : null];

        if (! $categoria) {
            return $base + ['motivo' => 'sin_vehiculo', 'senal' => ['led' => 'apagado', 'buzzer' => false, 'texto' => 'Sin vehículo']];
        }
        if (! $norm['valida'] || $conf < (float) config('peaje.confianza_minima_ocr')) {
            if ($manual && $registrar) {
                // Captura manual del operador: vehiculo sin placa o placa ilegible -> se registra con alerta
                return $this->registrarIlegible($imagen, $anotada, $det, $caseta, $usuario, $categoria, $ejes, $base);
            }
            // Frame automatico (2-5 fps) con placa ilegible: no se registra, el siguiente frame suele leerla mejor
            return $base + ['motivo' => 'placa_ilegible', 'senal' => ['led' => 'apagado', 'buzzer' => false, 'texto' => 'Leyendo placa…']];
        }
        $ventana = now()->subSeconds((int) config('peaje.ventana_duplicados_s'));
        $previo = Paso::where('caseta_id', $caseta->id)->where('placa', $norm['normalizada'])->where('created_at', '>=', $ventana)->latest('id')->first();
        if ($previo) {
            return array_merge($base, ['motivo' => 'duplicado', 'paso' => $previo->resumen(), 'senal' => HardwareService::senal($previo)]);
        }
        if (! $registrar) {
            return $base + ['motivo' => 'solo_consulta', 'senal' => ['led' => 'apagado', 'buzzer' => false, 'texto' => 'Consulta']];
        }

        $url = $this->guardarEvidencia($imagen, $anotada);
        [$paso, $alerta] = DB::transaction(function () use ($det, $norm, $conf, $vehiculo, $categoria, $ejes, $tarifa, $caseta, $usuario, $url) {
            $tipoAlerta = null;
            $mensaje = null;
            if (! $vehiculo) {
                $tipoAlerta = 'no_registrada';
                $mensaje = "Placa {$norm['normalizada']} identificada pero no registrada: posible evasión.";
            } elseif (! $vehiculo->activo) {
                $tipoAlerta = 'vehiculo_inactivo';
                $mensaje = "El vehículo {$vehiculo->placa} está inactivo.";
            } else {
                $vehiculo = Vehiculo::whereKey($vehiculo->id)->lockForUpdate()->first();
                if ((float) $vehiculo->saldo < $tarifa) {
                    $tipoAlerta = 'saldo_insuficiente';
                    $mensaje = "Saldo insuficiente para {$vehiculo->placa}: tiene Bs ".number_format((float) $vehiculo->saldo, 2).' y la tarifa es Bs '.number_format($tarifa, 2).'.';
                } else {
                    $vehiculo->decrement('saldo', $tarifa);
                }
            }
            $paso = Paso::create([
                'caseta_id' => $caseta->id, 'vehiculo_id' => $vehiculo?->id, 'usuario_id' => $usuario?->id,
                'placa_leida' => mb_substr((string) ($det['placa'] ?? ''), 0, 20), 'placa' => $norm['normalizada'],
                'confianza_ocr' => $conf, 'categoria' => $categoria, 'ejes' => $ejes, 'pasajeros' => $det['pasajeros'] ?? null,
                'monto' => $tipoAlerta ? 0 : $tarifa, 'estado' => $tipoAlerta ? 'alerta' : 'cobrado', 'imagen_url' => $url,
                'latencia_ms' => $det['latencia_ms'] ?? null,
                'datos_inferencia' => array_intersect_key($det, array_flip(['placa', 'placa_normalizada', 'confianza_ocr',
                    'categoria', 'confianza_categoria', 'ejes', 'ejes_fuente', 'pasajeros', 'pasajeros_fuente', 'latencia_ms', 'modelos'])),
            ]);
            $alerta = $tipoAlerta ? Alerta::create(['paso_id' => $paso->id, 'caseta_id' => $caseta->id,
                'placa' => $norm['normalizada'], 'tipo' => $tipoAlerta, 'mensaje' => $mensaje]) : null;

            return [$paso, $alerta];
        });

        $paso->load('caseta', 'vehiculo');
        $this->notificar($paso, $alerta);

        return array_merge($base, ['registrado' => true, 'motivo' => $alerta ? $alerta->tipo : 'cobrado',
            'paso' => $paso->resumen(), 'alerta' => $alerta?->resumen(),
            'vehiculo' => $paso->vehiculo ? ['placa' => $paso->vehiculo->placa, 'propietario' => $paso->vehiculo->propietario,
                'saldo' => (float) $paso->vehiculo->fresh()->saldo, 'activo' => $paso->vehiculo->activo] : null,
            'senal' => HardwareService::senal($paso)]);
    }

    public function notificar(Paso $paso, ?Alerta $alerta): void
    {
        $this->realtime->publicar('paso.registrado', ['paso' => $paso->resumen()]);
        if ($alerta) {
            $this->realtime->publicar('alerta.creada', ['alerta' => $alerta->resumen()]);
        }
        $this->hardware->notificar($paso);
    }

    private function registrarIlegible(UploadedFile $imagen, ?string $anotada, array $det, Caseta $caseta, ?Usuario $usuario,
                                       string $categoria, ?int $ejes, array $base): array
    {
        $url = $this->guardarEvidencia($imagen, $anotada);
        [$paso, $alerta] = DB::transaction(function () use ($det, $caseta, $usuario, $categoria, $ejes, $url) {
            $paso = Paso::create([
                'caseta_id' => $caseta->id, 'usuario_id' => $usuario?->id,
                'placa_leida' => mb_substr((string) ($det['placa'] ?? ''), 0, 20) ?: null, 'placa' => null,
                'confianza_ocr' => (float) ($det['confianza_ocr'] ?? 0), 'categoria' => $categoria, 'ejes' => $ejes,
                'pasajeros' => $det['pasajeros'] ?? null, 'monto' => 0, 'estado' => 'alerta', 'imagen_url' => $url,
                'latencia_ms' => $det['latencia_ms'] ?? null,
                'datos_inferencia' => array_intersect_key($det, array_flip(['placa', 'confianza_ocr', 'categoria', 'ejes',
                    'ejes_fuente', 'pasajeros', 'pasajeros_fuente', 'latencia_ms', 'modelos'])),
            ]);
            $alerta = Alerta::create(['paso_id' => $paso->id, 'caseta_id' => $caseta->id, 'placa' => null, 'tipo' => 'placa_ilegible',
                'mensaje' => "Vehículo ({$categoria}) sin placa visible o placa ilegible: revisar la evidencia."]);

            return [$paso, $alerta];
        });
        $paso->load('caseta', 'vehiculo');
        $this->notificar($paso, $alerta);

        return array_merge($base, ['registrado' => true, 'motivo' => 'placa_ilegible', 'paso' => $paso->resumen(),
            'alerta' => $alerta->resumen(), 'senal' => HardwareService::senal($paso)]);
    }

    /** Guarda la imagen anotada por el servidor de inferencia (mascara, placa, keypoints) o, si no viene, el frame. */
    private function guardarEvidencia(UploadedFile $imagen, ?string $anotadaBase64 = null): ?string
    {
        $ruta = 'evidencias/'.now()->format('Y/m/d').'/'.Str::uuid().'.jpg';
        $bytes = null;
        if ($anotadaBase64) {
            $b64 = str_contains($anotadaBase64, ',') ? substr($anotadaBase64, strpos($anotadaBase64, ',') + 1) : $anotadaBase64;
            $bytes = base64_decode($b64, true) ?: null;
        }
        Storage::disk('public')->put($ruta, $bytes ?? file_get_contents($imagen->getRealPath()));

        return Storage::disk('public')->url($ruta);
    }
}
