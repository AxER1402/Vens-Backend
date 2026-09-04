<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlockedDayController;
use App\Http\Controllers\Api\ClinicalHistoryController;
use App\Http\Controllers\Api\ClinicalOptionController;
use App\Http\Controllers\Api\DopplerReportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\ReportePeriodoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VenousMapCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Clínica Doctora Yojana Mendoza (Flebología)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Rutas públicas de autenticación
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Recuperación de contraseña (limitada a 5 intentos por minuto por IP)
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1');

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

        // Lectura de Citas (para calendario, agenda y filtros por año/mes/día)
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);

        // Gestión de Citas (Agendar, Editar, Reagendar, Asignar Paciente y Cancelar)
        Route::middleware('role:administrador,medico,recepcionista')->group(function () {
            Route::post('/appointments', [AppointmentController::class, 'store']);
            Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
            Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
            Route::patch('/appointments/{appointment}/assign-patient', [AppointmentController::class, 'assignPatient']);
            Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        });

        // Facturación: recibos internos y facturas electrónicas.
        // La lectura la necesita también el centro de reportes para contar los
        // ingresos del período, así que no está restringida por rol; emitir y
        // anular sí, que son las que mueven dinero.
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);

        // El documento imprimible (?formato=pdf|docx). Sin restricción de rol,
        // igual que los informes clínicos: quien lo ve puede entregarlo.
        Route::get('/invoices/{invoice}/reporte', [InvoiceController::class, 'reporte']);

        Route::middleware('role:administrador,recepcionista')->group(function () {
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::patch('/invoices/{invoice}/anular', [InvoiceController::class, 'anular']);
        });

        // Avisos del campanario: los pacientes que vienen en lo que queda de hoy
        // y mañana. Se calculan sobre la agenda, así que no hay que sincronizar
        // nada cuando una cita se reagenda o se cancela; lo único que se guarda
        // es cuáles descartó a mano cada usuario.
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::delete('/notifications', [NotificationController::class, 'destroyAll']);
        Route::delete('/notifications/{clave}', [NotificationController::class, 'destroy'])
            ->where('clave', '[a-z]+:[0-9]+');

        // Lectura de días bloqueados de la agenda (feriados, vacaciones y cierres)
        Route::get('/blocked-days', [BlockedDayController::class, 'index']);

        // Gestión de días bloqueados (Restringido a Administrador o Médico)
        Route::middleware('role:administrador,medico')->group(function () {
            Route::post('/blocked-days', [BlockedDayController::class, 'store']);
            Route::put('/blocked-days/{blockedDay}', [BlockedDayController::class, 'update']);
            Route::patch('/blocked-days/{blockedDay}', [BlockedDayController::class, 'update']);
            Route::delete('/blocked-days/{blockedDay}', [BlockedDayController::class, 'destroy']);
        });

        // Catálogo de opciones clínicas (síntomas, CEAP, indicaciones, etc.)
        Route::get('/clinical-history-options', [ClinicalOptionController::class, 'index']);

        // Catálogo del mapeo venoso (hallazgos, zonas anatómicas y plantilla)
        Route::get('/venous-map/catalog', [VenousMapCatalogController::class, 'index']);

        // Lectura de Historias Clínicas (disponible para personal autorizado)
        Route::get('/clinical-histories', [ClinicalHistoryController::class, 'index']);
        Route::get('/clinical-histories/{clinicalHistory}', [ClinicalHistoryController::class, 'show']);
        Route::get('/patients/{patient}/clinical-histories', [ClinicalHistoryController::class, 'byPatient']);

        // Descarga del informe de una consulta (?formato=pdf|docx&partes=historia,mapeo,doppler).
        // Sin restricción de rol: quien puede leer el expediente puede imprimirlo.
        Route::get('/clinical-histories/{clinicalHistory}/reporte', [ReporteController::class, 'historiaClinica']);
        Route::get('/clinical-histories/{clinicalHistory}/mapeo-venoso/reporte', [ReporteController::class, 'mapeoVenoso']);

        // Registro y edición de Historias Clínicas (Restringido a Administrador o Médico)
        Route::middleware('role:administrador,medico')->group(function () {
            Route::post('/clinical-histories', [ClinicalHistoryController::class, 'store']);
            Route::put('/clinical-histories/{clinicalHistory}', [ClinicalHistoryController::class, 'update']);
            Route::patch('/clinical-histories/{clinicalHistory}', [ClinicalHistoryController::class, 'update']);
            Route::post('/clinical-histories/{clinicalHistory}/venous-map', [ClinicalHistoryController::class, 'storeVenousMap']);
            Route::delete('/clinical-histories/{clinicalHistory}', [ClinicalHistoryController::class, 'destroy']);
        });

        // Lectura de Reportes de Ecodöppler venoso (disponible para personal autorizado)
        Route::get('/doppler-reports', [DopplerReportController::class, 'index']);
        Route::get('/doppler-reports/{dopplerReport}', [DopplerReportController::class, 'show']);
        Route::get('/patients/{patient}/doppler-reports', [DopplerReportController::class, 'byPatient']);
        Route::get('/clinical-histories/{clinicalHistory}/doppler-reports', [DopplerReportController::class, 'byClinicalHistory']);

        // Descarga del reporte de Ecodöppler (?formato=pdf|docx)
        Route::get('/doppler-reports/{dopplerReport}/reporte', [ReporteController::class, 'doppler']);

        // Reportes de período: los que resumen la actividad de la clínica entre
        // dos fechas, frente a los informes de un expediente concreto.
        // El permiso lo comprueba el controlador contra lo que cada reporte
        // declara: quien puede imprimir un expediente no tiene por qué ver la
        // producción del personal ni la epidemiología de la consulta.
        Route::get('/reportes', [ReportePeriodoController::class, 'index']);
        Route::get('/reportes/{clave}', [ReportePeriodoController::class, 'emitir'])
            ->where('clave', '[a-z0-9-]+');

        // Registro y edición de Reportes de Ecodöppler (Restringido a Administrador o Médico)
        Route::middleware('role:administrador,medico')->group(function () {
            Route::post('/doppler-reports', [DopplerReportController::class, 'store']);
            Route::put('/doppler-reports/{dopplerReport}', [DopplerReportController::class, 'update']);
            Route::patch('/doppler-reports/{dopplerReport}', [DopplerReportController::class, 'update']);
            Route::delete('/doppler-reports/{dopplerReport}', [DopplerReportController::class, 'destroy']);
        });
    });
});
