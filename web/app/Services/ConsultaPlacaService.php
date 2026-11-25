<?php

namespace App\Services;

use App\Models\Paso;
use App\Models\Vehiculo;

/**
 * Resuelve una placa contra la BD y devuelve exactamente uno de los 4 estados del agente:
 *   1. "Registrada y con paso"        (fecha, caseta, categoria y monto del ultimo paso)
 *   2. "Registrada sin paso"
 *   3. "Identificada no registrada"   (vista por una caseta pero sin registro: posible evasion)
 *   4. "No existe"
 */
class ConsultaPlacaService
{
    public const REGISTRADA_CON_PASO = 'Registrada y con paso';
    public const REGISTRADA_SIN_PASO = 'Registrada sin paso';
    public const IDENTIFICADA_NO_REGISTRADA = 'Identificada no registrada';
    public const NO_EXISTE = 'No existe';

    public function consultar(string $texto): array
    {
        $norm = PlacaService::normalizar($texto);
        $placa = $norm['normalizada'];
        $vehiculo = $placa !== '' ? Vehiculo::where('placa', $placa)->first() : null;
        $pasos = $placa !== '' ? Paso::with('caseta')->where('placa', $placa)->latest('id') : null;
        $ultimo = $pasos?->first();
        $total = $pasos ? (clone $pasos)->count() : 0;

        if ($vehiculo && $ultimo) {
            $estado = self::REGISTRADA_CON_PASO;
        } elseif ($vehiculo) {
            $estado = self::REGISTRADA_SIN_PASO;
        } elseif ($ultimo) {
            $estado = self::IDENTIFICADA_NO_REGISTRADA;
        } else {
            $estado = self::NO_EXISTE;
        }

        $sugerencias = ($estado === self::NO_EXISTE || $estado === self::IDENTIFICADA_NO_REGISTRADA)
            ? PlacaService::sugerencias($placa) : [];

        return [
            'placa_consultada' => $texto,
            'placa' => $placa,
            'placa_valida' => $norm['valida'],
            'estado' => $estado,
            'vehiculo' => $vehiculo ? [
                'placa' => $vehiculo->placa, 'propietario' => $vehiculo->propietario, 'categoria' => $vehiculo->categoria,
                'ejes' => $vehiculo->ejes, 'saldo' => (float) $vehiculo->saldo, 'activo' => $vehiculo->activo,
            ] : null,
            'ultimo_paso' => $ultimo ? [
                'fecha' => $ultimo->created_at?->toIso8601String(),
                'fecha_texto' => $ultimo->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'caseta' => $ultimo->caseta?->nombre, 'categoria' => $ultimo->categoria,
                'monto' => (float) $ultimo->monto, 'estado' => $ultimo->estado, 'imagen_url' => $ultimo->imagen_url,
            ] : null,
            'total_pasos' => $total,
            'posible_evasion' => $estado === self::IDENTIFICADA_NO_REGISTRADA,
            'sugerencias' => $sugerencias,
        ];
    }

    /** Respuesta en texto (sin LLM), siempre con datos reales. */
    public static function texto(array $r): string
    {
        $p = $r['placa'];
        $msg = match ($r['estado']) {
            self::REGISTRADA_CON_PASO => sprintf('**%s** — La placa %s pertenece a %s (%s). Último paso: %s en %s, categoría %s, monto Bs %s. Total de pasos: %d. Saldo actual: Bs %s.',
                $r['estado'], $p, $r['vehiculo']['propietario'], $r['vehiculo']['categoria'], $r['ultimo_paso']['fecha_texto'],
                $r['ultimo_paso']['caseta'], $r['ultimo_paso']['categoria'], number_format($r['ultimo_paso']['monto'], 2),
                $r['total_pasos'], number_format($r['vehiculo']['saldo'], 2)),
            self::REGISTRADA_SIN_PASO => sprintf('**%s** — La placa %s está registrada a nombre de %s (%s, %d ejes, saldo Bs %s), pero aún no tiene pasos.',
                $r['estado'], $p, $r['vehiculo']['propietario'], $r['vehiculo']['categoria'], $r['vehiculo']['ejes'],
                number_format($r['vehiculo']['saldo'], 2)),
            self::IDENTIFICADA_NO_REGISTRADA => sprintf('**%s** — La placa %s fue identificada %d %s (última: %s en %s) pero no está registrada: posible evasión.',
                $r['estado'], $p, $r['total_pasos'], $r['total_pasos'] === 1 ? 'vez' : 'veces', $r['ultimo_paso']['fecha_texto'], $r['ultimo_paso']['caseta']),
            default => sprintf('**%s** — No hay registros ni pasos de la placa %s.', $r['estado'], $p),
        };
        if (! $r['placa_valida'] && $r['estado'] === self::NO_EXISTE) {
            $msg .= ' El texto no tiene formato de placa boliviana (p. ej. 1234ABC).';
        }
        if ($r['sugerencias']) {
            $msg .= ' ¿Quisiste decir '.implode(' o ', $r['sugerencias']).'?';
        }

        return $msg;
    }
}
