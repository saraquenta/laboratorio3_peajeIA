<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    public function index(Request $request)
    {
        $estado = $request->input('estado', 'pendiente');
        $q = Alerta::with('caseta', 'paso', 'atendidaPor')->latest('id');
        if ($estado !== 'todas') {
            $q->where('estado', $estado);
        }
        if ($request->filled('tipo')) {
            $q->where('tipo', $request->input('tipo'));
        }

        return view('alertas.index', ['alertas' => $q->paginate(20)->withQueryString(), 'estado' => $estado, 'tipos' => Alerta::TIPOS]);
    }

    public function update(Request $request, Alerta $alerta)
    {
        $request->validate(['estado' => ['required', Rule::in(['pendiente', 'atendida', 'descartada'])]]);
        $alerta->update(['estado' => $request->input('estado'),
            'atendida_por' => $request->input('estado') === 'pendiente' ? null : $request->user()->id,
            'atendida_en' => $request->input('estado') === 'pendiente' ? null : now()]);

        return back()->with('ok', 'Alerta #'.$alerta->id.' marcada como '.$alerta->estado.'.');
    }
}
