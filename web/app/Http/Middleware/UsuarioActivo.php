<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Si un administrador desactiva a un usuario de la lista blanca, pierde el acceso de inmediato. */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && ! $u->activo) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $u->currentAccessToken()?->delete();

                return response()->json(['message' => 'Acceso denegado: usuario desactivado'], 403);
            }
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->with('error', 'Acceso denegado: tu usuario fue desactivado.');
        }

        return $next($request);
    }
}
