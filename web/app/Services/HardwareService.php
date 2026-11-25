<?php

namespace App\Services;

use App\Models\Paso;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integracion con el ESP32 (LED azul / verde / rojo + buzzer) sin abrir puertos ni depender de una PC:
 * el ESP32 consulta por HTTPS GET /api/v1/hardware/ultimo-evento cada ~1 s (X-Hardware-Token).
 *
 *   tipo_senal: idle | inferencia (azul) | ok (verde + 1 beep) | alerta (rojo + 3 beeps)
 *
 * Los eventos se guardan en la cache de Laravel (CACHE_STORE=database funciona en hosting compartido).
 * Opcional: HARDWARE_WEBHOOK_URL recibe un POST con cada evento (Node-RED, puente HTTP->MQTT, etc.).
 */
class HardwareService
{
    private const TTL = 86400;

    /** Senal compacta del ultimo paso (endpoint simple /hardware/estado, compatible con hardware/semaforo.py). */
    public static function senal(?Paso $paso): array
    {
        if (! $paso) {
            return ['evento_id' => 0, 'led' => 'apagado', 'buzzer' => false, 'texto' => 'Sin eventos'];
        }
        $ok = $paso->estado === 'cobrado';

        return [
            'evento_id' => $paso->id,
            'led' => $ok ? 'verde' : 'rojo',
            'buzzer' => ! $ok,
            'placa' => $paso->placa,
            'estado' => $paso->estado,
            'monto' => (float) $paso->monto,
            'texto' => $ok ? 'Paso autorizado' : 'ALERTA: '.($paso->alertas()->first()?->tipo ?? 'revisar'),
            'fecha' => $paso->created_at?->toIso8601String(),
        ];
    }

    /** Registra un evento para el ESP32 de la caseta (y uno global). */
    public function registrar(string $tipo, int $casetaId, ?string $placa = null, float $monto = 0, string $texto = ''): array
    {
        $id = (int) Cache::get('hw:contador', 0) + 1;
        Cache::put('hw:contador', $id, self::TTL * 30);
        $evento = ['id' => $id, 'tipo_senal' => $tipo, 'caseta_id' => $casetaId, 'placa' => $placa,
            'monto' => round($monto, 2), 'texto' => $texto, 'timestamp' => now()->toIso8601String()];
        Cache::put("hw:evento:{$casetaId}", $evento, self::TTL);
        Cache::put('hw:evento:todas', $evento, self::TTL);

        return $evento;
    }

    public function ultimoEvento(?int $casetaId): array
    {
        $e = Cache::get($casetaId ? "hw:evento:{$casetaId}" : 'hw:evento:todas');
        // Un evento "inferencia" de mas de 5 s ya no esta en curso: se informa idle para apagar el LED azul
        if (! $e || ($e['tipo_senal'] === 'inferencia' && now()->diffInSeconds($e['timestamp'], true) > 5)) {
            return ['id' => (int) ($e['id'] ?? 0), 'tipo_senal' => 'idle', 'caseta_id' => $casetaId, 'placa' => null,
                'monto' => 0, 'texto' => 'En espera', 'timestamp' => now()->toIso8601String()];
        }

        return $e;
    }

    /** El ESP32 informa su RSSI en cada consulta: se muestra en Estado del sistema. */
    public function marcarVisto(?int $casetaId, $rssi, ?string $ip): void
    {
        Cache::put('hw:visto', ['caseta_id' => $casetaId, 'rssi' => is_numeric($rssi) ? (int) $rssi : null, 'ip' => $ip,
            'fecha' => now()->toIso8601String()], self::TTL);
    }

    public function visto(): ?array
    {
        return Cache::get('hw:visto');
    }

    /** Evento final de un paso registrado (ok o alerta) + webhook opcional. */
    public function notificar(Paso $paso): void
    {
        $ok = $paso->estado === 'cobrado';
        $this->registrar($ok ? 'ok' : 'alerta', $paso->caseta_id, $paso->placa, (float) $paso->monto,
            $ok ? 'Paso autorizado' : 'ALERTA: '.($paso->alertas()->first()?->tipo ?? 'revisar'));

        $url = config('peaje.hardware.webhook_url');
        if (! $url) {
            return;
        }
        try {
            Http::timeout(2)->withHeaders(['X-Hardware-Token' => (string) config('peaje.hardware.token')])
                ->post($url, self::senal($paso) + ['caseta_id' => $paso->caseta_id]);
        } catch (\Throwable $e) {
            Log::info('Webhook de hardware no disponible: '.$e->getMessage());
        }
    }
}
