<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Protege el endpoint del ESP32 con un token de dispositivo (HARDWARE_TOKEN), no con cuenta de usuario. */
class TokenHardware
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('peaje.hardware.token');
        $recibido = (string) ($request->header('X-Hardware-Token') ?? $request->query('token', ''));
        if ($esperado === '' || ! hash_equals($esperado, $recibido)) {
            return response()->json(['message' => 'Token de hardware inválido'], 401);
        }

        return $next($request);
    }
}
