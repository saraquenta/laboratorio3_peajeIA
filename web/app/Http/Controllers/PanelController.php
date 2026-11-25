<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Vehiculo;
use App\Services\PasoService;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function index(RealtimeService $rt)
    {
        return view('panel', [
            'datos' => $this->datosPanel(),
            'casetas' => Caseta::where('activa', true)->get(),
            'pusher' => $rt->habilitado() ? ['key' => config('peaje.pusher.key'), 'cluster' => config('peaje.pusher.cluster'),
                'authEndpoint' => route('broadcasting.auth')] : null,
        ]);
    }

    /** JSON del panel (lo usa el sondeo de respaldo y la actualizacion tras un evento de Pusher). */
    public function datos()
    {
        return response()->json($this->datosPanel());
    }

    public function datosPanel(): array
    {
        $hoy = Paso::whereDate('created_at', today());

        return [
            'contadores' => [
                'pasos_hoy' => (clone $hoy)->count(),
                'recaudacion_hoy' => (float) (clone $hoy)->where('estado', 'cobrado')->sum('monto'),
                'alertas_pendientes' => Alerta::where('estado', 'pendiente')->count(),
                'pasajeros_hoy' => (int) (clone $hoy)->sum('pasajeros'),
                'vehiculos_registrados' => Vehiculo::count(),
                'recaudacion_total' => (float) Paso::where('estado', 'cobrado')->sum('monto'),
            ],
            'por_caseta' => Caseta::orderBy('id')->get()->map(fn ($c) => [
                'nombre' => $c->nombre,
                'pasos' => Paso::where('caseta_id', $c->id)->whereDate('created_at', today())->count(),
                'monto' => (float) Paso::where('caseta_id', $c->id)->whereDate('created_at', today())->where('estado', 'cobrado')->sum('monto'),
            ]),
            'pasos' => Paso::with('caseta', 'vehiculo')->latest('id')->limit(12)->get()->map->resumen(),
            'alertas' => Alerta::with('caseta', 'paso')->where('estado', 'pendiente')->latest('id')->limit(8)->get()->map->resumen(),
            'ultimo_id' => (int) Paso::max('id'),
            'ultima_alerta_id' => (int) Alerta::max('id'),
            'generado' => now()->toIso8601String(),
        ];
    }

    public function autorizarCanal(Request $request, RealtimeService $rt)
    {
        $firma = $rt->autorizar((string) $request->input('channel_name'), (string) $request->input('socket_id'));

        return $firma ? response($firma, 200, ['Content-Type' => 'application/json']) : response()->json(['message' => 'Canal no autorizado'], 403);
    }

    /** Formulario del panel: subir una foto y procesarla como si viniera de la app (util para pruebas y la demo). */
    public function simular(Request $request, PasoService $servicio)
    {
        $request->validate(['imagen' => 'required|file|mimes:jpg,jpeg,png|max:8192', 'caseta_id' => 'required|exists:casetas,id']);
        // Desde el panel es una captura manual: si la placa es ilegible igual se registra con alerta
        $r = $servicio->procesarFrame($request->file('imagen'), Caseta::findOrFail($request->integer('caseta_id')), $request->user(),
            true, true);
        $msg = $r['registrado'] ? 'Paso registrado: '.$r['paso']['placa'].' ('.$r['motivo'].')'
            : 'No se registró paso: '.$r['motivo'].(isset($r['error']) ? ' — '.$r['error'] : '');

        return back()->with($r['registrado'] && $r['motivo'] === 'cobrado' ? 'ok' : 'error', $msg);
    }
}
