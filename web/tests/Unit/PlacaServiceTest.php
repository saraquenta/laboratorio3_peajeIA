<?php

namespace Tests\Unit;

use App\Services\PlacaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlacaServiceTest extends TestCase
{
    public static function casos(): array
    {
        return [
            ['1234-ABC', '1234ABC'], ['1234 abc', '1234ABC'], ['852-KLM', '852KLM'], ['BOLIVIA 2345 BCD', '2345BCD'],
            ['I234ABC', '1234ABC'], ['12O4ABC', '1204ABC'], ['1234A8C', '1234ABC'], ['S678XYZ', '5678XYZ'], ['LPZ-123', 'LPZ123'],
        ];
    }

    #[DataProvider('casos')]
    public function test_normaliza_y_corrige_ocr(string $entrada, string $esperada): void
    {
        $r = PlacaService::normalizar($entrada);
        $this->assertTrue($r['valida']);
        $this->assertSame($esperada, $r['normalizada']);
    }

    public function test_invalidas(): void
    {
        foreach (['', 'HOLA', '12', 'ABCDEFGHIJ'] as $t) {
            $this->assertFalse(PlacaService::normalizar($t)['valida'], $t);
        }
    }

    public function test_extrae_placas_de_texto_libre(): void
    {
        $this->assertSame(['1234ABC'], PlacaService::extraerDeTexto('¿me consultas la placa 1234-abc por favor?'));
        $this->assertSame([], PlacaService::extraerDeTexto('hola, cuanto se recaudo hoy'));
    }

    public function test_variantes_ocr(): void
    {
        $v = PlacaService::variantes('1234AB0');
        $this->assertContains('1234ABO', $v);
    }
}
