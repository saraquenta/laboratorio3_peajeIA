<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Services\HardwareService;
use App\Services\InferenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CasetaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['casetas' => Caseta::where('activa', true)->orderBy('id')->get(['id', 'codigo', 'nombre', 'ubicacion'])]);
    }

    /** GET /api/v1/casetas/{id}/resumen?fecha=YYYY-MM-DD */
    public function resumen(Request $request, int $id): JsonResponse
    {
        $caseta = Caseta::findOrFail($id);
        $fecha = $request->filled('fecha') ? Carbon::parse($request->input('fecha')) : today();
        $q = Paso::where('caseta_id', $id)->whereDate('created_at', $fecha);

        $porCategoria = (clone $q)->selectRaw('categoria, count(*) as pasos, sum(monto) as monto')->groupBy('categoria')->get()
            ->map(fn ($f) => ['categoria' => $f->categoria, 'pasos' => (int) $f->pasos, 'monto' => (float) $f->monto]);

        return response()->json([
            'caseta' => $caseta->only(['id', 'codigo', 'nombre', 'ubicacion', 'activa']),
            'fecha' => $fecha->toDateString(),
            'pasos' => (clone $q)->count(),
            'cobrados' => (clone $q)->where('estado', 'cobrado')->count(),
            'con_alerta' => (clone $q)->where('estado', 'alerta')->count(),
            'recaudacion' => (float) (clone $q)->where('estado', 'cobrado')->sum('monto'),
            'pasajeros' => (int) (clone $q)->sum('pasajeros'),
            'por_categoria' => $porCategoria,
            'alertas_pendientes' => Alerta::where('caseta_id', $id)->where('estado', 'pendiente')->count(),
            'ultimos_pasos' => (clone $q)->with('caseta', 'vehiculo')->latest('id')->limit(10)->get()->map->resumen(),
        ]);
    }

    /** GET /api/v1/estado  -> si INFERENCE_URL responde */
    public function estado(InferenciaService $inferencia): JsonResponse
    {
        return response()->json(['inferencia' => $inferencia->estado()]);
    }

    /**
     * GET /api/v1/hardware/ultimo-evento?caseta_id=1&rssi=-60  (X-Hardware-Token)
     * -> {id, tipo_senal: idle|inferencia|ok|alerta, placa, monto, timestamp}. Lo consulta el ESP32 cada ~1 s.
     */
    public function ultimoEvento(Request $request, HardwareService $hardware): JsonResponse
    {
        $caseta = $request->filled('caseta_id') ? $request->integer('caseta_id') : null;
        $hardware->marcarVisto($caseta, $request->query('rssi'), $request->ip());

        return response()->json($hardware->ultimoEvento($caseta));
    }

    /** GET /api/v1/hardware/estado?caseta_id=1  (X-Hardware-Token) -> LED verde/rojo + buzzer del ultimo paso */
    public function hardware(Request $request): JsonResponse
    {
        $q = Paso::query()->latest('id');
        if ($request->filled('caseta_id')) {
            $q->where('caseta_id', $request->integer('caseta_id'));
        }

        return response()->json(HardwareService::senal($q->first()));
    }
}
