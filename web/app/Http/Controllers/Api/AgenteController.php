<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AgenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgenteController extends Controller
{
    /** POST /api/v1/agente/chat {mensaje} y/o imagen (multipart) */
    public function chat(Request $request, AgenteService $agente): JsonResponse
    {
        $request->validate(['mensaje' => 'nullable|string|max:1000',
            'imagen' => 'nullable|file|mimes:jpg,jpeg,png|max:8192']);
        if (! $request->filled('mensaje') && ! $request->hasFile('imagen')) {
            return response()->json(['message' => 'Envíe un mensaje o una imagen'], 422);
        }

        return response()->json($agente->responder($request->input('mensaje'), $request->file('imagen')));
    }
}
