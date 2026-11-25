<?php

use App\Http\Controllers\Api\AgenteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CasetaController;
use App\Http\Controllers\Api\PasoController;
use App\Http\Controllers\Api\PlacaController;
use Illuminate\Support\Facades\Route;

/*
| API REST v1. Todas las rutas requieren token Bearer (Sanctum) obtenido con Google,
| excepto POST /api/v1/auth/google. El endpoint del ESP32 usa un token de dispositivo.
*/
Route::prefix('v1')->group(function () {
    Route::post('auth/google', [AuthController::class, 'google'])->middleware('throttle:20,1');

    Route::middleware(['auth:sanctum', 'activo'])->group(function () {
        Route::get('auth/yo', [AuthController::class, 'yo']);
        Route::post('auth/salir', [AuthController::class, 'salir']);

        Route::post('pasos/frame', [PasoController::class, 'frame'])->middleware('throttle:600,1');

        Route::post('placas/consultar', [PlacaController::class, 'consultar']);
        Route::post('placas/consultar-imagen', [PlacaController::class, 'consultarImagen']);
        Route::get('placas/{placa}/historial', [PlacaController::class, 'historial']);

        Route::get('casetas', [CasetaController::class, 'index']);
        Route::get('casetas/{id}/resumen', [CasetaController::class, 'resumen'])->whereNumber('id');
        Route::get('estado', [CasetaController::class, 'estado']);

        Route::post('agente/chat', [AgenteController::class, 'chat'])->middleware('throttle:60,1');
    });

    Route::get('hardware/estado', [CasetaController::class, 'hardware'])->middleware(['hardware', 'throttle:240,1']);
    Route::get('hardware/ultimo-evento', [CasetaController::class, 'ultimoEvento'])->middleware(['hardware', 'throttle:240,1']);
});
