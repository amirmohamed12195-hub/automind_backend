<?php

use App\Http\Controllers\AdminBillingDashboardController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminSessionController;
use App\Http\Controllers\AppleSignInCallbackController;
use App\Http\Controllers\AssociationFileController;
use App\Http\Controllers\PasswordResetPageController;
use App\Http\Controllers\PublicAccountDeletionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');
Route::view('/privacy', 'public.privacy')->name('privacy');
Route::view('/terms', 'public.terms')->name('terms');
Route::view('/support', 'public.support')->name('support');
Route::get('/delete-account', [PublicAccountDeletionController::class, 'show'])->name('account-deletion.show');
Route::post('/delete-account', [PublicAccountDeletionController::class, 'request'])
    ->middleware('throttle:password-reset')
    ->name('account-deletion.request');
Route::get('/delete-account/confirm/{user}', [PublicAccountDeletionController::class, 'confirm'])
    ->middleware('signed')
    ->name('account-deletion.confirm');
Route::post('/delete-account/confirm/{user}', [PublicAccountDeletionController::class, 'destroy'])
    ->middleware(['signed', 'throttle:password-reset'])
    ->name('account-deletion.destroy');
Route::get('/reset-password', [PasswordResetPageController::class, 'show'])->name('password.reset.show');
Route::post('/reset-password', [PasswordResetPageController::class, 'store'])
    ->middleware('throttle:password-reset')
    ->name('password.reset.store');
Route::get('/.well-known/assetlinks.json', [AssociationFileController::class, 'android']);
Route::get('/.well-known/apple-app-site-association', [AssociationFileController::class, 'apple']);
Route::post('/callbacks/sign_in_with_apple', AppleSignInCallbackController::class)
    ->middleware('throttle:60,1')
    ->name('apple-sign-in.callback');

Route::get('/admin/login', [AdminSessionController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminSessionController::class, 'store'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.store');
Route::middleware('web-admin')->group(function (): void {
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::patch('/admin/users/{user}', [AdminDashboardController::class, 'updateUser'])->name('admin.users.update');
    Route::post('/admin/users/{user}/suspension', [AdminDashboardController::class, 'suspendUser'])->name('admin.users.suspension');
    Route::delete('/admin/users/{user}', [AdminDashboardController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::post('/admin/users/{user}/restore', [AdminDashboardController::class, 'restoreUser'])->name('admin.users.restore');
    Route::patch('/admin/vehicles/{vehicle}', [AdminDashboardController::class, 'updateVehicle'])->name('admin.vehicles.update');
    Route::delete('/admin/vehicles/{vehicle}', [AdminDashboardController::class, 'destroyVehicle'])->name('admin.vehicles.destroy');
    Route::post('/admin/diagnostics/{diagnosis}/retry', [AdminDashboardController::class, 'retryDiagnostic'])->name('admin.diagnostics.retry');
    Route::post('/admin/diagnostics/{diagnosis}/cancel', [AdminDashboardController::class, 'cancelDiagnostic'])->name('admin.diagnostics.cancel');
    Route::delete('/admin/diagnostics/{diagnosis}', [AdminDashboardController::class, 'destroyDiagnostic'])->name('admin.diagnostics.destroy');
    Route::post('/admin/mechanics', [AdminDashboardController::class, 'storeMechanic'])->name('admin.mechanics.store');
    Route::patch('/admin/mechanics/{mechanic}', [AdminDashboardController::class, 'updateMechanic'])->name('admin.mechanics.update');
    Route::patch('/admin/appointments/{appointment}', [AdminDashboardController::class, 'updateAppointment'])->name('admin.appointments.update');
    Route::post('/admin/notifications/broadcast', [AdminDashboardController::class, 'broadcast'])->name('admin.notifications.broadcast');
    Route::post('/admin/ai-runs/{run}/retry', [AdminDashboardController::class, 'retryAiRun'])->name('admin.ai-runs.retry');
    Route::patch('/admin/settings', [AdminDashboardController::class, 'updateSettings'])->name('admin.settings.update');
    Route::post('/admin/catalog/vehicle-makes', [AdminDashboardController::class, 'storeMake'])->name('admin.catalog.makes.store');
    Route::patch('/admin/catalog/vehicle-makes/{make}', [AdminDashboardController::class, 'updateMake'])->name('admin.catalog.makes.update');
    Route::post('/admin/catalog/vehicle-models', [AdminDashboardController::class, 'storeModel'])->name('admin.catalog.models.store');
    Route::patch('/admin/catalog/vehicle-models/{model}', [AdminDashboardController::class, 'updateModel'])->name('admin.catalog.models.update');
    Route::post('/admin/catalog/maintenance-services', [AdminDashboardController::class, 'storeMaintenanceService'])->name('admin.catalog.services.store');
    Route::patch('/admin/catalog/maintenance-services/{service}', [AdminDashboardController::class, 'updateMaintenanceService'])->name('admin.catalog.services.update');
    Route::post('/admin/catalog/currency-rates', [AdminDashboardController::class, 'storeCurrencyRate'])->name('admin.catalog.currency-rates.store');
    Route::post('/admin/catalog/labor-rates', [AdminDashboardController::class, 'storeLaborRate'])->name('admin.catalog.labor-rates.store');
    Route::patch('/admin/billing/plans/{plan}', [AdminBillingDashboardController::class, 'updatePlan'])->name('admin.billing.plans.update');
    Route::patch('/admin/billing/products/{product}', [AdminBillingDashboardController::class, 'updateProduct'])->name('admin.billing.products.update');
    Route::post('/admin/billing/events/{event}/reprocess', [AdminBillingDashboardController::class, 'reprocessEvent'])->name('admin.billing.events.reprocess');
    Route::post('/admin/logout', [AdminSessionController::class, 'destroy'])->name('admin.logout');
});

Route::get('/docs/openapi.yaml', function () {
    abort_if(app()->environment('production'), 404);

    return response()->file(base_path('docs/openapi.yaml'), ['Content-Type' => 'application/yaml']);
});

Route::get('/docs/api', function () {
    abort_if(app()->environment('production'), 404);

    return response(<<<'HTML'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AutoMind API</title><link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css"></head><body><div id="swagger-ui"></div><script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script><script>SwaggerUIBundle({url:'/docs/openapi.yaml',dom_id:'#swagger-ui',deepLinking:true,persistAuthorization:true});</script></body></html>
HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
});
