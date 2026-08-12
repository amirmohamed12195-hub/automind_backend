<?php

namespace App\Http\Controllers;

use App\Enums\DiagnosticStatus;
use App\Enums\DiagnosticStep;
use App\Jobs\AnalyzeDiagnosticSession;
use App\Jobs\RefreshServiceEstimate;
use App\Models\AiRun;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BillingEvent;
use App\Models\BillingPlan;
use App\Models\CreditLedgerEntry;
use App\Models\CurrencyRate;
use App\Models\DiagnosticSession;
use App\Models\LaborRateSource;
use App\Models\MaintenanceServiceDefinition;
use App\Models\Mechanic;
use App\Models\MechanicSpecialty;
use App\Models\PlatformSetting;
use App\Models\StoreProduct;
use App\Models\StorePurchase;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Services\Billing\ReportEntitlementService;
use App\Services\Diagnostics\DiagnosticStateMachine;
use App\Services\Notifications\UserNotificationService;
use App\Services\PlatformSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminDashboardController
{
    public function index(PlatformSettings $settings): View
    {
        if (! Schema::hasTable('users')) {
            return view('admin', $this->emptyDashboard($settings));
        }

        $users = User::query()->withTrashed()->withCount(['vehicles', 'diagnostics', 'appointments'])
            ->latest()->limit(50)->get();
        $vehicles = Vehicle::query()->withTrashed()->with([
            'user' => fn ($query) => $query->withTrashed(),
        ])->withCount('diagnostics')->latest()->limit(50)->get();
        $diagnostics = DiagnosticSession::query()->with(['user', 'vehicle', 'report.translations'])
            ->withCount(['media', 'obdSnapshots'])->latest()->limit(50)->get();
        $mechanics = Mechanic::query()->with('specialties')->withCount('appointments')->latest()->limit(50)->get();
        $appointments = Appointment::query()->with(['user', 'vehicle', 'mechanic'])->latest('requested_start_at')->limit(50)->get();
        $aiRuns = AiRun::query()->with('session.user')->latest()->limit(50)->get();
        $notifications = UserNotification::query()->with('user')->latest()->limit(50)->get();
        $auditLogs = AuditLog::query()->latest()->limit(75)->get();

        $today = now()->startOfDay();
        $weekStart = now()->subDays(6)->startOfDay();
        $completedAiRuns = AiRun::query()->where('created_at', '>=', $weekStart)->where('status', 'completed')->count();
        $totalAiRuns = AiRun::query()->where('created_at', '>=', $weekStart)->count();
        $weeklyDiagnostics = collect(range(6, 0))->map(function (int $daysAgo): array {
            $day = now()->subDays($daysAgo);

            return [
                'label' => $day->format('D'),
                'count' => DiagnosticSession::query()->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])->count(),
            ];
        });

        $overview = [
            'users' => User::query()->count(),
            'activeUsers' => User::query()->whereNull('suspended_at')->where('last_login_at', '>=', $weekStart)->count(),
            'vehicles' => Vehicle::query()->count(),
            'diagnostics' => DiagnosticSession::query()->count(),
            'diagnosticsToday' => DiagnosticSession::query()->where('created_at', '>=', $today)->count(),
            'aiSuccessRate' => $totalAiRuns > 0 ? round(($completedAiRuns / $totalAiRuns) * 100, 1) : 100.0,
            'verifiedMechanics' => Mechanic::query()->where('verified', true)->where('active', true)->count(),
            'pendingAppointments' => Appointment::query()->whereIn('status', ['requested', 'confirmed'])->count(),
            'failedAiRuns' => AiRun::query()->where('status', 'failed')->count(),
            'suspendedUsers' => User::query()->whereNotNull('suspended_at')->count(),
        ];

        return view('admin', [
            'overview' => $overview,
            'weeklyDiagnostics' => $weeklyDiagnostics,
            'users' => $users,
            'vehicles' => $vehicles,
            'diagnostics' => $diagnostics,
            'mechanics' => $mechanics,
            'appointments' => $appointments,
            'aiRuns' => $aiRuns,
            'notifications' => $notifications,
            'auditLogs' => $auditLogs,
            'mechanicSpecialties' => MechanicSpecialty::query()->orderBy('name_en')->get(),
            'vehicleMakes' => VehicleMake::query()->withCount('models')->orderBy('name_en')->get(),
            'vehicleModels' => VehicleModel::query()->with('make')->orderBy('name_en')->limit(150)->get(),
            'maintenanceServices' => MaintenanceServiceDefinition::query()->orderBy('code')->get(),
            'currencyRates' => CurrencyRate::query()->latest('effective_at')->limit(30)->get(),
            'laborRates' => LaborRateSource::query()->latest('observed_at')->limit(30)->get(),
            'platformSettings' => $settings->all(),
            'dataInventory' => $this->dataInventory(),
            ...$this->billingData(),
        ]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users')->ignore($user->id)],
            'locale' => ['required', Rule::in(['en', 'ar'])],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'city' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'is_admin' => ['required', 'boolean'],
            'admin_role' => ['nullable', Rule::in(['SUPER_ADMIN', 'BILLING_ADMIN', 'SUPPORT_AGENT', 'ANALYST', 'AUDITOR'])],
        ]);
        $before = $user->toArray();
        $user->forceFill([
            ...$data,
            'email' => mb_strtolower($data['email']),
            'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
            'currency' => strtoupper($data['currency']),
            'admin_role' => $data['is_admin'] ? ($data['admin_role'] ?: 'SUPER_ADMIN') : null,
        ])->save();
        $this->audit($request, 'admin.web.user.updated', $user, ['before' => $before, 'after' => $user->fresh()->toArray()]);

        return $this->done('users', 'User profile updated.');
    }

    public function suspendUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'suspended' => ['required', 'boolean'],
            'reason' => ['nullable', 'required_if:suspended,1', 'string', 'max:500'],
        ]);
        $suspended = (bool) $data['suspended'];
        $user->forceFill([
            'suspended_at' => $suspended ? now() : null,
            'suspension_reason' => $suspended ? $data['reason'] : null,
        ])->save();
        if ($suspended) {
            $user->tokens()->delete();
            DB::table('device_tokens')->where('user_id', $user->id)->update(['enabled' => false, 'updated_at' => now()]);
        }
        $this->audit($request, $suspended ? 'admin.web.user.suspended' : 'admin.web.user.reactivated', $user, ['reason' => $data['reason'] ?? null]);

        return $this->done('users', $suspended ? 'User suspended and active sessions revoked.' : 'User account reactivated.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse
    {
        $user->tokens()->delete();
        DB::table('device_tokens')->where('user_id', $user->id)->update(['enabled' => false, 'updated_at' => now()]);
        $this->audit($request, 'admin.web.user.deleted', $user);
        $user->delete();

        return $this->done('users', 'User account moved to deleted accounts.');
    }

    public function restoreUser(Request $request, string $user): RedirectResponse
    {
        $record = User::withTrashed()->findOrFail($user);
        $record->restore();
        $record->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        $this->audit($request, 'admin.web.user.restored', $record);

        return $this->done('users', 'User account restored.');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:120'], 'plate_number' => ['nullable', 'string', 'max:64'],
            'mileage_km' => ['required', 'integer', 'min:0'], 'health_score' => ['required', 'integer', 'between:0,100'],
            'year' => ['required', 'integer', 'between:1886,'.(now()->year + 1)], 'brand' => ['required', 'string', 'max:120'],
            'model' => ['required', 'string', 'max:120'],
        ]);
        $before = $vehicle->toArray();
        $vehicle->update($data);
        $this->audit($request, 'admin.web.vehicle.updated', $vehicle, ['before' => $before, 'after' => $vehicle->fresh()->toArray()]);

        return $this->done('vehicles', 'Vehicle updated.');
    }

    public function destroyVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        if ($vehicle->diagnostics()->whereIn('status', ['queued', 'analyzing'])->exists()) {
            throw ValidationException::withMessages(['vehicle' => 'A vehicle with an active diagnostic cannot be deleted.']);
        }
        $this->audit($request, 'admin.web.vehicle.deleted', $vehicle);
        $vehicle->delete();

        return $this->done('vehicles', 'Vehicle moved to deleted records.');
    }

    public function retryDiagnostic(Request $request, DiagnosticSession $diagnosis): RedirectResponse
    {
        if (! in_array($diagnosis->status, ['failed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['diagnosis' => 'Only failed or cancelled diagnostics can be retried.']);
        }
        $updated = DiagnosticSession::query()->whereKey($diagnosis->id)->whereIn('status', ['failed', 'cancelled'])->update([
            'status' => DiagnosticStatus::Queued->value, 'current_step' => DiagnosticStep::PreparingData->value,
            'progress_percentage' => 0, 'error_code' => null, 'safe_error_message' => null,
            'failed_at' => null, 'cancelled_at' => null, 'updated_at' => now(),
        ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['diagnosis' => 'The diagnostic status changed. Refresh and try again.']);
        }
        AnalyzeDiagnosticSession::dispatch($diagnosis->id)->afterCommit();
        $this->audit($request, 'admin.web.diagnostic.retried', $diagnosis);

        return $this->done('diagnostics', 'Diagnostic queued for retry.');
    }

    public function cancelDiagnostic(Request $request, DiagnosticSession $diagnosis, DiagnosticStateMachine $stateMachine, ReportEntitlementService $entitlements): RedirectResponse
    {
        if (in_array($diagnosis->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['diagnosis' => 'This diagnostic cannot be cancelled.']);
        }
        DB::transaction(function () use ($diagnosis, $stateMachine, $entitlements): void {
            $cancelled = $stateMachine->transition($diagnosis->fresh(), DiagnosticStatus::Cancelled, ['cancelled_at' => now()]);
            $entitlements->release($cancelled);
        });
        $this->audit($request, 'admin.web.diagnostic.cancelled', $diagnosis);

        return $this->done('diagnostics', 'Diagnostic cancelled.');
    }

    public function destroyDiagnostic(Request $request, DiagnosticSession $diagnosis): RedirectResponse
    {
        if (! in_array($diagnosis->status, ['draft', 'failed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['diagnosis' => 'Only draft, failed, or cancelled diagnostics can be deleted.']);
        }
        $this->audit($request, 'admin.web.diagnostic.deleted', $diagnosis);
        $diagnosis->delete();

        return $this->done('diagnostics', 'Diagnostic record deleted.');
    }

    public function storeMechanic(Request $request): RedirectResponse
    {
        $mechanic = Mechanic::query()->create($this->mechanicData($request, true));
        $this->syncSpecialties($request, $mechanic);
        $this->audit($request, 'admin.web.mechanic.created', $mechanic);

        return $this->done('mechanics', 'Mechanic created.');
    }

    public function updateMechanic(Request $request, Mechanic $mechanic): RedirectResponse
    {
        $mechanic->update($this->mechanicData($request, false));
        $this->syncSpecialties($request, $mechanic);
        $this->audit($request, 'admin.web.mechanic.updated', $mechanic);

        return $this->done('mechanics', 'Mechanic updated.');
    }

    public function updateAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['requested', 'confirmed', 'inProgress', 'completed', 'cancelled'])],
            'mechanic_note' => ['nullable', 'string', 'max:2000'], 'cancellation_reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:1000'],
        ]);
        $updates = $data;
        $updates['completed_at'] = $data['status'] === 'completed' ? ($appointment->completed_at ?? now()) : null;
        $updates['cancelled_at'] = $data['status'] === 'cancelled' ? ($appointment->cancelled_at ?? now()) : null;
        if ($data['status'] !== 'cancelled') {
            $updates['cancellation_reason'] = null;
        }
        $appointment->update($updates);
        $this->audit($request, 'admin.web.appointment.updated', $appointment, ['status' => $data['status']]);

        return $this->done('appointments', 'Appointment updated.');
    }

    public function broadcast(Request $request, UserNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['all', 'active', 'user'])], 'user_id' => ['nullable', 'required_if:audience,user', 'ulid', 'exists:users,id'],
            'title_en' => ['required', 'string', 'max:160'], 'title_ar' => ['required', 'string', 'max:160'],
            'body_en' => ['required', 'string', 'max:1000'], 'body_ar' => ['required', 'string', 'max:1000'],
        ]);
        $query = User::query()->when($data['audience'] === 'active', fn ($q) => $q->whereNull('suspended_at'))
            ->when($data['audience'] === 'user', fn ($q) => $q->whereKey($data['user_id']));
        $recipientCount = (clone $query)->count();
        $query->select(['id', 'locale'])->chunkById(250, function ($users) use ($notifications, $data): void {
            foreach ($users as $user) {
                $notifications->send($user, 'admin_broadcast', $data['title_en'], $data['title_ar'], $data['body_en'], $data['body_ar']);
            }
        });
        $this->audit($request, 'admin.web.notification.broadcast', null, ['audience' => $data['audience'], 'recipientCount' => $recipientCount]);

        return $this->done('notifications', "Broadcast created for {$recipientCount} users.");
    }

    public function retryAiRun(Request $request, AiRun $run): RedirectResponse
    {
        if ($run->status !== 'failed') {
            throw ValidationException::withMessages(['run' => 'Only failed AI runs can be retried.']);
        }
        $session = DiagnosticSession::query()->findOrFail($run->diagnostic_session_id);
        if ($run->task_type === 'price_research' && $session->report) {
            RefreshServiceEstimate::dispatch($session->report->id)->afterCommit();
        } else {
            $updated = DiagnosticSession::query()->whereKey($session->id)->where('status', 'failed')->update([
                'status' => 'queued', 'current_step' => DiagnosticStep::PreparingData->value,
                'progress_percentage' => 0, 'error_code' => null, 'safe_error_message' => null, 'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['run' => 'The parent diagnostic is not retryable.']);
            }
            AnalyzeDiagnosticSession::dispatch($session->id)->afterCommit();
        }
        $this->audit($request, 'admin.web.ai_run.retried', $run);

        return $this->done('ai', 'AI work queued for retry.');
    }

    public function updateSettings(Request $request, PlatformSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.registration_enabled' => ['required', 'boolean'],
            'settings.diagnostics_enabled' => ['required', 'boolean'],
            'settings.appointments_enabled' => ['required', 'boolean'],
            'settings.maintenance_banner' => ['nullable', 'string', 'max:500'],
            'settings.support_email' => ['required', 'email:rfc', 'max:255'],
            'settings.default_country' => ['required', 'string', 'size:2', 'alpha'],
            'settings.default_currency' => ['required', 'string', 'size:3', 'alpha'],
            'settings.default_locale' => ['required', Rule::in(['en', 'ar'])],
        ])['settings'];
        $definitions = $settings->definitions();
        foreach ($definitions as $key => $definition) {
            $value = $data[$key] ?? $definition['value'];
            if (in_array($key, ['default_country', 'default_currency'], true)) {
                $value = strtoupper((string) $value);
            }
            PlatformSetting::query()->updateOrCreate(['key' => $key], [
                'group' => $definition['group'], 'label' => $definition['label'], 'type' => $definition['type'],
                'value' => $value, 'description' => $definition['description'],
                'updated_by_admin' => (string) $request->session()->get('automind_admin_username', 'admin'),
            ]);
        }
        $this->audit($request, 'admin.web.settings.updated', null, ['keys' => array_keys($definitions)]);

        return $this->done('settings', 'Platform settings saved and are now active.');
    }

    public function storeMake(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:80', 'unique:vehicle_makes,code'],
            'name_en' => ['required', 'string', 'max:120'], 'name_ar' => ['required', 'string', 'max:120'], 'active' => ['required', 'boolean'],
        ]);
        $data['code'] = strtolower($data['code']);
        $make = VehicleMake::query()->create($data);
        $this->audit($request, 'admin.web.vehicle_make.created', $make);

        return $this->done('catalog', 'Vehicle make created.');
    }

    public function updateMake(Request $request, VehicleMake $make): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:120'], 'name_ar' => ['required', 'string', 'max:120'], 'active' => ['required', 'boolean'],
        ]);
        $make->update($data);
        $this->audit($request, 'admin.web.vehicle_make.updated', $make);

        return $this->done('catalog', 'Vehicle make updated.');
    }

    public function storeModel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'make_id' => ['required', 'ulid', 'exists:vehicle_makes,id'],
            'code' => ['required', 'alpha_dash', 'max:100', Rule::unique('vehicle_models')->where('make_id', $request->input('make_id'))],
            'name_en' => ['required', 'string', 'max:120'], 'name_ar' => ['required', 'string', 'max:120'],
            'start_year' => ['nullable', 'integer', 'between:1886,2200'], 'end_year' => ['nullable', 'integer', 'gte:start_year'],
            'active' => ['required', 'boolean'],
        ]);
        $data['code'] = strtolower($data['code']);
        $model = VehicleModel::query()->create($data);
        $this->audit($request, 'admin.web.vehicle_model.created', $model);

        return $this->done('catalog', 'Vehicle model created.');
    }

    public function updateModel(Request $request, VehicleModel $model): RedirectResponse
    {
        $data = $request->validate([
            'make_id' => ['required', 'ulid', 'exists:vehicle_makes,id'],
            'code' => ['required', 'alpha_dash', 'max:100', Rule::unique('vehicle_models')->where('make_id', $request->input('make_id'))->ignore($model->id)],
            'name_en' => ['required', 'string', 'max:120'], 'name_ar' => ['required', 'string', 'max:120'],
            'start_year' => ['nullable', 'integer', 'between:1886,2200'], 'end_year' => ['nullable', 'integer', 'gte:start_year'],
            'active' => ['required', 'boolean'],
        ]);
        $data['code'] = strtolower($data['code']);
        $model->update($data);
        $this->audit($request, 'admin.web.vehicle_model.updated', $model);

        return $this->done('catalog', 'Vehicle model updated.');
    }

    public function storeMaintenanceService(Request $request): RedirectResponse
    {
        $service = MaintenanceServiceDefinition::query()->create($this->serviceData($request, true));
        $this->audit($request, 'admin.web.maintenance_service.created', $service);

        return $this->done('catalog', 'Maintenance service created.');
    }

    public function updateMaintenanceService(Request $request, MaintenanceServiceDefinition $service): RedirectResponse
    {
        $service->update($this->serviceData($request, false, $service));
        $this->audit($request, 'admin.web.maintenance_service.updated', $service);

        return $this->done('catalog', 'Maintenance service updated.');
    }

    public function storeCurrencyRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_currency' => ['required', 'string', 'size:3', 'alpha', 'different:quote_currency'],
            'quote_currency' => ['required', 'string', 'size:3', 'alpha'], 'rate' => ['required', 'numeric', 'gt:0'],
            'provider' => ['required', 'string', 'max:120'], 'effective_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $data['base_currency'] = strtoupper($data['base_currency']);
        $data['quote_currency'] = strtoupper($data['quote_currency']);
        $rate = CurrencyRate::query()->create($data);
        $this->audit($request, 'admin.web.currency_rate.created', $rate);

        return $this->done('catalog', 'Currency rate added.');
    }

    public function storeLaborRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:2', 'alpha'], 'city' => ['nullable', 'string', 'max:120'],
            'service_category' => ['required', 'string', 'max:120'], 'hourly_low' => ['required', 'numeric', 'gt:0'],
            'hourly_typical' => ['required', 'numeric', 'gte:hourly_low'], 'hourly_high' => ['required', 'numeric', 'gte:hourly_typical'],
            'hours_low' => ['nullable', 'numeric', 'gt:0'], 'hours_typical' => ['nullable', 'numeric', 'gte:hours_low'],
            'hours_high' => ['nullable', 'numeric', 'gte:hours_typical'], 'currency' => ['required', 'string', 'size:3', 'alpha'],
            'observed_at' => ['required', 'date', 'before_or_equal:now'], 'expires_at' => ['nullable', 'date', 'after:observed_at'],
        ]);
        $data['country_code'] = strtoupper($data['country_code']);
        $data['currency'] = strtoupper($data['currency']);
        $rate = LaborRateSource::query()->create($data);
        $this->audit($request, 'admin.web.labor_rate.created', $rate);

        return $this->done('catalog', 'Labor rate source added.');
    }

    /** @return array<string, mixed> */
    private function billingData(): array
    {
        if (! Schema::hasTable('billing_plans')) {
            return [
                'billingOverview' => ['activeSubscriptions' => 0, 'graceOrRetry' => 0, 'creditsOutstanding' => 0, 'eventsNeedingAttention' => 0],
                'billingPlans' => collect(), 'billingProducts' => collect(), 'billingTransactions' => collect(), 'billingEvents' => collect(),
            ];
        }

        return [
            'billingOverview' => [
                'activeSubscriptions' => UserEntitlement::query()->whereIn('status', ['active', 'gracePeriod', 'canceledActiveUntilExpiry'])->where('period_end', '>', now())->count(),
                'graceOrRetry' => UserEntitlement::query()->whereIn('status', ['gracePeriod', 'billingRetry'])->count(),
                'creditsOutstanding' => (int) CreditLedgerEntry::query()->whereIn('id', CreditLedgerEntry::query()->selectRaw('MAX(id)')->groupBy('user_id'))->sum('balance_after'),
                'eventsNeedingAttention' => BillingEvent::query()->whereNotIn('processing_status', ['processed', 'ignored'])->count(),
            ],
            'billingPlans' => BillingPlan::query()->with(['localizations', 'storeProducts'])->orderBy('sort_order')->get(),
            'billingProducts' => StoreProduct::query()->with('plan')->orderBy('platform')->orderBy('product_id')->get(),
            'billingTransactions' => StorePurchase::query()->with(['user:id,email', 'storeProduct.plan'])->latest()->limit(30)->get(),
            'billingEvents' => BillingEvent::query()->latest()->limit(30)->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyDashboard(PlatformSettings $settings): array
    {
        return [
            'overview' => array_fill_keys(['users', 'activeUsers', 'vehicles', 'diagnostics', 'diagnosticsToday', 'verifiedMechanics', 'pendingAppointments', 'failedAiRuns', 'suspendedUsers'], 0) + ['aiSuccessRate' => 100.0],
            'weeklyDiagnostics' => collect(), 'users' => collect(), 'vehicles' => collect(), 'diagnostics' => collect(),
            'mechanics' => collect(), 'appointments' => collect(), 'aiRuns' => collect(), 'notifications' => collect(),
            'auditLogs' => collect(), 'mechanicSpecialties' => collect(), 'vehicleMakes' => collect(), 'vehicleModels' => collect(), 'maintenanceServices' => collect(),
            'currencyRates' => collect(), 'laborRates' => collect(), 'platformSettings' => $settings->all(), 'dataInventory' => [],
            ...$this->billingData(),
        ];
    }

    /** @return array<string, int> */
    private function dataInventory(): array
    {
        $tables = [
            'Users' => 'users', 'Vehicles' => 'vehicles', 'Diagnostics' => 'diagnostic_sessions', 'Reports' => 'diagnostic_reports',
            'Media files' => 'diagnostic_media', 'OBD snapshots' => 'obd_snapshots', 'Maintenance records' => 'vehicle_maintenance_records',
            'Reminders' => 'maintenance_reminders', 'Mechanics' => 'mechanics', 'Appointments' => 'appointments',
            'Reviews' => 'mechanic_reviews', 'Notifications' => 'notifications', 'AI runs' => 'ai_runs', 'Price searches' => 'price_searches',
            'Purchases' => 'store_purchases', 'Entitlements' => 'user_entitlements', 'Billing events' => 'billing_events', 'Audit events' => 'audit_logs',
        ];

        return collect($tables)->mapWithKeys(fn (string $table, string $label) => [
            $label => Schema::hasTable($table) ? DB::table($table)->count() : 0,
        ])->all();
    }

    /** @return array<string, mixed> */
    private function mechanicData(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'name_en' => [$required, 'string', 'max:160'], 'name_ar' => [$required, 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'], 'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => [$required, 'string', 'max:255'], 'city' => [$required, 'string', 'max:120'],
            'country_code' => [$required, 'string', 'size:2', 'alpha'], 'latitude' => [$required, 'numeric', 'between:-90,90'],
            'longitude' => [$required, 'numeric', 'between:-180,180'], 'verified' => ['required', 'boolean'], 'active' => ['required', 'boolean'],
            'specialty_codes' => ['nullable', 'array', 'max:20'], 'specialty_codes.*' => ['string', 'exists:mechanic_specialties,code'],
        ]);
        unset($data['specialty_codes']);
        if (isset($data['country_code'])) {
            $data['country_code'] = strtoupper($data['country_code']);
        }

        return $data;
    }

    private function syncSpecialties(Request $request, Mechanic $mechanic): void
    {
        if ($request->exists('specialty_codes')) {
            $mechanic->specialties()->sync(MechanicSpecialty::query()->whereIn('code', $request->input('specialty_codes', []))->pluck('id'));
        }
    }

    /** @return array<string, mixed> */
    private function serviceData(Request $request, bool $creating, ?MaintenanceServiceDefinition $service = null): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'code' => [$required, 'alpha_dash', 'max:100', Rule::unique('maintenance_service_definitions')->ignore($service?->id)],
            'name_en' => [$required, 'string', 'max:160'], 'name_ar' => [$required, 'string', 'max:160'],
            'default_month_interval' => ['nullable', 'integer', 'between:1,240'],
            'default_km_interval' => ['nullable', 'integer', 'between:1,1000000'], 'active' => ['required', 'boolean'],
        ]);
    }

    private function done(string $view, string $message): RedirectResponse
    {
        return redirect(route('admin.dashboard').'#'.$view)->with(['admin_status' => $message, 'admin_view' => $view]);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, ?Model $target, array $metadata = []): void
    {
        AuditLog::query()->create([
            'actor_user_id' => null, 'action' => $action, 'target_type' => $target !== null ? $target::class : null, 'target_id' => $target?->getKey(),
            'request_id' => (string) ($request->attributes->get('request_id') ?: Str::ulid()),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')),
            'user_agent_summary' => mb_substr((string) $request->userAgent(), 0, 255),
            'metadata_json' => ['administrator' => (string) $request->session()->get('automind_admin_username', 'admin'), ...$metadata],
        ]);
    }
}
