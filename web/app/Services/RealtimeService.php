<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * Tiempo real compatible con hosting compartido:
 *  - Con PUSHER_APP_KEY configurado publica en Pusher Channels (servicio gestionado: el navegador mantiene el WebSocket,
 *    el servidor PHP solo hace un POST HTTPS por evento). El canal es PRIVADO ("private-peaje"): Pusher pide permiso a
 *    /broadcasting/auth y solo se concede a usuarios con sesion iniciada (lista blanca de Google).
 *  - Sin Pusher el panel sondea /panel/datos cada 3 s, que funciona en cualquier hosting.
 */
class RealtimeService
{
    public const CANAL = 'private-peaje';

    public function habilitado(): bool
    {
        return filled(config('peaje.pusher.key')) && filled(config('peaje.pusher.secret')) && filled(config('peaje.pusher.app_id'));
    }

    private function cliente(): Pusher
    {
        return new Pusher(config('peaje.pusher.key'), config('peaje.pusher.secret'), config('peaje.pusher.app_id'),
            ['cluster' => config('peaje.pusher.cluster'), 'useTLS' => true, 'timeout' => 3]);
    }

    public function publicar(string $evento, array $datos): void
    {
        if (! $this->habilitado()) {
            return;
        }
        try {
            $this->cliente()->trigger(self::CANAL, $evento, $datos);
        } catch (\Throwable $e) {
            Log::warning('No se pudo publicar en Pusher: '.$e->getMessage());
        }
    }

    /** Firma la suscripcion al canal privado (respuesta JSON que espera pusher-js). */
    public function autorizar(string $canal, string $socketId): ?string
    {
        if (! $this->habilitado() || $canal !== self::CANAL) {
            return null;
        }

        return $this->cliente()->authorizeChannel($canal, $socketId);
    }
}
