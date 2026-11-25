<?php

namespace App\Http\Controllers;

use App\Services\AgenteService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    /** Usado por la pagina de chat y por la burbuja flotante (sesion web autenticada con Google). */
    public function mensaje(Request $request, AgenteService $agente)
    {
        $request->validate(['mensaje' => 'nullable|string|max:1000', 'imagen' => 'nullable|file|mimes:jpg,jpeg,png|max:8192']);
        if (! $request->filled('mensaje') && ! $request->hasFile('imagen')) {
            return response()->json(['message' => 'Escribe un mensaje o adjunta una foto'], 422);
        }

        return response()->json($agente->responder($request->input('mensaje'), $request->file('imagen')));
    }
}
