<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Caseta;
use App\Services\PasoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PasoController extends Controller
{
    /**
     * POST /api/v1/pasos/frame  (multipart: imagen, caseta_id, registrar=1)
     * La app movil envia 2-5 frames por segundo; solo se crea un paso cuando la placa se lee con
     * confianza suficiente y no se registro la misma placa en la misma caseta en la ventana de duplicados.
     */
    public function frame(Request $request, PasoService $servicio): JsonResponse
    {
        $request->validate([
            'imagen' => 'required|file|mimes:jpg,jpeg,png|max:8192',
            'caseta_id' => 'required|integer|exists:casetas,id',
            'registrar' => 'nullable|boolean',
            'manual' => 'nullable|boolean',   // captura manual del operador: registra aunque la placa sea ilegible
        ]);
        $caseta = Caseta::findOrFail($request->integer('caseta_id'));
        if (! $caseta->activa) {
            return response()->json(['message' => 'La caseta está inactiva'], 422);
        }
        $r = $servicio->procesarFrame($request->file('imagen'), $caseta, $request->user(), $request->boolean('registrar', true),
            $request->boolean('manual'));

        return response()->json($r, isset($r['error']) ? 503 : 200);
    }
}
