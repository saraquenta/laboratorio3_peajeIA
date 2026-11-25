<?php

namespace App\Services;

use App\Models\Paso;
use App\Models\Vehiculo;

/**
 * Normalizacion de placas de Bolivia (misma logica que inferencia/app/normalizacion.py).
 *
 * Vigente: 3-4 digitos + 3 letras (1234ABC, 852KLM). Antiguo: 2-3 letras + 3-4 digitos (LPZ123).
 * Corrige por posicion las confusiones tipicas del OCR: O<->0, I<->1, B<->8, S<->5, Z<->2, G<->6.
 */
class PlacaService
{
    private const LETRA_A_DIGITO = ['O' => '0', 'Q' => '0', 'D' => '0', 'I' => '1', 'L' => '1', 'T' => '1',
        'Z' => '2', 'B' => '8', 'S' => '5', 'G' => '6', 'A' => '4'];

    private const DIGITO_A_LETRA = ['0' => 'O', '1' => 'I', '2' => 'Z', '8' => 'B', '3' => 'B', '5' => 'S', '6' => 'G',
        '4' => 'A', '7' => 'T'];

    /** El formato antiguo solo se acepta con pocas correcciones (evita convertir texto cualquiera en placa). */
    private const MAX_CORRECCIONES_ANTIGUO = 2;

    /** Confusiones tipicas del OCR (un caracter puede confundirse con varios: 8 <-> B y 8 <-> 3). */
    private const PARES_OCR = ['O' => ['0'], '0' => ['O', 'D'], 'I' => ['1'], '1' => ['I', '7'], 'B' => ['8'], '8' => ['B', '3'],
        '3' => ['8'], 'S' => ['5'], '5' => ['S'], 'Z' => ['2'], '2' => ['Z'], 'G' => ['6'], '6' => ['G'], 'D' => ['0'], 'Q' => ['0'],
        '7' => ['1']];

    public const RE_VIGENTE = '/^\d{3,4}[A-Z]{3}$/';

    public const RE_ANTIGUA = '/^[A-Z]{2,3}\d{3,4}$/';

    public static function limpiar(?string $texto): string
    {
        $t = mb_strtoupper((string) $texto);
        $t = str_replace(['BOLIVIA', 'Ñ'], ['', 'N'], $t);

        return preg_replace('/[^A-Z0-9]/', '', $t) ?? '';
    }

    public static function esValida(string $placa): bool
    {
        return (bool) (preg_match(self::RE_VIGENTE, $placa) || preg_match(self::RE_ANTIGUA, $placa));
    }

    /** @return array{normalizada:string, valida:bool, correcciones:int, formato:string} */
    public static function normalizar(?string $texto): array
    {
        $t = self::limpiar($texto);
        if (preg_match(self::RE_VIGENTE, $t)) {
            return ['normalizada' => $t, 'valida' => true, 'correcciones' => 0, 'formato' => 'vigente'];
        }
        if (preg_match(self::RE_ANTIGUA, $t)) {
            return ['normalizada' => $t, 'valida' => true, 'correcciones' => 0, 'formato' => 'antiguo'];
        }
        $len = strlen($t);
        if ($len === 6 || $len === 7) {
            [$c, $n] = self::corregir($t, $len - 3, true);
            if (preg_match(self::RE_VIGENTE, $c)) {
                return ['normalizada' => $c, 'valida' => true, 'correcciones' => $n, 'formato' => 'vigente'];
            }
        }
        foreach ([3, 2] as $letras) {
            $dig = $len - $letras;
            if ($dig >= 3 && $dig <= 4) {
                [$c, $n] = self::corregir($t, $dig, false);
                if (preg_match(self::RE_ANTIGUA, $c) && $n <= self::MAX_CORRECCIONES_ANTIGUO) {
                    return ['normalizada' => $c, 'valida' => true, 'correcciones' => $n, 'formato' => 'antiguo'];
                }
            }
        }

        return ['normalizada' => $t, 'valida' => false, 'correcciones' => 0, 'formato' => 'desconocido'];
    }

