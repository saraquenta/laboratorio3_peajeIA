<?php

namespace App\Http\Controllers;

use App\Models\Caseta;
use App\Models\Tarifa;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Gestion exclusiva del administrador: tarifas, casetas y lista blanca de usuarios. */
class AdminController extends Controller
{
    public function tarifas()
    {
        return view('admin.tarifas', ['tarifas' => Tarifa::orderByRaw("CASE categoria WHEN 'moto' THEN 1 WHEN 'auto' THEN 2 WHEN 'minibus' THEN 3 WHEN 'bus' THEN 4 ELSE 5 END")->get()]);
    }

    public function guardarTarifa(Request $request, Tarifa $tarifa)
    {
        $tarifa->update($request->validate(['monto_base' => 'required|numeric|min:0', 'ejes_incluidos' => 'required|integer|min:2|max:9',
            'monto_eje_adicional' => 'required|numeric|min:0']));

        return back()->with('ok', 'Tarifa de '.Tarifa::NOMBRES[$tarifa->categoria].' actualizada.');
    }

    public function casetas()
    {
        return view('admin.casetas', ['casetas' => Caseta::withCount('pasos')->orderBy('id')->get()]);
    }

    public function guardarCaseta(Request $request, ?Caseta $caseta = null)
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('casetas', 'codigo')->ignore($caseta?->id)],
            'nombre' => 'required|string|max:120', 'ubicacion' => 'nullable|string|max:200',
        ]);
        $datos['activa'] = $request->boolean('activa');
        $caseta && $caseta->exists ? $caseta->update($datos) : Caseta::create($datos);

        return back()->with('ok', 'Caseta guardada.');
    }

    public function usuarios()
    {
        return view('admin.usuarios', ['usuarios' => Usuario::orderBy('rol')->orderBy('email_google')->get()]);
    }

    public function guardarUsuario(Request $request, ?Usuario $usuario = null)
    {
        $request->merge(['email_google' => mb_strtolower(trim((string) $request->input('email_google')))]);
        $datos = $request->validate([
            'email_google' => ['required', 'email', Rule::unique('usuarios', 'email_google')->ignore($usuario?->id)],
            'nombre' => 'nullable|string|max:120',
            'rol' => ['required', Rule::in([Usuario::ADMIN, Usuario::OPERADOR])],
        ]);
        $datos['activo'] = $request->boolean('activo');
        if ($usuario && $usuario->exists && $usuario->id === $request->user()->id && (! $datos['activo'] || $datos['rol'] !== Usuario::ADMIN)) {
            return back()->with('error', 'No puedes quitarte el rol de administrador ni desactivarte a ti mismo.');
        }
        $usuario && $usuario->exists ? $usuario->update($datos) : Usuario::create($datos);

        return back()->with('ok', 'Lista blanca actualizada.');
    }
}
