<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Paso;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Agente conversacional del peaje.
 *
 * Regla de oro: los DATOS salen siempre de la BD (ConsultaPlacaService / consultas Eloquent), nunca del LLM.
 *  1. El codigo entiende la intencion (placa en texto, foto de placa, resumen del dia, alertas).
 *  2. El codigo resuelve el estado y los datos reales.
 *  3. Opcional: un LLM solo REDACTA la respuesta en lenguaje natural usando exclusivamente esos datos
 *     (una sola llamada, sin herramientas). AGENTE_PROVEEDOR=gemini (GEMINI_API_KEY) o anthropic
 *     (ANTHROPIC_API_KEY). Si no hay clave o falla, se usa la plantilla determinista, que ya es completa.
 */
class AgenteService
{
    public function __construct(
        private ConsultaPlacaService $consultas,
        private InferenciaService $inferencia,
    ) {}

    public function responder(?string $mensaje, ?UploadedFile $imagen = null): array
    {
        $mensaje = trim((string) $mensaje);

        if ($imagen) {
            try {
                $det = $this->inferencia->predecir($imagen);
            } catch (\Throwable $e) {
                return $this->salida('No pude analizar la imagen porque el servicio de inferencia no responde. Intenta de nuevo o escribe la placa.',
                    null, ['error' => 'inferencia_no_disponible'], $mensaje);
            }
            $norm = PlacaService::normalizar($det['placa_normalizada'] ?? $det['placa'] ?? '');
            if (! $norm['valida'] || (float) ($det['confianza_ocr'] ?? 0) < (float) config('peaje.confianza_minima_ocr')) {
                $leido = $det['placa_normalizada'] ?? $det['placa'] ?? null;

                return $this->salida('No logro leer una placa legible en la imagen'.($leido ? " (leí «{$leido}» con poca confianza)" : '')
                    .'. Por favor envía otra foto más cercana y de frente a la placa, o escríbela.', null,
                    ['deteccion' => $det, 'solicitar_imagen' => true], $mensaje);
            }
            $r = $this->consultas->consultar($norm['normalizada']);
            $r['deteccion'] = array_intersect_key($det, array_flip(['placa', 'placa_normalizada', 'confianza_ocr', 'categoria', 'ejes', 'pasajeros']));

            return $this->salida(ConsultaPlacaService::texto($r), $r['estado'], $r, $mensaje);
        }

        if ($mensaje === '') {
            return $this->salida('Escríbeme una placa (por ejemplo 1234ABC) o envía una foto de la placa.', null, [], $mensaje);
        }

        $placas = PlacaService::extraerDeTexto($mensaje);
        if ($placas) {
            $resultados = array_map(fn ($p) => $this->consultas->consultar($p), array_slice($placas, 0, 3));
            $texto = implode("\n\n", array_map([ConsultaPlacaService::class, 'texto'], $resultados));

            return $this->salida($texto, $resultados[0]['estado'], count($resultados) === 1 ? $resultados[0] : ['resultados' => $resultados], $mensaje);
        }

        $m = mb_strtolower($mensaje);
        if (preg_match('/recaud|resumen|cu[aá]nto|ingres|hoy|total/u', $m)) {
            $hoy = Paso::whereDate('created_at', today());
            $datos = ['fecha' => today()->format('d/m/Y'), 'pasos_hoy' => (clone $hoy)->count(),
                'recaudado_hoy' => (float) (clone $hoy)->where('estado', 'cobrado')->sum('monto'),
                'alertas_pendientes' => Alerta::where('estado', 'pendiente')->count()];

            return $this->salida(sprintf('Hoy (%s) se registraron %d pasos y se recaudaron Bs %s. Hay %d alertas pendientes.',
                $datos['fecha'], $datos['pasos_hoy'], number_format($datos['recaudado_hoy'], 2), $datos['alertas_pendientes']), null, $datos, $mensaje);
        }
        if (preg_match('/alerta|evasi/u', $m)) {
            $alertas = Alerta::with('caseta')->where('estado', 'pendiente')->latest('id')->limit(5)->get();
            $datos = ['alertas_pendientes' => Alerta::where('estado', 'pendiente')->count(),
                'ultimas' => $alertas->map->resumen()->all()];
            $lista = $alertas->map(fn ($a) => "• {$a->placa} — ".(Alerta::TIPOS[$a->tipo] ?? $a->tipo).' ('.$a->created_at->format('d/m H:i').')')->implode("\n");

            return $this->salida("Hay {$datos['alertas_pendientes']} alertas pendientes.".($lista ? "\nÚltimas:\n".$lista : ''), null, $datos, $mensaje);
        }

        // Texto con algo parecido a una placa pero mal formado: se sugieren coincidencias cercanas
        $limpio = PlacaService::limpiar($mensaje);
        if (strlen($limpio) >= 5 && strlen($limpio) <= 8 && preg_match('/\d/', $limpio)) {
            $r = $this->consultas->consultar($limpio);

            return $this->salida(ConsultaPlacaService::texto($r), $r['estado'], $r, $mensaje);
        }

        return $this->salida('Puedo consultar placas (escribe por ejemplo «1234ABC» o envía una foto), darte el resumen de recaudación de hoy o listar las alertas pendientes. ¿Qué placa quieres consultar?',
            null, [], $mensaje);
    }

