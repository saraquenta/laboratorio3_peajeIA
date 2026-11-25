<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Http;

/**
 * Verifica tokens de Google Sign-In enviados por la app movil y aplica la lista blanca.
 *  - id_token (recomendado): se valida con el endpoint tokeninfo de Google (firma, expiracion, aud, iss).
 *  - access_token: se consulta userinfo de Google.
 */
class GoogleAuthService
{
    /** @return array{email:string, nombre:?string, google_id:?string, avatar:?string} */
    public function verificar(?string $idToken, ?string $accessToken): array
    {
        if ($idToken) {
            $r = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
            if (! $r->successful()) {
                throw new \RuntimeException('id_token de Google inválido o expirado');
            }
            $d = $r->json();
            $permitidos = config('peaje.google_client_ids');
            if ($permitidos && ! in_array($d['aud'] ?? '', $permitidos, true)) {
                throw new \RuntimeException('El token no fue emitido para este sistema (aud)');
            }
            if (! in_array($d['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
                throw new \RuntimeException('Emisor del token inválido');
            }
        } elseif ($accessToken) {
            $r = Http::timeout(10)->withToken($accessToken)->get('https://openidconnect.googleapis.com/v1/userinfo');
            if (! $r->successful()) {
                throw new \RuntimeException('access_token de Google inválido');
            }
            $d = $r->json();
        } else {
            throw new \InvalidArgumentException('Envíe id_token (o access_token) de Google');
        }

        $verificado = filter_var($d['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (empty($d['email']) || ! $verificado) {
            throw new \RuntimeException('La cuenta de Google no tiene un correo verificado');
        }

        return ['email' => mb_strtolower($d['email']), 'nombre' => $d['name'] ?? null,
            'google_id' => $d['sub'] ?? null, 'avatar' => $d['picture'] ?? null];
    }

    /** Devuelve el usuario autorizado (lista blanca + activo) o null. Actualiza sus datos de perfil. */
    public static function autorizar(array $perfil): ?Usuario
    {
        $u = Usuario::autorizado($perfil['email']);
        if (! $u) {
            return null;
        }
        $u->forceFill(array_filter([
            'nombre' => $u->nombre ?: $perfil['nombre'],
            'google_id' => $perfil['google_id'],
            'avatar_url' => $perfil['avatar'],
        ]) + ['ultimo_acceso' => now()])->save();

        return $u;
    }
}
