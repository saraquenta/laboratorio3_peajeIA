<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Token de acceso para la app (pruebas sin la pantalla de Google): solo lo genera un administrador. */
class TokenAppTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_genera_token_que_funciona_en_la_api(): void
    {
        $admin = Usuario::where('email_google', DatabaseSeeder::DOCENTE)->first();
        $operador = Usuario::where('email_google', 'operador.demo@gmail.com')->first();

        $r = $this->actingAs($admin)->post("/admin/usuarios/{$operador->id}/token-app");
        $r->assertRedirect()->assertSessionHas('token_app');
        $token = session('token_app')['token'];
        $this->assertNotNull($operador->tokens()->first()->expires_at);   // caduca

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertOk()->assertJsonPath('usuario.email', 'operador.demo@gmail.com');
        $this->withToken($token)->postJson('/api/v1/placas/consultar', ['placa' => '4047CKE'])->assertOk();

        // si el administrador lo desactiva, el token deja de servir
        $operador->update(['activo' => false]);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertForbidden();
    }

    public function test_operador_no_puede_generar_tokens(): void
    {
        $operador = Usuario::where('email_google', 'operador.demo@gmail.com')->first();
        $this->actingAs($operador)->post("/admin/usuarios/{$operador->id}/token-app")->assertForbidden();
    }

    public function test_sin_token_la_api_sigue_cerrada(): void
    {
        $this->getJson('/api/v1/auth/yo')->assertStatus(401);
        $this->withToken('1|token-inventado')->getJson('/api/v1/auth/yo')->assertStatus(401);
    }
}