    private function salida(string $plantilla, ?string $estado, array $datos, string $mensaje): array
    {
        $respuesta = $plantilla;
        $fuente = 'plantilla';
        $proveedor = (string) config('peaje.agente.proveedor');
        if ($mensaje !== '' && $datos !== []) {
            $redactada = match (true) {
                $proveedor === 'gemini' && filled(config('peaje.agente.gemini_api_key')) => $this->redactarConGemini($mensaje, $estado, $datos, $plantilla),
                $proveedor === 'anthropic' && filled(config('peaje.agente.anthropic_api_key')) => $this->redactarConClaude($mensaje, $estado, $datos, $plantilla),
                default => null,
            };
            if ($redactada) {
                $respuesta = $redactada;
                $fuente = 'llm:'.($proveedor === 'anthropic' ? 'claude' : 'gemini');
            }
        }

        return ['respuesta' => $respuesta, 'estado' => $estado, 'datos' => $datos,
            'sugerencias' => $datos['sugerencias'] ?? [], 'fuente' => $fuente];
    }

    private const SISTEMA = 'Eres el asistente del Sistema Inteligente de Control de Peaje (Bolivia). Respondes en español, en 1 a 4 frases. '
        .'Usa EXCLUSIVAMENTE los datos JSON que te da el sistema: no inventes placas, montos, fechas, casetas ni personas. '
        .'Si el campo "estado" no es nulo, la respuesta debe empezar exactamente con ese estado en negrita (**estado**). '
        .'Si hay "sugerencias", pregunta "¿Quisiste decir ...?" con ellas. Si faltan datos para responder, dilo.';

    private function contenidoUsuario(string $mensaje, ?string $estado, array $datos, string $plantilla): string
    {
        return "Pregunta del operador: {$mensaje}\n\nEstado: ".($estado ?? 'null')
            ."\nDatos reales de la base de datos (JSON):\n".json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ."\n\nRespuesta de referencia (correcta, puedes reformularla):\n{$plantilla}";
    }

    private function asegurarEstado(string $texto, ?string $estado): string
    {
        $texto = trim($texto);

        return ($estado && ! str_contains($texto, $estado)) ? "**{$estado}** — ".$texto : $texto;
    }

    /** Gemini (Google AI Studio) por HTTP: una llamada para redactar con los datos dados. Null si algo falla. */
    private function redactarConGemini(string $mensaje, ?string $estado, array $datos, string $plantilla): ?string
    {
        try {
            $modelo = (string) config('peaje.agente.gemini_modelo');
            $r = Http::timeout(20)
                ->withHeaders(['x-goog-api-key' => (string) config('peaje.agente.gemini_api_key')])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => self::SISTEMA]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $this->contenidoUsuario($mensaje, $estado, $datos, $plantilla)]]]],
                    'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 1024],
                ]);
            if (! $r->successful()) {
                Log::warning('Agente: Gemini respondio '.$r->status().', se usa plantilla.');

                return null;
            }
            $texto = collect($r->json('candidates.0.content.parts', []))
                ->filter(fn ($p) => isset($p['text']) && empty($p['thought']))->pluck('text')->implode('');

            return $texto !== '' ? $this->asegurarEstado($texto, $estado) : null;
        } catch (\Throwable $e) {
            Log::warning('Agente: Gemini no disponible, se usa plantilla. '.$e->getMessage());

            return null;
        }
    }

    /** Una sola llamada a Claude para redactar con los datos dados. Devuelve null si algo falla. */
    private function redactarConClaude(string $mensaje, ?string $estado, array $datos, string $plantilla): ?string
    {
        try {
            $client = new \Anthropic\Client(apiKey: (string) config('peaje.agente.anthropic_api_key'));
            // Beta messages solo para activar el respaldo del lado del servidor si el modelo declina (refusal)
            $msg = $client->beta->messages->create(
                model: (string) config('peaje.agente.modelo'),
                maxTokens: 1024,
                outputConfig: ['effort' => 'low'],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
                system: self::SISTEMA,
                messages: [['role' => 'user', 'content' => $this->contenidoUsuario($mensaje, $estado, $datos, $plantilla)]],
            );
            if ($msg->stopReason === 'refusal') {
                return null;
            }
            foreach ($msg->content as $block) {
                if ($block->type === 'text') {
                    return $this->asegurarEstado($block->text, $estado);   // el estado exacto siempre aparece
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Agente: Claude no disponible, se usa plantilla. '.$e->getMessage());
        }

        return null;
    }
}
