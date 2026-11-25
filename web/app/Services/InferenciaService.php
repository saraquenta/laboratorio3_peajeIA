<?php

namespace App\Services;

use App\Models\Configuracion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente del endpoint unificado de inferencia (RunPod o Colab).
 * La URL se configura con INFERENCE_URL (.env) o desde la pagina Estado (tabla configuraciones),
 * asi se puede cambiar de RunPod a Colab sin modificar el codigo.
 */
class InferenciaService
{
    public function url(): string
    {
        return rtrim((string) Configuracion::obtener('INFERENCE_URL', config('peaje.inference.url')), '/');
    }

    public function token(): string
    {
        return (string) Configuracion::obtener('INFERENCE_TOKEN', config('peaje.inference.token'));
    }

    /** @return array{en_linea:bool, url:string, latencia_ms:?float, detalle:mixed} */
    public function estado(): array
    {
        $url = $this->url();
        if ($url === '') {
            return ['en_linea' => false, 'url' => '', 'latencia_ms' => null, 'detalle' => 'INFERENCE_URL no configurada'];
        }
        $t0 = microtime(true);
        try {
            $r = Http::timeout(8)->acceptJson()->get($url.'/health');
            $ms = round((microtime(true) - $t0) * 1000, 1);

            return ['en_linea' => $r->successful() && ($r->json('estado') === 'ok'), 'url' => $url,
                'latencia_ms' => $ms, 'detalle' => $r->json() ?? $r->body()];
        } catch (\Throwable $e) {
            return ['en_linea' => false, 'url' => $url, 'latencia_ms' => null, 'detalle' => $e->getMessage()];
        }
    }

    /**
     * Envia la imagen y devuelve el JSON de inferencia
     * (placa, placa_normalizada, confianza_ocr, categoria, ejes, pasajeros, latencia_ms...).
     */
    public function predecir(UploadedFile|string $imagen, bool $anotar = false): array
    {
        $url = $this->url();
        if ($url === '') {
            throw new RuntimeException('INFERENCE_URL no configurada');
        }
        $bytes = $imagen instanceof UploadedFile ? file_get_contents($imagen->getRealPath()) : $imagen;

        $r = Http::timeout((int) config('peaje.inference.timeout', 30))
            // X-Anotar: el servidor devuelve tambien imagen_anotada_base64 (evidencia con mascara, placa y keypoints)
            ->withHeaders(['X-API-Token' => $this->token()] + ($anotar ? ['X-Anotar' => '1'] : []))
            ->withBody($bytes, 'image/jpeg')
            ->post($url.'/predict');

        if (! $r->successful()) {
            throw new RuntimeException('Inferencia respondio '.$r->status().': '.mb_substr($r->body(), 0, 200));
        }

        return $r->json() ?? [];
    }
}
