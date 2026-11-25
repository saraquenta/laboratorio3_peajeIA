<?php

namespace App\Http\Controllers;

use App\Models\Caseta;
use App\Models\Paso;
use App\Services\PlacaService;
use Illuminate\Http\Request;

class PasoWebController extends Controller
{
    public function index(Request $request)
    {
        $q = Paso::with('caseta', 'vehiculo')->latest('id');
        if ($p = $request->input('placa')) {
            $q->where('placa', 'like', '%'.PlacaService::limpiar($p).'%');
        }
        foreach (['caseta_id', 'estado', 'categoria'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->input($f));
            }
        }
        if ($request->filled('fecha')) {
            $q->whereDate('created_at', $request->input('fecha'));
        }

        return view('pasos.index', ['pasos' => $q->paginate(20)->withQueryString(), 'casetas' => Caseta::all()]);
    }

    public function show(Paso $paso)
    {
        $paso->load('caseta', 'vehiculo', 'usuario', 'alertas');

        return view('pasos.show', compact('paso'));
    }
}
