<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Models\Vehiculo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehiculoCrudTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_semilla_tiene_al_menos_20_vehiculos(): void
    {
        $this->assertGreaterThanOrEqual(20, Vehiculo::count());
    }

    public function test_crud_completo_por_administrador(): void
    {
        $admin = Usuario::where('email_google', DatabaseSeeder::DOCENTE)->first();
        $this->actingAs($admin)->get('/vehiculos')->assertOk()->assertSee('1234ABC');

        $this->actingAs($admin)->post('/vehiculos', ['placa' => '9876-xyz', 'propietario' => 'Nuevo Dueño', 'categoria' => 'minibus',
            'ejes' => 2, 'saldo' => 50, 'activo' => '1'])->assertRedirect('/vehiculos');
        $v = Vehiculo::where('placa', '9876XYZ')->firstOrFail();

        $this->actingAs($admin)->put("/vehiculos/{$v->id}", ['placa' => '9876XYZ', 'propietario' => 'Editado', 'categoria' => 'bus',
            'ejes' => 3, 'saldo' => 70])->assertRedirect('/vehiculos');
        $v->refresh();
        $this->assertSame('Editado', $v->propietario);
        $this->assertFalse($v->activo);

        $this->actingAs($admin)->delete("/vehiculos/{$v->id}")->assertRedirect('/vehiculos');
        $this->assertDatabaseMissing('vehiculos', ['placa' => '9876XYZ']);
    }

    public function test_valida_formato_boliviano_y_duplicados(): void
    {
        $admin = Usuario::where('email_google', DatabaseSeeder::DOCENTE)->first();
        $this->actingAs($admin)->post('/vehiculos', ['placa' => 'HOLA', 'propietario' => 'x', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0])
            ->assertSessionHasErrors('placa');
        $this->actingAs($admin)->post('/vehiculos', ['placa' => '1234ABC', 'propietario' => 'x', 'categoria' => 'auto', 'ejes' => 2, 'saldo' => 0])
            ->assertSessionHasErrors('placa');
    }

    public function test_paginas_principales_renderizan(): void
    {
        $admin = Usuario::where('email_google', DatabaseSeeder::DOCENTE)->first();
        foreach (['/panel', '/panel/datos', '/pasos', '/pasos/1', '/alertas', '/chat', '/estado', '/admin/casetas', '/admin/tarifas', '/vehiculos/nuevo'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
