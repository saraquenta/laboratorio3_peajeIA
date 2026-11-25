<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PasoFrameTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(Usuario::where('email_google', 'operador.demo@gmail.com')->first());
    }

    public static function jpeg(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('frame.jpg', file_get_contents(base_path('tests/fixtures/frame.jpg')));
    }

    private function inferencia(array $r): void
    {
        Http::fake(['inferencia.test/*' => Http::response($r + ['confianza_ocr' => 0.92, 'latencia_ms' => 120.5,
            'confianza_categoria' => 0.9, 'ejes_fuente' => 'keypoints', 'pasajeros' => 2])]);
    }

    private function frame(int $caseta = 1)
    {
        return $this->post('/api/v1/pasos/frame', ['imagen' => self::jpeg(), 'caseta_id' => $caseta],
            ['Accept' => 'application/json']);
    }

    public function test_vehiculo_registrado_con_saldo_se_cobra(): void
    {
        $saldo = (float) Vehiculo::where('placa', '6923TVW')->value('saldo');
        $this->inferencia(['placa' => '6923-TVW', 'placa_normalizada' => '6923TVW', 'categoria' => 'auto', 'ejes' => 2]);
        $r = $this->frame()->assertOk()->assertJsonPath('registrado', true)->assertJsonPath('motivo', 'cobrado')
            ->assertJsonPath('senal.led', 'verde')->assertJsonPath('paso.pasajeros', 2);
        $this->assertEquals($saldo - 5, (float) Vehiculo::where('placa', '6923TVW')->value('saldo'));
        $this->assertNotNull($r->json('paso.imagen_url'));
        Http::assertSent(fn ($req) => $req->hasHeader('X-API-Token', 'token-prueba') && str_ends_with($req->url(), '/predict'));
    }

    public function test_camion_paga_por_ejes_adicionales(): void
    {
        $this->inferencia(['placa' => '4680FHK', 'placa_normalizada' => '4680FHK', 'categoria' => 'camion', 'ejes' => 4]);
        $this->frame()->assertOk()->assertJsonPath('tarifa', 40)->assertJsonPath('paso.monto', 40); // 20 + 2 ejes x 10
    }

    public function test_placa_no_registrada_genera_alerta_de_evasion(): void
    {
        $this->inferencia(['placa' => '7777QRS', 'placa_normalizada' => '7777QRS', 'categoria' => 'auto', 'ejes' => 2]);
        $this->frame()->assertOk()->assertJsonPath('motivo', 'no_registrada')->assertJsonPath('senal.led', 'rojo')
            ->assertJsonPath('senal.buzzer', true);
        $this->assertDatabaseHas('alertas', ['placa' => '7777QRS', 'tipo' => 'no_registrada', 'estado' => 'pendiente']);
    }

    public function test_saldo_insuficiente_genera_alerta_y_no_cobra(): void
    {
        $this->inferencia(['placa' => '2589NPR', 'placa_normalizada' => '2589NPR', 'categoria' => 'auto', 'ejes' => 2]);
        $this->frame()->assertOk()->assertJsonPath('motivo', 'saldo_insuficiente')->assertJsonPath('paso.monto', 0);
        $this->assertEquals(0, (float) Vehiculo::where('placa', '2589NPR')->value('saldo'));
    }

    public function test_frames_repetidos_no_duplican_pasos(): void
    {
        $this->inferencia(['placa' => '1357BDF', 'placa_normalizada' => '1357BDF', 'categoria' => 'auto', 'ejes' => 2]);
        $this->frame()->assertJsonPath('registrado', true);
        $antes = Paso::count();
        $this->frame()->assertJsonPath('registrado', false)->assertJsonPath('motivo', 'duplicado');
        $this->assertSame($antes, Paso::count());
    }

    public function test_placa_ilegible_no_registra(): void
    {
        $this->inferencia(['placa' => 'XX', 'placa_normalizada' => 'XX', 'categoria' => 'auto', 'ejes' => 2]);
        $antes = Paso::count();
        $this->frame()->assertOk()->assertJsonPath('registrado', false)->assertJsonPath('motivo', 'placa_ilegible');
        $this->assertSame($antes, Paso::count());
    }

    public function test_inferencia_caida_responde_503(): void
    {
        Http::fake(['inferencia.test/*' => Http::response('error', 500)]);
        $this->frame()->assertStatus(503)->assertJsonPath('motivo', 'inferencia_no_disponible');
    }

    public function test_hardware_lee_ultimo_evento(): void
    {
        $this->inferencia(['placa' => '7777QRS', 'placa_normalizada' => '7777QRS', 'categoria' => 'auto', 'ejes' => 2]);
        $this->frame(2);
        $this->getJson('/api/v1/hardware/estado?caseta_id=2')->assertStatus(401);
        $this->getJson('/api/v1/hardware/estado?caseta_id=2', ['X-Hardware-Token' => 'hw-prueba'])->assertOk()
            ->assertJsonPath('led', 'rojo')->assertJsonPath('placa', '7777QRS');
    }

    public function test_resumen_de_caseta_e_historial(): void
    {
        $this->inferencia(['placa' => '6923TVW', 'placa_normalizada' => '6923TVW', 'categoria' => 'auto', 'ejes' => 2]);
        $this->frame(1);
        $this->getJson('/api/v1/casetas/1/resumen')->assertOk()->assertJsonStructure(['pasos', 'recaudacion', 'por_categoria', 'ultimos_pasos']);
        $this->getJson('/api/v1/placas/6923-tvw/historial')->assertOk()->assertJsonPath('placa', '6923TVW')
            ->assertJsonPath('registrada', true)->assertJsonPath('total_pasos', 1);
        $this->assertGreaterThan(0, Alerta::count()); // de la semilla
    }
}
