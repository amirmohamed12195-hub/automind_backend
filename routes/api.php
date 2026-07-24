<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DiagnosisController;
use App\Http\Controllers\Api\V1\DiagnosticMediaController;
use App\Http\Controllers\Api\V1\MaintenanceController;
use App\Http\Controllers\Api\V1\MaintenanceReminderController;
use App\Http\Controllers\Api\V1\MechanicController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ObdController;
use App\Http\Controllers\Api\V1\OpenAiWebhookController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\VehicleCatalogController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Services\Auth\SocialIdentityVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', [SystemController::class, 'health']);
    Route::get('version', [SystemController::class, 'version']);
    Route::post('webhooks/openai', OpenAiWebhookController::class)->middleware('throttle:120,1');
    Route::get('shared/reports/{report}', [ReportController::class, 'shared'])->middleware('signed')->name('reports.shared');

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('social/google', fn (Request $request, SocialIdentityVerifier $verifier) => app(AuthController::class)->social($request, 'google', $verifier))->middleware('throttle:login');
        Route::post('social/apple', fn (Request $request, SocialIdentityVerifier $verifier) => app(AuthController::class)->social($request, 'apple', $verifier))->middleware('throttle:login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    });

    Route::get('vehicle-catalog/makes', [VehicleCatalogController::class, 'makes']);
    Route::get('vehicle-catalog/makes/{makeCode}/models', [VehicleCatalogController::class, 'models']);
    Route::get('symptoms', [DiagnosisController::class, 'symptoms']);
    Route::get('maintenance-service-definitions', [MaintenanceController::class, 'serviceDefinitions']);
    Route::get('mechanics', [MechanicController::class, 'index']);
    Route::get('mechanics/{mechanic}', [MechanicController::class, 'show']);
    Route::get('mechanics/{mechanic}/availability', [MechanicController::class, 'availability']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AccountController::class, 'show']);
        Route::patch('me', [AccountController::class, 'update']);
        Route::post('me/avatar', [AccountController::class, 'uploadAvatar'])->middleware('throttle:uploads');
        Route::delete('me/avatar', [AccountController::class, 'deleteAvatar']);
        Route::delete('me', [AccountController::class, 'destroy']);
        Route::get('settings', [AccountController::class, 'settings']);
        Route::patch('settings', [AccountController::class, 'updateSettings']);
        Route::post('devices', [AccountController::class, 'registerDevice'])->middleware('throttle:60,1');
        Route::delete('devices/{deviceTokenId}', [AccountController::class, 'deleteDevice']);

        Route::get('vehicles', [VehicleController::class, 'index']);
        Route::post('vehicles', [VehicleController::class, 'store']);
        Route::get('vehicles/{vehicle}', [VehicleController::class, 'show']);
        Route::patch('vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy']);
        Route::put('vehicles/{vehicle}/selected', [VehicleController::class, 'select']);
        Route::get('vehicles/{vehicle}/health', [VehicleController::class, 'health']);

        Route::get('diagnoses', [DiagnosisController::class, 'index']);
        Route::post('diagnoses', [DiagnosisController::class, 'store'])->middleware('throttle:analysis');
        Route::get('diagnoses/{diagnosis}', [DiagnosisController::class, 'show']);
        Route::patch('diagnoses/{diagnosis}', [DiagnosisController::class, 'update']);
        Route::delete('diagnoses/{diagnosis}', [DiagnosisController::class, 'destroy']);
        Route::post('diagnoses/{diagnosis}/media', [DiagnosticMediaController::class, 'store'])->middleware('throttle:uploads');
        Route::delete('diagnoses/{diagnosis}/media/{media}', [DiagnosticMediaController::class, 'destroy']);
        Route::post('diagnoses/{diagnosis}/obd-snapshots', [ObdController::class, 'store']);
        Route::post('diagnoses/{diagnosis}/analyze', [DiagnosisController::class, 'analyze'])->middleware('throttle:analysis');
        Route::post('diagnoses/{diagnosis}/cancel', [DiagnosisController::class, 'cancel']);
        Route::post('diagnoses/{diagnosis}/retry', [DiagnosisController::class, 'retry'])->middleware('throttle:analysis');
        Route::get('diagnoses/{diagnosis}/status', [DiagnosisController::class, 'status']);
        Route::get('diagnoses/{diagnosis}/report', [DiagnosisController::class, 'report']);
        Route::get('reports/{report}', [ReportController::class, 'show']);
        Route::post('reports/{report}/feedback', [ReportController::class, 'feedback'])->middleware('throttle:feedback');
        Route::post('reports/{report}/refresh-estimate', [ReportController::class, 'refreshEstimate'])->middleware('throttle:web-search');
        Route::get('reports/{report}/share', [ReportController::class, 'share']);

        Route::get('vehicles/{vehicle}/maintenance', [MaintenanceController::class, 'index']);
        Route::post('vehicles/{vehicle}/maintenance', [MaintenanceController::class, 'store']);
        Route::get('vehicles/{vehicle}/maintenance/{record}', [MaintenanceController::class, 'show']);
        Route::patch('vehicles/{vehicle}/maintenance/{record}', [MaintenanceController::class, 'update']);
        Route::delete('vehicles/{vehicle}/maintenance/{record}', [MaintenanceController::class, 'destroy']);
        Route::get('vehicles/{vehicle}/maintenance-reminders', [MaintenanceReminderController::class, 'index']);
        Route::post('vehicles/{vehicle}/maintenance-reminders', [MaintenanceReminderController::class, 'store']);
        Route::patch('vehicles/{vehicle}/maintenance-reminders/{reminder}', [MaintenanceReminderController::class, 'update']);
        Route::post('vehicles/{vehicle}/maintenance-reminders/{reminder}/complete', [MaintenanceReminderController::class, 'complete']);
        Route::post('vehicles/{vehicle}/maintenance-reminders/{reminder}/snooze', [MaintenanceReminderController::class, 'snooze']);

        Route::post('appointments', [AppointmentController::class, 'store'])->middleware('throttle:appointments');
        Route::get('appointments', [AppointmentController::class, 'index']);
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::post('appointments/{appointment}/reviews', [AppointmentController::class, 'review']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

        Route::prefix('admin')->middleware('admin')->group(function (): void {
            Route::get('mechanics', [AdminController::class, 'mechanics']);
            Route::post('mechanics', [AdminController::class, 'storeMechanic']);
            Route::patch('mechanics/{mechanic}', [AdminController::class, 'updateMechanic']);
            Route::delete('mechanics/{mechanic}', [AdminController::class, 'deleteMechanic']);
            Route::put('mechanics/{mechanic}/verification', [AdminController::class, 'verifyMechanic']);
            Route::post('vehicle-catalog/makes', [AdminController::class, 'storeMake']);
            Route::patch('vehicle-catalog/makes/{make}', [AdminController::class, 'updateMake']);
            Route::post('vehicle-catalog/makes/{make}/models', [AdminController::class, 'storeModel']);
            Route::get('maintenance-service-definitions', [AdminController::class, 'serviceDefinitions']);
            Route::post('maintenance-service-definitions', [AdminController::class, 'storeServiceDefinition']);
            Route::patch('maintenance-service-definitions/{service}', [AdminController::class, 'updateServiceDefinition']);
            Route::post('notifications/broadcast', [AdminController::class, 'broadcast']);
            Route::get('labor-rate-sources', [AdminController::class, 'laborRates']);
            Route::post('labor-rate-sources', [AdminController::class, 'storeLaborRate']);
            Route::get('currency-rates', [AdminController::class, 'currencyRates']);
            Route::post('currency-rates', [AdminController::class, 'storeCurrencyRate']);
            Route::get('ai-runs/failed', [AdminController::class, 'failedAiRuns']);
            Route::post('ai-runs/{run}/retry', [AdminController::class, 'retryAiRun']);
        });
    });
});
