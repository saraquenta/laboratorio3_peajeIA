<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Services\InferenciaService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstadoController extends Controller
{
    public function index(InferenciaService $inferencia, RealtimeService $rt, \App\Services\HardwareService $hw)
    {
        try {
            DB::select('select 1');
            $bd = true;
        } catch (\Throwable) {
            $bd = false;
        }

        return view('estado', [
            'inferencia' => $inferencia->estado(),
            'origenUrl' => Configuracion::obtener('INFERENCE_URL') ? 'panel (base de datos)' : '.env',
            'tokenConfigurado' => filled($inferencia->token()),
            'bd' => $bd,
            'tiempoReal' => $rt->habilitado() ? 'Pusher Channels' : 'Sondeo cada 3 s (sin Pusher)',
            'google' => filled(config('services.google.client_id')),
            'agenteLlm' => match (config('peaje.agente.proveedor')) {
                'gemini' => filled(config('peaje.agente.gemini_api_key')) ? 'Gemini '.config('peaje.agente.gemini_modelo') : null,
                'anthropic' => filled(config('peaje.agente.anthropic_api_key')) ? 'Claude '.config('peaje.agente.modelo') : null,
                default => null,
            },
            'hardware' => filled(config('peaje.hardware.token')),
            'esp32' => $hw->visto(),
        ]);
    }

    /** JSON para refrescar el indicador sin recargar. */
    public function verificar(InferenciaService $inferencia)
    {
        return response()->json($inferencia->estado());
    }

    /** Solo administrador: cambia INFERENCE_URL / INFERENCE_TOKEN sin tocar el .env (p. ej. URL nueva de Colab). */
    public function guardarInferencia(Request $request)
    {
        $request->validate(['inference_url' => 'nullable|url|max:300', 'inference_token' => 'nullable|string|max:300']);
        Configuracion::guardar('INFERENCE_URL', rtrim((string) $request->input('inference_url'), '/') ?: null);
        if ($request->filled('inference_token')) {
            Configuracion::guardar('INFERENCE_TOKEN', $request->input('inference_token'));
        }

        return back()->with('ok', 'Configuración de inferencia guardada.');
    }
}
