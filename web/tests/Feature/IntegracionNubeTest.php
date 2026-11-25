<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Paso;
use App\Models\Usuario;
use App\Services\ConsultaPlacaService as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** ESP32 (ultimo-evento), captura manual, evidencia anotada, Gemini, fotos semilla y exportacion de BD. */
class IntegracionNubeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function jpeg(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('frame.jpg', file_get_contents(base_path('tests/fixtures/frame.jpg')));
    }

    private function operador(): Usuario
    {
        return Usuario::where('email_google', 'operador.demo@gmail.com')->first();
    }

    public function test_esp32_recibe_inferencia_y_luego_ok(): void
    {
        $h = ['X-Hardware-Token' => 'hw-prueba'];
        $this->getJson('/api/v1/hardware/ultimo-evento?caseta_id=1')->assertStatus(401);
        $this->getJson('/api/v1/hardware/ultimo-evento?caseta_id=1&rssi=-61', $h)->assertOk()->assertJsonPath('tipo_senal', 'idle');

        Storage::fake('public');
        Sanctum::actingAs($this->operador());
        Http::fake(['inferencia.test/*' => Http::response(['placa' => '4047CKE', 'placa_normalizada' => '4047CKE',
            'confianza_ocr' => 0.95, 'categoria' => 'auto', 'ejes' => 2, 'pasajeros' => 1, 'latencia_ms' => 90])]);
        $this->post('/api/v1/pasos/frame', ['imagen' => $this->jpeg(), 'caseta_id' => 1], ['Accept' => 'application/json'])
            ->assertJsonPath('motivo', 'cobrado');
        $this->getJson('/api/v1/hardware/ultimo-evento?caseta_id=1', $h)->assertOk()
            ->assertJsonPath('tipo_senal', 'ok')->assertJsonPath('placa', '4047CKE');
        // pide la imagen anotada al servidor de inferencia
        Http::assertSent(fn ($r) => $r->hasHeader('X-Anotar', '1'));
    }

    public function test_esp32_marca_alerta_y_estado_muestra_conexion(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->operador());
        Http::fake(['inferencia.test/*' => Http::response(['placa' => '8888ZZZ', 'placa_normalizada' => '8888ZZZ',
            'confianza_ocr' => 0.95, 'categoria' => 'camion', 'ejes' => 3, 'pasajeros' => 1, 'latencia_ms' => 90])]);
        $this->post('/api/v1/pasos/frame', ['imagen' => $this->jpeg(), 'caseta_id' => 2], ['Accept' => 'application/json']);
        $this->getJson('/api/v1/hardware/ultimo-evento?caseta_id=2&rssi=-55', ['X-Hardware-Token' => 'hw-prueba'])
            ->assertJsonPath('tipo_senal', 'alerta');

        $admin = Usuario::where('email_google', 'doc.alvaro.pinto.ma@unifranz.edu.bo')->first();
        $this->actingAs($admin)->get('/estado')->assertOk()->assertSee('Conectado')->assertSee('-55 dBm');
    }

    public function test_captura_manual_registra_placa_ilegible_con_evidencia_anotada(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->operador());
        $anotada = base64_encode('JPEG-ANOTADO');
        Http::fake(['inferencia.test/*' => Http::response(['placa' => null, 'placa_normalizada' => null, 'confianza_ocr' => 0,
            'categoria' => 'minibus', 'ejes' => 2, 'pasajeros' => 6, 'latencia_ms' => 80,
            'imagen_anotada_base64' => 'data:image/jpeg;base64,'.$anotada])]);

        // automatico: no se registra
        $antes = Paso::count();
        $this->post('/api/v1/pasos/frame', ['imagen' => $this->jpeg(), 'caseta_id' => 1], ['Accept' => 'application/json'])
            ->assertJsonPath('registrado', false)->assertJsonMissingPath('deteccion.imagen_anotada_base64');
        $this->assertSame($antes, Paso::count());

        // manual: se registra con alerta placa_ilegible y la evidencia es la imagen anotada
        $r = $this->post('/api/v1/pasos/frame', ['imagen' => $this->jpeg(), 'caseta_id' => 1, 'manual' => 1], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('registrado', true)->assertJsonPath('motivo', 'placa_ilegible')
            ->assertJsonPath('paso.pasajeros', 6)->json();
        $this->assertDatabaseHas('alertas', ['tipo' => 'placa_ilegible', 'estado' => 'pendiente']);
        $ruta = ltrim(str_replace('/storage/', '', $r['paso']['imagen_url']), '/');
        $this->assertSame('JPEG-ANOTADO', Storage::disk('public')->get($ruta));
        $this->assertStringStartsWith('/storage/', $r['paso']['imagen_url']);   // URL relativa, sin host local
    }

    public function test_agente_redacta_con_gemini_y_cae_a_plantilla_si_falla(): void
    {
        config(['peaje.agente.proveedor' => 'gemini', 'peaje.agente.gemini_api_key' => 'clave-prueba']);
        Sanctum::actingAs($this->operador());
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['candidates' => [['content' => ['parts' => [['text' => 'La placa 4047CKE pasó por la Caseta Achica Arriba.']]]]]])
            ->push('error', 500)]);
        $r = $this->postJson('/api/v1/agente/chat', ['mensaje' => '¿pasó la 4047-cke?'])->assertOk()->json();
        $this->assertSame('llm:gemini', $r['fuente']);
        $this->assertSame(C::REGISTRADA_CON_PASO, $r['estado']);
        $this->assertStringStartsWith('**'.C::REGISTRADA_CON_PASO.'**', $r['respuesta']);   // el estado siempre aparece
        Http::assertSent(fn ($req) => $req->hasHeader('x-goog-api-key', 'clave-prueba'));

        // segunda llamada: Gemini responde 500 -> plantilla determinista con el mismo estado
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '4047CKE'])->assertJsonPath('fuente', 'plantilla')
            ->assertJsonPath('estado', C::REGISTRADA_CON_PASO);
    }

    public function test_fotos_reales_y_cuatro_estados_con_placas_bolivianas(): void
    {
        $this->assertSame(19, Paso::whereDate('created_at', '2026-11-18')->count());
        Sanctum::actingAs($this->operador());
        $this->postJson('/api/v1/placas/consultar', ['placa' => '4047-cke'])->assertJsonPath('estado', C::REGISTRADA_CON_PASO);
        $this->postJson('/api/v1/placas/consultar', ['placa' => '5751PDE'])->assertJsonPath('estado', C::IDENTIFICADA_NO_REGISTRADA);
        // confusion 3<->8 del OCR: 5592UTF leida como 5592UTF con un 3 -> sugerencia
        $r = $this->postJson('/api/v1/agente/chat', ['mensaje' => '3620YKT'])->json();
        $this->assertSame(C::NO_EXISTE, $r['estado']);
        $this->assertContains('3620YKI', $r['sugerencias']);
        // 3 leido en lugar de B: se corrige por posicion y encuentra la placa registrada
        $this->postJson('/api/v1/agente/chat', ['mensaje' => '2874A3G'])->assertJsonPath('datos.placa', '2874ABG')
            ->assertJsonPath('estado', C::REGISTRADA_CON_PASO);
    }

    public function test_exportar_bd_y_canal_privado(): void
    {
        $dir = storage_path('framework/testing/export');
        File::deleteDirectory($dir);
        $this->artisan('peaje:exportar-bd', ['--dir' => $dir])->assertSuccessful();
        $sql = File::get(File::glob($dir.'/*.sql')[0]);
        $this->assertStringContainsString('doc.alvaro.pinto.ma@unifranz.edu.bo', $sql);
        $this->assertStringContainsString("'4047CKE'", $sql);
        $this->assertStringNotContainsString('127.0.0.1', $sql);
        File::deleteDirectory($dir);

        // Sin Pusher configurado no se firma ningun canal
        $this->actingAs($this->operador())->post('/broadcasting/auth', ['channel_name' => 'private-peaje', 'socket_id' => '1.1'])
            ->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post('/broadcasting/auth', ['channel_name' => 'private-peaje', 'socket_id' => '1.1'])->assertRedirect('/login');
    }

    public function test_admin_email_del_env_se_agrega_como_administrador(): void
    {
        config(['peaje.admin_emails' => ['saraquenta2@gmail.com']]);
        $this->artisan('peaje:sembrar-si-vacio')->assertSuccessful();
        $u = Usuario::where('email_google', 'saraquenta2@gmail.com')->first();
        $this->assertSame('administrador', $u->rol);
        $this->assertSame(0, Alerta::where('tipo', 'placa_ilegible')->count());
    }
}
