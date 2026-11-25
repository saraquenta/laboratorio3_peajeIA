<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /** POST /api/v1/auth/google  {id_token} o {access_token}  -> token Bearer (Sanctum) */
    public function google(Request $request, GoogleAuthService $google): JsonResponse
    {
        $request->validate(['id_token' => 'nullable|string', 'access_token' => 'nullable|string',
            'dispositivo' => 'nullable|string|max:100']);
        try {
            $perfil = $google->verificar($request->input('id_token'), $request->input('access_token'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        $usuario = GoogleAuthService::autorizar($perfil);
        if (! $usuario) {
            return response()->json(['message' => 'Acceso denegado: el correo '.$perfil['email'].' no está autorizado en la lista blanca.',
                'email' => $perfil['email']], 403);
        }
        $token = $usuario->createToken($request->input('dispositivo', 'app-movil'))->plainTextToken;

        return response()->json(['token' => $token, 'tipo' => 'Bearer', 'usuario' => $this->usuario($usuario)]);
    }

    public function yo(Request $request): JsonResponse
    {
        return response()->json(['usuario' => $this->usuario($request->user())]);
    }

    public function salir(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    private function usuario($u): array
    {
        return ['id' => $u->id, 'nombre' => $u->nombre, 'email' => $u->email_google, 'rol' => $u->rol, 'avatar_url' => $u->avatar_url];
    }
}
