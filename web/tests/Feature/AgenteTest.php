<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\ConsultaPlacaService as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgenteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(Usuario::where('email_google', 'operador.demo@gmail.com')->first());
    }

    public function test_los_cuatro_estados(): void
    {
        $this->postJson('/api/v1/agente/chat', ['mensaje' => 'consulta la placa 1234-abc'])->assertOk()
            ->assertJsonPath('estado', C::REGISTRADA_CON_PASO)->assertJsonPath('datos.ultimo_paso.caseta', 'Caseta Achica Arriba');
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '6802HKM'])->assertJsonPath('estado', C::REGISTRADA_SIN_PASO);
        $this->postJson('/api/v1/agente/chat', ['mensaje' => 'placa 9999ZZZ'])->assertJsonPath('estado', C::IDENTIFICADA_NO_REGISTRADA)
            ->assertJsonPath('datos.posible_evasion', true);
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '1111QQQ'])->assertJsonPath('estado', C::NO_EXISTE);
    }

    public function test_respuesta_contiene_el_estado_exacto_y_solo_datos_reales(): void
    {
        $r = $this->postJson('/api/v1/agente/chat', ['mensaje' => '1234ABC'])->json();
        $this->assertStringContainsString(C::REGISTRADA_CON_PASO, $r['respuesta']);
        $this->assertStringContainsString('Juan Carlos Mamani Quispe', $r['respuesta']);
        $this->assertSame('plantilla', $r['fuente']);
    }

    public function test_sugiere_quisiste_decir_por_errores_de_ocr(): void
    {
        // El OCR confundio la letra B con el 8: 1234A8C -> se corrige por posicion a 1234ABC
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '1234A8C'])->assertJsonPath('estado', C::REGISTRADA_CON_PASO);
        // 5791GJL existe; el usuario escribe 579IGJL (I en vez de 1) en formato invalido -> se normaliza
        $this->postJson('/api/v1/placas/consultar', ['placa' => '579I-GJL'])->assertJsonPath('placa', '5791GJL');
        // Placa inexistente a un caracter de distancia -> "¿Quisiste decir...?"
        $r = $this->postJson('/api/v1/agente/chat', ['mensaje' => '1234ABD'])->json();
        $this->assertSame(C::NO_EXISTE, $r['estado']);
        $this->assertContains('1234ABC', $r['sugerencias']);
        $this->assertStringContainsString('¿Quisiste decir', $r['respuesta']);
    }

    public function test_imagen_sin_placa_legible_pide_otra(): void
    {
        Http::fake(['inferencia.test/*' => Http::response(['placa' => null, 'placa_normalizada' => null, 'confianza_ocr' => 0.1,
            'categoria' => 'auto', 'ejes' => 2, 'pasajeros' => 1, 'latencia_ms' => 100])]);
        $r = $this->post('/api/v1/placas/consultar-imagen', ['imagen' => PasoFrameTest::jpeg()], ['Accept' => 'application/json'])
            ->assertOk()->json();
        $this->assertNull($r['estado']);
        $this->assertTrue($r['solicitar_imagen']);
        $this->assertStringContainsString('otra foto', $r['mensaje']);
    }

    public function test_imagen_con_placa_legible(): void
    {
        Http::fake(['inferencia.test/*' => Http::response(['placa' => '5678-FGH', 'placa_normalizada' => '5678FGH', 'confianza_ocr' => 0.95,
            'categoria' => 'bus', 'ejes' => 2, 'pasajeros' => 20, 'latencia_ms' => 100])]);
        $this->post('/api/v1/agente/chat', ['imagen' => PasoFrameTest::jpeg()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('estado', C::REGISTRADA_CON_PASO);
    }

    public function test_resumen_y_alertas_por_texto(): void
    {
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '¿Cuánto se recaudó hoy?'])->assertOk()
            ->assertJsonStructure(['datos' => ['pasos_hoy', 'recaudado_hoy']]);
        $this->postJson('/api/v1/agente/chat', ['mensaje' => 'muéstrame las alertas'])->assertOk()
            ->assertJsonPath('datos.alertas_pendientes', 2);
    }

    public function test_chat_web_requiere_sesion(): void
    {
        $this->app['auth']->forgetGuards();
        $this->postJson('/chat/mensaje', ['mensaje' => '1234ABC'])->assertStatus(401);
    }
}
