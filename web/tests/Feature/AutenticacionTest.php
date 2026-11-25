<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function fakeGoogle(string $email, string $aud = 'cliente-web.apps.googleusercontent.com'): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => $aud, 'iss' => 'https://accounts.google.com', 'email' => $email, 'email_verified' => 'true',
            'sub' => '1234567890', 'name' => 'Prueba', 'picture' => 'https://example.com/a.png',
        ])]);
    }

    public function test_semilla_incluye_al_docente_como_administrador(): void
    {
        $u = Usuario::where('email_google', DatabaseSeeder::DOCENTE)->first();
        $this->assertNotNull($u);
        $this->assertSame('administrador', $u->rol);
        $this->assertTrue($u->activo);
    }

    public function test_proveedor_de_sesion_usa_el_modelo_usuario(): void
    {
        $id = Usuario::where('email_google', DatabaseSeeder::DOCENTE)->value('id');
        $this->assertTrue(\Illuminate\Support\Facades\Auth::guard('web')->loginUsingId($id) instanceof Usuario);
    }

    public function test_rutas_api_protegidas_excepto_auth_google(): void
    {
        $rutas = [['post', '/api/v1/pasos/frame'], ['post', '/api/v1/placas/consultar'], ['post', '/api/v1/placas/consultar-imagen'],
            ['get', '/api/v1/placas/1234ABC/historial'], ['get', '/api/v1/casetas/1/resumen'], ['post', '/api/v1/agente/chat'],
            ['get', '/api/v1/casetas'], ['get', '/api/v1/estado']];
        foreach ($rutas as [$m, $url]) {
            $this->json($m, $url)->assertStatus(401);
        }
        $this->json('post', '/api/v1/auth/google')->assertStatus(422); // publica: valida datos, no pide token
    }

    public function test_login_google_con_correo_autorizado_entrega_token(): void
    {
        $this->fakeGoogle(DatabaseSeeder::DOCENTE);
        $r = $this->postJson('/api/v1/auth/google', ['id_token' => 'token-google-falso'])->assertOk()
            ->assertJsonPath('usuario.rol', 'administrador');
        $this->withToken($r->json('token'))->getJson('/api/v1/auth/yo')->assertOk()
            ->assertJsonPath('usuario.email', DatabaseSeeder::DOCENTE);
    }

    public function test_correo_fuera_de_lista_blanca_es_denegado(): void
    {
        $this->fakeGoogle('intruso@gmail.com');
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x'])->assertStatus(403)
            ->assertJsonPath('email', 'intruso@gmail.com');
    }

    public function test_usuario_desactivado_es_denegado(): void
    {
        Usuario::where('email_google', 'operador.demo@gmail.com')->update(['activo' => false]);
        $this->fakeGoogle('operador.demo@gmail.com');
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x'])->assertStatus(403);
    }

    public function test_token_emitido_para_otro_cliente_es_rechazado(): void
    {
        $this->fakeGoogle(DatabaseSeeder::DOCENTE, 'otra-app.apps.googleusercontent.com');
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x'])->assertStatus(401);
    }

    public function test_web_requiere_login_y_roles(): void
    {
        $this->get('/panel')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Ingresar con Google');
        $this->post('/auth/dev-login', ['email' => DatabaseSeeder::DOCENTE])->assertNotFound(); // deshabilitado fuera de local

        $operador = Usuario::where('email_google', 'operador.demo@gmail.com')->first();
        $this->actingAs($operador)->get('/panel')->assertOk()->assertSee('Panel en tiempo real');
        $this->actingAs($operador)->get('/admin/tarifas')->assertForbidden();
        $this->actingAs($operador)->get('/vehiculos/nuevo')->assertForbidden();

        $admin = Usuario::where('email_google', DatabaseSeeder::DOCENTE)->first();
        $this->actingAs($admin)->get('/admin/tarifas')->assertOk();
        $this->actingAs($admin)->get('/admin/usuarios')->assertOk()->assertSee(DatabaseSeeder::DOCENTE);
    }
}
