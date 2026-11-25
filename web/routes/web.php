<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuthWebController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\PasoWebController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'panel' : 'login'));

// --- Autenticacion: exclusivamente Google OAuth 2.0 ---
Route::get('/login', [AuthWebController::class, 'login'])->name('login');
Route::get('/auth/google/redirect', [AuthWebController::class, 'redirigir'])->name('google.redirect');
Route::get('/auth/google/callback', [AuthWebController::class, 'callback'])->name('google.callback');
Route::post('/auth/dev-login', [AuthWebController::class, 'devLogin'])->name('dev.login'); // 404 fuera de APP_ENV=local
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'activo'])->group(function () {
    Route::get('/panel', [PanelController::class, 'index'])->name('panel');
    Route::get('/panel/datos', [PanelController::class, 'datos'])->name('panel.datos');
    Route::post('/panel/simular', [PanelController::class, 'simular'])->name('panel.simular');
    // Autoriza la suscripcion de pusher-js al canal privado "private-peaje" (solo usuarios con sesion)
    Route::post('/broadcasting/auth', [PanelController::class, 'autorizarCanal'])->name('broadcasting.auth');

    Route::get('/vehiculos', [VehiculoController::class, 'index'])->name('vehiculos.index');
    Route::get('/pasos', [PasoWebController::class, 'index'])->name('pasos.index');
    Route::get('/pasos/{paso}', [PasoWebController::class, 'show'])->name('pasos.show');
    Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::patch('/alertas/{alerta}', [AlertaController::class, 'update'])->name('alertas.update');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/mensaje', [ChatController::class, 'mensaje'])->name('chat.mensaje')->middleware('throttle:60,1');
    Route::get('/estado', [EstadoController::class, 'index'])->name('estado');
    Route::get('/estado/verificar', [EstadoController::class, 'verificar'])->name('estado.verificar');

    // --- Solo administrador: vehiculos (escritura), tarifas, casetas, lista blanca, inferencia ---
    Route::middleware('rol:administrador')->group(function () {
        Route::get('/vehiculos/nuevo', [VehiculoController::class, 'create'])->name('vehiculos.create');
        Route::post('/vehiculos', [VehiculoController::class, 'store'])->name('vehiculos.store');
        Route::get('/vehiculos/{vehiculo}/editar', [VehiculoController::class, 'edit'])->name('vehiculos.edit');
        Route::put('/vehiculos/{vehiculo}', [VehiculoController::class, 'update'])->name('vehiculos.update');
        Route::delete('/vehiculos/{vehiculo}', [VehiculoController::class, 'destroy'])->name('vehiculos.destroy');

        Route::get('/admin/tarifas', [AdminController::class, 'tarifas'])->name('admin.tarifas');
        Route::put('/admin/tarifas/{tarifa}', [AdminController::class, 'guardarTarifa'])->name('admin.tarifas.update');
        Route::get('/admin/casetas', [AdminController::class, 'casetas'])->name('admin.casetas');
        Route::post('/admin/casetas', [AdminController::class, 'guardarCaseta'])->name('admin.casetas.store');
        Route::put('/admin/casetas/{caseta}', [AdminController::class, 'guardarCaseta'])->name('admin.casetas.update');
        Route::get('/admin/usuarios', [AdminController::class, 'usuarios'])->name('admin.usuarios');
        Route::post('/admin/usuarios', [AdminController::class, 'guardarUsuario'])->name('admin.usuarios.store');
        Route::put('/admin/usuarios/{usuario}', [AdminController::class, 'guardarUsuario'])->name('admin.usuarios.update');
        Route::post('/estado/inferencia', [EstadoController::class, 'guardarInferencia'])->name('estado.inferencia');
    });
});