    private static function corregir(string $t, int $nDig, bool $digitosPrimero): array
    {
        $out = '';
        $cambios = 0;
        $len = strlen($t);
        for ($i = 0; $i < $len; $i++) {
            $ch = $t[$i];
            $enNum = $digitosPrimero ? $i < $nDig : $i >= $len - $nDig;
            $nuevo = $ch;
            if ($enNum && ! ctype_digit($ch)) {
                $nuevo = self::LETRA_A_DIGITO[$ch] ?? $ch;
            } elseif (! $enNum && ctype_digit($ch)) {
                $nuevo = self::DIGITO_A_LETRA[$ch] ?? $ch;
            }
            $cambios += $nuevo !== $ch ? 1 : 0;
            $out .= $nuevo;
        }

        return [$out, $cambios];
    }

    /** Variantes intercambiando caracteres que el OCR confunde. */
    public static function variantes(string $placa, int $max = 64): array
    {
        $t = self::limpiar($placa);
        $vars = [$t => true];
        for ($i = 0; $i < strlen($t); $i++) {
            $ch = $t[$i];
            if (! isset(self::PARES_OCR[$ch])) {
                continue;
            }
            foreach (array_keys($vars) as $v) {
                foreach (self::PARES_OCR[$ch] as $alt) {
                    $vars[substr($v, 0, $i).$alt.substr($v, $i + 1)] = true;
                    if (count($vars) >= $max) {
                        break 3;
                    }
                }
            }
        }
        unset($vars[$t]);

        return array_keys($vars);
    }

    /**
     * Sugerencias "¿Quisiste decir...?": primero variantes por confusiones de OCR que existan en la BD
     * (vehiculos o pasos) y luego placas registradas a distancia de edicion 1.
     */
    public static function sugerencias(string $placa, int $limite = 3): array
    {
        $t = self::limpiar($placa);
        if ($t === '') {
            return [];
        }
        $candidatas = array_unique(array_merge([self::normalizar($t)['normalizada']], self::variantes($t)));
        $candidatas = array_values(array_diff($candidatas, [$t]));
        $encontradas = Vehiculo::whereIn('placa', $candidatas)->pluck('placa')->all();
        $encontradas = array_merge($encontradas, Paso::whereIn('placa', $candidatas)->distinct()->pluck('placa')->all());

        if (count($encontradas) < $limite) {
            // Placas registradas a distancia de edicion <= 2, de la mas parecida a la menos parecida
            $cercanas = [];
            foreach (Vehiculo::query()->pluck('placa') as $p) {
                $d = abs(strlen($p) - strlen($t)) <= 2 ? levenshtein($p, $t) : 99;
                if ($p !== $t && $d >= 1 && $d <= 2) {
                    $cercanas[$p] = $d;
                }
            }
            asort($cercanas);
            $encontradas = array_merge($encontradas, array_keys($cercanas));
        }

        return array_slice(array_values(array_unique($encontradas)), 0, $limite);
    }

    /** Extrae posibles placas de un texto libre ("consulta la placa 1234-abc por favor"). */
    public static function extraerDeTexto(string $texto): array
    {
        $t = mb_strtoupper($texto);
        // 1) Formato vigente escrito por una persona (1234ABC, 1234-abc, 852 KLM): tiene prioridad
        preg_match_all('/(?<![A-Z0-9])(\d{3,4})[\s\-.]?([A-Z]{3})(?![A-Z0-9])/u', $t, $v, PREG_SET_ORDER);
        if ($v) {
            return array_values(array_unique(array_map(fn ($g) => $g[1].$g[2], $v)));
        }
        // 2) Cualquier mezcla de letras y digitos (incluye errores de OCR como 1234A8C)
        preg_match_all('/\b([A-Z0-9]{2,4})[\s\-.]?([A-Z0-9]{3,4})\b/u', $t, $m, PREG_SET_ORDER);
        $res = [];
        foreach ($m as $g) {
            $cand = $g[1].$g[2];
            // debe mezclar letras y digitos para no confundir palabras normales con placas
            if (preg_match('/\d/', $cand) && preg_match('/[A-Z]/', $cand) && strlen($cand) >= 5 && strlen($cand) <= 8) {
                $res[] = $cand;
            }
        }

        return array_values(array_unique($res));
    }
}
