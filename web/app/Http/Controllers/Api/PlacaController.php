<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Paso;
use App\Models\Vehiculo;
use App\Services\AgenteService;
use App\Services\ConsultaPlacaService;
use App\Services\PlacaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlacaController extends Controller
{
    /** POST /api/v1/placas/consultar {placa} -> uno de los 4 estados */
    public function consultar(Request $request, ConsultaPlacaService $consultas): JsonResponse
    {
        $request->validate(['placa' => 'required|string|max:20']);
        $r = $consultas->consultar($request->string('placa'));
        $r['mensaje'] = ConsultaPlacaService::texto($r);

        return response()->json($r);
    }

    /** POST /api/v1/placas/consultar-imagen (multipart: imagen) -> OCR + 4 estados (pide otra foto si no es legible) */
    public function consultarImagen(Request $request, AgenteService $agente): JsonResponse
    {
        $request->validate(['imagen' => 'required|file|mimes:jpg,jpeg,png|max:8192']);
        $r = $agente->responder('', $request->file('imagen'));

        return response()->json(['estado' => $r['estado'], 'mensaje' => $r['respuesta']] + $r['datos']);
    }

    /** GET /api/v1/placas/{placa}/historial */
    public function historial(Request $request, string $placa): JsonResponse
    {
        $p = PlacaService::normalizar($placa)['normalizada'];
        $pasos = Paso::with('caseta', 'vehiculo')->where('placa', $p)->latest('id')
            ->paginate(min(100, max(1, $request->integer('por_pagina', 20))));
        $v = Vehiculo::where('placa', $p)->first();

        return response()->json([
            'placa' => $p,
            'registrada' => (bool) $v,
            'vehiculo' => $v?->only(['placa', 'propietario', 'categoria', 'ejes', 'saldo', 'activo']),
            'total_pasos' => $pasos->total(),
            'total_pagado' => (float) Paso::where('placa', $p)->where('estado', 'cobrado')->sum('monto'),
            'pasos' => collect($pasos->items())->map->resumen(),
            'pagina' => $pasos->currentPage(), 'ultima_pagina' => $pasos->lastPage(),
            'sugerencias' => $v ? [] : PlacaService::sugerencias($p),
        ]);
    }
}
