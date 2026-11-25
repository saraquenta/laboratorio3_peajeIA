<?php

namespace App\Http\Controllers;

use App\Models\Tarifa;
use App\Models\Vehiculo;
use App\Services\PlacaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehiculoController extends Controller
{
    public function index(Request $request)
    {
        $q = Vehiculo::query()->withCount('pasos')->orderBy('placa');
        if ($b = $request->input('buscar')) {
            $limpio = PlacaService::limpiar($b);
            $q->where(fn ($w) => $w->where('placa', 'like', "%{$limpio}%")->orWhere('propietario', 'like', "%{$b}%"));
        }
        if ($c = $request->input('categoria')) {
            $q->where('categoria', $c);
        }

        return view('vehiculos.index', ['vehiculos' => $q->paginate(15)->withQueryString(), 'categorias' => Tarifa::NOMBRES]);
    }

    public function create()
    {
        return view('vehiculos.form', ['vehiculo' => new Vehiculo(['ejes' => 2, 'activo' => true, 'saldo' => 0]), 'categorias' => Tarifa::NOMBRES]);
    }

    public function store(Request $request)
    {
        Vehiculo::create($this->validar($request));

        return redirect()->route('vehiculos.index')->with('ok', 'Vehículo registrado.');
    }

    public function edit(Vehiculo $vehiculo)
    {
        return view('vehiculos.form', ['vehiculo' => $vehiculo, 'categorias' => Tarifa::NOMBRES]);
    }

    public function update(Request $request, Vehiculo $vehiculo)
    {
        $vehiculo->update($this->validar($request, $vehiculo));

        return redirect()->route('vehiculos.index')->with('ok', 'Vehículo actualizado.');
    }

    public function destroy(Vehiculo $vehiculo)
    {
        $vehiculo->delete();

        return redirect()->route('vehiculos.index')->with('ok', 'Vehículo eliminado (sus pasos se conservan como historial).');
    }

    private function validar(Request $request, ?Vehiculo $v = null): array
    {
        $request->merge(['placa' => PlacaService::limpiar($request->input('placa'))]);
        $datos = $request->validate([
            'placa' => ['required', 'string', 'min:5', 'max:8', Rule::unique('vehiculos', 'placa')->ignore($v?->id),
                function ($attr, $valor, $fail) {
                    if (! PlacaService::esValida($valor)) {
                        $fail('La placa no tiene formato boliviano (p. ej. 1234ABC o 852KLM).');
                    }
                }],
            'propietario' => 'required|string|max:120',
            'categoria' => ['required', Rule::in(Tarifa::CATEGORIAS)],
            'ejes' => 'required|integer|min:2|max:9',
            'saldo' => 'required|numeric|min:0|max:999999',
        ]);
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
