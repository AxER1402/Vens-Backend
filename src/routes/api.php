<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Centro Médico Vens (Flebología)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Rutas públicas de autenticación
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Rutas protegidas con Laravel Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Gestión de usuarios (listar, mostrar, crear, editar y desactivar)
        Route::apiResource('users', UserController::class);

        // Lectura de Pacientes (disponible para personal autorizado)
        Route::get('/patients', [PatientController::class, 'index']);
        Route::get('/patients/{patient}', [PatientController::class, 'show']);

        // Creación y actualización de Pacientes (Administrador, Médico, Recepcionista)
        Route::middleware('role:administrador,medico,recepcionista')->group(function () {
            Route::post('/patients', [PatientController::class, 'store']);
            Route::put('/patients/{patient}', [PatientController::class, 'update']);
            Route::patch('/patients/{patient}', [PatientController::class, 'update']);
        });

        // Desactivación lógica de Pacientes (Restringido a Administrador o Médico)
        Route::middleware('role:administrador,medico')->group(function () {
            Route::delete('/patients/{patient}', [PatientController::class, 'destroy']);
            Route::patch('/patients/{patient}/toggle-active', [PatientController::class, 'toggleActive']);
        });
    });
});
